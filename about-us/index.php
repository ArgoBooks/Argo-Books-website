<?php require_once __DIR__ . '/../resources/icons.php';
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
        content="Learn about Argo, the Canada-based startup creating affordable finance management software for small businesses. Built by Evan, a self-funded founder in Saskatoon.">
    <meta name="keywords"
        content="about argo books, Canada startup, small business software company, affordable business tools, finance management developers, canadian software company, Canadian, saskatchewan tech company">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="About Us - Argo Books | Canadian Software Company">
    <meta property="og:description"
        content="Learn about Argo, the Canada-based startup creating affordable finance management software for small businesses. Built by Evan, a self-funded founder in Saskatoon.">
    <meta property="og:url" content="https://argorobots.com/about-us/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="About Us - Argo Books | Canadian Software Company">
    <meta name="twitter:description"
        content="Learn about Argo, the Canada-based startup creating affordable finance management software for small businesses. Built by Evan, a self-funded founder in Saskatoon.">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Additional SEO Meta Tags -->
    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">
    <meta name="geo.position" content="52.1579;-106.6702">
    <meta name="ICBM" content="52.1579, -106.6702">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/about-us/">

    <!-- Organization Schema -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Organization",
            "name": "Argo Books",
            "url": "https://argorobots.com/",
            "description": "Canada-based startup creating affordable finance management software for small businesses.",
            "address": {
                "@type": "PostalAddress",
                "addressLocality": "Saskatoon",
                "addressRegion": "SK",
                "addressCountry": "CA"
            },
            "foundingLocation": {
                "@type": "Place",
                "name": "Saskatoon, Saskatchewan, Canada"
            }
        }
    </script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>About Us - Argo Books | Canadian Software Company</title>

    <script src="../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="style.css">
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
    <section class="hero">
        <div class="container">
            <div class="hero-copy">
                <h1>About Argo Books</h1>
                <p class="hero-subtitle">Accounting software for small businesses, built and supported in Saskatoon, Canada.</p>
            </div>
            <figure class="hero-photo">
                <div class="hero-photo-frame">
                    <img src="../resources/images/saskatoon.webp" alt="Saskatoon skyline" fetchpriority="high">
                </div>
                <figcaption><?= svg_icon('map-pin', 16) ?> Saskatoon, SK, Canada</figcaption>
            </figure>
        </div>
    </section>

    <!-- Mission Section -->
    <section class="mission">
        <div class="container">
            <div class="mission-grid">
                <div class="mission-content animate-on-scroll">
                    <h2>Affordable tools for every business</h2>
                    <p>Most finance management software needs an expensive monthly subscription and is difficult
                        to use. I built Argo Books to be affordable and easy to pick up.</p>
                    <div class="mission-points">
                        <div class="mission-point">
                            <div class="point-icon">
                                <?= svg_icon('check-rounded') ?>
                            </div>
                            <p>Better than spreadsheets, simpler than enterprise software</p>
                        </div>
                        <div class="mission-point">
                            <div class="point-icon">
                                <?= svg_icon('check-rounded') ?>
                            </div>
                            <p>Easy to learn, with the features a small business needs</p>
                        </div>
                        <div class="mission-point">
                            <div class="point-icon">
                                <?= svg_icon('check-rounded') ?>
                            </div>
                            <p>Free to use, with a paid Premium plan when you need more</p>
                        </div>
                    </div>
                </div>
                <div class="mission-image animate-on-scroll">
                    <img src="../resources/images/dashboard.webp" alt="Argo Books Interface">
                </div>
            </div>
        </div>
    </section>

    <!-- Product Overview Section -->
    <section class="product-overview">
        <div class="container">
            <div class="overview-content animate-on-scroll">
                <h2>What Argo Books does</h2>
                <p>Argo Books is a free app for Windows, macOS and Linux, made for small businesses, startups and
                    solo entrepreneurs who need an affordable way to manage their finances and handle everyday
                    bookkeeping.</p>
            </div>
            <div class="features-grid">
                <a class="feature-item animate-on-scroll" href="../features/receipt-scanning/">
                    <div class="feature-icon">
                        <?= svg_icon('receipt-scan-detail', null, '', 1.5) ?>
                    </div>
                    <h3>Receipt Scanning</h3>
                    <p>Snap a photo and let Argo Books extract all the details automatically</p>
                </a>
                <a class="feature-item animate-on-scroll" href="../features/invoicing/">
                    <div class="feature-icon">
                        <?= svg_icon('document', null, '', 1.5) ?>
                    </div>
                    <h3>Invoicing &amp; Payments</h3>
                    <p>Create professional invoices and get paid faster</p>
                </a>
                <a class="feature-item animate-on-scroll" href="../features/predictive-analytics/">
                    <div class="feature-icon">
                        <?= svg_icon('analytics', null, '', 1.5) ?>
                    </div>
                    <h3>Predictive Analytics</h3>
                    <p>Forecast sales trends from your own numbers</p>
                </a>
                <a class="feature-item animate-on-scroll" href="../features/expense-revenue-tracking/">
                    <div class="feature-icon">
                        <?= svg_icon('dollar', null, '', 1.5) ?>
                    </div>
                    <h3>Expense &amp; Revenue</h3>
                    <p>See exactly where your money comes in and goes out, all in one place</p>
                </a>
            </div>
            <div class="features-cta animate-on-scroll">
                <a href="../features/" class="features-cta-link">
                    <span>View all features</span>
                    <?= svg_icon('arrow-right', 18) ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Story Section -->
    <section class="our-story">
        <div class="container">
            <div class="story-grid">
                <div class="story-content animate-on-scroll">
                    <h2>Why I started Argo Books</h2>
                    <p>I started Argo Books in 2024 with a simple goal: make the finance tracking tool I
                        wished existed for my own small businesses.</p>
                    <p>I've dealt with the same challenges small businesses face. Argo Books isn't a large
                        corporation with venture capital funding. It's fully self-funded, so I know what it means
                        to watch every dollar and choose carefully what to spend on software. The core version
                        is free.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Founder Section -->
    <section class="founder">
        <div class="container">
            <div class="founder-grid">
                <figure class="founder-photo animate-on-scroll">
                    <img src="../resources/images/founder.jpg" alt="Evan, founder of Argo Books" width="675" height="900" loading="lazy">
                </figure>
                <div class="founder-content animate-on-scroll">
                    <h2>Hi, I'm Evan</h2>
                    <p>I'm the founder of Argo Books, based in Saskatoon. I built it to give small
                        businesses accounting software they can count on: capable enough to run the whole
                        business, simple enough to use from day one, and genuinely affordable.</p>
                    <p>Handling your business's finances is a responsibility I take seriously. Every
                        release is tested, and your data is kept secure. I'm committed
                        to keeping Argo Books dependable and improving it for years to come.</p>
                    <p>When you write in, you hear from the person who built it. I read every support
                        message myself and usually reply within 1-8 business hours.</p>
                    <div class="founder-footer">
                        <p class="founder-signature">Evan<span>Founder &amp; Developer, Argo Books</span></p>
                        <a href="../contact-us/" class="founder-contact">
                            <span>Have a question? Contact me</span>
                            <?= svg_icon('arrow-right', 18) ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Future Section -->
    <section class="future">
        <div class="container">
            <div class="future-content animate-on-scroll">
                <h2>What's next</h2>
                <p>Most new features and improvements come directly from user feedback. I plan to keep adding to
                    Argo Books while keeping it simple and affordable. Every update is listed in the changelog.</p>
                <a href="../whats-new/" class="btn btn-secondary">
                    <span>View Changelog</span>
                    <?= svg_icon('arrow-right', 18) ?>
                </a>
            </div>
        </div>
    </section>

    </main>

    <!-- Contact + Footer Wrapper -->
    <div class="dark-section-wrapper">
        <!-- Contact Section -->
        <section class="contact-section">
            <div class="container">
                <div class="contact-card animate-on-scroll">
                    <h2>Get in touch</h2>
                    <p>Questions and suggestions come straight to me, and they shape what gets built next.</p>
                    <a href="../contact-us/" class="btn btn-primary">
                        <span>Contact me</span>
                        <?= svg_icon('arrow-right', 18) ?>
                    </a>
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
