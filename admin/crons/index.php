<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Cron Activity';
$page_description = "What each cron job has been doing.";

// ─── Time range ─────────────────────────────────────────────────────────────
$range = $_GET['range'] ?? '7d';
$rangeMap = [
    'today' => ['label' => 'Today',        'interval' => '1 DAY'],
    '7d'    => ['label' => 'Last 7 days',  'interval' => '7 DAY'],
    '30d'   => ['label' => 'Last 30 days', 'interval' => '30 DAY'],
    'all'   => ['label' => 'All time',     'interval' => null],
];
if (!isset($rangeMap[$range])) $range = '7d';
$rangeLabel = $rangeMap[$range]['label'];
$rangeInterval = $rangeMap[$range]['interval'];

// ─── Per-cron display config ────────────────────────────────────────────────
// Each entry: ordered metric keys with friendly labels. The page sums the
// metric across all runs in the time range. Crons not yet writing to
// cron_runs will show "No runs in range" with zeros across the board.
$cronConfig = [
    'argo_books_sync' => [
        'label'     => 'Argo Books Books Sync',
        'frequency' => 'daily',
        'description' => "Pushes this business's own trading history into a company inside Argo Books, through the public API: subscription income, the customers who paid it, affiliate commissions, the affiliates they went to, and refunds against income already sent. Nothing lands in the books unattended, because the API is an ingest queue and someone accepts the proposals inside the app. Most days almost everything is skipped: a row is only sent when it has never been sent or when what it would send has changed, so a large 'Skipped, unchanged' count next to small created counts is the healthy shape. 'Failed' above zero means objects were rejected and the detail is in cron/logs/; 'Refunds deferred' means a refund arrived before the revenue it reverses and will go out on the next run. Anything you discard in the app is picked up on the next run and never offered again, which is what the two 'declined' counts track.",
        'metrics'   => [
            'category_created'  => 'Categories created',
            'customer_created'  => 'Customers created',
            'customer_updated'  => 'Customers updated',
            'supplier_created'  => 'Suppliers created',
            'supplier_updated'  => 'Suppliers updated',
            'revenue_created'   => 'Revenue entries created',
            'revenue_updated'   => 'Revenue entries updated',
            'expense_created'   => 'Expenses created',
            'expense_updated'   => 'Expenses updated',
            'refund_created'    => 'Refunds created',
            'refunds_deferred'  => 'Refunds waiting on their revenue',
            'skipped_unchanged' => 'Skipped, unchanged',
            'newly_rejected'    => 'Newly declined in the app',
            'skipped_rejected'  => 'Skipped, declined earlier',
            'failed'            => 'Rejected by the API',
        ],
        'expected_interval_hours' => 48,
    ],
    'api_webhook_delivery' => [
        'label'     => 'API Webhook Delivery',
        'frequency' => 'every minute',
        'description' => "Delivers Argo Books API events to developer webhook endpoints. Events fire when a merchant imports, rejects, or undoes data a developer pushed, which is the only way an integration learns what happened to it. Delivery is signed and retried up to six times over about 15 hours; an endpoint whose last twenty deliveries all failed is switched off automatically and the merchant re-enables it once their receiver is fixed. A high 'Exhausted' count means somebody's integration has been broken long enough to lose events.",
        'metrics'   => [
            'due'                 => 'Deliveries due this run',
            'delivered'           => 'Delivered successfully',
            'retried'             => 'Failed, will retry',
            'exhausted'           => 'Gave up after 6 attempts',
            'blocked'             => 'Refused, destination not public',
            'endpoints_disabled'  => 'Endpoints auto-disabled',
            'events_pruned'       => 'Old events pruned',
        ],
        'expected_interval_hours' => 1,
    ],
    'api_retention' => [
        'label'     => 'API Retention',
        'frequency' => 'daily',
        'description' => "Deletes Argo Books API objects that will never be used again: ones a developer deleted, and ones a merchant rejected in the app, 30 days after that happened. Imported objects are kept, because the app looks them up later when a new sale names an old customer or a refund names its sale, and anything another object still points at is kept for the same reason. Zeros are normal on most days.",
        'metrics'   => [
            'objects_deleted'    => 'Objects deleted',
            'line_items_deleted' => 'Line items deleted',
        ],
        'expected_interval_hours' => 48,
    ],
    'payroll_rate_reminder' => [
        'label'     => 'Payroll Rate Reminder',
        'frequency' => 'daily',
        'description' => 'Chases the twice yearly CRA rate update. Emails on the 10th to 20th of December and June to start the work, and again in the last three days before the changeover if the file still is not up. Both go quiet as soon as the edition appears in resources/downloads/payroll/, so the three day warning arriving means the upload has not happened. Missing a changeover stops payroll: the app refuses to calculate a pay run when it has no table for that pay date, rather than falling back to the previous edition, so nothing wrong is produced, but nobody can run payroll until the new file ships.',
        'metrics'   => [
            'in_window'    => 'Ran inside a reminder window',
            'file_present' => 'Edition already uploaded, nothing to chase',
            'email_sent'   => 'Reminder emails sent',
            'already_sent' => 'Skipped, already sent this window',
        ],
    ],
    'outreach_pipeline' => [
        'label'     => 'Outreach Pipeline',
        'frequency' => 'daily',
        'description' => 'Writes each new lead on the Outreach page a personalized intro email with AI, sends it, and schedules follow-ups. It does not find leads: they are added by hand or by CSV.',
        'metrics'   => [
            'first_emails_sent'         => 'First emails sent',
            'followups_sent'            => 'Follow-ups sent',
            'drafts_generated'          => 'Drafts generated',
            'followup_drafts_generated' => 'Follow-up drafts',
        ],
        'expected_interval_hours' => 48,
    ],
    'agent_watch' => [
        'label'     => 'Marketing Agent Watch',
        'frequency' => 'daily',
        'description' => 'Checks that the marketing agent finished a run in the last day and a half, and emails you if it did not. The agent runs outside the site, so a run that never starts would otherwise go unnoticed.',
        'metrics'   => [
            'agent_on'             => 'Agent switched on (1 or 0)',
            'hours_since_last_run' => 'Hours since its last run',
            'waiting_for_approval' => 'Posts and emails waiting for you',
            'warnings_sent'        => 'Warnings sent',
        ],
        'expected_interval_hours' => 48,
    ],
    'portal_invoice_reminders' => [
        'label'     => 'Portal Invoice Reminders',
        'frequency' => 'daily',
        'description' => "Emails a merchant's customers when a portal invoice goes unpaid, at 3, 7 and 14 days past the due date, then stops. Opt-in per company from the Argo Books settings. Only chases invoices that fell due after the merchant switched reminders on, so enabling it never blasts a backlog of old overdue invoices. Stops immediately once an invoice is paid or cancelled.",
        'metrics'   => [
            'invoices_scanned'   => 'Invoices scanned',
            'reminders_sent'     => 'Reminders sent',
            'reminders_skipped'  => 'Skipped (paid/cancelled/suppressed)',
            'reminders_failed'   => 'Send failures',
            'stage1_sent'        => 'Stage 1 (3 days)',
            'stage2_sent'        => 'Stage 2 (7 days)',
            'stage3_sent'        => 'Stage 3 (14 days, final)',
        ],
        'expected_interval_hours' => 48,
    ],
    'subscription_renewal' => [
        'label'     => 'Subscription Renewal',
        'frequency' => 'daily',
        'description' => "Charges every Argo Books Premium subscriber whose billing date is today, sends them a receipt, and emails anyone whose card just declined. After three failed attempts in a row, the subscription is suspended so we don't keep retrying a bad card.",
        'metrics'   => [
            'renewals_succeeded'      => 'Renewals successful',
            'renewals_failed'         => 'Renewals failed',
            'subscriptions_suspended' => 'Subscriptions suspended',
            'subscriptions_expired'   => 'Subscriptions expired',
        ],
        'expected_interval_hours' => 48,
    ],
    'account_purge' => [
        'label'     => 'Account Purge',
        'frequency' => 'daily',
        'description' => "Permanently deletes accounts that were marked for deletion 30+ days ago. The grace period gives users time to change their mind by signing back in. Logging in cancels the deletion request.",
        'metrics'   => [
            'accounts_deleted' => 'Accounts deleted',
        ],
        'expected_interval_hours' => 48,
    ],
    'reply_checker' => [
        'label'     => 'Reply Checker',
        'frequency' => 'hourly',
        'description' => "Checks the contact inbox for replies to outreach emails. Any lead that replies gets auto-marked as 'replied' so the system stops sending them follow-ups.",
        'metrics'   => [
            'replies_matched' => 'Replies matched',
            'emails_scanned'  => 'Emails scanned',
        ],
        'expected_interval_hours' => 2,
    ],
    'refund_cooling_off_promoter' => [
        'label'     => 'Refund Cooling-Off Promoter',
        'frequency' => 'every minute',
        'description' => "Pushes refund requests through to the payment provider (Stripe, Square, etc.) once their 24-hour cooling-off window ends. The waiting period is a fraud safeguard: it gives us a chance to spot suspicious refund patterns before the money actually moves.",
        'metrics'   => [
            'refunds_promoted'       => 'Refunds promoted',
            'refunds_auto_cancelled' => 'Refunds auto-cancelled',
        ],
        'expected_interval_hours' => 1,
    ],
    'refund_stale_processing_reconcile' => [
        'label'     => 'Refund Stale Processing',
        'frequency' => 'every 5 minutes',
        'description' => "When a refund has been 'processing' for over half an hour, this directly asks the payment provider what really happened. Useful for the cases where their webhook never reached us. Without this, an actually-completed refund could stay stuck in 'processing' forever in our records.",
        'metrics'   => [
            'refunds_reconciled' => 'Refunds reconciled',
        ],
        'expected_interval_hours' => 1,
    ],
    'refund_stale_request_cleanup' => [
        'label'     => 'Refund Stale Request Cleanup',
        'frequency' => 'hourly',
        'description' => "Cancels refund requests where the user started the flow but never confirmed it, e.g. they closed the tab before entering their email-verification code. After an hour, the request is automatically dropped so the audit log doesn't fill up with abandoned attempts.",
        'metrics'   => [
            'requests_cancelled' => 'Requests cancelled',
        ],
        'expected_interval_hours' => 2,
    ],
    'refund_velocity_baseline_recompute' => [
        'label'     => 'Refund Velocity Baselines',
        'frequency' => 'nightly',
        'description' => "Recalculates each company's 'normal' refund volume from their own history. The fraud-detection thresholds (3×, 10×, 25%, 50% of normal) then adapt to each shop's size, so a huge merchant doesn't get blocked by limits set for a small one, and a small merchant can't drain accidentally because the limits were too high.",
        'metrics'   => [
            'baselines_recomputed' => 'Baselines recomputed',
        ],
        'expected_interval_hours' => 36,
    ],
    'marketing_broadcast' => [
        'label'     => 'Marketing Broadcast Sender',
        'frequency' => 'every 5 minutes',
        'description' => "Delivers the email broadcasts you queue from the Marketing page. Each run sends a capped batch (up to 100) so a big list goes out over several runs without tripping rate limits, and it skips anyone who unsubscribed after the broadcast was queued.",
        'metrics'   => [
            'emails_sent'           => 'Emails sent',
            'emails_failed'         => 'Emails failed',
            'broadcasts_completed'  => 'Broadcasts completed',
        ],
        'expected_interval_hours' => 1,
    ],
    'indexnow_submit' => [
        'label'     => 'IndexNow Submit',
        'frequency' => 'daily',
        'description' => "Tells Bing, Yandex, DuckDuckGo and other IndexNow search engines about pages that changed since the last run, so they get recrawled quickly without manual submission. Does not affect Google, which doesn't use IndexNow.",
        'metrics'   => [
            'changed_urls' => 'Pages changed',
            'submitted_ok' => 'Pages submitted',
        ],
        'expected_interval_hours' => 48,
    ],
    'purge_import_files' => [
        'label'     => 'Purge Import Files',
        'frequency' => 'hourly',
        'description' => "Deletes the diagnostic files people sent after a failed import, once each one passes its retention window, whether or not anyone looked at it. The Import Files page promises that deletion, so this is the thing keeping the promise: a run that stops happening is a privacy problem, not an untidy disk. 'Still held' is the number of customer files sitting on the server right now and the one to watch, because it should fall back to zero on its own. 'Orphans removed' counts files whose database row is missing, which can only happen if an upload died between writing the file and recording it; anything other than zero there is worth a look.",
        'metrics'   => [
            'expired_rows'    => 'Expired',
            'files_removed'   => 'Files deleted',
            'orphans_removed' => 'Orphans deleted',
            'kb_freed'        => 'KB freed',
            'still_held'      => 'Still held',
        ],
        'expected_interval_hours' => 2,
    ],
];

// ─── Aggregate runs in the time range ───────────────────────────────────────
$intervalClause = $rangeInterval ? "started_at >= DATE_SUB(NOW(), INTERVAL $rangeInterval)" : '1=1';
$rowsByCron = [];
try {
    $stmt = $pdo->query("SELECT cron_name, status, started_at, completed_at, metrics, error_message
        FROM cron_runs
        WHERE $intervalClause
        ORDER BY started_at DESC");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rowsByCron[$r['cron_name']][] = $r;
    }
} catch (PDOException $e) {
    $tableMissing = true;
}

// Latest run across all time (even outside the range), used for the status
// pill so a card without recent activity still tells you when it last ran.
$latestByCron = [];
try {
    $stmt = $pdo->query("SELECT t1.cron_name, t1.started_at, t1.completed_at, t1.status, t1.error_message, t1.metrics
        FROM cron_runs t1
        INNER JOIN (SELECT cron_name, MAX(id) AS max_id FROM cron_runs GROUP BY cron_name) t2
          ON t1.id = t2.max_id");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $latestByCron[$r['cron_name']] = $r;
    }
} catch (PDOException $e) {
    // table missing, fall through to empty state
}

// ─── Helpers ────────────────────────────────────────────────────────────────
function fmt_int($n) {
    return number_format((int) $n);
}

function relative_time_ago($datetime) {
    if (!$datetime) return 'never';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' days ago';
}

function cron_status_pill($latest, $expectedIntervalHours) {
    if (!$latest) return ['class' => 'status-stale', 'label' => 'No runs'];
    if ($latest['status'] === 'error') return ['class' => 'status-error', 'label' => 'Error'];
    if ($latest['status'] === 'running') {
        // A row stuck in 'running' well past when the run should have finished is
        // an orphan (process died before recording a result), not a live run.
        $runHours = (time() - strtotime($latest['started_at'])) / 3600;
        if ($runHours > max($expectedIntervalHours, 1)) {
            return ['class' => 'status-error', 'label' => 'Stalled'];
        }
        return ['class' => 'status-running', 'label' => 'Running'];
    }
    $ageHours = (time() - strtotime($latest['started_at'])) / 3600;
    if ($ageHours > $expectedIntervalHours * 2) return ['class' => 'status-stale', 'label' => 'Stale'];
    return ['class' => 'status-ok', 'label' => 'OK'];
}

function aggregate_metrics($runs, $metricKeys) {
    $totals = array_fill_keys(array_keys($metricKeys), 0);
    foreach ($runs as $r) {
        if (empty($r['metrics'])) continue;
        $m = json_decode($r['metrics'], true);
        if (!is_array($m)) continue;
        foreach ($metricKeys as $key => $_) {
            if (isset($m[$key]) && is_numeric($m[$key])) {
                $totals[$key] += (int) $m[$key];
            }
        }
    }
    return $totals;
}

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="style.css">

<div class="control-bar">
    <div class="control-group">
        <span class="control-label">Period:</span>
        <div class="control-pills">
            <?php foreach ($rangeMap as $key => $r): ?>
                <a href="?range=<?= $key ?>" class="control-pill <?= $range === $key ? 'active' : '' ?>">
                    <?= htmlspecialchars($r['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!empty($tableMissing)): ?>
    <div class="empty-state">
        <strong>cron_runs table not found.</strong>
        Run the SQL in mysql_schema.sql to create it. The page will populate once any cron has run.
    </div>
<?php else: ?>
    <div class="cron-cards">
    <?php foreach ($cronConfig as $cronName => $cfg):
        $runs = $rowsByCron[$cronName] ?? [];
        $latest = $latestByCron[$cronName] ?? null;
        $totals = aggregate_metrics($runs, $cfg['metrics']);
        $pill = cron_status_pill($latest, $cfg['expected_interval_hours']);
        $hasErrorInRange = false;
        foreach ($runs as $r) if ($r['status'] === 'error') { $hasErrorInRange = true; break; }
    ?>
        <div class="cron-card">
            <div class="cron-card-header">
                <div>
                    <h2>
                        <?= htmlspecialchars($cfg['label']) ?>
                        <?php if (!empty($cfg['description'])): ?>
                            <span class="cron-info" tabindex="0" aria-label="What does this cron do?">
                                <span class="cron-info-icon" aria-hidden="true">i</span>
                                <span class="cron-info-tooltip" role="tooltip"><?= htmlspecialchars($cfg['description']) ?></span>
                            </span>
                        <?php endif; ?>
                    </h2>
                    <div class="cron-meta">
                        <span class="cron-freq"><?= htmlspecialchars($cfg['frequency']) ?></span>
                        <span class="cron-runs"><?= count($runs) ?> runs in range</span>
                        <span class="cron-last">last: <?= htmlspecialchars(relative_time_ago($latest['started_at'] ?? null)) ?></span>
                    </div>
                </div>
                <span class="cron-pill <?= $pill['class'] ?>"><?= htmlspecialchars($pill['label']) ?></span>
            </div>

            <?php if (empty($runs)): ?>
                <div class="cron-empty">No runs recorded in <?= htmlspecialchars(strtolower($rangeLabel)) ?>.</div>
            <?php else: ?>
                <div class="cron-metrics">
                    <?php foreach ($cfg['metrics'] as $key => $label): ?>
                        <div class="cron-metric">
                            <div class="metric-value"><?= fmt_int($totals[$key]) ?></div>
                            <div class="metric-label"><?= htmlspecialchars($label) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($latest): ?>
                <details class="cron-detail">
                    <summary>Last run detail · <?= htmlspecialchars($latest['started_at']) ?> · <?= htmlspecialchars($latest['status']) ?></summary>
                    <?php if (!empty($latest['error_message'])): ?>
                        <?php // The same column carries the summary line on a successful run, so only style it as an error when it is one. ?>
                        <pre class="<?= $latest['status'] === 'ok' ? 'cron-summary' : 'cron-error' ?>"><?= htmlspecialchars($latest['error_message']) ?></pre>
                    <?php endif; ?>
                    <?php
                        $latestMetrics = !empty($latest['metrics']) ? json_decode($latest['metrics'], true) : null;
                        if (is_array($latestMetrics) && !empty($latestMetrics)):
                    ?>
                        <table class="cron-metric-table">
                            <?php foreach ($latestMetrics as $k => $v): ?>
                                <tr>
                                    <td class="k"><?= htmlspecialchars((string) $k) ?></td>
                                    <td class="v"><?= htmlspecialchars(is_scalar($v) ? (string) $v : json_encode($v)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php elseif ($latest['status'] === 'ok'): ?>
                        <p class="text-muted">Last run completed with no recorded metrics.</p>
                    <?php endif; ?>
                </details>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>

    document.querySelectorAll('a[href^="?range="]').forEach(link => {
        link.addEventListener('click', function () {
            sessionStorage.setItem('scrollPosition', window.scrollY);
        });
    });
</script>
<script>window.ADMIN_PRESERVE_SCROLL = ['a[href^="?range="]'];</script>
<script src="../preserve-scroll.js" defer></script>
</body>
</html>
