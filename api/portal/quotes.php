<?php
/**
 * Portal Quotes API Endpoint
 *
 * POST /api/portal/quotes - Publish or update a quote from Argo Books, and optionally email it.
 *
 * Requires the portal API key. A licence key in X-License-Key raises the send ceiling from the
 * free tier's to Premium's; the request still works without one.
 */

require_once __DIR__ . '/quote-helper.php';

set_portal_headers();
require_method(['POST']);

handle_publish_quote();

/**
 * Sending is capped per identity and, on the free tier, per IP as well. There is no monthly
 * quota: quotes are "unlimited" on every plan, and these limits exist so the address this site
 * sends from cannot be used to spam.
 */
function enforce_quote_send_limits(int $companyId): void
{
    $license = authenticate_license_request();

    if ($license) {
        $identity = 'quote_email_' . ($license['license_key_hash'] ?? get_client_ip());
        if (rate_limit_hit('quote_email', $identity, 'quote_email')) {
            send_rate_limited_response('quote_email');
        }
        return;
    }

    if (rate_limit_hit('quote_email_company', 'quote_email_co_' . $companyId, 'quote_email')) {
        send_rate_limited_response('quote_email_company');
    }

    // An API key is cheap to get on the free tier, so the IP is what actually bounds how much
    // mail one origin can send.
    $clientIp = get_client_ip();
    if (rate_limit_hit('quote_email_ip', $clientIp)) {
        send_rate_limited_response('quote_email_ip');
    }
    if (rate_limit_hit('quote_email_ip_daily', $clientIp)) {
        send_rate_limited_response('quote_email_ip_daily');
    }
}

/**
 * POST: Publish or update a quote from Argo Books
 */
function handle_publish_quote(): void
{
    $company = authenticate_portal_request();
    if (!$company) {
        send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error_response(400, 'Invalid JSON: ' . json_last_error_msg(), 'INVALID_JSON');
    }

    $required = ['quoteId', 'customerName', 'totalAmount'];
    $missing = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        send_error_response(400, 'Missing required fields: ' . implode(', ', $missing), 'MISSING_FIELDS');
    }

    global $pdo;
    $companyId = (int) $company['id'];
    $quoteId = quote_clean($data['quoteId'], 100);
    $customerName = quote_clean($data['customerName'], 255);
    $customerEmail = quote_clean($data['customerEmail'] ?? '', 254);
    $totalAmount = (float) $data['totalAmount'];
    $currency = strtoupper(preg_replace('/[^A-Za-z]/', '', $data['currency'] ?? 'USD') ?: 'USD');
    $validUntil = !empty($data['validUntil']) ? date('Y-m-d', strtotime((string) $data['validUntil'])) : null;
    $incomingStatus = strtolower(quote_clean($data['status'] ?? 'sent', 20));
    if (!in_array($incomingStatus, ['sent', 'cancelled'], true)) {
        $incomingStatus = 'sent';
    }
    $sendEmail = filter_var($data['sendEmail'] ?? false, FILTER_VALIDATE_BOOLEAN);
    // Set only when the user has been asked and confirmed they are replacing a quote the
    // customer already answered.
    $isRevision = filter_var($data['revision'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($sendEmail) {
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            send_error_response(400, 'A valid customer email is required to send a quote.', 'INVALID_EMAIL');
        }
        $domain = substr(strrchr($customerEmail, '@'), 1);
        if (!checkdnsrr($domain, 'MX')) {
            send_error_response(400, 'No mail servers found for ' . $domain, 'INVALID_DOMAIN');
        }
        enforce_quote_send_limits($companyId);
    }

    $stmt = $pdo->prepare(
        'SELECT id, quote_token, status, synced_to_argo, responded_at, response_note
         FROM portal_quotes WHERE company_id = ? AND quote_id = ? LIMIT 1'
    );
    $stmt->execute([$companyId, $quoteId]);
    $existing = $stmt->fetch() ?: null;

    $quoteToken = $existing['quote_token'] ?? generate_portal_token();
    $decision = quote_publish_status($existing, $incomingStatus, $isRevision);
    $status = $decision['status'];

    $quoteData = json_encode($data['quoteData'] ?? $data);

    try {
        if ($existing) {
            $sql = 'UPDATE portal_quotes SET
                        customer_name = ?, customer_email = ?, quote_data = ?, status = ?,
                        total_amount = ?, currency = ?, valid_until = ?, updated_at = NOW()';
            $params = [$customerName, $customerEmail, $quoteData, $status,
                       $totalAmount, $currency, $validUntil];
            if ($decision['clearResponse']) {
                $sql .= ', responded_at = NULL, response_note = NULL, synced_to_argo = 1';
            }
            $sql .= ' WHERE company_id = ? AND quote_id = ?';
            $params[] = $companyId;
            $params[] = $quoteId;
            $pdo->prepare($sql)->execute($params);
        } else {
            $pdo->prepare(
                'INSERT INTO portal_quotes
                 (company_id, quote_id, quote_token, customer_name, customer_email, quote_data,
                  status, total_amount, currency, valid_until, environment, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            )->execute([
                $companyId, $quoteId, $quoteToken, $customerName, $customerEmail, $quoteData,
                $status, $totalAmount, $currency, $validUntil, current_environment()
            ]);
        }
    } catch (\PDOException $e) {
        error_log('Portal quote DB error: ' . $e->getMessage());
        send_error_response(500, 'Failed to save quote. Please try again.', 'DB_ERROR');
    }

    $quoteUrl = site_url('/quote/' . $quoteToken);

    // Never let an email problem lose the publish: the quote is already saved and the link works.
    $emailSent = false;
    if ($sendEmail && $status === 'sent' && $incomingStatus === 'sent') {
        try {
            $result = send_quote_notification([
                'customerEmail' => $customerEmail,
                'customerName' => $customerName,
                'companyName' => $data['companyName'] ?? $company['company_name'],
                'quoteId' => $quoteId,
                'totalAmount' => $totalAmount,
                'currency' => $currency,
                'validUntil' => $validUntil,
                'quoteUrl' => $quoteUrl,
                'message' => $data['message'] ?? '',
                'replyTo' => $data['replyTo'] ?? '',
            ]);
            $emailSent = $result['success'];
        } catch (\Throwable $e) {
            error_log('Quote publish email failed: ' . $e->getMessage());
        }
    }

    send_json_response(200, [
        'success' => true,
        'quoteToken' => $quoteToken,
        'quoteUrl' => $quoteUrl,
        'status' => $status,
        'respondedAt' => $decision['clearResponse'] ? null : portal_iso_datetime($existing['responded_at'] ?? null),
        'responseNote' => $decision['clearResponse'] ? null : ($existing['response_note'] ?? null),
        'emailSent' => $emailSent,
        'message' => $existing ? 'Quote updated' : 'Quote published',
        'timestamp' => date('c')
    ]);
}
