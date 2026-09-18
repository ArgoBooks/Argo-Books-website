<?php
// Set headers for API response
header('Content-Type: application/json');

require_once __DIR__ . '/../../license_functions.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../portal/portal-helper.php';

// Initialize response array
$response = [
    'success' => false,
    'status' => 'error',
    'message' => 'Invalid request method.'
];

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limit per IP to slow brute-force / repeated takeover attempts.
    $client_ip = get_client_ip();
    if (rate_limit_exceeded('license_redeem', $client_ip)) {
        http_response_code(429);
        header('Retry-After: ' . rate_limit_window('license_redeem'));
        echo json_encode([
            'success' => false,
            'status' => 'rate_limited',
            'message' => 'Too many attempts. Please try again in ' . rate_limit_wait_phrase('license_redeem') . '.'
        ]);
        exit;
    }

    // Get the request data
    $data = json_decode(file_get_contents('php://input'), true);

    $premium_key = trim($data['premium_key'] ?? '');
    $device_id = trim($data['device_id'] ?? '');

    if (empty($premium_key)) {
        $response = [
            'success' => false,
            'status' => 'error',
            'message' => 'Premium key is required.'
        ];
    } elseif (empty($device_id)) {
        $response = [
            'success' => false,
            'status' => 'error',
            'message' => 'Device ID is required.'
        ];
    } else {
        $response = redeem_premium_key($premium_key, $device_id);
    }

    // Only a rejected key spends the budget: redeeming on a second machine is normal.
    if (empty($response['success'])) {
        rate_limit_record('license_redeem', $client_ip);
    } else {
        rate_limit_clear('license_redeem', $client_ip);
    }
}

// Return the JSON response
echo json_encode($response);
