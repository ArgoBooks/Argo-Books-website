<?php
/**
 * Settings tab partial.
 *
 * Renders toggles for:
 *   - Auto-send vs Review-before-send (state: auto_send_mode)
 *
 * Plus read-only status: daily send limit and a tail of today's pipeline log.
 *
 * Exposes:
 *   settings_tab_handle_post($pdo)
 *   settings_tab_render($pdo)
 *
 * State values live in outreach_pipeline_state via getState/setState
 * (defined in cron/outreach_pipeline.php, but those helpers are only
 * loaded in CLI context, so we define local equivalents here that use
 * the same table).
 */

/**
 * Local lightweight wrappers for outreach_pipeline_state.
 * Matches the CLI getState/setState behaviour.
 */
function settings_tab_get_state($pdo, $key, $default = null)
{
    try {
        $stmt = $pdo->prepare("SELECT state_value FROM outreach_pipeline_state WHERE state_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['state_value'] : $default;
    } catch (PDOException $e) {
        // Table may not exist yet on a fresh install: treat as default
        return $default;
    }
}

function settings_tab_set_state($pdo, $key, $value)
{
    $stmt = $pdo->prepare("INSERT INTO outreach_pipeline_state (state_key, state_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE state_value = VALUES(state_value)");
    $stmt->execute([$key, $value]);
}

function settings_tab_handle_post($pdo)
{
    $action = $_POST['action'] ?? '';

    if ($action === 'set_outreach_enabled') {
        $enabled = $_POST['enabled'] ?? '';
        $val = ($enabled === '1') ? '1' : '0';
        settings_tab_set_state($pdo, 'outreach_enabled', $val);
        $_SESSION['message'] = 'Outreach system: ' . ($val === '1' ? 'ENABLED' : 'DISABLED');
        $_SESSION['message_type'] = 'success';
        header('Location: index.php?tab=settings'); exit;
    }

    if ($action === 'set_auto_send_mode') {
        $mode = $_POST['mode'] ?? '';
        if (in_array($mode, ['auto', 'review'], true)) {
            settings_tab_set_state($pdo, 'auto_send_mode', $mode);
            $_SESSION['message'] = 'Send mode set to: ' . ($mode === 'auto' ? 'Auto-send' : 'Review before send');
            $_SESSION['message_type'] = 'success';
        }
        header('Location: index.php?tab=settings'); exit;
    }

    if ($action === 'set_followup_sequence') {
        $touches = $_POST['touches'] ?? [];
        if (!is_array($touches)) $touches = [];
        if (count($touches) > 6) {
            $_SESSION['message'] = 'Maximum 6 follow-up touches allowed.';
            $_SESSION['message_type'] = 'error';
            header('Location: index.php?tab=settings'); exit;
        }

        $cfg = [];
        $touchNum = 2;
        foreach ($touches as $touch) {
            if (!is_array($touch)) continue;
            $days = (int) ($touch['days_after_prev'] ?? 0);
            $intent = trim((string) ($touch['default_intent'] ?? ''));
            if ($days < 1 || $days > 90) {
                $_SESSION['message'] = "Touch $touchNum: days_after_prev must be 1-90.";
                $_SESSION['message_type'] = 'error';
                header('Location: index.php?tab=settings'); exit;
            }
            if (strlen($intent) > 200) {
                $_SESSION['message'] = "Touch $touchNum: intent exceeds 200 chars.";
                $_SESSION['message_type'] = 'error';
                header('Location: index.php?tab=settings'); exit;
            }
            // Strip HTML tags defensively
            $intent = strip_tags($intent);
            if ($intent === '') $intent = 'gentle bump';
            $cfg[] = [
                'touch' => $touchNum,
                'days_after_prev' => $days,
                'default_intent' => $intent,
            ];
            $touchNum++;
        }

        settings_tab_set_state($pdo, 'followup_sequence_config', json_encode($cfg, JSON_UNESCAPED_SLASHES));
        $_SESSION['message'] = 'Follow-up sequence saved (' . count($cfg) . ' touch' . (count($cfg) === 1 ? '' : 'es') . ').';
        $_SESSION['message_type'] = 'success';
        header('Location: index.php?tab=settings'); exit;
    }

    if ($action === 'set_max_review_count') {
        $raw = trim((string) ($_POST['max_review_count'] ?? ''));
        // Blank or 0 means "use the env/default fallback": clear the override.
        if ($raw === '' || $raw === '0') {
            settings_tab_set_state($pdo, 'max_review_count', '');
            $_SESSION['message'] = 'Review-count ceiling reset to the default.';
            $_SESSION['message_type'] = 'success';
            header('Location: index.php?tab=settings'); exit;
        }
        if (!ctype_digit($raw) || (int) $raw < 1 || (int) $raw > 2000) {
            $_SESSION['message'] = 'Review-count ceiling must be a whole number between 1 and 2000 (or blank to use the default).';
            $_SESSION['message_type'] = 'error';
            header('Location: index.php?tab=settings'); exit;
        }
        settings_tab_set_state($pdo, 'max_review_count', (string) (int) $raw);
        $_SESSION['message'] = 'Review-count ceiling set to ' . (int) $raw . '.';
        $_SESSION['message_type'] = 'success';
        header('Location: index.php?tab=settings'); exit;
    }

    header('Location: index.php?tab=settings'); exit;
}

function settings_tab_render($pdo)
{
    // ─── First-deploy seed: followup sequence config ───
    $existingFuCfg = settings_tab_get_state($pdo, 'followup_sequence_config', null);
    if ($existingFuCfg === null) {
        $defaultCfg = [
            ['touch' => 2, 'days_after_prev' => 3,  'default_intent' => 'gentle bump'],
            ['touch' => 3, 'days_after_prev' => 7,  'default_intent' => 'different angle'],
            ['touch' => 4, 'days_after_prev' => 14, 'default_intent' => 'final note before closing'],
        ];
        settings_tab_set_state($pdo, 'followup_sequence_config', json_encode($defaultCfg, JSON_UNESCAPED_SLASHES));
    }

    // Master enable flag for the whole outreach system; defaults ON so a
    // fresh install keeps behaving as before.
    $outreachEnabled = settings_tab_get_state($pdo, 'outreach_enabled', '1') === '1';

    // Current state. Default to 'auto' before the admin has explicitly picked
    // a mode, matching the cron pipeline's default in
    // cron/outreach_pipeline.php so the UI and the pipeline always agree.
    $autoSendMode = settings_tab_get_state($pdo, 'auto_send_mode', 'auto');
    if (!in_array($autoSendMode, ['auto', 'review'], true)) $autoSendMode = 'auto';

    $dailyLimit = (int) ($_ENV['OUTREACH_DAILY_SEND_LIMIT'] ?? 10);
    $followupLimit = (int) ($_ENV['OUTREACH_DAILY_FOLLOWUP_LIMIT'] ?? 75);

    // Tail today's pipeline log (last 40 lines) for a quick health check.
    $logLines = [];
    $logPath = __DIR__ . '/../../../cron/logs/outreach_pipeline_' . date('Y-m-d') . '.log';
    if (is_readable($logPath)) {
        // Logs are date-rotated daily so a single file stays small (typically a few KB
        // per cron run).
        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            $logLines = array_slice($lines, -40);
        }
    }
    ?>

    <div class="panel">
        <div class="panel-header">
            <h2>Outreach system</h2>
        </div>
        <div class="panel-content">
            <p class="hint" style="margin-top:0;">
                Master switch for the whole pipeline. When OFF, the cron exits without touching anything (no discovery, no drafts, no sends) even if it's still scheduled at the server level. Use this when you want a full pause without deleting the cron job.
            </p>
            <div class="segmented-toggle">
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="tab" value="settings">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="set_outreach_enabled">
                    <input type="hidden" name="enabled" value="1">
                    <button type="submit" class="segmented-option <?php echo $outreachEnabled ? 'active' : ''; ?>">
                        <span class="segmented-title">Enabled</span>
                        <span class="segmented-desc">Pipeline runs as normal on its cron schedule.</span>
                    </button>
                </form>
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="tab" value="settings">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="set_outreach_enabled">
                    <input type="hidden" name="enabled" value="0">
                    <button type="submit" class="segmented-option <?php echo !$outreachEnabled ? 'active' : ''; ?>">
                        <span class="segmented-title">Disabled</span>
                        <span class="segmented-desc">Cron exits immediately. Nothing runs until re-enabled.</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>Send mode</h2>
        </div>
        <div class="panel-content">
            <p class="hint" style="margin-top:0;">
                Controls whether the hourly pipeline auto-approves and sends drafts, or stops at draft generation for you to review.
            </p>
            <div class="segmented-toggle">
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="tab" value="settings">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="set_auto_send_mode">
                    <input type="hidden" name="mode" value="auto">
                    <button type="submit" class="segmented-option <?php echo $autoSendMode === 'auto' ? 'active' : ''; ?>">
                        <span class="segmented-title">Auto-send</span>
                        <span class="segmented-desc">Drafts are auto-approved and sent, up to <?php echo $dailyLimit; ?>/day. Follow-ups have a separate cap of <?php echo $followupLimit; ?>/day, oldest-due first.</span>
                    </button>
                </form>
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="tab" value="settings">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="set_auto_send_mode">
                    <input type="hidden" name="mode" value="review">
                    <button type="submit" class="segmented-option <?php echo $autoSendMode === 'review' ? 'active' : ''; ?>">
                        <span class="segmented-title">Review before send</span>
                        <span class="segmented-desc">Pipeline generates drafts and stops. Review or edit them in the Leads tab, then click Send Email (or use bulk send).</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php
    // ─── Discovery filters panel ───
    // Review-count ceiling: lower = skews discovery toward newer / smaller
    // businesses (chains and long-established operators accrue lots of reviews).
    $reviewStateRaw = trim((string) settings_tab_get_state($pdo, 'max_review_count', ''));
    $reviewEnvRaw   = trim((string) ($_ENV['OUTREACH_MAX_REVIEW_COUNT'] ?? ''));
    $reviewIsOverride = ($reviewStateRaw !== '' && ctype_digit($reviewStateRaw) && (int) $reviewStateRaw > 0);
    if ($reviewIsOverride) {
        $reviewEffective = (int) $reviewStateRaw;
        $reviewSource = 'this Settings value';
    } elseif (ctype_digit($reviewEnvRaw) && (int) $reviewEnvRaw > 0) {
        $reviewEffective = (int) $reviewEnvRaw;
        $reviewSource = 'OUTREACH_MAX_REVIEW_COUNT in .env';
    } else {
        $reviewEffective = 15;
        $reviewSource = 'the built-in default';
    }
    ?>

    <div class="panel">
        <div class="panel-header">
            <h2>Discovery filters</h2>
        </div>
        <div class="panel-content">
            <p class="hint" style="margin-top:0;">
                The Google Places channel skips businesses with more Google reviews than this ceiling. A low number biases discovery toward newer, smaller businesses (chains and long-established shops pile up reviews and usually already run accounting software). Leave blank to use <?php echo htmlspecialchars($reviewSource === 'OUTREACH_MAX_REVIEW_COUNT in .env' ? 'the .env value' : 'the default'); ?>.
            </p>
            <form method="POST" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="tab" value="settings">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="action" value="set_max_review_count">
                <div class="form-group" style="margin:0;">
                    <label for="maxReviewCount">Max Google reviews (1-2000, blank = default)</label>
                    <input type="number" id="maxReviewCount" name="max_review_count" min="1" max="2000"
                           value="<?php echo $reviewIsOverride ? (int) $reviewStateRaw : ''; ?>"
                           placeholder="<?php echo (int) $reviewEffective; ?>">
                </div>
                <button type="submit" class="btn btn-blue">Save</button>
            </form>
            <p class="hint" style="margin-bottom:0;">
                Currently using <strong><?php echo (int) $reviewEffective; ?></strong> (from <?php echo htmlspecialchars($reviewSource); ?>).
            </p>
        </div>
    </div>

    <?php
    // ─── Follow-up sequence panel ───

    $fuConfigJson = settings_tab_get_state($pdo, 'followup_sequence_config', '[]');
    $fuConfig = json_decode((string) $fuConfigJson, true);
    if (!is_array($fuConfig)) $fuConfig = [];
    ?>

    <div class="panel">
        <div class="panel-header">
            <h2>Follow-up sequence</h2>
        </div>
        <div class="panel-content">
            <p class="hint" style="margin-top:0;">
                The pipeline drafts each follow-up ~1 day before its scheduled send. Drafts queue in the
                <a href="?tab=followups">Follow-ups tab</a> when Review-before-send is on; otherwise they auto-send.
                Touch 1 is the original first-touch email; this list configures touches 2 onward.
            </p>
            <form method="POST">
                <input type="hidden" name="tab" value="settings">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="action" value="set_followup_sequence">

                <table class="data-table" style="margin-bottom:12px;">
                    <thead>
                        <tr>
                            <th>Touch #</th>
                            <th>Days after previous touch (1-90)</th>
                            <th>Default intent</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="followupTouchesBody">
                        <?php if (empty($fuConfig)): ?>
                            <tr><td colspan="4" class="empty-state">No follow-up touches configured. Click "Add touch" to add one.</td></tr>
                        <?php else: ?>
                            <?php foreach ($fuConfig as $i => $touch): ?>
                                <?php $touchNum = (int) ($touch['touch'] ?? ($i + 2)); ?>
                                <tr>
                                    <td>Touch <?php echo $touchNum; ?></td>
                                    <td><input type="number" name="touches[<?php echo $i; ?>][days_after_prev]" min="1" max="90" value="<?php echo (int) ($touch['days_after_prev'] ?? 5); ?>" required></td>
                                    <td><input type="text" name="touches[<?php echo $i; ?>][default_intent]" maxlength="200" value="<?php echo htmlspecialchars((string) ($touch['default_intent'] ?? '')); ?>" style="width:100%;" required></td>
                                    <td><!-- removed via JS only on the last row --></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="display:flex; gap:8px; align-items:center;">
                    <button type="button" class="btn btn-small btn-blue" onclick="addFollowupTouch()">+ Add touch</button>
                    <button type="button" class="btn btn-small btn-neutral" onclick="removeLastFollowupTouch()">Remove last touch</button>
                    <button type="submit" class="btn btn-blue" style="margin-left:auto;">Save sequence</button>
                </div>
            </form>

            <script>
                (function() {
                    var nextIndex = <?php echo count($fuConfig); ?>;
                    var defaultGaps = [3, 7, 14, 21, 30, 60]; // suggested defaults when adding rows

                    window.addFollowupTouch = function() {
                        if (nextIndex >= 6) { alert('Maximum 6 follow-up touches.'); return; }
                        var tbody = document.getElementById('followupTouchesBody');
                        // Clear empty-state row if present
                        if (tbody.querySelector('.empty-state')) tbody.innerHTML = '';
                        var i = nextIndex;
                        var touchNum = i + 2;
                        var gap = defaultGaps[i] || 14;
                        var row = document.createElement('tr');
                        row.innerHTML = '<td>Touch ' + touchNum + '</td>' +
                            '<td><input type="number" name="touches[' + i + '][days_after_prev]" min="1" max="90" value="' + gap + '" required></td>' +
                            '<td><input type="text" name="touches[' + i + '][default_intent]" maxlength="200" value="" style="width:100%;" required placeholder="e.g. gentle bump"></td>' +
                            '<td></td>';
                        tbody.appendChild(row);
                        nextIndex++;
                    };

                    window.removeLastFollowupTouch = function() {
                        var tbody = document.getElementById('followupTouchesBody');
                        var rows = tbody.querySelectorAll('tr');
                        if (rows.length === 0) return;
                        // Don't allow removing if it's the empty-state placeholder
                        if (rows[0].querySelector('.empty-state')) return;
                        rows[rows.length - 1].remove();
                        nextIndex = Math.max(0, nextIndex - 1);
                        if (tbody.children.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="4" class="empty-state">No follow-up touches configured. Click "Add touch" to add one.</td></tr>';
                        }
                    };
                })();
            </script>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>Today's pipeline log</h2>
        </div>
        <div class="panel-content">
            <?php if (!empty($logLines)): ?>
                <pre class="log-tail"><?php echo htmlspecialchars(implode("\n", $logLines)); ?></pre>
            <?php else: ?>
                <p class="empty-state">No pipeline entries in today's log yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
