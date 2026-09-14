<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Customer Management';
$pageDescription = 'Learn how to manage customer profiles, track expense history, and build lasting relationships with Argo Books customer management features.';
$currentPage = 'customers';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Build lasting relationships with your customers using Argo Books' customer management features.
            Keep track of customer information, expense history, and preferences to deliver personalized
            service that keeps them coming back.</p>

            <p>Customers live under the <strong>Revenue</strong> section of the sidebar, alongside Revenue, Invoices, and your revenue categories and products.</p>

            <h2>Customer Profiles</h2>
            <p>Create detailed customer profiles that store all the information you need:</p>
            <ul>
                <li><strong>Contact Information:</strong> First name, last name, email, and phone number</li>
                <li><strong>Company Name:</strong> The customer's business or company name (optional)</li>
                <li><strong>Address:</strong> Street, city, state or province, postal code, and country</li>
                <li><strong>Photo:</strong> An optional profile photo, shown in lists so you can pick people out at a glance</li>
                <li><strong>Status:</strong> Track whether customers are Active, Inactive, or Banned</li>
                <li><strong>Notes:</strong> Add personal notes about preferences or special requirements</li>
            </ul>

            <h2>Transaction History</h2>
            <p>View a complete history of all transactions with each customer:</p>
            <ul>
                <li>All past purchases and sales linked to the customer</li>
                <li>Total purchases to date, and the date of their most recent transaction</li>
                <li>Last rental date for rental customers</li>
                <li>Complete transaction records with dates and amounts</li>
            </ul>

            <div class="info-box">
                <strong>Tip:</strong> The Customers tab in <a class="link" href="analytics.php">Analytics</a> turns these profiles into retention, lifetime value and top-customer figures, and a customer counts as active only if they bought something inside the selected date range.
            </div>

            <h2>Linking Transactions</h2>
            <p>When recording a revenue entry, you can link it to a customer profile. Expenses are linked to a <a class="link" href="suppliers.php">supplier</a> instead.</p>
            <ol class="steps-list">
                <li>Start creating a new revenue entry (see <a class="link" href="sales-tracking.php">Expense/Revenue Tracking</a>)</li>
                <li>Click the "Customer" field</li>
                <li>Select an existing customer</li>
                <li>The transaction will be automatically added to their transaction history</li>
            </ol>

            <div class="page-navigation">
                <a href="rental.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Rental Management</span>
                </a>
                <a href="product-management.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Product Management &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
