<?php
/**
 * Stores the buyer's email against a redeemed licence key, and sends a verification link.
 *
 * Called by the desktop app straight after a successful redemption, and ONLY when that
 * redemption reported needs_email. Premium is already active by the time this runs: keys bought
 * through a reseller arrive as a pre-generated batch with no buyer details attached, so this is
 * the one moment an address can be asked for, and it must not stand between the customer and
 * what they paid for.
 *
 * Nothing here can revoke or withhold Premium. The worst outcome of every failure path below is
 * that we do not learn the address.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_sender.php';
require_once __DIR__ . '/../portal/portal-helper.php';

$response = [
    'success' => false,
    'status' => 'error',
    'message' => 'Invalid request method.'
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

$client_ip = get_client_ip();
if (rate_limit_hit('license_capture_email', $client_ip)) {
    http_response_code(429);
    header('Retry-After: ' . rate_limit_window('license_capture_email'));
    echo json_encode([
        'success' => false,
        'status' => 'rate_limited',
        'message' => 'Too many attempts. Please try again in ' . rate_limit_wait_phrase('license_capture_email') . '.'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$premium_key = trim($data['premium_key'] ?? '');
$device_id   = trim($data['device_id'] ?? '');
$email       = trim($data['email'] ?? '');

if ($premium_key === '' || $device_id === '') {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Premium key and device ID are required.'
    ]);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    echo json_encode([
        'success' => false,
        'status' => 'invalid_email',
        'message' => 'Please enter a valid email address.'
    ]);
    exit;
}

try {
    global $pdo;

    // The device must match the one that redeemed the key. Without this, knowing a key would be
    // enough to overwrite a stranger's contact address, and keys travel on printed cards.
    $stmt = $pdo->prepare("
        SELECT id, email, customer_email, device_id, redeemed_at
        FROM premium_subscription_keys
        WHERE subscription_key = ?
    ");
    $stmt->execute([$premium_key]);
    $row = $stmt->fetch();

    if (!$row || $row['redeemed_at'] === null) {
        echo json_encode([
            'success' => false,
            'status' => 'invalid_key',
            'message' => 'License key is not valid.'
        ]);
        exit;
    }

    if (!hash_equals((string) $row['device_id'], $device_id)) {
        echo json_encode([
            'success' => false,
            'status' => 'wrong_device',
            'message' => 'This license key is active on a different device.'
        ]);
        exit;
    }

    // Already on record. Reported as success because from the app's side there is nothing left
    // to do, and re-sending a verification email to an address we already hold is a good way to
    // look like a spammer.
    $existing = trim((string) ($row['email'] ?? '')) !== ''
        || trim((string) ($row['customer_email'] ?? '')) !== '';

    if ($existing) {
        echo json_encode([
            'success' => true,
            'status' => 'already_recorded',
            'message' => 'Thanks, we already have your email on file.'
        ]);
        exit;
    }

    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("
        UPDATE premium_subscription_keys
        SET customer_email = ?,
            customer_email_captured_at = NOW(),
            customer_email_token = ?,
            customer_email_source = 'app_redemption'
        WHERE id = ?
          AND customer_email IS NULL
    ");
    $stmt->execute([$email, $token, $row['id']]);

    if ($stmt->rowCount() === 0) {
        // Another request captured an address between the SELECT and here.
        echo json_encode([
            'success' => true,
            'status' => 'already_recorded',
            'message' => 'Thanks, we already have your email on file.'
        ]);
        exit;
    }

    // Sending is best effort. The address is already stored, which is the part that matters, so
    // a mail failure must not read to the customer as though their activation went wrong.
    $sent = false;
    try {
        $sent = send_license_email_verification($email, $token);
    } catch (Exception $e) {
        error_log('License verification email failed for key ' . $premium_key . ': ' . $e->getMessage());
    }

    $response = [
        'success' => true,
        'status' => 'recorded',
        'verification_sent' => (bool) $sent,
        'message' => 'Thanks! Check your inbox to confirm your email address.'
    ];
} catch (PDOException $e) {
    error_log('License email capture failed: ' . $e->getMessage());
    $response = [
        'success' => false,
        'status' => 'error',
        'message' => 'Could not save your email right now. Your Premium licence is still active.'
    ];
}

echo json_encode($response);
