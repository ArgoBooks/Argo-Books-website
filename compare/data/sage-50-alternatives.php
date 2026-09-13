<?php
// compare/data/sage-50-alternatives.php
//
// Content for /compare/sage-50-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/sage-50-alternatives.php.
$argo_yearly  = (int) $pricing['premium_yearly_price'];
$sage_pro     = competitor_price('sage', 'pro');     // 68  (monthly-equivalent; $814/yr, 1 user)
$sage_premium = competitor_price('sage', 'premium'); // 102 ($1,219/yr, 2 users)
$sage_quantum = competitor_price('sage', 'quantum'); // 470 ($5,636/yr, 5 users)
$sage_autoentry = competitor_price('sage', 'autoentry'); // 145 (AutoEntry document-capture add-on, 500 credits)

return [
    'competitor' => 'Sage 50',

    'breadcrumb' => 'Sage 50 alternatives',
    'title' => 'Sage 50 Alternatives: Modern, Cheaper, Cross-Platform | Argo Books',
    'meta_description' => 'Sage 50 alternatives for small businesses that want desktop accounting without the price or the complexity. Compare cross-platform options on features and cost.',
    'meta_keywords' => 'Sage 50 alternatives, Sage 50 alternative, Sage alternative, desktop accounting software, cross-platform accounting software, cheap accounting software',
    'og_title' => 'Sage 50 Alternatives: A Fraction of the Price',
    'og_description' => 'Sage 50 is powerful, expensive and Windows-only. Here are the modern cross-platform alternatives, minus the complexity.',

    'hero_h1' => 'Sage 50 alternatives',
    'hero_subtitle' => 'A simpler, more affordable way to manage your small business finances. All the essentials, none of Sage\'s price tag, learning curve, or Windows-only limits.',

    'differences_h2' => 'What\'s the difference between Argo Books and Sage 50?',
    'differences_desc' => 'Sage 50 is deep, mature desktop accounting built for established or complex businesses, but it\'s pricey, Windows-only, and has a steep learning curve. Argo Books is a fraction of the cost, modern and simple, cross-platform, with AI built in, for owners who don\'t need Sage\'s depth.',
    'why_h3' => 'What changes when you move from Sage 50 to Argo Books',
    'why_list' => [
        '<strong>Everything in one clean app.</strong> Invoicing, expenses, receipts, inventory, and forecasting together, with no accounting jargon and no double-entry to learn.',
        '<strong>A genuinely free plan.</strong> All the core features forever, no trial and no credit card. Sage 50 has no free plan, only a time-limited trial.',
        '<strong>Modern and cross-platform.</strong> A native desktop app that runs on Windows, macOS, and Linux, with a clean modern interface. Sage 50 is powerful, but it\'s Windows-only and its interface looks and feels its age.',
        '<strong>AI that\'s included, not upsold.</strong> Receipt scanning, bank-statement import, and spreadsheet import are built into Premium at $' . $argo_monthly . '/mo. On Sage 50 that same document capture is a paid add-on, AutoEntry, at about $' . $sage_autoentry . ' CAD/month for 500 credits.',
        '<strong>One predictable price.</strong> Everything in Premium for $' . $argo_monthly . ' CAD/month. No annual lock-in and none of Sage\'s four-figure yearly bills.',
    ],

    // Feature, Argo Free, Argo Premium, Sage 50.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Pro: $' . $sage_pro . ' CAD/mo (billed annually)',
    'table_rows' => [
        ['Expense &amp; revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Invoicing &amp; payments', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'yes'],
        ['Runs on Windows, macOS &amp; Linux', 'yes', 'yes', 'no'],
        ['No accounting knowledge required', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'no'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Advanced inventory (serial/BOM), job costing &amp; payroll', 'no', 'no', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs Sage 50: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>One flat price</strong>, Premium is $' . $argo_monthly . ' CAD/month vs Sage from around $' . $sage_pro . '/month (billed yearly)',
        '<strong>Modern and simple</strong>, built for business owners with no accounting degree required',
        '<strong>Truly cross-platform</strong>, runs on Windows, macOS, and Linux from one app',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics included',
    ],
    'argo_cons' => [
        'Not as deep as Sage for complex accounting: no job or project costing',
        'No serial-number or bill-of-materials inventory for advanced stock control',
        'No departmental accounting or built-in payroll',
    ],
    'competitor_cons' => [
        '<strong>Expensive</strong>: from around $' . $sage_pro . '/month (~$814/yr) up to $5,636/yr, and billed annually',
        '<strong>Windows-only</strong>, so Linux users are left out',
        '<strong>Steep learning curve and a dated interface</strong>: dense menus and toolbars that feel a decade or two behind, built for accountants rather than owners',
        '<strong>No free plan</strong>, only a time-limited trial',
        '<strong>Document capture costs extra</strong>: receipt and statement capture (AutoEntry) is a usage-based add-on at about $' . $sage_autoentry . ' CAD/month for 500 credits, and there\'s no predictive analytics',
    ],
    'competitor_pros' => [
        'Extremely deep, mature accounting that scales to complex businesses',
        'Advanced inventory with serial numbers, bill of materials, and multiple locations',
        'Job and project costing, departmental accounting, and payroll add-ons',
    ],

    'key_h2' => 'Desktop accounting without Sage 50\'s price or learning curve',
    'key_desc' => 'Both tools are desktop accounting apps, but they focus on different things. Sage 50 shines at deep, complex accounting for established businesses. Argo Books focuses on being simple, affordable, and cross-platform.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'A fraction of the cost', 'p' => 'Sage 50 runs from about $814/year up to $5,636/year, billed annually. Argo Books has a free version with core features, and Premium is just $' . $argo_monthly . ' CAD/month.'],
        ['icon' => 'bolt', 'h3' => 'Modern &amp; cross-platform', 'p' => 'Sage 50 is powerful but Windows-only with a steep learning curve. Argo Books is the opposite: so simple that anyone can keep their own books from day one, with no training and no accounting background, on Windows, macOS, or Linux.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'Sage 50 is deep, mature desktop accounting: advanced inventory, job costing, departmental accounting, and payroll. If you run an established or complex business that genuinely needs that depth, Sage 50 is a powerful tool.',
        'But if you\'re a small business that wants clean, simple books without the four-figure yearly bill, the steep learning curve, or the Windows-only limits, and you\'d like AI and a free plan, Argo Books is built for you.',
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
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books really free?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card, no trial period, and no strings attached. The Free plan includes all core features, ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month, and AI receipt scanning.</p>
                            <p>Sage 50 has no free plan, only a time-limited trial before paid plans that start around $' . $sage_pro . ' CAD/month (billed annually).</p>'],
        ['q_html' => 'Does Argo Books run on Linux?', 'a_html' => '<p>Yes. Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong> from the same app.</p>
                            <p>Sage 50 is a Windows-only desktop program, so Linux users are left out. If you\'re not on Windows, Argo Books is the more flexible choice.</p>'],
        ['q_html' => 'Is Argo Books as powerful as Sage 50?', 'a_html' => '<p>It depends on what you need. Sage 50 is deeper for complex accounting: advanced inventory with serial numbers and bill of materials, job and project costing, departmental accounting, and payroll add-ons.</p>
                            <p>Argo Books is deliberately simpler. For most owners who want clean books, invoicing, expenses, inventory, and reports without an accounting degree, Argo Books does the job at a fraction of the price. If you run a large or complex operation that needs Sage\'s depth, Sage 50 may be the better fit.</p>'],
        ['q_html' => 'How does Argo Books pricing compare to Sage 50?', 'a_html' => '<p>Argo Books is dramatically more affordable. The Free plan covers most small business needs at no cost, and Premium is just <strong>$' . $argo_monthly . ' CAD/month</strong> (or $' . $argo_yearly . '/year).</p>
                            <p>Sage 50 is billed annually and runs from about $814/year (roughly $' . $sage_pro . '/month, 1 user) up to $5,636/year (roughly $' . $sage_quantum . '/month, 5 users). Sage 50 also has no free plan.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong>. Because it\'s a desktop app, it\'s fast and responsive, and it works offline.</p>
                            <p>Sage 50 is also a desktop app, but it\'s Windows-only, so Linux users aren\'t supported.</p>'],
    ],

    'cta_h2' => 'Ready to try a simpler alternative?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
