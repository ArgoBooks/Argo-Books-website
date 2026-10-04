<?php
/**
 * Pure helpers for the free web receipt scanner (/free-receipt-scanner/).
 *
 * No top-level I/O so this file is safe to require from PHPUnit. The HTTP
 * endpoint (scan.php) handles all network, rate-limit, image, and email side
 * effects. The extraction prompt is ported from the desktop
 * GeminiReceiptScannerService so the web tool produces the same structured
 * output the desktop app does.
 */

const RECEIPT_SCAN_SYSTEM_PROMPT = <<<'PROMPT'
You are a receipt data extraction system. You must extract EVERY item and ALL data from the receipt image into structured JSON. Be thorough, missing items is unacceptable.

Return JSON only (no markdown code blocks), with this exact format:
{
  "supplierName": "Store or business name",
  "transactionDate": "YYYY-MM-DD",
  "subtotal": 0.00,
  "taxes": [{"name": "GST", "amount": 0.00}, {"name": "PST", "amount": 0.00}],
  "discounts": [{"name": "Member Discount", "amount": 0.00}],
  "totalAmount": 0.00,
  "currencyCode": "USD",
  "paymentMethod": "Credit Card",
  "confidence": 0.95,
  "lineItems": [
    {"description": "Product Name", "quantity": 1, "unitPrice": 0.00, "totalPrice": 0.00, "confidence": 0.9}
  ]
}

Rules:
1. LINE ITEMS: Extract EVERY purchased item on the receipt. Scan the entire receipt top to bottom. Grocery receipts often have 20-40+ items, include ALL of them. Do not summarize or skip items. Each product line with a price is a line item. Return items in the same order they appear on the receipt.
2. TAX: Return EACH tax line separately in the "taxes" array. Do NOT sum them, list every individual tax with its label and amount. Common tax labels: GST, G-GST, PST, P-PST, HST, QST, TVQ, TPS, VAT, state tax, county tax, city tax, sales tax, excise tax. If there is only one tax line, still return it as a single-element array.
3. PRODUCT NAMES: Transcribe EXACTLY as printed on the receipt, character by character. Do NOT normalize, expand abbreviations, correct spelling, or rename items. Keep the original abbreviations and casing. If a character is hard to read, use your best guess but do not substitute a different word. ALWAYS remove SKU codes, barcodes, and internal item numbers that are not part of the product name, especially a leading code printed before the name such as "6010-0272-0259-0062 Co Palm Refill" (extract just "Co Palm Refill") or a long leading digit string. The description must start with the product name, never with a code.
4. MONETARY VALUES: All as numbers. Use 0.00 for missing values, null for unknown fields.
5. CONFIDENCE: Both the overall "confidence" and each line item's "confidence" must be 0.0-1.0. Be STRICT and CONSERVATIVE with line item confidence: if the text is blurry, smudged, faded, partially obscured, wrinkled, or if ANY digit or character in the description or price required guessing, the confidence MUST be below 0.85. Use 0.5-0.7 for items where you are genuinely unsure about the price or name. Only use 0.9+ when the text is crisp and completely unambiguous. Do NOT default to high confidence, earn it.
6. PRICES vs DISCOUNTS: When a product has two numbers near it (a price and a discount/savings below it), the product's line item should use the FULL PRICE (the larger, positive number), not the discounted price. The discount is a separate entry in the "discounts" array.
7. DISCOUNTS: ANY line on the receipt with a negative amount or a minus sign is a discount. This includes lines labeled "Member Pricing", "Member Discount", "SAVE", "OFF", "DISCOUNT", coupons, promos, loyalty savings, price reductions, markdowns, or any other negative adjustment. Return EACH one separately in the "discounts" array with the label and amount as a positive number. Do NOT include discounts as line items. They belong only in the "discounts" array. Do NOT skip or ignore negative amounts.
8. ERROR: If the image is not a receipt or is completely unreadable, return: {"error": "Not a valid receipt", "confidence": 0.0}
9. DATE: YYYY-MM-DD format. Best guess if only partial date is visible.
10. CURRENCY: Infer the currency from location clues on the receipt: store address, city, province/state, country name, language, tax labels (e.g. GST/PST = CAD, VAT/TVA = EUR/GBP, IVA = EUR/MXN), and currency symbols ($ is ambiguous, GBP, EUR, JPY/CNY). Map the identified country to its ISO 4217 currency code. Default to "USD" only if there are genuinely no location or currency clues.
11. PAYMENT METHOD: One of "Credit Card", "Debit Card", "Cash", "Check", or null. "MASTERCARD", "VISA", "AMEX" = "Credit Card". "INTERAC", "DEBIT" = "Debit Card".
12. QUANTITY: Default to 1. For weighted/per-unit items (e.g. "1.340 kg @ $1.92/kg  2.57"), the rate line is NOT a separate line item. Use the FINAL COMPUTED PRICE on the right (2.57) as both unitPrice and totalPrice, and set quantity to 1. Ignore the per-unit rate and weight, the user only cares about the amount paid. These rate lines often contain "@", "/", "kg", "lb", "per", or appear indented below the product name.
13. SUPPLIER - This is often the largest and boldest text on the receipt, and usually at the very top.
14. SPATIAL ALIGNMENT: Grocery receipts use a two-column layout: product name on the LEFT, its price on the RIGHT of the SAME row. Match each product name to the price that is horizontally aligned with it, NOT the price on the row above or below. If a line has only a name with no price on its right, it is likely a description or category header, do not assign it a price from an adjacent row. The receipt photo may be tilted, mentally straighten it first, then read each row following the angle of the printed text lines.
15. CROSS-CHECK: After extracting all items, count the number of distinct price values visible on the right side of the receipt and compare to the number of line items you extracted. If you have fewer line items than prices, you missed an item, re-scan. Every price on the receipt must be accounted for as either a line item, a tax, a discount, or a total/subtotal.
16. DIGIT ACCURACY: Pay close attention to easily confused digits: 3 vs 8, 5 vs 6, 1 vs 7, 0 vs 6, swapped digits. When uncertain, look at the digit shape carefully before committing to a value.
PROMPT;

const RECEIPT_SCAN_USER_PROMPT = 'Extract all data from this receipt. Respond with JSON only.';

/**
 * Normalize the raw model text into the web response shape.
 *
 * @param string $modelContent Raw text returned by Gemini.
 * @return array ['ok'=>true,'receipt'=>[...]] on success, or
 *               ['ok'=>false,'error'=>'unreadable'] when the model reported a
 *               non-receipt or the JSON could not be parsed.
 */
function receipt_scan_normalize(string $modelContent): array
{
    $text = trim($modelContent);

    // Strip ```json ... ``` fences if the model added them despite instructions.
    if (str_starts_with($text, '```')) {
        $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);
    }

    $data = json_decode($text, true);
    if (!is_array($data) || isset($data['error'])) {
        return ['ok' => false, 'error' => 'unreadable'];
    }

    $taxes = receipt_scan_clean_name_amount($data['taxes'] ?? []);
    $discounts = receipt_scan_clean_name_amount($data['discounts'] ?? []);
    $taxTotal = 0.0;
    foreach ($taxes as $tax) {
        $taxTotal += $tax['amount'];
    }

    $lineItems = [];
    foreach (($data['lineItems'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $lineItems[] = [
            'description' => (string)($item['description'] ?? ''),
            'quantity'    => (float)($item['quantity'] ?? 1),
            'unitPrice'   => (float)($item['unitPrice'] ?? 0),
            'totalPrice'  => (float)($item['totalPrice'] ?? 0),
            'confidence'  => (float)($item['confidence'] ?? 0),
        ];
    }

    return [
        'ok' => true,
        'receipt' => [
            'supplierName'    => (string)($data['supplierName'] ?? ''),
            'transactionDate' => (string)($data['transactionDate'] ?? ''),
            'subtotal'        => (float)($data['subtotal'] ?? 0),
            'taxes'           => $taxes,
            'taxTotal'        => round($taxTotal, 2),
            'discounts'       => $discounts,
            'totalAmount'     => (float)($data['totalAmount'] ?? 0),
            'currencyCode'    => (string)($data['currencyCode'] ?? 'USD'),
            'paymentMethod'   => (string)($data['paymentMethod'] ?? ''),
            'confidence'      => (float)($data['confidence'] ?? 0),
            'lineItems'       => $lineItems,
        ],
    ];
}

/**
 * Coerce a taxes/discounts array into clean [{name, amount}] entries.
 *
 * @param mixed $arr
 * @return array
 */
function receipt_scan_clean_name_amount($arr): array
{
    if (!is_array($arr)) {
        return [];
    }
    $out = [];
    foreach ($arr as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $out[] = [
            'name'   => (string)($entry['name'] ?? ''),
            'amount' => (float)($entry['amount'] ?? 0),
        ];
    }
    return $out;
}
