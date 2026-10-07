<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Load environment variables so price overrides (PREMIUM_MONTHLY_PRICE, etc.) are applied.
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

require_once __DIR__ . '/../../config/pricing.php';

$pricing = get_pricing_config();
$plans = get_plan_features();
$monthlyPrice = $pricing['premium_monthly_price'];
$yearlyPrice = $pricing['premium_yearly_price'];
$yearlySavings = ($monthlyPrice * 12) - $yearlyPrice;
$currency = $pricing['currency'];

echo json_encode([
    'plans' => $plans,
    // The same figures appear inside the plan feature strings, but the desktop needs them as numbers to write its own sentences.
    'limits' => [
        'free_invoice_monthly_limit'      => (int) $pricing['free_invoice_monthly_limit'],
        'free_receipt_scan_monthly_limit' => (int) $pricing['free_receipt_scan_monthly_limit'],
    ],
    'pricing' => [
        'currency' => $currency,
        'premium_monthly_price' => $monthlyPrice,
        'premium_yearly_price' => $yearlyPrice,
        'premium_yearly_savings' => $yearlySavings,
        'premium_price_display' => '$' . number_format($monthlyPrice, 0) . ' ' . $currency,
        'premium_yearly_price_display' => '$' . number_format($yearlyPrice, 0) . ' ' . $currency,
        'premium_yearly_savings_display' => '$' . number_format($yearlySavings, 0),
    ],
]);
