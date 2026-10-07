<?php
/**
 * PayPal Webhook for Portal Payments
 *
 * POST /api/portal/webhooks/paypal
 *
 * Originally the backup confirmation channel for PayPal portal invoice
 * payments. Portal-side PayPal is currently disabled (checkout.php,
 * process-payment.php, and connect.php all return 503 PROVIDER_UNSUPPORTED;
 * the customer-facing invoice page does not render a PayPal button), so no
 * new PayPal portal payments are created. This handler therefore only fires
 * for any legacy in-flight payments from before the disable. See CLAUDE.md
 * (Payment gateways section) for the disablement rationale.
 */

require_once __DIR__ . '/../portal-helper.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit;
}

$is_production = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production';

// Verify webhook signature with PayPal. Split sandbox/live keys mirror
// the API-credential pair below so a single .env can carry both.
$webhookId = $is_production
    ? ($_ENV['PAYPAL_PORTAL_LIVE_WEBHOOK_ID'] ?? '')
    : ($_ENV['PAYPAL_PORTAL_SANDBOX_WEBHOOK_ID'] ?? '');
if (empty($webhookId)) {
    error_log('Portal PayPal webhook: No webhook ID configured (env: ' . ($is_production ? 'production' : 'sandbox') . ')');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Webhook not configured']);
    exit;
}

// PayPal webhook verification
$paypalClientId = $is_production
    ? ($_ENV['PAYPAL_LIVE_CLIENT_ID'] ?? '')
    : ($_ENV['PAYPAL_SANDBOX_CLIENT_ID'] ?? '');
$paypalSecret = $is_production
    ? ($_ENV['PAYPAL_LIVE_CLIENT_SECRET'] ?? '')
    : ($_ENV['PAYPAL_SANDBOX_CLIENT_SECRET'] ?? '');
$paypalBaseUrl = $is_production
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

// Get access token
$ch = curl_init("$paypalBaseUrl/v1/oauth2/token");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
    CURLOPT_USERPWD => "$paypalClientId:$paypalSecret",
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$response = curl_exec($ch);
if ($response === false) {
    // 503 forces PayPal to retry; acknowledging without verification would let
    // a forged payload through whenever the token endpoint is briefly unhealthy.
    error_log('PayPal token request failed: ' . curl_error($ch));
    http_response_code(503);
    exit;
}
$tokenResponse = json_decode($response, true);

if (empty($tokenResponse['access_token'])) {
    // Same reasoning: no token → no signature verification → don't ack.
    error_log('Portal PayPal webhook: Failed to get access token');
    http_response_code(503);
    exit;
}

// Verify the webhook event
$verifyBody = json_encode([
    'auth_algo' => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
    'cert_url' => $_SERVER['HTTP_PAYPAL_CERT_URL'] ?? '',
    'transmission_id' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
    'transmission_sig' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '',
    'transmission_time' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '',
    'webhook_id' => $webhookId,
    'webhook_event' => $data,
]);

$ch = curl_init("$paypalBaseUrl/v1/notifications/verify-webhook-signature");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $verifyBody,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$tokenResponse['access_token']}",
        "Content-Type: application/json"
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$verifyRaw = curl_exec($ch);
if ($verifyRaw === false) {
    // Network failure during verification: 503 forces a retry rather than either acknowledging an unverified payload or 400-ing a payload that might actually be legitimate.
    error_log('Portal PayPal webhook: verify-webhook-signature request failed: ' . curl_error($ch));
    http_response_code(503);
    exit;
}
$verifyResponse = json_decode($verifyRaw, true);

if (($verifyResponse['verification_status'] ?? '') !== 'SUCCESS') {
    error_log('Portal PayPal webhook: Signature verification failed');
    http_response_code(400);
    exit;
}

// Handle the event
$eventType = $data['event_type'] ?? '';

switch ($eventType) {
    case 'PAYMENT.CAPTURE.COMPLETED':
        // Payment completed - this is handled by process-payment.php
        // This webhook serves as a backup confirmation
        $resource = $data['resource'] ?? [];
        error_log('Portal PayPal webhook: Payment capture completed for order ' . ($resource['id'] ?? 'unknown'));

        $captureAmount = floatval($resource['amount']['value'] ?? 0);
        $captureCurrency = strtoupper($resource['amount']['currency_code'] ?? 'USD');
        $captureId = $resource['id'] ?? '';
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? '';
        $invoiceRef = $resource['invoice_id'] ?? ($resource['custom_id'] ?? '');

        if (!empty($captureId) && $captureAmount > 0 && !empty($invoiceRef)) {
            // Look up the invoice by invoice_id to get the company_id
            $stmt = $pdo->prepare(
                'SELECT company_id, invoice_id, customer_name FROM portal_invoices WHERE invoice_id = ? LIMIT 1'
            );
            $stmt->execute([$invoiceRef]);
            $invoiceRecord = $stmt->fetch();

            if ($invoiceRecord) {
                record_portal_payment([
                    'company_id' => (int) $invoiceRecord['company_id'],
                    'invoice_id' => $invoiceRecord['invoice_id'],
                    'customer_name' => $invoiceRecord['customer_name'] ?? '',
                    'amount' => $captureAmount,
                    'currency' => $captureCurrency,
                    'payment_method' => 'paypal',
                    'provider_payment_id' => $orderId ?: $captureId,
                    'provider_transaction_id' => $captureId,
                    'reference_number' => generate_reference_number(),
                    'status' => 'completed',
                    'payment_environment' => $is_production ? 'production' : 'sandbox',
                ]);
            }
        }
        break;

    case 'PAYMENT.CAPTURE.REFUNDED':
        // The resource is the capture itself; the refund metadata is in resource.links / resource.note_to_payer / resource.invoice_id.
        error_log('Portal PayPal webhook: Payment refunded for capture ' . ($data['resource']['id'] ?? 'unknown'));

        // Insert negative-amount portal_payments row + flip original (mirrors the Stripe webhook behavior).
        try {
            // On PAYMENT.CAPTURE.REFUNDED the resource is the refund and links[rel=up] is its capture; resource.id covers older webhook formats.
            $refundAmount = (float)($data['resource']['amount']['value'] ?? 0);
            $refundCurrency = strtoupper($data['resource']['amount']['currency_code'] ?? 'USD');
            $invoiceTag = $data['resource']['invoice_id'] ?? '';

            $refundId = $data['resource']['id'] ?? null;
            $captureId = null;
            foreach (($data['resource']['links'] ?? []) as $lnk) {
                if (($lnk['rel'] ?? '') === 'up' && !empty($lnk['href'])) {
                    $parts = explode('/', rtrim($lnk['href'], '/'));
                    $captureId = end($parts) ?: null;
                    break;
                }
            }
            if (empty($captureId)) {
                // Best-effort fallback for payloads without an 'up' link.
                $captureId = $data['resource']['supplementary_data']['related_ids']['capture_id']
                    ?? $refundId;
            }

            // Find original payment by capture id (provider_transaction_id) OR by order id (provider_payment_id).
            $stmt = $pdo->prepare("
                SELECT * FROM portal_payments
                WHERE (provider_transaction_id = ? OR provider_payment_id = ?)
                  AND amount > 0
                LIMIT 1
            ");
            $stmt->execute([$captureId, $captureId]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($original) {
                // Keyed by the refund's own id, because keying by the capture collides on a second partial refund.
                $refundProviderPaymentId = 'refund_' . ($refundId ?: $captureId);
                $recordResult = record_portal_payment([
                    'company_id' => $original['company_id'],
                    'invoice_id' => $original['invoice_id'],
                    'customer_name' => $original['customer_name'],
                    'amount' => -$refundAmount,
                    'currency' => $refundCurrency,
                    'payment_method' => 'paypal',
                    'provider_payment_id' => $refundProviderPaymentId,
                    'provider_transaction_id' => $captureId,
                    'reference_number' => generate_reference_number(),
                    'status' => 'refunded',
                    // Inherit the original payment's environment so a misconfigured APP_ENV can't tag a refund with a different env than the payment it offsets.
                    'payment_environment' => $original['payment_environment'] ?? ($is_production ? 'production' : 'sandbox'),
                ]);
                if (!empty($recordResult['inserted'])) {
                    // The cumulative refund total is scoped to this capture, so a refund on a sibling cannot flip the wrong payment.
                    $sumStmt = $pdo->prepare(
                        "SELECT COALESCE(SUM(amount), 0) AS refunded_total
                         FROM portal_payments
                         WHERE amount < 0 AND payment_method = 'paypal'
                           AND provider_transaction_id = ?"
                    );
                    $sumStmt->execute([$captureId]);
                    // Compare in integer cents so a chain of partial refunds
                    // can't drift past the threshold via repeated float rounding.
                    $refundedCents = (int)round(abs((float)$sumStmt->fetch()['refunded_total']) * 100);
                    $originalCents = (int)round((float)$original['amount'] * 100);
                    if ($refundedCents >= $originalCents) {
                        $pdo->prepare("UPDATE portal_payments SET status = 'refunded' WHERE id = ?")
                            ->execute([$original['id']]);
                    }

                    // Update invoice balance/status. SET-clause order matters: status CASE must see pre-update balance_due. Mirrors the Stripe path.
                    $pdo->prepare(
                        'UPDATE portal_invoices
                         SET status = CASE
                                 WHEN balance_due + ? >= total_amount THEN "sent"
                                 ELSE "partial"
                             END,
                             balance_due = LEAST(total_amount, balance_due + ?),
                             updated_at = NOW()
                         WHERE company_id = ? AND invoice_id = ?'
                    )->execute([$refundAmount, $refundAmount, $original['company_id'], $original['invoice_id']]);
                }

                // Reconcile refund_requests if argo_request_id is in invoice_id
                if (preg_match('/^argo_request_(\d+)$/', $invoiceTag, $m)) {
                    require_once __DIR__ . '/../_audit.php';
                    require_once __DIR__ . '/../_refund_helpers.php';
                    $argoId = (int)$m[1];
                    $rstmt = $pdo->prepare("SELECT * FROM refund_requests WHERE id = ?");
                    $rstmt->execute([$argoId]);
                    $rr = $rstmt->fetch(PDO::FETCH_ASSOC);
                    if ($rr && $rr['state'] !== 'completed' && $rr['state'] !== 'cancelled') {
                        // Only the UPDATE that actually flips the state notifies, so a race with the synchronous path cannot send two completion emails.
                        $upd = $pdo->prepare("UPDATE refund_requests SET state='completed', provider_refund_id = ?, completed_at = NOW(), cancel_token = NULL, updated_at = NOW() WHERE id = ? AND state IN ('processing','cooling_off')");
                        $upd->execute([$refundId, $argoId]);
                        if ($upd->rowCount() > 0) {
                            audit_log($pdo, (int)$rr['company_id'], 'completed', 'webhook', null, $argoId, null, [
                                'provider_refund_id' => $refundId,
                                'reconciled_via_webhook' => true,
                            ]);
                            $rr['state'] = 'completed';
                            $rr['provider_refund_id'] = $refundId;
                            refund_notify_completion($pdo, $rr);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('PAYPAL refund handler: ' . $e->getMessage());
        }
        break;

    default:
        // Acknowledge unknown events
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
