<?php
// for-pages/lp-page.php
//
// Shared template for the paid-ad landing pages at /for-{trade}/. Content lives
// in for-pages/data/{slug}.php.
//
// Each landing page used to be a standalone ~380-line file, and about 70% of
// every one of them was the same skeleton: the same head, hero, benefit grid,
// "what Argo Books isn't" block, pricing strip, cross-links, FAQ and CTA. Ten
// pages meant ten identical edits for any layout change, and they had already
// drifted (three of them carry an extra guide link, one an extra stylesheet).
//
// Routing is unchanged: /for-{trade}/ is still a real directory whose index.php
// is a stub setting $lp_slug and requiring this file. The ad URLs and the
// ?source= attribution that pays for them keep working untouched.
//
// Heading structure (strict, for SEO):
//   <h1>   the trade
//   <h2>   benefits / pricing / more trades / FAQ / closing CTA
//     <h3>   one per benefit, plus the "what it isn't" block

require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/feature-demo.php';
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../config/pricing.php';
require_once __DIR__ . '/../track_referral.php';
require_once __DIR__ . '/../statistics.php';
require_once __DIR__ . '/../partials/fonts.php';

// Data files check this constant, so a direct request for one returns 404
// instead of running it out of context and printing an error with the path.
define('ARGO_TEMPLATE_RENDER', true);

$slug = $lp_slug ?? '';
if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    exit;
}

$data_file = __DIR__ . '/data/' . $slug . '.php';
if (!is_file($data_file)) {
    http_response_code(404);
    exit;
}

$plans         = get_plan_features();
$pricing       = get_pricing_config();
$argo_monthly  = (int) $pricing['premium_monthly_price'];
$free_invoices = (int) $pricing['free_invoice_monthly_limit'];

/** @var array $d */
$d = require $data_file;

if (PHP_SAPI !== 'cli') {
    track_page_view($d['track_event']);
}

// Every CTA on the page carries ?source= so the ad click is attributed all the
// way through to the download.
$cta_source   = $d['cta_source'];
$download_url = '../downloads/?source=' . $cta_source;
$pricing_url  = '../pricing/?source=' . $cta_source;

$page_url = 'https://argorobots.com/' . $slug . '/';

// The visible accordion and the FAQPage JSON-LD come from one array, so the two
// cannot drift apart the way the hand-written pairs used to.
$faqs = $d['faqs'];
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
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $d['twitter_title'] ?? $d['og_title'] ?>">
    <meta name="twitter:description"
        content="<?= $d['twitter_description'] ?? $d['og_description'] ?>">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Additional SEO Meta Tags -->
    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $page_url ?>">

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", $d['breadcrumb'] => "/" . $slug . "/"]) ?></script>

    <!-- FAQ Schema, generated from the same array as the visible accordion -->
    <script type="application/ld+json"><?= argo_faq_schema($faqs) ?></script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title><?= $d['title'] ?></title>

    <script src="../resources/scripts/main.js"></script>
    <!-- Drives the mockup in the hero. -->
    <script src="../resources/scripts/feature-tour.js" defer></script>

    <link rel="stylesheet" href="../compare/style.css">
    <link rel="stylesheet" href="../resources/styles/for-pages.css">
    <link rel="stylesheet" href="../resources/styles/feature-tour.css">
    <link rel="stylesheet" href="../resources/styles/pricing-cards.css">
    <link rel="stylesheet" href="../features/feature-page.css">
    <link rel="stylesheet" href="../resources/styles/smartscreen-guide.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
    <link rel="stylesheet" href="../resources/styles/faq.css">
<?php foreach ($d['extra_styles'] ?? [] as $sheet): ?>
    <link rel="stylesheet" href="<?= $sheet ?>">
<?php endforeach; ?>
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

    <!-- Hero. Split, with the live mockup beside the copy rather than centred
         text on a gradient. Same demo markup and loop the landing page uses. -->
    <section class="fp-hero hero">
        <div class="fp-wrap">
            <div class="fp-hero-grid">
                <div>
                    <h1><?= $d['h1'] ?></h1>
                    <p class="fp-hero-sub"><?= $d['hero_sub'] ?></p>
                    <div class="fp-hero-act">
                        <a href="<?= htmlspecialchars($download_url) ?>" class="fp-btn fp-btn-primary js-direct-download">
                            <span>Download free</span>
                            <?= svg_icon('arrow-right', 17) ?>
                        </a>
                        <a href="#<?= $d['features_id'] ?? 'features' ?>" class="fp-textlink"><?= $d['hero_link_label'] ?? "See What's Included" ?></a>
                    </div>
                    <p class="fp-hero-facts"><?= $d['hero_facts'] ?></p>
                </div>

                <div class="fp-hero-demo" data-feature-demo="<?= $d['demo'] ?>">
                    <?= argo_feature_demo($d['demo']) ?>
                </div>
            </div>
        </div>
    </section>

    <!-- SmartScreen walkthrough, revealed by lp-direct-download.php after a
         Windows direct-download click. -->
    <div class="container">
        <?php include __DIR__ . '/../resources/smartscreen-guide/guide.php'; ?>
    </div>

    <section id="<?= $d['features_id'] ?? 'features' ?>" class="feature-blocks">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <span class="section-label"><?= $d['features_label'] ?></span>
                <h2><?= $d['features_h2'] ?></h2>
                <p class="section-desc"><?= $d['features_desc'] ?></p>
            </div>
<?php if (!empty($d['features_intro_2'])): ?>
            <div class="section-header animate-on-scroll">
                <span class="section-label"><?= $d['features_intro_2']['label'] ?></span>
                <h2><?= $d['features_intro_2']['h2'] ?></h2>
                <p class="section-desc"><?= $d['features_intro_2']['desc'] ?></p>
            </div>

<?php endif; ?>
            <div class="fp-benefits">
<?php foreach ($d['benefits'] as $i => $b): ?>
<?= $i ? "\n" : '' ?>                <div class="fp-benefit animate-on-scroll">
                    <div class="fp-benefit-ic">
                        <?= svg_icon($b['icon'], 20) ?>
                    </div>
                    <h3><?= $b['h3'] ?></h3>
                    <p><?= $b['p'] ?></p>
                </div>
<?php endforeach; ?>
            </div>
<?php if (!empty($d['features_note'])): ?>

            <p class="section-desc animate-on-scroll" style="text-align: center; margin-top: 28px;">
                <?= $d['features_note'] ?>

            </p>
<?php endif; ?>
        </div>
    </section>

    <section class="honest-take">
        <div class="container">
            <div class="honest-card animate-on-scroll">
                <div class="honest-icon">
                    <?= svg_icon('info', 28) ?>
                </div>
                <h3><?= $d['honest_h3'] ?></h3>
<?php foreach ($d['honest'] as $para): ?>
                <p><?= $para ?></p>
<?php endforeach; ?>
                <a href="<?= htmlspecialchars($download_url) ?>" class="btn-cta btn-cta-primary js-direct-download honest-take-cta">
                    <span>Download Free</span>
                    <?= svg_icon('arrow-right', 18) ?>
                </a>
            </div>
        </div>
    </section>

    <section class="pricing-comparison">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <span class="section-label">Pricing</span>
                <h2><?= $d['pricing_h2'] ?></h2>
                <p class="pricing-strip-intro"><?= $d['pricing_intro'] ?></p>
            </div>
            <?php
            // The real cards, the same ones the landing and pricing pages use.
            include __DIR__ . '/../partials/pricing-cards.php';
            ?>
        </div>
    </section>

    <!-- The for- pages did not link to each other, which wastes the internal
         linking they exist to earn. -->
    <section class="fp-section-tight">
        <div class="fp-wrap">
            <div class="fp-head-c animate-on-scroll">
                <div class="fp-eyebrow fp-eyebrow-c"><?= $d['related_eyebrow'] ?? 'More trades' ?></div>
                <h2 class="fp-h2">Argo Books for your line of work</h2>
            </div>
            <div class="fp-related animate-on-scroll">
<?php foreach ($d['related'] as $r): ?>
                <a href="<?= $r['href'] ?>" class="fp-rel-card">
                    <div class="fp-rel-ic"><?= svg_icon($r['icon'], 20) ?></div>
                    <h3><?= $r['h3'] ?></h3>
                    <p><?= $r['p'] ?></p>
                </a>
<?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="faq">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            <?= argo_faq_grid($faqs) ?>
        </div>
    </section>
<?php if (!empty($d['guide_link'])): ?>

    <section class="container" style="max-width:720px;text-align:center;padding-bottom:48px;">
        <p><?= $d['guide_link'] ?></p>
    </section>
<?php endif; ?>

    </main>

    <div class="dark-section-wrapper">
        <section class="cta-section">
            <div class="container">
                <div class="cta-card animate-on-scroll">
                    <h2><?= $d['cta_h2'] ?></h2>
                    <p><?= $d['cta_p'] ?></p>
                    <div class="cta-buttons">
                        <a href="<?= htmlspecialchars($download_url) ?>" class="btn-cta btn-cta-primary js-direct-download">
                            <span>Download Free</span>
                            <?= svg_icon('arrow-right', 18) ?>
                        </a>
                        <a href="<?= htmlspecialchars($pricing_url) ?>" class="btn-cta btn-cta-ghost">
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
<?php include __DIR__ . '/../resources/smartscreen-guide/lp-direct-download.php'; ?>
</body>

</html>
