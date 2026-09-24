<?php
require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../track_referral.php';
require_once __DIR__ . '/../partials/fonts.php';
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
        content="Discover Argo Books features: AI receipt scanning, expense tracking, analytics, inventory, invoicing, Canadian payroll, and more.">
    <meta name="keywords"
        content="Argo Books features, AI receipt scanning, expense tracking software, predictive analytics, inventory management, invoicing software, rental management, customer management, spreadsheet import">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Features: AI-Powered Business Tools | Argo Books">
    <meta property="og:description"
        content="Discover Argo Books features: AI receipt scanning, expense tracking, analytics, inventory, invoicing, Canadian payroll, and more.">
    <meta property="og:url" content="https://argorobots.com/features/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Features: AI-Powered Business Tools | Argo Books">
    <meta name="twitter:description"
        content="Discover Argo Books features: AI receipt scanning, expense tracking, analytics, inventory, invoicing, Canadian payroll, and more.">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Additional SEO Meta Tags -->
    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/features/">

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", "Features" => "/features/"]) ?></script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Features: AI-Powered Business Tools | Argo Books</title>

    <script src="../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="../resources/styles/marketing-sections.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
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

    <!-- Hero Section -->
    <section class="hero hero--rise">
        <div class="container">
            <h1 class="animate-fade-in">What Argo Books does</h1>
            <p class="hero-subtitle animate-fade-in">Scan receipts with AI, track expenses and inventory, send invoices, run Canadian payroll, and see how your business is doing in one set of books.</p>
            <div class="hero-ctas animate-fade-in">
                <a href="../downloads/" class="btn-cta btn-cta-primary">
                    <span>Get Started Free</span>
                    <?= svg_icon('arrow-right', 18) ?>
                </a>
                <a href="../pricing/" class="btn-cta btn-cta-outline">
                    <span>View Pricing</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Features Grid Section -->
    <section class="features-overview rise-section">
        <div class="container">
            <div class="rise-sheet">
            <div class="section-header">
                <h2 class="section-title">What's in Argo Books</h2>
                <p class="section-desc">Receipt scanning, expense tracking, inventory, rentals, invoicing, payroll and reports in one desktop app. Each one has its own page with the details.</p>
            </div>
            <div class="features-grid">
                <!-- AI Receipt Scanning -->
                <a href="receipt-scanning/" class="feature-card">
                    <div class="feature-card-icon blue">
                        <?= svg_icon('receipt-scan-detail', 24) ?>
                    </div>
                    <h3>Receipt Scanning</h3>
                    <p>Snap a photo of any receipt and let AI extract the details automatically. No more manual data entry. Just scan, review, and save.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Expense & Revenue Tracking -->
                <a href="expense-revenue-tracking/" class="feature-card">
                    <div class="feature-card-icon green">
                        <?= svg_icon('dollar', 24) ?>
                    </div>
                    <h3>Expense & Revenue Tracking</h3>
                    <p>Track every dollar coming in and going out. Categorize transactions, monitor cash flow, and keep your books accurate with guided forms that prevent mistakes.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Predictive Analytics -->
                <a href="predictive-analytics/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon purple">
                        <?= svg_icon('analytics', 24) ?>
                    </div>
                    <h3>Predictive Analytics</h3>
                    <p>See what's coming before it happens. Our AI engine analyzes your financial data to forecast trends, spot seasonal patterns, and help you plan ahead.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Inventory Management -->
                <a href="inventory-management/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon amber">
                        <?= svg_icon('package', 24) ?>
                    </div>
                    <h3>Inventory Management</h3>
                    <p>Track stock levels in real time, set low-stock alerts, and manage your entire product catalog. Never run out of your best-selling items again.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Rental Management -->
                <a href="rental-management/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon cyan">
                        <?= svg_icon('calendar', 24) ?>
                    </div>
                    <h3>Rental Management</h3>
                    <p>Manage bookings, track rental periods, and handle returns all in one place. Perfect for equipment rental, event supplies, or any rental-based business.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Customer Management -->
                <a href="customer-management/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon red">
                        <?= svg_icon('users', 24) ?>
                    </div>
                    <h3>Customer Management</h3>
                    <p>Keep each customer's contact details and purchase history in one place, so you can see what they bought and when.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Quotes -->
                <a href="quotes/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon blue">
                        <?= svg_icon('check', 24, '', '2.4') ?>
                    </div>
                    <h3>Quotes</h3>
                    <p>Price up the work and send it, then let your customer accept or decline online. A yes becomes an invoice in one click.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Invoicing -->
                <a href="invoicing/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon blue">
                        <?= svg_icon('document', 24) ?>
                    </div>
                    <h3>Invoicing</h3>
                    <p>Create branded invoices from templates you customize, and track which ones have been paid.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- AI Spreadsheet Import -->
                <a href="spreadsheet-import/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon green">
                        <?= svg_icon('document-upload', 24) ?>
                    </div>
                    <h3>Spreadsheet Import</h3>
                    <p>Import data from any spreadsheet format. Our AI automatically maps columns, detects data types, and imports everything cleanly, with no manual mapping required.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Bank Statement Import -->
                <a href="bank-statement-import/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon purple">
                        <?= svg_icon('bank', 24) ?>
                    </div>
                    <h3>Bank Statement Import</h3>
                    <p>Drop in a CSV, Excel, or PDF bank statement and every line comes back as a categorized expense or revenue. Match against your books too, all without connecting your bank.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Report Builder -->
                <a href="report-builder/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon cyan">
                        <?= svg_icon('report', 24) ?>
                    </div>
                    <h3>Report Builder</h3>
                    <p>Build Income Statements, Balance Sheets, tax summaries, and more from your own data. Design each report your way and export a clean, branded PDF. Free to use.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Payroll -->
                <a href="payroll/" class="feature-card animate-on-scroll">
                    <div class="feature-card-icon green">
                        <?= svg_icon('user-focused', 24) ?>
                    </div>
                    <h3>Payroll</h3>
                    <p>Pay Canadian staff without a separate payroll service. CPP, EI and income tax from the CRA's own tables, pay stubs for your people, and T4s ready in January.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>
            </div>
            </div>
        </div>
    </section>

    <!-- Why Argo Books Section -->
    <section class="why-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <span class="section-label">Why Argo Books</span>
                <h2 class="section-title">Simple, local, and free to start</h2>
                <p class="section-desc">Argo Books is for people who keep their own books and don't want to learn accounting, depend on an internet connection, or pay before they've tried it.</p>
            </div>
            <div class="why-grid">
                <div class="why-card animate-on-scroll">
                    <div class="why-card-icon">
                        <?= svg_icon('check', 28, '', 2.5) ?>
                    </div>
                    <h3>No accounting knowledge needed</h3>
                    <p>Guided forms, smart validation, and a clean interface make it easy for anyone to track finances, even if you've never used accounting software before.</p>
                </div>
                <div class="why-card animate-on-scroll">
                    <div class="why-card-icon">
                        <?= svg_icon('shield', 28) ?>
                    </div>
                    <h3>Works offline, your data stays local</h3>
                    <p>Argo Books is a desktop app. Your financial data never leaves your computer. No cloud servers, no data sharing, no internet required to get work done.</p>
                </div>
                <div class="why-card animate-on-scroll">
                    <div class="why-card-icon">
                        <?= svg_icon('dollar', 28) ?>
                    </div>
                    <h3>Free forever, premium for power users</h3>
                    <p>The free version covers the essentials with unlimited products. Premium unlocks AI features, invoicing, and more, all for a fraction of what competitors charge.</p>
                </div>
            </div>
        </div>
    </section>

    </main>

    <!-- CTA + Footer Wrapper -->
    <div class="dark-section-wrapper">
        <!-- CTA Section -->
        <section class="cta-section">
            <div class="container">
                <div class="cta-card animate-on-scroll">
                    <h2>Ready to get started?</h2>
                    <p>Download Argo Books for free and see how simple managing your business can be.</p>
                    <div class="cta-buttons">
                        <a href="../downloads/" class="btn-cta btn-cta-primary">
                            <span>Download for Free</span>
                            <?= svg_icon('arrow-right', 18) ?>
                        </a>
                        <a href="../pricing/" class="btn-cta btn-cta-ghost">
                            <span>View Pricing</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <footer class="footer">
            <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
        </footer>
    </div>

    <script defer src="../resources/scripts/reveal.js"></script>
</body>

</html>