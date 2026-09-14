<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Inventory Management';
$pageDescription = 'Learn how to track stock levels, set reorder points, and manage inventory with Argo Books inventory management features.';
$currentPage = 'inventory';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Keep complete control over your inventory with real-time stock tracking, automatic alerts, and intelligent reorder recommendations.</p>

            <h2>Stock Tracking</h2>
            <p>Monitor your inventory levels in real-time:</p>
            <ul>
                <li><strong>Current Stock:</strong> See how many units of each product you have on hand</li>
                <li><strong>Stock History:</strong> Track inventory changes over time</li>
            </ul>

            <h2>Setting Up Inventory</h2>
            <p>Stock is kept per product and per location. A product has stock only once it has a stock record, so setting up takes two steps:</p>
            <ol class="steps-list">
                <li>Go to "Expense products" or "Revenue products", open or create the product, and turn on "Track Inventory". You can also set its unit, reorder point and overstock threshold there. See <a class="link" href="product-management.php">Product Management</a>.</li>
                <li>Go to "Stock Levels" under Inventory, click "Add Item", pick the product and a location, and enter the "Initial Quantity" you have on hand</li>
            </ol>
            <p>Stock Levels needs a location. A new company has none, so add one on the <a class="link" href="locations.php">Locations</a> page, or use the "Create one" link in the location picker.</p>
            <p>You don't have to add the stock record by hand. Recording a purchase of a tracked product, receiving a <a class="link" href="purchase-orders.php">purchase order</a> for it, or importing an Inventory sheet creates one for you.</p>

            <div class="info-box">
                <strong>Note:</strong> For a product with Track Inventory on, buying stock adds to your stock instead of counting as an expense, and what that stock cost comes off your profit when it sells. The Balance Sheet values what's on hand. See <a class="link" href="../reference/how-numbers-are-calculated.php#inventory-balance-sheet">How Numbers Are Calculated</a>.
            </div>

            <h2>Reorder Points</h2>
            <p>Set stock thresholds to stay on top of your inventory:</p>
            <ul>
                <li><strong>Reorder Point:</strong> Get alerted when stock falls below this quantity</li>
                <li><strong>Overstock Threshold:</strong> Get notified when stock exceeds this level</li>
            </ul>

            <h2>Low Stock Alerts</h2>
            <p>Argo Books automatically monitors your inventory and notifies you when action is needed:</p>
            <ul>
                <li><strong>Dashboard Alerts:</strong> Low stock items appear on your main dashboard</li>
                <li><strong>Notification Center:</strong> View all inventory alerts in one place</li>
            </ul>

            <h2>Inventory Adjustments</h2>
            <p>To change stock by hand, click "New Adjustment" on the "Adjustments" page, or the adjust button on a row in Stock Levels. Pick one of three types:</p>
            <ul>
                <li><strong>Add:</strong> Increase stock, such as after finding extra units</li>
                <li><strong>Remove:</strong> Decrease stock, such as for damaged, lost or expired items</li>
                <li><strong>Set:</strong> Replace the count with what a physical stock count found</li>
            </ul>
            <p>Each adjustment keeps a reason and an optional reference number, so the Adjustments page doubles as an audit trail.</p>

            <h2>Moving Stock Between Locations</h2>
            <p>Click the transfer button on a row in Stock Levels, choose the location to move it to, and enter the quantity.</p>

            <h2>Automatic Stock Updates</h2>
            <p>For products with Track Inventory on, stock is adjusted automatically when you:</p>
            <ul>
                <li>Record a revenue transaction (stock decreases)</li>
                <li>Record an expense transaction (stock increases)</li>
                <li>Receive a purchase order (stock increases)</li>
                <li>Bring in sales or purchases from Stripe or the Argo Books API</li>
                <li>Rent items out or take them back (see <a class="link" href="rental.php">Rental Management</a>)</li>
            </ul>
            <p><a class="link" href="returns.php">Returns</a> and <a class="link" href="lost-damaged.php">lost or damaged</a> records don't change stock. Record an adjustment if the items should come off or go back on the shelf.</p>

            <h2>Inventory Dashboard</h2>
            <p>Monitor your inventory at a glance with key metrics:</p>
            <ul>
                <li><strong>Total Units:</strong> Total inventory across all products</li>
                <li><strong>In Stock:</strong> Products with healthy stock levels</li>
                <li><strong>Low Stock:</strong> Products below their reorder point</li>
                <li><strong>Out of Stock:</strong> Products with zero inventory</li>
                <li><strong>Overstock:</strong> Products above their overstock threshold</li>
            </ul>

            <div class="page-navigation">
                <a href="suppliers.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Supplier Management</span>
                </a>
                <a href="locations.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Locations &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
