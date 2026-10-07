<?php
// profit-analyzer/lib/import/pipeline.php
//
// Orchestrates the full server-side analysis and bridges the importer's output to
// the website's NormalizedData contract (lib/contract.php):
//
//   read file -> (xlsx: layout-normalize per sheet) -> build sheet structs
//             -> analyze (sheet type + column mapping, Tier1/Tier2)
//             -> bridge importer entities -> contract entities
//             -> categorize expenses (for the money-flow Sankey)
//             -> assemble meta (filename, currency, period)
//
// pa_analyze($path, $ext, $filename) is the real replacement for upload.php's stub.

require_once __DIR__ . '/csv_reader.php';
require_once __DIR__ . '/xlsx_reader.php';
require_once __DIR__ . '/grid.php';
require_once __DIR__ . '/analyzer.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/categorize.php';
require_once __DIR__ . '/currency.php';
require_once __DIR__ . '/../contract.php';

const PA_MAX_ROWS_PER_SHEET = 5000;

/**
 * Turn an uploaded spreadsheet into NormalizedData. This is the live analysis
 * (PHP port of the desktop importer) that replaces the fixture stub.
 *
 * @throws RuntimeException when the file can't be read or nothing is recognized.
 */
function pa_analyze(string $path, string $ext, string $filename = ''): array
{
    $country = null; // unknown for anonymous web visitors

    $sheets = pa_read_file_to_sheets($path, $ext, $filename);
    if (count($sheets) === 0) {
        throw new RuntimeException('No readable sheets in the file.');
    }

    $results = pa_analyze_file_sheets($sheets, $country);
    if (count($results) === 0) {
        throw new RuntimeException('No recognizable business data in the file.');
    }

    $normalized = pa_bridge_to_contract($results, $filename ?: basename($path));
    return pa_normalize($normalized);
}

/** Read a CSV or XLSX into a list of sheet structs (grid.php shape). */
function pa_read_file_to_sheets(string $path, string $ext, string $filename): array
{
    $sheets = [];

    if ($ext === 'csv') {
        [$headers, $rows] = pa_read_csv($path);
        if (count($rows) > PA_MAX_ROWS_PER_SHEET) {
            $rows = array_slice($rows, 0, PA_MAX_ROWS_PER_SHEET);
        }
        $name = pathinfo($filename ?: $path, PATHINFO_FILENAME) ?: 'Sheet1';
        $sheet = pa_sheet_from_csv($name, $headers, $rows);
        if ($sheet !== null) {
            $sheets[] = $sheet;
        }
        return $sheets;
    }

    // xlsx
    $raw = pa_read_xlsx($path);
    if ($raw === null) {
        return [];
    }
    foreach ($raw as $rawSheet) {
        $origMatrix = $rawSheet['matrix'];
        $matrix = $origMatrix;
        $merges = $rawSheet['merges'];
        // Rewrite messy layouts to a clean header+rows matrix (no-op for clean sheets).
        [$matrix, $merges] = pa_layout_normalize_matrix($matrix, $merges);

        // The per-cell currency map is aligned to the ORIGINAL matrix. Only carry it
        // when layout normalization was a true no-op; a rewritten matrix would desync.
        $fmtCurrency = ($matrix === $origMatrix) ? ($rawSheet['fmtCurrency'] ?? []) : [];

        if (count($matrix) > PA_MAX_ROWS_PER_SHEET + 1) {
            $matrix = array_slice($matrix, 0, PA_MAX_ROWS_PER_SHEET + 1);
            if ($fmtCurrency) {
                $fmtCurrency = array_slice($fmtCurrency, 0, PA_MAX_ROWS_PER_SHEET + 1);
            }
        }
        $sheet = pa_sheet_from_matrix($rawSheet['name'], $matrix, $merges, $fmtCurrency);
        if ($sheet !== null) {
            $sheets[] = $sheet;
        }
    }
    return $sheets;
}

// ─── Bridge: importer entities -> NormalizedData contract ─────────────────────

/** Map an importer entity-type (PascalCase) to a contract entity key. */
function pa_contract_key(string $entityType): ?string
{
    static $map = [
        'Revenue' => 'revenue',
        'Expenses' => 'expenses',
        'Products' => 'products',
        'Customers' => 'customers',
        'Suppliers' => 'suppliers',
        'Categories' => 'categories',
        'Invoices' => 'invoices',
        'Payments' => 'payments',
        'Inventory' => 'inventory',
        'Returns' => 'returns',
        'LostDamaged' => 'losses',
        'RentalRecords' => 'rentals',
        'RentalInventory' => 'rentalInventory',
        'Locations' => 'locations',
        'Departments' => 'departments',
        'Employees' => 'employees',
        'RecurringInvoices' => 'recurringInvoices',
        'StockAdjustments' => 'stockAdjustments',
        'PurchaseOrders' => 'purchaseOrders',
    ];
    return $map[$entityType] ?? null;
}

/** The pre-tax money amount for a transaction-like entity. */
function pa_entity_amount(array $e): float
{
    if (isset($e['unitPrice']) && (float)$e['unitPrice'] != 0.0) {
        return (float)$e['unitPrice'];
    }
    if (isset($e['total'])) {
        return (float)$e['total'] - (float)($e['taxAmount'] ?? 0);
    }
    if (isset($e['amount'])) {
        return (float)$e['amount'];
    }
    return 0.0;
}

/** Assemble the NormalizedData contract from the analyzer's per-sheet results. */
function pa_bridge_to_contract(array $results, string $filename): array
{
    // Collect importer entities grouped by contract key.
    $byKey = [];
    foreach ($results as $res) {
        $key = pa_contract_key($res['entityType']);
        if ($key === null) {
            continue;
        }
        foreach ($res['entities'] as $e) {
            $byKey[$key][] = $e;
        }
    }

    $entities = [];

    // Revenue
    if (!empty($byKey['revenue'])) {
        $entities['revenue'] = array_map(function ($e) {
            $amount = pa_entity_amount($e);
            return [
                'id' => $e['id'] ?? '',
                'date' => $e['date'] ?? '',
                'customerId' => $e['customerId'] ?? '',
                'productId' => $e['productId'] ?? null,
                'description' => $e['description'] ?? '',
                'quantity' => isset($e['quantity']) ? (float)$e['quantity'] : 1,
                'unitPrice' => isset($e['unitPrice']) ? (float)$e['unitPrice'] : $amount,
                'amount' => $amount,
                'taxAmount' => (float)($e['taxAmount'] ?? 0),
                'total' => isset($e['total']) ? (float)$e['total'] : $amount + (float)($e['taxAmount'] ?? 0),
                'paymentStatus' => $e['paymentStatus'] ?? 'Paid',
                'originalCurrency' => $e['originalCurrency'] ?? '',
            ];
        }, $byKey['revenue']);
    }

    // Expenses (category filled later by the categorizer)
    if (!empty($byKey['expenses'])) {
        $entities['expenses'] = array_map(function ($e) {
            $amount = pa_entity_amount($e);
            return [
                'id' => $e['id'] ?? '',
                'date' => $e['date'] ?? '',
                'supplierId' => $e['supplierId'] ?? '',
                'category' => $e['category'] ?? '',
                'description' => $e['description'] ?? '',
                'amount' => $amount,
                'taxAmount' => (float)($e['taxAmount'] ?? 0),
                'total' => isset($e['total']) ? (float)$e['total'] : $amount + (float)($e['taxAmount'] ?? 0),
                'paymentMethod' => $e['paymentMethod'] ?? '',
                'originalCurrency' => $e['originalCurrency'] ?? '',
            ];
        }, $byKey['expenses']);
        $entities['expenses'] = pa_categorize_expenses($entities['expenses']);
    }

    // Products
    if (!empty($byKey['products'])) {
        $entities['products'] = array_map(fn($e) => [
            'id' => $e['id'] ?? '',
            'name' => $e['name'] ?? '',
            'sku' => $e['sku'] ?? '',
            'type' => $e['type'] ?? '',
            'categoryName' => $e['categoryName'] ?? '',
            'supplierId' => $e['supplierId'] ?? '',
            'reorderPoint' => $e['reorderPoint'] ?? null,
        ], $byKey['products']);
    }

    // Customers (flatten nested address)
    if (!empty($byKey['customers'])) {
        $entities['customers'] = array_map(fn($e) => [
            'id' => $e['id'] ?? '',
            'name' => $e['name'] ?? '',
            'company' => $e['companyName'] ?? '',
            'email' => $e['email'] ?? '',
            'city' => $e['address']['city'] ?? '',
            'country' => $e['address']['country'] ?? '',
            'status' => $e['status'] ?? 'Active',
        ], $byKey['customers']);
    }

    // Suppliers
    if (!empty($byKey['suppliers'])) {
        $entities['suppliers'] = array_map(fn($e) => [
            'id' => $e['id'] ?? '',
            'name' => $e['name'] ?? '',
            'country' => $e['address']['country'] ?? '',
        ], $byKey['suppliers']);
    }

    // Invoices
    if (!empty($byKey['invoices'])) {
        $entities['invoices'] = array_map(fn($e) => [
            'invoiceNumber' => $e['id'] ?? '',
            'customerId' => $e['customerId'] ?? '',
            'issueDate' => $e['issueDate'] ?? '',
            'dueDate' => $e['dueDate'] ?? '',
            'subtotal' => (float)($e['subtotal'] ?? 0),
            'taxAmount' => (float)($e['taxAmount'] ?? 0),
            'total' => (float)($e['total'] ?? 0),
            'amountPaid' => (float)($e['amountPaid'] ?? 0),
            'balance' => (float)($e['balance'] ?? 0),
            'status' => $e['status'] ?? '',
            'originalCurrency' => $e['originalCurrency'] ?? '',
        ], $byKey['invoices']);
    }

    // Invoice-based businesses (freelancers, agencies) upload invoices, not a "sales" sheet.
    if (empty($entities['revenue']) && !empty($entities['invoices'])) {
        $entities['revenue'] = array_map(function ($inv) {
            $amount = (float)($inv['subtotal'] ?? 0);
            if ($amount == 0.0) {
                $amount = (float)($inv['total'] ?? 0) - (float)($inv['taxAmount'] ?? 0);
            }
            $status = $inv['status'] ?? '';
            return [
                'id' => $inv['invoiceNumber'] ?? '',
                'date' => $inv['issueDate'] ?? '',
                'customerId' => $inv['customerId'] ?? '',
                'productId' => null,
                'description' => $inv['invoiceNumber'] ? ('Invoice ' . $inv['invoiceNumber']) : 'Invoice',
                'quantity' => 1,
                'unitPrice' => $amount,
                'amount' => $amount,
                'taxAmount' => (float)($inv['taxAmount'] ?? 0),
                'total' => (float)($inv['total'] ?? ($amount + (float)($inv['taxAmount'] ?? 0))),
                'paymentStatus' => $status === 'Paid' ? 'Paid' : ($status ?: 'Unpaid'),
                'originalCurrency' => $inv['originalCurrency'] ?? '',
            ];
        }, $entities['invoices']);
    }

    // Pass remaining recognized types through untouched (analytics ignores them
    // today, but the cleaned-export and future tabs can use them).
    foreach ($byKey as $key => $list) {
        if (!isset($entities[$key])) {
            $entities[$key] = $list;
        }
    }

    // Detect the dominant currency and convert every monetary field into it (historical per-row rates). Runs after invoice->revenue synthesis so the synthesized rows are converted too.
    $currency = pa_apply_currency($entities);

    return [
        'meta' => pa_build_meta($entities, $filename, $currency),
        'entities' => $entities,
    ];
}

/** Build meta: filename, detected currency, and the date range covered. */
function pa_build_meta(array $entities, string $filename, string $currency = 'USD'): array
{
    $dates = [];
    foreach (['revenue', 'expenses'] as $k) {
        foreach ($entities[$k] ?? [] as $r) {
            $d = $r['date'] ?? '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$d)) {
                $dates[] = substr($d, 0, 10);
            }
        }
    }
    $period = null;
    if (count($dates) > 0) {
        sort($dates);
        $period = ['start' => $dates[0], 'end' => $dates[count($dates) - 1]];
    }

    return [
        'filename' => $filename !== '' ? $filename : 'your-spreadsheet.xlsx',
        'currency' => $currency !== '' ? $currency : 'USD',
        'period' => $period,
        'flagged' => 0,
    ];
}
