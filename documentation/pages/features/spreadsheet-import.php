<?php
require_once __DIR__ . '/../../../config/pricing.php';
require_once __DIR__ . '/../../../resources/icons.php';
$pricing = get_pricing_config();
$pageTitle = 'Spreadsheet Import';
$pageDescription = 'Import data from any Excel or CSV spreadsheet into Argo Books. AI automatically detects your data types and maps columns, with no reformatting needed.';
$currentPage = 'spreadsheet-import';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Import your existing business data from any Excel (.xlsx) or CSV file into Argo Books. The AI-powered importer automatically detects what type of data you have and maps your columns, so there's no need to reformat your spreadsheet or follow a template.</p>

            <h2>How AI Import Works</h2>
            <p>The AI importer analyzes your spreadsheet and figures out the rest. Here's what happens when you import a file:</p>
            <ol class="steps-list">
                <li>Click <strong>File &gt; Import &gt; Spreadsheet</strong>, then choose your file</li>
                <li>AI analyzes each sheet, detects the data type (customers, products, expenses, etc.), and maps your columns to Argo Books fields</li>
                <li>Review the mapping. You'll see confidence scores for each match, and can adjust anything that doesn't look right</li>
                <li>Click <strong>Import</strong> to bring everything in</li>
            </ol>

            <h2>Supported Data Types</h2>
            <p>The importer can detect and import all of the following data types from a single file:</p>
            <div class="two-column-list">
                <ul>
                    <li>Customers</li>
                    <li>Suppliers</li>
                    <li>Products</li>
                    <li>Categories</li>
                    <li>Expenses</li>
                    <li>Assets</li>
                    <li>Revenue</li>
                    <li>Invoices</li>
                    <li>Payments</li>
                    <li>Inventory</li>
                </ul>
                <ul>
                    <li>Employees</li>
                    <li>Locations</li>
                    <li>Rental Inventory</li>
                    <li>Rental Records</li>
                    <li>Recurring Invoices</li>
                    <li>Stock Adjustments</li>
                    <li>Purchase Orders</li>
                    <li>Returns</li>
                    <li>Lost / Damaged Items</li>
                </ul>
            </div>

            <p>Invoices and purchase orders can also carry their line items on a separate sheet, which is detected and linked to the parent records automatically.</p>

            <div class="info-box">
                <strong>Note:</strong> Pay run history is export only. An approved pay run's figures are frozen so a stub reprinted next year still matches the one the employee was handed, so a Pay Runs sheet is recognised and skipped rather than imported.
            </div>

            <h2>Column Mapping</h2>
            <p>AI reads each sheet's column names and a sample of its rows, and works out which Argo Books field each column holds. How your rows are then brought in depends on what it finds:</p>
            <ul>
                <li><strong>Direct Mapping</strong>: When the values already fit, such as a "Customer Name" column holding names, Argo Books copies each row into the matched fields itself. The rows aren't sent to the AI, so this is fast and gives the same result every time.</li>
                <li><strong>AI Processing</strong>: When the values need reworking, such as dates in an unusual format or a whole address in one cell, the AI rewrites the rows into the right fields. The review dialog shows when this will happen.</li>
            </ul>

            <h2>Reviewing the Mapping</h2>
            <p>Before importing, you get a review dialog showing:</p>
            <ul>
                <li><strong>Detected type</strong> for each sheet (e.g., "Customers", "Expenses"). You can change this if the AI got it wrong</li>
                <li><strong>Confidence score</strong> for each column mapping: high (>90%), medium (70-90%), or low (&lt;70%)</li>
                <li><strong>Unmapped columns</strong>: any source columns that couldn't be matched, and any target fields with no data</li>
                <li><strong>Row count</strong> for each sheet</li>
                <li><strong>Processing tier</strong>: whether Direct Mapping or AI Processing will be used</li>
            </ul>
            <p>You can include or exclude individual sheets from the import.</p>

            <h2>Validation</h2>
            <p>After mapping, the importer validates your data and shows any issues found:</p>
            <ul>
                <li><strong>Auto-fixable issues</strong>: Missing categories, customers, or suppliers referenced in your data will be created automatically</li>
                <li><strong>Manual issues</strong>: Invalid values that need your attention (e.g., unrecognized date formats)</li>
            </ul>
            <p>You can choose to import anyway or fix the issues first.</p>

            <h2>Supported File Formats</h2>
            <ul>
                <li><strong>Excel (.xlsx)</strong>: Supports multi-sheet workbooks. Each sheet is analyzed independently.</li>
                <li><strong>CSV (.csv)</strong>: Single data type per file. The AI detects which type automatically.</li>
            </ul>

            <h2>Usage Limits</h2>
            <p>Free accounts get <?= (int) $pricing['ai_import_monthly_limit'] ?> AI-powered imports per month and Premium gets <?= (int) $pricing['premium_ai_import_monthly_limit'] ?>. Each file you import (regardless of the number of sheets) counts as one import.</p>

            <div class="page-navigation">
                <a href="receipt-scanning.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; AI Receipt Scanning</span>
                </a>
                <a href="quickbooks-import.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Import from QuickBooks &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
