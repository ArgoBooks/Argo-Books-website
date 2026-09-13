<?php
// compare/data/gnucash-alternatives.php
//
// Content for /compare/gnucash-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/gnucash-alternatives.php.
// GnuCash is free and open source with no paid tier, so there is no
// competitor price to read here. The comparison is usability, not cost.

return [
    'competitor' => 'GnuCash',

    'breadcrumb' => 'GnuCash alternatives',
    'title' => 'GnuCash Alternatives: Local Books, No Double-Entry | Argo Books',
    'meta_description' => 'Looking for a GnuCash alternative? Compare desktop accounting apps that keep your books on your own machine, without needing to know debits from credits.',
    'meta_keywords' => 'GnuCash alternatives, GnuCash alternative, free desktop accounting software, open source accounting alternative, local accounting software, offline bookkeeping',
    'og_title' => 'GnuCash Alternatives: Local Data Without the Learning Curve',
    'og_description' => 'GnuCash is free, local and powerful, and built for people who know double-entry accounting. Here is the alternative that keeps the local data and drops the prerequisite.',

    'hero_h1' => 'GnuCash alternatives',
    'hero_subtitle' => 'GnuCash is free, runs on your machine, and keeps your data yours. It also expects you to already understand double-entry bookkeeping.',

    'differences_h2' => 'What\'s the difference between Argo Books and GnuCash?',
    'differences_desc' => 'GnuCash and Argo Books agree on the important part: accounting software should run on your computer and keep your books on your disk. Where they differ is the starting line. GnuCash was built for people who know accounting; Argo Books is built for people who run a business.',
    'why_h3' => 'What changes when you move from GnuCash to Argo Books',
    'why_list' => [
        '<strong>No accounting knowledge required.</strong> GnuCash opens on a chart of accounts and expects you to know what a credit is. Argo Books asks what you spent and who you paid.',
        '<strong>AI that\'s built in.</strong> Receipt scanning turns a photo into a filed expense, spreadsheet import maps your columns automatically, and predictive analytics forecasts your cash flow. GnuCash has none of these.',
        '<strong>Invoicing people will actually pay.</strong> Modern templates with a card payment link attached, rather than GnuCash\'s plain printed forms.',
        '<strong>Built this decade.</strong> GnuCash\'s interface has changed very little in twenty years. Argo Books is designed around the tasks you do each week.',
        '<strong>Local data on both sides.</strong> You do not have to give up privacy to get usability. Argo Books keeps your books on your machine, encrypted, exactly as GnuCash does.',
    ],

    // Feature, Argo Free, Argo Premium, GnuCash.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Free and open source',
    'table_rows' => [
        ['Expense & revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Invoicing & payments', 'yes', 'yes', 'Basic'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'yes'],
        ['No accounting knowledge required', 'yes', 'yes', 'no'],
        ['Unlimited products', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'no'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Local data storage', 'yes', 'yes', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs GnuCash: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>No accounting jargon</strong>, built for business owners rather than bookkeepers',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux, with your data stored locally',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics',
        '<strong>Modern invoicing</strong> with templates and a payment link your customer can click',
    ],
    'argo_cons' => [
        'Not open source, so you cannot inspect or modify the code the way you can with GnuCash',
        'Less depth for traditional double-entry accounting and advanced ledger work',
        'A newer platform with a smaller community than a twenty-year-old project',
    ],
    'competitor_cons' => [
        '<strong>Steep learning curve</strong>: it assumes you already understand double-entry accounting',
        '<strong>Dated interface</strong> that has changed little in two decades',
        '<strong>No AI</strong> receipt scanning, spreadsheet import or forecasting',
        '<strong>Basic invoicing</strong> with no online payment link, so getting paid is still manual',
    ],
    'competitor_pros' => [
        '<strong>Completely free</strong> and open source, with no tiers and nothing withheld',
        '<strong>Your data stays yours</strong>, on your own machine, like Argo Books',
        'Genuinely powerful double-entry accounting with deep reporting',
        'Twenty years of development and a large, active community',
    ],

    'key_h2' => 'Keep the local data, drop the prerequisite',
    'key_desc' => 'GnuCash proved that people want accounting software that runs on their own computer. Argo Books agrees, and removes the part where you have to learn double-entry bookkeeping before you can record your first expense.',
    'key_cards' => [
        ['icon' => 'users', 'h3' => 'Built for owners, not accountants', 'p' => 'GnuCash is organised around accounts, ledgers and journal entries. Argo Books is organised around invoices, expenses, receipts and stock, in the language you already use.'],
        ['icon' => 'bolt', 'h3' => 'AI that does the typing', 'p' => 'Receipt scanning, spreadsheet import and cash flow forecasting are included. GnuCash has no AI features, so every transaction is typed by hand.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'GnuCash deserves real respect. It is free, open source, genuinely powerful, and it has kept people\'s books on their own machines for over twenty years. If you understand double-entry accounting and want software you can inspect and modify, nothing here beats it, and we would rather say that than pretend otherwise.',
        'The barrier is the first hour. GnuCash opens expecting you to set up a chart of accounts and understand debits and credits, and most business owners never get past that. Argo Books takes the same principle, your books on your own computer, and builds it so you can record an expense without learning accounting first.',
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-quickbooks',
        'argo-books-vs-wave',
        'argo-books-vs-freshbooks',
        'manager-io-alternatives',
        'argo-books-vs-xero',
    ],

    'faqs' => [
        ['q_html' => 'Is GnuCash free?', 'a_html' => '<p>Yes. GnuCash is free and open source under the GPL, with no paid tier and no features withheld. Argo Books also has a free tier, with Premium at $' . $argo_monthly . ' CAD/month.</p>
                            <p>This comparison is not about price. It is about how much accounting knowledge each one expects before you can use it.</p>'],
        ['q_html' => 'Do both keep my data on my own computer?', 'a_html' => '<p>Yes. This is what the two have in common. Both are desktop applications that store your books locally rather than on someone else\'s servers.</p>
                            <p>Argo Books encrypts your local data with AES-256.</p>'],
        ['q_html' => 'Do I need accounting knowledge to use Argo Books?', 'a_html' => '<p>No. Argo Books uses guided forms and plain language: the amount, the category, who it was with. You do not need to know debits from credits.</p>
                            <p>GnuCash is built around double-entry accounting and expects you to set up a chart of accounts before you begin.</p>'],
        ['q_html' => 'What does Argo Books have that GnuCash does not?', 'a_html' => '<p>AI receipt scanning, AI spreadsheet import, predictive cash flow analytics, inventory management, and modern invoicing with an online payment link.</p>
                            <p>GnuCash has stronger double-entry depth and the advantage of being open source.</p>'],
        ['q_html' => 'Can I move my GnuCash data into Argo Books?', 'a_html' => '<p>Yes. Export your records from GnuCash to CSV and use Argo Books\' AI spreadsheet import, which works out which column is which rather than asking you to map them by hand.</p>'],
    ],

    'cta_h2' => 'Ready for local books without the learning curve?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
