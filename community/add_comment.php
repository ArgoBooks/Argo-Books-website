<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/community_functions.php';
require_once __DIR__ . '/users/user_functions.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/report/ban_check.php';

header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Check if user is logged in
if (!is_user_logged_in()) {
    $response['message'] = 'You must be logged in to comment';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$current_user = \CommunityUsers\get_current_user();

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $response['message'] = 'Invalid request. Please refresh and try again.';
        echo json_encode($response);
        exit;
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $content = isset($_POST['comment_content']) ? trim($_POST['comment_content']) : '';

    // Get user data from session
    $username = $_SESSION['username'] ?? 'Unknown';
    $email = $_SESSION['email'] ?? '';

    // Check if user is banned
    $ban = is_user_banned($user_id);
    if ($ban) {
        $response['success'] = false;
        $response['message'] = get_ban_message($ban);
        $response['banned'] = true;
        echo json_encode($response);
        exit;
    }

    // Check rate limit for comments
    $rate_limit_message = check_rate_limit($user_id, 'comment');

    if ($rate_limit_message !== false) {
        // User has hit rate limit
        $response['success'] = false;
        $response['message'] = 'You are commenting too frequently';
        $response['rate_limited'] = true;

        // Format the rate limit message with proper styling
        $response['html_message'] = $rate_limit_message;

        // Extract reset timestamp from the message for frontend countdown
        if (preg_match('/data-reset-timestamp="(\d+)"/', $rate_limit_message, $matches)) {
            $response['reset_timestamp'] = intval($matches[1]);
        } else {
            // Fallback to 5 minutes from now if no timestamp found
            $response['reset_timestamp'] = time() + 300;
        }

        echo json_encode($response);
        exit;
    }

    // Basic validation
    if (empty($content)) {
        $response['message'] = 'All fields are required';
    } elseif (strlen($content) > 2000) {
        $response['message'] = 'Comment is too long (maximum 2,000 characters)';
    } else {
        // Verify post exists
        $post = get_post($post_id);

        if (!$post) {
            $response['message'] = 'Post not found';
        } else {
            // Add the comment with the logged-in user_id so the row is associated correctly from the start (and so the post-author self-reply guard inside add_comment() works).
            $comment = add_comment($post_id, $username, $email, $content, $user_id);

            if ($comment) {
                $response = [
                    'success' => true,
                    'message' => 'Comment added successfully',
                    'comment' => $comment
                ];
            } else {
                $response['message'] = 'Error adding comment to the database';
            }
        }
    }
}

// Send the response
echo json_encode($response);
