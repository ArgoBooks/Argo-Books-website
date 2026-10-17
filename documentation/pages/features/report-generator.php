<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Report Generator';
$pageDescription = 'Learn how to generate professional single and multi-page reports with charts, accounting tables, and analytics in Argo Books using the 3-step wizard.';
$currentPage = 'report-generator';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Create professional, customized reports with charts, accounting tables, and analytics for presentations and financial analysis. The Report Generator uses a simple 3-step wizard to guide you through the process and supports multi-page reports for more detailed documents.</p>

            <h2>How to Generate a Report</h2>
            <ol class="steps-list">
                <li>Go to "Reports" in the navigation menu, under Main</li>
                <li>Follow the 3-step wizard to create your custom report</li>
            </ol>

            <h2>Step 1: Template & Settings</h2>
            <p>Choose a starting point for your report.</p>
            <ul>
                <li><strong>Select a Template:</strong> Choose from pre-built templates including chart-based reports and accounting report templates, or create your own</li>
                <li><strong>Name Your Report:</strong> Enter a name for your report</li>
                <li><strong>Set Date Range:</strong> Use quick presets (Last Month, Last 3 Months, etc.) or choose start and end dates</li>
            </ul>

            <h3>Accounting Report Templates</h3>
            <p>The following accounting report templates are available with pre-configured layouts and structured financial tables:</p>
            <ul>
                <li><strong>Income Statement:</strong> Revenue, expenses, and net income over the selected period</li>
                <li><strong>Balance Sheet:</strong> Assets, liabilities, and equity at a point in time</li>
                <li><strong>Cash Flow Statement:</strong> Cash coming in from sales and invoice payments, and going out on expenses, over the selected period</li>
                <li><strong>General Ledger:</strong> Every transaction, listed by category with its debit or credit and a running balance</li>
                <li><strong>Accounts Receivable Aging:</strong> Outstanding customer payments grouped by age</li>
                <li><strong>Tax Summary:</strong> Tax-related totals for the selected period</li>
                <li><strong>Sales by Product:</strong> Revenue and units sold, broken down per product</li>
            </ul>

            <div class="info-box">
                <strong>Tip:</strong> Accounting reports are generated directly from your transaction records, with no Chart of Accounts setup required.
            </div>

            <div class="info-box">
                <strong>Note:</strong> Reports use the accrual basis, counting all invoiced revenue whether or not it has been paid, while the Dashboard and Analytics count only money collected. That is why the two can disagree. See <a class="link" href="../reference/how-numbers-are-calculated.php#cash-vs-accrual">How Numbers Are Calculated</a>.
            </div>

            <h2>Step 2: Layout Designer</h2>
            <p>Design your report using drag-and-drop functionality.</p>
            <ul>
                <li><strong>Add Charts:</strong> Choose from 30+ chart types across categories like Revenue, Expenses, Financial, Geographic, Customers, Returns, and more</li>
                <li><strong>Add Elements:</strong> Include text labels, images, date ranges, summary statistics, and tables</li>
                <li><strong>Add Accounting Tables:</strong> Insert structured financial tables with section headers, subtotals, and grand totals, available when using accounting report templates</li>
                <li><strong>Drag and Drop:</strong> Click and drag elements to position them on the canvas</li>
                <li><strong>Resize:</strong> Select an element and drag the corner handles to resize</li>
                <li><strong>Customize:</strong> Use the properties panel to adjust colors, fonts, borders, and alignment</li>
                <li><strong>Alignment Tools:</strong> Align and distribute multiple elements using the toolbar</li>
                <li><strong>Undo/Redo:</strong> Use Ctrl+Z and Ctrl+Y (Cmd+Z and Cmd+Y on a Mac) to undo or redo changes. See <a class="link" href="../reference/keyboard_shortcuts.php">Keyboard Shortcuts</a> for the rest</li>
            </ul>

            <h3>Multi-Page Reports</h3>
            <p>Reports can span multiple pages for more detailed documents.</p>
            <ul>
                <li><strong>Add Page:</strong> Use the "Add Page" button in the bottom to add a new page</li>
                <li><strong>Delete Page:</strong> Use the "Delete Page" button to remove the current page</li>
                <li><strong>Page Navigation:</strong> Scroll through pages in the designer. All pages are stacked vertically</li>
            </ul>

            <h2>Step 3: Preview and Export</h2>
            <p>Review your report and export in your preferred format.</p>
            <ul>
                <li><strong>Preview:</strong> See how your finished report will look. All pages are displayed in a scrollable view</li>
                <li><strong>Page Numbers:</strong> Multi-page reports automatically display "Page X of Y" for easy reference</li>
                <li><strong>Export Format:</strong> Choose PNG, JPEG, or PDF</li>
                <li><strong>Export:</strong> Select your save location and click "Export"</li>
            </ul>

            <div class="page-navigation">
                <a href="predictive-analytics.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Predictive Analytics</span>
                </a>
                <a href="sales-tracking.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Expense/Revenue Tracking &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
