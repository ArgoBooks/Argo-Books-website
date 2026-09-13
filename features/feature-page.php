<?php
// features/feature-page.php
//
// Shared template for every page under /features/. Content lives in
// features/data/{slug}.php.
//
// Each feature page used to be a standalone ~390-line file, and roughly 69% of
// every one of them was the same skeleton: the same meta block, the same hero
// shell, how-it-works steps, split product blocks, mid-page CTA, benefit grid,
// privacy block, who-it's-for grid, FAQ, related cards and outro. A layout
// change meant eleven identical edits.
//
// Routing is unchanged: /features/{slug}/ is a real directory whose index.php
// is a stub setting $feature_slug and requiring this file.
//
// Heading structure (strict, for SEO):
//   <h1>   the feature
//   <h2>   one per section
//     <h3>   steps, benefits, who-it's-for and related cards
//
// A data file returns one array; copy an existing file in features/data/ to
// start a new one. 'splits_before_cta' and 'splits_after_benefits' are lists of
// product blocks, so a page can carry an extra one (payroll does) without the
// template needing to know about it.

require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/feature-demo.php';
require_once __DIR__ . '/../track_referral.php';
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../config/pricing.php';
require_once __DIR__ . '/../partials/fonts.php';

// Data files check this constant, so a direct request for one returns 404
// instead of running it out of context and printing an error with the path.
define('ARGO_TEMPLATE_RENDER', true);

$slug = $feature_slug ?? '';
if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    exit;
}

$data_file = __DIR__ . '/data/' . $slug . '.php';
if (!is_file($data_file)) {
    http_response_code(404);
    exit;
}

$pricing      = get_pricing_config();
$argo_monthly = (int) $pricing['premium_monthly_price'];

/** @var array $d */
$d = require $data_file;

// One array drives both the visible accordion and the FAQPage schema.
$faqs     = $d['faqs'];
$page_url = 'https://argorobots.com/features/' . $slug . '/';

/** One product block: text on one side, an illustration on the other. */
function argo_feature_split(array $b): void
{
    $classes = 'fp-split' . (!empty($b['flip']) ? ' fp-split-flip' : '') . ' fp-reveal';
    ?>

    <!-- =============================================
         <?= $b['banner'] ?>

         ============================================= -->
    <section class="fp-section"<?= !empty($b['bg']) ? ' style="background: var(--gray-50)"' : '' ?>>
        <div class="fp-wrap">
            <div class="<?= $classes ?>">
                <div class="fp-split-text">
                    <div class="fp-eyebrow"><?= $b['eyebrow'] ?></div>
                    <h2 class="fp-h2"><?= $b['h2'] ?></h2>
                    <p class="fp-lede"><?= $b['lede'] ?></p>
                    <ul class="fp-list">
<?php foreach ($b['list'] as $item): ?>
                        <li><?= svg_icon('check', 17) ?><span><?= $item ?></span></li>
<?php endforeach; ?>
                    </ul>
                </div>
                <div class="fp-split-media">
                    <img src="<?= $b['img'] ?>"
                         alt="<?= $b['img_alt'] ?>"
                         loading="lazy" width="<?= $b['img_w'] ?>" height="<?= $b['img_h'] ?>">
                </div>
            </div>
        </div>
    </section>
<?php
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
    <meta name="description" content="<?= $d['meta_description'] ?>">
    <meta name="keywords" content="<?= $d['meta_keywords'] ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= $d['og_title'] ?>">
    <meta property="og:description" content="<?= $d['og_description'] ?>">
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
    <meta name="twitter:description" content="<?= $d['twitter_description'] ?? $d['og_description'] ?>">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Additional SEO Meta Tags -->
    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $page_url ?>">

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", "Features" => "/features/", $d['breadcrumb'] => "/features/" . $slug . "/"]) ?></script>

    <!-- FAQ Schema, built from the same array as the accordion further down -->
    <script type="application/ld+json"><?= argo_faq_schema($faqs) ?></script>

    <!-- SoftwareApplication Schema -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "SoftwareApplication",
            "name": "Argo Books",
            "applicationCategory": "BusinessApplication",
            "operatingSystem": "Windows, macOS, Linux",
            "offers": {
                "@type": "Offer",
                "price": "<?= $d['offer_price'] ?? '0' ?>",
                "priceCurrency": "CAD",
                "description": "<?= $d['offer_description'] ?? 'Free plan available. Premium for $' . $argo_monthly . '/month.' ?>"
            },
            "description": "<?= $d['schema_description'] ?? $d['meta_description'] ?>",
            "featureList": "<?= $d['feature_list'] ?>"
        }
    </script>

    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
    <title><?= $d['title'] ?></title>

    <script src="../../resources/scripts/main.js"></script>
    <!-- Mockup animations, shared with the landing and comparison pages. -->
    <script src="../../resources/scripts/feature-tour.js" defer></script>

    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/styles/faq.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">
    <?= argo_font_links('editorial', '    ') ?>
    <link rel="stylesheet" href="../../resources/styles/typography.css">
    <link rel="stylesheet" href="../../resources/styles/feature-tour.css">
    <link rel="stylesheet" href="../feature-page.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>
    <main>

    <!-- =============================================
         <?= $d['hero_banner'] ?? 'HERO' ?>

         ============================================= -->
    <section class="fp-hero hero">
        <div class="fp-wrap">
            <div class="fp-hero-grid">
                <div>
                    <h1><?= $d['h1'] ?></h1>
                    <p class="fp-hero-sub"><?= $d['hero_sub'] ?></p>
                    <div class="fp-hero-act">
                        <a href="../../downloads/" class="fp-btn fp-btn-primary">
                            <span>Download free</span>
                            <?= svg_icon('arrow-right', 17) ?>
                        </a>
                        <a href="../../pricing/" class="fp-textlink">See pricing</a>
                    </div>
                    <p class="fp-hero-facts"><?= $d['hero_facts'] ?></p>
                </div>

                <div class="fp-hero-demo" data-feature-demo="<?= $d['demo'] ?>">
                    <?= argo_feature_demo($d['demo']) ?>
                </div>
        </div>
        </div>
    </section>

    <!-- =============================================
         HOW IT WORKS
         ============================================= -->
    <section class="fp-section">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">How it works</div>
                <h2 class="fp-h2"><?= $d['steps_h2'] ?></h2>
                <p class="fp-lede"><?= $d['steps_lede'] ?></p>
            </div>
            <div class="fp-steps fp-reveal">
<?php foreach ($d['steps'] as $i => $step): ?>
                <div class="fp-step">
                    <div class="fp-step-n">Step <?= $i + 1 ?></div>
                    <h3><?= $step['h3'] ?></h3>
                    <p><?= $step['p'] ?></p>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </section>
<?php foreach ($d['splits_before_cta'] as $block) {
    argo_feature_split($block);
} ?>

    <!-- =============================================
         The page's one mid-page CTA.
         ============================================= -->
    <section class="fp-midcta">
        <div class="fp-wrap fp-midcta-in">
            <div>
                <h2><?= $d['midcta_h2'] ?></h2>
                <p><?= $d['midcta_p'] ?></p>
            </div>
            <a href="<?= $d['midcta_href'] ?? '../../downloads/' ?>" class="fp-btn fp-btn-primary">
                <span><?= $d['midcta_label'] ?? 'Download free' ?></span>
                <?= svg_icon('arrow-right', 17) ?>
            </a>
        </div>
    </section>

    <!-- =============================================
         BENEFITS
         ============================================= -->
    <section class="fp-section">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">Why it matters</div>
                <h2 class="fp-h2"><?= $d['benefits_h2'] ?></h2>
            </div>
            <div class="fp-benefits fp-reveal">
<?php foreach ($d['benefits'] as $b): ?>
                <div class="fp-benefit">
                    <div class="fp-benefit-ic"><?= svg_icon($b['icon'], 20, '', $b['stroke'] ?? null) ?></div>
                    <h3><?= $b['h3'] ?></h3>
                    <p><?= $b['p'] ?></p>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </section>
<?php foreach ($d['splits_after_benefits'] as $block) {
    argo_feature_split($block);
} ?>

    <!-- =============================================
         WHO IT'S FOR
         ============================================= -->
    <section class="fp-section-tight">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">Who it's for</div>
                <h2 class="fp-h2"><?= $d['who_h2'] ?></h2>
            </div>
            <div class="fp-who fp-reveal">
<?php foreach ($d['who'] as $w): ?>
                <div class="fp-who-item">
                    <h3><?= svg_icon($w['icon'], 19) ?> <?= $w['h3'] ?></h3>
                    <p><?= $w['p'] ?></p>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- =============================================
         FAQ. Accordion and schema both come from $faqs.
         ============================================= -->
    <section class="fp-faq">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">Questions</div>
                <h2 class="fp-h2">Before you download</h2>
            </div>
            <?= argo_faq_grid($faqs) ?>
        </div>
    </section>

    <!-- =============================================
         RELATED
         ============================================= -->
    <section class="fp-section">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c"><?= $d['related_eyebrow'] ?></div>
                <h2 class="fp-h2"><?= $d['related_h2'] ?></h2>
            </div>
            <div class="fp-related fp-reveal">
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

    </main>

    <!-- Final CTA and footer share one dark block. -->
    <div class="dark-section-wrapper fp-outro">
        <section class="fp-outro-cta cta-section">
            <div class="fp-wrap">
                <h2><?= $d['outro_h2'] ?></h2>
                <p><?= $d['outro_p'] ?></p>
                <div class="fp-btns">
                    <a href="../../downloads/" class="fp-btn fp-btn-primary">
                        <span>Download free</span>
                        <?= svg_icon('arrow-right', 17) ?>
                    </a>
                    <a href="../../pricing/" class="fp-btn fp-btn-onnavy">
                        <span>See pricing</span>
                    </a>
                </div>
            </div>
        </section>

        <footer class="footer">
            <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
        </footer>
    </div>

    <script defer src="../../resources/scripts/reveal.js"></script>
</body>

</html>
