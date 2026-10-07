<?php
declare(strict_types=1);

/**
 * PayPal refund provider adapter.
 *
 * The desktop stores the PayPal order_id as Payment.ProviderPaymentId. PayPal
 * refunds work against captures, not orders, so we resolve the order to its
 * primary capture before issuing the refund.
 */

function refund_paypal_token(array $company): string {
    $env = $company['environment'] ?? 'production';
    $base = $env === 'production' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    $client_id = $env === 'production'
        ? ($_ENV['PAYPAL_LIVE_CLIENT_ID'] ?? '')
        : ($_ENV['PAYPAL_SANDBOX_CLIENT_ID'] ?? '');
    $secret = $env === 'production'
        ? ($_ENV['PAYPAL_LIVE_CLIENT_SECRET'] ?? '')
        : ($_ENV['PAYPAL_SANDBOX_CLIENT_SECRET'] ?? '');
    if (empty($client_id) || empty($secret)) {
        throw new RuntimeException("PayPal credentials not configured for env: $env");
    }

    $ch = curl_init("$base/v1/oauth2/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => "$client_id:$secret",
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400) throw new RuntimeException("PayPal token failed (HTTP $code)");
    $data = json_decode($resp, true);
    if (empty($data['access_token'])) throw new RuntimeException('PayPal token missing in response');
    return $data['access_token'];
}

function refund_paypal_base(array $company): string {
    return ($company['environment'] ?? 'production') === 'production'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

/**
 * Resolve the primary capture ID for a PayPal order without exiting the
 * request on failure: throws RuntimeException instead of echoing JSON
 * and calling exit(). Used by background paths (cron, promoter) where
 * the synchronous preflight isn't appropriate.
 */
function refund_paypal_resolve_capture_id(array $company, string $order_id): string {
    if (!preg_match('/^[A-Za-z0-9\-]+$/', $order_id)) {
        throw new RuntimeException('paypal_invalid_order_id');
    }
    $token = refund_paypal_token($company);
    $base = refund_paypal_base($company);

    $ch = curl_init("$base/v2/checkout/orders/" . urlencode($order_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", "Content-Type: application/json"],
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 404) throw new RuntimeException("paypal_order_not_found ($order_id)");
    if ($code >= 400)  throw new RuntimeException("paypal_lookup_failed ($code)");

    $order = json_decode($resp, true);
    $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (!$capture || empty($capture['id'])) {
        throw new RuntimeException('paypal_no_capture');
    }
    return (string)$capture['id'];
}

/**
 * Fetch the order, extract the primary capture, validate refundability.
 * Echoes JSON + exits on failure. Returns ['capture_id' => string,
 * 'refundable_cents' => int, 'currency' => string].
 */
function refund_paypal_preflight(array $company, string $order_id, int $requested_cents): int {
    if (!preg_match('/^[A-Za-z0-9\-]+$/', $order_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'INVALID_ORDER_ID']);
        exit;
    }

    try {
        $token = refund_paypal_token($company);
        $base = refund_paypal_base($company);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'PAYPAL_AUTH_FAILED', 'message' => $e->getMessage()]);
        exit;
    }

    $ch = curl_init("$base/v2/checkout/orders/" . urlencode($order_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", "Content-Type: application/json"],
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 404) {
        http_response_code(404); echo json_encode(['success' => false, 'error' => 'PROVIDER_PAYMENT_NOT_FOUND']); exit;
    }
    if ($code >= 400) {
        http_response_code(502); echo json_encode(['success' => false, 'error' => 'PAYPAL_LOOKUP_FAILED', 'message' => $resp]); exit;
    }
    $order = json_decode($resp, true);
    $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (!$capture || empty($capture['id'])) {
        http_response_code(409); echo json_encode(['success' => false, 'error' => 'NO_CAPTURE']); exit;
    }
    $captureAmount = (float)($capture['amount']['value'] ?? 0);
    $alreadyRefunded = 0.0;
    foreach ($capture['seller_payable_breakdown']['total_refunded_amount'] ?? [] as $k => $v) {
        if ($k === 'value') $alreadyRefunded = (float)$v;
    }
    $refundable_cents = (int)round(($captureAmount - $alreadyRefunded) * 100);
    if ($requested_cents > $refundable_cents) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'AMOUNT_EXCEEDS_REFUNDABLE', 'refundable_cents' => $refundable_cents]);
        exit;
    }

    // Stash the capture id on the request session so refund_paypal_issue() can pick it up
    $GLOBALS['__paypal_preflight_capture_id'] = $capture['id'];
    return $refundable_cents;
}

function refund_paypal_issue(array $company, array $request): array {
    // A capture resolved by preflight is reused; a background caller ran preflight in another request, so it resolves one here instead.
    $capture_id = $GLOBALS['__paypal_preflight_capture_id'] ?? null;
    if (!$capture_id) {
        $capture_id = refund_paypal_resolve_capture_id($company, $request['provider_payment_id']);
    }

    $token = refund_paypal_token($company);
    $base = refund_paypal_base($company);

    $body = json_encode([
        'amount' => [
            'value' => number_format($request['amount_cents'] / 100, 2, '.', ''),
            'currency_code' => $request['currency'],
        ],
        'invoice_id' => 'argo_request_' . $request['id'],
        'note_to_payer' => substr((string)($request['reason'] ?? 'Refund'), 0, 255),
    ]);

    $ch = curl_init("$base/v2/payments/captures/" . urlencode($capture_id) . "/refund");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json",
            "PayPal-Request-Id: argo_request_" . $request['id'],
        ],
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($resp, true) ?: [];
    if ($code >= 400) {
        $msg = $data['message'] ?? substr((string)$resp, 0, 500);
        throw new RuntimeException("paypal_refund_failed ($code): $msg");
    }
    return $data; // contains 'id' = refund id
}
