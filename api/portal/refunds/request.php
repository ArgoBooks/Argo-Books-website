<?php
declare(strict_types=1);

/**
 * POST /api/portal/refunds/request.php
 *
 * Body: { invoice_id, invoice_number, customer_name?, provider, provider_payment_id,
 *         amount_cents, currency, line_items?, reason? }
 * Headers: Authorization: Bearer <api_key>, Idempotency-Key: <uuid>
 *
 * Creates a refund_request in state pending_code, generates a 6-digit verification
 * code, hashes it, and emails it to the company's owner_email. The desktop submits
 * the code via /confirm.php to advance the state machine.
 */

require_once __DIR__ . '/../portal-helper.php';
require_once __DIR__ . '/../_audit.php';
require_once __DIR__ . '/../_idempotency.php';
require_once __DIR__ . '/../_refund_helpers.php';
require_once __DIR__ . '/_provider_stripe.php';

set_portal_headers();
require_method(['POST']);

$company = authenticate_portal_request();
if (!$company) {
    send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
}
refund_ensure_company_active($company);

global $pdo;
$raw = file_get_contents('php://input') ?: '';

// Refund creation is a financial mutation, so a retry without the header could create a second request, a second code and a second provider call.
with_idempotency($pdo, (int)$company['id'], $raw, function() use ($pdo, $company, $raw) {
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'INVALID_JSON']);
        return;
    }

    foreach (['invoice_id','invoice_number','provider','provider_payment_id','amount_cents','currency'] as $f) {
        if (!isset($body[$f]) || $body[$f] === '' || $body[$f] === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'MISSING_FIELD', 'field' => $f]);
            return;
        }
    }

    $provider = strtolower((string)$body['provider']);
    if (!in_array($provider, ['stripe','paypal','square'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'UNSUPPORTED_PROVIDER']);
        return;
    }

    $amount_cents = (int)$body['amount_cents'];
    if ($amount_cents <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'INVALID_AMOUNT']);
        return;
    }

    // Provider-specific pre-flight (fails-fast and exits inside on error)
    switch ($provider) {
        case 'stripe':
            refund_stripe_preflight($company, (string)$body['provider_payment_id'], $amount_cents);
            break;
        case 'paypal':
            require_once __DIR__ . '/_provider_paypal.php';
            refund_paypal_preflight($company, (string)$body['provider_payment_id'], $amount_cents);
            break;
        case 'square':
            require_once __DIR__ . '/_provider_square.php';
            refund_square_preflight($company, (string)$body['provider_payment_id'], $amount_cents);
            break;
    }

    // Per-company hourly code-issue rate limit (10 codes / hour)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM refund_email_codes c
        INNER JOIN refund_requests r ON c.refund_request_id = r.id
        WHERE r.company_id = ? AND c.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$company['id']]);
    if ((int)$stmt->fetchColumn() >= 10) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'TOO_MANY_CODES',
            'message' => 'Too many verification codes requested in the past hour. Please wait.',
        ]);
        return;
    }

    $ctx = audit_request_context();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO refund_requests
                (company_id, invoice_id, invoice_number, customer_name, provider,
                 provider_payment_id, amount_cents, currency, line_items_json, reason,
                 state, requested_ip, requested_user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_code', ?, ?)
        ");
        $stmt->execute([
            $company['id'],
            $body['invoice_id'],
            $body['invoice_number'],
            $body['customer_name'] ?? null,
            $provider,
            $body['provider_payment_id'],
            $amount_cents,
            strtoupper((string)$body['currency']),
            isset($body['line_items']) ? json_encode($body['line_items']) : null,
            isset($body['reason']) ? substr((string)$body['reason'], 0, 500) : null,
            $ctx['ip'],
            $ctx['ua'],
        ]);
        $request_id = (int)$pdo->lastInsertId();

        $code = refund_generate_code();
        $hash = refund_hash_code($code, (string)$request_id);
        $stmt = $pdo->prepare("
            INSERT INTO refund_email_codes (refund_request_id, code_hash, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ");
        $stmt->execute([$request_id, $hash]);

        audit_log($pdo, (int)$company['id'], 'request_created', 'owner', null, $request_id, null, [
            'amount_cents' => $amount_cents,
            'provider' => $provider,
            'invoice_number' => $body['invoice_number'],
        ], $ctx['ip'], $ctx['ua']);
        audit_log($pdo, (int)$company['id'], 'code_sent', 'system', null, $request_id, null, [
            'masked_email' => refund_mask_email($company['owner_email']),
        ]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('refunds/request DB error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'SERVER_ERROR']);
        return;
    }

    // Send the code AFTER commit. SMTP failure shouldn't roll back the request;
    // the user can use /resend-code if delivery fails.
    refund_email_send_code($company['owner_email'], $code, (string)$body['invoice_number'], $amount_cents, (string)$body['currency']);

    echo json_encode([
        'success' => true,
        'requestId' => $request_id,
        'expiresInSeconds' => 600,
        'maskedEmail' => refund_mask_email($company['owner_email']),
    ]);
}, true);
