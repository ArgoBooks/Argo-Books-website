<?php
// sitemap_urls.php
//
// Single source of truth for the site's public URL list. Both
// sitemap.xml.php (renders the XML served at /sitemap.xml) and
// cron/indexnow_submit.php (pings IndexNow with recently-changed pages) build
// their set from sitemap_build_urls(), so a new page shows up in both places
// with no extra edit.
//
// Each returned entry is:
//   [
//     'loc'        => absolute URL,
//     'file'       => source file path (string) or null,
//     'priority'   => string,
//     'changefreq' => string|null,
//     'lastmod'    => 'Y-m-d' string|null,   // for the sitemap
//     'mtime'      => int epoch|null,         // for IndexNow change detection
//   ]

require_once __DIR__ . '/env_helper.php';

/**
 * Queue one entry. <lastmod>/mtime are taken from $file's modification time
 * when the file exists; entries whose source file is missing are skipped so we
 * never advertise a dead URL.
 */
function sitemap_add_url(array &$urls, string $loc, ?string $file, string $priority, ?string $changefreq = null): void
{
    if ($file !== null && !file_exists($file)) {
        return;
    }
    $mtime = ($file !== null) ? filemtime($file) : null;
    $urls[] = [
        'loc'        => $loc,
        'file'       => $file,
        'priority'   => $priority,
        'changefreq' => $changefreq,
        'lastmod'    => $mtime !== null ? date('Y-m-d', $mtime) : null,
        'mtime'      => $mtime !== null ? (int) $mtime : null,
    ];
}

/**
 * Build the full list of public site URLs. Core pages are curated by hand;
 * feature / compare / industry / invoice / article clusters are discovered from
 * the filesystem so new pages appear automatically.
 */
function sitemap_build_urls(): array
{
    $root = __DIR__;
    $urls = [];

    // --- Core marketing pages (curated: priority + change frequency by hand) ---
    $corePages = [
        ['/',                'index.php',                '1.0', 'weekly'],
        ['/downloads/',      'downloads/index.php',      '1.0', 'weekly'],
        ['/features/',       'features/index.php',       '0.9', 'monthly'],
        ['/pricing/',        'pricing/index.php',        '0.9', 'monthly'],
        // Its own landing page rather than a stop on the feature tour, because payroll search and video land here and it targets a different buyer.
        ['/payroll/',        'payroll/index.php',        '0.9', 'monthly'],
        ['/documentation/',  'documentation/index.php',  '0.9', 'weekly'],
        ['/integrations/',   'integrations/index.php',   '0.9', 'monthly'],
        ['/about-us/',       'about-us/index.php',       '0.9', 'monthly'],
        ['/who-its-for/',    'who-its-for/index.php',    '0.8', 'monthly'],
        ['/contact-us/',     'contact-us/index.php',     '0.8', 'monthly'],
        ['/whats-new/',      'whats-new/index.php',      '0.7', 'weekly'],
        ['/community/',      'community/index.php',      '0.6', 'daily'],
        ['/older-versions/', 'older-versions/index.php', '0.5', 'monthly'],
        ['/review/',         'review/index.php',         '0.5', 'monthly'],
    ];
    foreach ($corePages as [$path, $file, $priority, $changefreq]) {
        sitemap_add_url($urls, site_url($path), $root . '/' . $file, $priority, $changefreq);
    }

    // --- Auto-discovered page clusters --- Each child directory with an index.php becomes one URL,
    // so adding a new feature / comparison page needs no sitemap edit.
    $clusters = [
        ['glob' => 'features/*/index.php', 'prefix' => '/features/',  'priority' => '0.8'],
        ['glob' => 'compare/*/index.php',  'prefix' => '/compare/',   'priority' => '0.8'],
        ['glob' => 'integrations/*/index.php', 'prefix' => '/integrations/', 'priority' => '0.8'],
    ];
    foreach ($clusters as $cluster) {
        foreach (glob($root . '/' . $cluster['glob']) as $file) {
            $slug = basename(dirname($file));
            sitemap_add_url($urls, site_url($cluster['prefix'] . $slug . '/'), $file, $cluster['priority'], 'monthly');
        }
    }

    // Industry pages live at the root as /for-<industry>/.
    foreach (glob($root . '/for-*/index.php') as $file) {
        $slug = basename(dirname($file));
        sitemap_add_url($urls, site_url('/' . $slug . '/'), $file, '0.8', 'monthly');
    }

    // --- Documentation sub-pages --- Served directly as .php under their category folder.
    foreach (glob($root . '/documentation/pages/*/*.php') as $file) {
        $category = basename(dirname($file));
        $slug     = basename($file, '.php');
        sitemap_add_url($urls, site_url('/documentation/pages/' . $category . '/' . $slug . '.php'), $file, '0.6', 'monthly');
    }

    // --- Legal pages (served as .php, change rarely) ---
    foreach (['privacy', 'terms', 'eula', 'refund'] as $doc) {
        sitemap_add_url($urls, site_url('/legal/' . $doc . '.php'), $root . '/legal/' . $doc . '.php', '0.4', 'yearly');
    }

    // --- Free invoice generator tool + niche landing pages ---
    sitemap_add_url($urls, site_url('/invoice-generator/'), $root . '/invoice-generator/index.php', '0.9', 'monthly');
    foreach (glob($root . '/niches/data/*.php') as $file) {
        $slug = basename($file, '.php');
        if ($slug === '_template') continue;
        $data = require $file;
        $urlSlug = $data['slug'] ?? $slug;
        // Generic seed lives at /free-invoice-generator/ (no slug in the URL).
        $loc = $urlSlug === 'generic'
            ? site_url('/free-invoice-generator/')
            : site_url('/free-invoice-generator/' . $urlSlug . '/');
        sitemap_add_url($urls, $loc, $file, $urlSlug === 'generic' ? '0.9' : '0.8', 'monthly');
    }

    // --- Free tools hub and the calculators it links to ---
    // Listed rather than globbed: they sit at the site root beside non-tool directories.
    $toolPages = [
        ['/tools/',                          'tools/index.php',                          '0.9', 'monthly'],
        ['/etsy-fee-calculator/',            'etsy-fee-calculator/index.php',            '0.9', 'monthly'],
        ['/self-employed-tax-calculator/',   'self-employed-tax-calculator/index.php',   '0.9', 'monthly'],
        ['/craft-pricing-calculator/',       'craft-pricing-calculator/index.php',       '0.9', 'monthly'],
        ['/estimate-generator/',             'estimate-generator/index.php',             '0.9', 'monthly'],
        ['/purchase-order-generator/',       'purchase-order-generator/index.php',       '0.9', 'monthly'],
        ['/free-receipt-scanner/',           'free-receipt-scanner/index.php',           '0.9', 'monthly'],
        ['/candle-pricing-calculator/',     'candle-pricing-calculator/index.php',     '0.9', 'monthly'],
        ['/soap-pricing-calculator/',       'soap-pricing-calculator/index.php',       '0.9', 'monthly'],
        ['/tumbler-pricing-calculator/',    'tumbler-pricing-calculator/index.php',    '0.9', 'monthly'],
        ['/cake-pricing-calculator/',       'cake-pricing-calculator/index.php',       '0.9', 'monthly'],
        ['/craft-fair-calculator/',         'craft-fair-calculator/index.php',         '0.9', 'monthly'],
        ['/hourly-rate-calculator/',        'hourly-rate-calculator/index.php',        '0.9', 'monthly'],
        ['/mileage-deduction-calculator/',  'mileage-deduction-calculator/index.php',  '0.9', 'monthly'],
        ['/late-fee-calculator/',           'late-fee-calculator/index.php',           '0.9', 'monthly'],
        ['/markup-margin-calculator/',      'markup-margin-calculator/index.php',      '0.9', 'monthly'],
        ['/break-even-calculator/',         'break-even-calculator/index.php',         '0.9', 'monthly'],
    ];
    foreach ($toolPages as [$path, $file, $priority, $changefreq]) {
        sitemap_add_url($urls, site_url($path), $root . '/' . $file, $priority, $changefreq);
    }

    // --- Free profit analyzer tool ---
    sitemap_add_url($urls, site_url('/profit-analyzer/'), $root . '/profit-analyzer/index.php', '0.9', 'monthly');
    sitemap_add_url($urls, site_url('/profit-analyzer/for-accountants/'), $root . '/profit-analyzer/for-accountants/index.php', '0.7', 'monthly');
    sitemap_add_url($urls, site_url('/profit-analyzer/legal/privacy.php'), $root . '/profit-analyzer/legal/privacy.php', '0.3', 'yearly');
    sitemap_add_url($urls, site_url('/profit-analyzer/legal/terms.php'), $root . '/profit-analyzer/legal/terms.php', '0.3', 'yearly');

    // --- Invoice template library ---
    sitemap_add_url($urls, site_url('/invoice-template/'), $root . '/invoice-template/index.php', '0.8', 'monthly');
    foreach (glob($root . '/invoice-template/data/*.php') as $file) {
        $slug = basename($file, '.php');
        if ($slug === '_template') continue;
        $data = require $file;
        $urlSlug = $data['slug'] ?? $slug;
        $kind = $data['kind'] ?? 'style-format';
        // Format-generic pages rank slightly above style-format pages so Search
        // Console treats them as the cluster entry point.
        sitemap_add_url($urls, site_url('/invoice-template/' . $urlSlug . '/'), $file, $kind === 'format-generic' ? '0.8' : '0.7', 'monthly');
    }

    // --- Articles: editorial hub + each guide ---
    sitemap_add_url($urls, site_url('/guides/'), $root . '/guides/index.php', '0.8', 'weekly');
    foreach (glob($root . '/guides/data/*.php') as $file) {
        $slug = basename($file, '.php');
        if ($slug === '_template') continue;
        $data = require $file;
        $urlSlug = $data['slug'] ?? $slug;
        sitemap_add_url($urls, site_url('/' . $urlSlug . '/'), $file, '0.7', 'monthly');
    }

    return $urls;
}
