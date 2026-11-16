<?php
require_once __DIR__ . '/admin_session.php';
require_once __DIR__ . '/trusted_devices.php';

revoke_current_trusted_device();

// Unset all session variables
$_SESSION = array();

session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
