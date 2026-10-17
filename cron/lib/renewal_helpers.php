<?php
declare(strict_types=1);

/**
 * Pure helpers extracted from subscription_renewal.php so the renewal logic
 * can be exercised without running the cron's top-level dispatch loop.
 *
 * - calculateNewEndDate: date math with a "stale end_date" guard
 * - decide_renewal_charge: credit-balance decision tree returning the
 *   billed amount including the processing fee
 * - recently_renewed: 23-hour idempotency guard against double-charges
 */

require_once __DIR__ . '/../../config/pricing.php';

/**
 * Calculate new subscription end date.
 *
 * Bases the new period on the LATER of the existing end_date or NOW(). When a
 * cron run is delayed and end_date is already in the past, extending from the
 * stale end_date can leave the new end_date still in the past, causing the
 * subscription to be picked up again and re-charged on the next run.
 */
function calculateNewEndDate($currentEndDate, $billing) {
    $endDateTime = new DateTime($currentEndDate);
    $now = new DateTime('now');
    if ($endDateTime < $now) {
        $endDateTime = $now;
    }

    if ($billing === 'yearly') {
        $endDateTime->add(new DateInterval('P1Y'));
    } else {
        $endDateTime->add(new DateInterval('P1M'));
    }

    return $endDateTime->format('Y-m-d H:i:s');
}

/**
 * Reconcile the checkout placeholder for a PayPal subscription's initial
 * capture.
 *
 * At checkout, process-subscription.php records the first PayPal payment
 * immediately, using the PayPal subscription id as the placeholder
 * transaction_id because the real sale id doesn't exist until PayPal actually
 * captures the money. When PayPal later reports that first sale via the
 * PAYMENT.SALE.COMPLETED webhook, its real id should REPLACE the placeholder
 * rather than create a second, phantom "renewal" row.
 *
 * Returns true if a placeholder row existed for this subscription (and now
 * carries $realTxnId), false if there was nothing to reconcile — in which case
 * the caller should treat the sale as a genuine new payment.
 */
function reconcile_paypal_initial_capture(PDO $pdo, string $subscriptionId, string $paypalSubscriptionId, string $realTxnId): bool
{
    $find = $pdo->prepare(
        "SELECT id FROM premium_subscription_payments
         WHERE subscription_id = ? AND transaction_id = ? AND payment_type = 'initial'
         LIMIT 1"
    );
    $find->execute([$subscriptionId, $paypalSubscriptionId]);
    $rowId = $find->fetchColumn();

    if ($rowId === false) {
        return false;
    }

    // The `transaction_id <> ?` guard makes a re-run a harmless no-op once the
    // real id is already attached.
    $upd = $pdo->prepare(
        "UPDATE premium_subscription_payments
         SET transaction_id = ?
         WHERE id = ? AND transaction_id <> ?"
    );
    $upd->execute([$realTxnId, $rowId, $realTxnId]);

    return true;
}

/**
 * Decide how much to charge for a renewal given the customer's credit balance.
 *
 * Returns:
 *   useCredit:       true only when credit fully covers the renewal
 *   creditUsed:      amount of credit consumed (0 when no credit)
 *   baseAmount:      pre-fee renewal price for the billing cycle
 *   amountToCharge:  final amount to charge the card, INCLUDING the
 *                     processing fee. 0 when fully credit-covered.
 *
 * The processing fee is intentionally added inside this helper so the call
 * site reflects the same one-shot decision the cron already makes, the
 * single source of truth for "what does the card see this cycle?".
 */
function decide_renewal_charge(float $creditBalance, string $billing, array $pricingConfig): array
{
    $baseAmount = ($billing === 'yearly')
        ? (float) $pricingConfig['premium_yearly_price']
        : (float) $pricingConfig['premium_monthly_price'];

    $useCredit = false;
    $creditUsed = 0.0;
    $amountToCharge = $baseAmount;

    if ($creditBalance > 0) {
        if ($creditBalance >= $baseAmount) {
            $useCredit = true;
            $creditUsed = $baseAmount;
            $amountToCharge = 0.0;
        } else {
            $creditUsed = $creditBalance;
            $amountToCharge = $baseAmount - $creditBalance;
        }
    }

    if ($amountToCharge > 0) {
        $amountToCharge += calculate_processing_fee($amountToCharge);
    }

    return [
        'useCredit' => $useCredit,
        'creditUsed' => $creditUsed,
        'baseAmount' => $baseAmount,
        'amountToCharge' => $amountToCharge,
    ];
}

/**
 * Idempotency guard for the renewal cron: returns true if a successful
 * renewal payment has already landed for this subscription within the last
 * 23 hours. Protects against double-charges when the cron fires twice
 * (overlapping runs, manual retries, accidental re-invokes).
 */
function recently_renewed(PDO $pdo, string $subscriptionId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM premium_subscription_payments
         WHERE subscription_id = ?
           AND status = 'completed'
           AND payment_type IN ('renewal', 'credit')
           AND created_at > DATE_SUB(NOW(), INTERVAL 23 HOUR)
         LIMIT 1"
    );
    $stmt->execute([$subscriptionId]);
    return $stmt->fetch() !== false;
}

/** Square refuses an idempotency key longer than 45 characters, and a refused key fails the whole payment. */
function square_idempotency_key(string $seed): string
{
    return substr(hash('sha256', $seed), 0, 45);
}

/** The reference_id a renewal charge carries, so a later run can find a charge whose record never reached the database. Square allows 40 characters. */
function square_renewal_reference(string $subscriptionId, string $periodEnd): string
{
    return substr(hash('sha256', 'renew|' . $subscriptionId . '|' . $periodEnd), 0, 40);
}

function square_api_base_url(string $environment): string
{
    return $environment === 'production'
        ? 'https://connect.squareup.com/v2'
        : 'https://connect.squareupsandbox.com/v2';
}

/** Sends one request to Square's API and returns [HTTP status, decoded JSON body or null]. */
function square_api_request(string $method, string $url, ?array $body, string $accessToken): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Square-Version: 2026-01-22',
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return [$status, is_array($decoded) ? $decoded : null];
}

/** Charges a card saved on a Square customer for the renewal cron, the failed-payment retry and the cycle switch; $send replaces square_api_request() in tests. */
function square_charge_saved_card(
    string $cardId,
    float $amount,
    string $idempotencyKey,
    string $note,
    string $accessToken,
    string $environment,
    ?string $referenceId = null,
    ?callable $send = null
): array {
    $send ??= 'square_api_request';

    // The card id goes into a URL path, so anything but Square's id characters could point the request somewhere else.
    if (!preg_match('/^[A-Za-z0-9\-_:]+$/', $cardId)) {
        return ['success' => false, 'error' => 'Invalid card ID format'];
    }
    if ($idempotencyKey === '' || strlen($idempotencyKey) > 45) {
        return ['success' => false, 'error' => 'Idempotency key must be 1 to 45 characters'];
    }

    $base = square_api_base_url($environment);
    $locationId = $environment === 'production'
        ? ($_ENV['SQUARE_LIVE_LOCATION_ID'] ?? '')
        : ($_ENV['SQUARE_SANDBOX_LOCATION_ID'] ?? '');

    // Square refuses a saved card without the customer it belongs to, and only the card record says which customer that is.
    [$cardStatus, $cardBody] = $send('GET', "$base/cards/$cardId", null, $accessToken);
    $customerId = $cardBody['card']['customer_id'] ?? null;
    if ($cardStatus < 200 || $cardStatus >= 300 || empty($customerId)) {
        return ['success' => false, 'error' => $cardBody['errors'][0]['detail'] ?? 'Failed to retrieve card details'];
    }

    $payment = [
        'idempotency_key' => $idempotencyKey,
        'source_id'       => $cardId,
        'customer_id'     => $customerId,
        'amount_money'    => [
            'amount'   => (int) round($amount * 100),
            'currency' => 'CAD',
        ],
        'location_id'     => $locationId,
        'note'            => $note,
        'autocomplete'    => true,
    ];
    if ($referenceId !== null) {
        $payment['reference_id'] = $referenceId;
    }

    [$status, $body] = $send('POST', "$base/payments", $payment, $accessToken);
    if ($status < 200 || $status >= 300 || empty($body['payment']['id'])) {
        return ['success' => false, 'error' => $body['errors'][0]['detail'] ?? 'Payment failed'];
    }
    if (($body['payment']['status'] ?? '') !== 'COMPLETED') {
        return ['success' => false, 'error' => 'Payment not completed: ' . ($body['payment']['status'] ?? 'unknown')];
    }

    return ['success' => true, 'transaction_id' => $body['payment']['id']];
}

/** The completed Square payment since $sinceUtc carrying $referenceId, as its id and amount, or null; throws when Square cannot be asked, so the caller decides whether to charge anyway. */
function square_find_payment_by_reference(
    string $referenceId,
    DateTimeInterface $sinceUtc,
    string $accessToken,
    string $environment,
    ?callable $send = null
): ?array {
    $send ??= 'square_api_request';
    $locationId = $environment === 'production'
        ? ($_ENV['SQUARE_LIVE_LOCATION_ID'] ?? '')
        : ($_ENV['SQUARE_SANDBOX_LOCATION_ID'] ?? '');

    $query = [
        'begin_time'  => (new DateTimeImmutable('@' . $sinceUtc->getTimestamp()))->format('Y-m-d\TH:i:s\Z'),
        'location_id' => $locationId,
        'limit'       => 100,
    ];
    // Ten pages is a thousand payments in a few days, far more than this location takes.
    for ($page = 0; $page < 10; $page++) {
        [$status, $body] = $send('GET', square_api_base_url($environment) . '/payments?' . http_build_query($query), null, $accessToken);
        if ($status < 200 || $status >= 300 || !is_array($body)) {
            throw new RuntimeException('Square payment lookup failed: ' . ($body['errors'][0]['detail'] ?? "HTTP $status"));
        }
        foreach ($body['payments'] ?? [] as $payment) {
            if (($payment['reference_id'] ?? null) !== $referenceId || ($payment['status'] ?? '') !== 'COMPLETED') {
                continue;
            }
            // A charge refunded in full no longer pays for the period.
            $refunded = (int) ($payment['refunded_money']['amount'] ?? 0);
            if ($refunded > 0 && $refunded >= (int) ($payment['amount_money']['amount'] ?? 0)) {
                continue;
            }
            return ['id' => $payment['id'], 'amount' => ((int) ($payment['amount_money']['amount'] ?? 0)) / 100];
        }
        if (empty($body['cursor'])) {
            return null;
        }
        $query['cursor'] = $body['cursor'];
    }
    throw new RuntimeException('Square payment lookup stopped after 10 pages without reaching the end');
}

/** The Stripe search that finds a succeeded renewal charge for one subscription and billing period. */
function stripe_renewal_search_query(string $subscriptionId, string $periodEnd): string
{
    $quote = fn(string $v): string => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $v) . "'";
    return "metadata['subscription_id']:" . $quote($subscriptionId)
        . " AND metadata['renewal_period']:" . $quote($periodEnd)
        . " AND status:'succeeded'";
}

/** The succeeded Stripe renewal charge for this subscription and period, as its id and amount, or null; $search replaces PaymentIntent::search() in tests. */
function stripe_find_paid_renewal(string $subscriptionId, string $periodEnd, ?callable $search = null): ?array
{
    $search ??= fn(array $params) => \Stripe\PaymentIntent::search($params);
    $result = $search([
        'query'  => stripe_renewal_search_query($subscriptionId, $periodEnd),
        'limit'  => 10,
        'expand' => ['data.latest_charge'],
    ]);
    foreach ($result->data ?? [] as $paymentIntent) {
        // A charge refunded in full no longer pays for the period.
        if (($paymentIntent->latest_charge->refunded ?? false) === true) {
            continue;
        }
        return ['id' => $paymentIntent->id, 'amount' => ((int) ($paymentIntent->amount_received ?? $paymentIntent->amount ?? 0)) / 100];
    }
    return null;
}
