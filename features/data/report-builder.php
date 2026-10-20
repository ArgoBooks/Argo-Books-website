<?php
// features/data/report-builder.php
//
// Content for /features/report-builder/. Layout lives in
// features/feature-page.php.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'breadcrumb' => 'Report Builder',
    'title' => 'Report Builder | Argo Books',
    'meta_description' => 'Build professional accounting reports in Argo Books: Income Statement, Balance Sheet, Cash Flow, General Ledger, AR Aging, and Tax Summary. A drag-and-drop designer, your branding, and clean PDF export. Free to use.',
    'meta_keywords' => 'accounting report software, income statement software, balance sheet software, general ledger software, financial report builder, tax summary report, report designer, free accounting reports, cash flow statement software',
    'og_title' => 'Report Builder | Argo Books',
    'og_description' => 'Build Income Statements, Balance Sheets, and more from your own data, design them your way, and export a clean PDF. Free to use.',
    'feature_list' => 'Custom report builder, Profit and loss statements, Balance sheets, Export to PDF and spreadsheet',

    'h1' => 'The statement<br>your accountant asked for.',
    'hero_sub' => 'Profit and loss, balance sheet, and whatever else you need, built from the records already in your books and exported in a format anyone can open.',
    'hero_facts' => 'Free plan, no credit card, and your reports are generated on your own computer.',
    'demo' => 'report',

    'steps_h2' => 'Three steps to a finished report',
    'steps_lede' => 'A report is only worth having if producing it does not take longer than reading it.',
    'steps' => [
        ['h3' => 'Pick the report', 'p' => 'Profit and loss, balance sheet, expense summary, or a layout you build yourself.'],
        ['h3' => 'Set the period', 'p' => 'A month, a quarter, a financial year, or any range you choose.'],
        ['h3' => 'Export and send it', 'p' => 'PDF for reading, spreadsheet for working with. Both come out ready to hand over.'],
    ],

    'splits_before_cta' => [
        [
            'banner' => 'PRODUCT BLOCK',
            'bg' => true,
            'eyebrow' => 'Not just the standard set',
            'h2' => 'Build the report you actually need',
            'lede' => 'The standard statements cover what an accountant asks for. The builder covers everything else: group by customer, by category or by period, filter to the part you care about, and save the layout so next quarter takes one click.',
            'list' => [
                'Profit and loss and balance sheet out of the box',
                'Custom layouts you can save and reuse',
                'Export to PDF or spreadsheet',
            ],
            'img' => '../../resources/images/features/report-types.svg',
            'img_alt' => 'The report types available in Argo Books, including profit and loss, balance sheet and expense summaries',
            'img_w' => 600, 'img_h' => 500,
        ],
    ],

    'midcta_h2' => 'Produce your first statement in minutes',
    'midcta_p' => 'No account, no credit card, and no accounting knowledge needed.',

    'benefits_h2' => 'What changes when reports are one click',
    'benefits' => [
        ['icon' => 'clock', 'h3' => 'Year end stops being a project', 'p' => 'When the statement takes a minute to produce, handing something over to an accountant is not a week of preparation.'],
        ['icon' => 'check', 'stroke' => 2.4, 'h3' => 'The figures come from the records', 'p' => 'Reports are generated from your actual transactions, so there is no re-keying step to get wrong.'],
        ['icon' => 'search', 'h3' => 'You can answer your own questions', 'p' => 'Group and filter the way you think about the business rather than the way a fixed template does.'],
        ['icon' => 'trending-up', 'h3' => 'Periods are comparable', 'p' => 'Run the same report across quarters and the change is visible instead of inferred.'],
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
        ['icon' => 'users', 'h3' => 'Freelancers', 'p' => 'A profit and loss for the year without paying someone to assemble it.'],
        ['icon' => 'package', 'h3' => 'Retail and e-commerce', 'p' => 'Margin and category reporting across a lot of transactions.'],
        ['icon' => 'wrench', 'h3' => 'Service businesses', 'p' => 'Profitability by job or customer, not just overall.'],
        ['icon' => 'document', 'h3' => 'Anyone with an accountant', 'p' => 'Hand over a statement in a format they can work with immediately.'],
    ],

    'related_eyebrow' => 'Works with',
    'related_h2' => 'What reports are built from',
    'related' => [
        ['href' => '../expense-revenue-tracking/', 'icon' => 'dollar', 'h3' => 'Expense & revenue tracking', 'p' => 'The transaction records every report is generated from.'],
        ['href' => '../invoicing/', 'icon' => 'document', 'h3' => 'Invoicing', 'p' => 'Billed and paid figures flow straight into your statements.'],
        ['href' => '../inventory-management/', 'icon' => 'package', 'h3' => 'Inventory management', 'p' => 'Stock value feeds the balance sheet, and cost of goods the income statement.'],
        ['href' => '../predictive-analytics/', 'icon' => 'analytics', 'h3' => 'Predictive analytics', 'p' => 'Look forward as well as back, from the same records.'],
    ],

    // Drives both the visible accordion and the FAQPage JSON-LD.
    'faqs' => [
    [
        'q' => 'What reports can I create in Argo Books?',
        'a' => 'Argo Books includes the core financial statements: Income Statement, Balance Sheet, Cash Flow Statement, General Ledger, AR Aging, Tax Summary, and Sales by Product, plus a Customer Statement to send each customer what they owe, and analytics-style overview templates. You can also start from a blank report and build your own.',
    ],
    [
        'q' => 'Can I customize how a report looks?',
        'a' => 'Yes. A three-step designer lets you drag, resize, align, and arrange charts, tables, labels, and images on the page, with snapping, undo and redo, and multi-page layouts. You control the page size, orientation, margins, colors, and your branded header and footer.',
    ],
    [
        'q' => 'What can I export a report to?',
        'a' => 'Finished reports export as a PDF for printing and sharing, or as a high-quality PNG or JPEG image. The PDF is a true multi-page document with your branding on every page.',
    ],
    [
        'q' => 'Is the report builder a paid feature?',
        'a' => 'No. The full report builder, including every accounting statement and the designer, is part of Argo Books at no cost, with no premium plan required and no usage limit.',
    ],
    [
        'q' => 'Does it use the right tax terms for my country?',
        'a' => 'Yes. Argo Books labels tax lines with the right terminology for your country, such as GST/HST in Canada, VAT in the UK and EU, or Sales Tax in the US, and it adjusts statement wording to match common accounting conventions.',
    ],
    ],

    'outro_h2' => 'Stop assembling statements by hand',
    'outro_p' => 'Download Argo Books and produce your first report today. Free plan, no credit card, and your data stays on your own machine.',
];
