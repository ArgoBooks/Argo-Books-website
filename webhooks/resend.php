<?php
/**
 * Resend Webhook Handler
 *
 * Receives delivery / engagement events from Resend (delivered, opened, clicked,
 * bounced, complained, failed, delivery_delayed) and stores them in
 * outreach_email_events. Permanent bounces and spam complaints additionally
 * flip the lead's status to email_bounced and add the address to
 * email_suppressions so we never re-send to it.
 *
 * Configure in Resend dashboard:
 *   - URL: https://argorobots.com/webhooks/resend.php
 *   - Events: email.delivered, email.bounced, email.complained, email.opened,
 *             email.clicked, email.failed, email.delivery_delayed
 *   - Copy the signing secret (whsec_...) into RESEND_WEBHOOK_SECRET in .env
 *
 * Resend uses Svix for delivery, so signature verification is the standard
 * Svix scheme: HMAC-SHA256(secret, "{svix-id}.{svix-timestamp}.{body}").
 */

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../db_connect.php';

// db_connect.php sets $pdo to null on a PDOException, and a later prepare() would throw an Error the catches below miss.
if (!($pdo instanceof PDO)) {
    error_log('Resend webhook: $pdo is not available (db_connect failed).');
    http_response_code(500);
    exit('DB unavailable');
}

// Only accept POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$rawBody = file_get_contents('php://input');
if ($rawBody === '' || $rawBody === false) {
    http_response_code(400);
    exit('Empty body');
}

$svixId        = $_SERVER['HTTP_SVIX_ID']        ?? '';
$svixTimestamp = $_SERVER['HTTP_SVIX_TIMESTAMP'] ?? '';
$svixSignature = $_SERVER['HTTP_SVIX_SIGNATURE'] ?? '';

if ($svixId === '' || $svixTimestamp === '' || $svixSignature === '') {
    http_response_code(400);
    exit('Missing Svix headers');
}

$secret = $_ENV['RESEND_WEBHOOK_SECRET'] ?? '';
if ($secret === '') {
    error_log('Resend webhook: RESEND_WEBHOOK_SECRET is not set; rejecting all requests.');
    http_response_code(500);
    exit('Webhook secret not configured');
}

if (!verify_svix_signature($rawBody, $svixId, $svixTimestamp, $svixSignature, $secret)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($rawBody, true);
if (!is_array($event) || !isset($event['type'], $event['data']) || !is_array($event['data'])) {
    http_response_code(400);
    exit('Malformed payload');
}

$eventType = (string) $event['type'];
$data      = $event['data'];

$resendEmailId = isset($data['email_id']) ? (string) $data['email_id'] : null;

// Without email_id the event cannot be deduplicated, since the unique index is (message_id, event_type), so it is dropped.
if ($resendEmailId === null || $resendEmailId === '') {
    error_log('Resend webhook: event missing email_id; skipping insert.');
    http_response_code(200);
    echo '{}';
    exit;
}

$recipients    = isset($data['to']) && is_array($data['to']) ? $data['to'] : [];
$primaryTo     = '';
foreach ($recipients as $r) {
    $candidate = strtolower(trim((string) $r));
    if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
        $primaryTo = $candidate;
        break;
    }
}

// Strip the "email." prefix so the stored event_type matches our short keys
// (delivered, bounced, opened, clicked, complained, failed, delivery_delayed).
$shortType = (strpos($eventType, 'email.') === 0)
    ? substr($eventType, strlen('email.'))
    : $eventType;

$occurredAt = isset($event['created_at']) ? (string) $event['created_at'] : '';
// Use gmdate (UTC) for the fallback so occurred_at is always UTC. Matches
// what format_iso8601_for_mysql() does when created_at is present.
$occurredAtSql = $occurredAt !== '' ? format_iso8601_for_mysql($occurredAt) : gmdate('Y-m-d H:i:s');

// Each lead is sent at most once, so the most recently contacted lead with that address is the right match.
$leadId = null;
if ($primaryTo !== '') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM outreach_leads
            WHERE LOWER(email) = ?
              AND sent_at IS NOT NULL
              AND sent_at > DATE_SUB(NOW(), INTERVAL 60 DAY)
            ORDER BY sent_at DESC
            LIMIT 1");
        $stmt->execute([$primaryTo]);
        $row = $stmt->fetch();
        if ($row && isset($row['id'])) {
            $leadId = (int) $row['id'];
        }
    } catch (PDOException $e) {
        error_log('Resend webhook: lead lookup failed: ' . $e->getMessage());
    }
}

if ($leadId === null) {
    // Not an outreach email (or send is older than 60d). Acknowledge so
    // Resend doesn't keep retrying.
    http_response_code(200);
    echo '{}';
    exit;
}

// Insert the event row. The (message_id, event_type) UNIQUE key gives us
// idempotency on retries.
try {
    $insStmt = $pdo->prepare("INSERT INTO outreach_email_events
        (lead_id, event_type, message_id, occurred_at, raw_payload)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE id = id");
    $insStmt->execute([
        $leadId,
        $shortType,
        $resendEmailId,
        $occurredAtSql,
        json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
} catch (PDOException $e) {
    error_log('Resend webhook: event insert failed: ' . $e->getMessage());
    http_response_code(500);
    exit('DB error');
}

// Side effects for terminal-bad events. Permanent bounce and spam complaint both mean we should never re-send to this address. Temporary bounces (soft) are recorded but don't suppress.
$shouldSuppress = false;
$suppressReason = null;
if ($shortType === 'bounced') {
    $bounceType = isset($data['bounce']['type']) ? strtolower((string) $data['bounce']['type']) : '';
    if ($bounceType === 'permanent') {
        $shouldSuppress = true;
        $bounceMsg = isset($data['bounce']['message']) ? (string) $data['bounce']['message'] : '';
        $suppressReason = 'hard_bounce: ' . mb_substr($bounceMsg, 0, 200);
    }
} elseif ($shortType === 'complained') {
    $shouldSuppress = true;
    $suppressReason = 'spam_complaint';
}

if ($shouldSuppress && $primaryTo !== '') {
    try {
        $pdo->prepare("UPDATE outreach_leads SET status = 'email_bounced'
            WHERE id = ? AND status NOT IN ('replied','interested','onboarded')")
            ->execute([$leadId]);

        $supStmt = $pdo->prepare("INSERT INTO email_suppressions (email, context, reason, source_id, suppressed_at)
            VALUES (?, 'outreach', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE reason = VALUES(reason), suppressed_at = VALUES(suppressed_at)");
        $supStmt->execute([$primaryTo, $suppressReason, $leadId]);

        $logStmt = $pdo->prepare("INSERT INTO outreach_activity_log (lead_id, action_type, details)
            VALUES (?, 'email_suppressed', ?)");
        $logStmt->execute([$leadId, $suppressReason]);
    } catch (PDOException $e) {
        error_log('Resend webhook: suppression update failed: ' . $e->getMessage());
        // Don't fail the webhook on side-effect errors. The event row is
        // already saved and a future job can reconcile.
    }
}

http_response_code(200);
echo '{}';

/**
 * Verify a Svix-signed webhook request (Resend uses Svix). Returns true when
 * the signature header contains at least one valid HMAC-SHA256 signature over
 * "{svix-id}.{svix-timestamp}.{body}", and the timestamp is within ±5 minutes
 * of now.
 *
 * The secret is the `whsec_<base64>` string from the Resend dashboard.
 */
function verify_svix_signature($body, $svixId, $svixTimestamp, $svixSignatureHeader, $secret)
{
    // Reject replayed events outside the 5-minute window.
    $ts = (int) $svixTimestamp;
    if ($ts <= 0 || abs(time() - $ts) > 300) {
        return false;
    }

    if (strpos($secret, 'whsec_') !== 0) {
        return false;
    }
    $secretBytes = base64_decode(substr($secret, strlen('whsec_')), true);
    if ($secretBytes === false || $secretBytes === '') {
        return false;
    }

    $signedPayload = $svixId . '.' . $svixTimestamp . '.' . $body;
    $expectedSig = base64_encode(hash_hmac('sha256', $signedPayload, $secretBytes, true));

    // Header format is space-separated "v1,<base64sig>" entries; multiple
    // signatures may appear during secret rotation.
    foreach (explode(' ', $svixSignatureHeader) as $entry) {
        $parts = explode(',', $entry, 2);
        if (count($parts) !== 2) continue;
        if ($parts[0] !== 'v1') continue;
        if (hash_equals($expectedSig, $parts[1])) {
            return true;
        }
    }
    return false;
}

/**
 * Convert an ISO 8601 timestamp (with or without fractional seconds) to MySQL
 * DATETIME format in UTC. Returns the current UTC time if parsing fails.
 */
function format_iso8601_for_mysql($iso)
{
    try {
        $dt = new DateTimeImmutable($iso);
        $dt = $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return gmdate('Y-m-d H:i:s');
    }
}
