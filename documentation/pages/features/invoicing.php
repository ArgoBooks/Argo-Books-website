<?php
require_once __DIR__ . '/../../../config/pricing.php';
require_once __DIR__ . '/../../../resources/icons.php';
$pricing = get_pricing_config();
$pageTitle = 'Invoicing & Payments';
$pageDescription = 'Create professional invoices, track payments, and accept online payments with Argo Books invoicing and payment features.';
$currentPage = 'invoicing';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Create professional invoices in seconds, track payment status, and get paid faster with
            integrated payment processing. Argo Books makes invoicing simple and efficient.</p>

            <div class="info-box">
                <p><strong>Note:</strong> The free version includes up to <?= (int) $pricing['free_invoice_monthly_limit'] ?> invoices per month. <a href="../getting-started/version-comparison.php" class="link">Upgrade to Premium</a> for unlimited invoices and online payment integration.</p>
            </div>

            <p>To let customers pay online, connect Stripe or Square first. See
            <a class="link" href="payment-portal.php">Payment Portal</a> for that one-time setup.</p>

            <h2>Creating Invoices</h2>
            <p>Generate professional invoices with just a few clicks:</p>
            <ol class="steps-list">
                <li>Go to "Invoices" in the navigation menu, under Revenue</li>
                <li>Click "Create Invoice"</li>
                <li>Select a customer or create a new one (see <a class="link" href="customers.php">Customer Management</a>)</li>
                <li>Add line items from your product catalog (see <a class="link" href="product-management.php">Product Management</a>)</li>
                <li>Set payment terms and due date</li>
                <li>Preview and send</li>
            </ol>

            <h2>Payment Tracking</h2>
            <p>Keep track of all your invoices and their payment status:</p>
            <ul>
                <li><strong>Draft:</strong> Invoice being prepared, never sent</li>
                <li><strong>Pending:</strong> Invoice is ready but has not been sent yet</li>
                <li><strong>Sent:</strong> Invoice delivered to customer, awaiting payment</li>
                <li><strong>Viewed:</strong> The customer opened it on the payment portal</li>
                <li><strong>Partial:</strong> Customer has made a partial payment</li>
                <li><strong>Paid:</strong> Invoice fully paid</li>
                <li><strong>Overdue:</strong> Payment is past the due date and not fully paid</li>
                <li><strong>Cancelled:</strong> Invoice has been cancelled</li>
                <li><strong>Refunded:</strong> Invoice was paid, then fully refunded</li>
                <li><strong>Partially Refunded:</strong> Invoice was paid, then refunded in part</li>
            </ul>
            <p>See <a class="link" href="../reference/how-numbers-are-calculated.php#invoice-status">How Numbers Are Calculated</a> for how each status affects your revenue and profit figures.</p>

            <div class="info-box">
                <p><strong>Note:</strong> When you record a payment manually, link it to an invoice or to a revenue so it stays tied to your income. Payments made through the online portal are linked to their invoice automatically.</p>
            </div>

            <p>Processing fees, and whether your customer or you pays them, are covered on
            <a class="link" href="payment-portal.php">Payment Portal</a>.</p>

            <div class="page-navigation">
                <a href="quotes.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Quotes</span>
                </a>
                <a href="payroll.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Payroll &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
