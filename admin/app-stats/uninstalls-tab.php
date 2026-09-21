<?php
// Uninstall survey tab body, included by admin/app-stats/index.php inside the
// #uninstalls tab. Admin auth + page chrome are handled by the host page; this
// reads the answers people left on /uninstall. Reuses .stats-grid /
// .stat-card / .section-title / .table-container from the host page's styles.

// Direct-access guard. This partial is only valid when included by its parent
// page (app-stats/index.php), which starts the session and verifies the admin
// login. Requested directly, no session is started so $_SESSION is empty and we
// fail closed. (An admin/.htaccess also denies *-tab.php as defense in depth.)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../../db_connect.php';

// The labels the survey offers, so a stored value reads the same here as it did there.
$uninstallReasons = [
    'missing_feature' => "Missing something they need",
    'too_complicated' => 'Harder to use than expected',
    'switched'        => 'Went with something else',
    'price'           => "Price didn't work",
    'not_needed'      => "Doesn't need accounting software",
    'problem'         => "Didn't work on their computer",
    'just_looking'    => 'Only trying it out',
    'other'           => 'Something else',
];

$uninstallRows = [];
$uninstallCounts = [];
$uninstallError = '';

try {
    $stmt = $pdo->prepare(
        'SELECT reason, comment, app_version, platform, created_at
         FROM uninstall_feedback
         WHERE environment = ?
         ORDER BY created_at DESC
         LIMIT 200'
    );
    $stmt->execute([current_environment()]);
    $uninstallRows = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT reason, COUNT(*) AS total
         FROM uninstall_feedback
         WHERE environment = ?
         GROUP BY reason
         ORDER BY total DESC'
    );
    $stmt->execute([current_environment()]);
    foreach ($stmt->fetchAll() as $row) {
        $uninstallCounts[$row['reason']] = (int) $row['total'];
    }
} catch (Exception $e) {
    error_log('Uninstall feedback read error: ' . $e->getMessage());
    $uninstallError = 'Could not read the answers.';
}

$uninstallTotal = array_sum($uninstallCounts);
$uninstallWithComment = count(array_filter($uninstallRows, static fn($r) => !empty($r['comment'])));
?>

<h2 class="section-title">Uninstall Survey</h2>

<?php if ($uninstallError !== ''): ?>
    <p class="error-message"><?= htmlspecialchars($uninstallError) ?></p>
<?php else: ?>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Answers</h3>
            <div class="value"><?= number_format($uninstallTotal) ?></div>
        </div>
        <div class="stat-card">
            <h3>With a comment</h3>
            <div class="value"><?= number_format($uninstallWithComment) ?></div>
        </div>
        <div class="stat-card">
            <h3>Most common reason</h3>
            <div class="value" style="font-size: 20px;">
                <?php
                $topReason = array_key_first($uninstallCounts);
                echo $topReason === null ? '—' : htmlspecialchars($uninstallReasons[$topReason] ?? $topReason);
                ?>
            </div>
        </div>
    </div>

    <?php if ($uninstallTotal === 0): ?>
        <p>Nobody has answered yet. The survey is at <code>/uninstall</code>, opened by the
           uninstaller. Only people who choose to answer appear here.</p>
    <?php else: ?>

        <div class="table-container">
            <h3 class="section-title">Why they left</h3>
            <table>
                <thead>
                    <tr><th>Reason</th><th>Answers</th><th>Share</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($uninstallCounts as $reason => $count): ?>
                        <tr>
                            <td><?= htmlspecialchars($uninstallReasons[$reason] ?? $reason) ?></td>
                            <td><?= number_format($count) ?></td>
                            <td><?= $uninstallTotal > 0 ? round($count / $uninstallTotal * 100) : 0 ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-container">
            <h3 class="section-title">What they said</h3>
            <table>
                <thead>
                    <tr><th>When</th><th>Reason</th><th>Comment</th><th>Version</th><th>Platform</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($uninstallRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($row['created_at']))) ?></td>
                            <td><?= htmlspecialchars($uninstallReasons[$row['reason']] ?? $row['reason']) ?></td>
                            <td><?= $row['comment'] ? nl2br(htmlspecialchars($row['comment'])) : '<span style="opacity:0.5;">—</span>' ?></td>
                            <td><?= htmlspecialchars($row['app_version'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($row['platform'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
<?php endif; ?>
