<?php
// features/data/invoicing.php
//
// Content for /features/invoicing/. Layout lives in
// features/feature-page.php.
//
// The plan limits below read from $pricing. A PHP tag inside a quoted string never evaluates, so write the value, not the tag.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'breadcrumb' => 'Invoicing',
    'title' => 'Invoicing | Argo Books',
    'meta_description' => 'Create professional invoices with Argo Books. Customizable templates, automatic line-item calculations, online payment links, and payment tracking that help you get paid faster.',
    'meta_keywords' => 'invoice software, invoice generator, professional invoicing, small business invoicing, invoice templates, online invoice payments, invoice tracking, send invoices, payment reminders, invoice management',
    'og_title' => 'Invoicing | Argo Books',
    'og_description' => 'Create professional invoices with Argo Books. Customizable templates, payment tracking, and online payment links that help you get paid faster.',
    'feature_list' => 'Invoice creation and templates, Online payment collection, Payment reminders, Invoice status tracking',

    'h1' => 'Send it today.<br>Get paid this week.',
    'hero_sub' => 'Build an invoice in a couple of minutes, send it with a payment link attached, and watch it move from sent to paid without chasing anyone by email.',
    'hero_facts' => 'Free plan, no credit card, and your invoices stay on your own computer.',
    'demo' => 'invoices',

    'steps_h2' => 'Three steps, about two minutes',
    'steps_lede' => 'The gap between finishing the work and getting paid is mostly admin. This removes the admin.',
    'steps' => [
        ['h3' => 'Fill in the work', 'p' => 'Pick the customer, add line items, and let the totals and tax work themselves out. Saved details fill most of it in for you.'],
        ['h3' => 'Choose how it looks', 'p' => 'Swap the template and the accent colour to something that matches your business, then preview exactly what your customer will open.'],
        ['h3' => 'Send it with a payment link', 'p' => 'The invoice goes out with a link to pay by card. You see the status change the moment they do.'],
    ],

    'splits_before_cta' => [
        [
            'banner' => 'PRODUCT BLOCK',
            'bg' => true,
            'eyebrow' => 'After you send',
            'h2' => 'Know what is paid, and what is not',
            'lede' => 'Every invoice carries a status: draft, sent, viewed, paid, overdue. The dashboard totals what is outstanding so you can see at a glance who owes you and for how long, and send a reminder without writing the email yourself.',
            'list' => [
                'Outstanding and overdue totals on one screen',
                'Automatic payment reminders on the schedule you set',
                'Card payments through Stripe or Square',
            ],
            'img' => '../../resources/images/features/invoice-dashboard.svg',
            'img_alt' => 'The Argo Books invoice dashboard showing outstanding, paid and overdue totals with a list of recent invoices',
            'img_w' => 600, 'img_h' => 500,
        ],
    ],

    'midcta_h2' => 'Send your first invoice in about two minutes',
    'midcta_p' => 'No account, no credit card, and no setup before you can bill someone.',

    'benefits_h2' => 'What changes when invoicing is not a chore',
    'benefits' => [
        ['icon' => 'clock', 'h3' => 'Billing stops slipping to the weekend', 'p' => 'When an invoice takes two minutes instead of half an hour, it goes out the day the work finishes rather than whenever you get around to it.'],
        ['icon' => 'credit-card', 'h3' => 'Paying you takes one click', 'p' => 'A payment link in the invoice removes the step where your customer means to pay you and then forgets for three weeks.'],
        ['icon' => 'bell', 'h3' => 'Reminders you do not have to write', 'p' => 'Overdue invoices chase themselves on the schedule you set, so you are not the one sending the awkward email.'],
        ['icon' => 'check', 'stroke' => 2.4, 'h3' => 'The numbers match your books', 'p' => 'A paid invoice becomes revenue in your records automatically, so what you billed and what you banked stay in step.'],
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
        ['icon' => 'users', 'h3' => 'Freelancers', 'p' => 'Bill by project or by hour, and see which clients pay on time and which ones need a reminder.'],
        ['icon' => 'wrench', 'h3' => 'Trades and services', 'p' => 'Quote, invoice and collect from the same record, without a separate app for each step.'],
        ['icon' => 'package', 'h3' => 'Retail and wholesale', 'p' => 'Invoice trade customers on terms while retail sales settle immediately.'],
        ['icon' => 'document', 'h3' => 'Anyone with a slow payer', 'p' => 'Overdue totals and automatic reminders make the follow-up routine instead of personal.'],
    ],

    'related_eyebrow' => 'Works with',
    'related_h2' => 'What invoicing connects to',
    'related' => [
        ['href' => '../customer-management/', 'icon' => 'users', 'h3' => 'Customer management', 'p' => 'Contacts, purchase history and balances, so a new invoice starts half filled in.'],
        ['href' => '../expense-revenue-tracking/', 'icon' => 'dollar', 'h3' => 'Expense & revenue tracking', 'p' => 'Paid invoices land in your revenue records without a second entry.'],
        ['href' => '../report-builder/', 'icon' => 'report', 'h3' => 'Report builder', 'p' => 'Turn what you have billed into statements you can hand to an accountant.'],
        ['href' => '../../invoice-generator/', 'icon' => 'document', 'h3' => 'Free invoice generator', 'p' => 'Make a one-off invoice in your browser without installing anything.'],
    ],

    // Drives both the visible accordion and the FAQPage JSON-LD.
    'faqs' => [
    [
        'q' => 'Can customers pay invoices online through Argo Books?',
        'a' => 'Yes. Every invoice includes a secure online payment link so your customers can pay by credit card with a single click. Argo Books supports Stripe and Square. You choose which one works best for your business. Payments are tracked automatically, so you always know which invoices are outstanding and which have been paid.',
    ],
    [
        'q' => 'Can I customize how my invoices look?',
        'a' => 'Yes. Invoices are sent via professional email templates that include your company logo, billing details, and itemized line items. You can choose from multiple templates and customize the content to match your brand. Every invoice looks polished and professional, with no design skills required.',
    ],
    [
        'q' => 'How does invoice tracking work?',
        'a' => 'Argo Books tracks every invoice from draft to paid with color-coded status badges so you can see where things stand at a glance. Summary cards on the invoicing dashboard show your outstanding, paid, and overdue totals in real time. You\'ll never have to wonder whether a client has paid. It\'s all right there.',
    ],
    [
        'q' => 'How many invoices can I send per month?',
        'a' => 'The Free plan includes ' . $pricing['free_invoice_monthly_limit'] . ' invoices per month, which is plenty for most small businesses and freelancers getting started. If you need unlimited invoicing, the Premium plan removes all limits so you can send as many invoices as your business requires.',
    ],
    ],

    'outro_h2' => 'Stop waiting to get paid',
    'outro_p' => 'Download Argo Books and send your first invoice today. Free plan, no credit card, and your data stays on your own machine.',
];
