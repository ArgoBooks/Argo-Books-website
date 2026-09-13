<?php
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/pricing.php';
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../track_referral.php';
require_once __DIR__ . '/../partials/fonts.php';

$pricing = get_pricing_config();
$plans = get_plan_features();
$monthlyPrice = $pricing['premium_monthly_price'];
$yearlyPrice = $pricing['premium_yearly_price'];
$yearlySavings = ($monthlyPrice * 12) - $yearlyPrice;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Argo">

    <!-- SEO Meta Tags -->
    <meta name="description"
        content="Argo Books Pricing. Subscribe to Premium for $<?php echo number_format($monthlyPrice, 0); ?>/month. Unlimited invoices, biometric login, AI-powered insights, and more.">
    <meta name="keywords"
        content="argo books pricing, buy full version, unlimited invoices, business software pricing, finance tracker, premium subscription">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Argo Books Pricing: Free Plan and Affordable Premium">
    <meta property="og:description"
        content="Argo Books Pricing. Subscribe to Premium for $<?php echo number_format($monthlyPrice, 0); ?>/month. Unlimited invoices, AI-powered insights, and more.">
    <meta property="og:url" content="https://argorobots.com/pricing/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Argo Books Pricing: Free Plan and Affordable Premium">
    <meta name="twitter:description"
        content="Argo Books Pricing. Subscribe to Premium for $<?php echo number_format($monthlyPrice, 0); ?>/month. Unlimited invoices, AI-powered insights, and more.">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/pricing/">

    <!-- FAQ Schema -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [
                {
                    "@type": "Question",
                    "name": "How does the Free plan work?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Argo Books is free to download and use. No credit card, no trial period, no strings attached. The Free plan includes all core features: products, customers and transactions with no cap, real-time analytics, receipt management, <?= (int) $pricing['free_invoice_monthly_limit'] ?> invoices per month, <?= (int) $pricing['free_receipt_scan_monthly_limit'] ?> AI receipt scans per month, and <?= (int) $pricing['ai_import_monthly_limit'] ?> AI spreadsheet imports per month. You can use it for as long as you like."
                    }
                },
                {
                    "@type": "Question",
                    "name": "What does Premium include?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Premium includes unlimited invoicing, <?= (int) $pricing['receipt_scan_monthly_limit'] ?> AI receipt scans per month, predictive analytics that forecast trends in your data, biometric login (Windows Hello, Touch ID or your Linux login), and priority customer support. Premium is available at $<?php echo number_format($monthlyPrice, 0); ?> CAD/month or $<?php echo number_format($yearlyPrice, 0); ?> CAD/year. The annual plan saves you $<?php echo number_format($yearlySavings, 0); ?> per year."
                    }
                },
                {
                    "@type": "Question",
                    "name": "I have multiple companies. Do I need to pay extra?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "No. Argo Books supports an unlimited number of companies, even on the Free plan. Each business gets its own separate transactions, invoices, and reports, so you can keep everything cleanly organized. There's no per-company fee, no upgrade required, and no cap on how many businesses you can run."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Can I cancel or change my plan anytime?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Yes. You can cancel your Premium subscription at any time from your customer portal. No phone calls, no hoops to jump through. Your Premium features stay active until the end of your current billing period. You can also switch between monthly and yearly billing whenever it suits you."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Do I need to install anything?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Yes. Argo Books is a desktop application, which is what makes it fast and private. Download the installer for Windows, macOS, or Linux, and you're up and running in under a minute. Because your data lives on your computer, Argo Books works offline too. You only need an internet connection for AI-powered features like receipt scanning and spreadsheet import."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Is my payment information secure?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Absolutely. All payments are processed through Stripe, PayPal, or Square. We never see or store your card details. These are PCI-compliant payment processors trusted by millions of businesses worldwide. Your Argo Books business data is also encrypted locally with AES-256-GCM, the same standard used by banks."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Do I still need an accountant?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "For day-to-day bookkeeping, probably not. Argo Books is designed to be simple enough that any small business owner can manage their own books. It keeps your transactions organized and produces the financial reports you'd otherwise pay someone to prepare. If you do work with an accountant at tax time, you can simply hand them your Argo Books file. The built-in report generator can produce income statements, balance sheets, cash flow statements, general ledgers, accounts receivable aging, and tax summaries: everything an accountant typically asks for."
                    }
                },
                {
                    "@type": "Question",
                    "name": "What if I need help getting started?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "All users have access to our documentation and community forum. Premium subscribers get priority support with faster response times. You can also reach us through our contact page. Evan, who builds Argo Books, reads every message personally."
                    }
                }
            ]
        }
    </script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Argo Books Pricing: Free Plan and Affordable Premium</title>

    <script src="../resources/scripts/main.js"></script>
    <script defer src="../resources/scripts/reveal.js"></script>

    <link rel="stylesheet" href="../resources/styles/marketing-sections.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
    <link rel="stylesheet" href="../resources/styles/faq.css">
    <link rel="stylesheet" href="../resources/styles/pricing-cards.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
    <!-- Brand typefaces (Fraunces display + IBM Plex Sans body), matched to the rest of the site -->
    <?= argo_font_links('default', '    ') ?>
    <link rel="stylesheet" href="../resources/styles/typography.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>
    <main>

    <section class="hero">
        <div class="container">
            <h1>Find a plan that's right for you</h1>
            <p class="hero-subtitle">Premium adds unlimited invoices, more AI receipt scans, predictive analytics and biometric login.</p>
        </div>
    </section>

    <section class="pricing-section">
        <div class="container">
            <?php
            $pricingCardsOptions = [
                'free_cta_url'     => '../downloads/',
                'premium_cta_url'  => 'premium/',
            ];
            include __DIR__ . '/../partials/pricing-cards.php';
            ?>
        </div>
    </section>

    <!-- =============================================
         PREMIUM FEATURES INTRO
         ============================================= -->
    <section class="premium-intro">
        <div class="container">
            <div class="premium-intro-inner animate-on-scroll">
                <h2>What Premium adds</h2>
                <p>Receipt scanning, revenue forecasts and unlimited invoicing with online payments, each explained below.</p>
            </div>
        </div>
    </section>

    <!-- =============================================
         DETAIL: AI Receipt Scanning
         Text left, image right
         ============================================= -->
    <section class="feature-detail-section">
        <div class="container">
            <div class="feature-detail animate-on-scroll">
                <div class="feature-detail-text">
                    <h2>Scan receipts in seconds, not minutes</h2>
                    <p>Point your camera at any receipt and Argo Books extracts the supplier, date, and total automatically, with no typing required. Receipts are categorized and attached to your expense records instantly.</p>
                    <ul class="feature-checklist">
                        <li><?= svg_icon('check', 20) ?><span><?= (int) $pricing['receipt_scan_monthly_limit'] ?> receipt scans per month</span></li>
                        <li><?= svg_icon('check', 20) ?><span>Auto-categorized by expense type</span></li>
                        <li><?= svg_icon('check', 20) ?><span>Attached directly to your transaction records</span></li>
                    </ul>
                </div>
                <div class="feature-detail-visual">
                    <img src="../resources/images/features/receipt-archive.svg" alt="AI receipt scanning archive showing scanned receipts organized by category" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         DETAIL: Predictive Analytics
         Image left, text right (reversed)
         ============================================= -->
    <section class="feature-detail-section" style="background: var(--gray-50);">
        <div class="container">
            <div class="feature-detail reversed animate-on-scroll">
                <div class="feature-detail-text">
                    <h2>See where your revenue is heading</h2>
                    <p>Argo Books analyzes your historical data to surface trends and forecast revenue. Built-in charts show where your business is headed, with no spreadsheets or external tools needed.</p>
                    <ul class="feature-checklist">
                        <li><?= svg_icon('check', 20) ?><span>Revenue and expense forecasting</span></li>
                        <li><?= svg_icon('check', 20) ?><span>Seasonal trend detection</span></li>
                        <li><?= svg_icon('check', 20) ?><span>Profit margin and anomaly alerts</span></li>
                    </ul>
                </div>
                <div class="feature-detail-visual">
                    <img src="../resources/images/features/analytics-dashboard.svg" alt="Predictive analytics dashboard showing revenue forecasts and trend charts" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         DETAIL: Invoices & Payments
         Text left, image right
         ============================================= -->
    <section class="feature-detail-section">
        <div class="container">
            <div class="feature-detail animate-on-scroll">
                <div class="feature-detail-text">
                    <h2>Send unlimited invoices and accept online payments</h2>
                    <p>The free plan caps invoices at <?= (int) $pricing['free_invoice_monthly_limit'] ?> per month. Premium removes that limit entirely, and adds online payment links so customers can pay directly from the invoice by credit card.</p>
                    <ul class="feature-checklist">
                        <li><?= svg_icon('check', 20) ?><span>Unlimited invoices with no monthly cap</span></li>
                        <li><?= svg_icon('check', 20) ?><span>Online payment links via Stripe, PayPal &amp; Square</span></li>
                        <li><?= svg_icon('check', 20) ?><span>Real-time status tracking from draft to paid</span></li>
                    </ul>
                </div>
                <div class="feature-detail-visual">
                    <img src="../resources/images/features/invoice-payment.svg" alt="Invoice payment page showing a secure online payment form with invoice details" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         ALSO IN PREMIUM
         The two Premium features the blocks above don't cover.
         ============================================= -->
    <section class="benefits-section" style="background: var(--gray-50);">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <h2 class="section-title">Also in Premium</h2>
            </div>
            <div class="benefits-grid">
                <div class="benefit-card animate-on-scroll">
                    <div class="benefit-card-icon">
                        <?= svg_icon('shield', 22) ?>
                    </div>
                    <h3>Biometric Login</h3>
                    <p>Open your books with your fingerprint or face instead of typing your password each time. Works with Windows Hello, Touch ID on a Mac, and your Linux login.</p>
                </div>
                <div class="benefit-card animate-on-scroll">
                    <div class="benefit-card-icon">
                        <?= svg_icon('message-circle', 22) ?>
                    </div>
                    <h3>Priority Support</h3>
                    <p>Support messages from Premium subscribers are answered first.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="faq">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            <?php $faqs = [];
            ob_start(); ?>How does the Free plan work?<?php $q = ob_get_clean();
            ob_start(); ?>

                            <p>Argo Books is free to download and use. No credit card, no trial period, no strings attached. The Free plan includes all core features: products, customers and transactions with no cap, real-time analytics, receipt management, <?= (int) $pricing['free_invoice_monthly_limit'] ?> invoices per month, <?= (int) $pricing['free_receipt_scan_monthly_limit'] ?> AI receipt scans per month, and <?= (int) $pricing['ai_import_monthly_limit'] ?> AI spreadsheet imports per month.</p>
                            <p>You can use it for as long as you like. When your business needs more, upgrading to Premium takes just a few clicks.</p>
                        
            <?php $faqs[] = ['q_html' => $q, 'a_html' => ob_get_clean()];
            ob_start(); ?>What does Premium include?<?php $q = ob_get_clean();
            ob_start(); ?>

                            <p>Premium includes unlimited invoicing, <?= (int) $pricing['receipt_scan_monthly_limit'] ?> AI receipt scans per month, predictive analytics that forecast trends in your data, biometric login (Windows Hello, Touch ID or your Linux login), and priority customer support.</p>
                            <p>Premium is available at <strong>$<?php echo number_format($monthlyPrice, 0); ?> CAD/month</strong> or <strong>$<?php echo number_format($yearlyPrice, 0); ?> CAD/year</strong>. The annual plan saves you $<?php echo number_format($yearlySavings, 0); ?> per year.</p>
                        
            <?php $faqs[] = ['q_html' => $q, 'a_html' => ob_get_clean()];
            ob_start(); ?>I have multiple companies. Do I need to pay extra?<?php $q = ob_get_clean();
            ob_start(); ?>

                            <p>No. Argo Books supports an unlimited number of companies, even on the Free plan. Each business gets its own separate transactions, invoices, and reports, so you can keep everything cleanly organized.</p>
                            <p>There's no per-company fee, no upgrade required, and no cap on how many businesses you can run.</p>
                        
            <?php $faqs[] = ['q_html' => $q, 'a_html' => ob_get_clean()];
            ob_start(); ?>Can I cancel or change my plan anytime?<?php $q = ob_get_clean();
            ob_start(); ?>

                            <p>Yes. You can cancel your Premium subscription at any time from your <a href="../portal/">customer portal</a>. No phone calls, no hoops to jump through. Your Premium features stay active until the end of your current billing period.</p>
                            <p>You can also switch between monthly and yearly billing whenever it suits you. If you believe you're entitled to a refund, <a href="../contact.php">get in touch</a> and we'll review your request. <a href="../legal/refund.php">View full refund policy</a></p>
                        
            <?php $faqs[] = ['q_html' => $q, 'a_html' => ob_get_clean()];
            ob_start(); ?>Do I need to install anything?<?php $q = ob_get_clean();
            ob_start(); ?>

                            <p>Yes. Argo Books is a desktop application, which is what makes it fast and private. Download the installer for Windows, macOS, or Linux, and you're up and running in under a minute. Because your data lives on your computer, Argo Books works offline too.</p>
                            <p>You only need an internet connection for AI-powered features like receipt scanning and spreadsheet import. <a href="../downloads/">Download Argo Books</a></p>
                        
            <?php $faqs[] = ['q_html' => $q, 'a_html' => ob_get_clean()];
            ob_start(); ?>Is my payment information secure?<?php $q = ob_get_clean();
            ob_start(); ?>

                            <p>Absolutely. All payments are processed through Stripe, PayPal, or Square. We never see or store your card details. These are PCI-compliant payment processors trusted by millions of businesses worldwide.</p>
                            <p>Your Argo Books business data is also encrypted locally with AES-256-GCM, the same standard used by banks.</p>
                        
            <?php $faqs[] = ['q_html' => $q, 'a_html' => ob_get_clean()];
            ob_start(); ?>Do I still need an accountant?<?php $q = ob_get_clean();
            ob_start(); ?>

                            <p>For day-to-day bookkeeping, probably not. Argo Books is designed to be simple enough that any small business owner can manage their own books. It keeps your transactions organized and produces the financial reports you'd otherwise pay someone to prepare.</p>
                            <p>If you do work with an accountant at tax time, you can simply hand them your Argo Books file. The built-in <a href="../documentation/pages/features/report-generator.php">report generator</a> can produce income statements, balance sheets, cash flow statements, general ledgers, accounts receivable aging, and tax summaries: everything an accountant typically asks for.</p>
                        
            <?php $faqs[] = ['q_html' => $q, 'a_html' => ob_get_clean()];
            ob_start(); ?>What if I need help getting started?<?php $q = ob_get_clean();
            ob_start(); ?>

                            <p>All users have access to our <a href="../documentation/">documentation</a> and <a href="../community/">community forum</a>. Premium subscribers get priority support with faster response times.</p>
                            <p>You can also reach us through our <a href="../contact.php">contact page</a>. Evan, who builds Argo Books, reads every message personally.</p>
                        
            <?php $faqs[] = ['q_html' => $q, 'a_html' => ob_get_clean()];
            echo argo_faq_grid($faqs); ?>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Pricing cycle toggle
        document.querySelectorAll('.pcards-cycle-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const cycle = this.dataset.cycle;
                document.querySelectorAll('.pcards-cycle-btn').forEach(b => {
                    const isActive = b === this;
                    b.classList.toggle('active', isActive);
                    b.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                document.querySelectorAll('[data-active-cycle]').forEach(c => {
                    c.dataset.activeCycle = cycle;
                });
                // Carry the chosen cycle through so /pricing/premium/ can hand it
                // straight to checkout instead of asking a second time.
                document.querySelectorAll('[data-premium-cta]').forEach(a => {
                    a.href = a.dataset.baseUrl + '?billing=' + cycle;
                });
                // Slide the new price in from the side the toggle moved toward.
                // Only the Premium card animates; the free card's $0 doesn't change.
                const from = cycle === 'yearly' ? 'price-swap-right' : 'price-swap-left';
                document.querySelectorAll('.pcard-premium .pcard-price').forEach(p => {
                    p.classList.remove('price-swap-right', 'price-swap-left');
                    void p.offsetWidth; // reflow, so the animation restarts on repeat switches
                    p.classList.add(from);
                });
            });
        });

    });
    </script>

    </main>

    <div class="dark-section-wrapper">
        <section class="cta-section">
            <div class="container">
                <div class="cta-card animate-on-scroll">
                    <h2>Premium for $<?php echo number_format($monthlyPrice, 0); ?>/month</h2>
                    <p>Everything in Free, plus AI receipt scanning, predictive analytics, unlimited invoices, and biometric login.</p>
                    <div class="cta-buttons">
                        <a href="premium/" class="btn-cta btn-cta-primary">
                            <span>Get Premium</span>
                            <?= svg_icon('arrow-right', 18) ?>
                        </a>
                        <a href="../downloads/" class="btn-cta btn-cta-ghost">
                            <span>Download Free</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <footer class="footer">
            <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
        </footer>
    </div>
</body>

</html>
