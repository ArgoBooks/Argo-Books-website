<?php
/**
 * Free web receipt scanner: XLSX export.
 *
 * Stateless formatter. Takes the receipt JSON the client already holds (one or
 * many) and returns a two-sheet .xlsx (Summary + Line items). No AI call, so no
 * Turnstile or daily-scan limit; just a lenient per-IP cap against spam.
 * Reuses the dependency-free writer in profit-analyzer/lib/xlsx.php.
 */

require_once __DIR__ . '/../../rate_limit_helper.php';
require_once __DIR__ . '/../../profit-analyzer/lib/xlsx.php';

function rx_fail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    rx_fail(405, 'Use POST to export.');
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 2 * 1024 * 1024) {
    rx_fail(413, 'Payload too large.');
}

$ip = get_client_ip();
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
// check_and_record rather than a bare check: nothing else writes to this
// bucket, so a check on its own would read a counter that is permanently 0
// and the limit could never trip.
if (!$isLocal && rate_limit_hit('web_receipt_export', $ip)) {
    header('Retry-After: ' . rate_limit_window('web_receipt_export'));
    rx_fail(429, 'Too many exports. Please try again in ' . rate_limit_wait_phrase('web_receipt_export') . '.');
}

$data = json_decode($raw, true);
$receipts = is_array($data) ? ($data['receipts'] ?? null) : null;
if (!is_array($receipts) || !count($receipts)) {
    rx_fail(400, 'No receipts provided.');
}
$receipts = array_slice($receipts, 0, 50);

$num = static function ($v) { return is_numeric($v) ? (float)$v : 0.0; };
// Sheet names: <=31 chars, no Excel-forbidden characters, unique per receipt.
$sheetName = static function ($supplier, $i) {
    $s = trim(preg_replace('/[\\\\\/?*\[\]:]/', ' ', (string)$supplier));
    if ($s === '') {
        $s = 'Receipt';
    }
    return mb_substr(($i + 1) . '. ' . $s, 0, 31);
};

$multi = count($receipts) > 1;
$sheets = [];
$lineCount = 0;

foreach ($receipts as $i => $r) {
    if (!is_array($r)) {
        continue;
    }

    $discount = $r['discountTotal'] ?? null;
    if ($discount === null) {
        $discount = 0.0;
        foreach (($r['discounts'] ?? []) as $d) {
            $discount += $num(is_array($d) ? ($d['amount'] ?? 0) : 0);
        }
    }

    // Metadata block, then the line table, then totals: one sheet, like the CSV.
    $rows = [
        ['Supplier', (string)($r['supplierName'] ?? ''), '', '', ''],
        ['Date', (string)($r['transactionDate'] ?? ''), '', '', ''],
        ['Currency', (string)($r['currencyCode'] ?? ''), '', '', ''],
        ['Payment', (string)($r['paymentMethod'] ?? ''), '', '', ''],
        [],
    ];

    foreach (($r['lineItems'] ?? []) as $li) {
        if (!is_array($li)) {
            continue;
        }
        if (++$lineCount > 5000) {
            break;
        }
        $rows[] = [
            'Item',
            (string)($li['description'] ?? ''),
            round($num($li['quantity'] ?? 0), 3),
            round($num($li['unitPrice'] ?? 0), 2),
            round($num($li['totalPrice'] ?? 0), 2),
        ];
    }
    // Each tax line, separately (matches the on-page promise).
    foreach (($r['taxes'] ?? []) as $t) {
        if (!is_array($t)) {
            continue;
        }
        if (++$lineCount > 5000) {
            break;
        }
        $rows[] = ['Tax', (string)($t['name'] ?? 'Tax'), '', '', round($num($t['amount'] ?? 0), 2)];
    }
    // Each discount line (negative, since it reduces the total).
    foreach (($r['discounts'] ?? []) as $d) {
        if (!is_array($d)) {
            continue;
        }
        if (++$lineCount > 5000) {
            break;
        }
        $rows[] = ['Discount', (string)($d['name'] ?? 'Discount'), '', '', -round($num($d['amount'] ?? 0), 2)];
    }

    $rows[] = [];
    $rows[] = ['Subtotal', '', '', '', round($num($r['subtotal'] ?? 0), 2)];
    $rows[] = ['Tax total', '', '', '', round($num($r['taxTotal'] ?? 0), 2)];
    $rows[] = ['Discount total', '', '', '', round($num($discount), 2)];
    $rows[] = ['Total', '', '', '', round($num($r['totalAmount'] ?? 0), 2)];

    $sheets[] = [
        'name' => $sheetName($r['supplierName'] ?? '', $i),
        'headers' => ['Type', 'Description', 'Quantity', 'Unit Price', 'Total'],
        'rows' => $rows,
    ];
}

if (!$sheets) {
    rx_fail(400, 'No receipts to export.');
}

$bytes = pa_write_xlsx($sheets);
$filename = (count($receipts) > 1 ? 'receipts' : 'receipt') . '-' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
