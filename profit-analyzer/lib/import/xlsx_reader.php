<?php
// profit-analyzer/lib/import/xlsx_reader.php
//
// Pure-PHP .xlsx reader. The server has neither the zip extension nor
// PhpSpreadsheet, so this unzips the workbook manually (zip central directory +
// gzinflate) and parses the OOXML parts with XMLReader/regex. It returns, per
// worksheet, the full string matrix + merged ranges — the equivalent of what
// ClosedXML hands the desktop analysis service. Date serials are rendered the
// same way CellToString does (yyyy-MM-dd, or with time when non-midnight).

require_once __DIR__ . '/currency.php'; // pa_currency_canon for number-format currency detection

/**
 * Unzip an in-memory .xlsx into a map of entry-name => uncompressed bytes.
 * Reads the zip central directory, then each local header, inflating DEFLATE
 * entries with gzinflate and copying STORED entries verbatim.
 *
 * @return array<string,string>|null  null if the file isn't a readable zip
 */
function pa_zip_entries(string $bytes): ?array
{
    $len = strlen($bytes);
    if ($len < 22) {
        return null;
    }

    // Find End Of Central Directory record (signature PK\x05\x06), scanning back
    // from the end (a trailing comment can push it up to ~64KB from EOF).
    $eocd = -1;
    $searchStart = max(0, $len - 65557);
    for ($i = $len - 22; $i >= $searchStart; $i--) {
        if ($bytes[$i] === "P" && substr($bytes, $i, 4) === "PK\x05\x06") {
            $eocd = $i;
            break;
        }
    }
    if ($eocd < 0) {
        return null;
    }

    $cdCount = unpack('v', substr($bytes, $eocd + 10, 2))[1];
    $cdOffset = unpack('V', substr($bytes, $eocd + 16, 4))[1];
    if ($cdOffset === 0xFFFFFFFF) {
        return null; // ZIP64 not supported (not needed for <=5MB xlsx)
    }

    $entries = [];
    $p = $cdOffset;
    for ($n = 0; $n < $cdCount; $n++) {
        if (substr($bytes, $p, 4) !== "PK\x01\x02") {
            break;
        }
        $method = unpack('v', substr($bytes, $p + 10, 2))[1];
        $compSize = unpack('V', substr($bytes, $p + 20, 4))[1];
        $nameLen = unpack('v', substr($bytes, $p + 28, 2))[1];
        $extraLen = unpack('v', substr($bytes, $p + 30, 2))[1];
        $commentLen = unpack('v', substr($bytes, $p + 32, 2))[1];
        $localOffset = unpack('V', substr($bytes, $p + 42, 4))[1];
        $name = substr($bytes, $p + 46, $nameLen);
        $p += 46 + $nameLen + $extraLen + $commentLen;

        // Read the local header to locate the actual data start.
        if (substr($bytes, $localOffset, 4) !== "PK\x03\x04") {
            continue;
        }
        $lNameLen = unpack('v', substr($bytes, $localOffset + 26, 2))[1];
        $lExtraLen = unpack('v', substr($bytes, $localOffset + 28, 2))[1];
        $dataStart = $localOffset + 30 + $lNameLen + $lExtraLen;
        $raw = substr($bytes, $dataStart, $compSize);

        if ($method === 0) {
            $entries[$name] = $raw;
        } elseif ($method === 8) {
            $inflated = @gzinflate($raw);
            $entries[$name] = $inflated === false ? '' : $inflated;
        }
        // Other methods are ignored (xlsx only ever uses stored/deflate).
    }

    return $entries;
}

/** Convert an A1-style column reference ("AB12" or "AB") to a 0-based column index. */
function pa_col_to_index(string $ref): int
{
    $col = 0;
    $n = strlen($ref);
    for ($i = 0; $i < $n; $i++) {
        $ch = $ref[$i];
        if ($ch >= 'A' && $ch <= 'Z') {
            $col = $col * 26 + (ord($ch) - ord('A') + 1);
        } elseif ($ch >= 'a' && $ch <= 'z') {
            $col = $col * 26 + (ord($ch) - ord('a') + 1);
        } else {
            break;
        }
    }
    return $col - 1;
}

/** Parse sharedStrings.xml into an indexed list. Concatenates all <t> runs in each <si>. */
function pa_xlsx_shared_strings(string $xml): array
{
    if ($xml === '') {
        return [];
    }
    $strings = [];
    $reader = new XMLReader();
    if (!@$reader->XML($xml)) {
        return [];
    }
    // NB: do not call $reader->next() here — combined with the while(read()) loop it double- advances and drops every other <si>.
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
            $node = $reader->readOuterXml();
            // Sum all <t> text in this <si> (covers multi-run rich text).
            $text = '';
            if (preg_match_all('/<(?:[a-zA-Z0-9]+:)?t[^>]*>(.*?)<\/(?:[a-zA-Z0-9]+:)?t>/s', $node, $m)) {
                foreach ($m[1] as $frag) {
                    $text .= html_entity_decode($frag, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
            $strings[] = $text;
        }
    }
    $reader->close();
    return $strings;
}

/**
 * Build the set of cell-style indices (the `s` attribute) that represent dates,
 * by mapping cellXfs -> numFmtId and classifying each numFmtId as a date format.
 * @return array<int,bool>  styleIndex => true when it's a date style
 */
function pa_xlsx_date_styles(string $xml): array
{
    if ($xml === '') {
        return [];
    }
    // Builtin date/time number-format ids.
    $builtinDate = [14 => 1, 15 => 1, 16 => 1, 17 => 1, 18 => 1, 19 => 1, 20 => 1,
        21 => 1, 22 => 1, 45 => 1, 46 => 1, 47 => 1];

    // Custom numFmts: numFmtId => formatCode (attribute order varies by writer).
    $custom = [];
    if (preg_match_all('/<numFmt\b[^>]*>/', $xml, $m)) {
        foreach ($m[0] as $tag) {
            if (preg_match('/\bnumFmtId="(\d+)"/', $tag, $idm) && preg_match('/\bformatCode="([^"]*)"/', $tag, $fm)) {
                $custom[(int)$idm[1]] = html_entity_decode($fm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
    }

    $isDateFmt = function (int $numFmtId) use ($builtinDate, $custom): bool {
        if (isset($builtinDate[$numFmtId])) {
            return true;
        }
        if (isset($custom[$numFmtId])) {
            // Strip quoted literals and bracketed sections, then look for date tokens.
            $code = preg_replace('/"[^"]*"/', '', $custom[$numFmtId]);
            $code = preg_replace('/\[[^\]]*\]/', '', $code);
            $code = strtolower($code);
            return (bool)preg_match('/[ymdhs]/', $code);
        }
        return false;
    };

    // cellXfs in document order define style indices 0..n.
    $dateStyles = [];
    if (preg_match('/<cellXfs[^>]*>(.*?)<\/cellXfs>/s', $xml, $block)) {
        if (preg_match_all('/<xf[^>]*\/?>/', $block[1], $xfs)) {
            foreach ($xfs[0] as $idx => $xf) {
                if (preg_match('/numFmtId="(\d+)"/', $xf, $nm)) {
                    if ($isDateFmt((int)$nm[1])) {
                        $dateStyles[$idx] = true;
                    }
                }
            }
        }
    }
    return $dateStyles;
}

/**
 * Map style index -> currency token carried in the cell's NUMBER FORMAT, so a
 * numeric 1200 displayed as "£1,200.00" is recognized as GBP even though the
 * stored value is a bare number. This is the website equivalent of the desktop
 * app reading ClosedXML's GetFormattedString. Returns styleIndex => token
 * (e.g. "£", "¥", "CAD", "$"); the detector resolves the token to an ISO code.
 */
function pa_xlsx_currency_styles(string $xml): array
{
    if ($xml === '') {
        return [];
    }

    // Builtin currency / accounting number-format ids (locale "$" in en-US).
    $builtinCurrency = [5 => '$', 6 => '$', 7 => '$', 8 => '$', 41 => '$', 42 => '$', 43 => '$', 44 => '$'];

    // Custom numFmts: numFmtId => formatCode.
    $custom = [];
    if (preg_match_all('/<numFmt\b[^>]*>/', $xml, $m)) {
        foreach ($m[0] as $tag) {
            if (preg_match('/\bnumFmtId="(\d+)"/', $tag, $idm) && preg_match('/\bformatCode="([^"]*)"/', $tag, $fm)) {
                $custom[(int) $idm[1]] = html_entity_decode($fm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
    }

    // Extract a currency token from a format code, or '' if it isn't a currency format.
    // When the literal is an AMBIGUOUS symbol (e.g. "¥" -> CNY/JPY), the format's
    // decimal count is used to disambiguate (JPY has 0 decimals, CNY has 2), since
    // the web tool has no user-resolution prompt like the desktop app. Resolving to
    // a single candidate emits its ISO code; otherwise the raw symbol is kept.
    $tokenFor = function (string $code): string {
        // A format with date tokens is a date, not currency.
        $bare = preg_replace('/"[^"]*"/', '', $code);
        $bare = preg_replace('/\[[^\]]*\]/', '', $bare);
        if (preg_match('/[ymdhs]/i', $bare)) {
            return '';
        }
        // Decimal places in the number pattern (e.g. #,##0.00 -> 2, #,##0 -> 0).
        $decimals = preg_match('/\.(0+)/', $bare, $dm) ? strlen($dm[1]) : 0;

        $cands = [];
        if (preg_match_all('/"([^"]*)"/', $code, $qm)) {       // "£", " CAD"
            foreach ($qm[1] as $lit) { $cands[] = trim($lit); }
        }
        if (preg_match_all('/\[\$([^\]\-]+)(?:-[^\]]*)?\]/', $code, $lm)) { // [$£-809], [$USD]
            foreach ($lm[1] as $lit) { $cands[] = trim($lit); }
        }
        foreach ($cands as $lit) {
            if ($lit === '' || pa_currency_canon($lit) === null) {
                continue;
            }
            $d = pa_currency_detect($lit);
            // Ambiguous symbol: try to pin it down by matching the format's decimals.
            if ($d['code'] === null && $d['ambiguous'] !== null) {
                $all = pa_currency_all();
                $matches = array_values(array_filter(
                    $d['candidates'],
                    fn($iso) => isset($all[$iso]) && $all[$iso][2] === $decimals
                ));
                if (count($matches) === 1) {
                    return $matches[0]; // e.g. "¥" + 0 decimals -> JPY
                }
            }
            return $lit;
        }
        return '';
    };

    // cellXfs in document order define style indices 0..n.
    $styles = [];
    if (preg_match('/<cellXfs[^>]*>(.*?)<\/cellXfs>/s', $xml, $block)) {
        if (preg_match_all('/<xf[^>]*\/?>/', $block[1], $xfs)) {
            foreach ($xfs[0] as $idx => $xf) {
                if (!preg_match('/numFmtId="(\d+)"/', $xf, $nm)) {
                    continue;
                }
                $fid = (int) $nm[1];
                if (isset($builtinCurrency[$fid])) {
                    $styles[$idx] = $builtinCurrency[$fid];
                } elseif (isset($custom[$fid])) {
                    $tok = $tokenFor($custom[$fid]);
                    if ($tok !== '') {
                        $styles[$idx] = $tok;
                    }
                }
            }
        }
    }
    return $styles;
}

/**
 * Convert a number to a plain decimal string (no scientific notation), matching
 * ClosedXML's GetDouble().ToString(InvariantCulture). Excel often stores values
 * like "1.2345E-2"; left as-is the amount parser would strip the "E" and mangle
 * the number, so expand only those.
 */
function pa_xlsx_plain_number(string $v): string
{
    if (stripos($v, 'e') === false) {
        return $v; // not scientific notation
    }
    $f = (float) $v;
    if (!is_finite($f)) {
        return $v;
    }
    $s = rtrim(rtrim(sprintf('%.15F', $f), '0'), '.');
    return ($s === '' || $s === '-0') ? '0' : $s;
}

/** Convert an Excel date serial to a string, mirroring CellToString's formatting. */
function pa_xlsx_serial_to_date(float $serial, bool $date1904 = false): string
{
    // 1900 system epoch is 1899-12-30 (accounts for the fictional 1900 leap day);
    // the 1904 system (some Mac-authored files) counts days from 1904-01-01.
    $days = (int)floor($serial);
    $frac = $serial - $days;
    $base = new DateTime($date1904 ? '1904-01-01 00:00:00' : '1899-12-30 00:00:00', new DateTimeZone('UTC'));
    $base->modify("+{$days} days");
    $seconds = (int)round($frac * 86400);
    if ($seconds > 0) {
        $base->modify("+{$seconds} seconds");
        return $base->format('Y-m-d\TH:i:s');
    }
    return $base->format('Y-m-d');
}

/**
 * Parse one worksheet XML into a string matrix (rows of cells, gaps filled with "").
 * @return list<list<string>>
 */
function pa_xlsx_parse_sheet(string $xml, array $sharedStrings, array $dateStyles, array $currencyStyles = [], ?array &$currencyMatrix = null, bool $date1904 = false): array
{
    $rows = [];
    $curRows = [];
    $reader = new XMLReader();
    if (!@$reader->XML($xml)) {
        $currencyMatrix = [];
        return [];
    }
    // NB: no $reader->next() — see pa_xlsx_shared_strings; it would skip every
    // other <row>. read() descends into the (ignored) <c> children harmlessly.
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
            $rowXml = $reader->readOuterXml();
            $parsed = pa_xlsx_parse_row($rowXml, $sharedStrings, $dateStyles, $currencyStyles, $date1904);
            $rows[] = $parsed['cells'];
            $curRows[] = $parsed['currency'];
        }
    }
    $reader->close();

    // Normalize every row (and its currency row) to the widest column count.
    $maxCols = 0;
    foreach ($rows as $r) {
        $maxCols = max($maxCols, count($r));
    }
    foreach ($rows as $i => &$r) {
        while (count($r) < $maxCols) {
            $r[] = '';
        }
        $cr = $curRows[$i];
        while (count($cr) < $maxCols) {
            $cr[] = '';
        }
        $curRows[$i] = $cr;
    }
    unset($r);

    $currencyMatrix = $curRows;
    return $rows;
}

/** Parse a single <row> element's cells into a positional string array. */
function pa_xlsx_parse_row(string $rowXml, array $sharedStrings, array $dateStyles, array $currencyStyles = [], bool $date1904 = false): array
{
    $cells = [];
    $currency = [];
    if (!preg_match_all('/<c\b([^>]*)(?:\/>|>(.*?)<\/c>)/s', $rowXml, $matches, PREG_SET_ORDER)) {
        return ['cells' => [], 'currency' => []];
    }
    $autoCol = 0;
    foreach ($matches as $cell) {
        $attrs = $cell[1];
        $inner = $cell[2] ?? '';

        // Column index from the r="A1" reference, else sequential.
        if (preg_match('/r="([A-Za-z]+)\d+"/', $attrs, $rm)) {
            $col = pa_col_to_index($rm[1]);
        } else {
            $col = $autoCol;
        }
        $autoCol = $col + 1;

        $type = preg_match('/t="([^"]+)"/', $attrs, $tm) ? $tm[1] : '';
        $style = preg_match('/s="(\d+)"/', $attrs, $sm) ? (int)$sm[1] : -1;

        $value = '';
        $vRaw = '';
        if ($type === 'inlineStr') {
            if (preg_match_all('/<(?:[a-zA-Z0-9]+:)?t[^>]*>(.*?)<\/(?:[a-zA-Z0-9]+:)?t>/s', $inner, $im)) {
                foreach ($im[1] as $frag) {
                    $value .= html_entity_decode($frag, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        } else {
            $vRaw = preg_match('/<v[^>]*>(.*?)<\/v>/s', $inner, $vm) ? $vm[1] : '';
            $vRaw = html_entity_decode($vRaw, ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($type === 's') {
                $idx = (int)$vRaw;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'b') {
                $value = $vRaw === '1' ? 'True' : 'False';
            } elseif ($type === 'str') {
                $value = $vRaw;
            } else {
                // Number (no type). Render as a date when the style says so, else
                // normalize scientific notation to a plain decimal.
                if ($vRaw !== '' && is_numeric($vRaw) && isset($dateStyles[$style])) {
                    $value = pa_xlsx_serial_to_date((float)$vRaw, $date1904);
                } elseif ($vRaw !== '' && is_numeric($vRaw)) {
                    $value = pa_xlsx_plain_number($vRaw);
                } else {
                    $value = $vRaw;
                }
            }
        }

        // Currency carried in the cell's number format (only for numeric cells
        // that weren't rendered as a date). Empty string when none.
        $token = '';
        if (($type === '' || $type === 'n') && $vRaw !== '' && is_numeric($vRaw)
            && !isset($dateStyles[$style]) && isset($currencyStyles[$style])) {
            $token = $currencyStyles[$style];
        }

        // Place at the right column, filling gaps with "".
        while (count($cells) < $col) {
            $cells[] = '';
            $currency[] = '';
        }
        $cells[$col] = $value;
        $currency[$col] = $token;
    }
    return ['cells' => $cells, 'currency' => $currency];
}

/** Extract merged-range rectangles (1-based, like the layout gate expects). */
function pa_xlsx_merged_ranges(string $sheetXml): array
{
    $merges = [];
    if (preg_match_all('/<mergeCell[^>]*ref="([A-Z]+\d+):([A-Z]+\d+)"/', $sheetXml, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            preg_match('/([A-Z]+)(\d+)/', $mm[1], $a);
            preg_match('/([A-Z]+)(\d+)/', $mm[2], $b);
            $merges[] = [
                'firstRow' => (int)$a[2],
                'lastRow' => (int)$b[2],
                'firstCol' => pa_col_to_index($a[1]) + 1,
                'lastCol' => pa_col_to_index($b[1]) + 1,
            ];
        }
    }
    return $merges;
}

/**
 * Read an .xlsx file into a list of worksheets, each a raw string matrix + merges.
 * @return list<array{name:string,matrix:list<list<string>>,merges:list<array>}>|null
 */
function pa_read_xlsx(string $path): ?array
{
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        return null;
    }
    $zip = pa_zip_entries($bytes);
    if ($zip === null || !isset($zip['xl/workbook.xml'])) {
        return null;
    }

    $sharedStrings = pa_xlsx_shared_strings($zip['xl/sharedStrings.xml'] ?? '');
    $dateStyles = pa_xlsx_date_styles($zip['xl/styles.xml'] ?? '');
    $currencyStyles = pa_xlsx_currency_styles($zip['xl/styles.xml'] ?? '');
    // The 1904 date system (common in Mac-authored files) shifts every serial by
    // ~4 years; honor <workbookPr date1904="1"/> so dates aren't silently wrong.
    $date1904 = (bool) preg_match('/<workbookPr\b[^>]*\bdate1904="(?:1|true)"/i', $zip['xl/workbook.xml']);

    // Map rId -> worksheet target path from the workbook relationships. Attribute
    // order varies by writer (Excel emits Type, Target, Id), so read each
    // <Relationship> tag and pull Id/Target independently rather than positionally.
    $relMap = [];
    $relsXml = $zip['xl/_rels/workbook.xml.rels'] ?? '';
    if (preg_match_all('/<Relationship\b[^>]*>/', $relsXml, $rm)) {
        foreach ($rm[0] as $tag) {
            if (preg_match('/\bId="([^"]+)"/', $tag, $idm) && preg_match('/\bTarget="([^"]+)"/', $tag, $tm)) {
                $target = preg_replace('#^/?xl/#', '', $tm[1]); // normalize to xl-relative
                $relMap[$idm[1]] = 'xl/' . ltrim($target, '/');
            }
        }
    }

    // Ordered sheets from workbook.xml (name + rId).
    $sheets = [];
    if (preg_match_all('/<sheet\b[^>]*\/?>/', $zip['xl/workbook.xml'], $sm)) {
        foreach ($sm[0] as $sheetTag) {
            $name = preg_match('/name="([^"]*)"/', $sheetTag, $nm)
                ? html_entity_decode($nm[1], ENT_QUOTES | ENT_XML1, 'UTF-8') : 'Sheet';
            $rid = preg_match('/r:id="([^"]+)"/', $sheetTag, $rm2) ? $rm2[1] : '';
            $target = $relMap[$rid] ?? '';
            if ($target === '' || !isset($zip[$target])) {
                continue;
            }
            $sheetXml = $zip[$target];
            $currencyMatrix = [];
            $matrix = pa_xlsx_parse_sheet($sheetXml, $sharedStrings, $dateStyles, $currencyStyles, $currencyMatrix, $date1904);
            $sheets[] = [
                'name' => $name,
                'matrix' => $matrix,
                'fmtCurrency' => $currencyMatrix,
                'merges' => pa_xlsx_merged_ranges($sheetXml),
            ];
        }
    }

    return $sheets;
}
