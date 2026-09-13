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
        content="Connect Argo Books to the tools you already use. Import your Stripe sales, fees, and customers automatically, or send data from your own systems through the Argo Books API.">
    <meta name="keywords"
        content="Argo Books integrations, Stripe integration, Square integration, PayPal integration, Shopify integration, Etsy integration, WooCommerce integration, Gumroad integration, Argo Books API, accounting software integrations, connect Stripe to accounting software">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Integrations | Argo Books">
    <meta property="og:description"
        content="Connect Argo Books to the tools you already use. Import your Stripe sales, fees, and customers automatically, or send data from your own systems through the Argo Books API.">
    <meta property="og:url" content="https://argorobots.com/integrations/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Integrations | Argo Books">
    <meta name="twitter:description"
        content="Connect Argo Books to the tools you already use. Import your Stripe sales, fees, and customers automatically, or send data from your own systems through the Argo Books API.">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Additional SEO Meta Tags -->
    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/integrations/">

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", "Integrations" => "/integrations/"]) ?></script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Integrations | Argo Books</title>

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
            <h1 class="animate-fade-in">Integrations</h1>
            <p class="hero-subtitle animate-fade-in">Bring the tools you already use into your books. Connect an existing tool, or your own systems through the API, so your sales, fees, and customers arrive on their own.</p>
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

    <!-- Integrations Grid Section -->
    <section class="features-overview rise-section">
        <div class="container">
            <div class="rise-sheet">
            <div class="section-header">
                <h2 class="section-title">What you can connect</h2>
                <p class="section-desc">Stripe and the Argo Books API are live today.</p>
            </div>
            <div class="features-grid">
                <!-- Stripe (live) -->
                <a href="<?= $base ?>integrations/stripe/" class="feature-card">
                    <div class="feature-card-icon blue">
                        <?= svg_icon('credit-card', 24) ?>
                    </div>
                    <h3>Stripe</h3>
                    <p>Import your Stripe sales, fees, tax, and customers into your books automatically.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>

                <!-- Argo Books API (live) -->
                <a href="<?= $base ?>integrations/argo-books-api/" class="feature-card">
                    <div class="feature-card-icon green">
                        <?= svg_icon('bolt', 24) ?>
                    </div>
                    <h3>Argo Books API</h3>
                    <p>Connect your own store, booking system or in-house tool and send sales straight into your books.</p>
                    <span class="feature-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>
            </div>

            <p class="section-desc" style="text-align: center; margin-top: 28px;">Next up: Square, PayPal, Shopify, Etsy, WooCommerce and Gumroad.</p>
            </div>
        </div>
    </section>

    <!-- Why Connect Section -->
    <section class="why-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <span class="section-label">Why Connect</span>
                <h2 class="section-title">Less typing, more accurate books</h2>
                <p class="section-desc">Integrations pull the details in for you, so nothing gets missed and nothing gets entered twice.</p>
            </div>
            <div class="why-grid">
                <div class="why-card animate-on-scroll">
                    <div class="why-card-icon">
                        <?= svg_icon('check', 28, '', 2.5) ?>
                    </div>
                    <h3>No manual data entry</h3>
                    <p>Sales, fees, and customers come in automatically, with the right amounts and categories already filled in.</p>
                </div>
                <div class="why-card animate-on-scroll">
                    <div class="why-card-icon">
                        <?= svg_icon('shield', 28) ?>
                    </div>
                    <h3>Limited by design</h3>
                    <p>Stripe connects with a restricted, read-only key. The API can only add to a review list you approve. Neither can move money or change records you already have.</p>
                </div>
                <div class="why-card animate-on-scroll">
                    <div class="why-card-icon">
                        <?= svg_icon('dollar', 28) ?>
                    </div>
                    <h3>Free to connect</h3>
                    <p>Integrations are included on every plan. Connect as many as you like at no extra cost.</p>
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
                    <h2>Ready to connect your tools?</h2>
                    <p>Download Argo Books for free and connect Stripe, or your own systems, in a few minutes.</p>
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
