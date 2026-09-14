<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Product Management';
$pageDescription = 'Learn how to manage products, services and categories in Argo Books, and how the expense and revenue sides of your catalog are kept separate.';
$currentPage = 'product-management';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Learn how to create categories and manage products in Argo Books.</p>

            <h2>Expense Side and Revenue Side</h2>
            <p>Argo Books keeps the things you <em>buy</em> separate from the things you <em>sell</em>. That is why the sidebar has two of each:</p>
            <ul>
                <li><strong>Expense categories</strong> and <strong>Expense products</strong>, under the Expenses section, for what you purchase</li>
                <li><strong>Revenue categories</strong> and <strong>Revenue products</strong>, under the Revenue section, for what you sell</li>
            </ul>
            <p>The two pages work identically. Which one you use decides whether the item shows up when you record an expense or when you record a revenue entry. Rental items have their own category type and live under Rentals.</p>

            <h2>Why Categories Matter</h2>
            <p>Categories are the foundation of how Argo Books organizes your data. Every product belongs to a category. By grouping products into categories, you can:</p>
            <ul>
                <li><strong>Track spending by area:</strong> See how much you're spending on "Office Supplies" vs "Raw Materials" at a glance</li>
                <li><strong>Generate meaningful reports:</strong> Reports and analytics break down data by category, giving you actionable insights</li>
                <li><strong>Stay organized as you grow:</strong> As your product catalog grows, categories keep everything manageable</li>
            </ul>
            <p>For example, a bakery might create expense categories like "Ingredients," "Packaging," and "Equipment" to track different types of spending separately, and revenue categories like "Bread," "Cakes," and "Catering" for what it sells.</p>

            <h2>Creating Categories</h2>
            <ol class="steps-list">
                <li>Go to "Expense categories" or "Revenue categories" in the sidebar</li>
                <li>Click "Add Category"</li>
                <li>Enter the category name</li>
                <li>Optionally add a description, pick a colour and an emoji icon so it stands out in lists and charts</li>
                <li>Optionally choose a parent category to nest it underneath an existing one</li>
                <li>Save the category</li>
            </ol>

            <div class="info-box">
                <strong>Tip:</strong> Create your categories before adding products. This makes it easier to assign each product to the right category as you go.
            </div>

            <h2>Adding Products</h2>
            <ol class="steps-list">
                <li>Go to "Expense products" or "Revenue products" in the sidebar</li>
                <li>Click "Add Product"</li>
                <li>Enter the product name</li>
                <li>Select a category</li>
                <li>Fill in any other details (SKU, pricing, supplier, inventory thresholds)</li>
                <li>Save the product</li>
            </ol>

            <div class="info-box">
                <strong>Tip:</strong> You don't have to add products here first. Type a new item and category on an expense or revenue line and Argo Books creates both when you save, with the item priced at that line's price. Come back here later to fill in the other details.
            </div>

            <h2>Product Fields</h2>
            <p>When adding or editing a product, you can set the following:</p>
            <ul>
                <li><strong>Name:</strong> The product or service name</li>
                <li><strong>SKU:</strong> Your own stock code, used for searching and shown on stock levels</li>
                <li><strong>Item Type:</strong> Whether this is a Product or a Service</li>
                <li><strong>Category:</strong> Which category the product belongs to</li>
                <li><strong>Supplier:</strong> The vendor you purchase this product from</li>
                <li><strong>Description:</strong> Additional notes about the product</li>
                <li><strong>Unit Price:</strong> The default price used when you add this item to a transaction or invoice</li>
                <li><strong>Cost Price:</strong> What the item costs you, as a reference figure</li>
                <li><strong>Tax Rate:</strong> The default tax rate applied when the item is used</li>
                <li><strong>Track Inventory:</strong> Whether stock levels are kept for this item. Turning it on doesn't add any stock by itself: add it on Stock Levels or record a purchase. See <a class="link" href="inventory.php">Inventory Management</a>.</li>
                <li><strong>Reorder Point:</strong> Minimum stock level before a low-stock alert is triggered</li>
                <li><strong>Overstock Threshold:</strong> Maximum stock level before an overstock alert appears</li>
                <li><strong>Status:</strong> Active or inactive, so retired items stay in your history without cluttering the picker</li>
            </ul>

            <div class="info-box">
                <strong>Note:</strong> Unit price, cost price and tax rate are defaults, not fixed values. You can change any of them on an individual transaction or invoice line without editing the product.
            </div>

            <h2>Managing Existing Products</h2>
            <p>From either products page, you can edit or delete any product using the action buttons. Changes to a product's details (like category or description) apply going forward and do not modify past transactions.</p>

            <div class="info-box">
                <strong>Reporting:</strong> Argo Books reports revenue per product, not profit per product, because there is often no reliable cost for a specific item sold. <a class="link" href="../reference/how-numbers-are-calculated.php#sales-by-product">How Numbers Are Calculated</a> explains the reasoning.
            </div>

            <div class="page-navigation">
                <a href="customers.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Customer Management</span>
                </a>
                <a href="suppliers.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Supplier Management &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
