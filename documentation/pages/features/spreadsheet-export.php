<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Spreadsheet Export';
$pageDescription = 'Learn how to export your Argo Books data to Excel spreadsheets for backup, analysis, or sharing with others.';
$currentPage = 'spreadsheet-export';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Export your data to Excel spreadsheets for backup, analysis, or sharing with business partners.</p>

            <h2>Exporting Your Data</h2>
            <ol class="steps-list">
                <li>Click "File > Export As..."</li>
                <li>Select the "Spreadsheet (XLSX)" tab</li>
                <li>Set a date range to filter which records to include</li>
                <li>Select which data categories to export</li>
                <li>Choose your preferred currency for the export</li>
                <li>Click "Export" and select a location to save your file</li>
            </ol>

            <h2>Currency Conversion</h2>
            <p>When exporting, you can choose any of the <a class="link" href="../reference/supported-currencies.php">supported currencies</a>. The system will:</p>
            <ul>
                <li>Convert all monetary values to your chosen currency using historical exchange rates</li>
                <li>Display values with proper currency formatting</li>
                <li>Add a note at the top indicating which currency is being used</li>
            </ul>

            <div class="info-box">
                <strong>Multi-Item Transactions:</strong> Transactions with multiple items are exported with the main transaction details on the first row, and additional items on subsequent rows with empty transaction ID cells.
            </div>

            <h2>Chart Export</h2>
            <p>Charts from the Analytics Dashboard can also be exported to Excel with full data:</p>
            <ol class="steps-list">
                <li>Right-click any chart in the Analytics Dashboard</li>
                <li>Select "Export to Excel"</li>
                <li>Choose a save location</li>
            </ol>

            <div class="page-navigation">
                <a href="spreadsheet-import.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Spreadsheet Import</span>
                </a>
                <a href="send-to-accountant.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Send to Accountant &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
