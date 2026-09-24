<?php
// features/data/quotes.php
//
// Content for /features/quotes/. Layout lives in
// features/feature-page.php.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'breadcrumb' => 'Quotes',
    'title' => 'Quotes & Estimates | Argo Books',
    'meta_description' => 'Send quotes your customer can accept or decline online, then turn an accepted one into an invoice in a click. Free quoting and estimate software for small businesses and trades.',
    'meta_keywords' => 'quoting software, estimate software, free quote software, send quotes online, accept quote online, quote to invoice, estimate template, contractor quotes, job quoting, quote tracking',
    'og_title' => 'Quotes & Estimates | Argo Books',
    'og_description' => 'Price up the work, send it, and let your customer accept or decline online. An accepted quote becomes an invoice in one click.',
    'feature_list' => 'Quote creation and templates, Online accept and decline, Quote to invoice conversion, Expiry and follow-up tracking',

    'h1' => 'Win the work first.<br>Bill it second.',
    'hero_sub' => 'Price up a job, send it, and let your customer accept or decline it online. When they say yes, the quote becomes a draft invoice without you retyping a thing.',
    'hero_facts' => 'Unlimited quotes on every plan, and your records stay on your own computer.',
    'demo' => 'quotes',

    'steps_h2' => 'Quote today, answer tomorrow',
    'steps_lede' => 'Most quoting is retyping: once into a document, again into an invoice when it lands. This does it once.',
    'steps' => [
        ['h3' => 'Price up the work', 'p' => 'Pick the customer, pull items from your catalogue, and set a date the price is good until. The totals and tax work themselves out.'],
        ['h3' => 'Send it and wait', 'p' => 'Your customer gets a link. The page shows the quote exactly as you built it, with Accept and Decline buttons and a box to reply.'],
        ['h3' => 'Turn a yes into an invoice', 'p' => 'One click copies the customer, the lines and the totals into a draft invoice for you to check before anything goes out.'],
    ],

    'splits_before_cta' => [
        [
            'banner' => 'PRODUCT BLOCK',
            'bg' => true,
            'eyebrow' => 'After you send',
            'h2' => 'Know which quotes are still alive',
            'lede' => 'Every quote carries a status: draft, sent, accepted, declined, expired. The page counts the ones about to run out of time, so the follow-up worth making is obvious and the dead ones stop taking up room in your head.',
            'list' => [
                'Accepted, declined and expiring counts on one screen',
                'Their reply comes back with their answer, in their words',
                'A revised quote asks again rather than assuming the old yes',
            ],
            'img' => '../../resources/images/features/quotes-dashboard.svg',
            'img_alt' => 'The Argo Books quotes page listing recent quotes with draft, sent, accepted, declined and expired statuses',
            'img_w' => 600, 'img_h' => 500,
        ],
    ],

    'midcta_h2' => 'Send your first quote in about two minutes',
    'midcta_p' => 'No account, no credit card, and nothing to set up before you can price a job.',

    'benefits_h2' => 'What changes when quoting is not paperwork',
    'benefits' => [
        ['icon' => 'clock', 'h3' => 'The quote goes out the same day', 'p' => 'Quoting on the drive home instead of on Sunday night is usually the difference between getting the job and hearing nothing back.'],
        ['icon' => 'check', 'stroke' => 2.4, 'h3' => 'A yes is a yes, in writing', 'p' => 'Accepting happens on a page with a timestamp and their message attached, so what was agreed is not a memory of a phone call.'],
        ['icon' => 'document', 'h3' => 'No retyping when it lands', 'p' => 'The invoice is built from the quote you already wrote, so the price they accepted is the price you bill.'],
        ['icon' => 'bar-chart', 'h3' => 'Quotes stay out of your income', 'p' => 'A quote is a price you offered, not money you are owed. It touches no report and no tax figure until it becomes an invoice.'],
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
        ['icon' => 'wrench', 'h3' => 'Trades and contractors', 'p' => 'Price a job on site, send it before you leave, and bill from the same record when it is signed off.'],
        ['icon' => 'users', 'h3' => 'Freelancers', 'p' => 'Scope the work in writing so the brief that gets agreed is the one you invoice against.'],
        ['icon' => 'package', 'h3' => 'Rental and events', 'p' => 'Quote a date and a kit list, and hold the price until the customer commits.'],
        ['icon' => 'analytics', 'h3' => 'Anyone bidding for work', 'p' => 'See how many quotes turn into invoices, and how long your customers take to decide.'],
    ],

    'related_eyebrow' => 'Works with',
    'related_h2' => 'What quotes connect to',
    'related' => [
        ['href' => '../invoicing/', 'icon' => 'document', 'h3' => 'Invoicing', 'p' => 'An accepted quote becomes a draft invoice, with the lines and totals already filled in.'],
        ['href' => '../customer-management/', 'icon' => 'users', 'h3' => 'Customer management', 'p' => 'Contacts and history, so a new quote starts half written.'],
        ['href' => '../inventory-management/', 'icon' => 'package', 'h3' => 'Inventory management', 'p' => 'Quote from your product catalogue instead of typing prices in by hand.'],
        ['href' => '../rental-management/', 'icon' => 'calendar', 'h3' => 'Rental management', 'p' => 'Price a hire period before it is booked in.'],
    ],

    // Drives both the visible accordion and the FAQPage JSON-LD.
    'faqs' => [
    [
        'q' => 'How does a customer accept a quote?',
        'a' => 'They get an email with a link. The page shows the quote exactly as you built it, with Accept and Decline buttons and a box to leave a message. There is nothing for them to sign up for and nothing to install. Their answer comes back into Argo Books on its own, along with anything they wrote, and the quote\'s status updates.',
    ],
    [
        'q' => 'Can I turn a quote into an invoice?',
        'a' => 'Yes, in one click. Argo Books copies the customer, the line items and the totals into a draft invoice and opens it so you can check it first. Nothing is sent, no revenue is recorded and no payment is requested until you send that invoice yourself. The quote is then marked as converted and links to the invoice it became.',
    ],
    [
        'q' => 'Do quotes count as income in my reports?',
        'a' => 'No. A quote is a price you are offering, not money you are owed, so it stays out of your income, your reports and your tax figures entirely. It only affects your books once you turn it into an invoice and send it.',
    ],
    [
        'q' => 'What happens when a quote expires?',
        'a' => 'You set a valid until date when you write the quote. Once that date passes, the quote shows as expired and can no longer be accepted online, so an old price cannot be taken up months later. The Quotes page counts how many are close to expiring so you know which ones are worth chasing.',
    ],
    [
        'q' => 'How many quotes can I send?',
        'a' => 'Quotes are unlimited on every plan, including the free one. There is a limit on how many can be emailed in a single day, which exists so the address Argo Books sends from cannot be used for bulk mail. Ordinary use will not reach it.',
    ],
    ],

    'outro_h2' => 'Stop losing jobs to a slow quote',
    'outro_p' => 'Download Argo Books and send your first quote today. Free plan, no credit card, and your data stays on your own machine.',
];
