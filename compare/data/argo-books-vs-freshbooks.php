<?php
// compare/data/argo-books-vs-freshbooks.php
//
// Content for /compare/argo-books-vs-freshbooks/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/argo-books-vs-freshbooks.php.
$argo_yearly  = (int) $pricing['premium_yearly_price'];
$fb_lite      = competitor_price('freshbooks', 'lite');
$fb_plus      = competitor_price('freshbooks', 'plus');
$fb_premium   = competitor_price('freshbooks', 'premium');

return [
    'competitor' => 'FreshBooks',

    'breadcrumb' => 'Argo Books vs FreshBooks',
    'title' => 'Argo Books vs FreshBooks: Simpler & More Affordable | Argo Books',
    'meta_description' => 'Argo Books vs FreshBooks: Compare features, pricing, and ease of use. See why small businesses choose Argo Books as a simpler, more affordable FreshBooks alternative.',
    'meta_keywords' => 'Argo Books vs FreshBooks, FreshBooks alternative, FreshBooks alternative Canada, cheap FreshBooks alternative, simple bookkeeping software, small business accounting, affordable accounting software',
    'og_title' => 'Argo Books vs FreshBooks: A Simpler, More Affordable Alternative',
    'og_description' => 'Compare Argo Books and FreshBooks side by side. See why small businesses are choosing Argo Books for simpler, more affordable finance management.',

    'hero_h1' => 'Argo Books vs FreshBooks',
    'hero_subtitle' => 'A simpler, more affordable way to manage your small business finances. All the essentials, none of the accounting jargon or the per-client fees.',

    'differences_h2' => 'What\'s the difference between Argo Books and FreshBooks?',
    'differences_desc' => 'Both handle the small business basics. The difference is who they\'re built for. FreshBooks is built for freelancers billing by the hour and priced per client; Argo Books is built for the business owner doing their own books, with no client limits and one flat price.',
    'why_h3' => 'What changes when you move from FreshBooks to Argo Books',
    'why_list' => [
        '<strong>Everything in one clean app.</strong> Invoicing, expenses, receipts, inventory, and forecasting together, with no accounting jargon and no double-entry to learn.',
        '<strong>A genuinely free plan.</strong> All the core features forever, no trial and no credit card. FreshBooks only gives you a 30-day trial.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your books open instantly and keep working with no internet, and your data stays on your machine.',
        '<strong>AI that\'s included, not upsold.</strong> Receipt scanning, spreadsheet import, and predictive analytics come built in, not features FreshBooks offers at all.',
        '<strong>One predictable price.</strong> Everything in Premium for $' . $argo_monthly . ' CAD/month. No per-client fees and no client limits on any plan.',
    ],

    // Feature, Argo Free, Argo Premium, FreshBooks.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Lite: $' . $fb_lite . ' CAD/month',
    'table_rows' => [
        ['Expense &amp; revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Invoicing &amp; payments', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['No accounting knowledge required', 'yes', 'yes', 'yes'],
        ['Unlimited products', 'yes', 'yes', 'no'],
        ['Inventory management', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'no'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Time tracking', 'no', 'no', 'yes'],
        ['Client portal', 'no', 'no', 'yes'],
        ['Mobile app', 'no', 'no', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs FreshBooks: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>No accounting jargon</strong>, built for business owners rather than accountants',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics included',
        '<strong>One flat price</strong>, Premium is $' . $argo_monthly . ' CAD/month with no per-client fees or client limits',
    ],
    'argo_cons' => [
        'No time tracking, so FreshBooks is the better fit if you bill by the hour',
        'No client portal for clients to view and pay invoices online',
        'No mobile app yet, it\'s desktop-first',
    ],
    'competitor_cons' => [
        '<strong>No free plan</strong> and per-client pricing: $' . $fb_lite . ' to $' . $fb_premium . ' CAD/month, with clients capped on cheaper tiers',
        '<strong>Cloud-only</strong>, no offline desktop access to your own books',
        '<strong>No inventory management</strong>, so it doesn\'t suit product-based businesses',
        '<strong>No AI tools</strong>: no receipt scanning, spreadsheet import, or predictive analytics',
        'No biometric login security',
    ],
    'competitor_pros' => [
        'Built-in time tracking, great for freelancers and consultants billing by the hour',
        'Client portal so clients can view and pay invoices online',
        'Mobile apps for iOS and Android, plus polished, strong invoicing',
    ],

    'key_h2' => 'No client limits, and it works offline',
    'key_desc' => 'Both tools work for small businesses, but they focus on different things. FreshBooks shines at invoicing and time tracking. Argo Books focuses on simplicity, offline access, and inventory.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'More affordable', 'p' => 'FreshBooks starts at $' . $fb_lite . ' CAD/month for just 5 clients. Argo Books has a free version with core features, and Premium is a fraction of the cost with no client limits.'],
        ['icon' => 'bolt', 'h3' => 'Works offline', 'p' => 'FreshBooks is cloud-only: no internet, no access. Argo Books is a desktop app that works offline, so you\'re never locked out of your own data.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'FreshBooks excels at invoicing, time tracking, and client management, especially for freelancers and service-based businesses. If those are your core needs, FreshBooks is a great tool.',
        'But if you\'re a product-based small business that needs inventory management, offline access, and straightforward finance tracking without paying $' . $fb_lite . '+ CAD/month, Argo Books is built for you.',
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-quickbooks',
        'argo-books-vs-wave',
        'argo-books-vs-xero',
        'zipbooks-alternatives',
        'odoo-accounting-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books really free?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card, no trial period, and no strings attached. The Free plan includes all core features, ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month, and AI receipt scanning.</p>
                            <p>FreshBooks only offers a 30-day free trial before requiring a paid plan starting at $' . $fb_lite . ' CAD/month.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption, giving you full control and privacy.</p>
                            <p>FreshBooks is cloud-only and requires a constant internet connection to access your data.</p>'],
        ['q_html' => 'Does Argo Books have time tracking?', 'a_html' => '<p>Not yet. FreshBooks has built-in time tracking, which is great for freelancers and consultants who bill by the hour. If billable hours are a core part of your business, FreshBooks may be a better fit for that specific need.</p>
                            <p>Argo Books is focused on product-based businesses, inventory management, and financial reporting.</p>'],
        ['q_html' => 'How does Argo Books pricing compare to FreshBooks?', 'a_html' => '<p>Argo Books is significantly more affordable. The Free plan covers most small business needs at no cost. Premium is just <strong>$' . $argo_monthly . ' CAD/month</strong> (or $' . $argo_yearly . '/year). FreshBooks starts at $' . $fb_lite . ' CAD/month for its Lite plan with a 5-client limit, and goes up to $' . $fb_premium . '/month for Premium.</p>
                            <p>Argo Books has no client limits on any plan.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong>. Because it\'s a desktop app, it\'s fast and responsive, with no browser tabs and no loading spinners.</p>
                            <p>FreshBooks is web-based and also has a mobile app for iOS and Android.</p>'],
    ],

    'cta_h2' => 'Ready to try a simpler alternative?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
