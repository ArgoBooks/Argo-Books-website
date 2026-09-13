<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mentions/mentions.php';
require_once __DIR__ . '/community_functions.php';
require_once __DIR__ . '/users/user_functions.php';
require_once __DIR__ . '/formatting/formatting_functions.php';
require_once __DIR__ . '/report/ban_check.php';

require_once __DIR__ . '/../resources/icons.php';

// Check for remember me cookie and auto-login user if valid
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    check_remember_me();
}

$is_logged_in = is_user_logged_in();
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
$username = $is_logged_in ? ($_SESSION['username'] ?? 'Unknown') : '';
$email = $is_logged_in ? ($_SESSION['email'] ?? '') : '';
$role = $is_logged_in ? ($_SESSION['role'] ?? 'user') : '';

// Check if user is banned
$user_ban = null;
if ($is_logged_in && $user_id > 0) {
    $user_ban = is_user_banned($user_id);
}

$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0; // Get post ID from URL parameter
$post = get_post($post_id);
$has_metadata = false;
$metadata = null;

// Redirect to index if no valid post ID or if post not found
if ($post_id <= 0 || !$post) {
    header('Location: index.php');
    exit;
}

// Check if metadata column exists
$result = $pdo->query("SHOW COLUMNS FROM community_posts LIKE 'metadata'");
$metadata_exists = ($result->fetch() !== false);

if ($metadata_exists) {
    // Get metadata if it exists
    $stmt = $pdo->prepare('SELECT metadata FROM community_posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $row = $stmt->fetch();

    if ($row && !empty($row['metadata'])) {
        $metadata = json_decode($row['metadata'], true);
        $has_metadata = !empty($metadata);
    }
}

// Get last edit for the post
$stmt = $pdo->prepare('SELECT h.*, u.username
    FROM post_edit_history h
    LEFT JOIN community_users u ON h.user_id = u.id
    WHERE h.post_id = ?
    ORDER BY h.edited_at DESC
    LIMIT 1');
$stmt->execute([$post_id]);
$post_last_edit = $stmt->fetch();

$current_user = $is_logged_in ? \CommunityUsers\get_current_user() : null;

// Increment view count (only once per session to count unique views)
$viewed_posts = isset($_SESSION['viewed_posts']) ? $_SESSION['viewed_posts'] : array();
$is_admin = $is_logged_in && (isset($current_user['role']) && $current_user['role'] === 'admin');

if (!$is_admin && !in_array($post_id, $viewed_posts)) {
    // Update view count in database
    $stmt = $pdo->prepare('UPDATE community_posts SET views = views + 1 WHERE id = ?');
    $stmt->execute([$post_id]);

    // Add post to viewed posts in session
    $viewed_posts[] = $post_id;
    $_SESSION['viewed_posts'] = $viewed_posts;

    // Update the post data with new view count
    $post = get_post($post_id);
}

$comments = get_post_comments($post_id);
$can_edit_post = ($role === 'admin') || (isset($post['user_id']) && !empty($post['user_id']) && $post['user_id'] == $user_id);
$user_vote = $is_logged_in ? get_user_vote($post_id, $email) : 0;

// Check for status messages in URL parameters
$status_message = '';
if (isset($_GET['created']) && $_GET['created'] == '1') {
    $status_message = 'Post created successfully!';
} elseif (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $status_message = 'Post updated successfully!';
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title><?php echo htmlspecialchars($post['title']); ?> - Argo Community</title>

    <script src="view-post.js"></script>
    <script src="../resources/scripts/main.js"></script>
    <script src="../resources/notifications/notifications.js" defer></script>
    <script src="../resources/scripts/utc-to-local.js" defer></script>
    <script src="formatting/text-formatting.js" defer></script>

    <link rel="stylesheet" href="create-post.css">
    <link rel="stylesheet" href="view-post.css">
    <link rel="stylesheet" href="rate-limit.css">
    <link rel="stylesheet" href="formatting/formatted-text.css">
    <link rel="stylesheet" href="report/report.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/header/dark.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
    <link rel="stylesheet" href="../resources/notifications/notifications.css">

    <!-- Mentions system -->
    <link rel="stylesheet" href="mentions/mentions.css">
    <script src="mentions/mentions.js" defer></script>
    <script src="mentions/init.js" defer></script>
</head>

<body>
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>

    <nav class="post-breadcrumb" aria-label="Breadcrumb">
        <a href="./">Community</a><span class="sep" aria-hidden="true">/</span><span><?php echo $post['post_type'] === 'bug' ? 'Bug Report' : 'Feature Request'; ?></span>
    </nav>

    <?php if ($status_message): ?>
        <div class="community-wrapper">
            <div class="success-message">
                <?php echo htmlspecialchars($status_message); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="community-container">
        <div class="page-header">
            <div class="header-left">
                <a href="./" class="btn back-button">
                    <?= svg_icon('arrow-back', 16) ?>
                    Back to All Posts
                </a>
            </div>

            <div class="header-center">
                <div class="post-status-display">
                    <span class="post-status-label">Status:</span>
                    <span class="post-status post-status-large <?php echo $post['status']; ?>">
                        <?php
                        switch ($post['status']) {
                            case 'open':
                                echo 'Open';
                                break;
                            case 'in_progress':
                                echo 'In Progress';
                                break;
                            case 'completed':
                                echo 'Completed';
                                break;
                            case 'declined':
                                echo 'Declined';
                                break;
                        }
                        ?>
                    </span>
                </div>
            </div>

            <div class="header-right">
                <?php if ($role === 'admin'): ?>
                    <select class="status-update" data-post-id="<?php echo $post['id']; ?>">
                        <option value="open" <?php echo $post['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo $post['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $post['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="declined" <?php echo $post['status'] === 'declined' ? 'selected' : ''; ?>>Declined</option>
                    </select>
                <?php endif; ?>
            </div>
        </div>

        <div class="post-detail">
            <div class="post-card" data-post-id="<?php echo $post['id']; ?>" data-post-type="<?php echo $post['post_type']; ?>">

                <!-- Post votes -->
                <div class="post-votes">
                    <button class="vote-btn upvote <?php echo $user_vote === 1 ? 'voted' : ''; ?>" data-post-id="<?php echo $post['id']; ?>" data-vote="up">
                        <?= svg_icon('vote-up', 24) ?>
                    </button>

                    <span class="vote-count"><?php echo $post['votes']; ?></span>
                    <button class="vote-btn downvote <?php echo $user_vote === -1 ? 'voted' : ''; ?>" data-post-id="<?php echo $post['id']; ?>" data-vote="down">
                        <?= svg_icon('vote-down', 24) ?>
                    </button>
                </div>

                <!-- Post content -->
                <div class="post-content">
                    <div class="post-header">
                        <h2 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                        <div class="post-meta">
                            <span class="post-type <?php echo $post['post_type']; ?>">
                                <?php echo $post['post_type'] === 'bug' ? 'Bug Report' : 'Feature Request'; ?>
                            </span>

                            <!-- Edit Post button -->
                            <?php if ($can_edit_post): ?>
                                <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="edit-post-btn">Edit</a>
                            <?php endif; ?>

                            <!-- Delete post button -->
                            <?php if ($can_edit_post): ?>
                                <button class="delete-post-btn" data-post-id="<?php echo $post['id']; ?>">Delete</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Post content for bug reports with metadata -->
                    <?php if ($post['post_type'] === 'bug' && $has_metadata): ?>
                        <div class="post-body">
                            <div class="bug-report-container">
                                <div class="bug-report-header">
                                    <h3>Bug Report Details</h3>
                                </div>

                                <div class="bug-info-grid">
                                    <?php if (!empty($metadata['bug_location'])): ?>
                                        <div class="bug-info-item">
                                            <div class="bug-info-label">Location:</div>
                                            <div class="bug-info-value"><?php echo $metadata['bug_location'] === 'website' ? 'Website' : 'Argo Books Application'; ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($metadata['bug_version'])): ?>
                                        <div class="bug-info-item">
                                            <div class="bug-info-label">Version:</div>
                                            <div class="bug-info-value"><?php echo process_mentions(render_formatted_text($metadata['bug_version'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($metadata['bug_steps'])): ?>
                                    <div class="bug-info-section">
                                        <div class="bug-info-section-title">Steps to Reproduce</div>
                                        <div class="bug-info-section-content"><?php echo process_mentions(render_formatted_text($metadata['bug_steps'])); ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="bug-results-container">
                                    <?php if (!empty($metadata['bug_expected'])): ?>
                                        <div class="bug-info-section">
                                            <div class="bug-info-section-title">Expected Result</div>
                                            <div class="bug-info-section-content"><?php echo process_mentions(render_formatted_text($metadata['bug_expected'])); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($metadata['bug_actual'])): ?>
                                        <div class="bug-info-section">
                                            <div class="bug-info-section-title">Actual Result</div>
                                            <div class="bug-info-section-content"><?php echo process_mentions(render_formatted_text($metadata['bug_actual'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="bug-info-section">
                                    <div class="bug-info-section-title">Additional Details</div>
                                    <div class="bug-info-section-content"><?php echo process_mentions(render_formatted_text($post['content'])); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Regular post content display for non-bug posts or bugs without metadata -->
                        <div class="post-body">
                            <?php echo process_mentions(render_formatted_text($post['content'])); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Post footer -->
                    <div class="post-footer">
                        <div class="post-info">
                            <!-- Author -->
                            <span class="post-author">
                                Posted by
                                <a href="users/profile.php?username=<?php echo urlencode($post['user_name']); ?>" class="link-no-underline">
                                    <span class="author-avatar">
                                        <?php if (!empty($post['avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($post['avatar']); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <span class="author-avatar-placeholder">
                                                <?php echo strtoupper(substr($post['user_name'], 0, 1)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span><?php echo htmlspecialchars($post['user_name']); ?>
                                </a>
                            </span>

                            <!-- Date -->
                            <span class="post-date" data-timestamp="<?php echo strtotime($post['created_at']); ?>">
                                <?php echo date('M j, Y g:i a', strtotime($post['created_at'])); ?>
                            </span>

                            <!-- Views -->
                            <span class="post-views">
                                <?= svg_icon('eye', 14, '', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                                <?php echo (isset($post['views']) && (int)$post['views'] > 0) ? (int)$post['views'] : 0; ?> <?php echo ((isset($post['views']) && (int)$post['views'] == 1) ? 'view' : 'views'); ?>
                            </span>

                            <!-- Last edited -->
                            <?php if ($post_last_edit): ?>
                                <span class="post-last-edited">
                                    <a class="link" href="post_history.php?id=<?php echo $post_id; ?>">Last edited by <?php echo htmlspecialchars($post_last_edit['username']); ?></a>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Report button -->
                        <?php if ($is_logged_in && $post['user_id'] != $user_id): ?>
                            <button class="report-btn" data-content-type="post" data-content-id="<?php echo $post['id']; ?>" title="Report this post">
                                <?= svg_icon('flag', 16) ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Comments -->
            <div class="comments-section">
                <h3>
                    <?php 
                        $count = count($comments);
                        echo $count . ' comment' . ($count != 1 ? 's' : '');
                    ?>
                </h3>

                <div class="comments-container">
                    <?php foreach ($comments as $comment): ?>
                        <?php
                        $can_delete_comment = ($role === 'admin') || (isset($comment['user_id']) && !empty($comment['user_id']) && $comment['user_id'] == $user_id);
                        $can_edit_comment = ($role === 'admin') || (isset($comment['user_id']) && !empty($comment['user_id']) && $comment['user_id'] == $user_id);
                        $user_comment_vote = $is_logged_in ? get_user_comment_vote($comment['id'], $email) : 0;
                        $comment_votes = isset($comment['votes']) ? (int)$comment['votes'] : 0;
                        ?>
                        <div class="comment" data-comment-id="<?php echo $comment['id']; ?>">
                            <!-- Vertical vote controls on left -->
                            <div class="comment-votes">
                                <button class="comment-vote-btn upvote <?php echo $user_comment_vote === 1 ? 'voted' : ''; ?>" data-comment-id="<?php echo $comment['id']; ?>" data-vote="up">
                                    <?= svg_icon('vote-up', 20) ?>
                                </button>
                                <span class="comment-vote-count"><?php echo $comment_votes; ?></span>
                                <button class="comment-vote-btn downvote <?php echo $user_comment_vote === -1 ? 'voted' : ''; ?>" data-comment-id="<?php echo $comment['id']; ?>" data-vote="down">
                                    <?= svg_icon('vote-down', 20) ?>
                                </button>
                            </div>

                            <!-- Main comment content area -->
                            <div class="comment-main">
                                <div class="comment-header">
                                    <div class="comment-author-info">
                                        <a href="users/profile.php?username=<?php echo urlencode($comment['user_name']); ?>" class="link-no-underline">
                                            <span class="author-avatar">
                                                <?php if (!empty($comment['avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($comment['avatar']); ?>" alt="Avatar">
                                                <?php else: ?>
                                                    <span class="author-avatar-placeholder">
                                                        <?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span><?php echo htmlspecialchars($comment['user_name']); ?>
                                        </a>

                                        <!-- Date -->
                                        <span class="comment-date" data-timestamp="<?php echo strtotime($comment['created_at']); ?>">
                                            <?php echo date('M j, Y g:i a', strtotime($comment['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="comment-controls">
                                        <div class="comment-actions-wrapper">
                                            <?php if ($can_edit_comment || $can_delete_comment): ?>
                                                <div class="comment-actions">
                                                    <?php if ($can_edit_comment): ?>
                                                        <button class="edit-comment-btn" data-comment-id="<?php echo $comment['id']; ?>">Edit</button>
                                                    <?php endif; ?>

                                                    <?php if ($can_delete_comment): ?>
                                                        <button class="delete-comment-btn" data-comment-id="<?php echo $comment['id']; ?>">Delete</button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Report button -->
                                            <?php if ($is_logged_in && $comment['user_id'] != $user_id): ?>
                                                <button class="report-btn report-btn-comment" data-content-type="comment" data-content-id="<?php echo $comment['id']; ?>" title="Report this comment">
                                                    <?= svg_icon('flag', 14) ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="comment-content">
                                    <?php echo process_mentions(htmlspecialchars($comment['content'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="comment-form">
                    <?php if ($is_logged_in): ?>
                        <?php if ($user_ban): ?>
                            <!-- User is banned - show ban message -->
                            <div class="comment-ban-notice">
                                <h4>Cannot Post Comments</h4>
                                <p style="margin-bottom: 0;"><?php echo htmlspecialchars(get_ban_message($user_ban)); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="comments-disabled-message" style="<?php echo ($post['status'] === 'completed' || $post['status'] === 'declined') ? '' : 'display: none;'; ?>">
                                <p>
                                    <?php
                                    if ($post['status'] === 'completed') {
                                        echo 'Comments are disabled for completed posts.';
                                    } elseif ($post['status'] === 'declined') {
                                        echo 'Comments are disabled for declined posts.';
                                    }
                                    ?>
                                </p>
                            </div>

                            <?php if ($post['status'] !== 'completed' && $post['status'] !== 'declined'): ?>
                                <form id="add-comment-form" data-post-id="<?php echo $post['id']; ?>">
                                    <h4>Add a comment</h4>
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <div class="form-group">
                                        <textarea id="comment_content" name="comment_content" class="mentionable" rows="4" required></textarea>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-gray">Submit Comment</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="login-required">
                            <p>Please <a class="link" href="users/login.php">log in</a> or
                                <a class="link" href="users/register.php">create an account</a> to comment on this post.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
    </footer>

    <!-- This will be used by mentions.js -->
    <div class="mention-dropdown" id="mentionDropdown"></div>

    <!-- Report Modal -->
    <div id="reportModal" class="report-modal" style="display: none;">
        <div class="report-modal-content">
            <div class="report-modal-header">
                <h3>Report Content</h3>
                <button class="report-modal-close">&times;</button>
            </div>
            <form id="reportForm">
                <input type="hidden" id="reportContentType" name="content_type">
                <input type="hidden" id="reportContentId" name="content_id">

                <div class="form-group">
                    <label for="violationType">This content violates our policy because:</label>
                    <select id="violationType" name="violation_type" required>
                        <option value="">Select a reason...</option>
                        <option value="spam">Spam or advertising</option>
                        <option value="harassment">Harassment or bullying</option>
                        <option value="hateful">Hateful or discriminatory content</option>
                        <option value="inappropriate">Inappropriate or offensive content</option>
                        <option value="misinformation">Misinformation or false information</option>
                        <option value="off-topic">Off-topic or irrelevant</option>
                        <option value="other">Other (please specify below)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="additionalInfo">Additional information (optional):</label>
                    <textarea id="additionalInfo" name="additional_info" rows="4" placeholder="Please provide any additional details..."></textarea>
                </div>

                <div class="report-modal-actions">
                    <button type="button" class="btn btn-outline report-modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-red">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <script src="report/report.js" defer></script>
</body>

</html>