<?php
// compare/data/manager-io-alternatives.php
//
// Content for /compare/manager-io-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/manager-io-alternatives.php.
$mgr_cloud = competitor_price('manager', 'cloud'); // 83 CAD (59 USD converted), desktop edition is free

return [
    'competitor' => 'Manager.io',

    'breadcrumb' => 'Manager.io alternatives',
    'title' => 'Manager.io Alternatives: Desktop Accounting Compared | Argo Books',
    'meta_description' => 'Manager.io alternatives for local, offline bookkeeping. Compare desktop accounting apps on ease of use, AI features and invoicing, with your data staying on your machine.',
    'meta_keywords' => 'Manager.io alternatives, Manager.io alternative, free desktop accounting software, local accounting software, offline bookkeeping, desktop accounting app',
    'og_title' => 'Manager.io Alternatives: Local Books Without the Learning Curve',
    'og_description' => 'Manager.io keeps your data on your machine and expects you to know double-entry. Here is the alternative that keeps the local data and drops the prerequisite.',

    'hero_h1' => 'Manager.io alternatives',
    'hero_subtitle' => 'Both run on your machine and keep your data local. Argo Books assumes you are a business owner, not a bookkeeper.',

    'differences_h2' => 'What\'s the difference between Argo Books and Manager.io?',
    'differences_desc' => 'Manager.io is the closest thing to Argo Books in philosophy: a real desktop application, free to download, with your data on your own disk. Where they part company is the audience. Manager.io is built around traditional double-entry accounting; Argo Books is built for the person running the business.',
    'why_h3' => 'What changes when you move from Manager.io to Argo Books',
    'why_list' => [
        '<strong>No accounting knowledge required.</strong> Manager.io expects you to understand double-entry, chart of accounts and journal entries. Argo Books asks what you spent and who you paid.',
        '<strong>AI that\'s built in.</strong> Receipt scanning turns a photo into a filed expense, spreadsheet import maps your columns automatically, and predictive analytics forecasts your cash flow. Manager.io has none of these.',
        '<strong>Cloud access without $' . $mgr_cloud . ' CAD a month.</strong> Manager.io\'s cloud edition is $' . $mgr_cloud . ' CAD/month. Argo Books Premium is $' . $argo_monthly . ' CAD/month.',
        '<strong>Modern interface.</strong> Argo Books is built to look and behave like software from this decade rather than a forms-and-tables admin panel.',
        '<strong>Local data on both sides.</strong> You do not have to give up privacy to get usability. Argo Books keeps your books on your machine, encrypted, exactly as Manager.io does.',
    ],

    // Feature, Argo Free, Argo Premium, Manager.io.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Desktop: Free',
    'table_rows' => [
        ['Expense & revenue tracking', 'yes', 'yes', 'yes'],
        ['Financial reports', 'yes', 'yes', 'yes'],
        ['Invoicing & payments', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'yes'],
        ['No accounting knowledge required', 'yes', 'yes', 'no'],
        ['Unlimited products', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'yes'],
        ['AI receipt scanning', 'yes', 'yes', 'no'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Local data storage', 'yes', 'yes', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs Manager.io: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>No accounting jargon</strong>, built for business owners rather than bookkeepers',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux, with your data stored locally',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics',
        '<strong>Modern interface</strong> designed around the tasks you actually do each week',
    ],
    'argo_cons' => [
        'Less depth for traditional accountants than a full double-entry system',
        'A newer platform with a smaller ecosystem than longer-established tools',
        'Fewer country-specific tax modules than Manager.io\'s extensive list',
    ],
    'competitor_cons' => [
        '<strong>Steep learning curve</strong>, built around double-entry accounting and journal entries',
        '<strong>No AI</strong> receipt scanning, spreadsheet import or forecasting',
        '<strong>Dated interface</strong> that reads as an admin panel rather than an app',
        '<strong>Cloud edition is $' . $mgr_cloud . ' CAD/month</strong>, well above Argo Books Premium',
    ],
    'competitor_pros' => [
        '<strong>Genuinely free</strong> desktop edition with no feature limits',
        'Full double-entry accounting with real depth for those who want it',
        'Your data stays on your own machine, like Argo Books',
        'Broad international tax and multi-currency support',
    ],

    'key_h2' => 'Local data without the accounting degree',
    'key_desc' => 'Manager.io proves people want desktop accounting that keeps data local. Argo Books agrees with that and takes out the part where you have to learn double-entry bookkeeping first.',
    'key_cards' => [
        ['icon' => 'users', 'h3' => 'Built for owners, not bookkeepers', 'p' => 'Manager.io is organised around accounts, journals and ledgers. Argo Books is organised around invoices, expenses, receipts and stock, in the language you already use.'],
        ['icon' => 'bolt', 'h3' => 'AI that does the typing', 'p' => 'Receipt scanning, spreadsheet import and cash flow forecasting are included. Manager.io has no AI features at all.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'Manager.io deserves credit. It is free, genuinely capable, keeps your data on your own machine, and its international tax support is broader than ours. If you are comfortable with double-entry accounting and want maximum depth at no cost, it is a legitimate choice and we would rather say so than pretend otherwise.',
        'The catch is who it is for. It expects you to understand chart of accounts and journal entries, and its interface has not moved on in years. Argo Books takes the same principle, your books on your own computer, and builds it for someone who runs a business rather than someone who trained in accounting, with AI doing the data entry.',
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
        ['q_html' => 'Is Manager.io free?', 'a_html' => '<p>Yes. Manager.io\'s desktop edition is a free download with no feature limits. Their cloud edition is $' . $mgr_cloud . ' CAD/month for remote and multi-user access.</p>
                            <p>Argo Books also has a free tier, with Premium at $' . $argo_monthly . ' CAD/month.</p>'],
        ['q_html' => 'Do both keep my data on my own computer?', 'a_html' => '<p>Yes. This is the main thing the two have in common. Both are desktop applications that store your books locally rather than on someone else\'s servers.</p>
                            <p>Argo Books encrypts your local data with AES-256.</p>'],
        ['q_html' => 'Do I need accounting knowledge to use Argo Books?', 'a_html' => '<p>No. Argo Books uses guided forms and plain language: the amount, the category, who it was with. You do not need to know debits from credits.</p>
                            <p>Manager.io is built around traditional double-entry accounting and expects familiarity with journals and a chart of accounts.</p>'],
        ['q_html' => 'What does Argo Books have that Manager.io does not?', 'a_html' => '<p>AI receipt scanning, AI spreadsheet import and predictive cash flow analytics, none of which Manager.io offers.</p>
                            <p>Argo Books also has a more modern interface built around everyday tasks rather than accounting structures.</p>'],
        ['q_html' => 'What platforms does Argo Books run on?', 'a_html' => '<p>Argo Books runs natively on Windows, macOS, and Linux, like Manager.io\'s desktop edition.</p>'],
    ],

    'cta_h2' => 'Ready for local books without the learning curve?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
