<?php
/**
 * AI Completions Proxy Endpoint
 *
 * POST /api/ai/completions - Proxy AI chat completion requests
 *
 * Receives prompts from the Argo Books app, forwards them to Gemini,
 * and returns the response. The Gemini API key is stored server-side.
 */

require_once __DIR__ . '/../portal/portal-helper.php';
require_once __DIR__ . '/_timing.php';

// Load environment variables
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

set_portal_headers();
require_method(['POST']);

// Authenticate using license key (premium) or device ID (free)
$license = authenticate_license_request();
$deviceIdHash = null;
if (!$license) {
    $deviceIdHash = authenticate_device_request();
    if (!$deviceIdHash) {
        send_error_response(401, 'Invalid or missing license key.', 'UNAUTHORIZED');
    }
}

// Rate limiting per identity: see RL_AI_COMPLETIONS_* in .env
$rateLimitId = $license ? substr($license['license_key_hash'], 0, 16) : substr($deviceIdHash, 0, 16);
if (rate_limit_hit('ai_completions', $rateLimitId, 'ai_license')) {
    send_rate_limited_response('ai_completions');
}

// Per-IP ceiling for the free (device) path only.
// The X-Device-Id of a free request is self-asserted and not checked against any
// record, so the per-identity limit above can be bypassed by rotating the header.
// A per-IP cap closes that hole: the source IP can't be rotated like a header, so
// it bounds total free AI usage from one origin regardless of how many device IDs
// are sent. Premium (license-validated) requests are exempt because their key is
// verified in the database and is not the abuse vector. This reuses the same
// get_client_ip() that the license-validation and payment endpoints already rely
// on in production. The ceiling is generous (well above the per-identity limit) so
// genuine shared networks (offices/households behind one NAT) are not affected;
// raise RL_AI_COMPLETIONS_IP_MAX if a large shared deployment ever legitimately hits it.
if (!$license) {
    $clientIp = get_client_ip();
    if (rate_limit_hit('ai_completions_ip', $clientIp, 'ai_ip')) {
        send_rate_limited_response('ai_completions_ip');
    }
}

// Parse request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error_response(400, 'Invalid JSON: ' . json_last_error_msg(), 'INVALID_JSON');
}

// Validate required fields
if (empty($data['systemPrompt']) && empty($data['userPrompt'])) {
    send_error_response(400, 'At least one of systemPrompt or userPrompt is required.', 'MISSING_FIELDS');
}

$systemPrompt = $data['systemPrompt'] ?? '';
$userPrompt = $data['userPrompt'] ?? '';
$requestedModel = $data['model'] ?? '';
$maxTokens = max(1, min((int)($data['maxTokens'] ?? 4000), 32000)); // Clamp 1-32k
$temperature = max(0, min(2, (float)($data['temperature'] ?? 0.1)));
$base64Image = $data['base64Image'] ?? null;
$mimeType = $data['mimeType'] ?? 'image/jpeg';

// Optional timing metadata sent by the desktop app so pooled duration priors can be
// kept per operation (receipt scan vs spreadsheet analysis vs bank categorize, which
// are otherwise indistinguishable here). Absent/older clients default to 'completion'.
$operation = isset($data['operation']) ? (string) $data['operation'] : 'completion';
$sizeFeature = isset($data['sizeFeature']) && is_numeric($data['sizeFeature']) ? (int) $data['sizeFeature'] : null;
$appPlatform = isset($data['platform']) ? (string) $data['platform'] : null;

// Receipt scans on the gemini-3.x thinking models spend a large, variable chunk of
// maxOutputTokens on hidden reasoning before writing the JSON answer, so budgets that
// were fine on the old model now truncate mid-JSON. The receipt budget is authoritative
// from .env (RECEIPT_SCAN_MAX_OUTPUT_TOKENS) and ignores whatever the client sent, so it
// can be tuned for all client versions instantly (older builds still send 16000) without
// an app release. Overrides the generic clamp above for this operation only.
// Two flags, not one, because the second pass of a scan needs everything a first pass needs
// EXCEPT the charge. The app re-sends the image when the extracted amounts do not reconcile
// against the printed total; that is the app deciding to look again, not the user asking for a
// second scan, and billing it meant ten receipts could cost twelve and the last be refused.
$isReceiptWork = (($operation === 'receipt_scan' || $operation === 'receipt_verify') && !empty($base64Image));

// Only the first pass is metered.
$isReceiptExtraction = ($operation === 'receipt_scan' && !empty($base64Image));

if ($isReceiptWork) {
    $maxTokens = max(1, (int)($_ENV['RECEIPT_SCAN_MAX_OUTPUT_TOKENS'] ?? 32000));
}

// Retired ids and empty requests get the default; free (device) requests always do.
require_once __DIR__ . '/_models.php';
$requestedModel = ai_resolve_model(is_string($requestedModel) ? $requestedModel : '', !empty($base64Image), (bool) $license);
if ($requestedModel === null) {
    send_error_response(400, 'Unsupported model. Supported: ' . implode(', ', AI_SUPPORTED_MODELS), 'INVALID_MODEL');
}

$geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
if (empty($geminiKey)) {
    send_error_response(500, 'Gemini AI service not configured on server.', 'CONFIG_ERROR');
}

// ---- Receipt-scan quota -----------------------------------------------------
// Taken here, before a single byte goes to Gemini, because this is the request that
// spends the money. It used to be enforced only by the client calling
// api/receipt/usage.php's increment after a scan succeeded, which meant a client that
// sent more requests than it reported, or skipped the call entirely, was unmetered.
// The take is a conditional UPDATE, so a parallel batch cannot overrun the cap.
//
// Only receipt extraction is metered. Other AI operations (spreadsheet analysis, bank
// categorization, plain completions) are covered by the rate limits above and have no
// monthly allowance of their own.
//
// receipt_verify sits with those: it is the same billable receipt looked at twice, so it is
// bounded by the rate limits rather than the monthly allowance. That does leave a caller able
// to spend vision calls under receipt_verify without touching its allowance, capped by the
// 60-per-15-minutes limiter above. Accepted deliberately: the alternative is charging a user
// twice for one receipt, and the cap is the same one already trusted for every other unmetered
// AI operation on this endpoint.
$scanQuotaIdentifier = null;
$scanQuotaSettled = false;
if ($isReceiptExtraction) {
    require_once __DIR__ . '/../receipt/scan_quota.php';

    // The raw key, not the hash: usage.php keys premium rows on the key itself, and
    // both endpoints have to land on the same row. Read the same headers
    // authenticate_license_request() does, since it only hands back a hash.
    $rawLicenseKey = '';
    if (!empty($_SERVER['HTTP_X_LICENSE_KEY'])) {
        $rawLicenseKey = (string) $_SERVER['HTTP_X_LICENSE_KEY'];
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])
        && preg_match('/Bearer\s+(.+)/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $rawLicenseKey = $m[1];
    }

    $identity = receipt_scan_quota_identity($pdo, $rawLicenseKey, $deviceIdHash);
    if ($identity === null) {
        send_error_response(401, 'Invalid or missing license key.', 'UNAUTHORIZED');
    }

    $take = receipt_scan_quota_consume($pdo, $identity['identifier'], $identity['limit']);
    if (!$take['allowed']) {
        send_error_response(
            429,
            sprintf(
                'Monthly scan limit reached (%d of %d used). Your limit resets on %s.',
                $take['scan_count'],
                $identity['limit'],
                date('Y-m-01', strtotime('first day of next month'))
            ),
            'SCAN_LIMIT_REACHED'
        );
    }

    $scanQuotaIdentifier = $identity['identifier'];

    // The scan is paid for up front, so anything that stops a result reaching the user
    // has to hand it back. A shutdown hook rather than a refund at each of the seven
    // failure exits below: those all exit() immediately, and this also covers a fatal
    // or a timeout, which no amount of call-site editing would.
    register_shutdown_function(static function () use (&$scanQuotaSettled, &$scanQuotaIdentifier) {
        if ($scanQuotaSettled || $scanQuotaIdentifier === null) {
            return;
        }
        global $pdo;
        if ($pdo instanceof PDO) {
            receipt_scan_quota_refund($pdo, $scanQuotaIdentifier);
        }
    });
}

$model = $requestedModel;

// Build Gemini request: https://ai.google.dev/api/generate-content
$contents = [];

// Gemini uses system_instruction for system prompts (not in contents array)
$systemInstruction = null;
if (!empty($systemPrompt)) {
    $systemInstruction = ['parts' => [['text' => $systemPrompt]]];
}

// Build user message parts
$userParts = [];
$uploadedFileUri = null;
$uploadedFileName = null;
if (!empty($base64Image)) {
    if ($mimeType === 'application/pdf') {
        // PDFs must be uploaded via the Gemini Files API, then referenced by URI.
        // inline_data only works for image formats.
        $pdfBytes = base64_decode($base64Image, true);
        if ($pdfBytes === false) {
            send_error_response(400, 'Invalid base64 PDF data.', 'INVALID_DATA');
        }

        $uploadUrl = "https://generativelanguage.googleapis.com/upload/v1beta/files";
        $boundary = bin2hex(random_bytes(16));

        // Build multipart/related body: JSON metadata + raw file bytes
        $metadataJson = json_encode(['file' => ['displayName' => 'receipt.pdf']]);
        $multipartBody = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadataJson . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/pdf\r\n\r\n"
            . $pdfBytes . "\r\n"
            . "--{$boundary}--\r\n";

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $multipartBody,
            CURLOPT_HTTPHEADER => [
                "x-goog-api-key: {$geminiKey}",
                "Content-Type: multipart/related; boundary={$boundary}",
                'X-Goog-Upload-Protocol: multipart',
                'Content-Length: ' . strlen($multipartBody),
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $uploadResponse = curl_exec($ch);
        $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $uploadError = curl_error($ch);

        if ($uploadResponse === false || $uploadHttpCode !== 200) {
            error_log("Gemini Files API upload failed ({$uploadHttpCode}): {$uploadError} - Response: {$uploadResponse}");
            send_error_response(502, 'Failed to upload PDF to AI service.', 'UPSTREAM_ERROR');
        }

        $uploadData = json_decode($uploadResponse, true);
        $uploadedFileUri = $uploadData['file']['uri'] ?? null;
        $uploadedFileName = $uploadData['file']['name'] ?? null;
        if (empty($uploadedFileUri) || empty($uploadedFileName)) {
            error_log('Gemini Files API returned no file URI: ' . $uploadResponse);
            send_error_response(502, 'Failed to process uploaded PDF.', 'UPSTREAM_ERROR');
        }

        // Poll until the file is ACTIVE (Gemini processes uploads asynchronously)
        // Polls up to 15 times at 500ms intervals (~7.5s max). If still PROCESSING after
        // all polls, the file is used as-is (Gemini will reject it if not ready).
        $fileStatusUrl = "https://generativelanguage.googleapis.com/v1beta/{$uploadedFileName}";
        $maxPolls = 15;
        for ($i = 0; $i < $maxPolls; $i++) {
            $ch = curl_init($fileStatusUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["x-goog-api-key: {$geminiKey}"],
                CURLOPT_TIMEOUT => 10,
            ]);
            $statusResponse = curl_exec($ch);

            $statusData = json_decode($statusResponse, true);
            $state = $statusData['state'] ?? '';
            if ($state === 'ACTIVE') {
                break;
            }
            if ($state === 'FAILED') {
                error_log('Gemini file processing failed: ' . $statusResponse);
                send_error_response(502, 'AI service failed to process the PDF.', 'UPSTREAM_ERROR');
            }
            // Still PROCESSING: wait and retry
            usleep(500000); // 500ms
        }

        $userParts[] = [
            'file_data' => [
                'file_uri' => $uploadedFileUri,
                'mime_type' => 'application/pdf',
            ],
        ];
    } else {
        $userParts[] = [
            'inline_data' => [
                'mime_type' => $mimeType,
                'data' => $base64Image,
            ],
        ];
    }
}
if (!empty($userPrompt)) {
    $userParts[] = ['text' => $userPrompt];
}
if (!empty($userParts)) {
    $contents[] = ['role' => 'user', 'parts' => $userParts];
}

$generationConfig = [
    'temperature' => $temperature,
    'maxOutputTokens' => $maxTokens,
    'responseMimeType' => 'application/json',
];

if ($isReceiptExtraction) {
    // Cap thinking to "low": gemini-3.x defaults to dynamic thinking that draws from
    // maxOutputTokens; extraction is a structured OCR task, not a reasoning task.
    $generationConfig['thinkingConfig'] = ['thinkingLevel' => 'low'];

    // Constrain generation to a strict schema. Without it, gemini-3.5-flash intermittently
    // emits invalid JSON even in JSON mode (extra brackets, objects cut off mid-field) with
    // finishReason=STOP - the model thinks it finished but the payload won't parse. A
    // responseSchema forces constrained decoding, so the output is always well-formed JSON
    // matching this shape. Fields are optional/nullable so the "not a receipt" error path and
    // any missing values still work; the app reads every field defensively.
    $numberOrNull = ['type' => 'number', 'nullable' => true];
    $stringOrNull = ['type' => 'string', 'nullable' => true];
    $nameAmountItem = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'amount' => ['type' => 'number'],
        ],
        'propertyOrdering' => ['name', 'amount'],
    ];
    $generationConfig['responseSchema'] = [
        'type' => 'object',
        'properties' => [
            'supplierName' => $stringOrNull,
            'transactionDate' => $stringOrNull,
            'subtotal' => $numberOrNull,
            'taxes' => ['type' => 'array', 'items' => $nameAmountItem, 'nullable' => true],
            'discounts' => ['type' => 'array', 'items' => $nameAmountItem, 'nullable' => true],
            'shipping' => $numberOrNull,
            'totalAmount' => $numberOrNull,
            'currencyCode' => $stringOrNull,
            'paymentMethod' => $stringOrNull,
            'confidence' => $numberOrNull,
            'lineItems' => [
                'type' => 'array',
                'nullable' => true,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string'],
                        'quantity' => ['type' => 'number'],
                        'unitPrice' => ['type' => 'number'],
                        'totalPrice' => ['type' => 'number'],
                        'confidence' => ['type' => 'number'],
                    ],
                    'propertyOrdering' => ['description', 'quantity', 'unitPrice', 'totalPrice', 'confidence'],
                ],
            ],
            'error' => $stringOrNull,
        ],
        'propertyOrdering' => [
            'supplierName', 'transactionDate', 'subtotal', 'taxes', 'discounts', 'shipping',
            'totalAmount', 'currencyCode', 'paymentMethod', 'confidence', 'lineItems', 'error',
        ],
    ];
}

$geminiPayload = [
    'contents' => $contents,
    'generationConfig' => $generationConfig,
];
if ($systemInstruction) {
    $geminiPayload['system_instruction'] = $systemInstruction;
}

$geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

// Server-measured Gemini wall time (isolated from the user's network) feeds the
// timing priors and the response 'timing' block.
$aiTimingStart = microtime(true);

$ch = curl_init($geminiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($geminiPayload),
    CURLOPT_HTTPHEADER => [
        "x-goog-api-key: {$geminiKey}",
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response = curl_exec($ch);
$aiElapsedMs = (int) round((microtime(true) - $aiTimingStart) * 1000);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

if ($response === false) {
    error_log('Gemini proxy cURL error: ' . $curlError);
    send_error_response(502, 'Failed to connect to AI service.', 'UPSTREAM_ERROR');
}

$responseData = json_decode($response, true);

if ($httpCode !== 200) {
    $errorMessage = $responseData['error']['message'] ?? 'Unknown upstream error';
    error_log("Gemini proxy error ({$httpCode}): {$errorMessage}");

    if ($httpCode === 429) {
        send_error_response(429, 'AI service rate limit exceeded. Please try again later.', 'UPSTREAM_RATE_LIMITED');
    }

    send_error_response(502, 'AI service returned an error.', 'UPSTREAM_ERROR');
}

// Clean up uploaded PDF file from Gemini storage
if (!empty($uploadedFileName)) {
    $deleteUrl = "https://generativelanguage.googleapis.com/v1beta/{$uploadedFileName}";
    $ch = curl_init($deleteUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => ["x-goog-api-key: {$geminiKey}"],
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
}

// Extract content + finish reason from the Gemini response. The gemini-3.x models can
// split the answer across several parts (and emit separate "thought" parts), so read only
// the earlier parts[0] would drop the tail and yield truncated JSON. Concatenate every
// non-thought text part instead.
$candidate = $responseData['candidates'][0] ?? [];
$content = null;
$parts = $candidate['content']['parts'] ?? [];
if (is_array($parts)) {
    $textSegments = [];
    foreach ($parts as $part) {
        if (!empty($part['thought'])) {
            continue; // reasoning trace, not part of the answer
        }
        if (isset($part['text']) && is_string($part['text'])) {
            $textSegments[] = $part['text'];
        }
    }
    if (!empty($textSegments)) {
        $content = implode('', $textSegments);
    }
}
$partCount = is_array($parts) ? count($parts) : 0;
$finishReason = $candidate['finishReason'] ?? null;

$usage = null;
if (isset($responseData['usageMetadata'])) {
    $usage = [
        'prompt_tokens' => $responseData['usageMetadata']['promptTokenCount'] ?? 0,
        'completion_tokens' => $responseData['usageMetadata']['candidatesTokenCount'] ?? 0,
        'total_tokens' => $responseData['usageMetadata']['totalTokenCount'] ?? 0,
    ];
}

// Diagnostic: a finishReason other than STOP (most often MAX_TOKENS) means the
// model stopped before completing, typically leaving truncated or empty JSON,
// which is the likely cause of downstream "JsonReaderException" parse failures.
// gemini-2.5-flash spends hidden "thinking" tokens out of maxOutputTokens, so a
// small token budget can be exhausted before the JSON answer is written. Logged
// only (response behaviour is unchanged) so truncation shows up in the PHP
// error log. Grep the error log for "[gemini]" to find these.
if ($finishReason !== null && $finishReason !== 'STOP') {
    error_log(sprintf(
        '[gemini] non-STOP finishReason=%s model=%s maxOutputTokens=%d tokens(prompt/out/total)=%d/%d/%d content=%s',
        $finishReason,
        $model,
        $maxTokens,
        $usage['prompt_tokens'] ?? 0,
        $usage['completion_tokens'] ?? 0,
        $usage['total_tokens'] ?? 0,
        $content === null ? 'null' : strlen($content) . ' chars'
    ));
}

if ($content === null) {
    send_error_response(502, 'Invalid response from AI service.', 'UPSTREAM_ERROR');
}

// TEMPORARY DIAGNOSTIC: for receipt extraction, verify the JSON parses server-side. When it
// doesn't, surface the real cause (finishReason, token usage, part count, and the tail of the
// content where it cut off) as a normal {"error": ...} 200 response, which is the only shape the
// desktop app displays verbatim. This replaces the cryptic client-side "truncated JSON" parse
// error with something we can actually read, and confirms whether this deploy is even live.
$receiptContentUsable = true;
if ($isReceiptExtraction) {
    json_decode($content);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // A scan whose JSON never parsed is a failed scan as far as the user is
        // concerned, so it must not cost them one. Left unsettled for the refund hook.
        $receiptContentUsable = false;
        $diag = sprintf(
            'DIAG truncated: finishReason=%s model=%s budget=%d tokens(p/o/t)=%d/%d/%d parts=%d len=%d err=%s tail=[%s]',
            $finishReason ?? 'null',
            $model,
            $maxTokens,
            $usage['prompt_tokens'] ?? 0,
            $usage['completion_tokens'] ?? 0,
            $usage['total_tokens'] ?? 0,
            $partCount,
            strlen($content),
            json_last_error_msg(),
            substr($content, -140)
        );
        error_log('[gemini] ' . $diag);
        $content = json_encode(['error' => $diag]);
    }
}

// Record the server-measured timing (best-effort; never breaks the response).
ai_timing_record([
    'operation' => $operation,
    'model' => $model,
    'size_feature' => $sizeFeature,
    'input_bytes' => !empty($base64Image) ? (int) (strlen($base64Image) * 0.75) : strlen($userPrompt),
    'mime' => !empty($base64Image) ? $mimeType : null,
    'prompt_tokens' => $usage['prompt_tokens'] ?? null,
    'output_tokens' => $usage['completion_tokens'] ?? null,
    'max_output_tokens' => $maxTokens,
    'finish_reason' => $finishReason,
    'elapsed_ms' => $aiElapsedMs,
    'success' => true,
    'app_platform' => $appPlatform,
]);

// A consumed scan is only kept once a usable result is genuinely on its way back.
// Anything else leaves this false and the shutdown hook returns it to the allowance.
$scanQuotaSettled = $receiptContentUsable;

send_json_response(200, [
    'success' => true,
    'content' => $content,
    'model' => $model,
    'usage' => $usage,
    'finishReason' => $finishReason,
    'timing' => [
        'elapsed_ms' => $aiElapsedMs,
        'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
        'output_tokens' => $usage['completion_tokens'] ?? 0,
        'load_factor' => ai_timing_load_factor(),
    ],
    'timestamp' => date('c'),
]);
