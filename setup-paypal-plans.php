<?php
/**
 * PayPal Subscription Plans Setup Script
 *
 * Run this script ONCE to create the subscription plans in PayPal.
 * After running, copy the Plan IDs to your .env file.
 *
 * Usage:
 *   php setup-paypal-plans.php
 *
 * This will create:
 *   1. A Product (Argo Books Premium Subscription)
 *   2. A Monthly Plan (configured in pricing.php)
 *   3. A Yearly Plan (configured in pricing.php)
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI.');
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/pricing.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pricing = get_pricing_config();
$monthlyBase = $pricing['premium_monthly_price'];
$yearlyBase  = $pricing['premium_yearly_price'];

// PayPal bills the plan's fixed_price with no fee added, but the card flow (Stripe/Square) charges base + processing fee, and every receipt prints base + fee.
$monthlyPrice = $monthlyBase + calculate_processing_fee($monthlyBase);
$yearlyPrice  = $yearlyBase + calculate_processing_fee($yearlyBase);

echo "=== PayPal Subscription Plans Setup ===\n\n";

// Configuration
$isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';
$environment = $isProduction ? 'PRODUCTION' : 'SANDBOX';

echo "Environment: $environment\n\n";

$clientId = $isProduction
    ? $_ENV['PAYPAL_LIVE_CLIENT_ID']
    : $_ENV['PAYPAL_SANDBOX_CLIENT_ID'];

$clientSecret = $isProduction
    ? $_ENV['PAYPAL_LIVE_CLIENT_SECRET']
    : $_ENV['PAYPAL_SANDBOX_CLIENT_SECRET'];

$baseUrl = $isProduction
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

if (empty($clientId) || empty($clientSecret)) {
    die("ERROR: PayPal credentials not configured in .env file\n");
}

echo "Getting access token...\n";
$accessToken = getAccessToken($baseUrl, $clientId, $clientSecret);
if (!$accessToken) {
    die("ERROR: Failed to get access token\n");
}
echo "Access token obtained.\n\n";

// Step 1: Create Product
echo "Step 1: Creating Product...\n";
$product = createProduct($baseUrl, $accessToken);
if (!$product) {
    die("ERROR: Failed to create product\n");
}
$productId = $product['id'];
echo "Product created: $productId\n\n";

// Step 2: Create Monthly Plan
echo "Step 2: Creating Monthly Plan (\${$monthlyPrice} CAD/month)...\n";
$monthlyPlan = createPlan($baseUrl, $accessToken, $productId, 'monthly', $monthlyPrice);
if (!$monthlyPlan) {
    die("ERROR: Failed to create monthly plan\n");
}
$monthlyPlanId = $monthlyPlan['id'];
echo "Monthly Plan created: $monthlyPlanId\n\n";

// Step 3: Create Yearly Plan
echo "Step 3: Creating Yearly Plan (\${$yearlyPrice} CAD/year)...\n";
$yearlyPlan = createPlan($baseUrl, $accessToken, $productId, 'yearly', $yearlyPrice);
if (!$yearlyPlan) {
    die("ERROR: Failed to create yearly plan\n");
}
$yearlyPlanId = $yearlyPlan['id'];
echo "Yearly Plan created: $yearlyPlanId\n\n";

// Output results
echo "===========================================\n";
echo "SUCCESS! Add these to your .env file:\n";
echo "===========================================\n\n";

if ($isProduction) {
    echo "PAYPAL_LIVE_MONTHLY_PLAN_ID=\"$monthlyPlanId\"\n";
    echo "PAYPAL_LIVE_YEARLY_PLAN_ID=\"$yearlyPlanId\"\n";
} else {
    echo "PAYPAL_SANDBOX_MONTHLY_PLAN_ID=\"$monthlyPlanId\"\n";
    echo "PAYPAL_SANDBOX_YEARLY_PLAN_ID=\"$yearlyPlanId\"\n";
}


// === Helper Functions ===

function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init("$baseUrl/v1/oauth2/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US',
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_USERPWD => "$clientId:$clientSecret"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        echo "Token Error: HTTP $httpCode\n$response\n";
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function createProduct($baseUrl, $accessToken) {
    $productData = [
        'name' => 'Argo Books Premium Subscription',
        'description' => 'Higher monthly limits for receipt scanning, spreadsheet imports, and bank statement imports, plus unlimited invoicing, revenue forecasting, biometric sign-in, and priority support.',
        'type' => 'SERVICE',
        'category' => 'SOFTWARE',
        'home_url' => 'https://argorobots.com/pricing/premium/'
    ];

    $ch = curl_init("$baseUrl/v1/catalogs/products");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($productData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken",
            'PayPal-Request-Id: ' . uniqid('product_', true)
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 201) {
        echo "Product Error: HTTP $httpCode\n$response\n";
        return null;
    }

    return json_decode($response, true);
}

function createPlan($baseUrl, $accessToken, $productId, $interval, $price) {
    $intervalUnit = $interval === 'yearly' ? 'YEAR' : 'MONTH';
    $planName = $interval === 'yearly' ? 'Yearly' : 'Monthly';

    $planData = [
        'product_id' => $productId,
        'name' => "Argo Books Premium - $planName",
        'description' => "Argo Books Premium Subscription - $planName billing",
        'status' => 'ACTIVE',
        'billing_cycles' => [
            [
                'frequency' => [
                    'interval_unit' => $intervalUnit,
                    'interval_count' => 1
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0, // 0 = infinite
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => number_format($price, 2, '.', ''),
                        'currency_code' => 'CAD'
                    ]
                ]
            ]
        ],
        'payment_preferences' => [
            'auto_bill_outstanding' => true,
            'setup_fee_failure_action' => 'CONTINUE',
            'payment_failure_threshold' => 3
        ]
    ];

    $ch = curl_init("$baseUrl/v1/billing/plans");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($planData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken",
            'PayPal-Request-Id: ' . uniqid('plan_', true)
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 201) {
        echo "Plan Error: HTTP $httpCode\n$response\n";
        return null;
    }

    return json_decode($response, true);
}
