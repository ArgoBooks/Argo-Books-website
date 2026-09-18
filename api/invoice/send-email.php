<?php
/**
 * Invoice Email API Endpoint
 *
 * Handles sending invoice emails from the Argo Books application.
 * Configuration is loaded from environment variables.
 */

// Load environment variables and portal helper
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../portal/portal-helper.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

// Set headers
header('Content-Type: application/json; charset=utf-8');
set_portal_headers();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST requests
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

// Authenticate using license key
$license = authenticate_license_request();
if (!$license) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing license key.',
        'messageId' => null,
        'errorCode' => 'UNAUTHORIZED',
        'timestamp' => date('c')
    ]);
    exit;
}

// Rate limit: 500 emails per hour per license key (prevents abuse while allowing legitimate bulk use)
$rateLimitKey = 'email_' . ($license['license_key_hash'] ?? get_client_ip());
if (rate_limit_hit('invoice_email', $rateLimitKey)) {
    http_response_code(429);
    header('Retry-After: ' . rate_limit_window('invoice_email'));
    echo json_encode([
        'success' => false,
        'message' => 'Email rate limit exceeded. Please try again in ' . rate_limit_wait_phrase('invoice_email') . '.',
        'messageId' => null,
        'errorCode' => 'RATE_LIMITED',
        'timestamp' => date('c')
    ]);
    exit;
}

// Get JSON input
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

// The sender is always INVOICE_DEFAULT_FROM_EMAIL; a 'from' in the request only
// becomes the Reply-To (see pin_client_email_sender()).

// Validate required fields (matching Argo Books client field names)
$requiredFields = ['to', 'subject', 'html'];
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

// Validate email addresses
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

// Validate domain has MX records (mail servers)
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

// Validate optional email fields
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

// Include the email sender
require_once __DIR__ . '/invoice_email_sender.php';

try {
    $sender = new InvoiceEmailSender();
    $result = $sender->send($data);

    http_response_code($result['success'] ? 200 : 500);
    echo json_encode($result);
} catch (\Throwable $e) {
    error_log('Invoice email send error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal server error occurred.',
        'messageId' => null,
        'errorCode' => 'SERVER_ERROR',
        'timestamp' => date('c')
    ]);
}
