<?php
// profit-analyzer/email.php
//
// "Email me my results" — sends the summary + the cleaned .xlsx attached
// (Option A: nothing stored; the spreadsheet is generated at send time). All
// transactional mail goes through Resend via the SMTP relay (argo_send_html_email),
// falling back to mail() only when SMTP isn't configured.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../rate_limit_helper.php';
require_once __DIR__ . '/../smtp_mailer.php';
require_once __DIR__ . '/../email_marketing.php'; // double opt-in subscriber list
require_once __DIR__ . '/lib/analytics.php';
require_once __DIR__ . '/lib/export.php';

function pae_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    pae_fail(405, 'Use POST.');
}

// Read the JSON body once: it carries both the email and (Option A) the
// client-held NormalizedData to build the cleaned spreadsheet from.
$postedNormalized = null;
$email = trim($_POST['email'] ?? '');
$subscribe = !empty($_POST['subscribe']);
if (($raw = file_get_contents('php://input')) !== false && $raw !== '') {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        if ($email === '') {
            $email = trim($json['email'] ?? '');
        }
        if (isset($json['normalized']) && is_array($json['normalized'])) {
            $postedNormalized = $json['normalized'];
        }
        if (array_key_exists('subscribe', $json)) {
            $subscribe = !empty($json['subscribe']);
        }
    }
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    pae_fail(400, 'Please enter a valid email address.');
}

// Light anti-abuse: cap emails per IP.
$ip = get_client_ip();
if (rate_limit_hit('profit_analyzer_email', $ip)) {
    header('Retry-After: ' . rate_limit_window('profit_analyzer_email'));
    pae_fail(429, 'Too many emails from this address right now. Please try again in '
        . rate_limit_wait_phrase('profit_analyzer_email') . '.');
}

// Use the client-held analyzed data when present; otherwise the sample fixture
// (e.g. someone emailing themselves the sample-data demo).
$normalized = $postedNormalized !== null ? pa_normalize($postedNormalized) : pa_load_fixture('maple-goods');
$analytics = pa_compute_analytics($normalized);

$revenue = pa_sum($normalized['entities']['revenue'], 'amount');
$expenses = pa_sum($normalized['entities']['expenses'], 'amount');
$profit = $revenue - $expenses;
$margin = pa_pct($profit, $revenue);
$costPct = pa_pct($expenses, $revenue);

$cta = 'https://argorobots.com/downloads/?source=profit-analyzer-email&utm_source=profit-analyzer&utm_medium=email';
$subject = 'Your numbers: ' . strip_tags(html_entity_decode($analytics['headline']['title'] ?? 'your profit breakdown'));
$html = pa_email_html($analytics, $revenue, $expenses, $profit, $margin, $costPct, $cta);

// Cleaned spreadsheet attachment, generated now (not stored).
$xlsx = pa_write_xlsx(pa_build_workbook($normalized));
$xlsxName = 'cleaned-' . date('Y-m-d') . '.xlsx';
$xlsxMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

$sent = argo_send_html_email($email, $subject, $html, [
    'attachments' => [['data' => $xlsx, 'name' => $xlsxName, 'mime' => $xlsxMime]],
]);
if (!$sent['success']) {
    error_log('profit-analyzer email failed: ' . ($sent['error'] ?? 'unknown'));
    pae_fail(500, "We couldn't send the email just now. Please try again.");
}

// Optional: double opt-in to the marketing list. This is independent of the
// results email above and never blocks it. A confirmation email is sent; the
// subscriber is only added to the broadcast list after they click confirm.
$subscribeStatus = 'skipped';
if ($subscribe) {
    $subscribeStatus = create_pending_subscriber($email, 'profit_analyzer', 'newsletter', $ip);
}

$message = 'Sent. Check your inbox.';
if ($subscribeStatus === 'sent') {
    $message = "Sent. Check your inbox, we've also emailed a link to confirm your subscription.";
} elseif ($subscribeStatus === 'already_confirmed') {
    $message = "Sent. You're already subscribed, so check your inbox for your results.";
}

echo json_encode(['ok' => true, 'message' => $message, 'subscribe' => $subscribeStatus]);

/** Build the results email (table-based + inline styles for email clients). */
function pa_email_html(array $a, float $rev, float $exp, float $profit, int $margin, int $costPct, string $cta): string
{
    $file = htmlspecialchars($a['meta']['filename'] ?? 'your spreadsheet');
    $hTitle = $a['headline']['title'] ?? '';
    $hDetail = $a['headline']['detail'] ?? '';
    $sym = $a['meta']['currencySymbol'] ?? pa_currency_symbol($a['meta']['currency'] ?? 'USD');
    $m = fn($n) => $sym . number_format(round($n));

    $stat = fn($label, $val, $color) =>
        '<td align="center" style="padding:12px 8px;border:1px solid #e6ebf2;">'
        . '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;font-weight:600;">' . $label . '</div>'
        . '<div style="font-size:20px;font-weight:700;margin-top:4px;color:' . $color . ';">' . $val . '</div></td>';

    return '<!DOCTYPE html><html><body style="margin:0;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">'
        . '<tr><td style="padding:22px 28px;border-bottom:1px solid #eef2f7;font-size:17px;font-weight:700;">Argo Books</td></tr>'
        . '<tr><td style="padding:26px 28px;">'
        . '<p style="font-size:15px;color:#475569;margin:0 0 18px;">Here\'s what we found in <b style="color:#0f172a;">' . $file . '</b>.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f59e0b;border-radius:12px;margin-bottom:22px;"><tr><td style="padding:16px 18px;">'
        . '<div style="font-size:16px;font-weight:700;color:#7c4a03;">' . $hTitle . '</div>'
        . '<div style="font-size:14px;color:#9a6b1e;margin-top:4px;">' . $hDetail . '</div></td></tr></table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:22px;border-collapse:collapse;"><tr>'
        . $stat('Revenue', $m($rev), '#047857') . $stat('Expenses', $m($exp), '#dc2626')
        . $stat('Net profit', $m($profit), '#0f172a') . $stat('Margin', $margin . '%', '#0f172a')
        . '</tr></table>'
        . '<div style="font-size:12px;color:#94a3b8;font-weight:600;margin-bottom:8px;">WHERE YOUR MONEY GOES</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:8px;overflow:hidden;margin-bottom:6px;"><tr style="height:30px;">'
        . '<td width="' . $costPct . '%" style="background:#d76b66;color:#fff;font-size:11px;font-weight:700;text-align:center;">Costs ' . $costPct . '%</td>'
        . '<td width="' . $margin . '%" style="background:#1f9d6b;color:#fff;font-size:11px;font-weight:700;text-align:center;">Profit ' . $margin . '%</td>'
        . '</tr></table>'
        . '<p style="font-size:12px;color:#94a3b8;text-align:center;margin:6px 0 22px;">Your full cleaned spreadsheet is attached to this email.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f1c33;border-radius:12px;"><tr><td align="center" style="padding:24px;">'
        . '<div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:8px;">Keep these numbers current, automatically.</div>'
        . '<div style="font-size:13px;color:#c3cee0;margin-bottom:18px;">Argo Books tracks your profit all year, plus invoices, expenses, and tax-ready reports.</div>'
        . '<a href="' . $cta . '" style="display:inline-block;background:#fff;color:#0f172a;font-weight:700;font-size:14px;text-decoration:none;padding:12px 22px;border-radius:10px;">Try Argo Books free</a>'
        . '</td></tr></table>'
        . '</td></tr>'
        . '<tr><td style="padding:18px 28px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #eef2f7;">'
        . 'Your uploaded file was deleted after analysis. We only email you when you ask.</td></tr>'
        . '</table></td></tr></table></body></html>';
}
