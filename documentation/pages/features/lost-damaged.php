<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Lost & Damaged Inventory';
$pageDescription = 'Learn how to record and track lost or damaged inventory items in Argo Books.';
$currentPage = 'lost-damaged';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Track items that have been lost, stolen, or damaged to keep your inventory records accurate and maintain a clear audit trail of inventory losses.</p>

            <h2>Lost & Damaged Dashboard</h2>
            <p>The Lost / Damaged page (under Tracking in the sidebar) displays four summary cards at the top:</p>
            <ul>
                <li><strong>Total Lost/Damaged:</strong> Total number of recorded loss events</li>
                <li><strong>Lost Items:</strong> Items recorded as lost or stolen</li>
                <li><strong>Damaged Items:</strong> Items recorded as damaged</li>
                <li><strong>Total Loss Value:</strong> Combined financial value of all losses</li>
            </ul>

            <h2>Recording a Loss</h2>
            <p>A loss is recorded from the expense or revenue transaction the items came from, so that transaction needs to exist first. See <a class="link" href="sales-tracking.php">Expense/Revenue Tracking</a>.</p>
            <ol class="steps-list">
                <li>Go to "Expenses" or "Revenue" in the sidebar</li>
                <li>Find the transaction and click "Mark as Lost / Damaged" in its action buttons</li>
                <li>Select a reason. For expenses: Damaged in transit, Defective product, Lost in warehouse, Damaged during storage, Expired, or Other. For revenue, Customer damaged replaces Expired.</li>
                <li>Add any notes and confirm</li>
            </ol>
            <p>The record uses the transaction's product and total, and today's date. It then appears on the "Lost / Damaged" page under Tracking.</p>

            <h2>What Happens When You Record a Loss</h2>
            <ul>
                <li><strong>Stock:</strong> Stock levels don't change. If the items should come off your stock, record a Remove adjustment on the <a class="link" href="inventory.php">Adjustments</a> page.</li>
                <li><strong>Financial Tracking:</strong> The loss value is recorded for accurate bookkeeping and reporting</li>
                <li><strong>Analytics:</strong> Losses appear in the Analytics dashboard under the Losses tab, helping you identify patterns</li>
            </ul>

            <h2>Viewing Loss History</h2>
            <p>The table shows all recorded losses with the product name, date, reason, loss value, and action buttons. Use the search and date range filter to find specific records.</p>

            <div class="page-navigation">
                <a href="returns.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Returns</span>
                </a>
                <a href="receipts.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Receipt Management &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
