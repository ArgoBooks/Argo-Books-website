<?php
// compare/data/argo-books-vs-quickbooks.php
//
// Content for /compare/argo-books-vs-quickbooks/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/argo-books-vs-quickbooks.php.
$qb_easystart = competitor_price('quickbooks', 'easystart');
$qb_plus      = competitor_price('quickbooks', 'plus');
$qb_advanced  = competitor_price('quickbooks', 'advanced');

return [
    'competitor' => 'QuickBooks',
    'extra_styles' => [
        '../../resources/styles/link.css',
    ],

    'breadcrumb' => 'QuickBooks alternative',
    'title' => 'QuickBooks Alternative Without a Subscription | Argo Books',
    'meta_description' => 'A QuickBooks alternative that runs on your own computer with no subscription creep. Compare price, offline access and features against QuickBooks Online.',
    'meta_keywords' => 'QuickBooks alternative, QuickBooks alternative without subscription, offline QuickBooks alternative, desktop accounting software, QuickBooks Desktop replacement',
    'og_title' => 'A QuickBooks Alternative Without the Subscription',
    'og_description' => 'QuickBooks raises its price every year and needs a connection. Here is the alternative that runs on your machine for one flat price.',

    'hero_h1' => 'Argo Books vs QuickBooks',
    'hero_subtitle' => 'A simpler, more affordable way to manage your small business finances. All the essentials, none of the accounting jargon or the price creep.',

    'differences_h2' => 'What\'s the difference between Argo Books and QuickBooks?',
    'differences_desc' => 'Both handle the accounting basics. The difference is who they\'re built for. QuickBooks is built for accountants and priced for growth; Argo Books is built for the business owner doing their own books, and priced to stay that way.',
    'why_h3' => 'What changes when you move from QuickBooks to Argo Books',
    'why_list' => [
        '<strong>Everything in one clean app.</strong> Invoicing, expenses, receipts, inventory, and forecasting together, with no accounting jargon and no double-entry to learn.',
        '<strong>A genuinely free plan.</strong> All the core features forever, no trial and no credit card. QuickBooks has no free tier at all.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your books open instantly and keep working with no internet, and your data stays on your machine.',
        '<strong>AI that\'s included, not upsold.</strong> Receipt scanning, spreadsheet import, and predictive analytics come built in, not bolted on as pricey add-ons.',
        '<strong>One predictable price.</strong> Everything in Premium for $' . $argo_monthly . ' CAD/month. No per-client fees, no upsells, no yearly price hikes.',
    ],

    // Feature, Argo Free, Argo Premium, QuickBooks.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD / month',
    'table_competitor_sub' => 'EasyStart: $' . $qb_easystart . ' CAD / month',
    'table_rows' => [
        ['Expense &amp; revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['No accounting knowledge required', 'yes', 'yes', 'no'],
        ['Unlimited products', 'yes', 'yes', 'yes'],
        ['Invoicing &amp; payments', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'yes'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'yes'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Payroll (Canada)', 'no', 'yes', 'yes'],
        ['Tax filing', 'no', 'no', 'yes'],
        ['Third-party app integrations', 'yes', 'yes', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs QuickBooks: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>No accounting jargon</strong>, built for business owners rather than accountants',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics included',
        '<strong>One flat price</strong>, Premium is $' . $argo_monthly . ' CAD/month with no upsells or yearly hikes',
        '<strong>Canadian payroll included</strong> in that price: CPP, EI and T4s, with no per-employee fee',
    ],
    'argo_cons' => [
        'Payroll covers Canada only, so QuickBooks is the better fit if you pay staff elsewhere',
        'No integrated tax filing yet',
        'A newer platform with a smaller ecosystem than a 20-year incumbent',
    ],
    'competitor_cons' => [
        '<strong>High, rising prices</strong>: $' . $qb_easystart . ' to $' . $qb_advanced . ' CAD/month, up around 70% in five years',
        '<strong>Steeper learning curve</strong>, it assumes double-entry accounting knowledge',
        '<strong>Core features gated</strong> behind pricier tiers, like inventory on Plus',
    ],
    'competitor_pros' => [
        'Built-in payroll and integrated tax filing',
        'Hundreds of third-party integrations and a mature ecosystem',
        'Deep reporting and advanced tools for larger, accountant-run teams',
    ],

    'key_h2' => 'Built for small businesses, not accountants',
    'key_desc' => 'QuickBooks assumes double-entry knowledge, surfaces accounting jargon throughout the UI, and gates useful features behind expensive tiers. Argo Books was built for business owners who want to manage finances without the learning curve or the price creep.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'No price creep', 'p' => 'QuickBooks has raised their prices twice since we launched this comparison page, and we\'ve had to update these numbers each time. They increased their prices by 70% in the last 5 years. How much will they increase it in the next 5 years?'],
        ['icon' => 'bolt', 'h3' => 'No feature gating', 'p' => 'QuickBooks locks inventory management and other core features behind their $' . $qb_plus . '+/month plans. Argo Books Premium gives you everything for $' . $argo_monthly . ' CAD/month: no upsells, no surprises.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest_comment' => 'Confusion Stats',
    'honest_icon' => 'help-circle',
    'honest_icon_size' => 30,
    'honest_h3' => 'The most confusing office tool in America',
    'honest_cta' => false,
    'honest' => [
        'According to a <a class="link" href="https://www.digitaljournal.com/tech-science/the-most-puzzling-office-apps-in-the-u-s-revealed/article" target="_blank" rel="noopener noreferrer">study by Digital Adoption</a>, QuickBooks is the most confusing office application in the U.S., generating over 68,000 support-related Google searches every month. The most common query? "QuickBooks customer service," searched 19,000 times per month in the U.S. alone.',
        'Argo Books takes the opposite approach. No accounting jargon, no double-entry complexity, just a clean, intuitive interface designed for business owners, not accountants.',
    ],

    // A second honest-take block, unique to this page.
    'honest_alt' => [
        'label' => 'An Honest Take',
        'h2' => 'QuickBooks is powerful, but is it right for you?',
        'paras' => [
            'QuickBooks is a mature platform with payroll in several countries, tax filing, and hundreds of integrations. If your business needs those features today, it\'s a solid choice. But as a publicly traded company, Intuit\'s priorities don\'t always align with small business owners, and it shows in the rising prices, aggressive feature gating, and complexity you don\'t need. Argo Books is built for you. Simple pricing, no upsells, and every feature included in one plan.',
            'Still weighing your options? Read our roundup of the <a class="link" href="../../best-quickbooks-alternatives/">best QuickBooks alternatives</a>, an honest look at Wave, Xero, FreshBooks, Zoho, and more, with where each one fits best.',
        ],
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-wave',
        'argo-books-vs-freshbooks',
        'argo-books-vs-xero',
        'zipbooks-alternatives',
        'odoo-accounting-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books really free?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card, no trial period, and no strings attached. The Free plan includes all core features, ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month, and AI receipt scanning.</p>
                            <p>QuickBooks does not offer a free plan. Pricing starts at $' . $qb_easystart . ' CAD/month after a limited trial.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption, giving you full control and privacy.</p>
                            <p>QuickBooks Online requires a constant internet connection to access your data.</p>'],
        ['q_html' => 'Does Argo Books support payroll or tax filing?', 'a_html' => '<p>Payroll, yes, for Canada. Premium works out CPP, EI and income tax for every province and territory, prints the pay stubs, and prepares your T4 slips and the CRA\'s XML file at year end, plus an RL-1 worksheet for Quebec staff. It does not pay staff outside Canada, and it does not file or remit on your behalf: you upload the file and make the payment. Integrated tax filing is still something QuickBooks does and Argo Books does not.</p>
                            <p>We\'re always adding new features based on user feedback.</p>'],
        ['q_html' => 'How does Argo Books pricing compare to QuickBooks?', 'a_html' => '<p>Argo Books is dramatically more affordable. The Free plan covers most small business needs at no cost. Premium is just <strong>$' . $argo_monthly . ' CAD/month</strong>. QuickBooks starts at $' . $qb_easystart . ' CAD/month for EasyStart and goes up to $' . $qb_advanced . '/month for Advanced, and that\'s before add-ons like payroll.</p>
                            <p>Argo Books has no hidden fees or client limits.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong>. Because it\'s a desktop app, it\'s fast and responsive, with no browser tabs and no loading spinners.</p>
                            <p>QuickBooks Online is web-based, and QuickBooks Desktop (Windows only) has been discontinued for new purchases in favor of the cloud version.</p>'],
    ],

    'cta_h2' => 'Ready to try a simpler alternative?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
