<?php
// compare/data/honeybook-alternatives.php
//
// Content for /compare/honeybook-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/honeybook-alternatives.php.
$argo_yearly   = (int) $pricing['premium_yearly_price'];
$hb_starter    = competitor_price('honeybook', 'starter');    // 40
$hb_essentials = competitor_price('honeybook', 'essentials'); // 67
$hb_premium    = competitor_price('honeybook', 'premium');    // 149

return [
    'competitor' => 'HoneyBook',

    'breadcrumb' => 'HoneyBook alternatives',
    'title' => 'HoneyBook Alternatives That Also Do Your Books | Argo Books',
    'meta_description' => 'HoneyBook alternatives that handle your actual bookkeeping, not just client flow and invoices. Compare expense tracking, reporting and invoicing in one place.',
    'meta_keywords' => 'HoneyBook alternatives, HoneyBook alternative, client management alternative, freelance bookkeeping software, invoicing and accounting software',
    'og_title' => 'HoneyBook Alternatives That Keep Your Actual Books',
    'og_description' => 'HoneyBook books clients and sends invoices. Here are the alternatives that also track expenses, stock and profit.',

    'hero_h1' => 'HoneyBook alternatives',
    'hero_subtitle' => 'HoneyBook runs your client pipeline and gets you paid. Argo Books actually keeps your books. See where each one fits, and why Argo does the books and the invoicing in one app.',

    'differences_h2' => 'What\'s the difference between Argo Books and HoneyBook?',
    'differences_desc' => 'They solve different problems. HoneyBook is a client-flow and CRM platform for service solopreneurs: proposals, contracts, scheduling, a client portal, lead forms, invoicing, and payments. Argo Books is bookkeeping: expense and revenue tracking, financial reports, inventory, and invoicing in one app. Many HoneyBook users still run separate accounting; Argo does the books and the invoicing together.',
    'why_h3' => 'What changes when you move from HoneyBook to Argo Books',
    'why_list' => [
        '<strong>It actually keeps your books.</strong> Invoicing, expenses, receipts, inventory, and reports in one clean app. HoneyBook isn\'t accounting software, so with it you\'d still need a separate tool for your books.',
        '<strong>A genuinely free plan.</strong> All the core features forever, no trial and no credit card. HoneyBook has no free plan, just a 7-day trial.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your books open instantly and keep working with no internet, while HoneyBook is cloud-only.',
        '<strong>AI built into your books.</strong> Receipt scanning, spreadsheet import, and predictive analytics come included, aimed at your bookkeeping rather than your client pipeline.',
        '<strong>One predictable price.</strong> Everything in Premium for $' . $argo_monthly . ' CAD/month. No per-client fees, and no HoneyBook-style $' . $hb_starter . '+ CAD/month floor.',
    ],

    // Feature, Argo Free, Argo Premium, HoneyBook.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Starter: $' . $hb_starter . ' CAD/month',
    'table_rows' => [
        ['Expense &amp; revenue tracking (bookkeeping)', 'yes', 'yes', 'no'],
        ['Financial reports (P&amp;L, balance sheet)', 'yes', 'yes', 'no'],
        ['Invoicing &amp; payments', 'yes', 'yes', 'yes'],
        ['Proposals &amp; contracts', 'no', 'no', 'yes'],
        ['Client scheduling &amp; calendar', 'no', 'no', 'yes'],
        ['Client portal', 'no', 'no', 'yes'],
        ['Inventory management', 'yes', 'yes', 'no'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'no'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
    ],

    'pros_cons_h2' => 'Argo Books vs HoneyBook: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>Real bookkeeping and invoicing in one app</strong>, so you\'re not stitching together separate tools',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux',
        '<strong>AI included</strong>: receipt scanning, spreadsheet import, and predictive analytics',
        '<strong>One flat price</strong>, Premium is $' . $argo_monthly . ' CAD/month with no per-client fees',
    ],
    'argo_cons' => [
        'No proposals or contracts, so HoneyBook is the better fit if you send those to book clients',
        'No client scheduling or calendar built in',
        'No client portal or lead-capture forms; Argo keeps your books, it isn\'t a client CRM',
    ],
    'competitor_cons' => [
        '<strong>No free plan</strong> and pricey: about $' . $hb_starter . ' to $' . $hb_premium . ' CAD/month',
        '<strong>Not accounting software</strong>, so you\'ll still need separate books for expenses and reports',
        '<strong>Cloud-only</strong>, no offline desktop access to your data',
    ],
    'competitor_pros' => [
        'Excellent client flow: proposals, contracts, and scheduling in one place',
        'Client portal plus lead-capture forms to bring new work in',
        'Built to book clients and get paid, with HoneyBook AI to help along the way',
    ],

    'key_h2' => 'Your actual books, not just the client flow',
    'key_desc' => 'Both tools help small businesses get paid, but they focus on different things. HoneyBook shines at client flow: proposals, contracts, and scheduling. Argo Books focuses on your actual books, offline access, and inventory.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'More affordable', 'p' => 'HoneyBook has no free plan and runs about $' . $hb_starter . ' to $' . $hb_premium . ' CAD/month. Argo Books has a free version with core features, and Premium is a fraction of the cost.'],
        ['icon' => 'bolt', 'h3' => 'Actually your books', 'p' => 'HoneyBook manages clients and invoices, then hands off to QuickBooks for the accounting. Argo Books keeps the books itself, invoicing included, so it\'s one tool instead of two.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'HoneyBook excels at running a client pipeline: proposals, contracts, scheduling, a client portal, and lead forms, all built to book clients and get you paid. If that client flow is your core need, HoneyBook is a genuinely strong tool.',
        'But HoneyBook isn\'t accounting software, so you\'ll still need something for your actual books. If you want expense tracking, financial reports, inventory, and invoicing in one app, without paying $' . $hb_starter . '+ CAD/month for a tool that then hands off to QuickBooks, Argo Books is built for you.',
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-quickbooks',
        'argo-books-vs-wave',
        'argo-books-vs-freshbooks',
        'argo-books-vs-xero',
        'zipbooks-alternatives',
        'odoo-accounting-alternatives',
        'sage-50-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books really free?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card, no trial period, and no strings attached. The Free plan includes all core features, ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month, and AI receipt scanning.</p>
                            <p>HoneyBook has no free plan, only a 7-day trial, and paid plans start at $' . $hb_starter . ' CAD/month.</p>'],
        ['q_html' => 'Is HoneyBook accounting software?', 'a_html' => '<p>Not really. HoneyBook is a client-flow and CRM platform for service solopreneurs: proposals, contracts, scheduling, a client portal, lead forms, invoicing, and payments. It doesn\'t do real bookkeeping, no expense tracking, no financial statements, no inventory.</p>
                            <p>In fact, HoneyBook integrates with QuickBooks Online to handle the actual accounting. Argo Books is your actual books, with expense and revenue tracking, financial reports, and invoicing in one app.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption, giving you full control and privacy.</p>
                            <p>HoneyBook is cloud-only, with a mobile app, and requires a constant internet connection to access your data.</p>'],
        ['q_html' => 'How does Argo Books pricing compare to HoneyBook?', 'a_html' => '<p>Argo Books is far more affordable, and there\'s no per-client fee. The Free plan covers most small business needs at no cost. Premium is just <strong>$' . $argo_monthly . ' CAD/month</strong> (or $' . $argo_yearly . '/year). HoneyBook has no free plan and runs about $' . $hb_starter . ' to $' . $hb_premium . ' CAD/month across its Starter, Essentials, and Premium tiers.</p>
                            <p>And because HoneyBook isn\'t accounting software, many users still pay for separate books on top.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on <strong>Windows</strong> and <strong>Linux</strong>. Because it\'s a desktop app, it\'s fast and responsive, with no browser tabs and no loading spinners.</p>
                            <p>HoneyBook is web-based and also has a mobile app for iOS and Android.</p>'],
    ],

    'cta_h2' => 'Ready to keep your books in one place?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
