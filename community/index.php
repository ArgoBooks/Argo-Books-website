<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/community_functions.php';
require_once __DIR__ . '/users/user_functions.php';
require_once __DIR__ . '/report/ban_check.php';

require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../partials/fonts.php';

// Check for remember me cookie and auto-login user if valid
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    check_remember_me();
}

$posts = get_all_posts();
$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? \CommunityUsers\get_current_user() : null;

// Check if user is banned
$user_ban = null;
if ($is_logged_in && $current_user) {
    $user_ban = is_user_banned($current_user['id']);
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

    <!-- SEO Meta Tags -->
    <meta name="description" content="Join the Argo Books community forum. Report bugs, suggest features, share tips, and connect with other small business owners using Argo Books.">
    <meta name="keywords" content="argo books community, argo books forum, feature requests, bug reports, small business community">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Community Forum - Argo Books">
    <meta property="og:description" content="Join the Argo Books community forum. Report bugs, suggest features, and connect with other small business owners.">
    <meta property="og:url" content="https://argorobots.com/community/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Community Forum - Argo Books">
    <meta name="twitter:description" content="Join the Argo Books community forum. Report bugs, suggest features, and connect with other small business owners.">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/community/">

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Argo Books Community</title>

    <script src="../resources/scripts/main.js"></script>
    <script src="../resources/notifications/notifications.js" defer></script>
    <script src="../resources/scripts/utc-to-local.js" defer></script>
    <script src="index.js" defer></script>

    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/checkbox.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
    <link rel="stylesheet" href="../resources/notifications/notifications.css">
    <!-- Brand typefaces (Fraunces display + IBM Plex Sans body), matched to the rest of the site -->
    <?= argo_font_links('default', '    ') ?>
    <link rel="stylesheet" href="../resources/styles/typography.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>
    <main>

    <div class="hero">
        <div class="hero-content">
            <h1>Argo Books Community</h1>
            <p>Report bugs and request features for Argo Books</p>
            <a href="guidelines.php" class="guidelines-btn">
                <?= svg_icon('document-lines', 16) ?>
                Community Guidelines
            </a>
        </div>
    </div>

    <div class="community-wrapper">
        <?php if (!$is_logged_in): ?>
            <div class="login-prompt">
                <p><a href="users/login.php">Log in</a> or <a href="users/register.php">create an account</a> to post, comment, or vote.</p>
            </div>
        <?php endif; ?>

        <?php if ($is_logged_in && $user_ban): ?>
            <div class="ban-notice">
                <h4>Cannot Create Posts</h4>
                <p><?php echo htmlspecialchars(get_ban_message($user_ban)); ?></p>
            </div>
        <?php endif; ?>

        <div class="community-actions">
            <div class="action-left">
                <?php if ($is_logged_in && $user_ban): ?>
                    <button class="create-new-post btn btn-blue" disabled style="opacity: 0.5; cursor: not-allowed;">Create New Post</button>
                <?php elseif ($is_logged_in): ?>
                    <a href="create_post.php" class="create-new-post btn btn-blue">Create New Post</a>
                <?php else: ?>
                    <a href="users/login.php" class="login-to-post btn btn-blue">Log in to Post</a>
                <?php endif; ?>

                <!-- Combined Sort/Filter dropdown -->
                <div class="filter-dropdown">
                    <select id="sort-filter" class="filter-select">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="most_voted">Most Voted</option>
                        <option value="open_only">Open Posts Only</option>
                        <option value="in_progress_only">In Progress Only</option>
                        <option value="completed_only">Completed Only</option>
                        <option value="declined_only">Declined Only</option>
                    </select>
                </div>
            </div>

            <div class="search-box">
                <input type="text" id="search-posts" placeholder="Search...">
                <button id="search-btn" class="search-icon-button" aria-label="Search">
                    <?= svg_icon('search', 20, '', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </button>
            </div>
        </div>

        <?php if ($is_logged_in && (isset($current_user['role']) && $current_user['role'] === 'admin')): ?>
            <!-- Add bulk selection controls (admin only) -->
            <div class="bulk-actions" style="display: none;">
                <div class="selection-controls">
                    <div class="checkbox">
                        <input type="checkbox" id="select-all-posts" name="select-all-posts">
                        <label for="select-all-posts">Select All</label>
                    </div>
                    <span class="selected-count">0 selected</span>
                </div>
                <button id="delete-selected" class="btn btn-red" disabled>Delete Selected</button>
            </div>
        <?php endif; ?>

        <div id="posts-container" class="posts-container">
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <h3>No posts yet</h3>
                    <p>Be the first to create a post in our community.</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php
                    // Get user's vote for this post
                    $user_vote = $is_logged_in ? get_user_vote($post['id'], $current_user['email']) : 0;
                    ?>
                    <div class="post-card" data-post-id="<?php echo $post['id']; ?>" data-post-type="<?php echo $post['post_type']; ?>" data-post-status="<?php echo $post['status']; ?>">
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
                        <!-- Post content remains the same -->
                        <div class="post-content">
                            <a href="view_post.php?id=<?php echo $post['id']; ?>" class="link-no-underline">
                                <div class="post-header">
                                    <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                    <div class="post-meta">
                                        <span class="post-type <?php echo $post['post_type']; ?>">
                                            <?php echo $post['post_type'] === 'bug' ? 'Bug Report' : 'Feature Request'; ?>
                                        </span>
                                        <span class="post-status <?php echo $post['status']; ?>">
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
                            </a>
                            <div class="post-footer">
                                <div class="post-info">
                                    <span class="post-author">
                                        Posted by
                                        <a href="users/profile.php?username=<?php echo urlencode(trim($post['user_name'])); ?>" class="link-no-underline">
                                            <span class="author-avatar">
                                                <?php if (!empty($post['avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($post['avatar']); ?>" alt="Avatar">
                                                <?php else: ?>
                                                    <span class="author-avatar-placeholder">
                                                        <?php echo strtoupper(substr(trim($post['user_name']), 0, 1)); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span><?php echo htmlspecialchars(trim($post['user_name'])); ?>
                                        </a>
                                    </span>

                                    <!-- Date -->
                                    <span class="post-date" data-timestamp="<?php echo strtotime($post['created_at']); ?>">
                                        <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                    </span>

                                    <!-- Views -->
                                    <span class="post-views">
                                        <?= svg_icon('eye', 14, '', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                                        <?php echo (isset($post['views']) && (int)$post['views'] > 0) ? (int)$post['views'] : 0; ?> <?php echo ((isset($post['views']) && (int)$post['views'] == 1) ? 'view' : 'views'); ?>
                                    </span>
                                </div>
                                <div class="post-actions">
                                    <a href="view_post.php?id=<?php echo $post['id']; ?>" class="link-no-underline">
                                        Comments (<?php echo get_comment_count($post['id']); ?>)
                                    </a>
                                    <?php if (
                                        $is_logged_in && (
                                            (isset($current_user['role']) && $current_user['role'] === 'admin') ||
                                            (isset($post['user_id']) && isset($current_user['id']) && $post['user_id'] == $current_user['id'])
                                        )
                                    ): ?>
                                        <?php if ($is_logged_in && isset($current_user['role']) && $current_user['role'] === 'admin'): ?>
                                            <div class="admin-actions">
                                                <select class="status-update" data-post-id="<?php echo $post['id']; ?>">
                                                    <option value="open" <?php echo $post['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                                    <option value="in_progress" <?php echo $post['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="completed" <?php echo $post['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="declined" <?php echo $post['status'] === 'declined' ? 'selected' : ''; ?>>Declined</option>
                                                </select>
                                                <button class="delete-post-btn" data-post-id="<?php echo $post['id']; ?>">Delete</button>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Loading indicator -->
        <div id="loading-indicator" style="display: none;">
            <div class="spinner"></div>
            <p>Loading more posts...</p>
        </div>
    </div>

    </main>

    <footer class="footer">
        <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
    </footer>
</body>

</html>