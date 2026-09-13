<?php
// compare/data/invoice2go-alternatives.php
//
// Content for /compare/invoice2go-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/invoice2go-alternatives.php.
$i2g_starter      = competitor_price('invoice2go', 'starter');      // 11 CAD (7.99 USD converted), 2 invoices/mo
$i2g_professional = competitor_price('invoice2go', 'professional'); // 17 CAD (11.99 USD converted), 5 invoices/mo
$i2g_premium      = competitor_price('invoice2go', 'premium');      // 63 CAD (44.99 USD converted), unlimited

return [
    'competitor' => 'Invoice2Go',

    'breadcrumb' => 'Invoice2Go alternatives',
    'title' => 'Invoice2Go Alternatives With No Invoice Limits | Argo Books',
    'meta_description' => 'Invoice2Go alternatives without monthly invoice caps. Compare invoicing apps that also track expenses, inventory and profit rather than just billing.',
    'meta_keywords' => 'Invoice2Go alternatives, Invoice2Go alternative, unlimited invoicing software, invoicing app for small business, free invoicing software',
    'og_title' => 'Invoice2Go Alternatives Without the Invoice Caps',
    'og_description' => 'Invoice2Go caps how many invoices you send and stops at billing. Here are the alternatives with no caps and the whole books.',

    'hero_h1' => 'Invoice2Go alternatives',
    'hero_subtitle' => 'Invoice2Go\'s cheapest plan allows two invoices a month. Argo Books gives you full bookkeeping, offline, for less than their unlimited tier.',

    'differences_h2' => 'What\'s the difference between Argo Books and Invoice2Go?',
    'differences_desc' => 'Invoice2Go is an invoicing app. Argo Books is the whole set of books, with invoicing inside it. The other difference is the caps: Invoice2Go\'s cheaper tiers limit how many invoices you can send, so the plan that actually compares to Argo is their most expensive one.',
    'why_h3' => 'What changes when you move from Invoice2Go to Argo Books',
    'why_list' => [
        '<strong>No invoice caps to work around.</strong> Invoice2Go Starter allows 2 invoices a month and Professional allows 5. Argo Books Premium does not cap invoices at all.',
        '<strong>Your actual books, not just invoicing.</strong> Expenses, receipts, inventory, reports and forecasting are all included, where Invoice2Go stops at billing.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your books open instantly with no internet, and your data stays on your machine.',
        '<strong>AI that\'s built in.</strong> Receipt scanning, spreadsheet import, and predictive analytics come included rather than as an upsell.',
        '<strong>One predictable price in CAD.</strong> Invoice2Go publishes in USD. The figures here are converted to CAD so the comparison is like for like.',
    ],

    // Feature, Argo Free, Argo Premium, Invoice2Go.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Premium: $' . $i2g_premium . ' CAD/month',
    'table_rows' => [
        ['Expense & revenue tracking', 'yes', 'yes', 'no'],
        ['Financial reports', 'yes', 'yes', 'Limited'],
        ['Invoicing & payments', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['No accounting knowledge required', 'yes', 'yes', 'yes'],
        ['Unlimited products', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'yes'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Local data storage', 'yes', 'yes', 'no'],
    ],

    'pros_cons_h2' => 'Argo Books vs Invoice2Go: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>No invoice caps</strong> on Premium, so billing volume never forces an upgrade',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux, with your data stored locally',
        '<strong>Full bookkeeping</strong>: expenses, inventory, reports and forecasting, not just invoices',
        '<strong>Priced in CAD</strong> at $' . $argo_monthly . '/month, so the amount never moves with the exchange rate',
    ],
    'argo_cons' => [
        'Desktop-first, so there\'s no browser or mobile-web access the way a cloud tool offers',
        'A newer platform with a smaller ecosystem than longer-established tools',
    ],
    'competitor_cons' => [
        '<strong>Invoice caps on cheaper plans</strong>: 2 a month on Starter, 5 on Professional',
        '<strong>Invoicing only</strong>, with no inventory, no forecasting, and limited bookkeeping',
        '<strong>Cloud-only</strong>, so no internet means no access to your invoices',
        '<strong>Priced in USD</strong>, so what you actually pay moves with the exchange rate',
    ],
    'competitor_pros' => [
        'Strong mobile apps for invoicing on the move',
        'Card payment processing built into the invoice',
        'Long-established with a large existing user base',
    ],

    'key_h2' => 'Unlimited invoicing without the unlimited price',
    'key_desc' => 'Invoice2Go\'s plan ladder is built around how many invoices you send. Argo Books charges one flat price and does not meter your billing, then adds the bookkeeping Invoice2Go leaves out.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'No caps, lower price', 'p' => 'Invoice2Go\'s unlimited tier is $' . $i2g_premium . ' CAD/month. Argo Books Premium is $' . $argo_monthly . ' CAD/month with no invoice cap, plus receipt scanning, inventory and forecasting.'],
        ['icon' => 'bolt', 'h3' => 'Works offline', 'p' => 'Invoice2Go is cloud-only. Argo Books is a desktop app that works without a connection, with your data stored locally on your device.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'Invoice2Go does invoicing well, and its mobile apps are genuinely good if billing from a phone is the main thing you need. If you only send a handful of invoices and never want to think about expenses, receipts or reports, it may be enough on its own.',
        'The trap is the plan ladder. The tiers most people look at first cap you at two or five invoices a month, so the realistic comparison is their unlimited tier, which is their most expensive. Argo Books is cheaper than that, does not cap invoices, and keeps your whole books rather than just the billing.',
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
        ['q_html' => 'How many invoices can I send with Argo Books?', 'a_html' => '<p>Argo Books Premium does not cap the number of invoices you send. The Free plan includes ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices per month.</p>
                            <p>Invoice2Go caps invoices on its cheaper plans: 2 per month on Starter and 5 per month on Professional. Only their Premium tier is unlimited.</p>'],
        ['q_html' => 'Is Argo Books cheaper than Invoice2Go?', 'a_html' => '<p>Argo Books Premium is $' . $argo_monthly . ' CAD/month. Invoice2Go Premium, the only tier without an invoice cap, is $' . $i2g_premium . ' CAD/month.</p>
                            <p>Invoice2Go publishes in US dollars, so the CAD figure here is converted at the current rate and moves with it.</p>'],
        ['q_html' => 'Does Argo Books do more than invoicing?', 'a_html' => '<p>Yes. Argo Books is full bookkeeping software: expenses, revenue, AI receipt scanning, inventory management, financial reports and predictive analytics, with invoicing as one part of it.</p>
                            <p>Invoice2Go is focused on invoicing and payments rather than keeping your books.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption.</p>
                            <p>Invoice2Go is cloud-based and needs a connection to reach your invoices.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on Windows, macOS, and Linux. Because it\'s a desktop app, it\'s fast and responsive, with no browser tabs and no loading spinners.</p>
                            <p>Invoice2Go is web and mobile based.</p>'],
    ],

    'cta_h2' => 'Ready to invoice without a cap?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
