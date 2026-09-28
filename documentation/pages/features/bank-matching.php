<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Bank Matching';
$pageDescription = 'Import a bank statement and match each line against your recorded revenue and expenses to verify your books and catch anything missing or duplicated.';
$currentPage = 'bank-matching';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Bank Matching lets you import a bank statement and check it against what you have recorded in Argo Books. Each line on the statement is matched to your recorded revenue and expenses, so you can confirm your books are complete and catch anything that is missing or entered twice. There are no bank connections to set up. You import a CSV or Excel statement that you download from your bank.</p>

            <p>Bank statement lines are reference data only. Importing a statement never creates expenses or revenue, and it never changes your transactions. The only thing matching does is mark a record as matched to a statement line.</p>

            <div class="info-box">
                <strong>Looking to add transactions instead?</strong> Bank Matching only verifies your books. To <em>create</em> expenses and revenue from a statement, use <a href="bank-statement-import.php" class="link">Bank Statement Import</a>.
            </div>

            <h2>How Bank Matching Works</h2>
            <ol class="steps-list">
                <li>Open <strong>Bank Matching</strong> from the sidebar, under Import</li>
                <li>Click <strong>Import statement</strong> and choose a CSV, Excel or PDF statement from your bank</li>
                <li>Argo Books reads the columns and automatically matches each line against your books</li>
                <li>Review the results: confident matches are confirmed for you, likely matches are suggested for you to accept, and anything left over is flagged as unmatched</li>
            </ol>

            <h2>Importing a Statement</h2>
            <p>The importer reads CSV, Excel and PDF bank statements and maps their columns to the fields Bank Matching needs:</p>
            <div class="two-column-list">
                <ul>
                    <li>Date</li>
                    <li>Description</li>
                    <li>Amount (signed)</li>
                </ul>
                <ul>
                    <li>Debit / Credit (separate columns)</li>
                    <li>Balance</li>
                    <li>Reference</li>
                </ul>
            </div>
            <p>Column detection happens locally and instantly, recognizing common header names (and tolerating a few preamble rows). If your statement uses unusual or cryptic headers that can't be recognized, the AI smart importer is used as a backup to map the columns. A PDF statement is always read with AI. Either way, it counts as one of your monthly bank statement imports. A statement only needs a date and either an Amount column or Debit/Credit columns.</p>

            <h2>Match Statuses</h2>
            <ul>
                <li><strong>Matched:</strong> Confirmed and linked to a recorded transaction.</li>
                <li><strong>Suggested:</strong> One or more records were found. Accept the best one or choose another.</li>
                <li><strong>Unmatched:</strong> No matching record was found. This usually means a transaction you forgot to record.</li>
                <li><strong>Ignored:</strong> A line you have set aside (for example an internal transfer) that you do not want to track.</li>
            </ul>

            <h2>Missing from Statement Tab</h2>
            <p>The <strong>Missing from statement</strong> tab is the other side of the check: it lists your recorded revenue and expenses that no statement line matched. These are entries that may be missing from your bank statement, or that you may have recorded twice. Records with the same amount around the same date are flagged as a <strong>possible duplicate</strong>. You can search these records and filter them by date range and record type.</p>

            <h2>List and Calendar Views</h2>
            <p>Both tabs have a <strong>List</strong> and a <strong>Calendar</strong> toggle in the top right of the table, next to the search and filter buttons. List is the detailed table described above. Calendar gives you a year-at-a-glance overview, so you can see which months are fully checked and which still need attention.</p>
            <p>The calendar shows a grid of all twelve months for one year. Use the arrows beside the year to step between years. Each month tile shows a count of how many lines (or records) are matched out of the total for that month, and is colored by status:</p>
            <ul>
                <li><strong>Green (Fully matched):</strong> every line that month is matched, or set aside.</li>
                <li><strong>Amber (Partially matched):</strong> some lines that month are still outstanding.</li>
                <li><strong>Red (Not matched):</strong> nothing that month is matched yet.</li>
                <li><strong>Grey:</strong> nothing recorded for that month.</li>
            </ul>

            <div class="page-navigation">
                <a href="bank-statement-import.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Bank Statement Import</span>
                </a>
                <a href="rental.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Rental Management &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
