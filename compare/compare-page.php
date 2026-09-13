<?php
// compare/compare-page.php
//
// Shared template for every page under /compare/. Driven entirely by the data
// files in compare/data/{slug}.php.
//
// Before this file, each comparison was a standalone ~570-line page and about
// 58% of every one of them was the same skeleton: the same meta block, hero,
// section wrappers, pros-and-cons grid, "keep comparing" strip, CTA and footer.
// Changing the layout meant fifteen identical edits, and the pages had already
// drifted apart in small ways.
//
// Routing is unchanged: /compare/{slug}/ is still a real directory served by
// DirectoryIndex, and its index.php is a stub that sets $compare_slug and
// requires this file. Keeping the directories means no new rewrite rules and no
// risk to the 301s in .htaccess that point old URLs at these pages.
//
// Heading structure (strict, for SEO):
//   <h1>   the comparison itself
//   <h2>   What's the difference / Side by side / pros & cons / why switch /
//          keep comparing / FAQ / closing CTA
//     <h3>   sub-blocks inside those sections
//
// A data file returns one array. Every key below is required except
// 'extra_styles', 'key_icon_size', 'honest_comment', 'honest_icon',
// 'honest_icon_size', 'honest_h3', 'honest_cta' and 'honest_alt', which are
// per-page overrides; copy an existing file in compare/data/ to start a new one.
// Each page also needs compare/mockups/{slug}.php for its price chart, and a
// compare/{slug}/index.php stub.

require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../config/pricing.php';
require_once __DIR__ . '/../track_referral.php';
require_once __DIR__ . '/../partials/fonts.php';
require_once __DIR__ . '/compare-lib.php';

// Data files check this constant, so a direct request for one returns 404
// instead of running it out of context and printing an error with the path.
define('ARGO_TEMPLATE_RENDER', true);

$slug = $compare_slug ?? '';
if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    exit;
}

$data_file = __DIR__ . '/data/' . $slug . '.php';
if (!is_file($data_file)) {
    http_response_code(404);
    exit;
}

$plans        = get_plan_features();
$pricing      = get_pricing_config();
$argo_monthly = (int) $pricing['premium_monthly_price'];

/** @var array $d */
$d = require $data_file;

$competitor = $d['competitor'];
$page_url   = 'https://argorobots.com/compare/' . $slug . '/';

// The visible accordion and the FAQPage JSON-LD are both built from $d['faqs'],
// so the two can no longer disagree. Every page used to carry a hand-written
// copy of the schema alongside the visible answers.
$faqs = $d['faqs'];

// "Keep comparing" links. Each page picks its own set and order; the labels
// come from the shared index so a rename lands everywhere at once.
$compare_index = argo_compare_index();
$other_comparisons = [];
foreach ($d['related'] as $related_slug) {
    if ($related_slug !== $slug && isset($compare_index[$related_slug])) {
        $other_comparisons[$related_slug] = $compare_index[$related_slug];
    }
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
        content="<?= $d['meta_description'] ?>">
    <meta name="keywords"
        content="<?= $d['meta_keywords'] ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= $d['og_title'] ?>">
    <meta property="og:description"
        content="<?= $d['og_description'] ?>">
    <meta property="og:url" content="<?= $page_url ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $d['og_title'] ?>">
    <meta name="twitter:description"
        content="<?= $d['og_description'] ?>">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Additional SEO Meta Tags -->
    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $page_url ?>">

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", $d['breadcrumb'] => "/compare/" . $slug . "/"]) ?></script>

    <!-- FAQ Schema, generated from the same array as the visible accordion -->
    <script type="application/ld+json"><?= argo_faq_schema($faqs) ?></script>

    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
    <title><?= $d['title'] ?></title>

    <script src="../../resources/scripts/main.js"></script>

    <!-- Brand typefaces, matched to the home page so this comparison reads as
         the same product. Fraunces = display, IBM Plex Sans = body. -->
    <?= argo_font_links('default', '    ') ?>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../../resources/styles/feature-tour.css">
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
<?php foreach ($d['extra_styles'] ?? [] as $sheet): ?>
    <link rel="stylesheet" href="<?= $sheet ?>">
<?php endforeach; ?>
    <link rel="stylesheet" href="../../resources/styles/faq.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">
    <link rel="stylesheet" href="../../resources/styles/typography.css">
</head>

<body class="compare-page">
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>
    <main>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content animate-fade-in">
                <h1><?= $d['hero_h1'] ?></h1>
                <p class="hero-subtitle"><?= $d['hero_subtitle'] ?></p>
                <div class="hero-ctas">
                    <a href="../../downloads/" class="btn-cta btn-cta-primary">
                        <span>Try Argo Books Free</span>
                        <?= svg_icon('arrow-right', 18) ?>
                    </a>
                    <a href="../../pricing/" class="btn-cta btn-cta-outline">
                        <span>View Pricing</span>
                    </a>
                </div>
            </div>
            <div class="hero-visual animate-fade-in">
                <div class="hero-device">
                    <img src="../../resources/images/dashboard.webp"
                         srcset="../../resources/images/dashboard-800.webp 800w, ../../resources/images/dashboard-1200.webp 1200w, ../../resources/images/dashboard-1600.webp 1600w"
                         sizes="(max-width: 900px) 90vw, 760px"
                         alt="The Argo Books dashboard" width="2400" height="1528" fetchpriority="high">
                </div>
            </div>
        </div>
    </section>

    <!-- Differences: narrative + product visual -->
    <section class="differences">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <span class="section-label">The short version</span>
                <h2><?= $d['differences_h2'] ?></h2>
                <p class="section-desc"><?= $d['differences_desc'] ?></p>
            </div>
            <div class="diff-split">
                <div class="diff-copy animate-on-scroll">
                    <h3><?= $d['why_h3'] ?></h3>
                    <ul class="why-list">
<?php foreach ($d['why_list'] as $item): ?>
                            <li>
                                <span class="why-check"><?= svg_icon('check', 15) ?></span>
                                <span><?= $item ?></span>
                            </li>
<?php endforeach; ?>
                    </ul>
                </div>
                <div class="diff-visual animate-on-scroll">
                    <div class="diff-mockup">
<?php include __DIR__ . '/mockups/' . $slug . '.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Feature showcase (shared partial, also used on the landing page) -->
    <?php include __DIR__ . '/../resources/sections/feature-tour.php'; ?>

    <!-- Feature Comparison Table -->
    <section class="comparison-table-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <h2>Side by side</h2>
            </div>
            <div class="table-wrapper animate-on-scroll">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th class="feature-col">Feature</th>
                            <th class="brand-col">Argo Free<span class="th-sub">$0 forever</span></th>
                            <th class="brand-col">Argo Books Premium<span class="th-sub"><?= $d['table_argo_sub'] ?></span></th>
                            <th class="brand-col"><?= $competitor ?><span class="th-sub"><?= $d['table_competitor_sub'] ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($d['table_rows'] as $row): ?>
                        <tr>
                            <td><?= $row[0] ?></td>
                            <td><?= argo_compare_cell($row[1]) ?></td>
                            <td><?= argo_compare_cell($row[2]) ?></td>
                            <td><?= argo_compare_cell($row[3]) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Pros & Cons -->
    <section class="pros-cons-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <span class="section-label">The honest verdict</span>
                <h2><?= $d['pros_cons_h2'] ?></h2>
            </div>
            <div class="pros-cons-grid">
                <div class="pc-card pc-argo animate-on-scroll">
                    <div class="pc-block">
                        <h3>Argo Books pros</h3>
                        <ul class="pc-list">
<?php foreach ($d['argo_pros'] as $item): ?>
                            <li><span class="pc-ico pc-pro"><?= svg_icon('check', 16) ?></span><span><?= $item ?></span></li>
<?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="pc-block">
                        <h3>Argo Books cons</h3>
                        <ul class="pc-list">
<?php foreach ($d['argo_cons'] as $item): ?>
                            <li><span class="pc-ico pc-con"><?= svg_icon('x', 16) ?></span><span><?= $item ?></span></li>
<?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="pc-card pc-competitor animate-on-scroll">
                    <div class="pc-block">
                        <h3><?= $competitor ?> cons</h3>
                        <ul class="pc-list">
<?php foreach ($d['competitor_cons'] as $item): ?>
                            <li><span class="pc-ico pc-con"><?= svg_icon('x', 16) ?></span><span><?= $item ?></span></li>
<?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="pc-block">
                        <h3><?= $competitor ?> pros</h3>
                        <ul class="pc-list">
<?php foreach ($d['competitor_pros'] as $item): ?>
                            <li><span class="pc-ico pc-pro"><?= svg_icon('check', 16) ?></span><span><?= $item ?></span></li>
<?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Differences -->
    <section class="key-differences">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <h2><?= $d['key_h2'] ?></h2>
                <p class="section-desc"><?= $d['key_desc'] ?></p>
            </div>
            <div class="diff-grid">
<?php foreach ($d['key_cards'] as $card): ?>
                <div class="diff-card animate-on-scroll">
                    <div class="diff-icon">
                        <?= svg_icon($card['icon'], $d['key_icon_size'] ?? 30, '', 1.5) ?>
                    </div>
                    <h3><?= $card['h3'] ?></h3>
                    <p><?= $card['p'] ?></p>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- <?= $d['honest_comment'] ?? 'Honest Take' ?> -->
    <section class="honest-take">
        <div class="container">
            <div class="honest-card animate-on-scroll">
                <div class="honest-icon">
                    <?= svg_icon($d['honest_icon'] ?? 'info', $d['honest_icon_size'] ?? 28) ?>
                </div>
                <h3><?= $d['honest_h3'] ?? 'An honest take' ?></h3>
<?php foreach ($d['honest'] as $para): ?>
                <p><?= $para ?></p>
<?php endforeach; ?>
<?php if ($d['honest_cta'] ?? true): ?>
                <a href="../../downloads/" class="btn-cta btn-cta-primary honest-take-cta">
                    <span>Get Started Now</span>
                    <?= svg_icon('arrow-right', 18) ?>
                </a>
<?php endif; ?>
            </div>
        </div>
    </section>
<?php if (!empty($d['honest_alt'])): ?>

    <!-- Honest Take -->
    <section class="honest-take-alt">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <span class="section-label"><?= $d['honest_alt']['label'] ?></span>
                <h2><?= $d['honest_alt']['h2'] ?></h2>
<?php foreach ($d['honest_alt']['paras'] as $para): ?>
                <p class="section-desc"><?= $para ?></p>
<?php endforeach; ?>
                <a href="../../downloads/" class="btn-cta btn-cta-primary honest-take-cta">
                    <span>Get Started Now</span>
                    <?= svg_icon('arrow-right', 18) ?>
                </a>
            </div>
        </div>
    </section>
<?php endif; ?>

    <!-- Other comparisons -->
    <section class="other-comparisons">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <h2>How does Argo Books compare to other accounting software?</h2>
            </div>
            <div class="compare-cards animate-on-scroll">
<?php foreach ($other_comparisons as $other_slug => $name): ?>
                <a class="compare-card" href="../<?= $other_slug ?>/">
                    <span><?= $name ?></span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="7" y1="17" x2="17" y2="7"></line>
                        <polyline points="7 7 17 7 17 17"></polyline>
                    </svg>
                </a>
<?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            <?= argo_faq_grid($faqs) ?>
        </div>
    </section>

    </main>

    <!-- CTA + Footer Wrapper -->
    <div class="dark-section-wrapper">
        <!-- CTA Section -->
        <section class="cta-section">
            <div class="container">
                <div class="cta-card animate-on-scroll">
                    <h2><?= $d['cta_h2'] ?></h2>
                    <p><?= $d['cta_p'] ?></p>
                    <div class="cta-buttons">
                        <a href="../../downloads/" class="btn-cta btn-cta-primary">
                            <span>Download for Free</span>
                            <?= svg_icon('arrow-right', 18) ?>
                        </a>
                        <a href="../../pricing/" class="btn-cta btn-cta-ghost">
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

    <script defer src="../../resources/scripts/reveal.js"></script>
    <script src="../../resources/scripts/feature-tour.js"></script>
</body>

</html>
