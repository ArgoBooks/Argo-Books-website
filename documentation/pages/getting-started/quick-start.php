<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Quick Start Tutorial';
$pageDescription = 'Get started quickly with Argo Books. Learn the basic steps to set up your business and start tracking products and revenue.';
$currentPage = 'quick-start';
$pageCategory = 'getting-started';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Get up and running with Argo Books in just a few steps. When you first open the application, a setup checklist will guide you through the initial configuration.</p>

            <h2>1. Create Your First Company</h2>
            <p>When you launch Argo Books for the first time, you'll see the Welcome Screen. Click "Create New Company" to open the setup wizard:</p>
            <ol class="steps-list">
                <li><strong>Step 1 – Company Information:</strong> Enter your company name, select your country and default currency. You can also fill in optional details like business type, industry, phone, email, and address.</li>
                <li><strong>Step 2 – Security & Branding:</strong> Optionally upload a company logo and set a password to protect your file.</li>
            </ol>
            <p>Each company's data is stored in a single <strong>.argo</strong> file that you can back up, copy, or move between computers.</p>

            <div class="info-box">
                <strong>Tip:</strong> Want to explore the app first? You can open the <strong>Sample Company</strong> from the welcome screen to see Argo Books populated with example data before setting up your own.
            </div>

            <h2>2. Find Your Way Around the Sidebar</h2>
            <p>Argo Books keeps the expense side and the revenue side of your business apart, and the sidebar reflects that:</p>
            <ul>
                <li><strong>Main:</strong> Dashboard, Analytics, Insights, Reports</li>
                <li><strong>Expenses:</strong> Expenses, Expense categories, Expense products, Suppliers</li>
                <li><strong>Revenue:</strong> Revenue, Invoices, Revenue categories, Revenue products, Customers</li>
                <li><strong>Import:</strong> Bank Matching, Receipts</li>
                <li><strong>Payroll:</strong> Employees, Pay Runs</li>
                <li><strong>Inventory:</strong> Stock Levels, Adjustments, Locations, Purchase Orders</li>
                <li><strong>Rentals:</strong> Rental Inventory, Rental Records</li>
                <li><strong>Tracking:</strong> Returns, Lost / Damaged</li>
            </ul>

            <h2>3. Add Your Suppliers</h2>
            <p>Go to "Suppliers" under the Expenses section. Add the businesses you purchase from, including their contact information and address details. Suppliers can be linked to your expense transactions later. See <a class="link" href="../features/suppliers.php">Supplier Management</a>.</p>

            <h2>4. Start Tracking Expenses and Revenue</h2>
            <p>Go to "Expenses" or "Revenue" and click "Add Expense" or "Add Revenue". On each line, pick an item or simply type a new one, along with its category, and Argo Books creates both when you save. Each entry can include multiple items, a receipt attachment, a customer or supplier, and more. See <a class="link" href="../features/sales-tracking.php">Expense/Revenue Tracking</a>.</p>

            <h2>5. Organize Your Products and Categories (Optional)</h2>
            <p>Because typed items and categories are created for you, there's nothing you have to set up first. When you want more detail, go to "Expense products" for the things you buy and "Revenue products" for the things you sell, to set pricing, a supplier, and other details. "Expense categories" and "Revenue categories" let you create and edit categories, which group similar items together so your spending is easier to follow and your reports more meaningful. For example, you might use expense categories like "Office Supplies," "Electronics," or "Raw Materials." See <a class="link" href="../features/product-management.php">Product Management</a>.</p>
            <p>Tracking stock and renting items out are the exceptions: a product needs Track Inventory turned on and a stock record before it has stock to sell or rent. See <a class="link" href="../features/inventory.php">Inventory Management</a> and <a class="link" href="../features/rental.php">Rental Management</a>.</p>

            <h2>In-App Tutorials</h2>
            <p>Argo Books includes a built-in tutorial system that walks you through key features step by step, directly inside the app. When you open certain pages for the first time, a tutorial will appear explaining how to use the feature. You can also re-access tutorials at any time from the help menu.</p>

            <div class="page-navigation">
                <a href="installation.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Installation Guide</span>
                </a>
                <a href="version-comparison.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Free vs. Paid Version &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
