<?php
require_once __DIR__ . '/../../../config/pricing.php';
require_once __DIR__ . '/../../../resources/icons.php';
$pricing = get_pricing_config();
$pageTitle = 'Quotes';
$pageDescription = 'Price up work before you invoice it. Send a quote your customer can accept or decline online, then turn an accepted one into an invoice.';
$currentPage = 'quotes';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>A quote is a price you are offering, not money you are owed. It stays out of your income,
            your reports and your tax figures until you turn it into an invoice. Send one, your customer
            accepts or declines it online, and an accepted quote becomes a draft invoice in one click.</p>

            <h2>Writing a Quote</h2>
            <ol class="steps-list">
                <li>Go to <strong>Quotes</strong> in the navigation menu, under Revenue</li>
                <li>Click <strong>New Quote</strong></li>
                <li>Pick the customer in the Bill To box on the quote itself, or create one as you type
                    (see <a class="link" href="customers.php">Customer Management</a>)</li>
                <li>Add line items from your product catalogue
                    (see <a class="link" href="product-management.php">Product Management</a>)</li>
                <li>Set the issue date and a <strong>valid until</strong> date, after which the price is no
                    longer on offer</li>
                <li>Click <strong>Preview</strong>, then <strong>Send quote</strong></li>
            </ol>

            <h2>Templates</h2>
            <p>Quotes and invoices share one set of templates, so both arrive looking the same. Choose which
            one to use in the panel beside the quote as you write it, or click <strong>Templates</strong> at
            the top of the Quotes page to design them (see
            <a class="link" href="report-generator.php">Report Generator</a> for the designer itself).</p>

            <h2>Sending a Quote</h2>
            <p>Sending needs the payment portal set up, because that is what hosts the page your customer
            answers on. See <a class="link" href="payment-portal.php">Payment Portal</a> for the one-time
            setup.</p>
            <p>Your customer gets an email with a link. The page shows the quote exactly as you built it,
            with <strong>Accept</strong> and <strong>Decline</strong> buttons and a box for a message back.
            There is nothing to sign up for and nothing to install.</p>

            <div class="info-box">
                <p><strong>Note:</strong> Quotes are unlimited on every plan. There is a limit on how many can
                be emailed in a day, which exists so the address Argo Books sends from cannot be used to send
                bulk mail. Ordinary use will not reach it.</p>
            </div>

            <h2>Their Answer</h2>
            <p>When a customer accepts or declines, the answer comes back into Argo Books on its own, along
            with any message they left. The quote's status changes and you get a notification.</p>
            <p>If they tell you some other way, by phone or in person, use the tick or cross on the quote's
            row to record it yourself. Nothing is emailed when you do; it only saves the answer here.</p>

            <h2>Resending and Revising</h2>
            <p>The send button on a quote you have already sent becomes <strong>Resend</strong>, which asks
            you to confirm and then sends the same quote again.</p>
            <p>To change the price, edit the quote and send it again. If your customer had already answered,
            Argo Books warns you first: sending a revised quote clears their answer and asks them again.</p>

            <div class="info-box">
                <p><strong>Note:</strong> Editing a quote your customer already has does not change what they
                see. They keep the version you sent until you resend it.</p>
            </div>

            <h2>Turning a Quote Into an Invoice</h2>
            <p>Click the invoice icon on an accepted quote. Argo Books copies the customer, the lines and the
            totals into a <strong>draft</strong> invoice and opens it for you to check. Nothing is sent, no
            revenue is recorded, and no payment is requested until you send that invoice yourself.</p>
            <p>The quote is then marked Converted, and the invoice button on its row takes you to the invoice
            it became.</p>

            <h2>Expiry</h2>
            <p>A sent quote whose valid-until date has passed shows as <strong>Expired</strong> and can no
            longer be answered online. The Quotes page counts how many are close to expiring, so you can
            chase the ones still worth chasing.</p>

            <div class="page-navigation">
                <a href="payment-portal.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Payment Portal</span>
                </a>
                <a href="invoicing.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Invoicing &amp; Payments &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
