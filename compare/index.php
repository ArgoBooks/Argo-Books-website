<?php
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../partials/fonts.php';

// Single source for the comparison hub grid. Add a competitor here (and create
// the matching compare/<slug>/ page) and it shows up automatically.
//
// 'slug' is the directory name verbatim, and 'title' is the card's anchor text.
// The two shapes are deliberate: pages targeting a "{brand} alternatives" query
// are named and labelled that way, while the head-to-head pages keep the
// "Argo Books vs X" form. Anchor text and page target have to agree, so don't
// re-add a hardcoded "Argo Books vs" prefix to the template below.
$comparisons = [
    ['slug' => 'argo-books-vs-quickbooks',        'title' => 'QuickBooks alternative',           'hook' => 'Simpler and far more affordable, with none of the price creep.'],
    ['slug' => 'argo-books-vs-freshbooks',        'title' => 'Argo Books vs FreshBooks',         'hook' => 'Your actual books, not just invoicing, and no per-client limits.'],
    ['slug' => 'argo-books-vs-wave',              'title' => 'Argo Books vs Wave',               'hook' => 'Both free to start, but Argo does more, including inventory and offline.'],
    ['slug' => 'argo-books-vs-xero',              'title' => 'Argo Books vs Xero',               'hook' => 'Less complexity, a lower price, and no invoice caps.'],
    ['slug' => 'zipbooks-alternatives',           'title' => 'ZipBooks alternatives',            'hook' => 'A more capable free plan, and a cheaper upgrade.'],
    ['slug' => 'odoo-accounting-alternatives',    'title' => 'Odoo accounting alternatives',     'hook' => 'One flat price instead of Odoo\'s per-user ERP billing.'],
    ['slug' => 'honeybook-alternatives',          'title' => 'HoneyBook alternatives',           'hook' => 'Argo keeps your actual books; HoneyBook just books clients and invoices.'],
    ['slug' => 'sage-50-alternatives',            'title' => 'Sage 50 alternatives',             'hook' => 'A fraction of Sage 50\'s price, modern and cross-platform, minus the complexity.'],
    ['slug' => 'zoho-books-alternatives',         'title' => 'Zoho Books alternatives',          'hook' => 'Desktop, offline, and standalone, without getting pulled into Zoho\'s cloud suite.'],
    ['slug' => 'invoice2go-alternatives',         'title' => 'Invoice2Go alternatives',          'hook' => 'No invoice caps, and the whole books rather than just the billing.'],
    ['slug' => 'square-invoices-alternatives',    'title' => 'Square Invoices alternatives',     'hook' => 'Both free to start; Argo also tracks expenses, stock and reports.'],
    ['slug' => 'manager-io-alternatives',         'title' => 'Manager.io alternatives',          'hook' => 'Local data on both sides, without needing to know double-entry.'],
    ['slug' => 'bonsai-alternatives',             'title' => 'Bonsai alternatives',              'hook' => "One flat price instead of Bonsai's per-user billing."],
    ['slug' => 'gnucash-alternatives',            'title' => 'GnuCash alternatives',             'hook' => 'Local data on both sides, without needing an accounting background.'],
    ['slug' => 'argo-books-vs-spreadsheet',       'title' => 'Argo Books vs Spreadsheets',       'hook' => 'For when Excel stops being enough. Import your file and keep the history.'],
    // Payroll providers. These compete on a different axis to the accounting
    // tools above: every one of them bills a base fee plus a fee per employee.
    ['slug' => 'argo-books-vs-wagepoint',         'title' => 'Argo Books vs Wagepoint',          'hook' => 'Canadian payroll without the per-employee fee. Same deductions, flat price.'],
    ['slug' => 'quickbooks-payroll-alternatives', 'title' => 'QuickBooks Payroll alternatives',  'hook' => 'Payroll billed on top of a subscription, then per head, against one price.'],
    ['slug' => 'payworks-alternatives',           'title' => 'Payworks alternatives',            'hook' => 'A quoted service contract, against software you download and run yourself.'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Argo">

    <meta name="description" content="See how Argo Books compares to QuickBooks, Wave, FreshBooks, Xero, ZipBooks and Odoo. Side-by-side comparisons of features, pricing, and ease of use.">
    <meta name="keywords" content="Argo Books comparison, QuickBooks alternative, accounting software comparison, small business accounting alternatives">

    <meta property="og:title" content="Compare Argo Books to Other Accounting Software">
    <meta property="og:description" content="Side-by-side comparisons of Argo Books vs QuickBooks, Wave, FreshBooks, Xero, ZipBooks, and Odoo.">
    <meta property="og:url" content="https://argorobots.com/compare/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <link rel="canonical" href="https://argorobots.com/compare/">
    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Compare Argo Books to QuickBooks, Wave, Xero &amp; More | Argo Books</title>

    <script src="../resources/scripts/main.js"></script>

    <?= argo_font_links('default', '    ') ?>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
    <link rel="stylesheet" href="../resources/styles/typography.css">
</head>

<body class="compare-page compare-hub-page">
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>
    <main>

    <!-- Hero -->
    <section class="hero">
        <div class="container">
            <div class="hero-content animate-fade-in">
                <h1>How Argo Books compares</h1>
                <p class="hero-subtitle">See how Argo Books stacks up against the other accounting and invoicing tools, side by side, on features, pricing, and ease of use.</p>
                <div class="hero-ctas">
                    <a href="../downloads/" class="btn-cta btn-cta-primary">
                        <span>Try Argo Books Free</span>
                        <?= svg_icon('arrow-right', 18) ?>
                    </a>
                    <a href="../pricing/" class="btn-cta btn-cta-outline">
                        <span>View Pricing</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison grid -->
    <section class="compare-hub">
        <div class="container">
            <div class="hub-grid">
                <?php foreach ($comparisons as $c): ?>
                <a class="hub-card animate-on-scroll" href="<?= $c['slug'] ?>/">
                    <span class="hub-card-title"><?= $c['title'] ?></span>
                    <span class="hub-card-hook"><?= $c['hook'] ?></span>
                    <span class="hub-card-cta">Compare <?= svg_icon('arrow-right', 16) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    </main>

    <div class="dark-section-wrapper">
        <section class="cta-section">
            <div class="container">
                <div class="cta-card animate-on-scroll">
                    <h2>Ready to see the difference yourself?</h2>
                    <p>Download Argo Books for free, no credit card, and try it against whatever you use today.</p>
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
