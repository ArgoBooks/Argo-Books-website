<?php
// compare/data/argo-books-vs-wave.php
//
// Content for /compare/argo-books-vs-wave/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/argo-books-vs-wave.php.
$wave_pro         = competitor_price('wave', 'pro');
$wave_receipt_mo  = competitor_price('wave', 'receipt_addon', 'monthly');
$wave_receipt_yr  = competitor_price('wave', 'receipt_addon', 'yearly');

return [
    'competitor' => 'Wave',

    'breadcrumb' => 'Argo Books vs Wave',
    'title' => 'Argo Books vs Wave: Offline-Capable & Feature-Rich | Argo Books',
    'meta_description' => 'Argo Books vs Wave: Compare features, pricing, and ease of use. See why small businesses choose Argo Books over Wave for offline-capable finance and inventory management.',
    'meta_keywords' => 'Argo Books vs Wave, Wave alternative, Wave accounting alternative, simple bookkeeping software, small business accounting, affordable accounting software, offline accounting software',
    'og_title' => 'Argo Books vs Wave: Offline-Capable Finance & Inventory Management',
    'og_description' => 'Compare Argo Books and Wave side by side. See why small businesses are choosing Argo Books for offline-capable finance and inventory management.',

    'hero_h1' => 'Argo Books vs Wave',
    'hero_subtitle' => 'Both free to start, but built for different businesses. Argo Books does more for product businesses: inventory, offline access, and AI receipt scanning included free.',

    'differences_h2' => 'What\'s the difference between Argo Books and Wave?',
    'differences_desc' => 'Both are free to start. The difference is what you get. Wave keeps things simple for freelancers and service businesses; Argo Books does more for product businesses, with inventory, offline access, and AI receipt scanning included free, for less on the paid plan.',
    'why_h3' => 'What changes when you move from Wave to Argo Books',
    'why_list' => [
        '<strong>Everything in one clean app.</strong> Invoicing, expenses, receipts, inventory, and forecasting together, with no accounting jargon and no double-entry to learn.',
        '<strong>A free plan that does more.</strong> All the core features forever, plus inventory and AI receipt scanning at no extra cost. Wave\'s free Starter charges extra for receipt scanning.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your books open instantly and keep working with no internet, and your data stays on your machine. Wave is cloud-only.',
        '<strong>AI that\'s included, not upsold.</strong> Receipt scanning, spreadsheet import, and predictive analytics come built in. Wave charges about $' . $wave_receipt_mo . '/month for receipt scanning on its free plan.',
        '<strong>One predictable price.</strong> Everything in Premium for $' . $argo_monthly . ' CAD/month, less than Wave Pro at $' . $wave_pro . ' CAD/month. No per-add-on fees.',
    ],

    // Feature, Argo Free, Argo Premium, Wave.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Pro: $' . $wave_pro . ' CAD/month',
    'table_rows' => [
        ['Expense &amp; revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Invoicing &amp; payments', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['No accounting knowledge required', 'yes', 'yes', 'yes'],
        ['Unlimited products', 'yes', 'yes', 'no'],
        ['Inventory management', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'yes'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Auto bank transaction import', 'no', 'no', 'no'],
        ['Mobile app', 'no', 'no', 'yes'],
        ['Payroll (Canada)', 'no', 'yes', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs Wave: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan that does more</strong>, with inventory and AI receipt scanning included at no extra cost',
        '<strong>No accounting jargon</strong>, built for business owners rather than accountants',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics included',
        '<strong>One flat price</strong>, Premium is $' . $argo_monthly . ' CAD/month, less than Wave Pro at $' . $wave_pro,
        '<strong>Canadian payroll included</strong> in that price: CPP, EI and T4s, where Wave charges for payroll separately',
    ],
    'argo_cons' => [
        'No automatic bank transaction import yet',
        'No mobile app yet, Wave has iOS and Android apps',
        'Payroll covers Canada only, so Wave is the better fit if you pay staff elsewhere',
    ],
    'competitor_cons' => [
        '<strong>Receipt scanning costs extra</strong>, about $' . $wave_receipt_mo . '/month on the free Starter plan',
        '<strong>Cloud-only</strong>, no offline access and no desktop app',
        '<strong>No inventory</strong>, spreadsheet import, predictive analytics, or biometric login',
    ],
    'competitor_pros' => [
        'A genuinely free Starter plan with no time limit',
        'Simple and quick to set up, great for freelancers and service businesses',
        'Mobile apps for iOS and Android, plus built-in payroll',
    ],

    'key_h2' => 'Built for product businesses, not just service providers',
    'key_desc' => 'Wave is great for freelancers and service businesses. Argo Books is built for small businesses that sell products and need inventory management, offline access, and predictive analytics.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'Inventory management', 'p' => 'Wave has no inventory features at all. Argo Books Premium includes full inventory management, so you can track stock levels alongside your finances.'],
        ['icon' => 'bolt', 'h3' => 'Works offline', 'p' => 'Wave is cloud-only: no internet, no access. Argo Books is a desktop app that works offline, so you\'re never locked out of your own data.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'Wave is an excellent free option for freelancers and service-based businesses that need invoicing, basic accounting, and bank transaction imports. If those are your core needs, Wave is a solid choice.',
        'But if you sell physical products and need inventory management, offline access, predictive analytics, or biometric security (features Wave doesn\'t offer), Argo Books is built for you.',
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-quickbooks',
        'argo-books-vs-freshbooks',
        'argo-books-vs-xero',
        'zipbooks-alternatives',
        'odoo-accounting-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books really free?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card, no trial period, and no strings attached. The Free plan includes all core features, ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month, and AI receipt scanning.</p>
                            <p>Wave also offers a free Starter plan, but features like auto bank import require the Pro plan at $' . $wave_pro . ' CAD/month, and receipt scanning costs another $' . $wave_receipt_mo . '/month or $' . $wave_receipt_yr . '/year on the free Starter plan (it\'s included on Pro).</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption, giving you full control and privacy.</p>
                            <p>Wave is cloud-only and requires a constant internet connection to access your data.</p>'],
        ['q_html' => 'Does Argo Books support bank transaction imports?', 'a_html' => '<p>Not yet. Wave\'s Pro plan includes automatic bank transaction imports, which is convenient for matching transactions against your books. If automatic bank feeds are critical for your workflow, Wave may be a better fit for now.</p>
                            <p>We\'re always adding new features based on user feedback.</p>'],
        ['q_html' => 'How does Argo Books pricing compare to Wave?', 'a_html' => '<p>Both offer free plans. Wave\'s Starter is free with manual transaction entry, and the Pro plan is $' . $wave_pro . ' CAD/month for auto bank imports. Receipt scanning costs another $' . $wave_receipt_mo . '/month or $' . $wave_receipt_yr . '/year on Wave\'s free Starter plan (it\'s included on Pro).</p>
                            <p>Argo Books Premium is just <strong>$' . $argo_monthly . ' CAD/month</strong> with unlimited invoicing, AI receipt scanning included, and predictive analytics, less than half of Wave\'s Pro plan.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong>. Because it\'s a desktop app, it\'s fast and responsive, with no browser tabs and no loading spinners.</p>
                            <p>Wave is web-based and also has a mobile app for iOS and Android.</p>'],
    ],

    'cta_h2' => 'Ready to try a more capable free option?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
