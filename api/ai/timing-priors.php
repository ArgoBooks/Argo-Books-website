<?php
/**
 * GET /api/ai/timing-priors.php
 *
 * Serves aggregated AI-operation timing priors for the CURRENTLY CONFIGURED Gemini
 * model so the Argo Books desktop app can drive accurate, smooth progress bars. The
 * app fetches this occasionally and caches it; when the model is swapped server-side,
 * the priors retrain from real traffic with no app release.
 *
 * Read-only. Auth is optional (used only for rate-limit bucketing). 1h HTTP cache.
 *
 * Response:
 *   { "success": true, "model": "gemini-2.5-flash", "window_days": 14,
 *     "load_factor": 1.0,
 *     "priors": [ { "operation": "...", "p50_ms": ..., "p90_ms": ...,
 *                   "sample_count": ..., "avg_size_feature": ...,
 *                   "avg_output_tokens": ..., "per_page_ms": ... }, ... ],
 *     "generated_at": "..." }
 */

require_once __DIR__ . '/../portal/portal-helper.php';
require_once __DIR__ . '/_timing.php';

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

set_portal_headers();
require_method(['GET']);

// Optional identity, used ONLY to bucket the rate limit (no auth required to read).
// The licence is checked against the database; the device header is whatever the caller
// typed, so a licensed request must not end up in the bucket its own header names.
$license = authenticate_license_request();
$deviceHash = $license ? null : authenticate_device_request();
$rateLimitId = $license
    ? substr($license['license_key_hash'], 0, 16)
    : ($deviceHash ? substr($deviceHash, 0, 16) : substr(hash('sha256', get_client_ip()), 0, 16));
if (rate_limit_hit('ai_priors', $rateLimitId)) {
    send_rate_limited_response('ai_priors');
}

$model = $_ENV['GEMINI_MODEL'] ?? 'gemini-3.1-flash-lite';

// p50/p90 change slowly, so a 1h shared cache is fine. load_factor is recomputed at
// most every 60s server-side, so occasional client polls still see it move.
header('Cache-Control: public, max-age=3600');

send_json_response(200, [
    'success' => true,
    'model' => $model,
    'window_days' => 14,
    'load_factor' => ai_timing_load_factor(),
    'priors' => ai_timing_compute_priors($model),
    'generated_at' => date('c'),
]);
