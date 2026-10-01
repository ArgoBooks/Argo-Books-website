<?php
require_once __DIR__ . '/../../../config/pricing.php';
require_once __DIR__ . '/../../../resources/icons.php';
$pricing = get_pricing_config();
$pageTitle = 'Import from QuickBooks';
$pageDescription = 'Move your customers, suppliers, products, employees and transactions out of QuickBooks and into Argo Books. Which reports to export, and what each one becomes.';
$currentPage = 'quickbooks-import';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Argo Books imports your QuickBooks data from the reports QuickBooks already knows how to export. There is no connection to set up and no password to hand over: you export files from QuickBooks, you drop them into Argo Books, and the AI importer works out what each column means. This page lists exactly which reports to export and what each one becomes once it arrives.</p>

            <div class="info-box">
                <strong>Export as Excel, not CSV.</strong> QuickBooks reports are not plain tables. They carry a few heading lines at the top, group headings in the middle, and subtotal rows mixed in with the data. Argo Books reads around all of that in an Excel file. A CSV is treated as a plain table, so those extra rows can be read as if they were transactions, which inflates your totals. Wherever QuickBooks offers both, choose Excel.
            </div>

            <h2>What Comes Across</h2>
            <p>Argo Books is built around transactions rather than a chart of accounts, so the import brings over the things you work with day to day:</p>
            <ul>
                <li><strong>Customers</strong>, with their contact details</li>
                <li><strong>Suppliers</strong>, the same</li>
                <li><strong>Products and services</strong>, including prices</li>
                <li><strong>Employees</strong>, so payroll has people to pay</li>
                <li><strong>Invoices</strong>, including the ones still unpaid</li>
                <li><strong>Revenue and expenses</strong> for whatever period you choose to bring across</li>
            </ul>

            <h2>What Does Not</h2>
            <p>Three things are worth knowing before you start, so nothing is a surprise afterwards:</p>
            <ul>
                <li><strong>Your chart of accounts and journal entries.</strong> Argo Books works out your Income Statement, Balance Sheet and General Ledger from your transactions, so there is no separate list of accounts for them to land in. Export transaction reports rather than the General Ledger or Journal.</li>
                <li><strong>Opening balances.</strong> There is no place to enter what your bank account held on the day you switched, so your figures reflect the transactions you bring across and nothing earlier.</li>
                <li><strong>Pay history.</strong> Employees come across, but their year-to-date earnings and deductions do not, so a mid-year switch means entering those by hand before your first pay run.</li>
            </ul>
            <p>Because of the first two, the cleanest time to switch is the start of your financial year. Our guide on <a href="/guides/how-to-switch-from-quickbooks/" class="link">how to switch from QuickBooks</a> covers choosing a date and keeping a permanent archive of the years you leave behind.</p>

            <h2>What to Export from QuickBooks Online</h2>
            <ol class="steps-list">
                <li><strong>Your lists.</strong> Go to <strong>Settings</strong>, then <strong>Export data</strong> under Tools, then <strong>Export to Excel</strong>. This gives you a single file holding your customers, suppliers and employees.</li>
                <li><strong>Your products.</strong> Go to <strong>Products and services</strong>, then <strong>Run reports</strong>, then export. This is not included in the file above.</li>
                <li><strong>Your open invoices.</strong> Run the <strong>Open Invoices</strong> report and export it, so anything still owed to you arrives ready to be paid.</li>
                <li><strong>Your transactions.</strong> Run a sales or expense detail report covering the period you are bringing across, and export that too.</li>
            </ol>

            <h2>What to Export from QuickBooks Desktop</h2>
            <ol class="steps-list">
                <li><strong>Your lists.</strong> Run the customer contact list, the supplier contact list and the item listing, and export each one to Excel.</li>
                <li><strong>Your open invoices.</strong> Run the open invoices report and export it.</li>
                <li><strong>Your transactions.</strong> Run a sales or transaction detail report for the period you want, and export that.</li>
                <li><strong>Keep them together.</strong> Saving everything into one folder means you can select the whole set at once in the next step.</li>
            </ol>
            <p>QuickBooks moves its menus around between versions, so the export button is not always in the same place. It is usually near the top of a report or list screen, often behind a small spreadsheet or download icon.</p>

            <h2>Bringing the Files In</h2>
            <ol class="steps-list">
                <li>Open the <strong>Import</strong> menu, from the File menu or the Import button on the Expenses or Revenue page, and choose <strong>QuickBooks</strong></li>
                <li>Pick QuickBooks Online or QuickBooks Desktop, so the steps on screen match the one you came from</li>
                <li>Add every file you exported. You can select them all at once</li>
                <li>Argo Books reads each file, works out what the columns mean, and shows you what it found</li>
                <li>Check the figures, change anything that looks wrong, and confirm</li>
            </ol>
            <p>Nothing is written to your books until you confirm, so you can look at what was understood before committing to it.</p>

            <h2>Checking the Import Worked</h2>
            <p>Run one check rather than reading every row. Open <strong>Reports</strong>, generate the <strong>Income Statement</strong> for the period you imported, and compare the totals against the same report in QuickBooks. If revenue and expenses match, the import is good. If a figure is roughly double, a subtotal row was read as a transaction, which is what exporting as Excel instead of CSV prevents.</p>

            <h2>Usage</h2>
            <p>Reading a file with AI counts as one spreadsheet import. Free accounts get <?= (int) $pricing['ai_import_monthly_limit'] ?> per month and Premium gets <?= (int) $pricing['premium_ai_import_monthly_limit'] ?>. A QuickBooks migration usually means several files, so each one counts separately.</p>

            <div class="page-navigation">
                <a href="spreadsheet-import.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; AI Spreadsheet Import</span>
                </a>
                <a href="spreadsheet-export.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Spreadsheet Export &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
