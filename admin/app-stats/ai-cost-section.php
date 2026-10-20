<?php
// Read from ai_call_timings rather than telemetry, because the server writes a row for every AI call from every app version.

require_once __DIR__ . '/../../api/ai/_models.php';

$aiCostOperationLabels = [
    'receipt_scan' => 'Receipt scans',
    'receipt_verify' => 'Receipt checks',
    'spreadsheet_analysis' => 'Spreadsheets: reading the columns',
    'spreadsheet_process' => 'Spreadsheets: row by row',
    'bank_pdf_extract' => 'Bank statement PDFs',
    'bank_categorize' => 'Bank lines: categories',
    'supplier_category' => 'Supplier categories',
    'completion' => 'Other (no operation sent)',
];

/** Estimated USD for a token count on a model, or null when the model has no price on file. */
function ai_cost_estimate(string $model, int $promptTokens, int $outputTokens): ?float
{
    if (!isset(AI_MODEL_PRICES[$model])) {
        return null;
    }
    [$inputPrice, $outputPrice] = AI_MODEL_PRICES[$model];
    return ($promptTokens * $inputPrice + $outputTokens * $outputPrice) / 1_000_000;
}

function ai_cost_format(?float $usd): string
{
    if ($usd === null) {
        return 'No price';
    }
    return $usd < 1 && $usd > 0 ? '$' . number_format($usd, 4) : '$' . number_format($usd, 2);
}

$aiCostWhere = 'environment = ?';
$aiCostParams = [current_environment()];
if ($tierFilter === 'free' || $tierFilter === 'premium') {
    $aiCostWhere .= ' AND tier = ?';
    $aiCostParams[] = $tierFilter;
}

$aiCostByOperation = [];
$aiCostTopCallers = [];
$aiCostMonthTotal = 0.0;
$aiCostError = null;

try {
    $rangeWhere = $aiCostWhere . ' AND created_at <= ?';
    $rangeParams = [...$aiCostParams, $rangeEnd->format('Y-m-d H:i:s')];
    if ($selectedRange !== 'All Time') {
        $rangeWhere .= ' AND created_at >= ?';
        $rangeParams[] = $rangeStart->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare(
        "SELECT operation, model, COUNT(*) AS calls,
                SUM(COALESCE(prompt_tokens, 0)) AS prompt_tokens,
                SUM(COALESCE(output_tokens, 0)) AS output_tokens,
                SUM(COALESCE(thought_tokens, 0)) AS thought_tokens
         FROM ai_call_timings
         WHERE $rangeWhere
         GROUP BY operation, model"
    );
    $stmt->execute($rangeParams);
    foreach ($stmt->fetchAll() as $row) {
        $op = $row['operation'];
        $billedOutput = (int) $row['output_tokens'] + (int) $row['thought_tokens'];
        $cost = ai_cost_estimate($row['model'], (int) $row['prompt_tokens'], $billedOutput);
        $entry = $aiCostByOperation[$op] ?? [
            'calls' => 0, 'prompt' => 0, 'output' => 0, 'thought' => 0, 'cost' => 0.0, 'unpriced' => false, 'models' => [],
        ];
        $entry['calls'] += (int) $row['calls'];
        $entry['prompt'] += (int) $row['prompt_tokens'];
        $entry['output'] += (int) $row['output_tokens'];
        $entry['thought'] += (int) $row['thought_tokens'];
        $entry['cost'] += $cost ?? 0.0;
        $entry['unpriced'] = $entry['unpriced'] || $cost === null;
        $entry['models'][$row['model']] = true;
        $aiCostByOperation[$op] = $entry;
    }
    uasort($aiCostByOperation, fn($a, $b) => $b['cost'] <=> $a['cost']);

    // The month so far, whatever range is picked, because a per-user budget would be monthly.
    $stmt = $pdo->prepare(
        "SELECT caller, tier, model, COUNT(*) AS calls,
                SUM(COALESCE(prompt_tokens, 0)) AS prompt_tokens,
                SUM(COALESCE(output_tokens, 0)) + SUM(COALESCE(thought_tokens, 0)) AS billed_output
         FROM ai_call_timings
         WHERE $aiCostWhere AND created_at >= ?
         GROUP BY caller, tier, model"
    );
    $stmt->execute([...$aiCostParams, date('Y-m-01 00:00:00')]);
    foreach ($stmt->fetchAll() as $row) {
        $cost = ai_cost_estimate($row['model'], (int) $row['prompt_tokens'], (int) $row['billed_output']) ?? 0.0;
        $aiCostMonthTotal += $cost;
        if ($row['caller'] === null) {
            continue;
        }
        $key = $row['caller'];
        $aiCostTopCallers[$key] ??= ['tier' => $row['tier'], 'calls' => 0, 'cost' => 0.0];
        $aiCostTopCallers[$key]['calls'] += (int) $row['calls'];
        $aiCostTopCallers[$key]['cost'] += $cost;
    }
    uasort($aiCostTopCallers, fn($a, $b) => $b['cost'] <=> $a['cost']);
    $aiCostTopCallers = array_slice($aiCostTopCallers, 0, 10, true);
} catch (PDOException $e) {
    error_log('[ai-cost] ' . $e->getMessage());
    $aiCostError = 'The AI cost figures could not be loaded. If the thought_tokens, caller and tier columns have not been added to ai_call_timings yet, add them with ALTER TABLE in HeidiSQL.';
}

$aiCostCalls = array_sum(array_column($aiCostByOperation, 'calls'));
$aiCostTotal = array_sum(array_column($aiCostByOperation, 'cost'));
?>
<h2 class="section-title">AI Cost</h2>

<?php if ($aiCostError !== null): ?>
    <div class="table-container"><p><?= htmlspecialchars($aiCostError) ?></p></div>
<?php else: ?>
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Estimated cost</h3>
            <div class="value"><?= ai_cost_format($aiCostTotal) ?></div>
            <p class="subtext">In the selected range</p>
        </div>
        <div class="stat-card">
            <h3>AI calls</h3>
            <div class="value"><?= number_format($aiCostCalls) ?></div>
            <p class="subtext">In the selected range</p>
        </div>
        <div class="stat-card">
            <h3>Cost per call</h3>
            <div class="value"><?= ai_cost_format($aiCostCalls > 0 ? $aiCostTotal / $aiCostCalls : 0.0) ?></div>
            <p class="subtext">Average</p>
        </div>
        <div class="stat-card">
            <h3>This month so far</h3>
            <div class="value"><?= ai_cost_format($aiCostMonthTotal) ?></div>
            <p class="subtext">Since <?= date('M 1') ?></p>
        </div>
    </div>
    <p class="subtext">Estimated from Google's list prices in api/ai/_models.php. Calls recorded before thinking tokens were stored leave those tokens out, so older ranges read low.</p>

    <div class="table-container">
        <h3>Cost by kind of call</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 30%;">Kind of call</th>
                    <th>Calls</th>
                    <th>Input tokens</th>
                    <th>Output tokens</th>
                    <th>Thinking tokens</th>
                    <th>Estimated cost</th>
                    <th>Per call</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($aiCostByOperation)): ?>
                    <tr><td colspan="7">No AI calls in this range.</td></tr>
                <?php endif; ?>
                <?php foreach ($aiCostByOperation as $op => $entry): ?>
                    <tr>
                        <td title="<?= htmlspecialchars($op . ' on ' . implode(', ', array_keys($entry['models']))) ?>">
                            <?= htmlspecialchars($aiCostOperationLabels[$op] ?? $op) ?>
                        </td>
                        <td><?= number_format($entry['calls']) ?></td>
                        <td><?= number_format($entry['prompt']) ?></td>
                        <td><?= number_format($entry['output']) ?></td>
                        <td><?= number_format($entry['thought']) ?></td>
                        <td><?= ai_cost_format($entry['cost']) ?><?= $entry['unpriced'] ? ' (some unpriced)' : '' ?></td>
                        <td><?= ai_cost_format($entry['calls'] > 0 ? $entry['cost'] / $entry['calls'] : 0.0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="table-container">
        <h3>Highest-cost users this month</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 40%;">User</th>
                    <th>Plan</th>
                    <th>Calls</th>
                    <th>Estimated cost</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($aiCostTopCallers)): ?>
                    <tr><td colspan="4">No AI calls with a recorded user this month.</td></tr>
                <?php endif; ?>
                <?php foreach ($aiCostTopCallers as $caller => $entry): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($caller) ?></code></td>
                        <td><?= htmlspecialchars(ucfirst((string) $entry['tier'])) ?></td>
                        <td><?= number_format($entry['calls']) ?></td>
                        <td><?= ai_cost_format($entry['cost']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="subtext">Users are shown by the start of their license or device hash, the same id the AI rate limit uses.</p>
    </div>
<?php endif; ?>
