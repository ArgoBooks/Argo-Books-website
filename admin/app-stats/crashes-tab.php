<?php
// Crash Reports tab body, included by admin/app-stats/index.php inside the
// #crashes tab. Admin auth + page chrome are handled by the host page; this
// just reads desktop crash reports from admin/data-logs/crashes/ and renders
// them grouped by exception signature. Reuses .stats-grid / .stat-card /
// .section-title from the host page's styles.

// Direct-access guard. This partial is only valid when included by its parent
// page (app-stats/index.php), which starts the session and verifies the admin
// login. Requested directly, no session is started so $_SESSION is empty and we
// fail closed. (An admin/.htaccess also denies *-tab.php as defense in depth.)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../../founder_identity.php';      // is_founder_auth_id()
require_once __DIR__ . '/../../country_names.php';         // country_name()
require_once __DIR__ . '/../../telemetry_environment.php'; // is_other_environment_auth_id()

// Date-range bounds come from the host page. Defaulted here so this partial
// still renders if it's ever included somewhere that doesn't set them.
$rangeStartTs = $rangeStartTs ?? null;
$rangeEndTs = $rangeEndTs ?? PHP_INT_MAX;

$crashDir = __DIR__ . '/../data-logs/crashes';
$crashFiles = is_dir($crashDir) ? (glob($crashDir . '/argo_crash_*.json') ?: []) : [];

$crashGroups = [];
$crashTotal = 0;
$crashVersions = [];
$crashDevices = [];

foreach ($crashFiles as $crashFile) {
    $raw = @file_get_contents($crashFile);
    if ($raw === false) {
        continue;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['crashes']) || !is_array($data['crashes'])) {
        continue;
    }

    // Hide the founder's own installs (single source of truth: FOUNDER_AUTH_IDS).
    // Their crash reports are still stored; only User Activity surfaces them.
    if (is_founder_auth_id($data['authId'] ?? null)) {
        continue;
    }

    // Same for a crash from an install whose premium subscription belongs to another
    // environment: a sandbox test crash is not a production crash.
    if (is_other_environment_auth_id($data['authId'] ?? null)) {
        continue;
    }

    $meta = [
        'appVersion' => (string) ($data['appVersion'] ?? 'unknown'),
        'platform'   => (string) ($data['platform'] ?? 'Unknown'),
        // Named countryCode, and it is one, so this column was never spelled out.
        'country'    => country_name($data['countryCode'] ?? '') ?: null,
        'authId'     => $data['authId'] ?? null,
        'receivedAt' => $data['receivedAt'] ?? null,
    ];

    foreach ($data['crashes'] as $c) {
        if (!is_array($c)) {
            continue;
        }

        $ts = $c['timestamp'] ?? $meta['receivedAt'];
        // Compare on parsed epochs, not raw strings: reports use ISO 8601 while
        // the receivedAt fallback is "Y-m-d H:i:s", which don't sort together.
        $tsEpoch = $ts !== null ? (strtotime((string) $ts) ?: 0) : 0;

        // Honour the page's date range, so this tab agrees with the charts. A
        // report we can't date stays visible in every range: silently hiding a
        // crash is worse than showing one that may be older than the window.
        if ($tsEpoch > 0) {
            if ($tsEpoch > $rangeEndTs || ($rangeStartTs !== null && $tsEpoch < $rangeStartTs)) {
                continue;
            }
        }

        $crashTotal++;
        if ($meta['authId']) {
            $crashDevices[$meta['authId']] = true;
        }

        // Prefer the version/platform stamped into each crash at capture time,
        // falling back to the upload's file-level values for older reports. This
        // avoids attributing a crash to whatever version the app updated to
        // before the report was uploaded on the next launch.
        $cVersion = (string) ($c['appVersion'] ?? $meta['appVersion']);
        $cPlatform = (string) ($c['platform'] ?? $meta['platform']);
        $crashVersions[$cVersion] = true;

        $type = (string) ($c['exceptionType'] ?? 'UnknownException');
        $source = (string) ($c['source'] ?? '');
        $sig = $type . '|' . $source;

        if (!isset($crashGroups[$sig])) {
            $crashGroups[$sig] = [
                'type' => $type, 'source' => $source, 'count' => 0,
                'platforms' => [], 'versions' => [], 'devices' => [],
                'first' => null, 'last' => null, 'firstEpoch' => null, 'lastEpoch' => null,
                'sample' => null, 'sampleMeta' => [], 'sampleEpoch' => -1, 'occurrences' => [],
            ];
        }

        $crashGroups[$sig]['count']++;
        $crashGroups[$sig]['platforms'][$cPlatform] = true;
        $crashGroups[$sig]['versions'][$cVersion] = true;
        if ($meta['authId']) {
            $crashGroups[$sig]['devices'][$meta['authId']] = true;
        }
        if ($ts !== null) {
            if ($crashGroups[$sig]['firstEpoch'] === null || $tsEpoch < $crashGroups[$sig]['firstEpoch']) {
                $crashGroups[$sig]['firstEpoch'] = $tsEpoch;
                $crashGroups[$sig]['first'] = $ts;
            }
            if ($crashGroups[$sig]['lastEpoch'] === null || $tsEpoch > $crashGroups[$sig]['lastEpoch']) {
                $crashGroups[$sig]['lastEpoch'] = $tsEpoch;
                $crashGroups[$sig]['last'] = $ts;
            }
        }
        if ($crashGroups[$sig]['sample'] === null || $tsEpoch >= $crashGroups[$sig]['sampleEpoch']) {
            $crashGroups[$sig]['sample'] = $c;
            $crashGroups[$sig]['sampleMeta'] = ['platform' => $cPlatform, 'appVersion' => $cVersion, 'country' => $meta['country']];
            $crashGroups[$sig]['sampleEpoch'] = $tsEpoch;
        }
        if (count($crashGroups[$sig]['occurrences']) < 30) {
            $crashGroups[$sig]['occurrences'][] = [
                'ts' => $ts, 'platform' => $cPlatform,
                'version' => $cVersion, 'country' => $meta['country'],
            ];
        }
    }
}

uasort($crashGroups, fn ($a, $b) => $b['count'] <=> $a['count']);

if (!function_exists('crash_fmt')) {
    function crash_fmt(?string $ts): string
    {
        if (!$ts) {
            return '-';
        }
        $t = strtotime($ts);
        return $t ? date('M j, Y g:i A', $t) : htmlspecialchars($ts);
    }
}
?>
<style>
.crash-group {
    border: 1px solid var(--border-color); border-radius: 12px;
    margin-bottom: 1rem; background: var(--background-color); overflow: hidden;
}
.crash-group > summary {
    list-style: none; cursor: pointer; padding: 1rem 1.25rem;
    display: flex; align-items: center; gap: 1rem;
}
.crash-group > summary::-webkit-details-marker { display: none; }
.crash-group > summary:hover { background: var(--hover-color, rgba(127,127,127,0.06)); }
.crash-count {
    flex: 0 0 auto; min-width: 2.5rem; text-align: center;
    font-weight: 700; font-size: 1.1rem; color: #fff;
    background: var(--danger-color, #e5484d); border-radius: 8px; padding: 0.35rem 0.6rem;
}
.crash-sig { flex: 1 1 auto; min-width: 0; }
.crash-sig .type { font-weight: 600; color: var(--text-color); word-break: break-all; }
.crash-sig .loc { font-family: monospace; font-size: 0.85rem; color: var(--text-muted); margin-top: 0.15rem; }
.crash-tags { flex: 0 0 auto; text-align: right; font-size: 0.78rem; color: var(--text-muted); }
.crash-detail { padding: 0 1.25rem 1.25rem; border-top: 1px solid var(--border-color); }
.crash-detail h4 { margin: 1.25rem 0 0.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.crash-detail pre {
    background: var(--code-bg, rgba(127,127,127,0.08)); border: 1px solid var(--border-color);
    border-radius: 8px; padding: 0.85rem 1rem; overflow-x: auto;
    font-size: 0.82rem; line-height: 1.5; white-space: pre-wrap; word-break: break-word;
}
.crash-detail .msg { color: var(--text-color); }
.breadcrumbs { list-style: none; margin: 0; padding: 0; font-family: monospace; font-size: 0.8rem; }
.breadcrumbs li { padding: 0.2rem 0; border-bottom: 1px dashed var(--border-color); color: var(--text-muted); }
.occ-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.occ-table th, .occ-table td { text-align: left; padding: 0.35rem 0.6rem; border-bottom: 1px solid var(--border-color); }
.occ-table th { color: var(--text-muted); font-weight: 600; }
.crash-empty { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
.crash-empty .big { font-size: 1.2rem; color: var(--text-color); margin-bottom: 0.5rem; }
.crash-stack-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 1.25rem 0 0.5rem; }
.crash-stack-head h4 { margin: 0; }
.crash-copy {
    flex: 0 0 auto; border: 1px solid var(--border-color); background: var(--code-bg, rgba(127,127,127,0.08));
    color: var(--text-color); border-radius: 6px; padding: 0.3rem 0.75rem;
    font-size: 0.75rem; font-weight: 600; cursor: pointer; white-space: nowrap;
}
.crash-copy:hover { background: var(--hover-color, rgba(127,127,127,0.15)); }
.crash-copy.copied { color: #16a34a; border-color: #16a34a; }
</style>

<h2 class="section-title">Crash Reports</h2>
<p style="color: var(--text-muted); margin-top: -0.5rem; margin-bottom: 1.5rem;">
    Unhandled exceptions reported by the desktop app, grouped by where they happened.
</p>

<div class="stats-grid">
    <div class="stat-card"><h3>Total Crashes</h3><div class="value"><?= number_format($crashTotal) ?></div></div>
    <div class="stat-card"><h3>Distinct Crashes</h3><div class="value"><?= number_format(count($crashGroups)) ?></div></div>
    <div class="stat-card"><h3>Affected Devices</h3><div class="value"><?= number_format(count($crashDevices)) ?></div></div>
    <div class="stat-card"><h3>App Versions</h3><div class="value"><?= number_format(count($crashVersions)) ?></div></div>
</div>

<?php if (count($crashGroups) === 0): ?>
    <div class="crash-empty">
        <div class="big">No crashes reported</div>
        <?php if (count($crashFiles) > 0 && isset($rangeDisplay)): ?>
            <div>Nothing crashed in <?= htmlspecialchars($rangeDisplay) ?>. Widen the date range to look further back.</div>
        <?php else: ?>
            <div>Either nothing has crashed, or no reports have arrived yet.</div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php foreach ($crashGroups as $g):
        $sample = $g['sample'] ?? [];
        $sampleMeta = $g['sampleMeta'] ?? [];
    ?>
    <details class="crash-group">
        <summary>
            <span class="crash-count"><?= number_format($g['count']) ?></span>
            <span class="crash-sig">
                <div class="type"><?= htmlspecialchars($g['type']) ?></div>
                <?php if ($g['source'] !== ''): ?><div class="loc"><?= htmlspecialchars($g['source']) ?></div><?php endif; ?>
            </span>
            <span class="crash-tags">
                <span><?= htmlspecialchars(implode(', ', array_keys($g['versions']))) ?></span><br>
                <span><?= htmlspecialchars(implode(', ', array_keys($g['platforms']))) ?> &middot; <?= count($g['devices']) ?> device<?= count($g['devices']) === 1 ? '' : 's' ?></span><br>
                <span>last <?= crash_fmt($g['last']) ?></span>
            </span>
        </summary>
        <div class="crash-detail">
            <?php if (!empty($sample['message'])): ?>
                <h4>Message</h4>
                <div class="msg"><?= htmlspecialchars($sample['message']) ?></div>
            <?php endif; ?>

            <?php if (!empty($sample['innerException'])): ?>
                <h4>Inner Exception</h4>
                <div class="msg"><?= htmlspecialchars($sample['innerException']) ?></div>
            <?php endif; ?>

            <h4>Where &amp; When (latest)</h4>
            <div class="msg">
                Handler: <?= htmlspecialchars($sample['handler'] ?? '-') ?> &middot;
                <?= htmlspecialchars($sampleMeta['platform'] ?? '-') ?>
                <?php if (!empty($sample['osVersion'])): ?> (<?= htmlspecialchars($sample['osVersion']) ?>)<?php endif; ?> &middot;
                V.<?= htmlspecialchars($sampleMeta['appVersion'] ?? '-') ?> &middot;
                <?= crash_fmt($g['last']) ?>
            </div>

            <?php if (!empty($sample['stackTrace'])): ?>
                <div class="crash-stack-head">
                    <h4>Stack Trace</h4>
                    <button type="button" class="crash-copy">Copy</button>
                </div>
                <pre class="crash-stack"><?= htmlspecialchars($sample['stackTrace']) ?></pre>
            <?php endif; ?>

            <?php if (!empty($sample['breadcrumbs']) && is_array($sample['breadcrumbs'])): ?>
                <h4>Breadcrumbs (leading up to the crash)</h4>
                <ul class="breadcrumbs">
                    <?php foreach ($sample['breadcrumbs'] as $b): ?>
                        <li><?= htmlspecialchars(is_scalar($b) ? (string) $b : json_encode($b)) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h4>Occurrences (<?= $g['count'] ?> total, first <?= crash_fmt($g['first']) ?>)</h4>
            <table class="occ-table">
                <thead><tr><th>Time</th><th>Platform</th><th>Version</th><th>Country</th></tr></thead>
                <tbody>
                    <?php foreach ($g['occurrences'] as $o): ?>
                        <tr>
                            <td><?= crash_fmt($o['ts']) ?></td>
                            <td><?= htmlspecialchars($o['platform']) ?></td>
                            <td><?= htmlspecialchars($o['version']) ?></td>
                            <td><?= htmlspecialchars($o['country'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
    <?php endforeach; ?>
<?php endif; ?>

<script>
(function () {
    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        // Fallback for non-secure contexts (e.g. local http).
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); resolve(); }
            catch (e) { reject(e); }
            finally { document.body.removeChild(ta); }
        });
    }

    document.querySelectorAll('.crash-copy').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var detail = btn.closest('.crash-detail');
            var pre = detail && detail.querySelector('pre.crash-stack');
            if (!pre) return;
            copyText(pre.textContent).then(function () {
                var orig = btn.getAttribute('data-label') || btn.textContent;
                btn.setAttribute('data-label', orig);
                btn.textContent = 'Copied!';
                btn.classList.add('copied');
                setTimeout(function () {
                    btn.textContent = orig;
                    btn.classList.remove('copied');
                }, 1500);
            }).catch(function () {
                var orig = btn.getAttribute('data-label') || btn.textContent;
                btn.textContent = 'Copy failed';
                setTimeout(function () { btn.textContent = orig; }, 1500);
            });
        });
    });
})();
</script>
