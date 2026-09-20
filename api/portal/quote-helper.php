<?php

/**
 * Quotes on the customer portal.
 *
 * A quote is not money owed, so it lives in its own table rather than as a row in
 * portal_invoices: the reminder cron, the customer portal's outstanding totals and the admin
 * payment pages all read that table and would otherwise chase or count quotes.
 *
 * The customer's answer travels back to the desktop app the same way payments do: the answer
 * sets synced_to_argo = 0, the app pulls it and then confirms it.
 */

require_once __DIR__ . '/portal-helper.php';

/**
 * Strip control characters and cut a caller-supplied string to length. The desktop app is the
 * usual caller, but anyone with an API key can post, so nothing arrives trusted.
 */
function quote_clean(mixed $value, int $maxLength): string
{
    $clean = preg_replace('/[\r\n\x00-\x1F\x7F]+/u', ' ', (string) $value);

    return trim(mb_substr($clean ?? '', 0, $maxLength));
}

/**
 * The quote behind a customer link, or null. Environment-scoped: sandbox and production share
 * one database, and a token issued on dev must not open a live quote.
 */
function get_quote_by_token(string $token): ?array
{
    global $pdo;
    if ($pdo === null) {
        error_log('get_quote_by_token: database connection unavailable');
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT q.*, c.company_name, c.company_logo_url
             FROM portal_quotes q
             INNER JOIN portal_companies c ON c.id = q.company_id
             WHERE q.quote_token = ? AND q.environment = ?
             LIMIT 1'
        );
        $stmt->execute([$token, current_environment()]);
        $quote = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('get_quote_by_token: DB error: ' . $e->getMessage());
        return null;
    }

    return $quote ?: null;
}

/**
 * True once the valid-until date has passed. Stored nowhere: a quote with no date never expires.
 */
function quote_is_expired(array $quote): bool
{
    if (empty($quote['valid_until'])) {
        return false;
    }

    return strtotime($quote['valid_until'] . ' 23:59:59') < time();
}

/**
 * Whether the customer can still answer: only a sent quote, before it expires.
 */
function quote_can_respond(array $quote): bool
{
    return $quote['status'] === 'sent' && !quote_is_expired($quote);
}

/**
 * Record the customer's answer.
 *
 * The UPDATE carries the status guard so two clicks from the same page, or two people on the
 * same link, cannot both count: the second one matches no row.
 *
 * @return bool True when this call was the one that recorded the answer.
 */
function quote_record_response(array $quote, string $status, ?string $note = null): bool
{
    global $pdo;
    if ($pdo === null || !in_array($status, ['accepted', 'declined'], true)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE portal_quotes
             SET status = ?, response_note = ?, responded_at = NOW(), synced_to_argo = 0, updated_at = NOW()
             WHERE id = ? AND status = 'sent'"
        );
        $stmt->execute([$status, $note, $quote['id']]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('quote_record_response: DB error: ' . $e->getMessage());
        return false;
    }
}

/**
 * What a re-publish should do to a quote that already exists.
 *
 * An answer is the one thing on a quote the business cannot take back, so re-publishing never
 * clears it: a plain resend of an accepted quote returns the acceptance instead of reopening it.
 * Only $isRevision, which the app sends after asking the user, puts an answered quote back to
 * sent. Cancelling always wins: it is how a deleted quote stops accepting answers.
 *
 * @return array{status: string, clearResponse: bool}
 */
function quote_publish_status(?array $existing, string $incomingStatus, bool $isRevision = false): array
{
    if ($incomingStatus === 'cancelled') {
        return ['status' => 'cancelled', 'clearResponse' => false];
    }

    if ($existing === null) {
        return ['status' => 'sent', 'clearResponse' => true];
    }

    $answered = in_array($existing['status'], ['accepted', 'declined'], true);

    if ($answered && !$isRevision) {
        return ['status' => $existing['status'], 'clearResponse' => false];
    }

    return ['status' => 'sent', 'clearResponse' => true];
}

/**
 * Email the customer their quote.
 *
 * The body is written here rather than sent up by the app: a caller can only choose who it goes
 * to and a short note, which leaves nothing worth spamming with.
 */
function send_quote_notification(array $params): array
{
    $customerEmail = quote_clean($params['customerEmail'] ?? '', 254);
    $customerName = quote_clean($params['customerName'] ?? '', 120);
    $companyName = quote_clean($params['companyName'] ?? '', 120);
    $quoteId = quote_clean($params['quoteId'] ?? '', 100);
    $quoteUrl = (string) ($params['quoteUrl'] ?? '');
    $totalAmount = (float) ($params['totalAmount'] ?? 0);
    $currency = quote_clean($params['currency'] ?? 'USD', 3);
    $validUntil = $params['validUntil'] ?? '';
    $note = quote_clean($params['message'] ?? '', 500);
    $replyTo = quote_clean($params['replyTo'] ?? '', 254);

    if (empty($customerEmail) || empty($quoteUrl)) {
        return ['success' => false, 'message' => 'Missing customer email or quote URL'];
    }

    $currencySymbol = $currency === 'CAD' ? 'CA$' : '$';
    $formattedAmount = $currencySymbol . number_format($totalAmount, 2) . ' ' . $currency;
    $formattedValidUntil = $validUntil ? date('F j, Y', strtotime($validUntil)) : '';
    $safeCompany = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');

    $detailRows = [
        ['Quote', htmlspecialchars($quoteId, ENT_QUOTES, 'UTF-8'), 'padding: 8px 0; text-align: right; font-size: 14px; font-weight: 600; color: #111827;'],
        ['Total', htmlspecialchars($formattedAmount, ENT_QUOTES, 'UTF-8'), 'padding: 8px 0; text-align: right; font-size: 18px; font-weight: 700; color: #111827;'],
    ];
    if ($formattedValidUntil !== '') {
        $detailRows[] = ['Valid until', htmlspecialchars($formattedValidUntil, ENT_QUOTES, 'UTF-8')];
    }

    $introHtml = 'You have a new quote from <strong>' . $safeCompany . '</strong>.';
    if ($note !== '') {
        $introHtml .= '</p><p style="margin: 0 0 16px; padding: 12px 16px; background: #f9fafb; border-left: 3px solid #d1d5db; color: #374151;">'
            . nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8'));
    }

    $closingHtml = 'You can accept or decline the quote on that page.';
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $closingHtml .= ' Replies go to ' . $safeCompany . ' at '
            . htmlspecialchars($replyTo, ENT_QUOTES, 'UTF-8') . '.';
    }

    // The people who receive quotes send their own: the shared portal footer carries no source,
    // so the loop link goes here.
    $closingHtml .= '</p><p style="margin: 24px 0 0; font-size: 12px; color: #8a8a8a;">'
        . 'Sent with <a href="https://argorobots.com/downloads/?source=loop-quote-email" '
        . 'style="color: #8a8a8a;">Argo Books</a>, free quoting and invoicing software';

    $html = build_portal_email_html([
        'headerGradient' => 'linear-gradient(135deg, #2563eb, #1e40af)',
        'headerTitle' => $companyName,
        'greetingName' => $customerName,
        'introHtml' => $introHtml,
        'detailRows' => $detailRows,
        'ctaButton' => ['url' => $quoteUrl, 'text' => 'View Quote', 'color' => '#2563eb'],
        'closingHtml' => $closingHtml,
    ]);

    try {
        $fromEmail = env('INVOICE_DEFAULT_FROM_EMAIL', 'noreply@argorobots.com');
        $fromName = env('INVOICE_DEFAULT_FROM_NAME', 'Argo Books');

        $sent = argo_send_html_email(
            $customerEmail,
            'Quote ' . $quoteId . ' from ' . $companyName,
            $html,
            [
                'toName' => $customerName,
                'fromEmail' => $fromEmail,
                'fromName' => $companyName !== '' ? $companyName : $fromName,
                'replyTo' => filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : $fromEmail,
                'replyToName' => $companyName !== '' ? $companyName : $fromName,
            ]
        );

        return $sent['success']
            ? ['success' => true, 'message' => 'Email sent']
            : ['success' => false, 'message' => $sent['error'] ?? 'Failed to send email'];
    } catch (\Throwable $e) {
        error_log('Quote notification email failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send email'];
    }
}
