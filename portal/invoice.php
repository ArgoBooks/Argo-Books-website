<?php
/**
 * Invoice View Page (Customer-Facing)
 *
 * Displays a single invoice with payment options.
 * URL: /invoice/{token} (rewritten by .htaccess)
 *
 * No login required - token-based access.
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../api/portal/portal-helper.php';
require_once __DIR__ . '/../shared/currencies.php';

require_once __DIR__ . '/../resources/icons.php';

$token = $_GET['token'] ?? '';

// Validate token format
if (empty($token) || !preg_match('/^[a-fA-F0-9]{48}$/', $token)) {
    http_response_code(404);
    include __DIR__ . '/../error-pages/404.html';
    exit;
}

// Rate limiting
$clientIp = get_client_ip();
if (rate_limit_exceeded('portal_lookup', $clientIp, 'portal')) {
    http_response_code(429);
    header('Retry-After: ' . rate_limit_window('portal_lookup'));
    include __DIR__ . '/../error-pages/429.html';
    exit;
}

// Get invoice data
$invoice = get_invoice_by_token($token);

if (!$invoice) {
    record_failed_lookup($clientIp);
    http_response_code(404);
    include __DIR__ . '/../error-pages/404.html';
    exit;
}

$paymentMethods = get_available_payment_methods($invoice);
$singleMethod = count($paymentMethods) === 1 ? $paymentMethods[0] : null;

// Get environment-based keys for payment SDKs
$is_production = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production';

$stripe_publishable_key = '';
if (in_array('stripe', $paymentMethods)) {
    $stripe_publishable_key = $is_production
        ? $_ENV['STRIPE_LIVE_PUBLISHABLE_KEY']
        : $_ENV['STRIPE_SANDBOX_PUBLISHABLE_KEY'];
}

$paypal_client_id = '';
if (in_array('paypal', $paymentMethods)) {
    $paypal_client_id = $is_production
        ? $_ENV['PAYPAL_LIVE_CLIENT_ID']
        : $_ENV['PAYPAL_SANDBOX_CLIENT_ID'];
}

$square_app_id = '';
if (in_array('square', $paymentMethods)) {
    $square_app_id = $is_production
        ? $_ENV['SQUARE_LIVE_APP_ID']
        : $_ENV['SQUARE_SANDBOX_APP_ID'];
}

// Parse invoice data
$invoiceData = $invoice['invoice_data'] ?? [];
$lineItems = $invoiceData['lineItems'] ?? $invoiceData['LineItems'] ?? [];
$companyName = $invoice['company_name'] ?? '';
$companyLogo = $invoice['company_logo_url'] ?? '';
$customerName = $invoice['customer_name'] ?? '';
$invoiceId = $invoice['invoice_id'] ?? '';
$totalAmount = floatval($invoice['total_amount']);
$balanceDue = floatval($invoice['balance_due']);
$currency = $invoice['currency'] ?: 'USD';
$currencySymbol = argo_currency_display_symbol($currency);
$dueDate = $invoice['due_date'] ?? '';
$status = $invoice['status'] ?? 'sent';
$issueDate = $invoiceData['issueDate'] ?? $invoiceData['IssueDate'] ?? $invoice['created_at'] ?? '';
$notes = $invoiceData['notes'] ?? $invoiceData['Notes'] ?? '';
$subtotal = $invoiceData['subtotal'] ?? $invoiceData['Subtotal'] ?? $totalAmount;
$taxAmount = $invoiceData['taxAmount'] ?? $invoiceData['TaxAmount'] ?? 0;
$taxRate = $invoiceData['taxRate'] ?? $invoiceData['TaxRate'] ?? '';
$amountPaid = $totalAmount - $balanceDue;

// Calculate processing fee if enabled and invoice is unpaid
$passProcessingFee = !empty($invoice['pass_processing_fee']);
$processingFee = 0.00;
$totalWithFee = $balanceDue;
$feeProviderLabel = '';
if ($passProcessingFee && $balanceDue > 0) {
    require_once __DIR__ . '/../config/pricing.php';
    $processingFee = calculate_invoice_processing_fee($balanceDue, $currency);
    $totalWithFee = $balanceDue + $processingFee;

    // Build provider name for the fee label
    $providerNames = [];
    if (in_array('stripe', $paymentMethods)) $providerNames[] = 'Stripe';
    if (in_array('paypal', $paymentMethods)) $providerNames[] = 'PayPal';
    if (in_array('square', $paymentMethods)) $providerNames[] = 'Square';
    $feeProviderLabel = !empty($providerNames) ? implode(' / ', $providerNames) : 'payment provider';
}

// Custom invoice HTML (rendered by Argo Books desktop app)
$customInvoiceHtml = $invoiceData['customInvoiceHtml'] ?? '';

// Company address info
$companyAddress = $invoiceData['companyAddress'] ?? $invoiceData['CompanyAddress'] ?? '';
$companyEmail = $invoiceData['companyEmail'] ?? $invoiceData['CompanyEmail'] ?? '';
$companyPhone = $invoiceData['companyPhone'] ?? $invoiceData['CompanyPhone'] ?? '';
$customerAddress = $invoiceData['customerAddress'] ?? $invoiceData['CustomerAddress'] ?? '';
$customerEmail = $invoice['customer_email'] ?? '';

// Determine if overdue
$isOverdue = false;
if ($dueDate && $status !== 'paid' && $status !== 'cancelled') {
    $isOverdue = strtotime($dueDate) < time();
    if ($isOverdue && $status !== 'partial') {
        $status = 'overdue';
    }
}

$isPaid = $status === 'paid' || $balanceDue <= 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Invoice <?php echo htmlspecialchars($invoiceId); ?><?php echo $companyName !== '' ? ' - ' . htmlspecialchars($companyName) : ''; ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/resources/images/argo-logo/argo-icon.ico">

    <link rel="stylesheet" href="/resources/styles/custom-colors.css">
    <link rel="stylesheet" href="/portal/style.css">

    <?php if (!$isPaid && !empty($paymentMethods)): ?>
    <?php $je = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT; ?>
    <script>
        window.PORTAL_CONFIG = {
            invoiceToken: <?php echo json_encode($token, $je); ?>,
            invoiceId: <?php echo json_encode($invoiceId, $je); ?>,
            balanceDue: <?php echo $balanceDue; ?>,
            processingFee: <?php echo $processingFee; ?>,
            totalWithFee: <?php echo $totalWithFee; ?>,
            feeProviderLabel: <?php echo json_encode($feeProviderLabel, $je); ?>,
            currency: <?php echo json_encode($currency, $je); ?>,
            currencySymbol: <?php echo json_encode($currencySymbol, $je); ?>,
            paymentMethods: <?php echo json_encode($paymentMethods, $je); ?>,
            stripe: {
                publishableKey: <?php echo json_encode($stripe_publishable_key, $je); ?>,
                accountId: <?php echo json_encode($invoice['stripe_account_id'] ?? '', $je); ?>
            },
            paypal: {
                clientId: <?php echo json_encode($paypal_client_id, $je); ?>
            },
            square: {
                appId: <?php echo json_encode($square_app_id, $je); ?>
            },
            apiBase: '/api/portal',
            singleMethod: <?php echo json_encode($singleMethod, $je); ?>
        };
    </script>
    <script src="/portal/main.js" defer></script>
    <?php endif; ?>
</head>
<body>
    <div class="portal-page">
        <!-- Header -->
        <header class="portal-header">
            <div class="portal-header-inner">
                <?php if (!empty($companyLogo)): ?>
                    <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="<?php echo htmlspecialchars($companyName); ?>" class="company-logo">
                <?php endif; ?>
                <div class="company-info">
                    <?php if ($companyName !== ''): ?>
                        <h1 class="company-name"><?php echo htmlspecialchars($companyName); ?></h1>
                        <span class="portal-subtitle">Invoice Portal</span>
                    <?php else: ?>
                        <h1 class="company-name">Invoice Portal</h1>
                    <?php endif; ?>
                </div>
                <?php if (!empty($invoice['customer_token'])): ?>
                    <a href="/portal/<?php echo htmlspecialchars($invoice['customer_token']); ?>" class="portal-all-invoices-link">
                        <?= svg_icon('document', 16) ?>
                        View all invoices
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="portal-main">
            <?php if (!empty($customInvoiceHtml)): ?>
                <!-- Custom Invoice rendered by Argo Books -->
                <?php if ($amountPaid > 0 || $isPaid): ?>
                    <div class="invoice-status-bar">
                        <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                            <?php echo ucfirst(htmlspecialchars($status)); ?>
                        </span>
                        <?php if ($amountPaid > 0 && !$isPaid): ?>
                            <span class="status-bar-detail">
                                Paid: <?php echo $currencySymbol . number_format($amountPaid, 2); ?>
                            </span>
                            <span class="status-bar-detail">
                                Balance Due: <strong><?php echo $currencySymbol . number_format($balanceDue, 2); ?> <?php echo $currency; ?></strong>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="custom-invoice-container">
                    <iframe
                        id="custom-invoice-frame"
                        srcdoc="<?php echo htmlspecialchars($customInvoiceHtml); ?>"
                        sandbox="allow-same-origin"
                        class="custom-invoice-iframe"
                        scrolling="no"
                        frameborder="0">
                    </iframe>
                </div>
                <script>
                (function() {
                    var iframe = document.getElementById('custom-invoice-frame');
                    iframe.addEventListener('load', function() {
                        try {
                            var doc = iframe.contentDocument;

                            // Strip email wrapper styling so the invoice fills the container
                            var style = doc.createElement('style');
                            style.textContent =
                                'html, body { margin: 0 !important; padding: 0 !important; background: transparent !important; overflow: hidden !important; }' +
                                'body > table { background: transparent !important; }' +
                                'body > table > tbody > tr > td { padding: 0 !important; }' +
                                'body > table > tbody > tr > td > table { max-width: 100% !important; width: 100% !important; box-shadow: none !important; border-radius: 0 !important; }';
                            doc.head.appendChild(style);

                            // Auto-resize height to content
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
                <!-- Standard invoice template -->
                <div class="invoice-header-section">
                    <div class="invoice-title-row">
                        <h2 class="invoice-title">Invoice <?php echo htmlspecialchars($invoiceId); ?></h2>
                        <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                            <?php echo ucfirst(htmlspecialchars($status)); ?>
                        </span>
                    </div>

                    <div class="invoice-parties">
                        <div class="party-info">
                            <span class="party-label">From</span>
                            <?php if ($companyName !== ''): ?>
                                <strong><?php echo htmlspecialchars($companyName); ?></strong>
                            <?php endif; ?>
                            <?php if ($companyAddress): ?>
                                <span class="party-detail"><?php echo nl2br(htmlspecialchars($companyAddress)); ?></span>
                            <?php endif; ?>
                            <?php if ($companyEmail): ?>
                                <span class="party-detail"><?php echo htmlspecialchars($companyEmail); ?></span>
                            <?php endif; ?>
                            <?php if ($companyPhone): ?>
                                <span class="party-detail"><?php echo htmlspecialchars($companyPhone); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="party-info">
                            <span class="party-label">To</span>
                            <strong><?php echo htmlspecialchars($customerName); ?></strong>
                            <?php if ($customerAddress): ?>
                                <span class="party-detail"><?php echo nl2br(htmlspecialchars($customerAddress)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="invoice-dates">
                        <?php if ($issueDate): ?>
                            <div class="date-item">
                                <span class="date-label">Issue Date</span>
                                <span class="date-value"><?php echo date('M j, Y', strtotime($issueDate)); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($dueDate): ?>
                            <div class="date-item <?php echo $isOverdue ? 'overdue' : ''; ?>">
                                <span class="date-label">Due Date</span>
                                <span class="date-value"><?php echo date('M j, Y', strtotime($dueDate)); ?></span>
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
                                            <?php echo htmlspecialchars($item['description'] ?? $item['Description'] ?? ''); ?>
                                        </td>
                                        <td class="col-qty" data-label="Qty">
                                            <?php echo htmlspecialchars($item['quantity'] ?? $item['Quantity'] ?? 1); ?>
                                        </td>
                                        <td class="col-price" data-label="Price">
                                            <?php echo $currencySymbol . number_format(floatval($item['unitPrice'] ?? $item['UnitPrice'] ?? $item['price'] ?? $item['Price'] ?? 0), 2); ?>
                                        </td>
                                        <td class="col-total" data-label="Amount">
                                            <?php echo $currencySymbol . number_format(floatval($item['total'] ?? $item['Total'] ?? $item['amount'] ?? $item['Amount'] ?? 0), 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="no-items">Invoice details</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="invoice-totals">
                        <?php if (abs($subtotal - $totalAmount) > 0.01): ?>
                            <div class="total-row">
                                <span>Subtotal</span>
                                <span><?php echo $currencySymbol . number_format(floatval($subtotal), 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($taxAmount > 0): ?>
                            <div class="total-row">
                                <span>Tax<?php echo $taxRate ? " ({$taxRate}%)" : ''; ?></span>
                                <span><?php echo $currencySymbol . number_format(floatval($taxAmount), 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="total-row total-row-main">
                            <span>Total</span>
                            <span><?php echo $currencySymbol . number_format($totalAmount, 2); ?> <?php echo $currency; ?></span>
                        </div>
                        <?php if ($amountPaid > 0): ?>
                            <div class="total-row total-row-paid">
                                <span>Paid</span>
                                <span>-<?php echo $currencySymbol . number_format($amountPaid, 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="total-row total-row-balance <?php echo $isPaid ? 'paid' : ($isOverdue ? 'overdue' : ''); ?>">
                            <span>Balance Due</span>
                            <span><?php echo $currencySymbol . number_format($balanceDue, 2); ?> <?php echo $currency; ?></span>
                        </div>
                        <?php if ($processingFee > 0 && !$isPaid): ?>
                            <div class="total-row total-row-fee">
                                <span>
                                    <?php echo htmlspecialchars($feeProviderLabel); ?> processing fee
                                    <button type="button" class="fee-info" aria-label="This fee is charged by <?php echo htmlspecialchars($feeProviderLabel); ?> for secure online payment processing. It is not charged by <?php echo htmlspecialchars($companyName); ?>." title="This fee is charged by <?php echo htmlspecialchars($feeProviderLabel); ?> for secure online payment processing. It is not charged by <?php echo htmlspecialchars($companyName); ?>.">&#9432;</button>
                                </span>
                                <span><?php echo $currencySymbol . number_format($processingFee, 2); ?></span>
                            </div>
                            <div class="total-row total-row-charge">
                                <span>Amount to Pay</span>
                                <span><?php echo $currencySymbol . number_format($totalWithFee, 2); ?> <?php echo $currency; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($notes): ?>
                    <div class="invoice-notes">
                        <h3>Notes</h3>
                        <p><?php echo nl2br(htmlspecialchars($notes)); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Payment Section -->
            <?php if (!$isPaid && !empty($paymentMethods)): ?>
                <div class="payment-section" id="payment-section">
                    <h3>Pay This Invoice</h3>
                    <p class="payment-summary">Amount due: <strong><?php echo $currencySymbol . number_format($totalWithFee, 2); ?> <?php echo $currency; ?></strong></p>

                    <?php if ($singleMethod): ?>
                        <!-- Single payment method: go straight to form -->
                        <div id="payment-form-container"></div>
                    <?php else: ?>
                        <!-- Multiple payment methods: show selection -->
                        <div class="payment-methods" id="payment-methods">
                            <p class="method-label">Pay with:</p>
                            <div class="method-buttons">
                                <?php if (in_array('stripe', $paymentMethods)): ?>
                                    <button type="button" class="method-btn" data-method="stripe">
                                        <img src="../resources/images/Stripe-logo.svg" alt="Stripe" loading="lazy">
                                    </button>
                                <?php endif; ?>
                                <?php if (in_array('paypal', $paymentMethods)): ?>
                                    <button type="button" class="method-btn" data-method="paypal">
                                        <img src="../resources/images/PayPal-logo.svg" alt="PayPal" loading="lazy">
                                    </button>
                                <?php endif; ?>
                                <?php if (in_array('square', $paymentMethods)): ?>
                                    <button type="button" class="method-btn" data-method="square">
                                        <img src="../resources/images/Square-logo.svg" alt="Square" loading="lazy">
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div id="payment-form-container" style="display: none;"></div>
                    <?php endif; ?>
                </div>
            <?php elseif ($isPaid): ?>
                <div class="invoice-paid-banner">
                    <?= svg_icon('circle-check', 48) ?>
                    <h3>This invoice has been paid</h3>
                    <p>Thank you for your payment.</p>
                    <p class="loop-cta">Need to send invoices too? <a href="https://argorobots.com/downloads/?source=loop-portal-paid" target="_blank" rel="noopener">Get paid online like this, free with Argo Books</a></p>
                </div>
            <?php else: ?>
                <div class="invoice-no-methods">
                    <p>Online payments are not available for this invoice. Please contact <?php echo $companyName !== '' ? htmlspecialchars($companyName) : 'the sender'; ?> for payment instructions.</p>
                </div>
            <?php endif; ?>

            <!-- Confirmation Section (shown after payment) -->
            <div id="payment-confirmation" class="payment-confirmation" style="display: none;">
                <?= svg_icon('circle-check', 64, 'confirmation-icon') ?>
                <h3>Payment Successful</h3>
                <div class="confirmation-details">
                    <div class="detail-row">
                        <span>Amount Paid</span>
                        <span id="conf-amount"></span>
                    </div>
                    <div class="detail-row">
                        <span>Reference</span>
                        <span id="conf-reference"></span>
                    </div>
                    <div class="detail-row">
                        <span>Payment Method</span>
                        <span id="conf-method"></span>
                    </div>
                    <div class="detail-row">
                        <span>Date</span>
                        <span id="conf-date"></span>
                    </div>
                    <div class="detail-row">
                        <span>Invoice</span>
                        <span id="conf-invoice"><?php echo htmlspecialchars($invoiceId); ?></span>
                    </div>
                </div>
                <p class="confirmation-note">A confirmation has been sent. You can close this page.</p>
                <p class="loop-cta">Need to send invoices too? <a href="https://argorobots.com/downloads/?source=loop-portal-paid" target="_blank" rel="noopener">Get paid online like this, free with Argo Books</a></p>
            </div>
        </main>

        <!-- Footer -->
        <footer class="portal-footer">
            <p>Powered by <a href="https://argorobots.com/downloads/?source=loop-portal" target="_blank" rel="noopener">Argo Books</a></p>
        </footer>
    </div>
</body>
</html>
