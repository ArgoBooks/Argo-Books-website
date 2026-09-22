<?php
require_once __DIR__ . '/../../../config/pricing.php';
require_once __DIR__ . '/../../../resources/icons.php';
$pricing = get_pricing_config();
$pageTitle = 'Payment Portal';
$pageDescription = 'Connect Stripe or Square so customers can pay your invoices online and answer your quotes, and understand the processing fees.';
$currentPage = 'payment-portal';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>The payment portal is where a customer pays an invoice online. Connecting it is also what
            lets you send quotes, because the page a customer accepts or declines one on is hosted there too.
            It is a one-time setup.</p>

            <h2>Setting It Up</h2>
            <p>Argo Books connects to <strong>Stripe</strong> and <strong>Square</strong>, covering credit and
            debit cards, Apple Pay, Google Pay, and more.</p>
            <ol class="steps-list">
                <li>Go to <strong>Settings &gt; Payment Portal</strong></li>
                <li>Set your company name and portal logo. These appear on the page your customers see</li>
                <li>Set your owner email and confirm it with the emailed code. It is used for refund
                    verification and account recovery, and a provider cannot be connected until it is set</li>
                <li>Under "Connected Payment Providers", click <strong>Connect</strong> on Stripe or Square to
                    link your existing account, or create one during the connect step</li>
                <li>Authorize Argo Books to process payments on your behalf</li>
            </ol>

            <div class="info-box">
                <p><strong>Note:</strong> A standard <strong>Stripe</strong> or <strong>Square seller</strong>
                account is all you need. Argo Books connects to your account, it never stores your payment
                credentials.</p>
            </div>

            <h2>What Your Customers See</h2>
            <ul>
                <li>They receive an email with a link, and nothing to sign up for or install</li>
                <li>An invoice can be paid there, securely, using their preferred payment method</li>
                <li>A quote can be accepted or declined there, with room for a message back
                    (see <a class="link" href="quotes.php">Quotes</a>)</li>
                <li>Payments and answers come back into Argo Books on their own</li>
            </ul>

            <h2>Payment Processing Fees</h2>
            <p>Payment processing fees are charged by the payment provider, not Argo Books. Both Stripe and
            Square typically charge around 2.9% + $0.30 per transaction. Because the exact rate depends on many
            different factors, Argo Books adds the 2.9% + $0.30 for every transaction.</p>
            <p>You decide if you want to pass this fee onto your customer, or take the cost yourself. There is
            a "pass processing fee" toggle:</p>
            <ul>
                <li><strong>Toggle on</strong> &rarr; passes the cost on to the customer. Customer pays invoice
                    total + 2.9% + $0.30.</li>
                <li><strong>Toggle off</strong> &rarr; customer pays just the invoice total. You absorb the
                    fee.</li>
            </ul>
            <p>When your customers pay the invoices, the total amount and the fee go into your account. It's
            when you take the money out of your Stripe or Square account and move it into your normal bank
            account that they charge you. The fee may be slightly different than the 2.9% + $0.30, especially
            if your customer is in a different country.</p>
            <p>Argo Books does not add any extra fee on top of the payment providers.</p>

            <div class="info-box">
                <p><strong>Tip:</strong> This is a per-invoice choice, not a global setting. The
                <strong>"Pass processing fee to customer"</strong> checkbox is on the Create Invoice modal and
                is ticked by default. Untick it on any invoice where you would rather absorb the fee
                yourself.</p>
            </div>

            <h2>Security</h2>
            <p>Your payment data is protected:</p>
            <ul>
                <li>All payment processing happens on the payment provider's secure servers</li>
                <li>Argo Books never stores card numbers or bank details</li>
                <li>PCI DSS compliant through certified providers</li>
                <li>End-to-end encryption for all transactions</li>
            </ul>

            <div class="page-navigation">
                <a href="sales-tracking.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Expense/Revenue Tracking</span>
                </a>
                <a href="quotes.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Quotes &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
