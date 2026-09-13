<?php
// compare/data/zipbooks-alternatives.php
//
// Content for /compare/zipbooks-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/zipbooks-alternatives.php.
$zb_smarter         = competitor_price('zipbooks', 'smarter');
$zb_sophisticated   = competitor_price('zipbooks', 'sophisticated');

return [
    'competitor' => 'ZipBooks',

    'breadcrumb' => 'ZipBooks alternatives',
    'title' => 'ZipBooks Alternatives: Free and Paid Options Compared | Argo Books',
    'meta_description' => 'ZipBooks alternatives compared on free-plan limits, pricing and features. See which small business accounting app gives you more without the upgrade pressure.',
    'meta_keywords' => 'ZipBooks alternatives, ZipBooks alternative, free accounting software, small business accounting software, cheap accounting software',
    'og_title' => 'ZipBooks Alternatives: More Features, Lower Price',
    'og_description' => 'Comparing ZipBooks alternatives on free-plan limits, upgrade price and what you actually get for it.',

    'hero_h1' => 'ZipBooks alternatives',
    'hero_subtitle' => 'Both simple, both free to start. But Argo Books works offline, includes AI, and costs less to upgrade.',

    'differences_h2' => 'What\'s the difference between Argo Books and ZipBooks?',
    'differences_desc' => 'Both are simple, and both are free to start. The difference is where your data lives, what\'s built in, and what you pay to unlock more. Argo Books works offline, includes AI, and costs less to upgrade.',
    'why_h3' => 'What changes when you move from ZipBooks to Argo Books',
    'why_list' => [
        '<strong>Everything in one clean app.</strong> Invoicing, expenses, receipts, inventory, and forecasting together, with no accounting jargon and no double-entry to learn.',
        '<strong>A more capable free plan.</strong> Both are free to start, but Argo\'s Free tier adds AI receipt scanning and inventory that ZipBooks\' free plan doesn\'t include.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your books open instantly and keep working with no internet, and your data stays on your machine. ZipBooks is cloud-only.',
        '<strong>AI that\'s built in.</strong> Receipt scanning, spreadsheet import, and predictive analytics come included, features ZipBooks doesn\'t offer at all.',
        '<strong>One predictable price.</strong> Everything in Premium for $' . $argo_monthly . ' CAD/month, less than ZipBooks Smarter, with no per-client fees or upsells.',
    ],

    // Feature, Argo Free, Argo Premium, ZipBooks.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Starter: Free',
    'table_rows' => [
        ['Expense &amp; revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Invoicing &amp; payments', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['No accounting knowledge required', 'yes', 'yes', 'yes'],
        ['Unlimited products', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'no'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Local data storage', 'yes', 'yes', 'no'],
    ],

    'pros_cons_h2' => 'Argo Books vs ZipBooks: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>No accounting jargon</strong>, built for business owners rather than accountants',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux, with your data stored locally',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics included',
        '<strong>One flat price</strong>, Premium is $' . $argo_monthly . ' CAD/month, cheaper than ZipBooks Smarter, with no upsells',
    ],
    'argo_cons' => [
        'Desktop-first, so there\'s no browser or mobile-web access the way a cloud tool offers',
        'A newer platform with a smaller ecosystem than longer-established tools',
    ],
    'competitor_cons' => [
        '<strong>Cloud-only</strong>, no offline access, so no internet means no access to your books',
        '<strong>No AI</strong> receipt scanning or spreadsheet import, and no predictive analytics',
        '<strong>No inventory management</strong>, and your data lives on their servers rather than your machine',
        '<strong>Limited development</strong> since the BILL Holdings acquisition',
    ],
    'competitor_pros' => [
        'Free Starter tier with basic invoicing and reports',
        'Simple and clean, with no accounting knowledge required',
        'Cloud-based and accessible from any browser',
    ],

    'key_h2' => 'Same simplicity, more features, lower price',
    'key_desc' => 'ZipBooks was acquired by Divvy, which was later acquired by BILL Holdings, and has seen limited development since. Argo Books offers AI receipt scanning, predictive analytics, and local data storage at a lower price, with active support and development.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'Lower premium price', 'p' => 'ZipBooks\' paid plans start at $' . $zb_smarter . ' CAD/month. Argo Books Premium is $' . $argo_monthly . ' CAD/month, with AI receipt scanning, predictive analytics, and inventory management included.'],
        ['icon' => 'bolt', 'h3' => 'Works offline', 'p' => 'ZipBooks is cloud-only: no internet, no access. Argo Books is a desktop app that works offline, with your data stored locally on your device for full privacy and control.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'ZipBooks was acquired by Divvy, which was later acquired by BILL Holdings, and since the acquisitions, the product appears to have little active development or support. Users have reported issues with bugs, missing features, and difficulty reaching customer service, raising concerns about the long-term viability of the platform.',
        'If you\'re looking for a simple bookkeeping tool that\'s actively maintained, Argo Books offers AI receipt scanning, predictive analytics, inventory management, and local data storage at a lower premium price, plus offline access so you\'re never locked out of your own data.',
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-quickbooks',
        'argo-books-vs-wave',
        'argo-books-vs-freshbooks',
        'argo-books-vs-xero',
        'odoo-accounting-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books really free?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card, no trial period, and no strings attached. The Free plan includes all core features, ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month, and AI receipt scanning.</p>
                            <p>ZipBooks also has a free tier, but it\'s more limited: no AI capabilities, no inventory management, and no offline access.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption, giving you full control and privacy.</p>
                            <p>ZipBooks is cloud-only and requires a constant internet connection to access your data.</p>'],
        ['q_html' => 'How is Argo Books different from ZipBooks?', 'a_html' => '<p>Both are simple, non-accountant-friendly tools with free tiers. Argo Books\' key advantages are local data storage for privacy, offline access, AI receipt scanning, predictive analytics, and inventory management, all at a lower premium price.</p>
                            <p>ZipBooks is cloud-based and focused more on invoicing and time tracking for freelancers.</p>'],
        ['q_html' => 'How does Argo Books pricing compare to ZipBooks?', 'a_html' => '<p>Both offer free plans, but Argo Books\' Free tier is more feature-rich with AI capabilities and inventory management. For paid plans, Argo Books Premium is <strong>$' . $argo_monthly . ' CAD/month</strong> vs ZipBooks Smarter at $' . $zb_smarter . ' CAD/month.</p>
                            <p>You get more features for less with Argo Books.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong>. Because it\'s a desktop app, it\'s fast and responsive, with no browser tabs and no loading spinners.</p>
                            <p>ZipBooks is web-based and accessible from any browser.</p>'],
    ],

    'cta_h2' => 'Ready to try a more capable free option?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
