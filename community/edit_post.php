<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/community_functions.php';
require_once __DIR__ . '/users/user_functions.php';
require_once __DIR__ . '/formatting/formatting_functions.php';
require_once __DIR__ . '/report/ban_check.php';

require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../partials/fonts.php';

// Check for remember me cookie and auto-login user if valid
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    check_remember_me();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: users/login.php');
    exit;
}

// Check if user is banned
$user_id = $_SESSION['user_id'];
$ban = is_user_banned($user_id);
if ($ban) {
    $_SESSION['error_message'] = get_ban_message($ban);
    header('Location: index.php');
    exit;
}

// Get post ID from URL
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Redirect to index if no valid post ID
if ($post_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get the post
$post = get_post($post_id);

// Redirect if post not found
if (!$post) {
    header('Location: index.php');
    exit;
}

// Check if user has permission to edit
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Determine if user can edit this post
$can_edit_post = ($role === 'admin') ||
    (isset($post['user_id']) && (int)$post['user_id'] === (int)$user_id);

if (!$can_edit_post) {
    // Redirect to view post if no permission
    header("Location: view_post.php?id=$post_id");
    exit;
}

// Check for metadata
$metadata = [];

// Check if metadata column exists
$metadata_exists = false;
$result = $pdo->query("SHOW COLUMNS FROM community_posts LIKE 'metadata'");
$metadata_exists = ($result->fetch() !== false);

if ($metadata_exists) {
    // Get metadata if it exists
    $stmt = $pdo->prepare('SELECT metadata FROM community_posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $row = $stmt->fetch();

    if ($row && !empty($row['metadata'])) {
        $metadata = json_decode($row['metadata'], true);
    }
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process form submission
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please refresh and try again.';
    }

    if (empty($error_message)):

    // Validate and sanitize inputs
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $post_type = isset($_POST['post_type']) ? trim($_POST['post_type']) : '';

    // Process bug-specific fields if this is a bug report
    $bug_metadata = [];
    if ($post_type === 'bug') {
        $bug_metadata = [
            'bug_location' => isset($_POST['bug_location']) ? trim($_POST['bug_location']) : '',
            'bug_version' => isset($_POST['bug_version']) ? trim($_POST['bug_version']) : '',
            'bug_steps' => isset($_POST['bug_steps']) ? trim($_POST['bug_steps']) : '',
            'bug_expected' => isset($_POST['bug_expected']) ? trim($_POST['bug_expected']) : '',
            'bug_actual' => isset($_POST['bug_actual']) ? trim($_POST['bug_actual']) : ''
        ];
    }

    // Process feature-specific fields if this is a feature request
    if ($post_type === 'feature' && isset($_POST['feature_benefit']) && !empty($_POST['feature_benefit'])) {
        // For feature requests, we keep the format with the benefit in the content
        $content_has_benefit = strpos($content, '**Benefit:**') !== false;

        if (!$content_has_benefit) {
            $content = "**Benefit:**\n" . trim($_POST['feature_benefit']) . "\n\n" . $content;
        }
    }

    // Basic validation
    if (empty($title) || empty($content) || empty($post_type)) {
        $error_message = 'All fields are required';
    } elseif (!in_array($post_type, ['bug', 'feature'])) {
        $error_message = 'Invalid post type';
    } elseif (strlen($title) > 255) {
        $error_message = 'Title is too long (maximum 255 characters)';
    } elseif (strlen($content) > 10000) {
        $error_message = 'Content is too long (maximum 10,000 characters)';
    } else {
        // Check if anything actually changed before saving to history
        $has_changes = false;

        // Check if title or content changed
        if ($title !== $post['title'] || $content !== $post['content'] || $post_type !== $post['post_type']) {
            $has_changes = true;
        }

        // Check if metadata changed (for bug reports)
        if ($post_type === 'bug' && $metadata_exists) {
            $current_metadata = null;
            $stmt = $pdo->prepare('SELECT metadata FROM community_posts WHERE id = ?');
            $stmt->execute([$post_id]);
            $row = $stmt->fetch();

            if ($row && !empty($row['metadata'])) {
                $current_metadata = json_decode($row['metadata'], true);

                // Compare metadata fields
                foreach ($bug_metadata as $key => $value) {
                    if (!isset($current_metadata[$key]) || $current_metadata[$key] !== $value) {
                        $has_changes = true;
                        break;
                    }
                }
            } else if (!empty(array_filter($bug_metadata))) {
                // If no previous metadata but new metadata has values
                $has_changes = true;
            }
        }

        // Only proceed if there are actual changes
        if ($has_changes) {
            $result = $pdo->query("SHOW COLUMNS FROM post_edit_history LIKE 'metadata'");

            // Get the current metadata for history
            $previous_metadata = null;
            if ($metadata_exists) {
                $stmt = $pdo->prepare('SELECT metadata FROM community_posts WHERE id = ?');
                $stmt->execute([$post_id]);
                $row = $stmt->fetch();
                if ($row && isset($row['metadata'])) {
                    $previous_metadata = $row['metadata'];
                }
            }

            // Save the current post to history
            $stmt = $pdo->prepare('INSERT INTO post_edit_history (post_id, user_id, title, content, metadata, edited_at) 
                                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$post_id, $user_id, $post['title'], $post['content'], $previous_metadata]);

            $stmt = $pdo->prepare('UPDATE community_posts 
                                SET title = ?, content = ?, post_type = ?, updated_at = CURRENT_TIMESTAMP 
                                WHERE id = ?');
            $update_success = $stmt->execute([$title, $content, $post_type, $post_id]);

            // Save bug metadata if applicable
            if ($post_type === 'bug' && !empty($bug_metadata) && $metadata_exists) {
                $metadata_json = json_encode($bug_metadata);
                $stmt = $pdo->prepare('UPDATE community_posts SET metadata = ? WHERE id = ?');
                $stmt->execute([$metadata_json, $post_id]);
            }

            if ($update_success) {
                // Redirect immediately to view page with success message
                header("Location: view_post.php?id=$post_id&updated=1");
                exit;
            } else {
                $error_message = 'Error updating the post';
            }
        } else {
            // No changes detected, just redirect back to the post without error
            header("Location: view_post.php?id=$post_id");
            exit;
        }
    }

    endif; // CSRF check
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Edit Post - Argo Community</title>

    <script src="../resources/scripts/main.js"></script>
    <script src="formatting/text-formatting.js" defer></script>
    <script src="preview.js" defer></script>

    <link rel="stylesheet" href="create-post.css">
    <link rel="stylesheet" href="edit-post.css">
    <link rel="stylesheet" href="formatting/formatted-text.css">
    <link rel="stylesheet" href="view-post.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
    <?= argo_font_links('default', '    ') ?>
    <link rel="stylesheet" href="../resources/styles/typography.css">

    <!-- Mentions system -->
    <link rel="stylesheet" href="mentions/mentions.css">
    <script src="mentions/mentions.js" defer></script>
    <script src="mentions/init.js" defer></script>
</head>

<body>
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>

    <div class="hero">
        <div class="hero-content">
            <h1>Edit Your Post</h1>
            <p>Update your bug report or feature request</p>
        </div>
    </div>

    <div class="community-wrapper">
        <div class="post-form-container">
            <?php if ($error_message): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="post-form">
                <h2>Edit Post</h2>

                <!-- Preview Toggle -->
                <div class="preview-toggle">
                    <button type="button" id="edit-tab" class="active">Edit</button>
                    <button type="button" id="preview-tab">Preview</button>
                </div>

                <!-- Edit Form -->
                <div class="edit-form-container" id="edit-container">
                    <form method="post" action="edit_post.php?id=<?php echo $post_id; ?>" id="edit-post-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="post_type">Post Type</label>
                        <select id="post_type" name="post_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="bug" <?php echo $post['post_type'] === 'bug' ? 'selected' : ''; ?>>Bug Report</option>
                            <option value="feature" <?php echo $post['post_type'] === 'feature' ? 'selected' : ''; ?>>Feature Request</option>
                        </select>
                    </div>

                    <!-- Bug Report Specific Fields -->
                    <div id="bug-specific-fields" style="display: <?php echo $post['post_type'] === 'bug' ? 'block' : 'none'; ?>;">
                        <div class="form-group">
                            <label for="bug_location">Where did you find this bug?</label>
                            <select id="bug_location" name="bug_location">
                                <option value="">-- Select Location --</option>
                                <option value="website" <?php echo isset($metadata['bug_location']) && $metadata['bug_location'] === 'website' ? 'selected' : ''; ?>>Website</option>
                                <option value="sales_tracker" <?php echo isset($metadata['bug_location']) && $metadata['bug_location'] === 'sales_tracker' ? 'selected' : ''; ?>>Argo Books Application</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="bug_version">Browser or Argo Books Version</label>
                            <input type="text" id="bug_version" name="bug_version" placeholder="e.g., Chrome 99.0.4844 or Argo Books V.1.0.8" value="<?php echo isset($metadata['bug_version']) ? htmlspecialchars($metadata['bug_version']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="bug_steps">Steps to Reproduce</label>
                            <textarea id="bug_steps" name="bug_steps" rows="4" class="formattable mentionable" placeholder="Please provide step-by-step instructions to reproduce the issue"><?php echo isset($metadata['bug_steps']) ? htmlspecialchars($metadata['bug_steps']) : ''; ?></textarea>
                            <?php add_formatting_toolbar('post_content'); ?>
                        </div>

                        <div class="form-group">
                            <label for="bug_expected">Expected Result</label>
                            <textarea id="bug_expected" name="bug_expected" rows="3" class="formattable mentionable" placeholder="What you expected to happen"><?php echo isset($metadata['bug_expected']) ? htmlspecialchars($metadata['bug_expected']) : ''; ?></textarea>
                            <?php add_formatting_toolbar('post_content'); ?>
                        </div>

                        <div class="form-group">
                            <label for="bug_actual">Actual Result</label>
                            <textarea id="bug_actual" name="bug_actual" rows="3" class="formattable mentionable" placeholder="What actually happened"><?php echo isset($metadata['bug_actual']) ? htmlspecialchars($metadata['bug_actual']) : ''; ?></textarea>
                            <?php add_formatting_toolbar('post_content'); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="content" id="content_label">
                            <?php
                            if ($post['post_type'] === 'bug') {
                                echo 'Additional Details';
                            } else if ($post['post_type'] === 'feature') {
                                echo 'Feature Description';
                            } else {
                                echo 'Content';
                            }
                            ?>
                        </label>
                        <?php
                        if ($post['post_type'] === 'feature' && !empty($feature_benefit)) {
                            $parts = explode("**Benefit:**\n$feature_benefit\n\n", $post['content'], 2);
                            $content_to_display = $parts[1] ?? $post['content'];
                        } else {
                            $content_to_display = $post['content'];
                        }
                        ?>
                        <textarea id="content" name="content" class="formattable mentionable" required><?= htmlspecialchars($content_to_display) ?></textarea>
                        <?php add_formatting_toolbar('post_content'); ?>
                    </div>

                    <div class="form-actions">
                        <a href="view_post.php?id=<?php echo $post_id; ?>" class="btn btn-black">Cancel</a>
                        <button type="submit" class="btn btn-blue">Save Changes</button>
                    </div>
                </form>
                </div>

                <!-- Preview Container -->
                <div class="preview-container" id="preview-container">
                    <div class="preview-post">
                        <div id="preview-content">
                            <div class="preview-empty-state">
                                <div class="preview-empty-icon"><?= svg_icon('eye', 40) ?></div>
                                <p>Fill out the form to see a preview of your post</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
    </footer>

    <!-- Preview functionality is handled by preview.js -->

    <!-- This will be used by mentions.js -->
    <div class="mention-dropdown" id="mentionDropdown"></div>
</body>

</html>