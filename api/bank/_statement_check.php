<?php
/**
 * Reading Gemini's answer for a bank statement and checking it against the statement's own balances.
 * Kept apart from extract.php so the checks can be tested without a request or a Gemini call.
 */

/**
 * Parses the model's JSON into the transaction rows and the balances the statement prints.
 * Tolerant of a bare array of rows, a { "lines": [...] } wrapper, and string amounts.
 *
 * @return array{lines: list<array{date: string, description: string, amount: float}>, opening: ?float, closing: ?float, credit_card: bool, accounts: int}
 */
function bs_parse_statement(string $content): array
{
    $decoded = json_decode($content, true);
    $result = ['lines' => [], 'opening' => null, 'closing' => null, 'credit_card' => false, 'accounts' => 1];
    if (!is_array($decoded)) {
        return $result;
    }

    $rows = $decoded['lines'] ?? (array_is_list($decoded) ? $decoded : []);
    $result['lines'] = is_array($rows) ? bs_normalize_lines($rows) : [];

    if (!array_is_list($decoded)) {
        $result['opening'] = bs_number_or_null($decoded['openingBalance'] ?? null);
        $result['closing'] = bs_number_or_null($decoded['closingBalance'] ?? null);
        $result['credit_card'] = ($decoded['creditCard'] ?? false) === true;
        $accounts = $decoded['accountCount'] ?? 1;
        $result['accounts'] = is_numeric($accounts) ? max(1, (int) $accounts) : 1;
    }
    return $result;
}

/**
 * Cleans the model's rows. Returns a list of [ 'date' => 'YYYY-MM-DD', 'description' => string, 'amount' => float ].
 */
function bs_normalize_lines(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $desc = trim((string)($row['description'] ?? ''));
        $rawAmount = $row['amount'] ?? null;
        if ($rawAmount === null || !is_numeric($rawAmount)) {
            continue; // a row with no usable amount is not a transaction
        }
        $amount = (float)$rawAmount;

        $date = trim((string)($row['date'] ?? ''));
        $ts = $date !== '' ? strtotime($date) : false;
        $isoDate = $ts !== false ? date('Y-m-d', $ts) : '';

        if ($desc === '' && $isoDate === '') {
            continue;
        }
        $out[] = [
            'date' => $isoDate,
            'description' => $desc,
            'amount' => $amount,
        ];
    }
    return $out;
}

/**
 * Checks that the opening balance plus the rows comes to the closing balance.
 * The gap is the closing balance minus what the rows come to, so zero when they agree.
 *
 * @return array{status: string, gap: ?float}  status is balanced, off or unchecked
 */
function bs_check_balance(array $parsed): array
{
    if ($parsed['opening'] === null || $parsed['closing'] === null || $parsed['accounts'] > 1 || $parsed['lines'] === []) {
        return ['status' => 'unchecked', 'gap' => null];
    }

    $sum = 0.0;
    foreach ($parsed['lines'] as $line) {
        $sum += $line['amount'];
    }
    // A card's balance is what is owed, so spending, which the rows show as negative, raises it.
    $expected = $parsed['credit_card'] ? $parsed['opening'] - $sum : $parsed['opening'] + $sum;
    $gap = round($parsed['closing'] - $expected, 2);

    return abs($gap) < 0.005
        ? ['status' => 'balanced', 'gap' => 0.0]
        : ['status' => 'off', 'gap' => $gap];
}

function bs_number_or_null($value): ?float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    if (is_string($value)) {
        $clean = str_replace([',', '$', ' '], '', $value);
        return is_numeric($clean) ? (float) $clean : null;
    }
    return null;
}
