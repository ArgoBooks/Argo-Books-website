<?php
/**
 * Purchase Order Email API Endpoint
 *
 * Handles sending purchase order emails (PDF attached) from the Argo Books desktop client.
 * Unlike invoice email, this endpoint is available on the free tier: requests authenticated
 * with either a license key (premium) OR a device ID (free) are accepted.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../portal/portal-helper.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

header('Content-Type: application/json; charset=utf-8');
set_portal_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.',
        'messageId' => null,
        'timestamp' => date('c')
    ]);
    exit;
}

// Authenticate: prefer license, fall back to device ID. Either is enough.
$license = authenticate_license_request();
$deviceHash = authenticate_device_request();

if (!$license && !$deviceHash) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please provide a license key or device ID.',
        'messageId' => null,
        'errorCode' => 'UNAUTHORIZED',
        'timestamp' => date('c')
    ]);
    exit;
}

// Rate limit. Premium users get a higher cap; free users get a tighter one to
// discourage abuse without penalising real small-business usage. Both windows
// are 1 hour.
if ($license) {
    $rateLimitName = 'purchase_order_email';
    $rateLimitKey = 'po_email_' . ($license['license_key_hash'] ?? get_client_ip());
} else {
    $rateLimitName = 'purchase_order_email_device';
    $rateLimitKey = 'po_email_dev_' . $deviceHash;
}

if (rate_limit_hit($rateLimitName, $rateLimitKey, 'purchase_order_email')) {
    http_response_code(429);
    header('Retry-After: ' . rate_limit_window($rateLimitName));
    echo json_encode([
        'success' => false,
        'message' => 'Email rate limit exceeded. Please try again in ' . rate_limit_wait_phrase($rateLimitName) . '.',
        'messageId' => null,
        'errorCode' => 'RATE_LIMITED',
        'timestamp' => date('c')
    ]);
    exit;
}

// The X-Device-Id of a free request is self-asserted, so rotating the header would get past
// the limit above. An IP cannot be rotated the same way, and mail sent from this domain by a
// stranger costs the sending reputation invoice delivery depends on.
if (!$license) {
    $clientIp = get_client_ip();
    if (rate_limit_hit('purchase_order_email_ip', $clientIp)) {
        http_response_code(429);
        header('Retry-After: ' . rate_limit_window('purchase_order_email_ip'));
        echo json_encode([
            'success' => false,
            'message' => 'Email rate limit exceeded. Please try again in ' . rate_limit_wait_phrase('purchase_order_email_ip') . '.',
            'messageId' => null,
            'errorCode' => 'RATE_LIMITED',
            'timestamp' => date('c')
        ]);
        exit;
    }
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON input: ' . json_last_error_msg(),
        'messageId' => null,
        'errorCode' => 'INVALID_JSON',
        'timestamp' => date('c')
    ]);
    exit;
}

// The sender is always PO_DEFAULT_FROM_EMAIL (or INVOICE_DEFAULT_FROM_EMAIL); a
// 'from' in the request only becomes the Reply-To (see pin_client_email_sender()).

// Required fields. POs are plaintext bodies, so `text` is required (no `html`).
$requiredFields = ['to', 'subject', 'text'];
$missingFields = [];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missingFields),
        'messageId' => null,
        'errorCode' => 'MISSING_FIELDS',
        'timestamp' => date('c')
    ]);
    exit;
}

if (!filter_var($data['to'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid recipient email address.',
        'messageId' => null,
        'errorCode' => 'INVALID_EMAIL',
        'timestamp' => date('c')
    ]);
    exit;
}

$domain = substr(strrchr($data['to'], '@'), 1);
if (!checkdnsrr($domain, 'MX')) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email domain - no mail servers found for ' . $domain,
        'messageId' => null,
        'errorCode' => 'INVALID_DOMAIN',
        'timestamp' => date('c')
    ]);
    exit;
}

if (!empty($data['from']) && !filter_var($data['from'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid sender email address.',
        'messageId' => null,
        'errorCode' => 'INVALID_EMAIL',
        'timestamp' => date('c')
    ]);
    exit;
}

if (!empty($data['replyTo']) && !filter_var($data['replyTo'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid reply-to email address.',
        'messageId' => null,
        'errorCode' => 'INVALID_EMAIL',
        'timestamp' => date('c')
    ]);
    exit;
}

if (!empty($data['cc']) && !filter_var($data['cc'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CC email address.',
        'messageId' => null,
        'errorCode' => 'INVALID_EMAIL',
        'timestamp' => date('c')
    ]);
    exit;
}

if (!empty($data['bcc']) && !filter_var($data['bcc'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid BCC email address.',
        'messageId' => null,
        'errorCode' => 'INVALID_EMAIL',
        'timestamp' => date('c')
    ]);
    exit;
}

require_once __DIR__ . '/purchase_order_email_sender.php';

try {
    $sender = new PurchaseOrderEmailSender();
    $result = $sender->send($data);

    http_response_code($result['success'] ? 200 : 500);
    echo json_encode($result);
} catch (\Throwable $e) {
    error_log('Purchase order email send error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal server error occurred.',
        'messageId' => null,
        'errorCode' => 'SERVER_ERROR',
        'timestamp' => date('c')
    ]);
}
