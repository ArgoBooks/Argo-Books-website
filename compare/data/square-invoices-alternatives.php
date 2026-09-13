<?php
// compare/data/square-invoices-alternatives.php
//
// Content for /compare/square-invoices-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/square-invoices-alternatives.php.
$sq_plus = competitor_price('square-invoices', 'plus'); // 30 CAD

return [
    'competitor' => 'Square Invoices',

    'breadcrumb' => 'Square Invoices alternatives',
    'title' => 'Square Invoices Alternatives: Invoicing Plus Books | Argo Books',
    'meta_description' => 'Square Invoices alternatives that also track expenses, stock and reports. Compare free invoicing options that grow into full small business accounting.',
    'meta_keywords' => 'Square Invoices alternatives, Square Invoices alternative, free invoicing software, invoicing and accounting software, small business invoicing',
    'og_title' => 'Square Invoices Alternatives: Billing Plus the Books',
    'og_description' => 'Square Invoices is free and stops at billing. Here are the alternatives that also track expenses, stock and reports.',

    'hero_h1' => 'Square Invoices alternatives',
    'hero_subtitle' => 'Square Invoices is genuinely free for unlimited invoicing. The question is not price, it\'s whether invoicing alone is enough.',

    'differences_h2' => 'What\'s the difference between Argo Books and Square Invoices?',
    'differences_desc' => 'This one is not about cost. Square Invoices is free and does not cap your invoices, and we are not going to pretend otherwise. The difference is scope: Square bills your customers, Argo Books keeps your books, and only one of the two works without an internet connection.',
    'why_h3' => 'What changes when you move from Square Invoices to Argo Books',
    'why_list' => [
        '<strong>Your whole books, not just billing.</strong> Expenses, receipts, inventory, reports and forecasting are built in, where Square Invoices covers the invoice and the payment.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your records open instantly with no internet, and your data stays on your machine rather than on Square\'s servers.',
        '<strong>AI that\'s built in.</strong> Receipt scanning turns a photo into a filed expense, and spreadsheet import brings your history across in one go.',
        '<strong>You are not tied to one processor.</strong> Argo Books connects to your own Stripe, PayPal or Square account, so you keep your rates and your relationship.',
        '<strong>Free to start, and honest about it.</strong> Both are free. Argo\'s free tier includes AI receipt scanning and inventory, which invoicing tools generally do not.',
    ],

    // Feature, Argo Free, Argo Premium, Square Invoices.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Free, unlimited invoices',
    'table_rows' => [
        ['Expense & revenue tracking', 'yes', 'yes', 'no'],
        ['Financial reports', 'yes', 'yes', 'no'],
        ['Invoicing & payments', 'yes', 'yes', 'yes'],
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

    'pros_cons_h2' => 'Argo Books vs Square Invoices: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>Full bookkeeping</strong>: expenses, revenue, inventory, reports and forecasting',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux, with your data stored locally',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics',
        '<strong>Bring your own processor</strong>, including Square itself, so you keep your own rates',
    ],
    'argo_cons' => [
        'Desktop-first, so there\'s no browser or mobile-web access the way a cloud tool offers',
        'No point-of-sale hardware, which is Square\'s core strength',
        'A newer platform with a smaller ecosystem than longer-established tools',
    ],
    'competitor_cons' => [
        '<strong>Invoicing only</strong>: no expense tracking, no inventory, no financial reports',
        '<strong>Cloud-only</strong>, so no internet means no access to your invoices',
        '<strong>Tied to Square processing</strong> rather than letting you choose a provider',
        '<strong>No AI</strong> receipt scanning, spreadsheet import or forecasting',
    ],
    'competitor_pros' => [
        '<strong>Genuinely free</strong> with unlimited invoices, estimates and contracts',
        'Unlimited users and customers on the free tier',
        'Excellent if you already use Square for point of sale',
        'Strong mobile apps and instant payment acceptance',
    ],

    'key_h2' => 'Free either way, so compare what you get',
    'key_desc' => 'Square Invoices costs nothing and sends unlimited invoices, so the honest comparison is about scope. Argo Books is also free to start, and covers the bookkeeping that has to happen after the invoice is paid.',
    'key_cards' => [
        ['icon' => 'document', 'h3' => 'Books, not just invoices', 'p' => 'Square Invoices ends at the payment. Argo Books tracks the expense side, scans receipts, manages stock, and produces the reports your accountant asks for.'],
        ['icon' => 'bolt', 'h3' => 'Works offline', 'p' => 'Square Invoices is cloud-only. Argo Books is a desktop app that works without a connection, with your data stored locally on your device.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'Square Invoices is a strong free product, and we are not going to invent a price gap that does not exist. If you already run Square for point of sale, unlimited free invoicing inside the same account is hard to argue with, and their mobile apps and payment hardware are better than anything a desktop bookkeeping tool will offer you.',
        'What it does not do is keep your books. There is no expense tracking, no inventory, no financial reporting, and nothing works without a connection. If invoicing is genuinely all you need, use Square. If you also need to know what you spent, what you hold, and whether you made money, that is the gap Argo Books fills, and it is free to start too.',
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
        ['q_html' => 'Is Square Invoices really free?', 'a_html' => '<p>Yes. Square Invoices has a free tier with unlimited invoices, estimates and contracts, unlimited users and unlimited customers. Payment processing fees apply when a customer pays by card.</p>
                            <p>Their Plus plan is $' . $sq_plus . ' CAD/month and adds custom templates, milestone payment schedules and project tracking.</p>'],
        ['q_html' => 'So why use Argo Books instead?', 'a_html' => '<p>Because invoicing is one part of running the books. Argo Books adds expense and revenue tracking, AI receipt scanning, inventory management, financial reports and predictive analytics.</p>
                            <p>It also works offline and keeps your data on your own computer, which Square Invoices does not do.</p>'],
        ['q_html' => 'Can I still take card payments with Argo Books?', 'a_html' => '<p>Yes. Argo Books connects to your own Stripe, PayPal or Square account, so you keep your existing rates and payout schedule rather than being tied to one processor.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption.</p>
                            <p>Square Invoices is cloud-based and needs a connection.</p>'],
        ['q_html' => 'Is Argo Books free as well?', 'a_html' => '<p>Yes. Argo Books has a free tier you can use forever, with no credit card and no trial period. It includes ' . (int) $pricing['free_invoice_monthly_limit'] . ' invoices a month plus AI receipt scanning and inventory.</p>
                            <p>Premium is $' . $argo_monthly . ' CAD/month and adds predictive analytics, higher limits and biometric login.</p>'],
    ],

    'cta_h2' => 'Ready for the rest of your books?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
