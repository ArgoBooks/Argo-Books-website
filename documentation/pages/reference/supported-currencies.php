<?php
require_once __DIR__ . '/../../../resources/icons.php';
// The table below is built from the website's currency list, so it cannot drift
// from the pickers or from the desktop app's CurrencyInfo.cs that mirrors it.
require_once __DIR__ . '/../../../shared/currencies.php';
$currencies = argo_currencies_all();
$currencyCount = count($currencies);
$pageTitle = 'Supported Currencies';
$pageDescription = "View the list of {$currencyCount} supported currencies in Argo Books for import, export, and real-time conversion.";
$currentPage = 'supported-currencies';
$pageCategory = 'reference';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Argo Books supports <?php echo $currencyCount; ?> currencies with exchange rate conversion. You can set your company's default currency when creating a company, and the system will handle conversions automatically when importing, exporting, or displaying data in other currencies.</p>

            <h2>Supported Currencies</h2>
            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Currency</th>
                            <th>Symbol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currencies as $code => $c): ?>
                        <tr><td><?php echo htmlspecialchars($code); ?></td><td><?php echo htmlspecialchars($c['name']); ?></td><td><?php echo htmlspecialchars($c['symbol']); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="info-box">
                <strong>Tip:</strong> USD, EUR, CAD and AUD are pinned to the top of every currency dropdown in the app, so the ones most people need are always the first four in the list.
            </div>

            <h2>How Currency Conversion Works</h2>
            <ul>
                <li><strong>Historical rates:</strong> every amount is converted using the exchange rate for that transaction's own date, so a conversion never silently changes because rates moved</li>
                <li><strong>Fetched when needed:</strong> rates are retrieved automatically as transactions are entered, imported, or exported</li>
                <li><strong>Local caching:</strong> rates are cached on your device to reduce internet requests and allow limited offline use</li>
            </ul>

            <p>If the rate for a transaction's date isn't available yet, the transaction is saved and marked <strong>Pending</strong> rather than converted at the wrong rate. See <a class="link" href="how-numbers-are-calculated.php#pending-conversion">How Numbers Are Calculated</a> for what that means for your totals.</p>

            <div class="warning-box">
                <strong>Internet Connection Required:</strong> Currency conversion requires an internet connection to fetch current and historical exchange rates. Cached rates are used when offline, but a date that has never been fetched cannot be converted until you reconnect.
            </div>

            <div class="page-navigation">
                <a href="how-numbers-are-calculated.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; How Numbers Are Calculated</span>
                </a>
                <a href="supported-languages.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Supported Languages &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
