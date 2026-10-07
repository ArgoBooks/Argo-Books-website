<?php
// profit-analyzer/lib/import/gemini.php
//
// Direct Gemini client for the Profit Analyzer's server-side analysis.
//
// The app calls Gemini through api/ai/completions.php, but that proxy requires a
// license key or device ID. The Profit Analyzer is anonymous public traffic with
// neither, so it calls Gemini directly here using the server-side GEMINI_API_KEY.
// Abuse is bounded by the per-IP rate limit + file caps in upload.php.
//
// The request/response shape mirrors completions.php exactly (system_instruction +
// user parts, responseMimeType application/json, content at candidates[0]...text)
// so the two stay behaviourally identical and easy to merge later.

if (!defined('PA_GEMINI_MODEL')) {
    // Financial-import extraction: use the accuracy-tier model like the receipt
    // scanner and bank extractor.
    define('PA_GEMINI_MODEL', $_ENV['GEMINI_MODEL_EXTRACTION'] ?? 'gemini-3.5-flash');
}

/** Loads GEMINI_API_KEY from the project .env if it isn't already in the environment. */
function pa_gemini_key(): string
{
    $key = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: '';
    if ($key !== '') {
        return $key;
    }
    $autoload = __DIR__ . '/../../../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Dotenv\\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
            $dotenv->safeLoad();
            $key = $_ENV['GEMINI_API_KEY'] ?? '';
        }
    }
    return $key ?: '';
}

/**
 * Sends a system+user chat to Gemini and returns the model's text content
 * (expected to be JSON, since responseMimeType is application/json). Returns null
 * on any failure — callers treat null as "this batch/chunk could not be analyzed".
 *
 * Mirrors GeminiService.SendChatAsync: maxTokens default 4000, temperature 0.1.
 */
function pa_gemini_chat(
    string $systemPrompt,
    string $userPrompt,
    int $maxTokens = 4000,
    float $temperature = 0.1
): ?string {
    $key = pa_gemini_key();
    if ($key === '') {
        error_log('profit-analyzer: GEMINI_API_KEY not configured');
        return null;
    }

    $ch = pa_gemini_build_handle($systemPrompt, $userPrompt, $maxTokens, $temperature, $key);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return pa_gemini_parse_response($response, $httpCode, $err);
}

/**
 * Run several Gemini chats concurrently with a cap on how many are in flight at
 * once — the website equivalent of the desktop app's parallel analysis batches
 * (5) and Tier 2 chunks (10). Each request is an assoc array
 * ['system'=>, 'user'=>, 'maxTokens'=>, 'temp'=>]. Returns ?string results in the
 * SAME ORDER as $requests (null for any that failed), so callers can keep row order.
 */
function pa_gemini_chat_multi(array $requests, int $maxConcurrent = 5): array
{
    $results = array_fill(0, count($requests), null);
    if (count($requests) === 0) {
        return $results;
    }

    $key = pa_gemini_key();
    if ($key === '') {
        error_log('profit-analyzer: GEMINI_API_KEY not configured');
        return $results;
    }

    // One request: no point spinning up curl_multi.
    if (count($requests) === 1) {
        $r = $requests[0];
        $results[0] = pa_gemini_chat($r['system'] ?? '', $r['user'] ?? '', $r['maxTokens'] ?? 4000, $r['temp'] ?? 0.1);
        return $results;
    }

    $maxConcurrent = max(1, $maxConcurrent);
    $mh = curl_multi_init();
    $handleIndex = []; // spl_object_id(handle) => request index
    $next = 0;
    $total = count($requests);

    // Add the next pending request to the multi handle; false when none remain.
    $launch = function () use (&$next, $total, $requests, $key, $mh, &$handleIndex): bool {
        if ($next >= $total) {
            return false;
        }
        $i = $next++;
        $r = $requests[$i];
        $ch = pa_gemini_build_handle($r['system'] ?? '', $r['user'] ?? '', $r['maxTokens'] ?? 4000, $r['temp'] ?? 0.1, $key);
        curl_multi_add_handle($mh, $ch);
        $handleIndex[spl_object_id($ch)] = $i;
        return true;
    };

    // Prime the window up to the cap.
    for ($k = 0; $k < $maxConcurrent; $k++) {
        if (!$launch()) {
            break;
        }
    }

    $running = 0;
    do {
        curl_multi_exec($mh, $running);
        while ($info = curl_multi_info_read($mh)) {
            if ($info['msg'] !== CURLMSG_DONE) {
                continue;
            }
            $ch = $info['handle'];
            $id = spl_object_id($ch);
            if (isset($handleIndex[$id])) {
                $resp = curl_multi_getcontent($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                $results[$handleIndex[$id]] = pa_gemini_parse_response($resp, $code, $err);
                unset($handleIndex[$id]);
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            $launch(); // backfill so the window stays full
        }
        if ($running > 0) {
            curl_multi_select($mh, 1.0); // block until activity, don't busy-spin
        }
    } while ($running > 0 || !empty($handleIndex));

    curl_multi_close($mh);
    return $results;
}

/** Build (but do not execute) a Gemini curl handle for one chat request. */
function pa_gemini_build_handle(string $systemPrompt, string $userPrompt, int $maxTokens, float $temperature, string $key)
{
    // A wide batch can work out a budget above 16k, so the cap is 32k and nothing is cut without a word.
    $maxTokens = max(1, min($maxTokens, 32000));
    $temperature = max(0.0, min(2.0, $temperature));

    $payload = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
        ],
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => $maxTokens,
            'responseMimeType' => 'application/json',
        ],
    ];
    if ($systemPrompt !== '') {
        $payload['system_instruction'] = ['parts' => [['text' => $systemPrompt]]];
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . PA_GEMINI_MODEL . ":generateContent?key={$key}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    return $ch;
}

/** Parse a Gemini HTTP result into the model's text content, or null on failure. */
function pa_gemini_parse_response($response, int $httpCode, string $curlError): ?string
{
    if ($response === false || $response === null || $response === '') {
        if ($curlError !== '') {
            error_log('profit-analyzer Gemini cURL error: ' . $curlError);
        }
        return null;
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? 'unknown error';
        error_log("profit-analyzer Gemini error ({$httpCode}): {$msg}");
        return null;
    }

    $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    return is_string($content) && $content !== '' ? $content : null;
}

/**
 * Strips a ```json ... ``` markdown fence if the model wrapped its JSON in one.
 * Mirrors JsonResponseHelper.StripMarkdownCodeBlock.
 */
function pa_strip_markdown_json(string $response): string
{
    $s = trim($response);
    if (strncmp($s, '```', 3) === 0) {
        // Drop the opening fence line (``` or ```json) and the closing fence.
        $s = preg_replace('/^```[a-zA-Z]*\s*\n?/', '', $s);
        $s = preg_replace('/\n?```\s*$/', '', $s);
        $s = trim($s);
    }
    return $s;
}
