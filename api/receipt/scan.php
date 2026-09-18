<?php
/**
 * Free web receipt scanner endpoint (/free-receipt-scanner/).
 *
 * Public, no-signup. Verifies a Cloudflare Turnstile token, enforces
 * per-visitor / per-IP / global daily limits via the file-based rate limiter,
 * downscales the image, calls Gemini 2.5 Flash with the ported desktop prompt,
 * and returns the structured receipt. Stores nothing. Mirrors
 * profit-analyzer/upload.php.
 */

header('Content-Type: application/json; charset=utf-8');

@set_time_limit(180);
ignore_user_abort(true);

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

require_once __DIR__ . '/../../rate_limit_helper.php';
require_once __DIR__ . '/../../config/pricing.php';
require_once __DIR__ . '/receipt_scan_lib.php';
require_once __DIR__ . '/../../smtp_mailer.php'; // create_smtp_mailer() (loads env_helper.php)

const RS_MAX_BYTES = 10 * 1024 * 1024;         // 10 MB upload ceiling (matches .htaccess post_max_size); downscaled server-side after
const RS_TARGET_BYTES = 4 * 1024 * 1024;       // compress to <= 4 MB before sending (matches desktop)
const RS_WINDOW = 86400;                        // 24h
const RS_ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

function rs_fail(int $code, string $error, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'error' => $error, 'message' => $message], $extra));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    rs_fail(405, 'method_not_allowed', 'Use POST to scan a receipt.');
}

// PHP empties $_POST/$_FILES when the body exceeds post_max_size. Detect that up
// front so an oversized upload gets a clear message instead of a generic auth or
// "no file" error (a real scan request always has $_FILES populated).
if (empty($_FILES) && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024 * 1024) {
    rs_fail(413, 'too_large', 'That image is too large. Please use a photo under 10 MB.');
}

$cfg = get_pricing_config();
$perVisitor = $cfg['web_receipt_scan_daily_limit'];
$globalCap  = $cfg['web_receipt_scan_global_daily_cap'];

$ip = get_client_ip();
// Use REMOTE_ADDR (real TCP peer), not get_client_ip(), for the local-dev
// bypass: get_client_ip() can honor X-Forwarded-For behind a trusted proxy, so
// a spoofed "X-Forwarded-For: 127.0.0.1" must NOT be able to skip auth + limits.
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

// --- 1. Auth: a valid scan pass (issued after a Turnstile solve) OR a fresh
// Turnstile token. The pass is signed, IP-bound, and short-lived; it lets the
// rest of a bulk batch run concurrently without a new token per request.
// Skipped on local dev so testing isn't blocked. ---
$turnstileToken = $_POST['turnstile_token'] ?? '';
$scanPass = $_POST['scan_pass'] ?? '';
if (!$isLocal) {
    $authed = ($scanPass !== '' && rs_check_pass($scanPass, $ip)) || rs_verify_turnstile($turnstileToken, $ip);
    if (!$authed) {
        rs_fail(401, 'bad_turnstile', 'Could not verify you are human. Please reload and try again.');
    }
}
// A fresh short-lived pass the client reuses for the rest of a bulk batch.
$authPass = $isLocal ? '' : rs_make_pass($ip);

// --- 2. Identify the visitor. The daily limits are reserved ATOMICALLY just
// before the paid Gemini call (below), so invalid uploads don't consume a scan
// and concurrent bulk requests can't overshoot the cap. ---
$fingerprint = preg_replace('/[^a-zA-Z0-9]/', '', (string)($_POST['fingerprint'] ?? ''));
$fpKey = $fingerprint !== '' ? $fingerprint : 'anon';

// --- 3. File validation ---
$file = $_FILES['receipt'] ?? null;
$uploadErr = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if (!$file || $uploadErr === UPLOAD_ERR_NO_FILE) {
    rs_fail(400, 'no_file', 'No receipt image was uploaded.');
}
if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
    rs_fail(413, 'too_large', 'That image is too large. Please use a photo under 10 MB.');
}
if ($uploadErr !== UPLOAD_ERR_OK) {
    rs_fail(400, 'upload_error', 'The upload did not complete. Please try again.');
}
if (($file['size'] ?? 0) > RS_MAX_BYTES) {
    rs_fail(413, 'too_large', 'That image is over 10 MB. Please use a smaller photo.');
}

$tmp = tempnam(sys_get_temp_dir(), 'rs_');
if (!$tmp || !move_uploaded_file($file['tmp_name'], $tmp)) {
    rs_fail(500, 'io_error', 'Could not read the uploaded file. Please try again.');
}

$mime = rs_detect_mime($tmp);
if (!in_array($mime, RS_ALLOWED, true)) {
    @unlink($tmp);
    rs_fail(415, 'bad_type', 'Please upload a JPEG, PNG, or WebP photo of your receipt.');
}

// Reject pixel/decompression bombs (a small file can decode to a huge bitmap and
// exhaust memory) by checking dimensions from the header before GD decodes it.
$dims = @getimagesize($tmp);
if ($dims && (int)$dims[0] * (int)$dims[1] > 40 * 1000 * 1000) {
    @unlink($tmp);
    rs_fail(413, 'too_large', 'That image is too large to process. Please use a normal photo.');
}

// --- 4. Gemini call ---
$geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
if ($geminiKey === '') {
    @unlink($tmp);
    rs_fail(500, 'config', 'Scanner is not configured. Please try again later.');
}

// Downscale + EXIF-rotate + re-encode to JPEG under ~4 MB before sending,
// mirroring the desktop ReceiptImageHelper. Keeps Gemini token cost and latency
// down and fixes rotated phone photos.
$jpeg = rs_preprocess_image($tmp, $mime);
@unlink($tmp); // store nothing past this point
if ($jpeg === null) {
    rs_fail(422, 'unreadable', "We couldn't read that image. Try a clearer photo.", ['scan_pass' => $authPass]);
}
$base64 = base64_encode($jpeg);

// --- Reserve a scan slot ATOMICALLY just before the paid call. Using
// check_and_record (not check-then-record) closes the race where concurrent
// bulk requests each pass a plain check and overshoot the cap. Order: per-
// visitor, per-IP, then the global cost cap LAST so it never over-counts. Only
// requests that reach the Gemini call consume a slot (invalid/undecodable
// uploads were already rejected above). ---
if (!$isLocal) {
    if (check_and_record_rate_limit($fpKey, $perVisitor, RS_WINDOW, 'web_receipt_fp')) {
        rs_fail(429, 'rate_limited', "You've used your free scans for today. Get Argo Books free to keep scanning and save them as expenses.",
            ['cta' => '/downloads/?source=receipt-scanner-limit&utm_source=receipt-scanner&utm_medium=tool', 'scan_pass' => $authPass]);
    }
    if (rate_limit_hit('web_receipt_ip', $ip)) {
        header('Retry-After: ' . rate_limit_window('web_receipt_ip'));
        rs_fail(429, 'rate_limited', "You've used your free scans for today. Get Argo Books free to keep scanning.",
            ['cta' => '/downloads/?source=receipt-scanner-limit', 'scan_pass' => $authPass]);
    }
    if (check_and_record_rate_limit('GLOBAL', $globalCap, RS_WINDOW, 'web_receipt_global')) {
        // First blocked request of the day -> email the admin once.
        if (!check_and_record_rate_limit('GLOBAL', 1, RS_WINDOW, 'web_receipt_alert')) {
            rs_send_cap_alert($globalCap);
        }
        rs_fail(429, 'capacity', 'Free scanning is at capacity for today. Try again tomorrow, or get Argo Books for unlimited scanning.',
            ['cta' => '/pricing/?source=receipt-scanner-capacity', 'scan_pass' => $authPass]);
    }
}

$model = $_ENV['GEMINI_MODEL_EXTRACTION'] ?? 'gemini-3.5-flash';
$content = rs_call_gemini($geminiKey, $model, 'image/jpeg', $base64);
if ($content === null) {
    rs_fail(502, 'upstream', 'The scanner had trouble reading that. Please try again in a moment.', ['scan_pass' => $authPass]);
}

$normalized = receipt_scan_normalize($content);
if (!$normalized['ok']) {
    rs_fail(422, 'unreadable', "That doesn't look like a receipt we can read. Try a clearer, well-lit photo.", ['scan_pass' => $authPass]);
}

// The slot was already reserved before the Gemini call (atomic, race-free).
echo json_encode(['ok' => true, 'receipt' => $normalized['receipt'], 'scan_pass' => $authPass]);

// ---------------------------------------------------------------------------

/**
 * Issue a short-lived, IP-bound, signed scan pass. After one Turnstile solve the
 * client reuses this for the rest of a bulk batch so requests can run
 * concurrently (Turnstile tokens are single-use). Signed with the server-side
 * Turnstile secret so it can't be forged.
 */
/** Secret for signing scan passes: a dedicated key if set, else the Turnstile secret. */
function rs_pass_secret(): string
{
    $s = $_ENV['SCAN_PASS_SECRET'] ?? '';
    return $s !== '' ? $s : ($_ENV['TURNSTILE_SECRET_KEY'] ?? '');
}

function rs_make_pass(string $ip): string
{
    $secret = rs_pass_secret();
    $payload = (time() + 600) . ':' . substr(hash('sha256', $ip), 0, 16); // 10 min
    $sig = hash_hmac('sha256', $payload, $secret);
    return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' . $sig;
}

/** Validate a scan pass: signature, expiry, and IP binding. */
function rs_check_pass(string $pass, string $ip): bool
{
    $secret = rs_pass_secret();
    $parts = explode('.', $pass, 2);
    if (count($parts) !== 2) {
        return false;
    }
    $payload = base64_decode(strtr($parts[0], '-_', '+/'), true);
    if ($payload === false) {
        return false;
    }
    if (!hash_equals(hash_hmac('sha256', $payload, $secret), $parts[1])) {
        return false;
    }
    $bits = explode(':', $payload, 2);
    if (count($bits) !== 2) {
        return false;
    }
    if ((int)$bits[0] < time()) {
        return false; // expired
    }
    return hash_equals($bits[1], substr(hash('sha256', $ip), 0, 16));
}

/** Verify a Cloudflare Turnstile token server-side. */
function rs_verify_turnstile(string $token, string $ip): bool
{
    $secret = $_ENV['TURNSTILE_SECRET_KEY'] ?? '';
    if ($secret === '' || $token === '') {
        return false;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        return false;
    }
    $data = json_decode($resp, true);
    return is_array($data) && !empty($data['success']);
}

/**
 * Decode, EXIF-rotate, lightly enhance, downscale, and re-encode to JPEG under
 * RS_TARGET_BYTES. Mirrors the desktop ReceiptImageHelper (4 MB target, scale
 * factors 0.85..0.3, quality 95/85/75/65, EXIF correction, contrast + sharpen).
 * Returns JPEG bytes, or null if the image cannot be decoded.
 */
function rs_preprocess_image(string $path, string $mime): ?string
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    // GD missing: send the original bytes rather than failing the scan.
    if (!function_exists('imagecreatefromstring')) {
        return $raw;
    }
    $img = @imagecreatefromstring($raw);
    if ($img === false) {
        return null;
    }

    // Convolution/contrast filters no-op on palette (indexed) images, e.g. 8-bit
    // PNGs, so promote to true colour first or faded receipts skip sharpening.
    if (function_exists('imageistruecolor') && !imageistruecolor($img)) {
        @imagepalettetotruecolor($img);
    }

    // EXIF orientation (JPEG only).
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = (int)($exif['Orientation'] ?? 1);
        $rotated = null;
        if ($orientation === 3) {
            $rotated = imagerotate($img, 180, 0);
        } elseif ($orientation === 6) {
            $rotated = imagerotate($img, -90, 0);
        } elseif ($orientation === 8) {
            $rotated = imagerotate($img, 90, 0);
        }
        if ($rotated !== false && $rotated !== null) {
            imagedestroy($img);
            $img = $rotated;
        }
    }

    // Light contrast boost + unsharp mask to help faded thermal receipts.
    // GD's contrast filter is inverted (negative = more contrast).
    @imagefilter($img, IMG_FILTER_CONTRAST, -10);
    @imageconvolution($img, [[0, -0.5, 0], [-0.5, 3, -0.5], [0, -0.5, 0]], 1, 0);

    $w = imagesx($img);
    $h = imagesy($img);

    foreach ([1.0, 0.85, 0.7, 0.55, 0.4, 0.3] as $scale) {
        $canvas = $img;
        if ($scale < 1.0) {
            $scaled = imagescale($img, max(1, (int)round($w * $scale)), max(1, (int)round($h * $scale)));
            if ($scaled === false) {
                continue;
            }
            $canvas = $scaled;
        }
        foreach ([95, 85, 75, 65] as $quality) {
            ob_start();
            imagejpeg($canvas, null, $quality);
            $out = ob_get_clean();
            if ($out !== false && strlen($out) <= RS_TARGET_BYTES) {
                if ($canvas !== $img) {
                    imagedestroy($canvas);
                }
                imagedestroy($img);
                return $out;
            }
        }
        if ($canvas !== $img) {
            imagedestroy($canvas);
        }
    }

    // Still over target at the smallest scale: send the most-compressed version.
    ob_start();
    imagejpeg($img, null, 65);
    $out = ob_get_clean();
    imagedestroy($img);
    return $out === false ? null : $out;
}

/** Best-effort MIME detection from file contents. */
function rs_detect_mime(string $path): string
{
    $info = @getimagesize($path);
    if (is_array($info) && !empty($info['mime'])) {
        return $info['mime'];
    }
    if (function_exists('mime_content_type')) {
        return (string)@mime_content_type($path);
    }
    return '';
}

/**
 * Call Gemini with the receipt prompt. Returns the model text content or null.
 * Mirrors the proven request shape in api/ai/completions.php.
 */
function rs_call_gemini(string $key, string $model, string $mime, string $base64): ?string
{
    $payload = [
        'contents' => [[
            'role' => 'user',
            'parts' => [
                ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                ['text' => RECEIPT_SCAN_USER_PROMPT],
            ],
        ]],
        'system_instruction' => ['parts' => [['text' => RECEIPT_SCAN_SYSTEM_PROMPT]]],
        'generationConfig' => [
            'temperature' => 0.0,
            'maxOutputTokens' => (int)($_ENV['RECEIPT_SCAN_MAX_OUTPUT_TOKENS'] ?? 32000),
            'responseMimeType' => 'application/json',
        ],
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ["x-goog-api-key: {$key}", 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false || $httpCode !== 200) {
        error_log('[receipt-scan] Gemini error ' . $httpCode . ': ' . substr((string)$resp, 0, 500));
        return null;
    }
    $data = json_decode($resp, true);
    $candidate = $data['candidates'][0] ?? [];
    // gemini-3.x models spend hidden "thinking" tokens out of maxOutputTokens. The
    // budget above comes from RECEIPT_SCAN_MAX_OUTPUT_TOKENS in .env (shared with the
    // app receipt scanner); log any non-STOP finish so future truncation is visible.
    $finish = $candidate['finishReason'] ?? null;
    if ($finish !== null && $finish !== 'STOP') {
        error_log('[receipt-scan] non-STOP finishReason=' . $finish);
    }
    return $candidate['content']['parts'][0]['text'] ?? null;
}

/** Email the owner once when the global daily cap is reached. */
function rs_send_cap_alert(int $cap): void
{
    $to = $_ENV['WEB_RECEIPT_SCAN_ALERT_EMAIL'] ?? 'support@argorobots.com';
    $subject = 'Free receipt scanner hit its daily cap';
    $when = date('Y-m-d H:i T');
    $body = "The free web receipt scanner reached its global daily cap of {$cap} scans at {$when}.\n\n"
          . "Further free scans are blocked until tomorrow. Visitors are seeing the at-capacity upsell message.\n\n"
          . "Raise WEB_RECEIPT_SCAN_GLOBAL_DAILY_CAP in .env if this is happening too early in the day.";
    try {
        $mailer = create_smtp_mailer();
        if ($mailer !== null) {
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->isHTML(false);
            $mailer->Body = $body;
            $mailer->send();
            return;
        }
        @mail($to, $subject, $body, 'From: noreply@argorobots.com');
    } catch (\Throwable $e) {
        error_log('[receipt-scan] cap alert email failed: ' . $e->getMessage());
    }
}
