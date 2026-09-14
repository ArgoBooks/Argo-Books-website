<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Expense/Revenue Tracking';
$pageDescription = 'Learn how to track expenses and revenue in Argo Books. Add transactions, manage orders, and keep accurate records.';
$currentPage = 'sales-tracking';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Track all your business transactions with Argo Books. The Expenses and Revenue pages let you record, organize, and review every financial transaction in your business.</p>

            <h2>Expenses Overview</h2>
            <p>The Expenses page displays four summary cards at the top:</p>
            <ul>
                <li><strong>Monthly Expenses:</strong> Total spending for the current month</li>
                <li><strong>Transactions:</strong> Number of expense transactions</li>
                <li><strong>Receipts on File:</strong> Number of receipts attached to expenses</li>
                <li><strong>Returns:</strong> Number of expense returns</li>
            </ul>

            <h2>Revenue Overview</h2>
            <p>The Revenue page displays four summary cards at the top:</p>
            <ul>
                <li><strong>Monthly Revenue:</strong> Total income for the current month</li>
                <li><strong>Transactions:</strong> Number of revenue transactions</li>
                <li><strong>Unique Customers:</strong> Number of distinct customers</li>
                <li><strong>Returns:</strong> Number of customer returns</li>
            </ul>

            <h2>Adding a Transaction</h2>
            <ol class="steps-list">
                <li>Go to "Expenses" (under the Expenses section) or "Revenue" (under the Revenue section) in the navigation menu</li>
                <li>Click the "Add Expense" or "Add Revenue" button</li>
                <li>On each line, pick an item or type a new one, pick or type its category, then enter the quantity and price</li>
                <li>Choose the date, and optionally assign a customer or supplier</li>
                <li>Attach a receipt if available (optional)</li>
                <li>Click "Save" to record the transaction</li>
            </ol>

            <div class="info-box">
                <strong>New items:</strong> An item or category you type that doesn't exist yet is created when you save, so there's no need to set up products first. A new item takes the price from its line.
            </div>

            <h2>Multi-Item Transactions</h2>
            <p>A single transaction can include multiple products. Click the "Add item" button to add additional line items to the same transaction. This is useful for recording orders or invoices that contain several products at once.</p>

            <h2>Editing and Deleting Transactions</h2>
            <p>To modify an existing transaction, click the edit button in the actions column on the Expenses or Revenue page. You can update any field, add or remove line items, and attach or replace receipts. To delete a transaction, click the delete button and confirm.</p>

            <h2>Filtering and Searching</h2>
            <p>Use the date range filter at the top of the page to view transactions within a specific period. You can also search by product name, category, or customer to quickly find the records you need.</p>

            <h2>Transaction Details</h2>
            <p>Each transaction records the following information:</p>
            <ul>
                <li><strong>Product(s):</strong> The items involved in the transaction</li>
                <li><strong>Amount:</strong> Quantity and unit price per item</li>
                <li><strong>Date:</strong> When the transaction occurred</li>
                <li><strong>Category:</strong> For organizing and reporting</li>
                <li><strong>Customer/Supplier:</strong> The party involved (optional)</li>
                <li><strong>Receipt:</strong> An attached image or document (optional)</li>
            </ul>

            <div class="info-box">
                <strong>Tip:</strong> Transactions automatically update your analytics dashboard, and the stock of any product with Track Inventory on. When that product is stocked at more than one location, the line asks which <a class="link" href="locations.php">location</a> to take stock from or add it to. See <a class="link" href="inventory.php">Inventory Management</a>.
            </div>

            <div class="page-navigation">
                <a href="report-generator.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Report Generator</span>
                </a>
                <a href="invoicing.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Invoicing & Payments &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
