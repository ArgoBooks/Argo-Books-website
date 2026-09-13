<?php
// compare/data/zoho-books-alternatives.php
//
// Content for /compare/zoho-books-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/zoho-books-alternatives.php.
$argo_yearly       = (int) $pricing['premium_yearly_price'];
$zoho_free         = competitor_price('zoho-books', 'free');         // 0
$zoho_standard     = competitor_price('zoho-books', 'standard');     // 15
$zoho_professional = competitor_price('zoho-books', 'professional'); // 30
$zoho_premium      = competitor_price('zoho-books', 'premium');      // 40
$zoho_elite        = competitor_price('zoho-books', 'elite');        // 165
$zoho_ultimate     = competitor_price('zoho-books', 'ultimate');     // 290

return [
    'competitor' => 'Zoho Books',

    'breadcrumb' => 'Zoho Books alternatives',
    'title' => 'Zoho Books Alternatives: Standalone and Offline | Argo Books',
    'meta_description' => 'Zoho Books alternatives that work on their own, without being pulled into a wider cloud suite. Compare offline-capable desktop accounting for small business.',
    'meta_keywords' => 'Zoho Books alternatives, Zoho Books alternative, offline accounting software, desktop accounting software, standalone accounting software',
    'og_title' => 'Zoho Books Alternatives: Standalone, Offline, Yours',
    'og_description' => 'Zoho Books pulls you into a wider suite and needs a connection. Here are the alternatives that stay standalone and work offline.',

    'hero_h1' => 'Zoho Books alternatives',
    'hero_subtitle' => 'A simpler, offline way to manage your small business finances. All the essentials in one native desktop app, with a free plan that has no revenue cap, no 40-app suite to navigate.',

    'differences_h2' => 'What\'s the difference between Argo Books and Zoho Books?',
    'differences_desc' => 'Both cover the small business basics, both are free to start, and their paid plans begin at about the same price. The difference is what kind of tool each one is. Zoho Books is a powerful, feature-dense cloud app and one piece of a 40-plus app suite; Argo Books is a simple, standalone desktop app that works offline and is built for the owner doing their own books.',
    'why_h3' => 'What changes when you move from Zoho Books to Argo Books',
    'why_list' => [
        '<strong>A free plan with no revenue cap.</strong> All the core features forever, no trial and no credit card. Zoho Books has a free plan too, but it\'s capped by your annual revenue.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your books open instantly and keep working with no internet, and your data stays on your machine. Zoho Books is cloud-only.',
        '<strong>Genuinely simple.</strong> Invoicing, expenses, receipts, inventory, and forecasting in one clean app with no accounting jargon. Zoho Books is powerful, but that power comes with density and a learning curve.',
        '<strong>Standalone, not a suite.</strong> Argo is one focused tool, not a slice of a 40-app ecosystem full of cross-sells and add-ons.',
        '<strong>One predictable price.</strong> Everything in Premium for $' . $argo_monthly . ' CAD/month, with AI receipt scanning included in one flat plan.',
    ],

    // Feature, Argo Free, Argo Premium, Zoho Books.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Standard: $' . $zoho_standard . ' CAD/month',
    'table_rows' => [
        ['Expense &amp; revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Invoicing &amp; payments', 'yes', 'yes', 'yes'],
        ['AI receipt scanning', 'yes', 'yes', 'yes'],
        ['Bank reconciliation', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'yes'],
        ['Multi-currency', 'no', 'no', 'yes'],
        ['Projects &amp; time tracking', 'no', 'no', 'yes'],
        ['Hundreds of third-party integrations', 'no', 'no', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs Zoho Books: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free plan with no revenue cap</strong>, every core feature, no trial and no credit card',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux',
        '<strong>Genuinely simple</strong>, built for business owners rather than accountants',
        '<strong>Standalone</strong>, one focused tool rather than a slice of a 40-app suite',
        '<strong>Canadian (CAD)</strong> with AI receipt scanning included in one flat $' . $argo_monthly . '/mo plan',
    ],
    'argo_cons' => [
        'No multi-currency, so Zoho Books fits better if you bill in several currencies',
        'No project or time tracking for billable-hours work',
        'A small integration library, not Zoho\'s huge marketplace',
    ],
    'competitor_cons' => [
        '<strong>Cloud-only</strong>, so no offline access and your books live on Zoho\'s servers',
        '<strong>Free plan is capped</strong> by your annual revenue',
        '<strong>Part of a sprawling ecosystem</strong>, with the upsells and density a 40-app suite brings',
    ],
    'competitor_pros' => [
        'A genuinely capable free plan and a low starting price',
        'Deep features at higher tiers: multi-currency, projects, forecasting, heavy customization',
        'A huge marketplace of integrations, and it scales as you grow',
    ],

    'key_h2' => 'A free plan with no revenue cap, and books that work offline',
    'key_desc' => 'Both tools work for small businesses, but they focus on different things. Zoho Books goes wide and deep with features and integrations. Argo Books focuses on simplicity, offline access, and staying out of your way.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'Free without the cap', 'p' => 'Zoho\'s free plan is limited by your annual revenue. Argo\'s free plan has core features with no revenue cap, and Premium is one flat price with AI receipt scanning included.'],
        ['icon' => 'bolt', 'h3' => 'Works offline', 'p' => 'Zoho Books is cloud-only: no internet, no access, and your books live on their servers. Argo Books is a desktop app that works offline, so you\'re never locked out of your own data.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'Zoho Books is a strong, affordable, feature-rich product. It goes broader and deeper than Argo, especially at its higher tiers, with multi-currency, projects and time tracking, cashflow forecasting, heavy customization, and a huge integration marketplace. If you want a powerful cloud suite that scales, Zoho Books is a great tool.',
        'But if you\'d rather have a simple, standalone desktop app that works offline, keeps your books on your own machine, and gives you a free plan with no revenue cap, Argo Books is built for you.',
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-quickbooks',
        'argo-books-vs-wave',
        'argo-books-vs-freshbooks',
        'argo-books-vs-xero',
        'zipbooks-alternatives',
        'odoo-accounting-alternatives',
        'honeybook-alternatives',
        'sage-50-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books really free?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card, no trial period, and no revenue cap. The Free plan includes all core features, ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month, and AI receipt scanning.</p>
                            <p>Zoho Books also has a free plan, but it\'s limited to micro-businesses: it\'s capped by your annual revenue. Argo\'s free plan has no revenue cap.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption, giving you full control and privacy.</p>
                            <p>Zoho Books is cloud-only, so it needs an internet connection and your books live on Zoho\'s servers.</p>'],
        ['q_html' => 'Is Argo Books as powerful as Zoho Books?', 'a_html' => '<p>Honestly, Zoho Books is broader and deeper, especially at its higher tiers, with multi-currency, projects and time tracking, cashflow forecasting, heavy customization, and a huge integration marketplace.</p>
                            <p>Argo Books is deliberately simpler. It\'s an offline desktop app, standalone rather than part of a 40-app suite, and it covers what most small businesses actually need: invoicing, expenses, AI receipt scanning, bank matching, inventory, and reports.</p>'],
        ['q_html' => 'How does Argo Books pricing compare to Zoho Books?', 'a_html' => '<p>They\'re priced about the same, and both are free to start. Argo Books Premium is <strong>$' . $argo_monthly . ' CAD/month</strong> (or $' . $argo_yearly . '/year), the same entry price as Zoho Books Standard at $' . $zoho_standard . ' CAD/month.</p>
                            <p>Zoho\'s plans then rise to Professional at $' . $zoho_professional . ' and Premium at $' . $zoho_premium . ', and up to $' . $zoho_elite . ' and $' . $zoho_ultimate . ' CAD/month for its Elite and Ultimate tiers. The real difference isn\'t the price, it\'s what kind of tool each one is.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong>. Because it\'s a desktop app, it\'s fast and responsive, with no browser tabs and no loading spinners.</p>
                            <p>Zoho Books is web-based and also has mobile apps for iOS and Android.</p>'],
    ],

    'cta_h2' => 'Ready to try a simpler alternative?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
