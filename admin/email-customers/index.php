<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_marketing.php';
require_once __DIR__ . '/../../cron/lib/broadcast_helpers.php'; // pulls in email_marketing + env_helper

// Auth: admin session only (matches admin/outreach/index.php pattern)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Email Customers';
$page_description = 'Send broadcasts to your opt-in lists and review / feedback emails to license customers.';

$flash = null;
$flash_type = null;
// Which tab renders active. Set by the POST handler so the acted-on tab stays open.
$active_tab = 'broadcast';

$audiences = broadcast_audiences();

/**
 * Eligibility query: license keys we can send a review/feedback email to.
 * Re-used both for rendering and for the POST handler (defence in depth).
 */
function fetch_eligible_licenses(PDO $pdo, ?array $idFilter = null): array
{
    $sql = "
        SELECT
          lk.id, lk.email, lk.license_key,
          lk.created_at AS purchased_at,
          GREATEST(
            COALESCE((SELECT MAX(updated_at) FROM receipt_scan_usage WHERE license_key = lk.license_key AND environment = ?), '1970-01-01'),
            COALESCE((SELECT MAX(updated_at) FROM invoice_send_usage  WHERE license_key = lk.license_key AND environment = ?), '1970-01-01')
          ) AS last_active_at
        FROM license_keys lk
        WHERE lk.activated = 1
          AND lk.created_at <= NOW() - INTERVAL 30 DAY
          AND lk.review_email_sent_at IS NULL
          AND NOT EXISTS (
            SELECT 1 FROM email_suppressions s
            WHERE s.email = lk.email AND s.context IN ('reviews','all_marketing')
          )
    ";

    // The first two placeholders are the environment filters inside the
    // last_active_at subqueries, so they have to stay at the head of the
    // parameter list, ahead of the optional id filter appended below.
    $params = [current_environment(), current_environment()];
    if ($idFilter !== null && count($idFilter) > 0) {
        $placeholders = implode(',', array_fill(0, count($idFilter), '?'));
        $sql .= " AND lk.id IN ($placeholders)";
        $params = array_merge($params, array_values(array_map('intval', $idFilter)));
    }

    $sql .= " ORDER BY lk.created_at ASC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * "Active" = used the desktop app in the last 14 days.
 */
function is_active_user(string $lastActiveAt): bool
{
    $threshold = strtotime('-14 days');
    return strtotime($lastActiveAt) >= $threshold;
}

// ─── POST handlers (broadcast + review emails share one handler) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!$postedToken || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $flash = 'Session expired or invalid request token. Please reload and try again.';
        $flash_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // ---- Review / feedback emails ----
        if ($action === 'skip') {
            $active_tab = 'reviews';
            $skipId = (int) ($_POST['license_id'] ?? 0);
            if ($skipId > 0) {
                $stmt = $pdo->prepare("UPDATE license_keys
                                       SET review_email_sent_at = NOW(),
                                           review_email_variant = 'manually_skipped'
                                       WHERE id = ? AND review_email_sent_at IS NULL");
                $stmt->execute([$skipId]);
                $flash = 'License skipped. It will no longer appear in this list.';
                $flash_type = 'success';
            }
        } elseif ($action === 'send') {
            $active_tab = 'reviews';
            $selected = $_POST['license_ids'] ?? [];
            if (!is_array($selected) || count($selected) === 0) {
                $flash = 'No licenses selected.';
                $flash_type = 'error';
            } else {
                $eligible = fetch_eligible_licenses($pdo, $selected);
                $sent = 0;
                $failed = 0;

                foreach ($eligible as $row) {
                    $licenseId = (int) $row['id'];
                    $email = $row['email'];
                    $variant = is_active_user($row['last_active_at']) ? 'active' : 'inactive';

                    // Atomic claim: only proceed if no other request beat us to this row.
                    // The IS NULL guard makes the operation idempotent under concurrent
                    // admin sessions and protects against double-emailing the customer.
                    $claim = $pdo->prepare("UPDATE license_keys
                                            SET review_email_sent_at = NOW(),
                                                review_email_variant = ?
                                            WHERE id = ? AND review_email_sent_at IS NULL");
                    $claim->execute([$variant, $licenseId]);
                    if ($claim->rowCount() === 0) {
                        // Already claimed by another request. Skip silently.
                        continue;
                    }

                    $ok = ($variant === 'active')
                        ? send_review_request_email($licenseId, $email)
                        : send_feedback_request_email($licenseId, $email);

                    if ($ok) {
                        $sent++;
                    } else {
                        // Best-effort revert so the admin can retry. Scoped by variant
                        // so we only un-claim the row WE just claimed.
                        $revert = $pdo->prepare("UPDATE license_keys
                                                 SET review_email_sent_at = NULL,
                                                     review_email_variant = NULL
                                                 WHERE id = ? AND review_email_variant = ?");
                        $revert->execute([$licenseId, $variant]);
                        $failed++;
                    }
                }

                $flash = "Sent {$sent} email(s)." . ($failed > 0 ? " {$failed} failed, check error logs." : '');
                $flash_type = $failed > 0 ? 'error' : 'success';
            }

        // ---- Broadcasts ----
        } elseif ($action === 'send_test' || $action === 'queue_broadcast' || $action === 'cancel_broadcast') {
            $active_tab = 'broadcast';
            $audience = $_POST['audience'] ?? '';
            $subject  = trim($_POST['subject'] ?? '');
            $body     = trim($_POST['html_body'] ?? '');
            $validAudience = isset($audiences[$audience]);

            if ($action === 'send_test') {
                $testEmail = trim($_POST['test_email'] ?? '');
                if (!$validAudience) {
                    $flash = 'Pick a valid audience first.';
                    $flash_type = 'error';
                } elseif (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                    $flash = 'Enter a valid test email address.';
                    $flash_type = 'error';
                } elseif ($subject === '' || $body === '') {
                    $flash = 'Subject and body are both required to send a test.';
                    $flash_type = 'error';
                } else {
                    // A test send bypasses the gate (it goes only to the admin's own
                    // address) and uses a placeholder unsubscribe link.
                    $ok = send_broadcast_email($testEmail, '[TEST] ' . $subject, $body, site_url('/unsubscribe/marketing.php'));
                    $flash = $ok ? "Test email sent to {$testEmail}." : 'Test email failed to send, check the error log.';
                    $flash_type = $ok ? 'success' : 'error';
                }
            } elseif ($action === 'queue_broadcast') {
                if (!$validAudience) {
                    $flash = 'Pick a valid audience.';
                    $flash_type = 'error';
                } elseif ($subject === '' || $body === '') {
                    $flash = 'Subject and body are both required.';
                    $flash_type = 'error';
                } else {
                    $emails = broadcast_audience_emails($pdo, $audience);
                    if (!$emails) {
                        $flash = 'That audience has no deliverable recipients right now (everyone is unsubscribed, unconfirmed, or suppressed).';
                        $flash_type = 'error';
                    } else {
                        try {
                            $pdo->beginTransaction();

                            $ins = $pdo->prepare(
                                "INSERT INTO marketing_broadcasts (subject, html_body, audience, status, total_recipients, created_by)
                                 VALUES (?, ?, ?, 'queued', ?, ?)"
                            );
                            $ins->execute([$subject, $body, $audience, count($emails), $_SESSION['admin_username'] ?? 'admin']);
                            $broadcastId = (int) $pdo->lastInsertId();

                            // Snapshot recipients in chunks.
                            $recStmt = $pdo->prepare(
                                'INSERT IGNORE INTO marketing_broadcast_recipients (broadcast_id, email, status) VALUES (?, ?, ' . "'pending')"
                            );
                            foreach ($emails as $em) {
                                $recStmt->execute([$broadcastId, $em]);
                            }

                            $pdo->commit();
                            $flash = 'Queued a broadcast to ' . count($emails) . ' recipient(s). The sender cron will deliver them in batches.';
                            $flash_type = 'success';
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            error_log('queue_broadcast failed: ' . $e->getMessage());
                            $flash = 'Could not queue the broadcast, check the error log.';
                            $flash_type = 'error';
                        }
                    }
                }
            } elseif ($action === 'cancel_broadcast') {
                $bid = (int) ($_POST['broadcast_id'] ?? 0);
                if ($bid > 0) {
                    // Only stop ones that haven't finished; leaves already-sent rows intact.
                    $upd = $pdo->prepare("UPDATE marketing_broadcasts SET status = 'canceled' WHERE id = ? AND status IN ('queued','sending')");
                    $upd->execute([$bid]);
                    $pdo->prepare("UPDATE marketing_broadcast_recipients SET status = 'skipped', error = 'broadcast canceled' WHERE broadcast_id = ? AND status = 'pending'")->execute([$bid]);
                    $flash = $upd->rowCount() ? 'Broadcast canceled. Pending recipients will not be emailed.' : 'That broadcast could not be canceled (already finished?).';
                    $flash_type = $upd->rowCount() ? 'success' : 'error';
                }
            }
        }
    }
}

// ─── Data for rendering ─────────────────────────────────────────────────────
// Broadcast tab: subscriber breakdown, per-audience deliverable counts, recent sends.
$subCounts = ['pending' => 0, 'confirmed' => 0, 'unsubscribed' => 0];
$rows = $pdo->query("SELECT status, COUNT(*) c FROM marketing_subscribers WHERE context = 'newsletter' GROUP BY status")->fetchAll();
foreach ($rows as $r) {
    $subCounts[$r['status']] = (int) $r['c'];
}

$audienceCounts = [];
foreach (array_keys($audiences) as $key) {
    $audienceCounts[$key] = count(broadcast_audience_emails($pdo, $key));
}

// Which surface each subscriber came from. The whole point of putting the capture box on some
// tools and not others is being able to see which ones earn it before doing the rest.
$sourceRows = $pdo->query(
    "SELECT source,
            SUM(status = 'confirmed')    AS confirmed,
            SUM(status = 'pending')      AS pending,
            SUM(status = 'unsubscribed') AS unsubscribed,
            COUNT(*)                     AS total,
            MAX(created_at)              AS latest
     FROM marketing_subscribers
     WHERE context = 'newsletter'
     GROUP BY source
     ORDER BY confirmed DESC, total DESC"
)->fetchAll();

$recent = $pdo->query(
    "SELECT id, subject, audience, status, total_recipients, sent_count, failed_count, skipped_count, created_at
     FROM marketing_broadcasts ORDER BY id DESC LIMIT 20"
)->fetchAll();

// Review tab: eligible license customers, split by activity.
$eligible = fetch_eligible_licenses($pdo);
$active = [];
$inactive = [];
foreach ($eligible as $row) {
    if (is_active_user($row['last_active_at'])) {
        $active[] = $row;
    } else {
        $inactive[] = $row;
    }
}

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="../../resources/styles/checkbox.css">

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash_type) ?>"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="section-tabs">
    <button class="section-tab <?= $active_tab === 'broadcast' ? 'active' : '' ?>" data-tab="broadcast">Broadcast</button>
    <button class="section-tab <?= $active_tab === 'reviews' ? 'active' : '' ?>" data-tab="reviews">Review requests</button>
</div>

<!-- ══════════════ Broadcast ══════════════ -->
<div id="broadcast" class="tab-content <?= $active_tab === 'broadcast' ? 'active' : '' ?>">
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Confirmed subscribers</h3>
            <div class="stat-value"><?= number_format($subCounts['confirmed']) ?></div>
        </div>
        <div class="stat-card">
            <h3>Pending confirmation</h3>
            <div class="stat-value"><?= number_format($subCounts['pending']) ?></div>
        </div>
        <div class="stat-card">
            <h3>Unsubscribed</h3>
            <div class="stat-value"><?= number_format($subCounts['unsubscribed']) ?></div>
        </div>
    </div>

    <h2 class="mkt-section-head" style="margin-top:36px">Where they signed up</h2>
    <?php if (!$sourceRows): ?>
        <p class="empty-state">No subscribers yet.</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Confirmed</th>
                        <th>Pending</th>
                        <th>Unsubscribed</th>
                        <th>Total</th>
                        <th>Most recent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sourceRows as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars(marketing_source_label((string) $s['source'])) ?></td>
                            <td><?= number_format((int) $s['confirmed']) ?></td>
                            <td><?= number_format((int) $s['pending']) ?></td>
                            <td><?= number_format((int) $s['unsubscribed']) ?></td>
                            <td><?= number_format((int) $s['total']) ?></td>
                            <td><?= $s['latest'] ? htmlspecialchars(date('Y-m-d', strtotime((string) $s['latest']))) : '&mdash;' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 class="mkt-section-head" style="margin-top:36px">Compose a broadcast</h2>
    <p class="mkt-hint" style="margin:0 auto 16px;max-width:760px;text-align:center">
        The body accepts HTML and is wrapped in the standard Argo email template. An unsubscribe
        footer is appended automatically. Always send yourself a test first. Queuing hands the send
        to the cron, which delivers in batches and skips anyone who has since unsubscribed.
    </p>

    <form method="post" class="mkt-compose" id="broadcast-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="mkt-field">
            <label for="audience">Audience</label>
            <select name="audience" id="audience" required>
                <?php foreach ($audiences as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" data-count="<?= (int) $audienceCounts[$key] ?>">
                        <?= htmlspecialchars($label) ?> — <?= (int) $audienceCounts[$key] ?> deliverable
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mkt-field">
            <label for="subject">Subject</label>
            <input type="text" name="subject" id="subject" maxlength="300" required
                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
        </div>

        <div class="mkt-field">
            <label for="html_body">Body (HTML)</label>
            <textarea name="html_body" id="html_body" required placeholder="<p>Hi there,</p>&#10;<p>Here's what's new in Argo Books this month…</p>"><?= htmlspecialchars($_POST['html_body'] ?? '') ?></textarea>
            <div class="mkt-hint">Tip: keep it simple. Inline styles render most reliably across email clients.</div>
        </div>

        <div class="mkt-actions">
            <div class="mkt-test">
                <input type="email" name="test_email" placeholder="you@argorobots.com (send a test)">
                <button type="submit" name="action" value="send_test" class="btn btn-gray">Send test</button>
            </div>
            <button type="submit" name="action" value="queue_broadcast" class="btn btn-blue" id="queue-btn">Queue broadcast</button>
        </div>
    </form>

    <h2 class="mkt-section-head" style="margin-top:36px">Recent broadcasts</h2>
    <?php if (!$recent): ?>
        <p class="empty-state">No broadcasts yet.</p>
    <?php else: ?>
        <div class="table-container">
            <table data-paginate="25">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Audience</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['subject']) ?></td>
                            <td><?= htmlspecialchars($audiences[$b['audience']] ?? $b['audience']) ?></td>
                            <td><span class="mkt-badge <?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></td>
                            <td>
                                <?= (int) $b['sent_count'] ?>/<?= (int) $b['total_recipients'] ?> sent
                                <?php if ((int) $b['failed_count'] > 0): ?>
                                    · <?= (int) $b['failed_count'] ?> failed
                                <?php endif; ?>
                                <?php if ((int) $b['skipped_count'] > 0): ?>
                                    · <?= (int) $b['skipped_count'] ?> skipped
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($b['created_at']))) ?></td>
                            <td>
                                <?php if (in_array($b['status'], ['queued', 'sending'], true)): ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Cancel this broadcast? Recipients not yet emailed will be skipped.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="cancel_broadcast">
                                        <input type="hidden" name="broadcast_id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit" class="btn btn-small btn-red">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ══════════════ Review requests ══════════════ -->
<div id="reviews" class="tab-content <?= $active_tab === 'reviews' ? 'active' : '' ?>">
    <div class="reviews-intro">
        <p>
            These are license-key customers who purchased 30+ days ago and haven't been emailed yet. Active customers (used the app in the last 14 days) get a review request linking to Capterra. Inactive customers get a feedback request asking them to reply with what's getting in the way. Each customer is asked at most once.
        </p>
        <p>
            Use <strong>Skip</strong> to permanently exclude someone (for example, customers who already left a review or who you've already spoken to in person).
        </p>
    </div>

    <form method="post" id="reviews-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="send">

        <?php
        $renderSection = function (array $rows, string $sectionTitle, string $sectionDescription, string $variantClass) {
            ?>
            <div class="review-section <?= $variantClass ?>">
                <h2><?= htmlspecialchars($sectionTitle) ?> <span class="count">(<?= count($rows) ?>)</span></h2>
                <p class="section-description"><?= htmlspecialchars($sectionDescription) ?></p>

                <?php if (count($rows) === 0): ?>
                    <p class="empty-state">No eligible customers in this group.</p>
                <?php else: ?>
                    <table class="reviews-table" data-paginate="25">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="select-all" data-section="<?= $variantClass ?>" checked></th>
                                <th>Email</th>
                                <th>License key</th>
                                <th>Purchased</th>
                                <th>Last active</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row):
                                $purchased = date('Y-m-d', strtotime($row['purchased_at']));
                                $lastActive = strtotime($row['last_active_at']);
                                $lastActiveDisplay = ($lastActive > strtotime('1970-01-02')) ? date('Y-m-d', $lastActive) : 'Never';
                                $licenseShort = substr($row['license_key'], 0, 12) . '…';
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="row-check <?= $variantClass ?>-row"
                                               name="license_ids[]" value="<?= (int) $row['id'] ?>" checked>
                                    </td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><code title="<?= htmlspecialchars($row['license_key']) ?>"><?= htmlspecialchars($licenseShort) ?></code></td>
                                    <td><?= htmlspecialchars($purchased) ?></td>
                                    <td><?= htmlspecialchars($lastActiveDisplay) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-small btn-gray skip-btn"
                                                data-license-id="<?= (int) $row['id'] ?>">Skip</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php
        };

        $renderSection(
            $active,
            'Active customers (review request)',
            'These customers used the app in the last 14 days. They will receive a Capterra review ask.',
            'active'
        );

        $renderSection(
            $inactive,
            'Inactive customers (feedback request)',
            'These customers have not used the app in the last 14 days. They will receive a "what got in the way?" email asking them to reply.',
            'inactive'
        );
        ?>

        <?php if (count($eligible) > 0): ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-blue">Send to selected</button>
            </div>
        <?php endif; ?>
    </form>

    <form method="post" id="skip-form" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="skip">
        <input type="hidden" name="license_id" id="skip-license-id" value="">
    </form>
</div>

<script>
    // Broadcast: confirm before queueing (not for the test send).
    document.getElementById('broadcast-form').addEventListener('submit', function (e) {
        if (e.submitter && e.submitter.value === 'queue_broadcast') {
            var sel = document.getElementById('audience');
            var count = sel.options[sel.selectedIndex].getAttribute('data-count') || '0';
            if (count === '0') {
                e.preventDefault();
                alert('That audience has no deliverable recipients right now.');
                return;
            }
            if (!confirm('Queue this broadcast to ' + count + ' recipient(s)? This cannot be undone once the cron starts sending.')) {
                e.preventDefault();
            }
        }
    });

    // Review requests: select-all per section, skip, and send confirmation.
    document.querySelectorAll('.select-all').forEach(function (master) {
        master.addEventListener('change', function () {
            var section = master.dataset.section;
            document.querySelectorAll('.row-check.' + section + '-row').forEach(function (cb) {
                cb.checked = master.checked;
            });
        });
    });

    document.querySelectorAll('.skip-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Skip this customer permanently? They will no longer appear in this list, even if they remain eligible.')) {
                return;
            }
            document.getElementById('skip-license-id').value = btn.dataset.licenseId;
            document.getElementById('skip-form').submit();
        });
    });

    document.getElementById('reviews-form').addEventListener('submit', function (e) {
        var checked = document.querySelectorAll('.row-check:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('No customers selected.');
            return;
        }
        if (!confirm('Send emails to ' + checked + ' customer(s)?')) {
            e.preventDefault();
        }
    });
</script>
