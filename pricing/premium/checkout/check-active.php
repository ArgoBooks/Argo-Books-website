<?php
// The PayPal button calls this before opening PayPal, because PayPal takes the first payment the moment the customer approves.
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/checkout_helpers.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

echo json_encode([
    'success' => true,
    'active'  => premium_active_subscription($pdo, (int) $_SESSION['user_id']) !== null,
]);
