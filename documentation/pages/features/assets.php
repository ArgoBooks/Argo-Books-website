<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Fixed Assets';
$pageDescription = 'Learn how to track fixed assets in Argo Books. Turn a purchase into an asset, depreciate it over the years you use it, and record its sale.';
$currentPage = 'assets';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Some purchases last for years: land, a building, a vehicle, equipment, furniture or computers. Counting the whole price as an expense on the day you buy it would make that one month look terrible and every month after look better than it was. Argo Books lets you record these as fixed assets instead, and spreads their cost over the years you use them. This is called depreciation.</p>

            <p>Your assets are listed on the Assets page, under Expenses in the navigation menu. Each row shows what the asset cost, its depreciation this year, and what it's worth on your books today.</p>

            <h2>Turning a Purchase into an Asset</h2>
            <p>Most assets start as a purchase you record as an expense. There are two ways to mark it as an asset:</p>
            <ul>
                <li><strong>When you record the expense:</strong> tick "This is an asset I'll keep for years (depreciate it)" on the expense form, then choose its type and how many years you'll use it. The asset is created when you save.</li>
                <li><strong>After you've recorded it:</strong> click the "Make this an asset" button on the expense's row on the Expenses page. The asset form opens filled in from the expense, so you can set how it depreciates.</li>
            </ul>
            <p>The asset's cost is what the expense cost before tax, including any shipping and fees. If only part of the expense was the asset, for example a van bought together with a tank of fuel, use "Make this an asset" and lower the cost to just the asset's part.</p>
            <p>If you change the expense later, an asset that took the whole expense follows its new amount. Unticking the box on the expense removes the asset.</p>

            <h2>Adding an Asset You Already Owned</h2>
            <ol class="steps-list">
                <li>Go to "Assets" in the navigation menu, under Expenses</li>
                <li>Click "Add Asset"</li>
                <li>Enter its name, type, the date you bought it and what it cost</li>
                <li>Set how it depreciates (see below)</li>
                <li>Click "Add Asset" to save it</li>
            </ol>
            <p>If you bought it before this year, the form also asks for the depreciation already claimed before this year. Enter the total from your earlier books or tax returns, and Argo Books carries on from there.</p>

            <h2>How an Asset Depreciates</h2>
            <p>Land doesn't depreciate, so it stays on your books at what you paid. For every other type, you choose:</p>
            <ul>
                <li><strong>Straight line:</strong> the same amount every year, spread over the number of years you'll use it. A $12,000 van over 5 years depreciates $2,400 a year.</li>
                <li><strong>Declining balance:</strong> a percentage of what's left each year, so it loses more value early on and less later. A $10,000 truck at 30% depreciates $3,000 in the first year and $2,100 in the second.</li>
                <li><strong>Value at end (optional):</strong> what you expect to sell it for when you're done with it. It's never depreciated below this.</li>
            </ul>
            <p>As you fill in the form, it shows the yearly amount. Click "Show schedule" to see each year's depreciation and what the asset will be worth at the end of it.</p>
            <p>Depreciation is counted at the end of each month, starting with the month you bought the asset, so an asset bought in October has three months of depreciation in its first year.</p>

            <div class="info-box">
                <strong>Use the lifetime or rate your accountant gives you.</strong> Tax rules set their own rates for each kind of asset (Capital Cost Allowance in Canada, MACRS in the US), and they can differ from what you'd pick yourself. Argo Books shows depreciation in your reports but doesn't file taxes, so check the figures with your accountant.
            </div>

            <h2>Canadian Companies: CRA Rates Filled In</h2>
            <p>If your company is in Canada, the asset form fills in CRA's Capital Cost Allowance class and rate for you. Each choice in the Type list shows its class and rate, such as "Vehicle (Class 10, 30%)" or "Computer equipment (Class 50, 55%)", and picking one sets the depreciation to match. Where a type can be in more than one class, the list has both, such as "Passenger vehicle over CRA's limit (Class 10.1, 30%)". You can still change the rate if your accountant tells you to.</p>
            <p>An asset with a CRA class is depreciated the way CRA works it out:</p>
            <ul>
                <li><strong>By calendar year:</strong> each year takes the class's rate of what's left.</li>
                <li><strong>A different first year:</strong> CRA only allows part of the rate, or sometimes more, in the year you buy an asset. The half-year rule allows half; recent budgets have allowed one and a half times the rate for a few years to encourage investment. Argo Books uses the rule for the date you bought it, and the form says which.</li>
                <li><strong>Nothing in the year it's sold:</strong> the year an asset leaves the business has no depreciation.</li>
            </ul>
            <p>Argo Books keeps the rates up to date, so a budget's change reaches you without installing an update. On the expense form, the type you pick under "This is an asset" carries its CRA class the same way, so a Canadian company isn't asked for a number of years.</p>
            <p>A few things CRA handles that Argo Books doesn't: the cost limit on class 10.1 passenger vehicles, tax years that don't end on December 31, and depreciating a whole class as one pool. Your accountant takes care of these when filing.</p>

            <h2>Selling or Disposing of an Asset</h2>
            <p>When an asset leaves the business, click the "Sell or dispose" button on its row on the Assets page. Choose whether it was sold or scrapped or given away, the date, and the sale price if it was sold.</p>
            <p>The window shows what the asset was worth on your books on that date, and whether the sale is a gain or a loss: the difference between what you got for it and that book value. Depreciation stops from the month it left.</p>
            <p>The sale price counts as money coming into the business, so there's no need to also record the sale as revenue. If you recorded a sale by mistake, the "Undo sale" button on the row puts the asset back on your books.</p>

            <h2>Assets in Your Reports</h2>
            <ul>
                <li><strong>Income Statement:</strong> the purchase isn't counted as an expense. Instead, a Depreciation line shows the depreciation in the report's period, and a Gain or Loss on Sale of Assets line shows any asset sold in it.</li>
                <li><strong>Balance Sheet:</strong> a Fixed Assets section shows what your assets cost, less the depreciation so far. The money from a sale is added to cash.</li>
                <li><strong>Cash Flow Statement:</strong> buying and selling assets appears under Investing Activities rather than with your everyday expenses.</li>
                <li><strong>Asset Register:</strong> a report listing every asset with its cost, depreciation in the period and so far, and what it's worth now, plus any sold or disposed of in the period. See <a class="link" href="report-generator.php">Report Generator</a>.</li>
            </ul>
            <p>The Dashboard and Analytics count money as it comes and goes, so they still show an asset purchase as an expense on the day you paid for it. See <a class="link" href="../reference/how-numbers-are-calculated.php#fixed-assets">How Numbers Are Calculated</a>.</p>

            <h2>Managing Existing Assets</h2>
            <p>From the Assets page, you can edit or delete any asset using the action buttons in each row. Deleting an asset that came from an expense means that expense counts as an ordinary expense again. Every change can be undone.</p>

            <h2>Assets in Spreadsheets</h2>
            <p>File &gt; Export As includes an Assets sheet with each asset's cost, depreciation settings, depreciation so far and value today, which is the list an accountant usually asks for at year end. An Assets sheet can also be brought in with <a class="link" href="spreadsheet-import.php">Spreadsheet Import</a>, for example to add assets you owned before using Argo Books all at once. For a Canadian company the sheet only needs each asset's CRA class: the rate and first-year rule are filled in from it.</p>

            <div class="page-navigation">
                <a href="suppliers.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Supplier Management</span>
                </a>
                <a href="inventory.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Inventory Management &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
