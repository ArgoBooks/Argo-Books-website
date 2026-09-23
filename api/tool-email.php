<?php
// api/tool-email.php
//
// One endpoint behind every free tool's "email me these results" box, so the
// site has a single place that turns a visitor into a contactable address
// rather than a copy of profit-analyzer/email.php per tool.
//
// The tool posts label/value pairs, not HTML: the email body is rendered here
// from a fixed template, so a tool page can never dictate what goes out under
// the Argo Books name. Attachments are deliberately not accepted for the same
// reason, which is why the generators use the opt-in-only mode below rather
// than mailing the PDF they already produced in the browser.
//
// Modes, decided by whether any summary rows arrive:
//   - with rows: sends the results email, and subscribes as well if asked
//   - without:   opt-in only, so the double opt-in confirmation is the only mail
//
// All transactional mail goes through Resend via the SMTP relay
// (argo_send_html_email), which falls back to mail() only when SMTP is absent.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../rate_limit_helper.php';
require_once __DIR__ . '/../smtp_mailer.php';
require_once __DIR__ . '/../email_marketing.php';
require_once __DIR__ . '/../env_helper.php';

/**
 * Sources allowed to use this endpoint. An allowlist rather than a free string,
 * following api/invoice-generator/track.php, so the subscriber table cannot be
 * sprayed with invented sources and every row stays attributable to a page.
 *
 * Narrower than marketing_source_labels(), which names every source the list has
 * ever held: profit_analyzer posts to its own endpoint and has no business here.
 */
const TOOL_EMAIL_SOURCES = [
    'hourly_rate_calculator',
    'break_even_calculator',
    'self_employed_tax',
    'invoice_generator',
    'estimate_generator',
    'purchase_order_generator',
    // Opt-in only: never posts summary rows, so it takes the confirmation-only branch below.
    'desktop_app',
];

const TOOL_EMAIL_MAX_ROWS = 20;
const TOOL_EMAIL_MAX_LABEL = 80;
const TOOL_EMAIL_MAX_VALUE = 120;

function tool_email_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    tool_email_fail(405, 'Use POST.');
}

$raw = file_get_contents('php://input');
$body = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($body)) {
    tool_email_fail(400, 'Expected a JSON body.');
}

$email = strtolower(trim((string) ($body['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    tool_email_fail(400, 'Please enter a valid email address.');
}

$source = (string) ($body['source'] ?? '');
if (!in_array($source, TOOL_EMAIL_SOURCES, true)) {
    tool_email_fail(400, 'Unknown tool.');
}
$toolName = marketing_source_label($source);

$subscribe = !empty($body['subscribe']);

// Rows are capped and truncated rather than rejected: a tool that grows an extra
// output line should not start failing silently in someone's browser.
$rows = [];
if (isset($body['summary']) && is_array($body['summary'])) {
    foreach ($body['summary'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        $value = trim((string) ($row['value'] ?? ''));
        if ($label === '' && $value === '') {
            continue;
        }
        $rows[] = [
            'label' => mb_substr($label, 0, TOOL_EMAIL_MAX_LABEL),
            'value' => mb_substr($value, 0, TOOL_EMAIL_MAX_VALUE),
        ];
        if (count($rows) >= TOOL_EMAIL_MAX_ROWS) {
            break;
        }
    }
}

// Opt-in-only mode has nothing to send unless the box was ticked, so an empty
// post is a mistake rather than a silent no-op.
if (!$rows && !$subscribe) {
    tool_email_fail(400, 'Nothing to send.');
}

$ip = get_client_ip();
if (rate_limit_hit('tool_email', $ip)) {
    header('Retry-After: ' . rate_limit_window('tool_email'));
    tool_email_fail(429, 'Too many emails from this address right now. Please try again in '
        . rate_limit_wait_phrase('tool_email') . '.');
}

$sentResults = false;
if ($rows) {
    $cta = 'https://argorobots.com/downloads/?source=' . rawurlencode(str_replace('_', '-', $source))
        . '-email&utm_source=' . rawurlencode($source) . '&utm_medium=email';

    $sent = argo_send_html_email(
        $email,
        'Your ' . $toolName . ' results',
        tool_email_html($toolName, $rows, $cta)
    );

    if (!$sent['success']) {
        error_log('tool-email send failed (' . $source . '): ' . ($sent['error'] ?? 'unknown'));
        tool_email_fail(500, "We couldn't send the email just now. Please try again.");
    }
    $sentResults = true;
}

// Independent of the results email above, and never allowed to fail it: the
// subscriber is only added to the broadcast list once they click confirm.
$subscribeStatus = 'skipped';
if ($subscribe) {
    $subscribeStatus = create_pending_subscriber($email, $source, 'newsletter', $ip);
}

if ($sentResults) {
    $message = 'Sent. Check your inbox.';
    if ($subscribeStatus === 'sent') {
        $message = "Sent. We've also emailed a link to confirm your subscription.";
    } elseif ($subscribeStatus === 'already_confirmed') {
        $message = "Sent. You're already subscribed, so just check your inbox for your results.";
    }
} else {
    $message = match ($subscribeStatus) {
        'sent'              => 'Almost there. Check your inbox for a link to confirm.',
        'already_confirmed' => "You're already on the list, so there's nothing to confirm.",
        'invalid'           => 'Please enter a valid email address.',
        default             => "We couldn't sign you up just now. Please try again.",
    };
    if ($subscribeStatus === 'invalid' || $subscribeStatus === 'error') {
        tool_email_fail($subscribeStatus === 'invalid' ? 400 : 500, $message);
    }
}

echo json_encode(['ok' => true, 'message' => $message, 'subscribe' => $subscribeStatus]);

/**
 * Build the results email. Table-based with inline styles, like the other
 * transactional mail on the site, because email clients ignore stylesheets.
 */
function tool_email_html(string $toolName, array $rows, string $cta): string
{
    $tool = htmlspecialchars($toolName, ENT_QUOTES, 'UTF-8');
    $ctaSafe = htmlspecialchars($cta, ENT_QUOTES, 'UTF-8');

    $lines = '';
    foreach ($rows as $row) {
        $label = htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8');
        $lines .= <<<HTML
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#475569;font-size:14px;">{$label}</td>
              <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;">{$value}</td>
            </tr>
HTML;
    }

    return <<<HTML
    <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:560px;margin:0 auto;color:#0f172a;">
      <h2 style="font-size:20px;margin:0 0 4px;">Your {$tool} results</h2>
      <p style="font-size:14px;color:#64748b;margin:0 0 20px;">Here is what you worked out, so you have it to hand.</p>
      <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
        {$lines}
      </table>
      <p style="font-size:14px;color:#475569;margin:24px 0 8px;">
        Argo Books keeps these numbers up to date on their own, as you record sales and expenses.
        It runs on your computer, and it is free to start.
      </p>
      <p style="margin:0 0 24px;">
        <a href="{$ctaSafe}" style="display:inline-block;background:#3f63e8;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:8px;font-size:14px;font-weight:600;">Download Argo Books</a>
      </p>
      <p style="font-size:12px;color:#94a3b8;margin:0;">You asked for this from a free tool on argorobots.com. We did not add you to any list by sending it.</p>
    </div>
HTML;
}
