<?php require_once __DIR__ . '/../partials/schema.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta Tags -->
    <meta name="description" content="Read the Argo Books Terms of Service. Understand the terms and conditions for using our website, community forums, and software.">
    <meta name="keywords" content="argo books terms of service, terms and conditions, user agreement">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Terms of Service - Argo Books">
    <meta property="og:description" content="Read the Argo Books Terms of Service for using our website, forums, and software.">
    <meta property="og:url" content="https://argorobots.com/legal/terms.php">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Terms of Service - Argo Books">
    <meta name="twitter:description" content="Read the Argo Books Terms of Service for using our website, forums, and software.">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/legal/terms.php">

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", "Terms of Service" => "/legal/terms.php"]) ?></script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Terms of Service - Argo Books</title>

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
            <h1>Terms of Service</h1>

            <h2>Introduction</h2>
            <p>Welcome to Argo Books. These Terms of Service ("Terms") govern your use of our website, community
                forums, and the Argo Books software (collectively, the "Service"). By accessing or using the
                Service, you agree to be bound by these Terms. If you disagree with any part of the terms, you may not
                access the Service.</p>

            <h2>Account Terms</h2>
            <p>When you create an account with us, you must provide accurate and complete information. You are
                responsible for maintaining the security of your account, and you are fully responsible for all
                activities that occur under your account.</p>
            <p>You must immediately notify us of any unauthorized uses of your account or any other breaches of
                security. We will not be liable for any acts or omissions by you, including any damages of any kind
                incurred as a result of such acts or omissions.</p>
            <p>The Argo Books desktop application collects usage data to help us improve Argo Books. We
                use it to understand how the software is used, identify and fix bugs, diagnose performance issues,
                prioritize new features, and optimize the app for different regions. This includes operational data,
                API usage, error tracking, session information, startup timing, geographic data (country, region,
                and timezone), and the profile of the business you keep books for (company name, business type,
                industry, country, currency, and language). It does not include your transactions, customers, suppliers, or 
                documents. The data is uploaded to our servers while the application runs and when it is closed.
                This data collection is separate from any standard web analytics used on our
                website. For more information about what data is collected and how it is used, please see our
                <a class="link" href="privacy.php">Privacy Policy</a>.</p>

            <h2>User Content</h2>
            <p>Our Service allows you to post, link, store, share and otherwise make available certain information,
                text, or other material ("Content"). You are responsible for the Content that you post on or through the
                Service, including its legality, reliability, and appropriateness.</p>
            <p>By posting Content on or through the Service, you represent and warrant that:</p>
            <ol>
                <li>The Content is yours and/or you have the right to use it and the right to grant us the rights and
                    license as provided in these Terms, and</li>
                <li>That the posting of your Content on or through the Service does not violate the privacy rights,
                    publicity rights, copyrights, contract rights or any other rights of any person.</li>
            </ol>
            <p>We reserve the right to terminate the account of any user who infringes copyrights or other intellectual
                property rights.</p>

            <h2>Acceptable Use</h2>
            <p>You agree not to use the Service for any purpose that is illegal or prohibited by these Terms. You may
                not use the Service in any manner that could damage, disable, overburden, or impair the Service or
                interfere with any other party's use and enjoyment of the Service.</p>
            <p>Prohibited behaviors include, but are not limited to:</p>
            <ul>
                <li>Posting content that is illegal, harmful, threatening, abusive, harassing, tortious, defamatory,
                    vulgar, or invasive of another's privacy.</li>
                <li>Impersonating any person or entity, or falsely stating or otherwise misrepresenting your affiliation
                    with a person or entity.</li>
                <li>Interfering with or disrupting the Service or servers or networks connected to the Service.</li>
                <li>Collecting or storing personal data about other users without their permission.</li>
                <li>Using the Service for any commercial purpose not expressly permitted by us.</li>
            </ul>

            <h2>License and Use of Argo Books</h2>
            <p>Argo Books is licensed, not sold. Upon purchasing a license key, you are granted a non-exclusive,
                non-transferable license to use the software according to the terms of the license purchased.</p>
            <p>License keys may not be shared, distributed, or used on more instances than permitted by the license
                terms. We reserve the right to revoke license keys that are used in violation of these Terms.</p>

            <h2>Argo Books Premium Subscription</h2>
            <p>Argo Books Premium is an optional subscription that raises the monthly usage limits of the free version,
                including invoicing, receipt scanning, and spreadsheet and bank statement imports, and unlocks
                additional features such as revenue forecasting, biometric sign-in, and priority support. The current
                limits and full feature list for each plan are published on our <a href="../pricing/">pricing page</a>.
                By subscribing, you agree to the following:</p>
            <ul>
<?php
                    require_once __DIR__ . '/../config/pricing.php';
                    $pricing = get_pricing_config();
                ?>
                <li><strong>Billing</strong>: Subscriptions are billed monthly ($<?php echo number_format($pricing['premium_monthly_price'], 0); ?> CAD) or yearly ($<?php echo number_format($pricing['premium_yearly_price'], 0); ?> CAD).</li>
                <li><strong>Auto-Renewal</strong>: Subscriptions automatically renew unless cancelled before the billing date.</li>
                <li><strong>Cancellation</strong>: You may cancel at any time. Access continues until the end of your billing period. Cancellation forfeits any account credit balance on your subscription.</li>
                <li><strong>Plan Changes (Upgrade)</strong>: When upgrading from monthly to yearly billing, you will be charged the yearly amount immediately, less the prorated value of your current unused billing period and any existing account credit. The new yearly billing period begins on the date of upgrade.</li>
                <li><strong>Plan Changes (Downgrade)</strong>: When downgrading from yearly to monthly billing, the prorated value of your unused yearly subscription is applied to reduce or fully cover the first new monthly charge. Any leftover value remains as account credit and is automatically applied to future monthly renewals until depleted.</li>
                <li><strong>Account Credit</strong>: Account credit is non-refundable, has no cash value, and is forfeited upon cancellation. Credit is consumed automatically by future renewal charges and does not expire while your subscription remains active.</li>
                <li><strong>Payment Methods</strong>: We accept payments via Stripe, PayPal, and Square. PayPal plan changes are refunded to your PayPal account; Stripe and Square plan changes apply credits or partial charges directly.</li>
            </ul>
            <p>We reserve the right to modify subscription pricing with 30 days notice to existing subscribers.</p>

            <h2>Payment Portal</h2>
            <p>Argo Books allows you to connect your own Stripe or Square merchant account so your customers can view and pay invoices online through our payment portal.</p>
            <ul>
                <li><strong>Facilitation only</strong>: Argo Books facilitates the connection between you and your payment provider. We do not process, hold, or have access to funds at any point.</li>
                <li><strong>Your responsibility</strong>: You are responsible for maintaining your merchant account and complying with the terms of your payment provider.</li>
                <li><strong>Invoice data</strong>: When you publish an invoice to the portal, the invoice data (customer name, amounts, line items) is transmitted to and hosted on our servers so your customers can access it.</li>
                <li><strong>No guarantee</strong>: We do not guarantee the availability of the payment portal or any third-party payment service.</li>
            </ul>

            <h2>Google API Services</h2>
            <p>The Argo Books desktop application includes a feature to export data to Google Sheets. When you use this feature:</p>
            <ul>
                <li>You will be prompted to sign in to your Google account and grant permission to the application.</li>
                <li>The application will only access your Google Drive to create and write to spreadsheets.</li>
                <li>We do not store, share, or use your Google data for any other purpose.</li>
                <li>You can revoke this access at any time through your Google Account permissions page.</li>
            </ul>
            <p>By using the Google Sheets export feature, you agree to allow the application to access your Google account for the sole purpose of creating and populating spreadsheets with your chart data. For more information, see our <a class="link" href="privacy.php">Privacy Policy</a>.</p>

            <h2>Intellectual Property</h2>
            <p>The Service and its original content (excluding Content provided by users), features and functionality
                are and will remain the exclusive property of Argo Books and its licensors. The Service is
                protected by copyright, trademark, and other laws of Canada. Our trademarks and trade dress may not be
                used in connection with any product or service without our prior written consent.</p>

            <h2>Termination</h2>
            <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason
                whatsoever, including without limitation if you breach the Terms.</p>
            <p>Upon termination, your right to use the Service will immediately cease. If you wish to terminate your
                account, you may simply discontinue using the Service.</p>

            <h2>Limitation of Liability</h2>
            <p>In no event shall Argo Books, nor its directors, employees, partners, agents, suppliers, or
                affiliates, be liable for any indirect, incidental, special, consequential or punitive damages,
                including without limitation, loss of profits, data, use, goodwill, or other intangible losses,
                resulting from:</p>
            <ol>
                <li>Your access to or use of or inability to access or use the Service;</li>
                <li>Any conduct or content of any third party on the Service;</li>
                <li>Any content obtained from the Service; and</li>
                <li>Unauthorized access, use or alteration of your transmissions or content, whether based on warranty,
                    contract, tort (including negligence) or any other legal theory, whether or not we have been
                    informed of the possibility of such damage.</li>
            </ol>

            <h2>Indemnification</h2>
            <p>You agree to defend, indemnify, and hold harmless Argo Books, and its directors, employees, and
                agents, from and against any claims, damages, losses, liabilities, costs, and expenses (including
                reasonable legal fees) arising out of or connected with your use of the Service, any Content you post,
                your breach of these Terms, your violation of any law, or your infringement of the rights of any third
                party.</p>

            <h2>Disclaimer</h2>
            <p>Your use of the Service is at your sole risk. The Service is provided on an "AS IS" and "AS AVAILABLE"
                basis. The Service is provided without warranties of any kind, whether express or implied, including,
                but not limited to, implied warranties of merchantability, fitness for a particular purpose,
                non-infringement or course of performance.</p>

            <h2>No Financial or Professional Advice</h2>
            <p><strong>Argo Books is a tool to help you record and organize your own financial information. It is not
                a substitute for professional accounting, bookkeeping, tax, legal, or financial advice, and we are not
                acting as your accountant, bookkeeper, or tax advisor.</strong> You are solely responsible for the
                accuracy and completeness of the information you enter and for reviewing and verifying any
                calculations, reports, tax figures, or other output before relying on them or submitting them to any
                authority or third party. We do not guarantee that any output is accurate, complete, or suitable for
                your circumstances or compliant with the laws that apply to you, and we recommend that you consult a
                qualified professional for advice specific to your situation. To the fullest extent permitted by law,
                we are not liable for any financial, tax, accounting, or business decision made, or any loss, penalty,
                or liability incurred, in reliance on the Service or its output.</p>

            <h2>Governing Law</h2>
            <p>These Terms shall be governed and construed in accordance with the laws of Canada, without
                regard to its conflict of law provisions.</p>
            <p>Our failure to enforce any right or provision of these Terms will not be considered a waiver of those
                rights. If any provision of these Terms is held to be invalid or unenforceable by a court, the remaining
                provisions of these Terms will remain in effect.</p>

            <h2>Changes</h2>
            <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a revision
                is material we will try to provide at least 30 days notice prior to any new terms taking effect. What
                constitutes a material change will be determined at our sole discretion.</p>
            <p>By continuing to access or use our Service after those revisions become effective, you agree to be bound
                by the revised terms. If you do not agree to the new terms, please stop using the Service.</p>

            <h2>Contact Us</h2>
            <p>If you have any questions about these Terms, please contact us:</p>
            <ul>
                <li>By email: <a class="link" href="mailto:contact@argorobots.com">contact@argorobots.com</a>.</li>
            </ul>

            <p class="last-updated">Last updated: August 13, 2026</p>
        </div>
    </div>

    </main>

    <footer class="footer">
        <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
    </footer>
</body>

</html>