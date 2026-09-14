<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Locations';
$pageDescription = 'Learn how to add the warehouses, stores and other places you keep stock in Argo Books, and how stock is tracked per location.';
$currentPage = 'locations';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Locations are the places you keep stock, such as a warehouse or a store. Argo Books tracks stock per product and per location, so you need at least one location before you can add stock by hand.</p>

            <h2>Adding a Location</h2>
            <ol class="steps-list">
                <li>Go to "Locations" under the Inventory section in the sidebar</li>
                <li>Click "Add Location"</li>
                <li>Enter the location name</li>
                <li>Optionally enter a code (one is generated if you leave it empty) and pick a type: Warehouse, Storage Facility, Factory, Retail Store, or Distribution Center</li>
                <li>Add the address and any notes</li>
                <li>Save the location</li>
            </ol>

            <div class="info-box">
                <strong>Tip:</strong> You can also add a location without leaving the page you're on: the location picker on Stock Levels has a "Create one" link.
            </div>

            <h2>How Locations Are Used</h2>
            <ul>
                <li><strong>Stock Levels:</strong> Each stock record is a product at one location. Adding an item on Stock Levels asks for both. See <a class="link" href="inventory.php">Inventory Management</a>.</li>
                <li><strong>Transfers:</strong> Move stock from one location to another with the transfer button on a Stock Levels row</li>
                <li><strong>Transactions:</strong> When a tracked product is stocked at more than one location, each expense or revenue line asks which location its stock comes from or goes to. See <a class="link" href="sales-tracking.php">Expense/Revenue Tracking</a>.</li>
                <li><strong>Rentals:</strong> A rental item rents out the stock at one location. See <a class="link" href="rental.php">Rental Management</a>.</li>
            </ul>

            <h2>Managing Locations</h2>
            <p>From the Locations page you can edit or delete a location with the buttons on its row, and filter the list by type or status. A location that still has stock records or stock transfers can't be deleted.</p>

            <div class="page-navigation">
                <a href="inventory.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Inventory Management</span>
                </a>
                <a href="purchase-orders.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Purchase Orders &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
