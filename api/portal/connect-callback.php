<?php
/**
 * OAuth Callback Handler
 *
 * GET /api/portal/connect/callback/{provider} - Handle OAuth redirect from payment provider
 *
 * This endpoint is called by the browser after the user authorizes on the provider's site.
 * It verifies the CSRF state token, exchanges the authorization code for credentials,
 * stores them in portal_companies, and shows a result page.
 */

require_once __DIR__ . '/portal-helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// This is a browser redirect, not an API call. Output HTML
header('Content-Type: text/html; charset=utf-8');

$provider = $_GET['provider'] ?? '';
if (!in_array($provider, ['stripe', 'paypal', 'square'])) {
    show_result_page(false, 'Invalid provider.');
    exit;
}

// Check for errors from the provider (user denied, etc.)
$error = $_GET['error'] ?? $_GET['error_description'] ?? '';
if (!empty($error)) {
    show_result_page(false, 'Authorization was denied: ' . $error);
    exit;
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

// Stripe Account Links flow only returns state (no code). Other providers require both.
if ($provider !== 'stripe' && (empty($code) || empty($state))) {
    show_result_page(false, 'Missing authorization code or state parameter.');
    exit;
}
if (empty($state)) {
    show_result_page(false, 'Missing state parameter.');
    exit;
}

// Verify the CSRF state token and look up the company
$stmt = $pdo->prepare(
    'SELECT os.id AS state_id, os.company_id, pc.company_name
     FROM portal_oauth_states os
     JOIN portal_companies pc ON os.company_id = pc.id
     WHERE os.state_token = ? AND os.provider = ? AND os.expires_at > NOW()
     LIMIT 1'
);
$stmt->execute([$state, $provider]);
$oauthState = $stmt->fetch();

if (!$oauthState) {
    show_result_page(false, 'Invalid or expired authorization state. Please try connecting again from Argo Books.');
    exit;
}

$companyId = $oauthState['company_id'];
$companyName = $oauthState['company_name'];
$stateId = $oauthState['state_id'];
$is_production = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production';
$callbackBase = rtrim(env('SITE_URL', 'https://argorobots.com'), '/');

$isRefresh = isset($_GET['refresh']);

try {
    switch ($provider) {
        case 'stripe':
            $callbackUrl = "$callbackBase/api/portal/connect/callback/stripe";
            $result = handle_stripe_callback($pdo, $companyId, $is_production, $isRefresh, $callbackUrl, $state);
            if ($result === 'redirect') {
                // Onboarding incomplete; keep the state token alive for the next callback
                exit;
            }
            break;
        case 'paypal':
            handle_paypal_callback($pdo, $companyId, $code, $is_production);
            break;
        case 'square':
            handle_square_callback($pdo, $companyId, $code, $is_production);
            break;
    }

    // Delete the used state token (and clean up expired ones)
    $stmt = $pdo->prepare('DELETE FROM portal_oauth_states WHERE id = ?');
    $stmt->execute([$stateId]);
    $pdo->query('DELETE FROM portal_oauth_states WHERE expires_at <= NOW()');

    show_result_page(true, ucfirst($provider) . ' has been connected successfully!', $companyName);
} catch (Exception $e) {
    // Clean up state token even on failure
    $stmt = $pdo->prepare('DELETE FROM portal_oauth_states WHERE id = ?');
    $stmt->execute([$stateId]);

    error_log("OAuth callback error ($provider): " . $e->getMessage());
    show_result_page(false, 'Failed to complete connection: ' . $e->getMessage());
}

/**
 * Handle Stripe Account Links callback.
 * Retrieves the Express account, checks onboarding status, and saves email if complete.
 * Returns 'redirect' if onboarding is incomplete and the user was redirected back to Stripe.
 */
function handle_stripe_callback(PDO $db, int $companyId, bool $is_production, bool $isRefresh, string $callbackUrl, string $state): ?string
{
    $secretKey = $is_production
        ? ($_ENV['STRIPE_LIVE_SECRET_KEY'] ?? '')
        : ($_ENV['STRIPE_SANDBOX_SECRET_KEY'] ?? '');
    \Stripe\Stripe::setApiKey($secretKey);

    // Look up the stored Stripe account ID for this company.
    $stmt = $db->prepare('SELECT stripe_account_id, stripe_pending_account_id FROM portal_companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $row = $stmt->fetch();

    $stripeAccountId = !empty($row['stripe_account_id'])
        ? $row['stripe_account_id']
        : ($row['stripe_pending_account_id'] ?? '');
    if (empty($stripeAccountId)) {
        throw new Exception('No Stripe account found. Please initiate the connection again from Argo Books.');
    }

    // Retrieve the Express account to check onboarding status
    $account = \Stripe\Account::retrieve($stripeAccountId);

    if (!$account->details_submitted || $isRefresh) {
        // Onboarding incomplete or user requested refresh, send them back to Stripe
        $accountLink = \Stripe\AccountLink::create([
            'account' => $stripeAccountId,
            'return_url' => $callbackUrl . '?state=' . $state,
            'refresh_url' => $callbackUrl . '?state=' . $state . '&refresh=1',
            'type' => 'account_onboarding',
        ]);
        header('Location: ' . $accountLink->url);
        return 'redirect';
    }

    // Onboarding complete: promote the account to "connected" and save the
    // account email (try multiple fields for Express accounts)
    $acctData = $account->toArray();
    $email = $acctData['email']
        ?? $acctData['business_profile']['support_email']
        ?? $acctData['individual']['email']
        ?? null;
    $stmt = $db->prepare(
        'UPDATE portal_companies
         SET stripe_account_id = ?, stripe_pending_account_id = NULL, stripe_email = ?, updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([$stripeAccountId, $email, $companyId]);

    return null;
}

/**
 * PayPal portal Connect is not currently supported. The flow can't onboard
 * Business merchants because PayPal's "Log in with PayPal" userinfo endpoint
 * refuses tokens issued to Business accounts (confirmed against all three
 * documented endpoint variants). Proper merchant onboarding requires PayPal
 * Partner Referrals API, which is gated behind PayPal Platforms &
 * Marketplaces enrollment. Until that's in place, the desktop app hides the
 * PayPal Connect button; this stub catches any stale URL that still arrives
 * (cached emails, old desktop builds, manual nav) and shows a clear message.
 */
function handle_paypal_callback(PDO $db, int $companyId, string $code, bool $is_production): void
{
    throw new Exception('PayPal portal Connect is not currently supported. Use Stripe or Square instead.');
}

/**
 * Handle Square OAuth callback.
 * Exchanges the authorization code for access token, merchant ID, and location.
 */
function handle_square_callback(PDO $db, int $companyId, string $code, bool $is_production): void
{
    $appId = $is_production
        ? ($_ENV['SQUARE_LIVE_APP_ID'] ?? '')
        : ($_ENV['SQUARE_SANDBOX_APP_ID'] ?? '');
    $appSecret = $is_production
        ? ($_ENV['SQUARE_LIVE_APP_SECRET'] ?? '')
        : ($_ENV['SQUARE_SANDBOX_APP_SECRET'] ?? '');
    $apiBase = $is_production
        ? 'https://connect.squareup.com'
        : 'https://connect.squareupsandbox.com';

    // Build the redirect_uri (must match the one sent in the authorize request)
    $callbackBase = rtrim(env('SITE_URL', 'https://argorobots.com'), '/');
    $redirectUri = "$callbackBase/api/portal/connect/callback/square";

    // Exchange authorization code for access token
    $ch = curl_init("$apiBase/oauth2/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]),
        CURLOPT_HTTPHEADER => [
            'Square-Version: 2026-01-22',
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        $errorMsg = $error['errors'][0]['detail'] ?? ($error['message'] ?? 'Square token exchange failed.');
        throw new Exception($errorMsg);
    }

    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'] ?? '';
    $merchantId = $tokenData['merchant_id'] ?? '';

    if (empty($accessToken) || empty($merchantId)) {
        throw new Exception('Missing access token or merchant ID from Square.');
    }

    // Retrieve primary location and business email
    $email = null;
    $locationId = null;

    $ch = curl_init("$apiBase/v2/locations");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Square-Version: 2026-01-22',
            "Authorization: Bearer $accessToken",
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200) {
        $locationsData = json_decode($response, true);
        $locations = $locationsData['locations'] ?? [];

        // Prefer the first active location
        foreach ($locations as $location) {
            if (($location['status'] ?? '') === 'ACTIVE') {
                $locationId = $location['id'];
                if (!empty($location['business_email'])) {
                    $email = $location['business_email'];
                }
                break;
            }
        }

        // Fallback to first location if none are active
        if (!$locationId && !empty($locations)) {
            $locationId = $locations[0]['id'];
            if (!$email && !empty($locations[0]['business_email'])) {
                $email = $locations[0]['business_email'];
            }
        }
    }

    // Encrypt the access token before storing
    $encryptedAccessToken = portal_encrypt($accessToken);

    // Store credentials
    $stmt = $db->prepare(
        'UPDATE portal_companies
         SET square_merchant_id = ?, square_access_token = ?, square_location_id = ?,
             square_email = ?, updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([$merchantId, $encryptedAccessToken, $locationId, $email, $companyId]);
}

/**
 * Show an HTML result page after the OAuth callback.
 * The user sees this in their browser and can close it to return to Argo Books.
 */
function show_result_page(bool $success, string $message, string $companyName = ''): void
{
    $statusClass = $success ? 'success' : 'error';
    $statusText = $success ? 'Connection Successful' : 'Connection Failed';
    $iconColor = $success ? '#22c55e' : '#ef4444';
    $iconSvg = $success
        ? '<svg width="56" height="56" viewBox="0 0 56 56" fill="none"><circle cx="28" cy="28" r="28" fill="' . $iconColor . '" opacity="0.1"/><circle cx="28" cy="28" r="20" fill="' . $iconColor . '" opacity="0.15"/><path d="M20 28.5L25.5 34L36 22" stroke="' . $iconColor . '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        : '<svg width="56" height="56" viewBox="0 0 56 56" fill="none"><circle cx="28" cy="28" r="28" fill="' . $iconColor . '" opacity="0.1"/><circle cx="28" cy="28" r="20" fill="' . $iconColor . '" opacity="0.15"/><path d="M22 22L34 34M34 22L22 34" stroke="' . $iconColor . '" stroke-width="3" stroke-linecap="round"/></svg>';

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($statusText) ?> - Argo Books</title>
    <link rel="shortcut icon" type="image/x-icon" href="/resources/images/argo-logo/argo-icon.ico">
    <link rel="stylesheet" href="/resources/styles/custom-colors.css">
    <link rel="stylesheet" href="/portal/style.css">
    <style>
        .callback-result { text-align: center; padding: 60px 20px; max-width: 480px; margin: 0 auto; }
        .callback-icon { margin-bottom: 20px; }
        .callback-title { font-size: 22px; font-weight: 600; color: var(--gray-900, #111); margin: 0; }
        .callback-message { color: var(--gray-900, #111); font-size: 15px; margin-top: 12px; line-height: 1.5; }
        .callback-hint { color: var(--gray-900, #111); font-size: 13px; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="portal-page">
        <header class="portal-header">
            <div class="portal-header-inner">
                <div class="company-info">
                    <h1 class="company-name">Payment Portal</h1>
                    <span class="portal-subtitle">Powered by Argo Books</span>
                </div>
            </div>
        </header>
        <main class="portal-main">
            <div class="callback-result">
                <div class="callback-icon"><?= $iconSvg ?></div>
                <h2 class="callback-title"><?= htmlspecialchars($statusText) ?></h2>
                <p class="callback-message"><?= htmlspecialchars($message) ?></p>
                <?php if ($success): ?>
                <p class="callback-hint">You can close this window and return to Argo Books. It may take a few seconds for the app to update.</p>
                <?php else: ?>
                <p class="callback-hint">You can close this window and try again from Argo Books.</p>
                <?php endif; ?>
            </div>
        </main>
        <footer class="portal-footer">
            <p>Secure payments powered by <a href="https://argorobots.com" target="_blank" rel="noopener">Argo Books</a></p>
        </footer>
    </div>
</body>
</html>
    <?php
}
