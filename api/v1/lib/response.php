<?php
declare(strict_types=1);

/**
 * Response and error envelopes.
 *
 * One shape for success, one shape for failure, both stable across every
 * endpoint. The error envelope in particular is the part of an API that is
 * hardest to change once developers parse it, which is why it is settled here
 * before any resource exists.
 */

/** Headers every /v1 response carries. */
function api_send_common_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Argo-Version: ' . API_VERSION);
    header('Request-Id: ' . api_request_id());
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    // No Access-Control-Allow-Origin, because the API is called server to server and a key must never sit in front-end JavaScript.
}

/**
 * Thrown instead of exiting when API_TESTING is defined.
 *
 * api_json() ends the request, which makes every validator untestable in
 * process. Rather than duplicating validation logic somewhere test-shaped, the
 * exit becomes a throw under a flag that only the PHPUnit bootstrap sets.
 */
class ApiResponseSent extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly array $payload)
    {
        parent::__construct('API response sent with status ' . $status);
    }
}

function api_json(int $status, array $payload): void
{
    if (defined('API_TESTING') && API_TESTING) {
        throw new ApiResponseSent($status, $payload);
    }

    api_send_common_headers();
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Emit a Stripe-shaped error and stop.
 *
 * $type is the broad class a client switches on:
 *   authentication_error  bad or missing key
 *   invalid_request_error the request itself is wrong (most 4xx)
 *   rate_limit_error      too many requests
 *   idempotency_error     Idempotency-Key reused with a different body
 *   api_error             our fault
 *
 * $code is the specific, stable machine-readable reason.
 */
function api_error(
    int $status,
    string $type,
    string $code,
    string $message,
    ?string $param = null
): void {
    $error = [
        'type'       => $type,
        'code'       => $code,
        'message'    => $message,
        'doc_url'    => API_DOC_BASE . '/errors.php#' . $code,
        'request_id' => api_request_id(),
    ];
    if ($param !== null) {
        $error['param'] = $param;
    }
    api_json($status, ['error' => $error]);
}

/**
 * Collection envelope. `has_more` is what a client loops on; `url` mirrors
 * Stripe so pagination helpers in a generated SDK have somewhere to point.
 */
/**
 * Send an already-serialized body, used by the idempotent replay path.
 *
 * Separate from api_json because the cached body is a string we must return
 * byte for byte, and because it needs the same API_TESTING escape hatch: without
 * it the replay would exit() straight out of the test runner.
 */
function api_send_cached_response(int $status, string $rawBody): void
{
    if (defined('API_TESTING') && API_TESTING) {
        throw new ApiResponseSent($status, json_decode($rawBody, true) ?? []);
    }

    api_send_common_headers();
    header('Idempotent-Replayed: true');
    http_response_code($status);
    echo $rawBody;
    exit;
}

function api_list_response(array $data, bool $hasMore, string $url): array
{
    return [
        'object'   => 'list',
        'url'      => $url,
        'has_more' => $hasMore,
        'data'     => $data,
    ];
}

/**
 * Convert a DATETIME/DATE column to a unix timestamp, or null.
 * Stripe returns times as integers and so do we; a developer should never have
 * to guess our server's timezone to parse a date string.
 */
function api_timestamp(?string $value): ?int
{
    if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
        return null;
    }
    $ts = strtotime($value);
    return $ts === false ? null : $ts;
}

/** Decode a metadata JSON column into an object-shaped array. */
function api_decode_metadata(?string $json): object
{
    if ($json === null || $json === '') {
        return (object) [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? (object) $decoded : (object) [];
}
