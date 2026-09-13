<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/community_functions.php';
require_once __DIR__ . '/users/user_functions.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/formatting/formatting_functions.php';
require_once __DIR__ . '/report/ban_check.php';
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../partials/fonts.php';

require_login();
$current_user = \CommunityUsers\get_current_user();

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is banned and redirect to index
$user_ban = is_user_banned($current_user['id']);
if ($user_ban) {
    header('Location: index.php');
    exit;
}

$html_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $current_user['id'];

    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh and try again.']);
            exit;
        }
        $error_message = 'Invalid request. Please refresh and try again.';
    }

    // Check if user is banned
    $ban = is_user_banned($user_id);
    if ($ban) {
        $ban_message = get_ban_message($ban);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $ban_message,
                'banned' => true
            ]);
            exit;
        } else {
            $error_message = $ban_message;
        }
    }

    // Check rate limit
    if (empty($error_message)) {
        $rate_limit_message = check_rate_limit($user_id, 'post');
    } else {
        $rate_limit_message = false;
    }

    if ($rate_limit_message !== false) {
        if ($is_ajax) {
            // Return JSON for AJAX requests
            header('Content-Type: application/json');

            $response = [
                'success' => false,
                'message' => 'You are posting too frequently',
                'rate_limited' => true,
                'html_message' => $rate_limit_message
            ];

            // Extract reset timestamp from the message for frontend countdown
            if (preg_match('/data-reset-timestamp="(\d+)"/', $rate_limit_message, $matches)) {
                $response['reset_timestamp'] = intval($matches[1]);
            } else {
                // Fallback to 5 minutes from now if no timestamp found
                $response['reset_timestamp'] = time() + 300;
            }

            echo json_encode($response);
            exit;
        } else {
            // For regular form submissions, keep the existing behavior
            $html_message = $rate_limit_message;
        }
    } elseif (empty($error_message)) {
        // Process the form normally
        $title = isset($_POST['post_title']) ? trim($_POST['post_title']) : '';
        $content = isset($_POST['post_content']) ? trim($_POST['post_content']) : '';
        $post_type = isset($_POST['post_type']) ? trim($_POST['post_type']) : '';

        // Get bug-specific fields if this is a bug report
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
            // Add the post with user info
            $post_id = add_post($current_user['id'], $current_user['username'], $current_user['email'], $title, $content, $post_type);

            if ($post_id) {
                // Connect post to user account
                $stmt = $pdo->prepare('UPDATE community_posts SET user_id = ? WHERE id = ?');
                $stmt->execute([$current_user['id'], $post_id]);

                // Save bug metadata as JSON in a separate field or table if needed
                if ($post_type === 'bug' && !empty($bug_metadata)) {
                    $metadata_json = json_encode($bug_metadata);
                    $stmt = $pdo->prepare('UPDATE community_posts SET metadata = ? WHERE id = ?');
                    $stmt->execute([$metadata_json, $post_id]);
                }

                if ($is_ajax) {
                    // Return JSON success response for AJAX
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'post_id' => $post_id,
                        'message' => 'Post created successfully'
                    ]);
                    exit;
                } else {
                    // Regular redirect for non-AJAX requests
                    header("Location: view_post.php?id=$post_id&created=1");
                    exit;
                }
            } else {
                $error_message = 'Error adding post to the database';

                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $error_message
                    ]);
                    exit;
                }
            }
        }

        // Handle validation errors for AJAX requests
        if ($is_ajax && !empty($error_message)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error_message
            ]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Create New Post - Argo Community</title>

    <script src="../resources/scripts/main.js"></script>
    <script src="formatting/text-formatting.js" defer></script>
    <script src="preview.js" defer></script>

    <link rel="stylesheet" href="create-post.css">
    <link rel="stylesheet" href="rate-limit.css">
    <link rel="stylesheet" href="formatting/formatted-text.css">
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
            <h1>Create a Post</h1>
            <p>Share a bug report or feature request with the community</p>
        </div>
    </div>

    <div class="community-wrapper">
        <div class="post-form-container">
            <?php if ($html_message): ?>
                <?php echo $html_message; ?>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            <div class="post-form">
                <h2>Create New Post</h2>

                <!-- Preview Toggle -->
                <div class="preview-toggle">
                    <button type="button" id="edit-tab" class="active">Edit</button>
                    <button type="button" id="preview-tab">Preview</button>
                </div>

                <!-- Edit Form -->
                <div class="edit-form-container" id="edit-container">
                    <form id="community-post-form" method="post" action="create_post.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="form-group">
                            <label for="post_title">Title</label>
                            <input type="text" id="post_title" name="post_title" required>
                        </div>

                        <div class="form-group">
                            <label for="post_type">Post Type</label>
                            <select id="post_type" name="post_type" required>
                                <option value="">Select post type</option>
                                <option value="bug">Bug Report</option>
                                <option value="feature">Feature Request</option>
                            </select>
                        </div>

                        <!-- Bug-specific fields -->
                        <div id="bug-specific-fields" class="form-section hidden">
                            <div class="form-section-title">
                                Bug Report Details
                            </div>

                            <div class="form-group">
                                <label for="bug_location">Where did you encounter this bug?</label>
                                <input type="text" id="bug_location" name="bug_location" placeholder="e.g., Dashboard > Sales Report, Login page">
                                <small class="field-hint">Specify the page, feature, or area where the issue occurred</small>
                            </div>

                            <div class="form-group">
                                <label for="bug_version">Version/Browser</label>
                                <input type="text" id="bug_version" name="bug_version" placeholder="e.g., Chrome 118, Mobile app v2.1">
                                <small class="field-hint">Browser version, app version, or device info</small>
                            </div>

                            <div class="form-group">
                                <label for="bug_steps">Steps to Reproduce</label>
                                <textarea id="bug_steps" name="bug_steps" placeholder="1. Navigate to...&#10;2. Click on...&#10;3. Notice that..."></textarea>
                                <small class="field-hint">Detailed steps to help us reproduce the issue</small>
                            </div>

                            <div class="form-group">
                                <label for="bug_expected">Expected Behavior</label>
                                <textarea id="bug_expected" name="bug_expected" placeholder="What should have happened?"></textarea>
                                <small class="field-hint">Describe what you expected to happen</small>
                            </div>

                            <div class="form-group">
                                <label for="bug_actual">Actual Behavior</label>
                                <textarea id="bug_actual" name="bug_actual" placeholder="What actually happened?"></textarea>
                                <small class="field-hint">Describe what actually occurred</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="post_content" id="content_label">Content</label>
                            <textarea id="post_content" name="post_content" class="formattable mentionable" required></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="./" class="btn btn-black">Cancel</a>
                            <button type="submit" class="btn btn-blue">Create Post</button>
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

    <!-- Mention dropdown -->
    <div class="mention-dropdown" id="mentionDropdown"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any existing countdown timers on page load
            function startCountdown(element, endTime) {
                if (!element) return;

                function updateCountdown() {
                    const now = Math.floor(Date.now() / 1000);
                    const timeLeft = endTime - now;

                    if (timeLeft <= 0) {
                        element.textContent = "now";

                        // Re-enable the form when countdown expires
                        const postForm = document.getElementById('community-post-form');
                        const submitBtn = postForm?.querySelector('button[type="submit"]');

                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove("btn-disabled");
                        }

                        // Remove the rate limit message
                        const rateMessage = element.closest(".rate-limit-message");
                        if (rateMessage) {
                            rateMessage.style.opacity = "0";
                            setTimeout(() => {
                                if (rateMessage && rateMessage.parentNode) {
                                    rateMessage.parentNode.removeChild(rateMessage);
                                }
                            }, 500);
                        }

                        return;
                    }

                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    element.textContent = minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
                    setTimeout(updateCountdown, 1000);
                }

                updateCountdown();
            }

            // Start any existing countdown timers
            document.querySelectorAll(".countdown-timer").forEach((element) => {
                if (element.dataset.resetTimestamp) {
                    startCountdown(element, parseInt(element.dataset.resetTimestamp, 10));

                    // Also disable the submit button while rate limited
                    const submitBtn = document.querySelector('#community-post-form button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add("btn-disabled");
                    }
                }
            });

            // Handle rate limit error for posts (similar to comments)
            function handlePostRateLimitError(data, formContainer, submitButton) {
                // Preserve all form content before clearing
                const preservedFormData = {};
                const formInputs = formContainer.querySelectorAll('input, textarea, select');
                formInputs.forEach(input => {
                    if (input.name) {
                        if (input.type === 'checkbox' || input.type === 'radio') {
                            preservedFormData[input.name] = input.checked;
                        } else {
                            preservedFormData[input.name] = input.value;
                        }
                    }
                });

                // Remove any existing rate limit messages first
                const existingMessages = document.querySelectorAll(".rate-limit-message");
                existingMessages.forEach((el) => el.remove());

                // Create rate limit message container
                const messageContainer = document.createElement("div");
                messageContainer.className = "rate-limit-message";

                let messageHTML;
                if (data.html_message) {
                    // Use server-provided HTML message
                    if (data.html_message.includes('class="rate-limit-message"')) {
                        const tempDiv = document.createElement("div");
                        tempDiv.innerHTML = data.html_message;
                        const innerMessage = tempDiv.querySelector(".rate-limit-message");
                        if (innerMessage) {
                            messageHTML = innerMessage.innerHTML;
                        } else {
                            messageHTML = data.html_message;
                        }
                    } else {
                        messageHTML = data.html_message;
                    }
                } else {
                    // Create our own message with countdown
                    messageHTML = `You are posting too frequently. 
                    Please wait <span class="countdown-timer" data-reset-timestamp="${data.reset_timestamp}">5m 00s</span> 
                    before posting again.`;
                }

                messageContainer.innerHTML = messageHTML;

                // Insert message at the top of the form container
                const postForm = document.querySelector('.post-form');
                if (postForm) {
                    postForm.insertBefore(messageContainer, postForm.firstChild);

                    // Restore all form content
                    setTimeout(() => {
                        Object.keys(preservedFormData).forEach(name => {
                            const input = document.querySelector(`[name="${name}"]`);
                            if (input) {
                                if (input.type === 'checkbox' || input.type === 'radio') {
                                    input.checked = preservedFormData[name];
                                } else {
                                    input.value = preservedFormData[name];
                                }
                            }
                        });

                        // Trigger post type change event if needed to show/hide bug fields
                        const postTypeSelect = document.getElementById('post_type');
                        if (postTypeSelect && postTypeSelect.value === 'bug') {
                            postTypeSelect.dispatchEvent(new Event('change'));
                        }
                    }, 100);

                    // Start countdown
                    const countdownElement = messageContainer.querySelector(".countdown-timer");
                    if (countdownElement && data.reset_timestamp) {
                        startCountdown(countdownElement, parseInt(data.reset_timestamp, 10));
                    }

                    // Keep submit button disabled
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.classList.add("btn-disabled");
                        submitButton.textContent = "Create Post";
                    }
                }
            }

            // AJAX form submission with rate limit handling
            const postForm = document.getElementById('community-post-form');

            if (postForm) {
                postForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent normal form submission

                    const submitBtn = this.querySelector('button[type="submit"]');
                    const formContainer = document.querySelector('.post-form-container');

                    // Disable submit button while processing
                    submitBtn.disabled = true;
                    submitBtn.classList.add("btn-disabled");
                    submitBtn.textContent = "Creating Post...";

                    // Create FormData from the form
                    const formData = new FormData(this);

                    fetch('create_post.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Redirect to the new post
                                window.location.href = `view_post.php?id=${data.post_id}&created=1`;
                            } else {
                                // Check for rate limit errors
                                if (data.rate_limited) {
                                    handlePostRateLimitError(data, formContainer, submitBtn);
                                } else {
                                    alert('Error creating post: ' + data.message);
                                    // Re-enable submit button
                                    submitBtn.disabled = false;
                                    submitBtn.classList.remove("btn-disabled");
                                    submitBtn.textContent = "Create Post";
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while creating the post: ' + error.message);
                            // Re-enable submit button
                            submitBtn.disabled = false;
                            submitBtn.classList.remove("btn-disabled");
                            submitBtn.textContent = "Create Post";
                        });
                });
            }
        });
    </script>

    <!-- This will be used by mentions.js -->
    <div class="mention-dropdown" id="mentionDropdown"></div>
</body>

</html>