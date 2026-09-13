<?php
// compare/data/odoo-accounting-alternatives.php
//
// Content for /compare/odoo-accounting-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/odoo-accounting-alternatives.php.
$odoo_standard = competitor_price('odoo', 'standard');
$odoo_custom   = competitor_price('odoo', 'custom');

return [
    'competitor' => 'Odoo',

    'breadcrumb' => 'Odoo accounting alternatives',
    'title' => 'Odoo Accounting Alternatives: No Per-User Billing | Argo Books',
    'meta_description' => 'Odoo Accounting alternatives with one flat price instead of per-user ERP billing. Compare simpler small business options on features, setup effort and cost.',
    'meta_keywords' => 'Odoo accounting alternatives, Odoo alternative, ERP alternative for small business, flat price accounting software, simple accounting software',
    'og_title' => 'Odoo Accounting Alternatives: One Flat Price',
    'og_description' => 'Odoo bills per user and per module and expects an implementation. Here are the alternatives that are one flat price and ready to use.',

    'hero_h1' => 'Odoo accounting alternatives',
    'hero_subtitle' => 'A simpler, more affordable way to manage your small business finances. All the essentials, none of the ERP complexity or the per-user price creep.',

    'differences_h2' => 'What\'s the difference between Argo Books and Odoo?',
    'differences_desc' => 'Both can handle your finances. The difference is scope. Odoo is a full modular ERP built for growing, multi-department companies and priced per user; Argo Books is built for the business owner who just needs their books, and priced as one flat plan for the whole team.',
    'why_h3' => 'What changes when you move from Odoo to Argo Books',
    'why_list' => [
        '<strong>Everything in one clean app.</strong> Invoicing, expenses, receipts, inventory, and forecasting together, with no ERP modules to install or configure and no accounting jargon to learn.',
        '<strong>A genuinely usable free plan.</strong> All the core finance features forever, no credit card. Odoo\'s free plan is limited to a single app, so a second module already means paying per user.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your books open instantly and keep working with no internet, with no server to host or maintain.',
        '<strong>AI that\'s included, not upsold.</strong> Receipt scanning, spreadsheet import, and predictive analytics come built in, with no consultant or implementation project required.',
        '<strong>One predictable price.</strong> Everything in Premium for $' . $argo_monthly . ' CAD/month, flat. No per-user fees, so your cost doesn\'t climb as your team grows.',
    ],

    // Feature, Argo Free, Argo Premium, Odoo.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'One App Free / $' . $odoo_standard . '+ CAD/user/mo',
    'table_rows' => [
        ['Expense &amp; revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['No accounting knowledge required', 'yes', 'yes', 'no'],
        ['Unlimited products', 'yes', 'yes', 'yes'],
        ['Invoicing &amp; payments', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'yes'],
        ['AI receipt scanning', 'yes', 'yes', 'yes'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'yes'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['CRM &amp; sales pipeline', 'no', 'no', 'yes'],
        ['HR &amp; payroll', 'no', 'no', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs Odoo: pros &amp; cons',
    'argo_pros' => [
        '<strong>One flat price</strong>, Premium is $' . $argo_monthly . ' CAD/month for your whole team, with no per-user fees',
        '<strong>All your finances in one app</strong>: invoicing, expenses, inventory, and reporting, with no ERP modules to configure',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux, with no server to host',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics included',
        '<strong>Simple from day one</strong>, no consultant or implementation project to get started',
    ],
    'argo_cons' => [
        'No CRM or sales pipeline, so Odoo is the better fit if you need those',
        'No HR or payroll modules',
        'A focused finance tool, not a full modular suite with hundreds of apps',
    ],
    'competitor_cons' => [
        '<strong>Priced per user</strong>: from $' . $odoo_standard . ' CAD/user/month, so cost climbs fast as your team grows',
        '<strong>Complex to set up</strong>, a full ERP that often needs configuration or a consultant',
        '<strong>Developer-oriented</strong>, and the free plan is limited to a single app',
    ],
    'competitor_pros' => [
        'Extremely powerful, a full modular ERP that scales to complex needs',
        'Huge app ecosystem: CRM, HR, manufacturing, e-commerce, and hundreds more',
        'Deeply customizable for growing, multi-department companies',
    ],

    'key_h2' => 'Built for small businesses, not enterprise ERP',
    'key_desc' => 'Odoo is a full ERP suite with hundreds of apps designed for mid-to-large businesses. Argo Books is purpose-built for small businesses that need finance and inventory management without the complexity.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'More affordable', 'p' => 'Odoo charges per user per month, and costs add up fast as your team grows. Argo Books has a free version and Premium at a flat $' . $argo_monthly . ' CAD/month, with no per-user fees.'],
        ['icon' => 'bolt', 'h3' => 'Simple from day one', 'p' => 'Odoo\'s learning curve is steep: it\'s a full ERP with hundreds of modules. Argo Books is focused and intuitive, so you can get started in minutes, not weeks.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'Odoo is a powerful, full-featured ERP platform with CRM, HR, manufacturing, e-commerce, and hundreds of other modules. If your business needs an all-in-one enterprise system, Odoo is hard to beat.',
        'But if you\'re a small business that just needs straightforward finance management, inventory tracking, and invoicing without configuring an entire ERP, Argo Books gets you there in minutes, not weeks.',
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-quickbooks',
        'argo-books-vs-wave',
        'argo-books-vs-freshbooks',
        'argo-books-vs-xero',
        'zipbooks-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books really free?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card, no trial period, and no strings attached. The Free plan includes all core features, ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month, and AI receipt scanning.</p>
                            <p>Odoo\'s free plan is limited to a single app, and adding a second module starts at $' . $odoo_standard . ' CAD/user/month.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption, giving you full control and privacy.</p>
                            <p>Odoo Online requires a constant internet connection, and self-hosted Odoo requires significant IT infrastructure to set up and maintain.</p>'],
        ['q_html' => 'Does Argo Books have CRM or HR features?', 'a_html' => '<p>No. Argo Books is focused on finance management, inventory, invoicing, and financial reporting. If you need CRM, HR, manufacturing, or other enterprise modules, Odoo is the better choice.</p>
                            <p>Argo Books is designed to do fewer things really well: it\'s simple to learn and doesn\'t require a consultant to set up.</p>'],
        ['q_html' => 'How does Argo Books pricing compare to Odoo?', 'a_html' => '<p>Argo Books is much simpler and more affordable. The Free plan covers most small business needs at no cost. Premium is just <strong>$' . $argo_monthly . ' CAD/month</strong>. Odoo\'s free tier is limited to one app, and as soon as you need invoicing plus inventory (two apps), pricing jumps to $' . $odoo_standard . '+ CAD/user/month.</p>
                            <p>Costs escalate quickly as you add modules and users.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong>. Because it\'s a desktop app, it\'s fast and responsive, with no browser tabs and no loading spinners.</p>
                            <p>Odoo Online is web-based, and self-hosted Odoo can run on any server but requires technical expertise to deploy.</p>'],
    ],

    'cta_h2' => 'Ready to try a simpler alternative?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
