<?php
// profit-analyzer/lib/import/analyzer.php
//
// Port of the analysis + tier-processing core of SpreadsheetAnalysisService.cs:
//  - build the analysis prompt (schema + sample tables + column profiles + relationships)
//  - call Gemini per column-bounded batch, parse sheet-type + column mappings + tier
//  - Tier 1: rename source headers to target names, read deterministically into entities
//  - Tier 2: send 100-row chunks to Gemini, normalize into entity JSON
//
// Output of pa_analyze_file_sheets(): per included sheet,
//   { entityType, entities: [ importer-shape assoc arrays keyed by JsonName ] }
// "importer-shape" matches the desktop app's Tier 2 JSON (nested address.*, etc.).

require_once __DIR__ . '/gemini.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/profiler.php';
require_once __DIR__ . '/currency.php'; // pa_currency_detect for per-row currency (Tier 1)

const PA_MIN_TYPE_CONFIDENCE = 0.5;
const PA_MAX_COLUMNS_PER_BATCH = 40;
const PA_TIER2_CHUNK = 100;
// Concurrency caps, matching the desktop app (MaxConcurrentAnalysisBatches=5,
// MaxConcurrentChunks=10). The analysis batches and Tier 2 chunks each fan out
// through pa_gemini_chat_multi up to these many requests in flight at once.
const PA_MAX_CONCURRENT_ANALYSIS = 5;
const PA_MAX_CONCURRENT_CHUNKS = 10;

// ─── Prompt building (port of BuildAnalysis*Prompt / BuildTier2*Prompt) ───────

function pa_build_analysis_system_prompt(): string
{
    return "You are an expert data analyst for a bookkeeping application called Argo Books. Your task is to analyze spreadsheet data and determine:\n"
        . "1. What type of business entity each sheet represents\n"
        . "2. How source columns map to the expected Argo Books schema\n"
        . "3. Whether simple column mapping (Tier 1) suffices, or if complex row transformation (Tier 2) is needed\n\n"
        . "Use Tier 2 ONLY when:\n"
        . "- Multiple entity types are mixed in one sheet\n"
        . "- Rows need grouping (e.g., line-item-per-row that must become one invoice)\n"
        . "- The structure is fundamentally different from a simple table (e.g., pivot tables, cross-tabs)\n"
        . "- Data requires splitting/combining columns in non-trivial ways\n\n"
        . "For everything else (renamed columns, different terminology, minor format differences), use Tier 1.\n\n"
        . "Respond with valid JSON only, no markdown code blocks.";
}

/** Port of BuildAnalysisUserPrompt. $sheets: list of sheet structs (grid.php). */
function pa_build_analysis_user_prompt(array $sheets, ?string $country): string
{
    $sb = "## Target Schema\n";
    $sb .= pa_format_schema_for_prompt($country);
    $sb .= "## Source Data\n\n";

    foreach ($sheets as $sheet) {
        $headers = $sheet['headers'];
        $sb .= "### Sheet: \"{$sheet['name']}\" ({$sheet['totalRows']} data rows)\n\n";
        $sb .= '| ' . implode(' | ', $headers) . " |\n";
        $sb .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";

        foreach ($sheet['sampleRows'] as $row) {
            $cells = $row;
            while (count($cells) < count($headers)) {
                $cells[] = '';
            }
            $escaped = array_map(fn($c) => str_replace('|', '\\|', (string)$c), $cells);
            $sb .= '| ' . implode(' | ', $escaped) . " |\n";
        }
        $sb .= "\n";

        if (count($sheet['sampleRows']) > 0) {
            $profiles = pa_profile_columns($headers, $sheet['sampleRows']);
            $sb .= "#### Column profiles\n";
            foreach ($profiles as $p) {
                $examples = count($p['examples']) > 0
                    ? ', examples: ' . implode(', ', $p['examples'])
                    : '';
                $sb .= "- {$p['header']} ({$p['type']}, distinct={$p['distinct']}, empty={$p['empty']}{$examples})\n";
            }
            $sb .= "\n";

            $relationships = pa_detect_relationships($headers, $sheet['sampleRows']);
            if (count($relationships) > 0) {
                $sb .= "#### Detected relationships\n";
                foreach ($relationships as $rel) {
                    $sb .= "- {$rel}\n";
                }
                $sb .= "\n";
            }
        }
    }

    $types = implode(', ', pa_entity_type_names());
    $sb .= "## Response Format\n"
        . "{\n"
        . "  \"sheets\": [\n"
        . "    {\n"
        . "      \"sourceSheetName\": \"<exact sheet name>\",\n"
        . "      \"detectedType\": \"<one of: {$types}>\",\n"
        . "      \"confidence\": 0.95,\n"
        . "      \"tier\": \"Tier1_Mapping\",\n"
        . "      \"tierReason\": \"\",\n"
        . "      \"columnMappings\": [\n"
        . "        { \"sourceColumn\": \"<source col>\", \"targetColumn\": \"<target col from schema>\", \"confidence\": 0.98, \"transformHint\": null }\n"
        . "      ],\n"
        . "      \"unmappedSourceColumns\": [\"<columns that don't map to any target>\"],\n"
        . "      \"unmappedTargetColumns\": [\"<target columns with no source match>\"]\n"
        . "    }\n"
        . "  ],\n"
        . "  \"warnings\": [\"<any general warnings>\"]\n"
        . "}\n\n"
        . "IMPORTANT:\n"
        . "- sourceSheetName must EXACTLY match the original sheet name\n"
        . "- targetColumn must EXACTLY match a column name from the target schema above\n"
        . "- detectedType must be one of the listed entity types\n"
        . "- Only include mappings where you are reasonably confident (>0.5)\n"
        . "- Set tier to \"Tier2_LlmProcessing\" only when simple column mapping cannot work";

    return $sb;
}

/** Port of BuildTier2SystemPrompt. */
function pa_build_tier2_system_prompt(string $entityType, array $schema): string
{
    $sb = "You are converting raw spreadsheet data into normalized {$entityType} records for Argo Books.\n\n";
    $sb .= "Target JSON schema (use these exact property names as JSON keys):\n";

    $seen = [];
    $hasDotted = false;
    foreach ($schema as $col) {
        $json = $col['json'] ?? $col['name'];
        if (isset($seen[$json])) {
            continue;
        }
        $seen[$json] = true;
        if (strpos((string)$col['json'], '.') !== false) {
            $hasDotted = true;
        }
        $req = $col['required'] ? ' (REQUIRED)' : '';
        $sb .= "- {$json} ({$col['type']}): {$col['desc']}{$req}\n";
    }

    if ($hasDotted) {
        $sb .= "\nFor dotted property names like 'address.street', nest them as JSON objects:\n";
        $sb .= "  { \"address\": { \"street\": \"value\", \"city\": \"value\" } }\n";
    }

    $sb .= "\nRules:\n"
        . "- Output a JSON array of objects using the exact JSON property names listed above\n"
        . "- Generate reasonable IDs if none exist (e.g., CUS-001, INV-2024-001)\n"
        . "- Parse dates to ISO 8601 format (yyyy-MM-dd or yyyy-MM-ddTHH:mm:ss)\n"
        . "- Parse decimal amounts (remove currency symbols, handle comma/dot separators)\n"
        . "- Skip rows that are clearly subtotals, headers, or empty\n"
        . "- If multiple source rows represent one entity, group them\n"
        . "- Respond with JSON array only, no markdown\n"
        . "- Cell values containing pipe characters appear as \\| in the table, use | (without backslash) in your JSON output\n";

    if ($entityType === 'Products') {
        $sb .= "\nProduct-specific rules:\n"
            . "- ALWAYS provide a categoryName for every product, even if the source data has no category column\n"
            . "- If the source data has a category, use it as categoryName\n"
            . "- If no category exists in source data, infer an appropriate category name from the product name and description (e.g., 'Industrial Drill Press' → 'Power Tools', 'Monthly Bookkeeping' → 'Bookkeeping Services', 'Copper Pipe' → 'Plumbing')\n"
            . "- Set type to 'Expense' for products/services that are typically purchased or expensed (e.g., office supplies, bookkeeping, equipment rental), and 'Revenue' for items typically sold to customers\n";
    }

    return $sb;
}

/** Port of BuildTier2UserPrompt. */
function pa_build_tier2_user_prompt(array $headers, array $rows, array $currencyRows = []): string
{
    $sb = "Convert these rows:\n\n";
    $sb .= '| ' . implode(' | ', $headers) . " |\n";
    $sb .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";
    foreach ($rows as $rowIdx => $row) {
        $cells = $row;
        while (count($cells) < count($headers)) {
            $cells[] = '';
        }
        // Surface currency carried in the cell number format (e.g. show 1200 as
        // "£1200") so the model emits originalCurrency for these rows.
        $curRow = $currencyRows[$rowIdx] ?? [];
        foreach ($cells as $c => $v) {
            $tok = $curRow[$c] ?? '';
            if ($tok !== '' && trim((string) $v) !== '') {
                $cells[$c] = $tok . $v;
            }
        }
        $escaped = array_map(fn($c) => str_replace('|', '\\|', (string)$c), $cells);
        $sb .= '| ' . implode(' | ', $escaped) . " |\n";
    }
    return $sb;
}

// ─── Batching + analysis call (port of SplitIntoAnalysisBatches / AnalyzeBatchAsync) ─

/** Split sheets into batches whose combined column count stays within the cap. */
function pa_split_into_batches(array $sheets): array
{
    $batches = [];
    $current = [];
    $currentColumns = 0;
    foreach ($sheets as $sheet) {
        $columns = count($sheet['headers']);
        if (count($current) > 0 && $currentColumns + $columns > PA_MAX_COLUMNS_PER_BATCH) {
            $batches[] = $current;
            $current = [];
            $currentColumns = 0;
        }
        $current[] = $sheet;
        $currentColumns += $columns;
    }
    if (count($current) > 0) {
        $batches[] = $current;
    }
    return $batches;
}

/**
 * Analyze all sheets and return a map: sourceSheetName => analysis
 *   analysis = { detectedType, confidence, tier, columnMappings:[{source,target}], included:bool }
 */
function pa_analyze_sheets(array $sheets, ?string $country): array
{
    $batches = pa_split_into_batches($sheets);
    $system = pa_build_analysis_system_prompt();

    // Build one request per batch and run up to 5 concurrently (app parity).
    $requests = [];
    foreach ($batches as $batch) {
        $totalColumns = array_sum(array_map(fn($s) => count($s['headers']), $batch));
        $requests[] = [
            'system' => $system,
            'user' => pa_build_analysis_user_prompt($batch, $country),
            'maxTokens' => max(4000, $totalColumns * 200 + count($batch) * 400),
            'temp' => 0.1,
        ];
    }

    $responses = pa_gemini_chat_multi($requests, PA_MAX_CONCURRENT_ANALYSIS);

    $merged = [];
    foreach ($responses as $response) {
        if ($response === null) {
            continue;
        }
        $parsed = pa_parse_analysis_response($response);
        if ($parsed === null) {
            continue;
        }
        foreach ($parsed as $name => $analysis) {
            $merged[$name] = $analysis;
        }
    }

    return $merged;
}

/** Port of ParseAnalysisResponse. Returns map sheetName => analysis, or null. */
function pa_parse_analysis_response(string $response): ?array
{
    $clean = pa_strip_markdown_json($response);
    $doc = json_decode($clean, true);
    if (!is_array($doc) || !isset($doc['sheets']) || !is_array($doc['sheets'])) {
        return null;
    }

    $validTypes = array_flip(pa_entity_type_names());
    $out = [];
    foreach ($doc['sheets'] as $sheetEl) {
        if (!is_array($sheetEl)) {
            continue;
        }
        $name = (string)($sheetEl['sourceSheetName'] ?? '');
        $confidence = (float)($sheetEl['confidence'] ?? 0);

        $typeStr = (string)($sheetEl['detectedType'] ?? 'Unknown');
        // Case-insensitive enum match.
        $detectedType = 'Unknown';
        foreach ($validTypes as $t => $_) {
            if (strcasecmp($t, $typeStr) === 0) {
                $detectedType = $t;
                break;
            }
        }

        $tierStr = (string)($sheetEl['tier'] ?? '');
        $tier = stripos($tierStr, 'Tier2') !== false ? 'Tier2' : 'Tier1';

        $mappings = [];
        if (isset($sheetEl['columnMappings']) && is_array($sheetEl['columnMappings'])) {
            foreach ($sheetEl['columnMappings'] as $mapEl) {
                if (!is_array($mapEl)) {
                    continue;
                }
                $mappings[] = [
                    'source' => (string)($mapEl['sourceColumn'] ?? ''),
                    'target' => (string)($mapEl['targetColumn'] ?? ''),
                ];
            }
        }

        $included = !($detectedType === 'Unknown' || $confidence < PA_MIN_TYPE_CONFIDENCE);

        $out[$name] = [
            'detectedType' => $detectedType,
            'confidence' => $confidence,
            'tier' => $tier,
            'columnMappings' => $mappings,
            'included' => $included,
        ];
    }
    return $out;
}

// ─── Tier 1 (port of ApplyColumnMapping + the deterministic importers) ────────

/** Port of ApplyColumnMapping: rename matching source headers to their target names. */
function pa_apply_column_mapping(array $headers, array $mappings): array
{
    foreach ($mappings as $mapping) {
        foreach ($headers as $i => $h) {
            if (strcasecmp((string)$h, $mapping['source']) === 0) {
                $headers[$i] = $mapping['target'];
                break;
            }
        }
    }
    return $headers;
}

/** Build Tier 1 entities for a sheet by reading mapped columns by target name. */
function pa_tier1_entities(array $sheet, array $analysis, ?string $country): array
{
    $schema = pa_schema_for_type($analysis['detectedType'], $country);
    if ($schema === null) {
        return [];
    }
    $headers = pa_apply_column_mapping($sheet['headers'], $analysis['columnMappings']);

    // target name (lower) => first column index
    $nameToIdx = [];
    foreach ($headers as $i => $h) {
        $key = strtolower((string)$h);
        if (!isset($nameToIdx[$key])) {
            $nameToIdx[$key] = $i;
        }
    }

    // Amount columns to inspect for in-cell currency (mirrors CurrencyImportPreparer's
    // MoneyTargets).
    $moneyTargets = ['total', 'amount', 'unit price', 'subtotal'];
    $amountCols = [];
    foreach ($headers as $i => $h) {
        if (in_array(strtolower((string)$h), $moneyTargets, true)) {
            $amountCols[] = $i;
        }
    }
    $currencyRows = $sheet['currencyRows'] ?? [];

    $entities = [];
    $rowNum = 0;
    foreach ($sheet['dataRows'] as $rowIdx => $row) {
        $rowNum++;
        $entity = [];
        foreach ($schema as $col) {
            $json = $col['json'];
            if ($json === null) {
                continue;
            }
            $idx = $nameToIdx[strtolower($col['name'])] ?? null;
            if ($idx === null) {
                continue;
            }
            $raw = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
            if ($raw === '') {
                continue;
            }
            pa_set_json($entity, $json, pa_parse_value($raw, $col['type']));
        }
        if (empty($entity['id'])) {
            $entity['id'] = pa_generate_id($analysis['detectedType'], $rowNum);
        }
        // Drop rows that carried no real data (only the generated id).
        if (count($entity) <= 1) {
            continue;
        }
        // Per-row currency from the amount cells, unless the sheet already mapped a
        // Currency column onto the row (then trust that).
        if (empty($entity['originalCurrency']) && $amountCols) {
            $cur = pa_detect_row_currency($row, $currencyRows[$rowIdx] ?? [], $amountCols);
            if ($cur !== null) {
                $entity['originalCurrency'] = $cur;
            }
        }
        $entities[] = $entity;
    }
    return $entities;
}

/**
 * Resolve one row's currency from its amount cells, like CurrencyImportPreparer:
 * the first amount cell with an explicit code or unambiguous symbol wins; failing
 * that, the first ambiguous symbol resolves to its priority default (no user prompt
 * on the web). Prefers the number-format token, falling back to the cell text.
 */
function pa_detect_row_currency(array $row, array $currencyRow, array $amountCols): ?string
{
    $pending = null;
    foreach ($amountCols as $col) {
        $token = $currencyRow[$col] ?? '';
        $probe = $token !== '' ? $token : (string) ($row[$col] ?? '');
        if (trim($probe) === '') {
            continue;
        }
        $d = pa_currency_detect($probe);
        if ($d['code'] !== null) {
            return $d['code'];
        }
        if ($d['ambiguous'] !== null && $pending === null && $d['candidates']) {
            $pending = $d['candidates'][0];
        }
    }
    return $pending;
}

// ─── Tier 2 (port of ProcessChunkAsync / ParseTier2Response) ──────────────────

/** Process all rows of a Tier 2 sheet through Gemini in 100-row chunks. */
function pa_tier2_entities(array $sheet, array $analysis, ?string $country): array
{
    $schema = pa_schema_for_type($analysis['detectedType'], $country);
    if ($schema === null) {
        return [];
    }
    $system = pa_build_tier2_system_prompt($analysis['detectedType'], $schema);
    $headers = $sheet['headers'];
    $allRows = $sheet['dataRows'];
    $allCur = $sheet['currencyRows'] ?? [];

    // One request per 100-row chunk, run up to 10 concurrently (app parity).
    // Results come back in request order, so entity/row order is preserved.
    $requests = [];
    for ($i = 0; $i < count($allRows); $i += PA_TIER2_CHUNK) {
        $chunk = array_slice($allRows, $i, PA_TIER2_CHUNK);
        $curChunk = array_slice($allCur, $i, PA_TIER2_CHUNK);
        $requests[] = [
            'system' => $system,
            'user' => pa_build_tier2_user_prompt($headers, $chunk, $curChunk),
            'maxTokens' => 16000,
            'temp' => 0.0,
        ];
    }

    $responses = pa_gemini_chat_multi($requests, PA_MAX_CONCURRENT_CHUNKS);

    $entities = [];
    foreach ($responses as $response) {
        if ($response === null) {
            continue;
        }
        foreach (pa_parse_tier2_response($response) as $ent) {
            $entities[] = $ent;
        }
    }
    return $entities;
}

/** Port of ParseTier2Response: a JSON array of entity objects (or {entities:[...]}). */
function pa_parse_tier2_response(string $response): array
{
    $clean = pa_strip_markdown_json($response);
    $doc = json_decode($clean, true);
    if (is_array($doc)) {
        if (array_is_list($doc)) {
            return array_values(array_filter($doc, 'is_array'));
        }
        if (isset($doc['entities']) && is_array($doc['entities'])) {
            return array_values(array_filter($doc['entities'], 'is_array'));
        }
    }
    return [];
}

// ─── Value parsing helpers ────────────────────────────────────────────────────

/** Set a possibly-dotted JsonName ("address.city") into a nested entity array. */
function pa_set_json(array &$entity, string $json, $value): void
{
    if (strpos($json, '.') === false) {
        $entity[$json] = $value;
        return;
    }
    $parts = explode('.', $json);
    $ref = &$entity;
    foreach ($parts as $k => $part) {
        if ($k === count($parts) - 1) {
            $ref[$part] = $value;
        } else {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
    }
    unset($ref);
}

/** Parse a raw cell into a typed value based on the schema column type. */
function pa_parse_value(string $raw, string $type)
{
    if ($type === 'decimal') {
        return pa_parse_number($raw);
    }
    if ($type === 'int') {
        return (int)round(pa_parse_number($raw));
    }
    if ($type === 'datetime') {
        return pa_parse_date($raw);
    }
    // string + enum: pass through.
    return $raw;
}

/** Strip currency symbols/spaces and parse a decimal. Handles 1,234.56 and bare numbers. */
function pa_parse_number(string $raw): float
{
    $s = trim($raw);
    $negative = false;
    if (preg_match('/^\(.*\)$/', $s)) { // (123.45) accounting negative
        $negative = true;
        $s = trim($s, '()');
    }
    // Keep digits, separators, and sign.
    $s = preg_replace('/[^0-9,.\-]/', '', $s);
    // If both separators present, assume comma = thousands.
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace(',', '', $s);
    } elseif (strpos($s, ',') !== false) {
        // Comma only: treat as thousands unless it looks like a decimal comma (1,5).
        if (preg_match('/,\d{1,2}$/', $s) && substr_count($s, ',') === 1) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
    }
    $val = is_numeric($s) ? (float)$s : 0.0;
    return $negative ? -$val : $val;
}

/** Normalize a date to ISO yyyy-MM-dd; keep the raw value if it can't be parsed. */
function pa_parse_date(string $raw): string
{
    $s = trim($raw);
    if ($s === '') {
        return '';
    }
    // Already ISO (from the xlsx reader)?
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
        return substr($s, 0, 10);
    }
    $ts = strtotime($s);
    return $ts !== false ? date('Y-m-d', $ts) : $s;
}

/** Generate a typed placeholder id (e.g. SAL-001) when a row has none. */
function pa_generate_id(string $type, int $n): string
{
    $prefixes = [
        'Customers' => 'CUS', 'Suppliers' => 'SUP', 'Products' => 'PRD',
        'Categories' => 'CAT', 'Invoices' => 'INV', 'Expenses' => 'PUR',
        'Revenue' => 'SAL', 'Inventory' => 'INV-ITM', 'Payments' => 'PAY',
        'Locations' => 'LOC', 'Departments' => 'DEP', 'Employees' => 'EMP',
        'Returns' => 'RET', 'LostDamaged' => 'LOST', 'RentalRecords' => 'RNT',
        'PurchaseOrders' => 'PO', 'BankStatement' => 'TXN',
    ];
    $prefix = $prefixes[$type] ?? 'ROW';
    return $prefix . '-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}

/**
 * Top-level: analyze a list of sheet structs and return per-included-sheet
 * entities. Each result: { sheetName, entityType, entities:[importer-shape] }.
 */
function pa_analyze_file_sheets(array $sheets, ?string $country): array
{
    if (count($sheets) === 0) {
        return [];
    }
    $analyses = pa_analyze_sheets($sheets, $country);

    $results = [];
    foreach ($sheets as $sheet) {
        $analysis = $analyses[$sheet['name']] ?? null;
        if ($analysis === null || !$analysis['included']) {
            continue;
        }
        $entities = $analysis['tier'] === 'Tier2'
            ? pa_tier2_entities($sheet, $analysis, $country)
            : pa_tier1_entities($sheet, $analysis, $country);

        if (count($entities) === 0) {
            continue;
        }
        $results[] = [
            'sheetName' => $sheet['name'],
            'entityType' => $analysis['detectedType'],
            'entities' => $entities,
        ];
    }
    return $results;
}
