<?php
// features/data/spreadsheet-import.php
//
// Content for /features/spreadsheet-import/. Layout lives in
// features/feature-page.php.
//
// The plan limits below read from $pricing. A PHP tag inside a quoted string never evaluates, so write the value, not the tag.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'breadcrumb' => 'Spreadsheet Import',
    'title' => 'Spreadsheet Import | Argo Books',
    'meta_description' => 'Drop an Excel or CSV file into Argo Books and your customers, products, invoices, and expenses are mapped and imported for you, with no manual setup.',
    'meta_keywords' => 'spreadsheet import, CSV import software, Excel import tool, automatic column mapping, data migration tool, bulk data import, spreadsheet to accounting, business data import, Excel to bookkeeping',
    'og_title' => 'Spreadsheet Import | Argo Books',
    'og_description' => 'Drop a spreadsheet, get clean records. Argo Books imports your customers, products, invoices, and expenses from Excel or CSV files automatically.',
    'feature_list' => 'Spreadsheet import, AI column mapping, Data validation, Excel and CSV support',

    'h1' => 'Bring your<br>spreadsheet with you.',
    'hero_sub' => 'Drop in the Excel or CSV file you already keep and Argo Books works out which column is which, checks the data, and shows you exactly what it will create before anything is saved.',
    'hero_facts' => 'Free plan, no credit card, and the file is read on your own computer.',
    'demo' => 'sheet-import',

    'steps_h2' => 'Three steps, whatever your columns are called',
    'steps_lede' => 'Most import tools make you describe your own spreadsheet to them first. This one reads it.',
    'steps' => [
        ['h3' => 'Drop the file in', 'p' => 'Excel or CSV, however you have laid it out. There is no template to match first.'],
        ['h3' => 'It maps the columns', 'p' => 'AI works out which column holds the date, the amount, the supplier and the rest, whatever you happened to call them.'],
        ['h3' => 'Check and import', 'p' => 'You see what will be created, with anything questionable flagged, and nothing is saved until you say so.'],
    ],

    'splits_before_cta' => [
        [
            'banner' => 'PRODUCT BLOCK',
            'bg' => true,
            'eyebrow' => 'Real files, not tidy ones',
            'h2' => 'It copes with how spreadsheets actually look',
            'lede' => 'Merged cells, a title row above the headings, inconsistent date formats, blank rows in the middle. The files people really keep are messy, and the import is built for those rather than for a clean example.',
            'list' => [
                'Merged cells and stray header rows handled',
                'Mixed date and number formats normalised',
                'Everything validated and previewed before import',
            ],
            'img' => '../../resources/images/features/ai-import-validation.svg',
            'img_alt' => 'Argo Books validating imported spreadsheet rows and flagging the ones that need attention',
            'img_w' => 600, 'img_h' => 500,
        ],
    ],

    'midcta_h2' => 'Move your records across in minutes',
    'midcta_p' => 'No account, no credit card, and no template to fill in first.',

    'benefits_h2' => 'What changes when switching is easy',
    'benefits' => [
        ['icon' => 'clock', 'h3' => 'Years of history in one go', 'p' => 'The reason people stay on a spreadsheet is the cost of moving off it. That cost is a file drop.'],
        ['icon' => 'check', 'stroke' => 2.4, 'h3' => 'Nothing saves until you approve it', 'p' => 'The preview shows exactly what will be created, so a bad import is caught before it becomes a cleanup job.'],
        ['icon' => 'search', 'h3' => 'Bad rows get flagged, not swallowed', 'p' => 'Missing amounts and impossible dates are surfaced for you to fix rather than imported quietly.'],
        ['icon' => 'bolt', 'h3' => 'No mapping screen to fight', 'p' => 'The columns are worked out from the file itself, which is the step that usually makes people give up.'],
    ],

    'splits_after_benefits' => [
        [
            'banner' => 'PRIVACY',
            'bg' => true,
            'flip' => true,
            'eyebrow' => 'Privacy',
            'h2' => 'Your books stay on your computer',
            'lede' => 'Argo Books is a desktop application, not a cloud service holding your finances on someone else\'s server. Your records are written to your own machine, and you can back them up or move them like any other file.',
            'list' => [
                'Records and documents stored locally',
                'No third-party cloud storage of your financial data',
                'Your data moves and backs up like any other file',
            ],
            'img' => '../../resources/images/privacy-local-storage.svg',
            'img_alt' => 'The Argo Books folder open on a local disk, showing receipts, invoices and the database file stored on this computer',
            'img_w' => 600, 'img_h' => 500,
        ],
    ],

    'who_h2' => 'Built for the way you actually work',
    'who' => [
        ['icon' => 'document', 'h3' => 'Anyone switching over', 'p' => 'Move off a spreadsheet without retyping the last three years.'],
        ['icon' => 'users', 'h3' => 'Freelancers', 'p' => 'One file, and your whole history is in the app.'],
        ['icon' => 'package', 'h3' => 'Retail and e-commerce', 'p' => 'Product lists and sales exports brought in as they are.'],
        ['icon' => 'wrench', 'h3' => 'Businesses leaving another tool', 'p' => 'Export from the old system, import here, keep the history.'],
    ],

    'related_eyebrow' => 'Works with',
    'related_h2' => 'Other ways to get data in',
    'related' => [
        ['href' => '../bank-statement-import/', 'icon' => 'bank', 'h3' => 'Bank statement import', 'p' => 'A month of banking in one file, matched against what you already have.'],
        ['href' => '../receipt-scanning/', 'icon' => 'receipt-scan', 'h3' => 'AI receipt scanning', 'p' => 'Photograph a receipt and the expense record writes itself.'],
        ['href' => '../expense-revenue-tracking/', 'icon' => 'dollar', 'h3' => 'Expense & revenue tracking', 'p' => 'Where your imported records land.'],
        ['href' => '../inventory-management/', 'icon' => 'package', 'h3' => 'Inventory management', 'p' => 'Import a product list and start tracking stock from it.'],
    ],

    // Drives both the visible accordion and the FAQPage JSON-LD.
    'faqs' => [
    [
        'q' => 'What file formats does spreadsheet import support?',
        'a' => 'Argo Books supports Excel (.xlsx) and CSV files. Drag and drop your file and Argo Books detects your columns, maps them to the right fields, and imports everything. No manual formatting or templates needed.',
    ],
    [
        'q' => 'What types of data can I import?',
        'a' => 'You can import customers, products, expenses, revenue, invoices, and more. Argo Books reads your column headers and figures out what each spreadsheet contains, whether you\'re moving from another tool or cleaning up old spreadsheets.',
    ],
    [
        'q' => 'Do I need to manually map columns?',
        'a' => 'Usually not. Argo Books reads your column headers and maps them to the right fields for you. You can review and adjust the mapping before importing, but most imports go through with a quick confirmation.',
    ],
    [
        'q' => 'How many records can I import per month?',
        'a' => 'The Free plan includes ' . $pricing['ai_import_monthly_limit'] . ' spreadsheet imports per month, which is plenty for getting started or migrating in batches. Premium users have no limit. Each file counts as one import, no matter how many rows it contains.',
    ],
    ],

    'outro_h2' => 'Bring your history with you',
    'outro_p' => 'Download Argo Books and import your spreadsheet today. Free plan, no credit card, and your data stays on your own machine.',
];
