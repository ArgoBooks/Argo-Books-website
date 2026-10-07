<?php
// features/data/bank-statement-import.php
//
// Content for /features/bank-statement-import/. Layout lives in
// features/feature-page.php.
//
// The plan limits below read from $pricing. A PHP tag inside a quoted string never evaluates, so write the value, not the tag.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'breadcrumb' => 'Bank Statement Import',
    'title' => 'Bank Statement Import | Argo Books',
    'meta_description' => 'Import a bank statement (CSV, Excel, or PDF) into Argo Books and every line becomes a categorized expense or revenue, ready to review. Match against your books too. No bank login required.',
    'meta_keywords' => 'bank statement import, import bank statement CSV, bank statement to accounting software, bank reconciliation software, categorize bank transactions, PDF bank statement import, bank matching, no bank connection bookkeeping',
    'og_title' => 'Bank Statement Import | Argo Books',
    'og_description' => 'Drop in a bank statement and every line becomes a categorized expense or revenue. Match against your books, all without connecting your bank.',
    'feature_list' => 'Bank statement import, Automatic transaction matching, Duplicate detection, Multi-format support',

    'h1' => 'A month of banking,<br>in one go.',
    'hero_sub' => 'Drop in the statement your bank gives you and Argo Books reads the transactions, matches the ones you have already recorded, and leaves you only the genuinely new entries to confirm.',
    'hero_facts' => 'Free plan, no credit card, and the file never leaves your own computer.',
    'demo' => 'bank-import',

    'steps_h2' => 'Three steps, one file',
    'steps_lede' => 'Typing a statement in by hand is where an afternoon goes. Importing it is where ten minutes goes.',
    'steps' => [
        ['h3' => 'Export from your bank', 'p' => 'Whatever format they offer. CSV, Excel, PDF and the common statement layouts are all read.'],
        ['h3' => 'Drop the file in', 'p' => 'Columns are worked out for you, so there is no mapping screen to fight with before anything happens.'],
        ['h3' => 'Confirm what is new', 'p' => 'Anything already in your books is matched and set aside. You review the rest and save.'],
    ],

    'splits_before_cta' => [
        [
            'banner' => 'PRODUCT BLOCK',
            'bg' => true,
            'eyebrow' => 'The part that saves the time',
            'h2' => 'It knows what you have already recorded',
            'lede' => 'The slow part of importing a statement is not reading the file, it is working out which lines you already entered. Argo Books matches on amount, date and description, so scanned receipts and manual entries are not duplicated when the statement arrives.',
            'list' => [
                'Existing transactions matched, not duplicated',
                'Columns detected without a mapping step',
                'Review everything before a single record is saved',
            ],
            'img' => '../../resources/images/features/bank-statement-matching.svg',
            'img_alt' => 'Argo Books matching imported bank transactions against records that already exist in the books',
            'img_w' => 600, 'img_h' => 500,
        ],
    ],

    'midcta_h2' => 'Import a month of transactions in minutes',
    'midcta_p' => 'No account, no credit card, and no bank login handed over.',

    'benefits_h2' => 'What changes when the statement does the typing',
    'benefits' => [
        ['icon' => 'clock', 'h3' => 'Catch-up stops taking a weekend', 'p' => 'A backlog of statements becomes a job you finish in one sitting rather than one you keep putting off.'],
        ['icon' => 'check', 'stroke' => 2.4, 'h3' => 'The numbers match the bank', 'p' => 'Working from the statement itself means your books agree with your account instead of drifting apart.'],
        ['icon' => 'shield', 'h3' => 'No bank credentials involved', 'p' => 'You export a file and import it. Argo Books never asks for your online banking login.'],
        ['icon' => 'search', 'h3' => 'Nothing gets counted twice', 'p' => 'Duplicate detection means a receipt you scanned last week does not reappear when the statement lands.'],
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
        ['icon' => 'users', 'h3' => 'Freelancers', 'p' => 'One import a month is often the whole of your bookkeeping.'],
        ['icon' => 'package', 'h3' => 'Retail and e-commerce', 'p' => 'High transaction volumes that would be unreasonable to type in.'],
        ['icon' => 'wrench', 'h3' => 'Trades and services', 'p' => 'Fuel, materials and supplier payments brought in together.'],
        ['icon' => 'document', 'h3' => 'Anyone catching up', 'p' => 'Months of backlog cleared file by file rather than line by line.'],
    ],

    'related_eyebrow' => 'Works with',
    'related_h2' => 'Other ways to get data in',
    'related' => [
        ['href' => '../receipt-scanning/', 'icon' => 'receipt-scan', 'h3' => 'AI receipt scanning', 'p' => 'Photograph a receipt and the expense writes itself, line items and all.'],
        ['href' => '../spreadsheet-import/', 'icon' => 'document-upload', 'h3' => 'Spreadsheet import', 'p' => 'Bring across records you already keep in Excel or CSV.'],
        ['href' => '../expense-revenue-tracking/', 'icon' => 'dollar', 'h3' => 'Expense & revenue tracking', 'p' => 'Where every imported transaction lands.'],
        ['href' => '../predictive-analytics/', 'icon' => 'analytics', 'h3' => 'Predictive analytics', 'p' => 'More history in means a sharper forecast out.'],
    ],

    // Drives both the visible accordion and the FAQPage JSON-LD.
    'faqs' => [
    [
        'q' => 'What bank statement formats can I import?',
        'a' => 'Argo Books imports bank statements as CSV, Excel (.xlsx and .xls), or PDF. Export a statement from your online banking, drop the file in, and each transaction line is read and pre-filled for you.',
    ],
    [
        'q' => 'Do I need to connect my bank account?',
        'a' => 'No. There is no bank login, no connection, and no third-party aggregator. Argo Books works entirely from the statement file you export yourself, so nothing is ever linked to your bank.',
    ],
    [
        'q' => 'Does it record transactions automatically?',
        'a' => 'Every line is pre-filled for you with a type, category, and supplier or customer, but nothing is saved until you review and confirm. You stay in control of what goes into your books.',
    ],
    [
        'q' => 'How is importing different from matching?',
        'a' => 'Import turns each bank line into a new categorized expense or revenue. Matching compares your statement against records you have already entered, confirms the ones that line up, and shows anything missing from your books. You can use either, or both.',
    ],
    [
        'q' => 'How many bank statements can I import per month?',
        'a' => 'The Free plan includes ' . $pricing['bank_import_monthly_limit'] . ' AI bank imports per month and Premium includes ' . $pricing['premium_bank_import_monthly_limit'] . '. Reading a CSV or Excel file without AI categorization doesn\'t count against your limit, and even at the limit you can still import and fill lines in by hand.',
    ],
    ],

    'outro_h2' => 'Stop typing your bank statement in',
    'outro_p' => 'Download Argo Books and import your first statement today. Free plan, no credit card, and your data stays on your own machine.',
];
