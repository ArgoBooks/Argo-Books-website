<?php
$pageTitle = 'Free vs. Paid Version';
$pageDescription = 'Compare Argo Books free and premium versions. Learn about features, limitations, and which version is right for your business.';
$currentPage = 'version-comparison';
$pageCategory = 'getting-started';

require_once __DIR__ . '/../../../config/pricing.php';

$pricing = get_pricing_config();
$monthlyPrice = $pricing['premium_monthly_price'];
$yearlyPrice = $pricing['premium_yearly_price'];
$yearlySavings = ($monthlyPrice * 12) - $yearlyPrice;

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Argo Books offers two tiers to match your business needs. Start with our free version,
            perfect for small businesses just getting started. As your business grows, upgrade to
            Premium for unlimited invoicing, higher monthly limits, the payroll and forecasting
            tools, and the API.</p>

            <p>Not sure which version is right for you? <a href="../../../downloads/" class="link">Try our free
                version first</a> – you can always <a href="../../../pricing/" class="link">upgrade
                later</a> when you need more features.</p>

            <p>The free version costs nothing and never expires. Premium is
                $<?php echo number_format($monthlyPrice, 0); ?> CAD/month, or
                $<?php echo number_format($yearlyPrice, 0); ?> CAD/year which saves you
                $<?php echo number_format($yearlySavings, 0); ?>. See the
                <a href="../../../pricing/" class="link">pricing page</a> for payment options and
                billing questions.</p>

            <h2>Feature Comparison</h2>
            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Free</th>
                            <th>Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Ordered by how much the tiers differ: metered features first,
                             then premium-only, then everything included on both. -->
                        <tr>
                            <td>Invoices</td>
                            <td><?= (int) $pricing['free_invoice_monthly_limit'] ?> invoices / month</td>
                            <td>Unlimited</td>
                        </tr>
                        <tr>
                            <td>Receipt Scanning</td>
                            <td><?= (int) $pricing['free_receipt_scan_monthly_limit'] ?> receipts / month</td>
                            <td><?= (int) $pricing['receipt_scan_monthly_limit'] ?> receipts / month</td>
                        </tr>
                        <tr>
                            <td>Spreadsheet Import</td>
                            <td><?= (int) $pricing['ai_import_monthly_limit'] ?> imports / month</td>
                            <td><?= (int) $pricing['premium_ai_import_monthly_limit'] ?> imports / month</td>
                        </tr>
                        <tr>
                            <td>Bank Statement Import</td>
                            <td><?= (int) $pricing['bank_import_monthly_limit'] ?> imports / month</td>
                            <td><?= (int) $pricing['premium_bank_import_monthly_limit'] ?> imports / month</td>
                        </tr>
                        <tr>
                            <td>Canadian Payroll</td>
                            <td>—</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Predictive Analytics</td>
                            <td>—</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Argo Books API</td>
                            <td>—</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Biometric Login</td>
                            <td>—</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Support</td>
                            <td>Standard</td>
                            <td>Priority</td>
                        </tr>
                        <tr>
                            <td>Transactions</td>
                            <td>Unlimited</td>
                            <td>Unlimited</td>
                        </tr>
                        <tr>
                            <td>Works Offline</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Inventory Management</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Customer Management</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Expense &amp; Revenue Tracking</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Rental Management</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Receipt Management</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Real-time Analytics</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Report Builder</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>Excel Import/Export</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="page-navigation">
                <a href="quick-start.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Quick Start Tutorial</span>
                </a>
                <a href="redeem-license-key.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Redeem a License Key &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
