<?php
/**
 * Portal Company Registration API Endpoint
 *
 * POST /api/portal/register - Register a new company for the payment portal
 *
 * Called from Argo Books when a user sets up the payment portal feature.
 * Validates the user's license key and device ID, then generates
 * a portal API key and returns connection details.
 */

require_once __DIR__ . '/portal-helper.php';
require_once __DIR__ . '/../../license_functions.php';

set_portal_headers();
require_method(['POST']);

// Rate limiting: 10 registration attempts per 15 minutes per IP
$ip = get_client_ip();
if (rate_limit_hit('portal_register', $ip, 'register')) {
    send_rate_limited_response('portal_register');
}

// Parse request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error_response(400, 'Invalid JSON: ' . json_last_error_msg(), 'INVALID_JSON');
}

// Validate required fields
if (empty($data['deviceId'])) {
    send_error_response(400, 'Missing required field: deviceId', 'MISSING_FIELDS');
}
if (empty($data['companyName'])) {
    send_error_response(400, 'Missing required field: companyName', 'MISSING_FIELDS');
}

$licenseKey = trim($data['licenseKey'] ?? '');
$deviceId = trim($data['deviceId']);

// Validate identity: premium users use license key, free users use device ID
global $pdo;
if ($pdo === null) {
    error_log('Portal registration failed: database connection unavailable');
    send_error_response(500, 'Service temporarily unavailable. Please try again later.', 'DB_UNAVAILABLE');
}

if (!empty($licenseKey)) {
    // Premium path: validate license key
    $licenseResult = validate_license($licenseKey, $deviceId);
    if (!($licenseResult['success'] ?? false)) {
        $message = $licenseResult['message'] ?? 'Invalid license key.';
        $status = $licenseResult['status'] ?? 'invalid_key';
        send_error_response(401, $message, strtoupper($status));
    }
}
// Free path: no license key, device ID is the sole identifier (already validated as non-empty)

// Check if company already exists by owner_email
$ownerEmail = $data['ownerEmail'] ?? '';
if (!empty($ownerEmail)) {
    $stmt = $pdo->prepare('SELECT id, company_name FROM portal_companies WHERE owner_email = ? LIMIT 1');
    $stmt->execute([$ownerEmail]);
    $existing = $stmt->fetch();

    if ($existing) {
        // One portal record per owner email, so a second company on the same address lands
        // on the first one's record. Previously it kept that record's name and logo, which
        // meant a new company silently inherited the identity of an older one, including
        // one deleted locally: the server is never told about a deleted company file.
        //
        // A different name means a different company is claiming the record, so it takes
        // over the name and drops the stale logo reference. A matching name is the same
        // company re-registering (a company file that lost its key, a restored backup),
        // so nothing is touched. The logo file itself is left on disk: guessing wrong here
        // would delete a live company's branding, and an orphan file costs only space.
        $incomingName = trim((string)($data['companyName'] ?? ''));
        $claimingRecord = $incomingName !== '' && $incomingName !== $existing['company_name'];

        // Rotate API key: generate new key and store its hash
        $rotatedKey = bin2hex(random_bytes(32));
        $rotatedHash = hash('sha256', $rotatedKey);
        try {
            if ($claimingRecord) {
                // email_verified_at goes too. The claiming company has proven nothing about
                // the address, and rows predating that column were backfilled as verified,
                // so an inherited stamp can demand a confirmation code from an address
                // nobody ever confirmed. owner_email stays, since it is how the record was
                // found and is what the new owner asked for.
                $rotateStmt = $pdo->prepare(
                    'UPDATE portal_companies
                     SET api_key_hash = ?, company_name = ?, company_logo_url = NULL,
                         email_verified_at = NULL
                     WHERE id = ?');
                $rotateStmt->execute([$rotatedHash, $incomingName, $existing['id']]);
            } else {
                $rotateStmt = $pdo->prepare('UPDATE portal_companies SET api_key_hash = ? WHERE id = ?');
                $rotateStmt->execute([$rotatedHash, $existing['id']]);
            }
            if ($rotateStmt->rowCount() <= 0) {
                error_log('Portal registration failed: API key rotation UPDATE affected 0 rows for company ID ' . $existing['id']);
                send_error_response(500, 'Service temporarily unavailable. Please try again later.', 'DB_ERROR');
            }
        } catch (PDOException $e) {
            error_log('Portal registration failed: API key rotation error: ' . $e->getMessage());
            send_error_response(500, 'Service temporarily unavailable. Please try again later.', 'DB_ERROR');
        }
        send_json_response(200, [
            'success' => true,
            'api_key' => $rotatedKey,
            'company_id' => $existing['id'],
            'message' => 'Company already registered',
            'timestamp' => date('c')
        ]);
    }
}

// Generate API key (64-char hex = 256 bits of entropy)
$apiKey = bin2hex(random_bytes(32));
$apiKeyHash = hash('sha256', $apiKey);

$companyName = $data['companyName'];
$companyLogoUrl = $data['companyLogoUrl'] ?? null;
$stripeAccountId = $data['stripeAccountId'] ?? null;
$paypalMerchantId = $data['paypalMerchantId'] ?? null;
$squareMerchantId = $data['squareMerchantId'] ?? null;
$squareAccessToken = $data['squareAccessToken'] ?? null;
if ($squareAccessToken !== null && $squareAccessToken !== '') {
    $squareAccessToken = portal_encrypt($squareAccessToken);
}
$squareLocationId = $data['squareLocationId'] ?? null;
$environment = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';

try {
    $stmt = $pdo->prepare(
        'INSERT INTO portal_companies
         (api_key_hash, company_name, company_logo_url, stripe_account_id,
          paypal_merchant_id, square_merchant_id, square_access_token,
          square_location_id, owner_email, environment, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $apiKeyHash, $companyName, $companyLogoUrl, $stripeAccountId,
        $paypalMerchantId, $squareMerchantId, $squareAccessToken,
        $squareLocationId, $ownerEmail, $environment
    ]);
} catch (PDOException $e) {
    error_log('Portal registration DB error: ' . $e->getMessage());
    send_error_response(500, 'Failed to register company. Please try again.', 'DB_ERROR');
}

$companyId = (int)$pdo->lastInsertId();

// Issue an email verification code immediately. The desktop will collect it
// from the user via the verify-email confirmation modal. Until verified,
// portal_companies.email_verified_at stays NULL and refund endpoints will
// return 412. Other endpoints (invoice publishing, sync) remain available.
$emailVerificationRequired = false;
if (!empty($ownerEmail)) {
    try {
        require_once __DIR__ . '/_audit.php';
        require_once __DIR__ . '/_refund_helpers.php';
        $verifyCode = refund_generate_code();
        $verifyHash = refund_hash_code($verifyCode, (string)$companyId);
        $vstmt = $pdo->prepare("INSERT INTO email_verifications (company_id, email, purpose, code_hash, expires_at) VALUES (?, ?, 'registration', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $vstmt->execute([$companyId, $ownerEmail, $verifyHash]);
        audit_log($pdo, $companyId, 'code_sent', 'system', null, null, null, ['purpose' => 'registration']);
        refund_email_send_registration_code($ownerEmail, $verifyCode);
        $emailVerificationRequired = true;
    } catch (\Throwable $e) {
        // Don't fail registration if the verification email send fails. The user
        // can request a resend via /api/portal/account/verify-email/request.php.
        error_log('Portal registration: failed to send verification email: ' . $e->getMessage());
        $emailVerificationRequired = true;
    }
}

send_json_response(201, [
    'success' => true,
    'api_key' => $apiKey,
    'company_id' => $companyId,
    'email_verification_required' => $emailVerificationRequired,
    'message' => 'Company registered successfully',
    'timestamp' => date('c')
]);
