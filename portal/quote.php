<?php
/**
 * Quote View Page (Customer-Facing)
 *
 * Shows a quote and lets the customer accept or decline it.
 * URL: /quote/{token} (rewritten by .htaccess)
 *
 * No login required, the token is the credential. The answer posts back to this same page so the
 * page needs no JavaScript, and a reload after answering cannot send the answer twice.
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../api/portal/quote-helper.php';
require_once __DIR__ . '/../resources/icons.php';

$token = $_GET['token'] ?? '';

if (empty($token) || !preg_match('/^[a-fA-F0-9]{48}$/', $token)) {
    http_response_code(404);
    include __DIR__ . '/../error-pages/404.html';
    exit;
}

$clientIp = get_client_ip();
if (rate_limit_exceeded('portal_lookup', $clientIp, 'portal')) {
    http_response_code(429);
    header('Retry-After: ' . rate_limit_window('portal_lookup'));
    include __DIR__ . '/../error-pages/429.html';
    exit;
}

$quote = get_quote_by_token($token);

if (!$quote) {
    record_failed_lookup($clientIp);
    http_response_code(404);
    include __DIR__ . '/../error-pages/404.html';
    exit;
}

// The answer arrives as a plain form post. Redirect afterwards so a refresh re-renders the
// answered quote instead of resubmitting.
$responseError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (rate_limit_hit('quote_respond', $clientIp)) {
        http_response_code(429);
        header('Retry-After: ' . rate_limit_window('quote_respond'));
        include __DIR__ . '/../error-pages/429.html';
        exit;
    }

    if (!in_array($action, ['accept', 'decline'], true)) {
        $responseError = 'Please choose accept or decline.';
    } elseif (!quote_can_respond($quote)) {
        $responseError = 'This quote can no longer be answered.';
    } else {
        $note = quote_clean($_POST['note'] ?? '', 500);
        $recorded = quote_record_response(
            $quote,
            $action === 'accept' ? 'accepted' : 'declined',
            $note !== '' ? $note : null
        );

        if ($recorded) {
            header('Location: /quote/' . $token . '?answered=1');
            exit;
        }

        $responseError = 'This quote has already been answered.';
    }

    $quote = get_quote_by_token($token) ?? $quote;
}

$quoteData = json_decode($quote['quote_data'] ?? '{}', true) ?: [];
$customQuoteHtml = $quoteData['customQuoteHtml'] ?? '';
$quoteId = $quote['quote_id'];
$companyName = $quote['company_name'] ?? '';
$companyLogo = $quote['company_logo_url'] ?? '';
$customerName = $quote['customer_name'] ?? '';
$currency = $quote['currency'] ?: 'USD';
$currencySymbol = $currency === 'CAD' ? 'CA$' : '$';
$totalAmount = (float) $quote['total_amount'];
$validUntil = $quote['valid_until'] ?? null;
$status = $quote['status'];
$expired = quote_is_expired($quote);
$canRespond = quote_can_respond($quote);
$justAnswered = isset($_GET['answered']);
$statusLabel = $expired && $status === 'sent' ? 'Expired' : ucfirst($status);
$statusClass = $expired && $status === 'sent' ? 'overdue' : $status;
$lineItems = is_array($quoteData['lineItems'] ?? null) ? $quoteData['lineItems'] : [];
$notes = $quoteData['notes'] ?? '';
$issueDate = $quoteData['issueDate'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Quote <?php echo htmlspecialchars($quoteId); ?><?php echo $companyName !== '' ? ' - ' . htmlspecialchars($companyName) : ''; ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/resources/images/argo-logo/argo-icon.ico">

    <link rel="stylesheet" href="/resources/styles/custom-colors.css">
    <link rel="stylesheet" href="/portal/style.css">
</head>
<body>
    <div class="portal-page">
        <header class="portal-header">
            <div class="portal-header-inner">
                <?php if (!empty($companyLogo)): ?>
                    <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="<?php echo htmlspecialchars($companyName); ?>" class="company-logo">
                <?php endif; ?>
                <div class="company-info">
                    <?php if ($companyName !== ''): ?>
                        <h1 class="company-name"><?php echo htmlspecialchars($companyName); ?></h1>
                        <span class="portal-subtitle">Quote</span>
                    <?php else: ?>
                        <h1 class="company-name">Quote</h1>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="portal-main">
            <div class="invoice-status-bar">
                <span class="status-badge status-<?php echo htmlspecialchars($statusClass); ?>">
                    <?php echo htmlspecialchars($statusLabel); ?>
                </span>
                <?php if ($validUntil): ?>
                    <span class="status-bar-detail">
                        Valid until <strong><?php echo date('M j, Y', strtotime($validUntil)); ?></strong>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($customQuoteHtml)): ?>
                <div class="custom-invoice-container">
                    <iframe
                        id="custom-quote-frame"
                        srcdoc="<?php echo htmlspecialchars($customQuoteHtml); ?>"
                        sandbox="allow-same-origin"
                        class="custom-invoice-iframe"
                        scrolling="no"
                        frameborder="0">
                    </iframe>
                </div>
                <script>
                (function() {
                    var iframe = document.getElementById('custom-quote-frame');
                    iframe.addEventListener('load', function() {
                        try {
                            var doc = iframe.contentDocument;

                            // Strip email wrapper styling so the quote fills the container
                            var style = doc.createElement('style');
                            style.textContent =
                                'html, body { margin: 0 !important; padding: 0 !important; background: transparent !important; overflow: hidden !important; }' +
                                'body > table { background: transparent !important; }' +
                                'body > table > tbody > tr > td { padding: 0 !important; }' +
                                'body > table > tbody > tr > td > table { max-width: 100% !important; width: 100% !important; box-shadow: none !important; border-radius: 0 !important; }';
                            doc.head.appendChild(style);

                            iframe.style.height = doc.documentElement.scrollHeight + 'px';
                        } catch(e) {}
                    });
                    window.addEventListener('resize', function() {
                        setTimeout(function() {
                            try {
                                iframe.style.height = iframe.contentDocument.documentElement.scrollHeight + 'px';
                            } catch(e) {}
                        }, 100);
                    });
                })();
                </script>
            <?php else: ?>
                <div class="invoice-header-section">
                    <div class="invoice-title-row">
                        <h2 class="invoice-title">Quote <?php echo htmlspecialchars($quoteId); ?></h2>
                    </div>

                    <div class="invoice-parties">
                        <div class="party-info">
                            <span class="party-label">From</span>
                            <?php if ($companyName !== ''): ?>
                                <strong><?php echo htmlspecialchars($companyName); ?></strong>
                            <?php endif; ?>
                        </div>
                        <div class="party-info">
                            <span class="party-label">To</span>
                            <strong><?php echo htmlspecialchars($customerName); ?></strong>
                        </div>
                    </div>

                    <div class="invoice-dates">
                        <?php if ($issueDate): ?>
                            <div class="date-item">
                                <span class="date-label">Issue Date</span>
                                <span class="date-value"><?php echo date('M j, Y', strtotime($issueDate)); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($validUntil): ?>
                            <div class="date-item <?php echo $expired ? 'overdue' : ''; ?>">
                                <span class="date-label">Valid Until</span>
                                <span class="date-value"><?php echo date('M j, Y', strtotime($validUntil)); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="invoice-items-section">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th class="col-description">Description</th>
                                <th class="col-qty">Qty</th>
                                <th class="col-price">Price</th>
                                <th class="col-total">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($lineItems)): ?>
                                <?php foreach ($lineItems as $item): ?>
                                    <tr>
                                        <td class="col-description" data-label="Description">
                                            <?php echo htmlspecialchars((string) ($item['description'] ?? '')); ?>
                                        </td>
                                        <td class="col-qty" data-label="Qty">
                                            <?php echo htmlspecialchars((string) ($item['quantity'] ?? 1)); ?>
                                        </td>
                                        <td class="col-price" data-label="Price">
                                            <?php echo $currencySymbol . number_format((float) ($item['unitPrice'] ?? 0), 2); ?>
                                        </td>
                                        <td class="col-total" data-label="Amount">
                                            <?php echo $currencySymbol . number_format((float) ($item['amount'] ?? 0), 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="no-items">Quote details</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="invoice-totals">
                        <div class="total-row total-row-main">
                            <span>Total</span>
                            <span><?php echo $currencySymbol . number_format($totalAmount, 2); ?> <?php echo htmlspecialchars($currency); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($notes !== ''): ?>
                    <div class="invoice-notes">
                        <h3>Notes</h3>
                        <p><?php echo nl2br(htmlspecialchars((string) $notes)); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($canRespond): ?>
                <div class="payment-section">
                    <h3>Accept this quote?</h3>
                    <?php if ($responseError !== ''): ?>
                        <p class="quote-response-error"><?php echo htmlspecialchars($responseError); ?></p>
                    <?php endif; ?>
                    <form method="post" action="/quote/<?php echo htmlspecialchars($token); ?>" class="quote-response-form">
                        <label for="quote-note" class="quote-note-label">Message for <?php echo $companyName !== '' ? htmlspecialchars($companyName) : 'the sender'; ?> (optional)</label>
                        <textarea id="quote-note" name="note" rows="3" maxlength="500" placeholder="Anything you want to add"></textarea>
                        <div class="quote-response-actions">
                            <button type="submit" name="action" value="accept" class="btn-pay">Accept quote</button>
                            <button type="submit" name="action" value="decline" class="btn-decline">Decline</button>
                        </div>
                    </form>
                </div>
            <?php elseif ($status === 'accepted'): ?>
                <div class="invoice-paid-banner">
                    <?= svg_icon('circle-check', 48) ?>
                    <h3><?php echo $justAnswered ? 'Thanks, your answer has been sent' : 'This quote has been accepted'; ?></h3>
                    <p><?php echo $companyName !== '' ? htmlspecialchars($companyName) : 'The sender'; ?> will be in touch with the invoice.</p>
                    <p class="loop-cta">Need to send quotes too? <a href="https://argorobots.com/downloads/?source=loop-portal-quote" target="_blank" rel="noopener">Quote and invoice for free with Argo Books</a></p>
                </div>
            <?php elseif ($status === 'declined'): ?>
                <div class="invoice-no-methods">
                    <p><?php echo $justAnswered ? 'Thanks, your answer has been sent.' : 'This quote was declined.'; ?></p>
                    <p class="loop-cta">Need to send quotes too? <a href="https://argorobots.com/downloads/?source=loop-portal-quote" target="_blank" rel="noopener">Quote and invoice for free with Argo Books</a></p>
                </div>
            <?php elseif ($status === 'cancelled'): ?>
                <div class="invoice-no-methods">
                    <p>This quote has been withdrawn. Please contact <?php echo $companyName !== '' ? htmlspecialchars($companyName) : 'the sender'; ?> for an up to date one.</p>
                </div>
            <?php else: ?>
                <div class="invoice-no-methods">
                    <p>This quote expired on <?php echo $validUntil ? date('M j, Y', strtotime($validUntil)) : 'its valid-until date'; ?>. Please contact <?php echo $companyName !== '' ? htmlspecialchars($companyName) : 'the sender'; ?> for an up to date one.</p>
                </div>
            <?php endif; ?>
        </main>

        <footer class="portal-footer">
            <p>Powered by <a href="https://argorobots.com/downloads/?source=loop-portal-quote" target="_blank" rel="noopener">Argo Books</a></p>
        </footer>
    </div>
</body>
</html>
