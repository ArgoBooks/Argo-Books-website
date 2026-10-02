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
    'reinstalling'    => 'Reinstalling, not leaving',
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

        <?php
        // Keyed by reason rather than by position, so a reason keeps its colour when the
        // order changes. "Something else" and any reason not listed here are grey.
        $uninstallColors = [
            'missing_feature' => ['#2a78d6', '#3987e5'],
            'too_complicated' => ['#eb6834', '#d95926'],
            'switched'        => ['#1baf7a', '#199e70'],
            'price'           => ['#eda100', '#c98500'],
            'not_needed'      => ['#e87ba4', '#d55181'],
            'problem'         => ['#008300', '#008300'],
            'just_looking'    => ['#4a3aa7', '#9085e9'],
            'reinstalling'    => ['#e34948', '#e66767'],
        ];
        $uninstallChart = [];
        foreach ($uninstallCounts as $reason => $count) {
            $uninstallChart[] = [
                'label' => ($uninstallReasons[$reason] ?? $reason) . ': ' . number_format($count)
                    . ' (' . round($count / $uninstallTotal * 100) . '%)',
                'count' => $count,
                'color' => $uninstallColors[$reason] ?? ['#8a8a85', '#8a8a85'],
            ];
        }
        ?>
        <div class="chart-container" style="height: 380px;">
            <h2>Why they left</h2>
            <canvas id="uninstallReasonsChart"></canvas>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var rows = <?= json_encode($uninstallChart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var canvas = document.getElementById('uninstallReasonsChart');
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            new Chart(canvas, {
                type: 'pie',
                data: {
                    labels: rows.map(function (r) { return r.label; }),
                    datasets: [{
                        data: rows.map(function (r) { return r.count; }),
                        backgroundColor: rows.map(function (r) { return r.color[dark ? 1 : 0]; }),
                        // The card's own colour, so the border reads as a gap between slices.
                        borderColor: getComputedStyle(canvas.parentElement).backgroundColor,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' },
                        tooltip: { callbacks: { label: function (ctx) { return ctx.label; } } }
                    }
                }
            });
        });
        </script>

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
