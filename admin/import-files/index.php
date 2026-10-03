<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Import Files';
$page_description = "Files people sent after an import failed, held briefly so you can see why.";

// Must match IMPORT_DIAGNOSTIC_RETENTION_DAYS in api/import-diagnostic/upload.php,
// which is what actually sets expires_at on the row.
$retentionDays = 7;

$state = $_GET['state'] ?? 'held';
if (!in_array($state, ['held', 'downloaded', 'all'], true)) {
    $state = 'held';
}

$tableMissing = false;
$files = [];
$heldCount = 0;
$heldBytes = 0;
$oldestHeld = null;

try {
    $where = 'environment = ?';
    $params = [current_environment()];
    if ($state !== 'all') {
        $where .= ' AND state = ?';
        $params[] = $state;
    }

    $stmt = $pdo->prepare(
        "SELECT id, storage_name, device_hash, failure_reason, file_kind, file_bytes,
                page_count, app_version, state, created_at, expires_at,
                downloaded_at, downloaded_by
           FROM import_diagnostic_files
          WHERE $where
          ORDER BY created_at DESC
          LIMIT 500"
    );
    $stmt->execute($params);
    $files = $stmt->fetchAll();

    // The counters describe what is actually on disk, not the current filter.
    $sum = $pdo->prepare(
        "SELECT COUNT(*) AS n, COALESCE(SUM(file_bytes), 0) AS bytes, MIN(created_at) AS oldest
           FROM import_diagnostic_files
          WHERE state = 'held' AND environment = ?"
    );
    $sum->execute([current_environment()]);
    $totals = $sum->fetch() ?: ['n' => 0, 'bytes' => 0, 'oldest' => null];
    $heldCount  = (int) $totals['n'];
    $heldBytes  = (int) $totals['bytes'];
    $oldestHeld = $totals['oldest'] ? strtotime((string) $totals['oldest']) : null;
} catch (PDOException $e) {
    $tableMissing = true;
    error_log('import-files: ' . $e->getMessage());
}

/** Short, human size. */
function if_size(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024) . ' KB';
    return $bytes . ' B';
}

/** How long before this deletes itself, and how urgent that is. */
function if_expiry(int $expiresAt): array
{
    $secondsLeft = $expiresAt - time();
    if ($secondsLeft <= 0) return ['Due for deletion', 'now'];
    $hours = (int) floor($secondsLeft / 3600);
    if ($hours < 24)       return [$hours . 'h left', 'soon'];
    return [(int) floor($hours / 24) . 'd left', 'ok'];
}

$messages = [
    'gone'        => 'That file was already gone.',
    'error'       => 'That file could not be decrypted. It has been left in place and the error logged.',
    'deleted'     => 'File deleted.',
    'deleted_all' => 'Deleted ' . (int) ($_GET['n'] ?? 0) . ' files.',
];
$message = $messages[$_GET['msg'] ?? ''] ?? null;

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="style.css">

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="if-notice" role="note">
    <span class="if-notice-mark" aria-hidden="true">!</span>
    <div>
        <strong>These are real customer bank statements.</strong>
        Each was sent deliberately by the person whose import failed, and is encrypted at rest.
        Every file deletes itself <?= (int) $retentionDays ?> days after it arrives, whether or not you
        have looked at it. Take the one you need, read it locally, delete the rest.
    </div>
</div>

<?php if ($tableMissing): ?>
    <div class="empty-state">
        <strong>import_diagnostic_files table not found.</strong>
        Run the CREATE TABLE in mysql_schema.sql. The page populates once a file arrives.
    </div>
<?php else: ?>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Files held</h3>
        <div class="value"><?= number_format($heldCount) ?></div>
        <div class="subtext">the right number is zero</div>
    </div>
    <div class="stat-card">
        <h3>Oldest</h3>
        <div class="value"><?= $oldestHeld
            ? htmlspecialchars(if_expiry($oldestHeld + $retentionDays * 86400)[0])
            : '&mdash;' ?></div>
        <div class="subtext">until it deletes itself</div>
    </div>
    <div class="stat-card">
        <h3>Total size</h3>
        <div class="value"><?= if_size($heldBytes) ?></div>
        <div class="subtext">across everything on disk</div>
    </div>
    <div class="stat-card">
        <h3>Retention</h3>
        <div class="value"><?= (int) $retentionDays ?> days</div>
        <div class="subtext">enforced by cron, not by hand</div>
    </div>
</div>

<div class="control-bar">
    <div class="control-group">
        <span class="control-label">Show:</span>
        <div class="control-pills">
            <?php foreach (['held' => 'Held', 'downloaded' => 'Taken', 'all' => 'All'] as $key => $label): ?>
                <a href="?state=<?= $key ?>" class="control-pill <?= $state === $key ? 'active' : '' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="control-spacer"></div>
    <?php if ($heldCount > 0): ?>
    <div class="control-group">
        <form method="post" action="../_actions/import_file_action.php"
              onsubmit="return confirm('Delete every held file? This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="delete_all">
            <button type="submit" class="if-btn if-btn-danger">Delete every held file</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="table-container">
    <h2>Captured files</h2>

    <?php if (!$files): ?>
        <div class="empty-state">
            <strong>Nothing here.</strong>
            Files appear only when an import fails and the person chooses to send it.
            An empty page is the normal state.
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table data-paginate="25">
            <thead>
                <tr>
                    <th>Captured</th>
                    <th>Device</th>
                    <th>Why it failed</th>
                    <th>File</th>
                    <th>Expires</th>
                    <th class="if-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($files as $f):
                $createdTs = strtotime((string) $f['created_at']);
                $expiresTs = strtotime((string) $f['expires_at']);
                [$expiryText, $urgency] = if_expiry($expiresTs);
                $isHeld = $f['state'] === 'held';
                $device = (string) ($f['device_hash'] ?? ''); ?>
                <tr class="<?= $isHeld ? '' : 'if-row-done' ?>">
                    <td><time data-epoch="<?= (int) $createdTs ?>" data-epoch-seconds="1"></time></td>
                    <td>
                        <?php if ($device !== ''): ?>
                            <code class="if-device" title="<?= htmlspecialchars($device) ?>"><?= htmlspecialchars(substr($device, 0, 12)) ?></code>
                        <?php else: ?>
                            <span class="if-sub">unknown device</span>
                        <?php endif; ?>
                        <?php if (!empty($f['app_version'])): ?>
                            <div class="if-sub">v<?= htmlspecialchars((string) $f['app_version']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><code class="if-reason"><?= htmlspecialchars((string) $f['failure_reason']) ?></code></td>
                    <td>
                        <?= htmlspecialchars((string) $f['file_kind']) ?>
                        <div class="if-sub">
                            <?= if_size((int) $f['file_bytes']) ?><?= $f['page_count'] ? ' &middot; ' . (int) $f['page_count'] . ' pages' : '' ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($isHeld): ?>
                            <span class="if-expiry if-expiry-<?= $urgency ?>"><?= htmlspecialchars($expiryText) ?></span>
                        <?php else: ?>
                            <span class="if-sub">gone from disk</span>
                        <?php endif; ?>
                    </td>
                    <td class="if-col-actions">
                        <?php if ($isHeld): ?>
                            <form method="post" action="../_actions/import_file_action.php" class="if-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="download">
                                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                <button type="submit" class="if-btn if-btn-primary">Download</button>
                            </form>
                            <form method="post" action="../_actions/import_file_action.php" class="if-inline"
                                  onsubmit="return confirm('Delete this file without looking at it?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                <button type="submit" class="if-btn">Delete</button>
                            </form>
                        <?php elseif (!empty($f['downloaded_by'])): ?>
                            <span class="if-sub">
                                Taken by <?= htmlspecialchars((string) $f['downloaded_by']) ?>
                                <time data-epoch="<?= (int) strtotime((string) $f['downloaded_at']) ?>"></time>
                            </span>
                        <?php else: ?>
                            <span class="if-sub">Deleted unread</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<p class="if-footnote">
    A download decrypts the file into the response and removes the server's copy in the same
    request, before the body is sent. The row stays as a record of who took it and when.
</p>

<?php endif; ?>

</div>
</body>
</html>
