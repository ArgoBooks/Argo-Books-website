<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/community_functions.php';
require_once __DIR__ . '/users/user_functions.php';

require_once __DIR__ . '/../resources/icons.php';

// Check for remember me cookie and auto-login user if valid
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    check_remember_me();
}

// Get post ID from URL
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Redirect to index if no valid post ID
if ($post_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get the current post
$post = get_post($post_id);

// Redirect if post not found
if (!$post) {
    header('Location: index.php');
    exit;
}

// Check if metadata column exists in post_edit_history
$pdo->query("SHOW COLUMNS FROM post_edit_history LIKE 'metadata'");

// Fetch the current post metadata
$current_metadata = null;
$result = $pdo->query("SHOW COLUMNS FROM community_posts LIKE 'metadata'");
$metadata_exists = ($result->fetch() !== false);

if ($metadata_exists) {
    $stmt = $pdo->prepare('SELECT metadata FROM community_posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $row = $stmt->fetch();

    if ($row && !empty($row['metadata'])) {
        $current_metadata = $row['metadata'];
    }
}

// Fetch edit history ordered by newest first
$stmt = $pdo->prepare('SELECT h.*, u.username
    FROM post_edit_history h
    LEFT JOIN community_users u ON h.user_id = u.id
    WHERE h.post_id = ?
    ORDER BY h.edited_at DESC');
$stmt->execute([$post_id]);

$history = [];
while ($row = $stmt->fetch()) {
    $history[] = $row;
}

// The current version is not in the edit history, so add it as the most current version
$current_post = [
    'id' => 'current',
    'title' => $post['title'],
    'content' => $post['content'],
    'metadata' => $current_metadata,
    'edited_at' => isset($post['updated_at']) && $post['updated_at'] != $post['created_at'] ? $post['updated_at'] : $post['created_at'],
    'username' => $post['user_name'],
    'user_id' => $post['user_id'],
    'is_current' => true,
    'is_original' => count($history) === 0
];

// Add the current version at the beginning
array_unshift($history, $current_post);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Post Edit History - Argo Community</title>

    <script src="../resources/scripts/main.js"></script>
    <script src="../resources/scripts/utc-to-local.js" defer></script>
    <script src="post-history-diff.js" defer></script>

    <link rel="stylesheet" href="post-history.css">
    <link rel="stylesheet" href="view-post.css">
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/header/dark.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>

    <nav class="post-breadcrumb" aria-label="Breadcrumb">
        <a href="./">Community</a><span class="sep" aria-hidden="true">/</span><a href="view_post.php?id=<?php echo $post_id; ?>"><?php echo $post['post_type'] === 'bug' ? 'Bug Report' : 'Feature Request'; ?></a><span class="sep" aria-hidden="true">/</span><span>Edit History</span>
    </nav>

    <div class="community-wrapper">
        <div class="page-header">
            <a href="view_post.php?id=<?php echo $post_id; ?>" class="btn back-button">
                <?= svg_icon('arrow-back', 16) ?>
                Back to Post
            </a>
        </div>

        <div class="history-container">
            <div class="history-header">
                <h2>Edit History for: <?php echo htmlspecialchars($post['title']); ?></h2>
            </div>

            <?php if (count($history) <= 1 && !isset($history[0]['is_original'])): ?>
                <div class="no-history">
                    <p>This post has not been edited yet.</p>
                </div>
            <?php else: ?>
                <?php
                // Initialize previous version for comparison
                $prev_metadata = null;

                foreach ($history as $index => $version):
                    // Parse metadata if exists
                    $version_metadata = null;
                    if (isset($version['metadata']) && !empty($version['metadata'])) {
                        $version_metadata = json_decode($version['metadata'], true);
                    }

                    // Determine if this is the first iteration
                    $is_first = ($index === 0);
                ?>
                    <div class="history-entry" data-version="<?php echo $index; ?>">
                        <div class="history-meta">
                            <div>
                                <?php if ($index === (count($history) - 1)): ?>
                                    <strong>Created by:</strong>
                                <?php else: ?>
                                    <strong>Edited by:</strong>
                                <?php endif; ?>

                                <a class="link" href="users/profile.php?username=<?php echo urlencode($version['username']); ?>">
                                    <?php echo htmlspecialchars($version['username']); ?>
                                </a>

                                <?php if (isset($version['is_current'])): ?>
                                    <span class="current-badge">Current Version</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <!-- Date -->
                                <strong>Date:</strong>
                                <span data-timestamp="<?php echo strtotime($version['edited_at']); ?>">
                                    <?php echo date('M j, Y g:i a', strtotime($version['edited_at'])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="history-title">
                            <?php echo htmlspecialchars($version['title']); ?>
                        </div>

                        <div class="history-content">
                            <?php echo nl2br(htmlspecialchars($version['content'])); ?>
                        </div>

                        <?php if ($post['post_type'] === 'bug'): ?>
                            <div class="history-metadata">
                                <div class="history-metadata-title">Bug Report Details</div>

                                <?php if ($version_metadata): ?>
                                    <?php
                                    // Fields to display
                                    $fields = [
                                        'bug_location' => 'Location',
                                        'bug_version' => 'Version',
                                        'bug_steps' => 'Steps to Reproduce',
                                        'bug_expected' => 'Expected Result',
                                        'bug_actual' => 'Actual Result'
                                    ];

                                    foreach ($fields as $field_key => $field_label):
                                        // Check if this field has changed from the previous version
                                        $has_changed = false;
                                        if (!$is_first && $prev_metadata) {
                                            $prev_value = isset($prev_metadata[$field_key]) ? $prev_metadata[$field_key] : '';
                                            $current_value = isset($version_metadata[$field_key]) ? $version_metadata[$field_key] : '';
                                            $has_changed = ($prev_value !== $current_value);
                                        }
                                    ?>
                                        <div class="metadata-field <?php echo $has_changed ? 'metadata-changed' : ''; ?>" data-field="<?php echo $field_key; ?>">
                                            <div class="metadata-field-label"><?php echo $field_label; ?></div>
                                            <div class="metadata-field-value">
                                                <?php
                                                if (isset($version_metadata[$field_key]) && !empty($version_metadata[$field_key])) {
                                                    if ($field_key === 'bug_location') {
                                                        echo $version_metadata[$field_key] === 'website' ? 'Website' : 'Argo Books Application';
                                                    } else {
                                                        echo nl2br(htmlspecialchars($version_metadata[$field_key]));
                                                    }
                                                } else {
                                                    echo '<span class="no-metadata">Not specified</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-metadata">No structured bug data available for this version.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php
                    // Store current metadata as previous for next iteration
                    $prev_metadata = $version_metadata;

                    // Add divider between versions (except after the last one)
                    if ($index < count($history) - 1):
                    ?>
                        <div class="version-divider"></div>
                    <?php endif; ?>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
    </footer>
</body>

</html>