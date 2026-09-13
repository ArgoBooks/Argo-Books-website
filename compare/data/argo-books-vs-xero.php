<?php
// compare/data/argo-books-vs-xero.php
//
// Content for /compare/argo-books-vs-xero/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/argo-books-vs-xero.php.
$xero_starter  = competitor_price('xero', 'starter');
$xero_standard = competitor_price('xero', 'standard');
$xero_premium  = competitor_price('xero', 'premium');

return [
    'competitor' => 'Xero',
    'key_icon_size' => 28,

    'breadcrumb' => 'Argo Books vs Xero',
    'title' => 'Argo Books vs Xero: Simpler & More Affordable | Argo Books',
    'meta_description' => 'Argo Books vs Xero: Compare features, pricing, and ease of use. See why small businesses choose Argo Books as a simpler, more affordable Xero alternative with offline access.',
    'meta_keywords' => 'Argo Books vs Xero, Xero alternative, Xero alternative Canada, cheap Xero alternative, simple bookkeeping software, small business accounting, affordable accounting software, offline accounting',
    'og_title' => 'Argo Books vs Xero: Simpler, Offline-Capable & More Affordable',
    'og_description' => 'Compare Argo Books and Xero side by side. See why small businesses are choosing Argo Books for simpler, more affordable finance management with offline access.',

    'hero_h1' => 'Argo Books vs Xero',
    'hero_subtitle' => 'A simpler, more affordable way to manage your small business finances. All the essentials, none of the accounting jargon or the cloud dependency.',

    'differences_h2' => 'What\'s the difference between Argo Books and Xero?',
    'differences_desc' => 'Both handle the accounting basics. The difference is who they\'re built for. Xero is built for accountants and bookkeepers, cloud-only, and caps invoices on its cheapest plan; Argo Books is built for the business owner doing their own books, works offline, and stays one flat price.',
    'why_h3' => 'What changes when you move from Xero to Argo Books',
    'why_list' => [
        '<strong>Everything in one clean app.</strong> Invoicing, expenses, receipts, inventory, and forecasting together, with no accounting jargon and no double-entry to learn.',
        '<strong>A genuinely free plan.</strong> All the core features forever, no trial and no credit card. Xero has no free tier at all.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your books open instantly and keep working with no internet, and your data stays on your machine.',
        '<strong>AI that\'s included, not upsold.</strong> Receipt scanning, spreadsheet import, and predictive analytics come built in, not bolted on as pricey add-ons.',
        '<strong>One predictable price.</strong> Everything in Premium for $' . $argo_monthly . ' CAD/month, with no invoice caps and no per-tier upsells.',
    ],

    // Feature, Argo Free, Argo Premium, Xero.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Starter: $' . $xero_starter . ' CAD/month',
    'table_rows' => [
        ['Expense &amp; revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Invoicing &amp; payments', 'yes', 'yes', 'yes'],
        ['Unlimited invoices', 'no', 'yes', 'no'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['No accounting knowledge required', 'yes', 'yes', 'no'],
        ['Unlimited products', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'yes'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Multi-currency support', 'no', 'no', 'no'],
        ['Bank transaction auto-import', 'no', 'no', 'yes'],
        ['Third-party app integrations', 'no', 'no', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs Xero: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>No accounting jargon</strong>, built for business owners rather than accountants and bookkeepers',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux, with your data stored locally',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics included',
        '<strong>One flat price</strong>, Premium is $' . $argo_monthly . ' CAD/month with unlimited invoices and no upsells',
    ],
    'argo_cons' => [
        'No automatic bank feeds yet, so Xero is the better fit if that\'s core to your workflow',
        'No third-party app integrations yet',
        'No multi-currency support yet',
    ],
    'competitor_cons' => [
        '<strong>No free plan</strong>, pricing runs $' . $xero_starter . ' to $' . $xero_premium . ' CAD/month',
        '<strong>Invoice cap</strong>: the $' . $xero_starter . ' Starter plan limits you to about 20 invoices a month, pushing most to the $' . $xero_standard . ' plan',
        '<strong>Cloud-only</strong>, so no offline access and your data lives on their servers',
        '<strong>Built for accountants</strong>, and it has no inventory, AI spreadsheet import, or predictive analytics',
    ],
    'competitor_pros' => [
        'Automatic bank feeds that import and match transactions for you',
        'Hundreds of third-party integrations and a mature app ecosystem',
        'Multi-currency and advanced reporting available on higher tiers',
    ],

    'key_h2' => 'Built for business owners, not accountants',
    'key_desc' => 'Xero is polished and globally popular, but it\'s cloud-only, designed for businesses that already have an accountant, and its Starter plan caps you at just 20 invoices per month. Argo Books is built for non-accountants who want simplicity, privacy, and affordable pricing.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'No invoice caps', 'p' => 'Xero\'s Starter plan limits you to 20 invoices per month, pushing most businesses to the $' . $xero_standard . '/month Standard plan. Argo Books Premium includes unlimited invoicing for $' . $argo_monthly . ' CAD/month.'],
        ['icon' => 'bolt', 'h3' => 'Works offline', 'p' => 'Xero is cloud-only: no internet, no access to your data. Argo Books is a desktop app that works offline, so your finances are always available and stored locally on your device.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'Xero is a polished, globally popular platform with strong app integrations, unlimited users on every plan, and a clean UI. If your business already works with an accountant or needs deep third-party integrations, Xero is a solid choice.',
        'But if you\'re a small business owner who wants an app that is really easy to use, with offline access, local data storage, and straightforward finance management without paying $' . $xero_standard . '+/month for unlimited invoices, Argo Books is built for you.',
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-quickbooks',
        'argo-books-vs-wave',
        'argo-books-vs-freshbooks',
        'zipbooks-alternatives',
        'odoo-accounting-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books really free?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card, no trial period, and no strings attached. The Free plan includes all core features, ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month, and AI receipt scanning.</p>
                            <p>Xero does not offer a free plan. Pricing starts at $' . $xero_starter . ' CAD/month after a 30-day trial.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption, giving you full control and privacy.</p>
                            <p>Xero is cloud-only and requires a constant internet connection to access your data.</p>'],
        ['q_html' => 'Does Argo Books support bank connections?', 'a_html' => '<p>Not yet. Xero connects directly to major Canadian banks for automatic transaction imports, which is convenient for matching transactions against your books. If automatic bank feeds are critical for your workflow, Xero may be a better fit for now.</p>
                            <p>We\'re always adding new features based on user feedback.</p>'],
        ['q_html' => 'How does Argo Books pricing compare to Xero?', 'a_html' => '<p>Argo Books is significantly more affordable. The Free plan covers most small business needs at no cost. Premium is just <strong>$' . $argo_monthly . ' CAD/month</strong>. Xero starts at $' . $xero_starter . ' CAD/month for Starter (limited to 20 invoices) and goes up to $' . $xero_premium . '/month for Premium.</p>
                            <p>Argo Books has no client limits or invoice caps on Premium.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong>. Because it\'s a desktop app, it\'s fast and responsive, with no browser tabs and no loading spinners.</p>
                            <p>Xero is web-based and also has a mobile app for iOS and Android.</p>'],
    ],

    'cta_h2' => 'Ready to try a simpler alternative?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
