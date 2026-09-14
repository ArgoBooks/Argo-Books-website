<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'How Numbers Are Calculated';
$pageDescription = 'Plain-language reference for how Argo Books calculates revenue, net profit, tax, refunds, and currency conversion. Understand exactly what each figure on the dashboard means.';
$currentPage = 'how-numbers-are-calculated';
$pageCategory = 'reference';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>This page explains how Argo Books arrives at every dollar figure you see (revenue, profit, tax, refunds) in plain language. If a number on your dashboard surprises you, this is the page to check.</p>

            <h2>Quick answers</h2>
            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Answer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Does tax count as revenue?</td>
                            <td>Yes. "Total Revenue" includes it (gross). Profit excludes it.</td>
                        </tr>
                        <tr>
                            <td>Does shipping the customer paid me count as profit?</td>
                            <td>Yes. Record what you paid the courier as an Expense to balance it out.</td>
                        </tr>
                        <tr>
                            <td>Why does the dashboard show $0 even though I have unpaid invoices?</td>
                            <td>The dashboard shows money you've actually collected. Unpaid invoices appear in "Outstanding Invoices" instead.</td>
                        </tr>
                        <tr>
                            <td>Why is my Net Profit smaller than my Total Revenue minus Expenses?</td>
                            <td>Profit excludes sales tax (it's not yours to keep). See <a href="#net-profit" class="link">Net Profit</a> below.</td>
                        </tr>
                        <tr>
                            <td>How are refunds handled?</td>
                            <td>Subtracted from revenue on the day the refund was issued. See <a href="#refunds" class="link">Refunds</a> below.</td>
                        </tr>
                        <tr>
                            <td>Why doesn't my per-product revenue add up to Total Revenue?</td>
                            <td>Refunds aren't split per product, and un-itemised sales aren't counted. See <a href="#sales-by-product" class="link">Sales by product</a> below.</td>
                        </tr>
                        <tr>
                            <td>Why does a transaction show "Pending" instead of an amount?</td>
                            <td>Its exchange rate isn't available yet, so it counts as zero until it is. See <a href="#pending-conversion" class="link">Currency display</a> below.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h2 id="revenue">Total Revenue</h2>
            <p>Total Revenue is the full amount your customers paid you, including sales tax, shipping, and any fees. It matches the invoice totals you sent out.</p>

            <p>What's <strong>in</strong>:</p>
            <ul>
                <li>The item or service amount the customer was charged</li>
                <li>Sales tax you collected</li>
                <li>Shipping the customer paid</li>
                <li>Any custom fees you added (rush delivery, setup fee, etc.)</li>
            </ul>

            <p>What's <strong>out</strong>:</p>
            <ul>
                <li>Discounts (already subtracted before the total)</li>
                <li>Refunds you've issued (subtracted from the day the refund was sent)</li>
                <li>Unpaid invoices (the dashboard only counts money you've actually received)</li>
                <li>Security deposits (see below)</li>
            </ul>

            <div class="info-box">
                <strong>Security deposits:</strong> a deposit is added to the invoice total so the customer is charged it, but it isn't taxed and isn't treated as money you've earned. It's a refundable hold. If a customer forfeits their deposit and you get to keep it, record that as revenue separately so it counts.
            </div>

            <h2 id="net-profit">Net Profit</h2>
            <p>Net Profit answers a different question: <em>how much did the business actually keep?</em> So it deliberately excludes anything that isn't yours to keep.</p>

            <p>The formula:</p>
            <div class="info-box">
                <strong>Net Profit&nbsp;=&nbsp;Revenue (excluding sales tax)&nbsp;&minus;&nbsp;Expenses&nbsp;&minus;&nbsp;Refunds</strong>
            </div>

            <p>The key thing to know: <strong>sales tax is not part of profit.</strong> When a customer pays you $11 on a $10 sale with 10% sales tax, you collected $11 but only $10 is yours: the $1 is owed to the government. Argo Books treats that $1 as a liability, not as profit.</p>

            <p>Shipping the customer paid is in. If they paid you $8 for shipping, that's $8 in your pocket. If you then paid a courier $8 to actually ship the item, you'll record that as an Expense and it cancels out. Net effect on profit: zero. If you charged $8 but only paid $5 to ship, you keep $3 in margin.</p>

            <div class="info-box">
                <strong>Tip:</strong> If your profit looks higher than expected, double-check that you've recorded the shipping cost you paid (carrier invoices) as Expenses. The customer's payment is automatically in revenue; the carrier payment is not automatically in expenses, so you have to add it.
            </div>

            <h2 id="refunds">Refunds</h2>
            <p>A refund reduces your numbers on the day the refund was issued, not the day of the original payment. Same-day refund nets out to zero on that day; a refund issued a week later leaves the original day's revenue intact and shows a negative on the refund's day.</p>

            <p>How refunds affect each number:</p>
            <ul>
                <li><strong>Total Revenue:</strong> reduced by the full refund amount.</li>
                <li><strong>Net Profit:</strong> reduced by the refund amount <em>minus</em> the tax portion of that refund. The tax portion was never profit on the way in (it was always owed to the government), so it doesn't reduce profit on the way out either.</li>
                <li><strong>Invoice status:</strong> flips to "Refunded" when the refund covers the full invoice value, or "Partially Refunded" if only part was returned (or if you paid, refunded, and paid again).</li>
            </ul>

            <p>If you pay a customer, get refunded, then pay them again, the invoice stays "Partially Refunded" so the refund history is still visible alongside the new payment.</p>

            <h2 id="tax">Tax</h2>
            <p>Argo Books tracks two tax numbers:</p>
            <ul>
                <li><strong>Tax Collected:</strong> the sales tax you charged customers on your invoices.</li>
                <li><strong>Tax Paid:</strong> the sales tax you paid suppliers on your expenses (when you record it).</li>
            </ul>
            <p><strong>Argo Books does not subtract one from the other for you.</strong> It reports the tax you collected, and shows the tax you paid separately. In many places the tax you paid suppliers can be deducted from the tax you owe, but the rules vary and the decision is yours, so Argo Books leaves that step to you or your advisor rather than guessing.</p>

            <p>Argo Books also does not file or remit tax. It gives you the figures to hand to your accountant or filing service.</p>

            <h2 id="cash-vs-accrual">Cash basis (Dashboard) vs. accrual (Reports)</h2>
            <p>This is the single biggest source of "why does the dashboard say X but the Revenue page says Y?" confusion. The two views answer different questions:</p>

            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Surface</th>
                            <th>What it counts</th>
                            <th>Use it for</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Dashboard, Analytics, charts</strong></td>
                            <td>Cash basis: only invoices the customer has actually paid.</td>
                            <td>"How is my business doing right now? What's in the bank?"</td>
                        </tr>
                        <tr>
                            <td><strong>Revenue page (list view)</strong></td>
                            <td>All revenue rows you've recorded, paid or not.</td>
                            <td>Reviewing or editing individual records.</td>
                        </tr>
                        <tr>
                            <td><strong>Outstanding Invoices stat card</strong></td>
                            <td>Only invoices that <em>haven't</em> been paid.</td>
                            <td>Following up on what customers owe you.</td>
                        </tr>
                        <tr>
                            <td><strong>Reports (Income Statement, Balance Sheet)</strong></td>
                            <td>Accrual basis: all invoiced revenue, paid or not.</td>
                            <td>Filing taxes, sharing with an accountant, year-end reviews.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="info-box">
                <strong>Why the difference?</strong> Cash basis answers "did money actually arrive?" Accrual basis answers "did I do the work that earned the money?" Both are valid views; small businesses usually want the cash-basis dashboard for day-to-day decisions and the accrual report for taxes and accountants.
            </div>

            <h2 id="inventory-balance-sheet">Inventory on the Balance Sheet</h2>
            <p>The Balance Sheet lists your stock on hand as a current asset. It values each item at its current unit cost (Argo Books doesn't keep a history of past costs) and works out how much stock you held as of the report date from your recorded stock movements.</p>
            <p>For a product with Track Inventory on, buying stock adds it to your stock instead of counting as an expense. When it sells, what that stock cost comes off your profit, and the Income Statement shows it as Cost of Goods Sold with Gross Profit underneath. The cost is saved on the sale, so changing a cost price later doesn't rewrite past profit.</p>
            <p>Stock you already had when this started was expensed when you bought it, so it sells at no cost and isn't taken off your profit twice. Tax, shipping and fees on a purchase stay expenses, and the Expenses page still shows every purchase in full, because that money did leave the business.</p>

            <h2 id="sales-by-product">Sales by product</h2>
            <p>The Products tab in Analytics and the "Sales by Product" report both break your sales down per product. Two things about these figures surprise people, so they're worth knowing up front.</p>

            <p><strong>They show revenue, not profit.</strong> Argo Books deliberately doesn't calculate profit per product, because it can't do it honestly. Working out profit on a product needs a reliable cost for that exact product, and there often isn't one: the cost you enter is an optional default, stock is treated as an expense when you buy it rather than matched to the sale, and anything you assemble yourself (a pizza made from flour and cheese) has no purchase cost of its own. Rather than show you a margin that might be wrong, these screens show what's always true: what each product sold for, and how many units moved.</p>

            <p><strong>They won't exactly match your Total Revenue.</strong> Two differences pull in opposite directions:</p>
            <ul>
                <li><strong>Refunds aren't broken down per product.</strong> A refund is recorded against the invoice as a whole, not against a particular line, so there's no reliable way to know which product it concerned. Per-product revenue therefore reads slightly high for anything that was later refunded.</li>
                <li><strong>Sales with no line items are excluded.</strong> If a sale was recorded as a lump sum without itemising it, there's no product to attribute it to, so it doesn't appear here at all. That pulls the total the other way.</li>
            </ul>

            <p>The Analytics tab counts only sales you've been paid for, like the rest of the dashboard. The report version counts all invoiced sales, like the Income Statement.</p>

            <h2 id="not-counted">Things that don't affect your numbers</h2>
            <ul>
                <li><strong>Returns and losses:</strong> customer-returned items and lost or damaged stock have their own charts, but they don't feed revenue, profit or expenses. If you refunded the customer, it's that refund that moves your numbers.</li>
                <li><strong>Bank matching:</strong> ticking off bank statement lines against your records is a checking tool. It marks records as matched and nothing else, so it never changes any figure.</li>
            </ul>

            <h2 id="currency">Currency display</h2>
            <p>Argo Books stores every amount internally in US dollars so that businesses dealing in multiple currencies (e.g., invoicing some customers in EUR and others in USD) still get one consistent set of totals. The display currency is whatever you picked in Settings.</p>
            <ul>
                <li><strong>Stat cards, charts, totals:</strong> shown in your display currency.</li>
                <li><strong>Invoices and customer emails:</strong> shown in the currency the invoice was issued in, which is what your customer expects to see.</li>
                <li><strong>Spreadsheet exports of transactions:</strong> the original currency you entered, so the export is faithful to the source.</li>
            </ul>

            <h3 id="pending-conversion">Why a transaction sometimes shows "Pending" instead of an amount</h3>
            <p>Every amount is converted using the exchange rate for that transaction's own date. Argo Books never substitutes a different day's rate, because that would quietly change what your books say.</p>

            <p><em>This is unrelated to the <strong>Pending</strong> invoice status further down the page, which simply means an invoice is ready but hasn't been sent.</em></p>

            <p>If the rate for a transaction's date isn't available yet, the transaction is saved and shown with a <strong>Pending</strong> marker in place of the converted figure. That happens when you were offline when you entered it, or when the transaction is dated in the future and the rate doesn't exist yet.</p>

            <p><strong>While a transaction is pending, it counts as zero in your totals.</strong> That's deliberate: including it at the wrong rate would make every total slightly wrong in a way you'd never spot. Argo Books fetches the correct rate automatically once it can, and the transaction joins your totals at that point with no action needed from you.</p>

            <div class="info-box">
                <strong>If a total looks too low:</strong> check whether any transactions in that period show Pending. Connecting to the internet and reopening the company is usually enough to resolve them. Future-dated transactions stay pending until their date arrives, which is expected.
            </div>

            <h2 id="invoice-status">Invoice status meanings</h2>
            <p>The status badge on each invoice is computed from the payment history:</p>
            <ul>
                <li><strong>Draft:</strong> being prepared, never sent.</li>
                <li><strong>Pending:</strong> ready but not sent yet.</li>
                <li><strong>Sent:</strong> sent to the customer, awaiting payment.</li>
                <li><strong>Viewed:</strong> the customer opened it on the payment portal.</li>
                <li><strong>Partial:</strong> the customer paid part of the invoice; some is still owed.</li>
                <li><strong>Paid:</strong> the customer paid the full amount.</li>
                <li><strong>Overdue:</strong> past the due date and not fully paid.</li>
                <li><strong>Cancelled:</strong> voided by you.</li>
                <li><strong>Refunded:</strong> the customer was paid fully, then fully refunded.</li>
                <li><strong>Partially Refunded:</strong> the customer was paid fully, then refunded part of it, or paid, refunded, then paid again so the refund history stays visible.</li>
            </ul>

            <h2>Example</h2>
            <p>Let's walk through a simple $110 sale:</p>
            <ul>
                <li>Product/service: <strong>$100</strong>.</li>
                <li>Tax at 10%: <strong>+$10</strong>.</li>
                <li>Invoice total the customer pays: <strong>$110</strong>.</li>
            </ul>

            <p>If the customer pays the invoice in full, Argo Books shows:</p>
            <ul>
                <li>Total Revenue stat card: <strong>$110</strong> (gross, what they paid you).</li>
                <li>Net Profit (assuming no expenses): <strong>$100</strong> (revenue minus the $10 tax you owe the government)</li>
                <li>Tax Collected: <strong>$10</strong>.</li>
            </ul>

            <p>Refunds are issued from the <strong>Refund</strong> action on the invoice row. In the refund modal you choose exactly what to refund; you don't have to give the whole invoice back. Each line item on the invoice is a separate checkbox. Tax, fees, and security deposits each get their own row too.</p>

            <p>What you see depends on how much you refunded:</p>

            <p><strong>Full refund ($110)</strong>, where you ticked everything:</p>
            <ul>
                <li>Revenue page: the sale's <strong>Total</strong> column shows <strong>$0</strong>; status badge becomes <strong>Refunded</strong>.</li>
                <li>Invoice history (the "View History" action on the invoice row): the original $110 payment plus a separate &minus;$110 refund entry.</li>
                <li>Dashboard: Total Revenue <strong>$0</strong>, Net Profit <strong>$0</strong>.</li>
            </ul>

            <p><strong>Partial refund ($40)</strong>, where you only refunded one item:</p>
            <ul>
                <li>Revenue page: the sale's <strong>Total</strong> column shows <strong>$70</strong> ($110 minus $40); status badge becomes <strong>Partially Refunded</strong>.</li>
                <li>Invoice history: the original $110 payment plus a &minus;$40 refund entry.</li>
                <li>Dashboard: Total Revenue <strong>$70</strong>. Net Profit drops by the pre-tax portion of the refund (the tax portion was never profit on the way in, so it doesn't reduce profit on the way out either).</li>
            </ul>

            <div class="page-navigation">
                <a href="../api/errors.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; API Errors</span>
                </a>
                <a href="supported-currencies.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Supported Currencies &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
