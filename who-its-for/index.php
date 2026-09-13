<?php
require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../track_referral.php';
require_once __DIR__ . '/../statistics.php';
require_once __DIR__ . '/../partials/fonts.php';

if (PHP_SAPI !== 'cli') {
    track_page_view('who_its_for_hub');
}

$cta_source = 'who-its-for-hub';
$download_url = '../downloads/?source=' . $cta_source;

$niches = [
    [
        'slug'  => 'landscapers',
        'name'  => 'Landscapers',
        'icon'  => 'leaf',
        'tone'  => 'green',
        'blurb' => 'Deposits, materials, and seasonal cashflow, without the bookkeeping headache.',
    ],
    [
        'slug'  => 'contractors',
        'name'  => 'Contractors',
        'icon'  => 'hard-hat',
        'tone'  => 'amber',
        'blurb' => 'Progress billing across deposits, mid-job draws, change orders, and final balances.',
    ],
    [
        'slug'  => 'repair-shops',
        'name'  => 'Repair Shops',
        'icon'  => 'wrench',
        'tone'  => 'blue',
        'blurb' => 'Diagnostic fee, parts at your markup, and labor at your shop rate, on one clean invoice.',
    ],
    [
        'slug'  => 'rental-businesses',
        'name'  => 'Rental Businesses',
        'icon'  => 'package-clock',
        'tone'  => 'purple',
        'blurb' => 'Track what is out, who has it, when it is coming back, and what they owe. Rental management built in.',
    ],
    [
        'slug'  => 'cleaning-companies',
        'name'  => 'Cleaning Companies',
        'icon'  => 'spray-bottle',
        'tone'  => 'blue',
        'blurb' => 'Recurring invoices, supply costs, and the difference between a profitable client and a busy one.',
    ],
    [
        'slug'  => 'local-wholesalers',
        'name'  => 'Local Wholesalers',
        'icon'  => 'truck',
        'tone'  => 'green',
        'blurb' => 'Net-30 invoicing, standing orders, and inventory built in for distributors serving small accounts.',
    ],
    [
        'slug'  => 'resellers',
        'name'  => 'Resellers',
        'icon'  => 'tag',
        'tone'  => 'amber',
        'blurb' => 'Cost of goods, sourcing receipts, and real margins for thrift flippers and online resellers.',
    ],
    [
        'slug'  => 'auto-detailing',
        'name'  => 'Auto Detailing',
        'icon'  => 'car',
        'tone'  => 'purple',
        'blurb' => 'Tiered packages, ceramic coating jobs, and the supply receipts that quietly add up.',
    ],
    [
        'slug'  => 'solo-operators',
        'name'  => 'Solo Operators with Inventory',
        'icon'  => 'user-package',
        'tone'  => 'blue',
        'blurb' => 'One person doing all the jobs: materials, finished goods, and the books, in one app.',
    ],
    [
        'slug'  => 'software-companies',
        'name'  => 'Software & SaaS Companies',
        'icon'  => 'code-window',
        'tone'  => 'green',
        'blurb' => 'Connect Stripe with a read-only key and your charges, fees, and refunds land in the books for you.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Argo">

    <meta name="description"
        content="Accounting software for small business owners who do their own bookkeeping. See how Argo Books fits landscapers, contractors, repair shops, rentals, software companies, and more.">
    <meta name="keywords"
        content="who is argo books for, argo books industries, accounting software by industry, small business accounting software by trade">

    <meta property="og:title" content="Who Argo Books is For: Industries and Trades We Built It For">
    <meta property="og:description"
        content="Landscapers, contractors, repair shops, rental businesses, cleaning companies, wholesalers, resellers, auto detailers, solo operators with inventory, and software companies.">
    <meta property="og:url" content="https://argorobots.com/who-its-for/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Who Argo Books is For: Industries and Trades We Built It For">
    <meta name="twitter:description"
        content="Bookkeeping built for the way small businesses actually work, across ten industries.">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">

    <link rel="canonical" href="https://argorobots.com/who-its-for/">

    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", "Who It's For" => "/who-its-for/"]) ?></script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Who Argo Books is For: Industries and Trades We Built It For</title>

    <script src="../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="../compare/style.css">
    <link rel="stylesheet" href="../resources/styles/for-pages.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
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

    <section class="hero wif-hero">
        <div class="container">
            <div class="wif-hero-grid">
                <div class="wif-hero-copy">
                    <h1 class="animate-fade-in">Who Argo Books is for</h1>
                    <p class="hero-subtitle animate-fade-in">Argo Books was built for small business owners doing their own bookkeeping. Here's how it fits the work you actually do.</p>
                    <div class="hero-ctas animate-fade-in">
                        <a href="<?= htmlspecialchars($download_url) ?>" class="btn-cta btn-cta-primary">
                            <span>Download Free</span>
                            <?= svg_icon('arrow-right', 18) ?>
                        </a>
                        <a href="#niches" class="btn-cta btn-cta-outline">
                            <span>Find Your Industry</span>
                        </a>
                    </div>
                    <p class="hero-reassurance animate-fade-in">Free desktop app for Windows, macOS, and Linux. No account, no credit card.</p>
                </div>
                <div class="wif-hero-intro animate-fade-in">
                    <h2>Ten industries, one app</h2>
                    <p>Each page below covers how Argo Books handles the billing patterns, supply costs, and reporting that matter for that line of work. If your industry is not listed, the core bookkeeping still works the same way: invoices, expenses, reports, and (where it applies) inventory or rentals.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="niches-section">
        <div class="container">
            <div id="niches" class="niche-card-grid">
                <?php foreach ($niches as $n): ?>
                <a href="../for-<?= htmlspecialchars($n['slug']) ?>/?source=<?= htmlspecialchars($cta_source) ?>" class="niche-card animate-on-scroll">
                    <div class="niche-card-icon <?= htmlspecialchars($n['tone']) ?>">
                        <?= svg_icon($n['icon'], 28, '', 1.5) ?>
                    </div>
                    <h3>For <?= htmlspecialchars($n['name']) ?></h3>
                    <p><?= htmlspecialchars($n['blurb']) ?></p>
                    <span class="niche-card-link">Learn more <?= svg_icon('arrow-right', 16) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="honest-take-alt">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <span class="section-label">Not on the list?</span>
                <h2>The core works for most small businesses</h2>
                <p class="section-desc">If your industry isn't on this page, that doesn't mean Argo Books won't fit. The core (invoicing, expense tracking, receipt scanning, customer management, financial reports, and where it applies, inventory or rentals) covers most small businesses regardless of trade. The pages above are just the ones we've written specific copy for. Download the free version and try it against your actual workflow. If it doesn't fit, you've lost nothing.</p>
                <a href="<?= htmlspecialchars($download_url) ?>" class="btn-cta btn-cta-primary honest-take-cta">
                    <span>Download Free</span>
                    <?= svg_icon('arrow-right', 18) ?>
                </a>
            </div>
        </div>
    </section>

    </main>

    <div class="dark-section-wrapper">
        <section class="cta-section">
            <div class="container">
                <div class="cta-card animate-on-scroll">
                    <h2>Ready to try it on your business?</h2>
                    <p>Download Argo Books for free. Set up your first customer, scan a receipt, and send an invoice in under ten minutes.</p>
                    <div class="cta-buttons">
                        <a href="<?= htmlspecialchars($download_url) ?>" class="btn-cta btn-cta-primary">
                            <span>Download Free</span>
                            <?= svg_icon('arrow-right', 18) ?>
                        </a>
                        <a href="../pricing/?source=<?= htmlspecialchars($cta_source) ?>" class="btn-cta btn-cta-ghost">
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
