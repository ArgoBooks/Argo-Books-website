<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_sender.php';
require_once __DIR__ . '/../../community/report/ban_check.php';

require_once __DIR__ . '/../../resources/icons.php';

// Check if user is already logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Set page variables for the header
$page_title = "User Account Management";
$page_description = "Manage community user accounts, view user statistics, and moderate users";

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle bulk user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'error';
        header('Location: index.php');
        exit;
    }

    $action = $_POST['bulk_action'];
    $selected_ids = $_POST['selected_ids'] ?? [];

    if (!empty($selected_ids)) {
        $success_count = 0;
        $fail_count = 0;

        if ($action === 'delete') {
            foreach ($selected_ids as $user_id) {
                $stmt = $pdo->prepare('DELETE FROM community_users WHERE id = ?');

                try {
                    if ($stmt->execute([$user_id])) {
                        $success_count++;
                    } else {
                        $fail_count++;
                    }
                } catch (Exception $e) {
                    $fail_count++;
                }
            }

            if ($success_count > 0) {
                $msg = $success_count . ' user' . ($success_count > 1 ? 's' : '') . ' deleted successfully.';
                if ($fail_count > 0) {
                    $msg .= ' ' . $fail_count . ' failed.';
                }
                $_SESSION['message'] = $msg;
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to delete users.';
                $_SESSION['message_type'] = 'error';
            }
        } elseif ($action === 'unban') {
            foreach ($selected_ids as $user_id) {
                // Deactivate all active bans for this user
                $stmt = $pdo->prepare('UPDATE user_bans SET is_active = 0, unbanned_at = NOW(), unbanned_by = NULL WHERE user_id = ? AND is_active = 1');

                if ($stmt->execute([$user_id]) && $stmt->rowCount() > 0) {
                    // Get user info for email
                    $stmt2 = $pdo->prepare('SELECT username, email FROM community_users WHERE id = ?');
                    $stmt2->execute([$user_id]);
                    $user = $stmt2->fetch();

                    if ($user) {
                        send_unban_notification_email($user['email'], $user['username']);
                        $success_count++;
                    }
                } else {
                    $fail_count++;
                }
            }

            if ($success_count > 0) {
                $msg = $success_count . ' user' . ($success_count > 1 ? 's' : '') . ' unbanned successfully.';
                if ($fail_count > 0) {
                    $msg .= ' ' . $fail_count . ' failed.';
                }
                $_SESSION['message'] = $msg;
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'No active bans found for selected users.';
                $_SESSION['message_type'] = 'error';
            }
        } elseif ($action === 'ban') {
            $ban_reason = trim($_POST['ban_reason'] ?? '');
            $ban_duration = $_POST['ban_duration'] ?? 'permanent';
            $allowed_durations = ['5_days', '10_days', '30_days', '100_days', '1_year', 'permanent'];
            if (!in_array($ban_duration, $allowed_durations, true)) {
                $ban_duration = 'permanent';
            }

            if ($ban_reason === '') {
                $_SESSION['message'] = 'A ban reason is required.';
                $_SESSION['message_type'] = 'error';
            } else {
                // Expiry from duration; null = permanent. Mirrors admin/reports/handle_report.php.
                $duration_days = ['5_days' => 5, '10_days' => 10, '30_days' => 30, '100_days' => 100];
                $expires_at = null;
                if (isset($duration_days[$ban_duration])) {
                    $expires_at = date('Y-m-d H:i:s', strtotime('+' . $duration_days[$ban_duration] . ' days'));
                } elseif ($ban_duration === '1_year') {
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
                }

                $skipped = 0;
                foreach ($selected_ids as $user_id) {
                    // Don't stack a second active ban on an already-banned user.
                    if (is_user_banned($user_id)) {
                        $skipped++;
                        continue;
                    }
                    try {
                        // banned_by stays NULL: admins authenticate against admin_users,
                        // not community_users (same as the unban handler above).
                        if ($expires_at) {
                            $stmt = $pdo->prepare('INSERT INTO user_bans (user_id, banned_by, ban_reason, ban_duration, expires_at) VALUES (?, NULL, ?, ?, ?)');
                            $ok = $stmt->execute([$user_id, $ban_reason, $ban_duration, $expires_at]);
                        } else {
                            $stmt = $pdo->prepare('INSERT INTO user_bans (user_id, banned_by, ban_reason, ban_duration) VALUES (?, NULL, ?, ?)');
                            $ok = $stmt->execute([$user_id, $ban_reason, $ban_duration]);
                        }
                        if ($ok) {
                            $success_count++;
                            $stmt2 = $pdo->prepare('SELECT username, email FROM community_users WHERE id = ?');
                            $stmt2->execute([$user_id]);
                            $u = $stmt2->fetch();
                            if ($u) {
                                send_ban_notification_email($u['email'], $u['username'], $ban_reason, $ban_duration, $expires_at);
                            }
                        } else {
                            $fail_count++;
                        }
                    } catch (Exception $e) {
                        $fail_count++;
                    }
                }

                if ($success_count > 0) {
                    $msg = $success_count . ' user' . ($success_count > 1 ? 's' : '') . ' banned successfully.';
                    if ($skipped > 0) $msg .= ' ' . $skipped . ' already banned, skipped.';
                    if ($fail_count > 0) $msg .= ' ' . $fail_count . ' failed.';
                    $_SESSION['message'] = $msg;
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = $skipped > 0 ? 'Selected user(s) are already banned.' : 'Failed to ban users.';
                    $_SESSION['message_type'] = $skipped > 0 ? 'info' : 'error';
                }
            }
        }

        // Redirect to prevent form resubmission
        $redirect_params = [];
        if (!empty($_GET['search'])) $redirect_params[] = 'search=' . urlencode($_GET['search']);
        if (!empty($_GET['ban_status'])) $redirect_params[] = 'ban_status=' . urlencode($_GET['ban_status']);
        $redirect_url = 'index.php' . (!empty($redirect_params) ? '?' . implode('&', $redirect_params) : '');
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Function to get all users with optional filters
function get_all_users($search = '', $date_from = '', $date_to = '', $ban_status = 'all')
{
    global $pdo;
    $users = [];

    $query = 'SELECT u.* FROM community_users u WHERE 1=1';
    $params = [];

    if (!empty($search)) {
        $query .= ' AND (u.username LIKE ? OR u.email LIKE ?)';
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if (!empty($date_from)) {
        $query .= ' AND DATE(u.created_at) >= ?';
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $query .= ' AND DATE(u.created_at) <= ?';
        $params[] = $date_to;
    }

    // Handle ban status filter
    if ($ban_status === 'banned') {
        $query .= ' AND EXISTS (SELECT 1 FROM user_bans b WHERE b.user_id = u.id AND b.is_active = 1 AND (b.expires_at IS NULL OR b.expires_at > NOW()))';
    } elseif ($ban_status === 'unbanned') {
        $query .= ' AND NOT EXISTS (SELECT 1 FROM user_bans b WHERE b.user_id = u.id AND b.is_active = 1 AND (b.expires_at IS NULL OR b.expires_at > NOW()))';
    }

    $query .= ' ORDER BY u.created_at DESC';

    if (!empty($params)) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            $row['is_banned'] = is_user_banned($row['id']);
            $users[] = $row;
        }
    } else {
        $stmt = $pdo->query($query);

        while ($row = $stmt->fetch()) {
            $row['is_banned'] = is_user_banned($row['id']);
            $users[] = $row;
        }
    }

    return $users;
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_preset = isset($_GET['date_preset']) ? trim($_GET['date_preset']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$ban_status = isset($_GET['ban_status']) ? trim($_GET['ban_status']) : 'all';

// Calculate date range based on preset
if (!empty($date_preset) && $date_preset !== 'custom') {
    $date_to = date('Y-m-d'); // Today

    switch ($date_preset) {
        case 'today':
            $date_from = date('Y-m-d');
            break;
        case 'last_week':
            $date_from = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'last_month':
            $date_from = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'last_year':
            $date_from = date('Y-m-d', strtotime('-365 days'));
            break;
        case 'last_3_years':
            $date_from = date('Y-m-d', strtotime('-1095 days'));
            break;
        case 'last_5_years':
            $date_from = date('Y-m-d', strtotime('-1825 days'));
            break;
    }
}

// Get users (filtered)
$users = get_all_users($search, $date_from, $date_to, $ban_status);

// Get user statistics for dashboard

$total_users = count($users);

// Admin users count
$admin_count = 0;
foreach ($users as $user) {
    if ($user['role'] === 'admin') {
        $admin_count++;
    }
}

// Banned users count
$banned_count = 0;
foreach ($users as $user) {
    if ($user['is_banned']) {
        $banned_count++;
    }
}

// Check for flash messages
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

/**
 * Most active community users, ranked by posts + comments. Moved here from the
 * Website Stats page so community activity lives alongside user management.
 */
function get_most_active_users($limit = 25)
{
    global $pdo;
    $query = "
        SELECT
            u.username,
            u.email,
            COUNT(DISTINCT p.id) as post_count,
            COUNT(DISTINCT c.id) as comment_count,
            SUM(p.views) as total_views,
            (COUNT(DISTINCT p.id) + COUNT(DISTINCT c.id)) as activity_score
        FROM community_users u
        LEFT JOIN community_posts p ON u.id = p.user_id
        LEFT JOIN community_comments c ON u.id = c.user_id
        GROUP BY u.id, u.username, u.email
        ORDER BY activity_score DESC
        LIMIT ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$limit]);

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = $row;
    }

    return $data;
}

$active_users = get_most_active_users();

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="../search.css">
<link rel="stylesheet" href="../../resources/styles/checkbox.css">

<div class="container">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Users</h3>
            <div class="stat-value"><?php echo $total_users; ?></div>
        </div>
        <div class="stat-card">
            <h3>Admin Users</h3>
            <div class="stat-value"><?php echo $admin_count; ?></div>
        </div>
        <div class="stat-card">
            <h3>Banned Users</h3>
            <div class="stat-value"><?php echo $banned_count; ?></div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="card" data-live-region>
        <div class="card-header">
            <h2>Users</h2>
            <div class="search-container">
                <form method="GET" action="" class="control-bar search-form" data-live-filter>
                    <input type="text"
                           id="search"
                           name="search"
                           placeholder="Search by username or email..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="control-input search-input">

                    <div class="control-group">
                        <select name="date_preset" id="date_preset" class="control-select" onchange="this.form.requestSubmit()">
                            <option value="">All Time</option>
                            <option value="today" <?php echo $date_preset === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="last_week" <?php echo $date_preset === 'last_week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="last_month" <?php echo $date_preset === 'last_month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="last_year" <?php echo $date_preset === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="last_3_years" <?php echo $date_preset === 'last_3_years' ? 'selected' : ''; ?>>Last 3 Years</option>
                            <option value="last_5_years" <?php echo $date_preset === 'last_5_years' ? 'selected' : ''; ?>>Last 5 Years</option>
                            <option value="custom" <?php echo $date_preset === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <select name="ban_status" id="ban_status" class="control-select" onchange="this.form.requestSubmit()">
                            <option value="all" <?php echo $ban_status === 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="banned" <?php echo $ban_status === 'banned' ? 'selected' : ''; ?>>Banned</option>
                            <option value="unbanned" <?php echo $ban_status === 'unbanned' ? 'selected' : ''; ?>>Not Banned</option>
                        </select>
                    </div>

                    <button type="submit" class="search-button">
                        <?= svg_icon('search') ?>
                        Search
                    </button>
                    <?php if (!empty($search) || !empty($date_preset) || $ban_status !== 'all'): ?>
                        <a href="index.php" class="clear-button">Clear</a>
                    <?php endif; ?>

                    <div id="custom_date_range" class="custom-date-range" style="display: <?php echo $date_preset === 'custom' ? 'flex' : 'none'; ?>;">
                        <div class="control-group">
                            <label for="date_from" class="control-label">From</label>
                            <input type="date"
                                   name="date_from"
                                   id="date_from"
                                   class="control-input"
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="control-group">
                            <label for="date_to" class="control-label">To</label>
                            <input type="date"
                                   name="date_to"
                                   id="date_to"
                                   class="control-input"
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <button type="submit" class="apply-button">Apply</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($users)): ?>
            <div class="no-results">
                <?= svg_icon('alert-circle') ?>
                <p>No users found matching your criteria</p>
            </div>
        <?php else: ?>
            <form id="bulk-form" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bulk-actions-bar">
                    <div class="selection-info">
                        <span id="selected-count">0</span> users selected
                    </div>
                    <div class="bulk-actions">
                        <button type="button" class="btn btn-bulk btn-unban" data-action="unban" disabled>
                            <?= svg_icon('shield-check') ?>
                            Unban Selected
                        </button>
                        <button type="button" class="btn btn-bulk btn-ban" data-action="ban" disabled>
                            <?= svg_icon('shield') ?>
                            Ban Selected
                        </button>
<button type="button" class="btn btn-bulk btn-delete" data-action="delete" disabled>
                            <?= svg_icon('trash') ?>
                            Delete Selected
                        </button>
                    </div>
                </div>

                <input type="hidden" name="bulk_action" id="bulk_action_input">
                <input type="hidden" name="ban_reason" id="ban_reason_input">
                <input type="hidden" name="ban_duration" id="ban_duration_input">

                <div class="table-responsive">
                    <table data-paginate="25">
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <div class="checkbox">
                                        <input type="checkbox" id="select-all">
                                        <label for="select-all"></label>
                                    </div>
                                </th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Banned</th>
                                <th>Created</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="checkbox-column">
                                        <div class="checkbox">
                                            <input type="checkbox" 
                                                name="selected_ids[]" 
                                                value="<?php echo htmlspecialchars($user['id']); ?>"
                                                class="row-checkbox"
                                                data-banned="<?php echo $user['is_banned'] ? '1' : '0'; ?>"
                                                id="user-<?php echo htmlspecialchars($user['id']); ?>">
                                            <label for="user-<?php echo htmlspecialchars($user['id']); ?>"></label>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'admin' : 'user'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['is_banned']): ?>
                                            <span class="badge badge-banned">Banned</span>
                                        <?php else: ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($user['created_at']))); ?></td>
                                    <td><?php echo $user['last_login'] ? htmlspecialchars(date('Y-m-d', strtotime($user['last_login']))) : 'Never'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Ban Users Modal -->
            <div id="ban-modal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Ban users</h3>
                        <button type="button" class="modal-close" id="ban-modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Ban <strong><span id="ban-modal-count">0</span></strong> selected user(s). Already-banned users are skipped.</p>
                        <div class="form-group">
                            <label for="ban-modal-duration">Duration</label>
                            <select id="ban-modal-duration">
                                <option value="permanent">Permanent</option>
                                <option value="5_days">5 days</option>
                                <option value="10_days">10 days</option>
                                <option value="30_days">30 days</option>
                                <option value="100_days">100 days</option>
                                <option value="1_year">1 year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ban-modal-reason">Reason</label>
                            <textarea id="ban-modal-reason" rows="3" placeholder="Shown to the user in their ban notification email."></textarea>
                        </div>
                        <p class="modal-note">The user will be emailed to let them know they have been banned.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="ban-modal-cancel">Cancel</button>
                        <button type="button" class="btn btn-bulk btn-ban" id="ban-modal-confirm">Ban users</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Most active community users (moved here from Website Stats) -->
    <div class="table-container">
        <h2>Most Active Community Users</h2>
        <div class="table-responsive">
            <table data-paginate="25">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Posts</th>
                        <th>Comments</th>
                        <th>Total Views</th>
                        <th>Activity Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo $user['post_count']; ?></td>
                            <td><?php echo $user['comment_count']; ?></td>
                            <td><?php echo number_format($user['total_views']); ?></td>
                            <td><?php echo $user['activity_score']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Runs on load and again after a filter swaps in a fresh users card, whose elements need their handlers.
    function initUsersPage() {
        const selectAllCheckbox = document.getElementById('select-all');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        const bulkButtons = document.querySelectorAll('.btn-bulk');
        const unbanButton = document.querySelector('.btn-unban');
        const banButton = document.querySelector('.btn-ban');
        const deleteButton = document.querySelector('.btn-delete');
        const selectedCountSpan = document.getElementById('selected-count');
        const bulkForm = document.getElementById('bulk-form');
        const bulkActionInput = document.getElementById('bulk_action_input');
        const banReasonInput = document.getElementById('ban_reason_input');
        const banDurationInput = document.getElementById('ban_duration_input');

        // Ban modal elements
        const banModal = document.getElementById('ban-modal');
        const banModalCount = document.getElementById('ban-modal-count');
        const banModalReason = document.getElementById('ban-modal-reason');
        const banModalDuration = document.getElementById('ban-modal-duration');
        const banModalConfirm = document.getElementById('ban-modal-confirm');
        const banModalCancel = document.getElementById('ban-modal-cancel');
        const banModalClose = document.getElementById('ban-modal-close');

        // A search with no results shows no table, so there is nothing to select.
        if (selectAllCheckbox && bulkForm) {
            const updateSelectedCount = function () {
                const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                const count = checkedBoxes.length;
                selectedCountSpan.textContent = count;

                // Check if any banned users are selected
                let bannedUsersSelected = 0;
                checkedBoxes.forEach(checkbox => {
                    if (checkbox.dataset.banned === '1') {
                        bannedUsersSelected++;
                    }
                });

                // Enable/disable buttons based on selection
                if (count === 0) {
                    unbanButton.disabled = true;
                    if (banButton) banButton.disabled = true;
                    deleteButton.disabled = true;
                } else {
                    // Only enable unban if at least one banned user is selected,
                    // and ban if at least one NOT-banned user is selected.
                    unbanButton.disabled = bannedUsersSelected === 0;
                    if (banButton) banButton.disabled = (count - bannedUsersSelected) === 0;
                    deleteButton.disabled = false;
                }

                // Update select-all checkbox state
                if (count === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else if (count === rowCheckboxes.length) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                }
            };

            // Select all functionality
            selectAllCheckbox.addEventListener('change', function() {
                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectedCount();
            });

            // Individual checkbox changes
            rowCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Bulk action buttons
            bulkButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const action = this.dataset.action;
                    // The modal's confirm button shares .btn-bulk styling but has no
                    // data-action; it has its own handler, so ignore it here.
                    if (!action) return;
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    const count = checkedBoxes.length;

                    if (count === 0) return;

                    // Ban needs a reason + duration, so it opens a modal instead of a confirm.
                    if (action === 'ban') {
                        banModalCount.textContent = count;
                        banModalReason.value = '';
                        banModalDuration.value = 'permanent';
                        banModal.style.display = 'flex';
                        banModalReason.focus();
                        return;
                    }

                    let confirmMessage = '';

                    if (action === 'delete') {
                        confirmMessage = `Are you sure you want to delete ${count} user${count > 1 ? 's' : ''}? This action cannot be undone.`;
                    } else if (action === 'unban') {
                        confirmMessage = `Are you sure you want to unban ${count} user${count > 1 ? 's' : ''}? They will be able to post again.`;
                    }

                    if (confirm(confirmMessage)) {
                        bulkActionInput.value = action;
                        sessionStorage.setItem('scrollPosition', window.scrollY);
                        bulkForm.submit();
                    }
                });
            });

            // Ban modal wiring
            const closeBanModal = function () {
                if (banModal) banModal.style.display = 'none';
            };
            if (banModalConfirm) {
                banModalConfirm.addEventListener('click', function() {
                    const reason = banModalReason.value.trim();
                    if (!reason) {
                        banModalReason.focus();
                        return;
                    }
                    banReasonInput.value = reason;
                    banDurationInput.value = banModalDuration.value;
                    bulkActionInput.value = 'ban';
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                    bulkForm.submit();
                });
            }
            if (banModalCancel) banModalCancel.addEventListener('click', closeBanModal);
            if (banModalClose) banModalClose.addEventListener('click', closeBanModal);
            if (banModal) {
                banModal.addEventListener('click', function(e) {
                    if (e.target === banModal) closeBanModal();
                });
            }

            // Initial count
            updateSelectedCount();
        }

        // Date preset select handling
        const datePresetSelect = document.getElementById('date_preset');
        const customDateRange = document.getElementById('custom_date_range');

        if (datePresetSelect && customDateRange) {
            datePresetSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customDateRange.style.display = 'flex';
                } else {
                    customDateRange.style.display = 'none';
                }
            });

            // If user clicks on date inputs, select custom option
            customDateRange.querySelectorAll('input[type="date"]').forEach(input => {
                input.addEventListener('focus', function() {
                    datePresetSelect.value = 'custom';
                    customDateRange.style.display = 'flex';
                });
            });
        }

        // Auto-clear search when textbox is emptied
        const searchInput = document.querySelector('#search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    this.form.requestSubmit();
                }
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    this.form.requestSubmit();
                }
            });
        }
    }

    // Registered once, because the ban window it closes is looked up afresh each time.
    document.addEventListener('keydown', function(e) {
        const banModal = document.getElementById('ban-modal');
        if (e.key === 'Escape' && banModal && banModal.style.display === 'flex') banModal.style.display = 'none';
    });
    document.addEventListener('DOMContentLoaded', initUsersPage);
    document.addEventListener('admin:live-update', initUsersPage);
</script>

        </main>
    </div>
</body>

</html>