<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../email_sender.php';

// Check if temp_user_id is set
if (!isset($_SESSION['temp_user_id'])) {
    // Redirect to registration page if no temp user ID exists
    header('Location: register.php?error=no_session');
    exit;
}

$user_id = $_SESSION['temp_user_id'];

// Get user details from database
$stmt = $pdo->prepare('SELECT id, email, username, email_verified FROM community_users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: register.php?error=user_not_found');
    exit;
}

// Check if user is already verified
if ($user['email_verified'] == 1) {
    header('Location: login.php?message=already_verified');
    exit;
}

if (rate_limit_hit('community_resend_verification', (string) $user_id)) {
    header('Location: verify_code.php?error=too_many_resends');
    exit;
}

$new_verification_code = generate_verification_code();

// Update the database with the new verification code
$stmt = $pdo->prepare('UPDATE community_users SET verification_code = ? WHERE id = ?');
$update_result = $stmt->execute([$new_verification_code, $user_id]);

if (!$update_result) {
    header('Location: verify_code.php?error=update_failed');
    exit;
}

$email_sent = send_verification_email($user['email'], $new_verification_code, $user['username']);

if ($email_sent) {
    // Redirect back to verification page with success message
    header('Location: verify_code.php?message=code_resent');
    exit;
} else {
    // Redirect back with error message
    header('Location: verify_code.php?error=email_failed');
    exit;
}
