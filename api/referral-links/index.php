<?php
/**
 * The owner's referral links, for scripts.
 *
 *   GET  /api/referral-links/    lists every link with its visits and installs
 *   POST /api/referral-links/    adds links, body {"links": [{"source_code", "name", "category", "target_url"}]}
 *
 * Both take Authorization: Bearer <REFERRAL_API_TOKEN>. See read-me/Referral-links-API.md.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../referral_links.php';

/** The most links one request may add. */
const REFERRAL_API_BATCH_LIMIT = 50;

function referral_api_answer(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$token = $_ENV['REFERRAL_API_TOKEN'] ?? '';
if (strlen($token) < 32) {
    // No token, or one too short to be a secret, means the endpoint does not exist yet.
    referral_api_answer(['ok' => false, 'reason' => 'NOT_CONFIGURED', 'message' => 'The referral links API is not set up.'], 503);
}

$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$given = preg_match('/^Bearer\s+(\S+)$/i', trim((string) $header), $m) ? $m[1] : '';
if (!hash_equals($token, $given)) {
    error_log('referral links API: request refused, bad or missing token, from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    usleep(500000);
    referral_api_answer(['ok' => false, 'reason' => 'UNAUTHORIZED', 'message' => 'Missing or wrong token.'], 401);
}

global $pdo;
$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    referral_api_answer(['ok' => true, 'links' => referral_api_list($pdo)]);
}
if ($method !== 'POST') {
    referral_api_answer(['ok' => false, 'reason' => 'BAD_METHOD', 'message' => 'Use GET or POST.'], 405);
}

$in = json_decode((string) file_get_contents('php://input'), true);
$links = is_array($in) ? ($in['links'] ?? null) : null;
if (!is_array($links) || $links === [] || !array_is_list($links) || count($links) > REFERRAL_API_BATCH_LIMIT) {
    referral_api_answer(['ok' => false, 'reason' => 'BAD_INPUT', 'message' => 'Send {"links": [...]} with 1 to ' . REFERRAL_API_BATCH_LIMIT . ' links.'], 400);
}

// One link's mistake is reported beside it, so the rest of the batch still goes through.
$results = [];
foreach ($links as $link) {
    try {
        $results[] = referral_api_add($pdo, is_array($link) ? $link : []);
    } catch (InvalidArgumentException $e) {
        $results[] = ['status' => 'refused', 'source_code' => is_array($link) ? ($link['source_code'] ?? null) : null, 'message' => $e->getMessage()];
    }
}
referral_api_answer(['ok' => true, 'results' => $results]);
