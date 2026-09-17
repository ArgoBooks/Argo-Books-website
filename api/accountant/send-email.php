<?php
/**
 * Accountant Pack Email API Endpoint
 *
 * Emails a business's year-end pack (report PDFs, a transactions spreadsheet and a zip of
 * receipts) from the Argo Books desktop client to their accountant. The message is written here
 * rather than by the client, so every accountant sees the same wording and the Argo Books link
 * that makes these packs a way for accountants to find the app.
 *
 * Open to the free tier like purchase order email: a license key or a device ID authenticates.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../portal/portal-helper.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

header('Content-Type: application/json; charset=utf-8');
set_portal_headers();

// Raw bytes across all attachments. The client keeps packs under 7 MB, and post_max_size (10M)
// has to hold them base64 encoded, which grows them by a third.
const ACCOUNTANT_PACK_MAX_BYTES = 7500000;
const ACCOUNTANT_PACK_MAX_FILES = 10;
const ACCOUNTANT_PACK_TYPES = [
    'pdf' => 'application/pdf',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'zip' => 'application/zip',
];
const ACCOUNTANT_PACK_LINK = 'https://argorobots.com/downloads/?source=loop-accountant-email';

function accountant_pack_fail(int $status, string $code, string $message): never
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'errorCode' => $code,
        'timestamp' => date('c'),
    ]);
    exit;
}

function accountant_pack_clean(string $value, int $maxLength): string
{
    $value = preg_replace('/[[:cntrl:]]+/', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $maxLength);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    accountant_pack_fail(405, 'METHOD_NOT_ALLOWED', 'Method not allowed. Only POST requests are accepted.');
}

$license = authenticate_license_request();
$deviceHash = authenticate_device_request();

if (!$license && !$deviceHash) {
    accountant_pack_fail(401, 'UNAUTHORIZED', 'Authentication required. Please provide a license key or device ID.');
}

// A business sends a pack once or twice a year, so these caps only need to stop abuse.
if ($license) {
    $rateLimitKey = 'accountant_email_' . ($license['license_key_hash'] ?? get_client_ip());
    $rateLimitMax = 30;
} else {
    $rateLimitKey = 'accountant_email_dev_' . $deviceHash;
    $rateLimitMax = 10;
}

if (is_rate_limited($rateLimitKey, $rateLimitMax, 3600, 'accountant_email')) {
    accountant_pack_fail(429, 'RATE_LIMITED', 'Email rate limit exceeded. Please try again later.');
}
record_rate_limit_attempt($rateLimitKey, 'accountant_email', 3600);

$input = file_get_contents('php://input');

// PHP drops a body larger than post_max_size, which would otherwise read as invalid JSON.
if ($input === '' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    accountant_pack_fail(413, 'PAYLOAD_TOO_LARGE', 'This pack is too large to email. Save it as a zip instead.');
}

$data = json_decode($input, true);
if (!is_array($data)) {
    accountant_pack_fail(400, 'INVALID_JSON', 'Invalid JSON input.');
}

$to = trim((string) ($data['to'] ?? ''));
$toName = accountant_pack_clean((string) ($data['toName'] ?? ''), 100);
$replyTo = trim((string) ($data['replyTo'] ?? ''));
$companyName = accountant_pack_clean((string) ($data['companyName'] ?? ''), 120);
$period = accountant_pack_clean((string) ($data['period'] ?? ''), 40);
$note = mb_substr(trim((string) ($data['note'] ?? '')), 0, 2000);
$receiptsOmitted = !empty($data['receiptsOmitted']);
$attachments = $data['attachments'] ?? null;

if ($to === '' || $companyName === '' || $period === '' || !is_array($attachments) || $attachments === []) {
    accountant_pack_fail(400, 'MISSING_FIELDS', 'Missing required fields: to, companyName, period and attachments.');
}

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    accountant_pack_fail(400, 'INVALID_EMAIL', 'Invalid recipient email address.');
}

$domain = substr(strrchr($to, '@'), 1);
if (!checkdnsrr($domain, 'MX')) {
    accountant_pack_fail(400, 'INVALID_DOMAIN', 'No mail servers were found for ' . $domain . '.');
}

if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
    accountant_pack_fail(400, 'INVALID_EMAIL', 'Invalid reply-to email address.');
}

if (count($attachments) > ACCOUNTANT_PACK_MAX_FILES) {
    accountant_pack_fail(400, 'TOO_MANY_FILES', 'Too many attachments.');
}

$files = [];
$totalBytes = 0;
foreach ($attachments as $attachment) {
    if (!is_array($attachment)) {
        accountant_pack_fail(400, 'INVALID_ATTACHMENT', 'An attachment could not be read.');
    }

    $name = preg_replace('/[^A-Za-z0-9 ._()-]/', '_', basename((string) ($attachment['filename'] ?? ''))) ?? '';
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($name === '' || !isset(ACCOUNTANT_PACK_TYPES[$extension])) {
        accountant_pack_fail(400, 'INVALID_ATTACHMENT', 'Attachments must be PDF, Excel or zip files.');
    }

    $bytes = base64_decode((string) ($attachment['data'] ?? ''), true);
    if ($bytes === false || $bytes === '') {
        accountant_pack_fail(400, 'INVALID_ATTACHMENT', 'An attachment could not be read.');
    }

    $totalBytes += strlen($bytes);
    if ($totalBytes > ACCOUNTANT_PACK_MAX_BYTES) {
        accountant_pack_fail(413, 'PAYLOAD_TOO_LARGE', 'This pack is too large to email. Save it as a zip instead.');
    }

    $files[] = ['data' => $bytes, 'name' => $name, 'mime' => ACCOUNTANT_PACK_TYPES[$extension]];
}

// Attachments only travel over SMTP. Without it the mail() fallback would deliver the message
// with nothing attached, which is worse than failing.
if (!create_smtp_mailer()) {
    accountant_pack_fail(503, 'EMAIL_UNAVAILABLE', 'Email is not available right now. Save the pack as a zip instead.');
}

$defaultFrom = env('INVOICE_DEFAULT_FROM_EMAIL', '') ?: 'noreply@argorobots.com';
$sender = pin_client_email_sender(['fromName' => $companyName, 'replyTo' => $replyTo], $defaultFrom, 'Argo Books');

$escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$subject = $companyName . ' books for ' . $period;
$greeting = $toName !== '' ? 'Hi ' . $toName . ',' : 'Hi,';
$intro = $companyName . ' has sent you their books for ' . $period . '.';
$omitted = 'The receipts were too large to email. Ask ' . $companyName . ' to share the full pack.';
$replyLine = 'Reply to this email to reach ' . $companyName . '.';

$fileListHtml = '';
$fileListText = '';
foreach ($files as $file) {
    $fileListHtml .= '<li>' . $escape($file['name']) . '</li>';
    $fileListText .= '- ' . $file['name'] . PHP_EOL;
}

$html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#1f2937;max-width:560px;">'
    . '<p>' . $escape($greeting) . '</p>'
    . '<p>' . $escape($intro) . '</p>'
    . ($note !== ''
        ? '<p style="white-space:pre-line;border-left:3px solid #e5e7eb;padding-left:12px;color:#374151;">' . $escape($note) . '</p>'
        : '')
    . '<p>Attached:</p><ul>' . $fileListHtml . '</ul>'
    . ($receiptsOmitted ? '<p>' . $escape($omitted) . '</p>' : '')
    . ($sender['replyTo'] !== null ? '<p>' . $escape($replyLine) . '</p>' : '')
    . '<p style="margin-top:28px;font-size:12px;color:#8a8a8a;">Prepared in <a href="' . $escape(ACCOUNTANT_PACK_LINK)
    . '" style="color:#8a8a8a;text-decoration:underline;">Argo Books</a>, free accounting software for small businesses</p>'
    . '</div>';

$text = $greeting . PHP_EOL . PHP_EOL
    . $intro . PHP_EOL . PHP_EOL
    . ($note !== '' ? $note . PHP_EOL . PHP_EOL : '')
    . 'Attached:' . PHP_EOL . $fileListText . PHP_EOL
    . ($receiptsOmitted ? $omitted . PHP_EOL . PHP_EOL : '')
    . ($sender['replyTo'] !== null ? $replyLine . PHP_EOL . PHP_EOL : '')
    . 'Prepared in Argo Books, free accounting software for small businesses: ' . ACCOUNTANT_PACK_LINK;

$result = argo_send_html_email($to, $subject, $html, [
    'toName' => $toName,
    'fromEmail' => $sender['fromEmail'],
    'fromName' => $sender['fromName'],
    'replyTo' => $sender['replyTo'],
    'textBody' => $text,
    'attachments' => $files,
]);

if (!$result['success']) {
    error_log('Accountant pack email failed for ' . $to . ': ' . ($result['error'] ?? 'unknown error'));
    accountant_pack_fail(500, 'SEND_FAILED', 'Failed to send the email. Please try again, or save the pack as a zip.');
}

echo json_encode([
    'success' => true,
    'message' => 'Email sent successfully.',
    'timestamp' => date('c'),
]);
