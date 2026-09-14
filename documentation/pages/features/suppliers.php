<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Supplier Management';
$pageDescription = 'Learn how to manage suppliers in Argo Books. Add vendor details, track purchases, and link suppliers to products and transactions.';
$currentPage = 'suppliers';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Keep track of your vendors and where your products come from. The Suppliers page lets you store contact details, link suppliers to products, and see a complete purchase history for each vendor.</p>

            <h2>Adding a Supplier</h2>
            <ol class="steps-list">
                <li>Go to "Suppliers" in the navigation menu, under Expenses</li>
                <li>Click "Add Supplier"</li>
                <li>Enter the supplier's name and contact information</li>
                <li>Add address details if applicable</li>
                <li>Save the supplier</li>
            </ol>

            <h2>Supplier Details</h2>
            <p>Each supplier profile can include:</p>
            <ul>
                <li><strong>Name:</strong> The business or vendor name</li>
                <li><strong>Contact Person:</strong> Who you deal with at that business</li>
                <li><strong>Contact Information:</strong> Phone number, email address</li>
                <li><strong>Website:</strong> The supplier's website URL</li>
                <li><strong>Address:</strong> Street address, city, state or province, postal code, country</li>
                <li><strong>Payment Terms:</strong> The terms you have agreed with them, for reference</li>
                <li><strong>Photo:</strong> An optional logo or photo shown in supplier lists</li>
                <li><strong>Notes:</strong> Any additional information about the supplier</li>
            </ul>

            <h2>Linking Suppliers to Products</h2>
            <p>When adding or editing a product, you can assign a supplier to it. This helps you track which vendor supplies each product and makes it easier to manage reordering. See <a class="link" href="product-management.php">Product Management</a>.</p>

            <h2>Linking Suppliers to Transactions</h2>
            <p>When recording an expense, you can select a supplier to associate with the transaction. This builds a purchase history for each supplier, which you can review from their profile. See <a class="link" href="sales-tracking.php">Expense/Revenue Tracking</a>.</p>

            <h2>Managing Existing Suppliers</h2>
            <p>From the Suppliers page, you can edit or delete any supplier using the action buttons in each row. Editing a supplier updates their information across all linked products and future transactions.</p>

            <div class="page-navigation">
                <a href="product-management.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Product Management</span>
                </a>
                <a href="inventory.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Inventory Management &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
