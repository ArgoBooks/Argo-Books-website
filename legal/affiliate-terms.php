<?php require_once __DIR__ . '/../partials/schema.php'; ?>
<?php require_once __DIR__ . '/../community/affiliate/affiliate_functions.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta Tags -->
    <meta name="description" content="Read the Argo Books Affiliate Program Terms. Learn about commissions, payouts, attribution, disclosure requirements, and program rules.">
    <meta name="keywords" content="argo books affiliate terms, affiliate program terms, commission terms, affiliate agreement">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Affiliate Program Terms - Argo Books">
    <meta property="og:description" content="Read the Argo Books Affiliate Program Terms: commissions, payouts, attribution, and program rules.">
    <meta property="og:url" content="https://argorobots.com/legal/affiliate-terms.php">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Affiliate Program Terms - Argo Books">
    <meta name="twitter:description" content="Read the Argo Books Affiliate Program Terms: commissions, payouts, attribution, and program rules.">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/legal/affiliate-terms.php">

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", "Affiliate Program Terms" => "/legal/affiliate-terms.php"]) ?></script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Affiliate Program Terms - Argo Books</title>

    <script src="../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/header/dark.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>
    <main>

    <div class="legal-container">
        <div class="legal-content">
            <h1>Affiliate Program Terms</h1>

            <h2>Introduction</h2>
            <p>These terms govern your participation in the Argo Books Affiliate Program. By applying to or
                taking part in the program, you agree to these terms. If you do not agree, please do not apply.
                We may update these terms from time to time; the current version always applies.</p>

            <h2>Eligibility</h2>
            <ul>
                <li>You must have an Argo Books community account to apply.</li>
                <li>Every application is reviewed manually. We may approve or decline any application at our
                    discretion, and we do not have to give a reason.</li>
                <li>Your referral link only becomes active once your application is approved.</li>
            </ul>

            <h2>Commissions</h2>
            <ul>
                <li>You earn <strong>50% of the subscription price</strong> on every completed payment made by a
                    customer you refer, for the <strong>first 12 months</strong> of that customer's subscription.
                    This includes the initial payment and renewals within that window.</li>
                <li>Commission is calculated on the subscription price, before payment processing fees, in
                    Canadian dollars (CAD).</li>
                <li>Commission is earned only on genuine, completed payments. Payments that are pending, failed,
                    or refunded do not earn commission.</li>
            </ul>

            <h2>Referral Tracking</h2>
            <ul>
                <li>Referrals are tracked through your unique referral link. When someone clicks your link, a
                    cookie is stored in their browser for up to one year and is used to attribute a later
                    purchase to you.</li>
                <li>Attribution depends on the visitor allowing cookies and not clearing them or using another
                    device or browser before purchasing. We cannot guarantee that every referral will be
                    tracked.</li>
                <li>You may not refer yourself, your own accounts, or purchases you control. Self-referrals do
                    not earn commission and may result in removal from the program.</li>
            </ul>

            <h2>Refunds and Chargebacks</h2>
            <p>If a payment that earned you commission is later refunded, charged back, or reversed, the related
                commission is cancelled. If it was already paid out to you, it may be deducted from your future
                commission balance.</p>

            <h2>Payouts</h2>
            <ul>
                <li>Commission is held for <?php echo (int) affiliate_hold_days(); ?> days after each payment before
                    it becomes payable. This covers the refund and chargeback window, so commission is only paid on
                    payments that have settled. Your dashboard shows held amounts as "Pending" until they clear.</li>
                <li>Payouts are made in Canadian dollars (CAD) by PayPal to the email address on your affiliate
                    account. Keep that address accurate.</li>
                <li>We may set a minimum balance before a payout is issued.</li>
                <li>Any fees charged by PayPal, including currency-conversion and cross-border fees when you
                    receive money in a currency or country other than CAD, come out of the amount you receive and
                    are your responsibility. Receiving in CAD avoids these fees.</li>
                <li>You are responsible for any taxes on the commission you earn. We may ask you to provide tax
                    information before issuing payouts where required by law.</li>
            </ul>

            <h2>How You May Promote</h2>
            <p>You are welcome to share your link on your website, videos, social media, newsletters, and similar
                channels. You may not:</p>
            <ul>
                <li>Send spam or unsolicited messages, or post your link where it is not allowed.</li>
                <li>Bid on "Argo Books" or confusingly similar terms in paid search, or run ads that impersonate
                    Argo Books.</li>
                <li>Make false, misleading, or exaggerated claims about Argo Books, its pricing, or its features.</li>
                <li>Use cookie stuffing, forced clicks, or any method that creates referrals a person did not
                    genuinely choose to make.</li>
                <li>Offer unauthorized rebates, cashback, or coupon codes in our name.</li>
            </ul>

            <h2>Disclosure Requirements</h2>
            <p>When you promote Argo Books, you must clearly disclose that you may earn a commission, in line with
                the rules that apply to you (for example the U.S. FTC, the UK ASA, or the Canadian Competition
                Bureau). A simple, visible note such as "affiliate link" or "#ad" is usually enough. Failure to
                disclose can result in removal from the program.</p>

            <h2>Suspension and Termination</h2>
            <ul>
                <li>You may leave the program at any time.</li>
                <li>We may suspend or remove any affiliate, withhold or reverse commission, or change or end the
                    program at any time, particularly in the case of fraud, abuse, or a breach of these terms.</li>
                <li>Commission you have genuinely earned and that is not affected by a refund, chargeback, or
                    breach remains payable.</li>
            </ul>

            <h2>Independent Relationship</h2>
            <p>You take part in the program as an independent party. Nothing in these terms creates an employment,
                agency, partnership, joint venture, or franchise relationship between you and Argo Books. You have
                no authority to make any statement, representation, or commitment on behalf of Argo Books, or to
                bind us in any way, beyond promoting the Service as permitted here. You are responsible for your own
                costs, equipment, and taxes connected with your promotional activity.</p>

            <h2>No Guarantee</h2>
            <p>The affiliate program is provided on an "as is" and "as available" basis. We do not guarantee any
                particular level of earnings, that every referral will be tracked, or that the program, your
                referral link, or your dashboard will be available without interruption. Commission rates, cookie
                duration, and program features may change, and the program may be paused or ended, as described in
                these terms.</p>

            <h2>Indemnification</h2>
            <p>You agree to defend, indemnify, and hold harmless Argo Books, and its directors, employees, and
                agents, from and against any claims, damages, losses, liabilities, costs, and expenses (including
                reasonable legal fees) arising out of or connected with your participation in the program. This
                includes your promotional methods and content, your use of your referral link, any statement you
                make about Argo Books, your breach of these terms, and your violation of any law or the rights of
                any third party.</p>

            <h2>Limitation of Liability</h2>
            <p>To the fullest extent permitted by law, Argo Books, and its directors, employees, and agents, will
                not be liable for any indirect, incidental, special, consequential, or punitive damages, or for any
                loss of profits, revenue, data, or goodwill, arising out of or connected with the affiliate program,
                whether based on warranty, contract, tort (including negligence), or any other legal theory, even if
                we have been advised of the possibility of such damages. In any event, our total liability to you
                relating to the program will not exceed the total commission paid or payable to you in the twelve
                months before the event giving rise to the claim.</p>

            <h2>Governing Law</h2>
            <p>These terms are governed by the laws of Canada, excluding its conflict of law rules. Any dispute
                arising out of or in connection with these terms or the program is subject to the exclusive
                jurisdiction of the courts located in Canada. Our failure to enforce any right or provision of these
                terms is not a waiver of that right or provision. If any provision of these terms is held to be
                invalid or unenforceable, the remaining provisions stay in effect. These terms are the entire
                agreement between you and Argo Books regarding the affiliate program.</p>

            <h2>Changes to These Terms</h2>
            <p>We may update these terms. The version on this page is always the current one, and the date below
                shows when it last changed. If you keep taking part in the program after an update, you accept the
                new terms.</p>

            <h2>Contact Us</h2>
            <p>If you have any questions about these Affiliate Program Terms, please contact us:</p>
            <ul>
                <li>By email: <a class="link" href="mailto:contact@argorobots.com">contact@argorobots.com</a>.</li>
                <li>Through the <a class="link" href="../contact-us/">Contact Us Page</a> on our website.</li>
            </ul>

            <p class="last-updated">Last updated: July 7, 2026</p>
        </div>
    </div>

    </main>

    <footer class="footer">
        <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
    </footer>
</body>

</html>
