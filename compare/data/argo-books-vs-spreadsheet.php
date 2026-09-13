<?php
// compare/data/argo-books-vs-spreadsheet.php
//
// Content for /compare/argo-books-vs-spreadsheet/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/argo-books-vs-spreadsheet.php.
// No competitor price here. Most people already own Excel or use Google
// Sheets free, so the honest cost of a spreadsheet is time, not licensing.

return [
    'competitor' => 'Spreadsheets',

    'breadcrumb' => 'Argo Books vs Spreadsheets',
    'title' => 'Argo Books vs Spreadsheets: When Excel Stops Being Enough | Argo Books',
    'meta_description' => 'Argo Books vs spreadsheets: when Excel or Google Sheets stops being enough for bookkeeping, and what you gain by moving. Import your existing spreadsheet in one go.',
    'meta_keywords' => 'accounting software vs Excel, Excel bookkeeping alternative, replace spreadsheet accounting, Google Sheets bookkeeping, small business accounting software, spreadsheet import accounting',
    'og_title' => 'Argo Books vs Spreadsheets: When Excel Stops Being Enough',
    'og_description' => 'Spreadsheets are free and flexible until they are not. See what changes when your books stop being a file you maintain by hand.',

    'hero_h1' => 'Argo Books vs Spreadsheets',
    'hero_subtitle' => 'A spreadsheet is the right answer right up until it isn\'t. Bring yours across in one go and keep the history.',

    'differences_h2' => 'What\'s the difference between Argo Books and Spreadsheets?',
    'differences_desc' => 'Almost everyone starts in a spreadsheet, and for a while that is the correct choice: it is free, you already know it, and it does exactly what you tell it. The trouble starts when the file gets long enough that you stop trusting it, and when a typed formula quietly changes a number you already reported.',
    'why_h3' => 'What changes when you move from spreadsheets to Argo Books',
    'why_list' => [
        '<strong>Nothing to maintain by hand.</strong> No formulas to drag down, no ranges to extend, no column that silently stops being included in the total.',
        '<strong>It checks before it saves.</strong> Guided forms enforce the fields and verify the amounts, so a transaction cannot land half finished or in the wrong column.',
        '<strong>Receipts stop being a separate pile.</strong> Photograph a receipt and the expense writes itself with the line items attached, rather than being typed in and filed somewhere else.',
        '<strong>Reports you don\'t build.</strong> Profit and loss, balance sheet and expense summaries generate from your records, rather than being a second sheet you maintain.',
        '<strong>Bring the history with you.</strong> AI spreadsheet import reads your existing file, works out which column is which, and shows you what it will create before anything is saved.',
    ],

    // Feature, Argo Free, Argo Premium, Spreadsheets.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Excel, Sheets or LibreOffice',
    'table_rows' => [
        ['Expense & revenue tracking', 'yes', 'yes', 'Manual'],
        ['Financial reports', 'yes', 'yes', 'Manual'],
        ['Invoicing & payments', 'yes', 'yes', 'Manual'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'yes'],
        ['No accounting knowledge required', 'yes', 'yes', 'yes'],
        ['Unlimited products', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'Manual'],
        ['AI receipt scanning', 'yes', 'yes', 'no'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Local data storage', 'yes', 'yes', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs Spreadsheets: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>Nothing breaks when you add a row</strong>: no formulas, ranges or references to maintain',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux, with your data stored locally, exactly like a file on your disk',
        '<strong>AI built in</strong>: receipt scanning, spreadsheet import, and predictive analytics',
        '<strong>Reports generate themselves</strong> from your records rather than being a sheet you build',
    ],
    'argo_cons' => [
        'Less flexible than a blank grid: it does bookkeeping, not arbitrary calculation',
        'You work within its structure rather than inventing your own layout',
        'A newer platform with a smaller ecosystem than the world\'s most-used software',
    ],
    'competitor_cons' => [
        '<strong>Everything is manual</strong>: every total, category and report is a formula you maintain',
        '<strong>Silent errors</strong>: a mistyped range or an overwritten formula changes a number without telling you',
        '<strong>No receipts attached</strong>, so proof of an expense lives somewhere else entirely',
        '<strong>No forecasting or stock tracking</strong>, and reporting means building another sheet by hand',
    ],
    'competitor_pros' => [
        '<strong>Free</strong>, or already paid for as part of software you own',
        'Completely flexible: it does exactly what you tell it to',
        'You already know how to use it, with no new software to learn',
        'Perfectly adequate for a handful of transactions a month',
    ],

    'key_h2' => 'Keep the spreadsheet habit, lose the spreadsheet work',
    'key_desc' => 'Nobody should move off a spreadsheet before they need to. The signal is when you stop trusting the file: when you check a total twice, or find a formula that stopped covering the last few rows.',
    'key_cards' => [
        ['icon' => 'document-upload', 'h3' => 'Bring your file across', 'p' => 'AI spreadsheet import reads your Excel or CSV file, works out which column holds the date, the amount and the supplier, and previews everything before saving. Years of history move in one go.'],
        ['icon' => 'check', 'h3' => 'It checks your work', 'p' => 'Guided forms enforce required fields and verify amounts, so nothing saves half finished. A spreadsheet lets you type anything into any cell.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'A spreadsheet is genuinely the right tool at the start. It is free, infinitely flexible, and you already know how to use it. If you record a handful of transactions a month and your reporting need is a single total at year end, moving to accounting software would be overhead for no gain, and we would rather you kept the spreadsheet.',
        'The moment it stops working is usually not dramatic. It is the day you check a total twice because you are not sure, or find that a formula stopped covering the last twenty rows. At that point the file has become something you maintain instead of something that helps you. Argo Books imports it, keeps the history, and takes over the maintenance.',
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
        ['q_html' => 'Can I import my existing spreadsheet?', 'a_html' => '<p>Yes. Argo Books has AI spreadsheet import: drop in your Excel or CSV file and it works out which column holds the date, the amount, the supplier and the rest, whatever you happened to call them.</p>
                            <p>You see exactly what will be created, with anything questionable flagged, and nothing is saved until you approve it.</p>'],
        ['q_html' => 'Is my spreadsheet layout a problem?', 'a_html' => '<p>Usually not. The import is built for real files rather than tidy ones: merged cells, a title row above the headings, inconsistent date formats and blank rows in the middle are all handled.</p>'],
        ['q_html' => 'When should I move off a spreadsheet?', 'a_html' => '<p>When you stop trusting it. The usual signals are checking a total twice, finding a formula that stopped covering recent rows, or realising you cannot answer a question without building another sheet.</p>
                            <p>If you record a few transactions a month and never need a report, a spreadsheet is still the right answer.</p>'],
        ['q_html' => 'Is Argo Books harder than a spreadsheet?', 'a_html' => '<p>It is different rather than harder. You fill in guided forms instead of typing into cells, and the totals, categories and reports are produced for you rather than maintained by you.</p>
                            <p>No accounting knowledge is required.</p>'],
        ['q_html' => 'Does Argo Books keep my data on my computer like a spreadsheet file?', 'a_html' => '<p>Yes. Argo Books is a desktop application and your books are stored locally on your own machine, encrypted with AES-256. You can back them up or move them like any other file.</p>'],
    ],

    'cta_h2' => 'Ready to stop maintaining the file?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
