<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../resources/icons.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// CSRF token for the moderation actions posted to handle_report.php.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set page variables for the header
$page_title = "Content Reports";
$page_description = "Review and moderate reported posts and comments on the community page";

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$content_type_filter = $_GET['content_type'] ?? 'all';

// Function to get all reports with filters
function get_reports($status = 'pending', $content_type = 'all')
{
    global $pdo;
    $reports = [];

    $query = 'SELECT
        r.id,
        r.content_type,
        r.content_id,
        r.violation_type,
        r.additional_info,
        r.status,
        r.created_at,
        r.reporter_email,
        reporter.username AS reporter_username,
        reported_user.id AS reported_user_id,
        reported_user.username AS reported_user_username,
        reported_user.email AS reported_user_email,
        reported_user.role AS reported_user_role,
        reported_user.bio AS reported_user_bio,
        CASE
            WHEN r.content_type = "post" THEN p.title
            WHEN r.content_type = "comment" THEN CONCAT("Comment on: ", post.title)
            WHEN r.content_type = "user" THEN CONCAT("User Profile: ", reported_user.username)
        END AS content_title,
        CASE
            WHEN r.content_type = "post" THEN p.content
            WHEN r.content_type = "comment" THEN c.content
            WHEN r.content_type = "user" THEN CONCAT("Username: ", reported_user.username, "\nBio: ", COALESCE(reported_user.bio, "(No bio)"))
        END AS content_text,
        (
            SELECT COUNT(*) + 1
            FROM content_reports r2
            LEFT JOIN community_posts p2 ON r2.content_type = "post" AND r2.content_id = p2.id
            LEFT JOIN community_comments c2 ON r2.content_type = "comment" AND r2.content_id = c2.id
            WHERE r2.status = "resolved"
            AND r2.created_at < r.created_at
            AND (
                (r2.content_type = "post" AND p2.user_id = reported_user.id) OR
                (r2.content_type = "comment" AND c2.user_id = reported_user.id) OR
                (r2.content_type = "user" AND r2.content_id = reported_user.id)
            )
        ) AS offense_count
    FROM content_reports r
    LEFT JOIN community_users reporter ON r.reporter_user_id = reporter.id
    LEFT JOIN community_posts p ON r.content_type = "post" AND r.content_id = p.id
    LEFT JOIN community_comments c ON r.content_type = "comment" AND r.content_id = c.id
    LEFT JOIN community_posts post ON c.post_id = post.id
    LEFT JOIN community_users reported_user ON (
        (r.content_type = "post" AND p.user_id = reported_user.id) OR
        (r.content_type = "comment" AND c.user_id = reported_user.id) OR
        (r.content_type = "user" AND r.content_id = reported_user.id)
    )
    WHERE 1=1';

    $params = [];

    if ($status !== 'all') {
        $query .= ' AND r.status = ?';
        $params[] = $status;
    }

    if ($content_type !== 'all') {
        $query .= ' AND r.content_type = ?';
        $params[] = $content_type;
    }

    $query .= ' ORDER BY r.created_at DESC';

    if (!empty($params)) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($query);
    }

    while ($row = $stmt->fetch()) {
        $reports[] = $row;
    }

    return $reports;
}

function get_reports_count_by_status()
{
    global $pdo;
    $counts = ['pending' => 0, 'resolved' => 0, 'dismissed' => 0];

    $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM content_reports GROUP BY status');
    while ($row = $stmt->fetch()) {
        $counts[$row['status']] = $row['count'];
    }

    return $counts;
}

$reports = get_reports($status_filter, $content_type_filter);
$status_counts = get_reports_count_by_status();

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="style.css">

<div data-live-region>
<!-- Summary Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card pending">
        <h3>Pending</h3>
        <div class="value"><?php echo $status_counts['pending']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Resolved</h3>
        <div class="value"><?php echo $status_counts['resolved']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Dismissed</h3>
        <div class="value"><?php echo $status_counts['dismissed']; ?></div>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="" class="control-bar" data-live-filter>
    <div class="control-group">
        <span class="control-label">Status</span>
        <select name="status" id="status" class="control-select" onchange="this.form.requestSubmit()">
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
            <option value="dismissed" <?php echo $status_filter === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
        </select>
    </div>

    <div class="control-group">
        <span class="control-label">Content Type</span>
        <select name="content_type" id="content_type" class="control-select" onchange="this.form.requestSubmit()">
            <option value="all" <?php echo $content_type_filter === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="post" <?php echo $content_type_filter === 'post' ? 'selected' : ''; ?>>Posts</option>
            <option value="comment" <?php echo $content_type_filter === 'comment' ? 'selected' : ''; ?>>Comments</option>
            <option value="user" <?php echo $content_type_filter === 'user' ? 'selected' : ''; ?>>Users</option>
        </select>
    </div>
</form>

<!-- Reports list -->
<div class="reports-table">
    <?php if (empty($reports)): ?>
        <div class="empty-state">
            <?= svg_icon('circle-check-sm', 64) ?>
            <p>No reports found</p>
        </div>
    <?php else: ?>
        <?php foreach ($reports as $report): ?>
            <div class="report-row" data-report-id="<?php echo $report['id']; ?>">
                <div class="report-header">
                    <div class="report-top">
                        <div class="report-id-title">
                            <span class="report-id">Report #<?php echo $report['id']; ?></span>
                            <h3 class="report-title"><?php echo htmlspecialchars($report['content_title'] ?? 'Content Deleted'); ?></h3>
                        </div>
                        <div class="report-badges">
                            <span class="content-type-badge"><?php echo ucfirst($report['content_type']); ?></span>
                            <span class="violation-badge"><?php echo ucfirst(str_replace('_', ' ', $report['violation_type'])); ?></span>
                            <span class="status-badge-inline <?php echo $report['status']; ?>"><?php echo ucfirst($report['status']); ?></span>
                            <?php if ($report['reported_user_id']): ?>
                                <?php
                                $offense_count = (int)$report['offense_count'];
                                $offense_class = '';

                                if ($offense_count === 1) {
                                    $offense_class = 'green';
                                } elseif ($offense_count === 2) {
                                    $offense_class = 'yellow';
                                } else {
                                    $offense_class = 'red';
                                }
                                ?>
                                <span class="offense-indicator">
                                    <span class="offense-dot <?php echo $offense_class; ?>"></span>
                                    <?php echo $offense_count . ($offense_count === 1 ? 'st' : ($offense_count === 2 ? 'nd' : ($offense_count === 3 ? 'rd' : 'th offence'))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="report-meta-row">
                        <span><strong>Reporter:</strong> <?php echo htmlspecialchars($report['reporter_username'] ?? $report['reporter_email']); ?></span>
                        <span class="meta-separator">•</span>
                        <span><strong>Reported User:</strong> <?php echo htmlspecialchars($report['reported_user_username'] ?? 'Unknown'); ?></span>
                        <span class="meta-separator">•</span>
                        <span><?php echo date('M j, Y g:i a', strtotime($report['created_at'])); ?></span>
                    </div>
                </div>

                <?php if ($report['content_text']): ?>
                    <div class="content-preview">
                        <?php echo htmlspecialchars(substr($report['content_text'], 0, 200)) . (strlen($report['content_text']) > 200 ? '...' : ''); ?>
                    </div>
                <?php endif; ?>

                <?php if ($report['additional_info']): ?>
                    <div class="report-details">
                        <strong>Additional information:</strong> <?php echo htmlspecialchars($report['additional_info']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($report['status'] === 'pending'): ?>
                    <div class="report-actions">
                        <div class="action-group">
                            <?php if ($report['content_type'] !== 'user' && $report['content_text']): ?>
                                <a href="../../community/<?php echo $report['content_type'] === 'post' ? 'view_post.php?id=' . $report['content_id'] : 'view_post.php?id=' . $report['content_id']; ?>"
                                   class="btn-small btn-view" target="_blank">View</a>
                            <?php elseif ($report['content_type'] === 'user'): ?>
                                <a href="../../community/users/profile.php?username=<?php echo urlencode($report['reported_user_username']); ?>"
                                   class="btn-small btn-view" target="_blank">View Profile</a>
                            <?php endif; ?>
                            <button class="btn-small btn-dismiss" onclick="handleReport(<?php echo $report['id']; ?>, 'dismiss')">Dismiss</button>
                        </div>

                        <?php if ($report['reported_user_role'] !== 'admin'): ?>
                            <div class="action-group action-group-danger">
                                <?php if ($report['content_type'] === 'user'): ?>
                                    <button class="btn-small btn-warning" onclick="showResetUsernameModal(<?php echo $report['id']; ?>, <?php echo $report['reported_user_id']; ?>, '<?php echo htmlspecialchars($report['reported_user_username'], ENT_QUOTES); ?>')">Reset Username</button>
                                    <button class="btn-small btn-warning" onclick="showClearBioModal(<?php echo $report['id']; ?>, <?php echo $report['reported_user_id']; ?>, '<?php echo htmlspecialchars($report['reported_user_username'], ENT_QUOTES); ?>')">Clear Bio</button>
                                <?php else: ?>
                                    <button class="btn-small btn-delete" onclick="handleReport(<?php echo $report['id']; ?>, 'delete', '<?php echo $report['content_type']; ?>', <?php echo $report['content_id']; ?>)">Delete</button>
                                <?php endif; ?>

                                <?php if ($report['reported_user_id']): ?>
                                    <button class="btn-small btn-ban" onclick="showBanModal(<?php echo $report['id']; ?>, <?php echo $report['reported_user_id']; ?>, '<?php echo htmlspecialchars($report['reported_user_username'], ENT_QUOTES); ?>')">Ban User</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>

<!-- Ban Modal -->
<div id="banModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Ban User</h3>
            <button class="modal-close" onclick="closeBanModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>You are about to ban <strong id="banUsername"></strong></p>
            <input type="hidden" id="banReportId">
            <input type="hidden" id="banUserId">

            <div class="form-group">
                <label for="banViolationType">Reason for Ban</label>
                <select id="banViolationType" required>
                    <option value="">Select a reason...</option>
                    <option value="spam">Spam</option>
                    <option value="harassment">Harassment</option>
                    <option value="hateful">Hateful Content</option>
                    <option value="inappropriate">Inappropriate Content</option>
                    <option value="inappropriate_username">Inappropriate Username</option>
                    <option value="inappropriate_bio">Inappropriate Bio</option>
                    <option value="impersonation">Impersonation</option>
                    <option value="misinformation">Misinformation</option>
                    <option value="repeated_violations">Repeated Violations</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="banReason">Additional Details (Optional)</label>
                <textarea id="banReason" rows="4" placeholder="Provide additional context for the user..."></textarea>
            </div>

            <div class="form-group">
                <label for="banDuration">Ban Duration</label>
                <select id="banDuration">
                    <option value="5_days">5 Days</option>
                    <option value="10_days">10 Days</option>
                    <option value="30_days">30 Days</option>
                    <option value="100_days">100 Days</option>
                    <option value="1_year">1 Year</option>
                    <option value="permanent">Permanent</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeBanModal()">Cancel</button>
            <button class="btn btn-red" onclick="submitBan()">Ban User</button>
        </div>
    </div>
</div>

<!-- Username Reset Modal -->
<div id="resetUsernameModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Reset Username</h3>
            <button class="modal-close" onclick="closeResetUsernameModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>You are about to reset the username for <strong id="resetUsername"></strong></p>
            <p class="warning-text">This will replace their username with a random string and update all their posts and comments.</p>
            <input type="hidden" id="resetUsernameReportId">
            <input type="hidden" id="resetUsernameUserId">

            <div class="form-group">
                <label for="resetUsernameViolationType">Reason for Reset</label>
                <select id="resetUsernameViolationType" required>
                    <option value="">Select a reason...</option>
                    <option value="inappropriate_username">Inappropriate Username</option>
                    <option value="impersonation">Impersonation</option>
                    <option value="harassment">Harassment</option>
                    <option value="hateful">Hateful Content</option>
                    <option value="spam">Spam</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="resetUsernameDetails">Additional Details (Optional)</label>
                <textarea id="resetUsernameDetails" rows="4" placeholder="Provide additional context for the user..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeResetUsernameModal()">Cancel</button>
            <button class="btn btn-red" onclick="submitResetUsername()">Reset Username</button>
        </div>
    </div>
</div>

<!-- Clear Bio Modal -->
<div id="clearBioModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Clear Bio</h3>
            <button class="modal-close" onclick="closeClearBioModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>You are about to clear the bio for <strong id="clearBioUsername"></strong></p>
            <input type="hidden" id="clearBioReportId">
            <input type="hidden" id="clearBioUserId">

            <div class="form-group">
                <label for="clearBioViolationType">Reason for Clearing Bio</label>
                <select id="clearBioViolationType" required>
                    <option value="">Select a reason...</option>
                    <option value="inappropriate_bio">Inappropriate Bio</option>
                    <option value="harassment">Harassment</option>
                    <option value="hateful">Hateful Content</option>
                    <option value="spam">Spam</option>
                    <option value="inappropriate">Inappropriate Content</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="clearBioDetails">Additional Details (Optional)</label>
                <textarea id="clearBioDetails" rows="4" placeholder="Provide additional context for the user..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeClearBioModal()">Cancel</button>
            <button class="btn btn-red" onclick="submitClearBio()">Clear Bio</button>
        </div>
    </div>
</div>

<script>window.REPORTS_CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;</script>
<script src="reports.js"></script>

        </main>
    </div>
</body>

</html>