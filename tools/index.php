<?php
// tools/index.php
//
// Free tools hub. A directory page that links out to the free, standalone
// tools, grouped by what they are for. Served at argorobots.com/tools/ via
// Apache DirectoryIndex.
//
// Unlike the tool pages themselves (which use the isolated invoice-generator
// layout), this hub is a normal marketing page: real site header + footer so
// visitors can navigate the rest of the site from here.

require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../partials/fonts.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('tools_hub');
}

// Icons are drawn here rather than taken from resources/icons.php because
// several tools (candle, soap, cake, tumbler, stall) have no fitting icon in
// the shared set, and mixing sources gave the list uneven line weights.
$tool_icons = [
    'invoice'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/>',
    'clipboard'   => '<path d="M9 2h6a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M8 12h8"/><path d="M8 16h5"/>',
    'cart'        => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L21 8H6"/>',
    'layout'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
    'scissors'    => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.12 15.88"/><path d="M14.47 14.48L20 20"/><path d="M8.12 8.12L12 12"/>',
    'candle'      => '<rect x="8" y="10" width="8" height="11" rx="1"/><path d="M12 7.5V10"/><path d="M12 2c1.4 1.5 2 2.5 2 3.4a2 2 0 0 1-4 0c0-.9.6-1.9 2-3.4z"/>',
    'soap'        => '<rect x="3" y="11" width="15" height="9" rx="3"/><circle cx="17" cy="6" r="2.5"/><circle cx="10.5" cy="6.5" r="1.5"/>',
    'tumbler'     => '<rect x="5" y="4" width="14" height="3" rx="1"/><path d="M6.5 7l1.3 13.1a1 1 0 0 0 1 .9h6.4a1 1 0 0 0 1-.9L17.5 7"/><path d="M13.5 4l1.5-3"/>',
    'cake'        => '<path d="M4 21v-8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8"/><path d="M4 15.5c2 0 2-1.5 4-1.5s2 1.5 4 1.5 2-1.5 4-1.5 2 1.5 4 1.5"/><path d="M2 21h20"/><path d="M12 7.5V11"/><path d="M12 2.5c1 1.1 1.5 1.8 1.5 2.5a1.5 1.5 0 0 1-3 0c0-.7.5-1.4 1.5-2.5z"/>',
    'bag'         => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'stall'       => '<path d="M3 4h18l-1.5 5h-15z"/><path d="M5 9v12"/><path d="M19 9v12"/><path d="M5 15h14"/>',
    'clock'       => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    'tag'         => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.1"/>',
    'break-even'  => '<path d="M3 3v18h18"/><path d="M7 17L20 6"/><path d="M7 11h13"/>',
    'overdue'     => '<path d="M21 10V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><circle cx="17" cy="17" r="5"/><path d="M17 15v2l1.5 1.5"/>',
    'tax-form'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 18l6-6"/><circle cx="9.5" cy="12.5" r="1"/><circle cx="14.5" cy="17.5" r="1"/>',
    'car'         => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12.4V16c0 .6.4 1 1 1h2"/><path d="M9 17h6"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
    'scan'        => '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/>',
    'chart'       => '<path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-5"/>',
];

$tool_groups = [
    [
        'heading' => 'Invoices and documents',
        'tools'   => [
            [
                'name'        => 'Invoice Generator',
                'description' => 'Fill in an invoice and download it as a PDF or Word file.',
                'href'        => '../invoice-generator/',
                'icon'        => 'invoice',
            ],
            [
                'name'        => 'Estimate Generator',
                'description' => 'Quote a job before the work starts, and send the client a PDF or Word copy.',
                'href'        => '../estimate-generator/',
                'icon'        => 'clipboard',
            ],
            [
                'name'        => 'Purchase Order Generator',
                'description' => 'Order stock or supplies from a vendor with an itemized purchase order.',
                'href'        => '../purchase-order-generator/',
                'icon'        => 'cart',
            ],
            [
                'name'        => 'Invoice Templates',
                'description' => 'Blank invoices in PDF, Word, Excel, Google Docs, and Google Sheets, in five styles.',
                'href'        => '../invoice-template/',
                'icon'        => 'layout',
            ],
        ],
    ],
    [
        'heading' => 'Selling handmade products',
        'tools'   => [
            [
                'name'        => 'Craft Pricing Calculator',
                'description' => 'Turn material cost, labour, and markup into a selling price for anything handmade.',
                'href'        => '../craft-pricing-calculator/',
                'icon'        => 'scissors',
            ],
            [
                'name'        => 'Candle Pricing Calculator',
                'description' => 'Cost per candle from a batch of wax, wicks, jars, and fragrance, and a price to sell at.',
                'href'        => '../candle-pricing-calculator/',
                'icon'        => 'candle',
            ],
            [
                'name'        => 'Soap Pricing Calculator',
                'description' => 'Cost per bar from a whole batch, and a price that covers your time. Not a lye calculator.',
                'href'        => '../soap-pricing-calculator/',
                'icon'        => 'soap',
            ],
            [
                'name'        => 'Tumbler Pricing Calculator',
                'description' => 'Price sublimation, vinyl, or epoxy tumblers with your time counted in the cost.',
                'href'        => '../tumbler-pricing-calculator/',
                'icon'        => 'tumbler',
            ],
            [
                'name'        => 'Cake Pricing Calculator',
                'description' => 'Ingredients, decorating time, board and box, and delivery, added up into a price.',
                'href'        => '../cake-pricing-calculator/',
                'icon'        => 'cake',
            ],
            [
                'name'        => 'Etsy Fee Calculator',
                'description' => 'Every fee Etsy takes from a sale and what you keep, or the price to list at for a target profit.',
                'href'        => '../etsy-fee-calculator/',
                'icon'        => 'bag',
            ],
            [
                'name'        => 'Craft Fair Calculator',
                'description' => 'How many sales cover the booth fee and travel, and whether the day paid for your hours.',
                'href'        => '../craft-fair-calculator/',
                'icon'        => 'stall',
            ],
        ],
    ],
    [
        'heading' => 'Rates, margins, and late payments',
        'tools'   => [
            [
                'name'        => 'Hourly Rate Calculator',
                'description' => 'The rate you need once unbillable hours, business costs, and tax are covered.',
                'href'        => '../hourly-rate-calculator/',
                'icon'        => 'clock',
            ],
            [
                'name'        => 'Markup vs Margin Calculator',
                'description' => 'Enter any two of cost, price, markup, or margin and get the other two.',
                'href'        => '../markup-margin-calculator/',
                'icon'        => 'tag',
            ],
            [
                'name'        => 'Break-Even Calculator',
                'description' => 'How many sales cover your fixed costs, and the profit on each sale after that.',
                'href'        => '../break-even-calculator/',
                'icon'        => 'break-even',
            ],
            [
                'name'        => 'Late Fee Calculator',
                'description' => 'Interest and fees on an overdue invoice, simple or compounding, and the total owed today.',
                'href'        => '../late-fee-calculator/',
                'icon'        => 'overdue',
            ],
        ],
    ],
    [
        'heading' => 'Tax',
        'tools'   => [
            [
                'name'        => 'Self-Employed Tax Calculator',
                'description' => 'Self-employment and income tax for 2026, and how much to set aside each quarter. US and Canada.',
                'href'        => '../self-employed-tax-calculator/',
                'icon'        => 'tax-form',
            ],
            [
                'name'        => 'Mileage Deduction Calculator',
                'description' => 'What your business driving is worth at tax time, including the 2026 US mid-year rate change.',
                'href'        => '../mileage-deduction-calculator/',
                'icon'        => 'car',
            ],
        ],
    ],
    [
        'heading' => 'Upload a receipt or spreadsheet',
        'tools'   => [
            [
                'name'        => 'Receipt Scanner',
                'description' => 'Pull the line items, each tax line, and the total off a receipt photo. Nothing is stored.',
                'href'        => '../free-receipt-scanner/',
                'icon'        => 'scan',
            ],
            [
                'name'        => 'Profit Analyzer',
                'description' => 'Upload a sales spreadsheet to find fees, products that lose money, and your actual margin.',
                'href'        => '../profit-analyzer/',
                'icon'        => 'chart',
            ],
        ],
    ],
];

$tool_count = 0;
foreach ($tool_groups as $group) {
    $tool_count += count($group['tools']);
}
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
        content="Free tools for small businesses from Argo Books. Generate invoices, grab invoice templates, and more. No signup required.">
    <meta name="keywords"
        content="free business tools, free invoice generator, free invoice templates, small business tools, argo books tools">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Free Tools - Argo Books">
    <meta property="og:description"
        content="Free tools for small businesses from Argo Books. Generate invoices, grab invoice templates, and more. No signup required.">
    <meta property="og:url" content="https://argorobots.com/tools/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Free Tools - Argo Books">
    <meta name="twitter:description"
        content="Free tools for small businesses from Argo Books. Generate invoices, grab invoice templates, and more. No signup required.">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/tools/">

    <!-- CollectionPage Schema -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "CollectionPage",
            "name": "Free Tools",
            "url": "https://argorobots.com/tools/",
            "description": "Free tools for small businesses from Argo Books."
        }
    </script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Free Tools - Argo Books</title>

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

<body class="tools-hub">
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>
    <main>

    <section class="tools-hero">
        <div class="container">
            <h1>Free tools</h1>
            <p><?= $tool_count ?> free tools for small businesses and self-employed people. None of them ask you to sign up.</p>
        </div>
    </section>

    <section class="tools-section">
        <div class="container">
            <?php foreach ($tool_groups as $group): ?>
                <div class="tool-group">
                    <h2><?= htmlspecialchars($group['heading']) ?></h2>
                    <ul class="tool-list">
                        <?php foreach ($group['tools'] as $tool): ?>
                            <li>
                                <a class="tool-row" href="<?= htmlspecialchars($tool['href']) ?>">
                                    <span class="tool-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><?= $tool_icons[$tool['icon']] ?></svg>
                                    </span>
                                    <span class="tool-text">
                                        <span class="tool-name"><?= htmlspecialchars($tool['name']) ?></span>
                                        <span class="tool-desc"><?= htmlspecialchars($tool['description']) ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="tools-note">
        <div class="container tools-note-inner">
            <div>
                <h2>Argo Books</h2>
                <p>Accounting software for small businesses, with a free plan. Invoices, expenses, receipts, and reports in one app.</p>
            </div>
            <a href="../downloads/" class="btn btn-primary">
                <span>Download Argo Books</span>
                <?= svg_icon('arrow-right', 18) ?>
            </a>
        </div>
    </section>

    </main>

    <div class="dark-section-wrapper">
        <footer class="footer">
            <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
        </footer>
    </div>
</body>

</html>
