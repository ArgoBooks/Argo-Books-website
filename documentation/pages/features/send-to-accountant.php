<?php
require_once __DIR__ . '/../../../config/pricing.php';
require_once __DIR__ . '/../../../resources/icons.php';
$pricing = get_pricing_config();
$pageTitle = 'Send to Accountant';
$pageDescription = 'Send your accountant a full year of books in one go: the financial statements as PDFs, every transaction as a spreadsheet, and the receipts behind them.';
$currentPage = 'send-to-accountant';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>At year end your accountant asks for the same things every time: the statements, the
            transactions behind them, and the receipts. This gathers all three for a period you choose and
            hands them over as one file, or emails them directly.</p>

            <h2>Where to Find It</h2>
            <p>Two places, both doing the same thing:</p>
            <ul>
                <li>The <strong>File</strong> menu, under Import and Export As</li>
                <li>The <strong>Reports</strong> page, beside the Templates and Custom tabs</li>
            </ul>

            <h2>Choosing the Period</h2>
            <p>Pick a year from the list, or <strong>Custom range</strong> for anything else. The list runs
            back to your earliest records, up to fifteen years, and starts on last year, since books usually
            go to the accountant once the year has closed.</p>

            <h2>What Goes In</h2>
            <p>Three parts, each of which can be left out:</p>
            <ul>
                <li><strong>Reports.</strong> Income Statement, Balance Sheet, Cash Flow Statement, General
                    Ledger and Tax Summary, as PDFs</li>
                <li><strong>Transactions.</strong> Every revenue, expense, invoice and payment in the period,
                    one sheet each, in a single spreadsheet</li>
                <li><strong>Receipts.</strong> The receipt files attached to those transactions, each named by
                    its date and the transaction it belongs to</li>
            </ul>
            <p>Receipts are chosen by the transaction's date, which is the date your accountant books it on,
            and a receipt shared by two transactions is included once.</p>
            <p>A short readme goes in listing what is there, so your accountant can see the contents without
            opening anything.</p>

            <h2>Saving or Emailing</h2>
            <p>Save the pack as a zip and send it however you like, or have Argo Books email it straight to
            your accountant.</p>

            <div class="info-box">
                <p><strong>Note:</strong> Email has a size limit, so a year with a lot of receipts may be too
                large to send that way. Argo Books tells you before you send rather than after, and saving the
                zip has no limit. It also tells you when the period you picked has nothing in it.</p>
            </div>

            <h2>What It Does Not Do</h2>
            <p>This is a copy of your books, not a handover of them. Nothing leaves your computer except the
            files you choose to send, your company file itself is never included, and your accountant does not
            need Argo Books to open any of it.</p>

            <div class="page-navigation">
                <a href="spreadsheet-export.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Spreadsheet Export</span>
                </a>
                <a href="history-modal.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Version History &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
