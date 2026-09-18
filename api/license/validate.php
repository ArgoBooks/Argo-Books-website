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
    // Rate limit per IP. Generous, since the app validates on launch, but still
    // caps abusive enumeration.
    $client_ip = get_client_ip();
    if (rate_limit_exceeded('license_validate', $client_ip)) {
        http_response_code(429);
        header('Retry-After: ' . rate_limit_window('license_validate'));
        echo json_encode([
            'success' => false,
            'status' => 'rate_limited',
            'message' => 'Too many requests. Please try again in ' . rate_limit_wait_phrase('license_validate') . '.'
        ]);
        exit;
    }

    // Get the request data
    $data = json_decode(file_get_contents('php://input'), true);

    $license_key = trim($data['license_key'] ?? '');
    $device_id = trim($data['device_id'] ?? '');

    if (empty($license_key) || empty($device_id)) {
        $response = [
            'success' => false,
            'status' => 'error',
            'message' => 'License key and device ID are required.'
        ];
    } else {
        $response = validate_license($license_key, $device_id);
    }

    // The app validates on every launch, so counting successes would mean normal use
    // filling the budget meant for someone working through a list of keys.
    if (empty($response['success'])) {
        rate_limit_record('license_validate', $client_ip);
    } else {
        rate_limit_clear('license_validate', $client_ip);
    }
}

// Return the JSON response
echo json_encode($response);
