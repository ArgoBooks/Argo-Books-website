<?php
/**
 * Customer Portal Page
 *
 * Displays all invoices and payment history for a customer.
 * URL: /portal/{token} (rewritten by .htaccess)
 *
 * Tabbed interface: Active Invoices | All Invoices
 * No login required - token-based access.
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../api/portal/portal-helper.php';
require_once __DIR__ . '/../shared/currencies.php';

require_once __DIR__ . '/../resources/icons.php';

$token = $_GET['token'] ?? '';

// If no token provided, show a generic portal landing page
if (empty($token) || !preg_match('/^[a-fA-F0-9]{48}$/', $token)) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Payment Portal - Argo Books</title>
    <link rel="shortcut icon" type="image/x-icon" href="/resources/images/argo-logo/argo-icon.ico">
    <link rel="stylesheet" href="/resources/styles/custom-colors.css">
    <link rel="stylesheet" href="/portal/style.css">
</head>
<body>
    <div class="portal-page">
        <header class="portal-header">
            <div class="portal-header-inner">
                <div class="company-info">
                    <h1 class="company-name">Payment Portal</h1>
                    <span class="portal-subtitle">Powered by Argo Books</span>
                </div>
            </div>
        </header>
        <main class="portal-main">
            <div class="portal-welcome" style="text-align: center; padding: 48px 20px;">
                <h2>Welcome to the Payment Portal</h2>
                <p style="margin-top: 12px; color: var(--gray-600); font-size: 15px; max-width: 480px; margin-left: auto; margin-right: auto;">
                    To view and pay your invoices, please use the link provided in your invoice email.
                </p>
                <a href="https://argorobots.com/?source=loop-portal" style="display: inline-block; margin-top: 24px; color: var(--primary-blue); text-decoration: none; font-weight: 500;">
                    &larr; Back to Argo Books
                </a>
            </div>
        </main>
        <footer class="portal-footer">
            <p>Secure payments powered by <a href="https://argorobots.com/downloads/?source=loop-portal" target="_blank" rel="noopener">Argo Books</a></p>
        </footer>
    </div>
</body>
</html>
    <?php
    exit;
}

// Rate limiting
$clientIp = get_client_ip();
if (is_rate_limited($clientIp)) {
    http_response_code(429);
    echo '<!DOCTYPE html><html><head><title>Too Many Requests</title></head><body><h1>Too many requests. Please try again later.</h1></body></html>';
    exit;
}

// Get customer data
$result = get_invoices_by_customer_token($token);

if (!$result['company']) {
    record_failed_lookup($clientIp);
    http_response_code(404);
    include __DIR__ . '/../error-pages/404.html';
    exit;
}

$company = $result['company'];
$invoices = $result['invoices'];
$paymentMethods = get_available_payment_methods($company);

$companyName = $company['company_name'] ?? '';
$companyLogo = $company['company_logo_url'] ?? '';

// Categorize invoices
$activeInvoices = [];
$paidInvoices = [];
$totalOutstanding = 0;

foreach ($invoices as $inv) {
    $invStatus = $inv['status'];
    $balanceDue = floatval($inv['balance_due']);

    // Check if overdue
    if ($inv['due_date'] && !in_array($invStatus, ['paid', 'cancelled'])) {
        if (strtotime($inv['due_date']) < time() && $invStatus !== 'partial') {
            $invStatus = 'overdue';
        }
    }
    $inv['display_status'] = $invStatus;

    if ($invStatus === 'paid' || $balanceDue <= 0) {
        $inv['display_status'] = 'paid';
        $paidInvoices[] = $inv;
    } elseif (in_array($invStatus, ['sent', 'viewed', 'partial', 'overdue', 'pending'])) {
        $activeInvoices[] = $inv;
        $totalOutstanding += $balanceDue;
    }
}

// Get customer name from first invoice
$customerName = !empty($invoices) ? ($invoices[0]['customer_name'] ?? '') : '';

// Currency info
$currency = !empty($invoices) ? ($invoices[0]['currency'] ?: 'USD') : 'USD';
$currencySymbol = argo_currency_display_symbol($currency);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Invoice Portal<?php echo $companyName !== '' ? ' - ' . htmlspecialchars($companyName) : ''; ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/resources/images/argo-logo/argo-icon.ico">

    <link rel="stylesheet" href="/resources/styles/custom-colors.css">
    <link rel="stylesheet" href="/portal/style.css">
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
            </div>
        </header>

        <main class="portal-main">
            <!-- Welcome Section -->
            <div class="portal-welcome">
                <h2>Hello<?php echo $customerName ? ', ' . htmlspecialchars($customerName) : ''; ?></h2>
                <?php if ($totalOutstanding > 0): ?>
                    <div class="outstanding-summary">
                        <span class="outstanding-label">Total Outstanding</span>
                        <span class="outstanding-amount"><?php echo $currencySymbol . number_format($totalOutstanding, 2); ?> <?php echo $currency; ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tabs -->
            <div class="portal-tabs">
                <button class="tab-btn active" data-tab="active">
                    Active Invoices
                    <?php if (count($activeInvoices) > 0): ?>
                        <span class="tab-count"><?php echo count($activeInvoices); ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn" data-tab="all">
                    All Invoices
                    <span class="tab-count"><?php echo count($invoices); ?></span>
                </button>
            </div>

            <!-- Active Invoices Tab -->
            <div class="tab-content active" id="tab-active">
                <?php if (empty($activeInvoices)): ?>
                    <div class="empty-state">
                        <?= svg_icon('circle-check', 48, '', 1.5, 'opacity="0.4"') ?>
                        <p>No outstanding invoices. You're all caught up!</p>
                    </div>
                <?php else: ?>
                    <div class="invoice-cards">
                        <?php foreach ($activeInvoices as $inv):
                            $invData = $inv['invoice_data'] ?? [];
                            $invStatus = $inv['display_status'];
                            $invBalance = floatval($inv['balance_due']);
                            $invTotal = floatval($inv['total_amount']);
                            $invCurrency = $inv['currency'] ?: 'USD';
                            $invSymbol = argo_currency_display_symbol($invCurrency);
                        ?>
                            <div class="invoice-card <?php echo $invStatus === 'overdue' ? 'overdue' : ''; ?>">
                                <div class="card-header">
                                    <div class="card-id"><?php echo htmlspecialchars($inv['invoice_id']); ?></div>
                                    <span class="status-badge status-<?php echo htmlspecialchars($invStatus); ?>">
                                        <?php echo ucfirst(htmlspecialchars($invStatus)); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="card-dates">
                                        <?php if ($inv['due_date']): ?>
                                            <span class="<?php echo $invStatus === 'overdue' ? 'text-danger' : ''; ?>">
                                                Due: <?php echo date('M j, Y', strtotime($inv['due_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-amounts">
                                        <?php if ($invBalance < $invTotal): ?>
                                            <span class="card-total-label">Total: <?php echo $invSymbol . number_format($invTotal, 2); ?></span>
                                        <?php endif; ?>
                                        <span class="card-balance"><?php echo $invSymbol . number_format($invBalance, 2); ?> <?php echo $invCurrency; ?></span>
                                        <span class="card-balance-label">Balance Due</span>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <a href="/invoice/<?php echo htmlspecialchars($inv['invoice_token']); ?>" class="btn-pay">
                                        View & Pay
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Invoices Tab -->
            <div class="tab-content" id="tab-all" style="display: none;">
                <?php if (empty($invoices)): ?>
                    <div class="empty-state">
                        <p>No invoices found.</p>
                    </div>
                <?php else: ?>
                    <div class="all-invoices-table-wrapper">
                        <table class="all-invoices-table">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Due Date</th>
                                    <th>Total</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv):
                                    $invStatus = $inv['status'];
                                    $invBalance = floatval($inv['balance_due']);
                                    $invTotal = floatval($inv['total_amount']);
                                    $invCurrency = $inv['currency'] ?: 'USD';
                                    $invSymbol = argo_currency_display_symbol($invCurrency);

                                    if ($inv['due_date'] && !in_array($invStatus, ['paid', 'cancelled'])) {
                                        if (strtotime($inv['due_date']) < time() && $invStatus !== 'partial') {
                                            $invStatus = 'overdue';
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td data-label="Invoice #"><strong><?php echo htmlspecialchars($inv['invoice_id']); ?></strong></td>
                                        <td data-label="Date"><?php echo date('M j, Y', strtotime($inv['created_at'])); ?></td>
                                        <td data-label="Due Date" class="<?php echo $invStatus === 'overdue' ? 'text-danger' : ''; ?>">
                                            <?php echo $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : '-'; ?>
                                        </td>
                                        <td data-label="Total"><?php echo $invSymbol . number_format($invTotal, 2); ?></td>
                                        <td data-label="Balance"><?php echo $invSymbol . number_format($invBalance, 2); ?></td>
                                        <td data-label="Status">
                                            <span class="status-badge status-<?php echo htmlspecialchars($invStatus); ?>">
                                                <?php echo ucfirst(htmlspecialchars($invStatus)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="/invoice/<?php echo htmlspecialchars($inv['invoice_token']); ?>" class="btn-view">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Footer -->
        <footer class="portal-footer">
            <p>Powered by <a href="https://argorobots.com/downloads/?source=loop-portal" target="_blank" rel="noopener">Argo Books</a></p>
        </footer>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetTab = this.getAttribute('data-tab');

                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');

                // Show target tab content
                document.querySelectorAll('.tab-content').forEach(function(tc) {
                    tc.style.display = 'none';
                    tc.classList.remove('active');
                });
                var target = document.getElementById('tab-' + targetTab);
                if (target) {
                    target.style.display = 'block';
                    target.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
