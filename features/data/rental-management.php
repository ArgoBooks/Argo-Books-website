<?php
// features/data/rental-management.php
//
// Content for /features/rental-management/. Layout lives in
// features/feature-page.php.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'breadcrumb' => 'Rental Management',
    'title' => 'Rental Management | Argo Books',
    'meta_description' => 'Manage rental bookings, track rental periods, handle returns, and monitor revenue with Argo Books. Built for equipment rental, event companies, and any rental-based business.',
    'meta_keywords' => 'rental management software, booking management, equipment rental tracking, rental business software, rental inventory, rental returns, rental invoicing, equipment booking, rental deposits, overdue rentals',
    'og_title' => 'Rental Management | Argo Books',
    'og_description' => 'Manage rental bookings, track rental periods, handle returns, and monitor revenue with Argo Books. Built for equipment rental and any rental-based business.',
    'feature_list' => 'Booking calendar, Availability tracking, Return tracking, Rental item management',

    'h1' => 'Booked, out,<br>and back again.',
    'hero_sub' => 'A calendar that shows what is reserved and what is free, so you can answer "is it available next Thursday?" without checking three places first.',
    'hero_facts' => 'Free plan, no credit card, and your booking data stays on your own computer.',
    'demo' => 'rental',

    'steps_h2' => 'Three steps from enquiry to return',
    'steps_lede' => 'Double bookings happen when availability lives in somebody\'s head. This puts it on a calendar that the bookings themselves keep current.',
    'steps' => [
        ['h3' => 'List what you rent out', 'p' => 'Each item with its rate and how many you have. One record covers the whole fleet of a given item.'],
        ['h3' => 'Take the booking', 'p' => 'Pick the customer and the dates. The calendar blocks the item out and stops it being promised twice.'],
        ['h3' => 'Mark it back in', 'p' => 'Returns free the item up immediately and close the rental off against the customer.'],
    ],

    'splits_before_cta' => [
        [
            'banner' => 'PRODUCT BLOCK',
            'bg' => true,
            'eyebrow' => 'At a glance',
            'h2' => 'Availability you can trust when the phone rings',
            'lede' => 'The booking calendar shows each item across every date, with what is out, what is reserved and what is free. Because bookings write to it directly there is no second diary to keep in step.',
            'list' => [
                'Every item and every date on one calendar',
                'Bookings tied to the customer who took the item',
                'Overdue returns visible without hunting for them',
            ],
            'img' => '../../resources/images/features/rental-records.svg',
            'img_alt' => 'The Argo Books rental calendar showing booked, reserved and available dates across rental items',
            'img_w' => 600, 'img_h' => 500,
        ],
    ],

    'midcta_h2' => 'Get your bookings on one calendar',
    'midcta_p' => 'No account, no credit card, and nothing to set up before your first booking.',

    'benefits_h2' => 'What changes when availability is visible',
    'benefits' => [
        ['icon' => 'calendar', 'h3' => 'No more double bookings', 'p' => 'An item that is out cannot be promised to somebody else, because the calendar and the booking are the same record.'],
        ['icon' => 'clock', 'h3' => 'Overdue returns surface themselves', 'p' => 'You see what should have come back yesterday without going looking for it.'],
        ['icon' => 'dollar', 'h3' => 'Rentals land in your books', 'p' => 'Mark a rental paid, or invoice it, and the money lands in your revenue with the customer attached.'],
        ['icon' => 'check', 'stroke' => 2.4, 'h3' => 'One answer, not three', 'p' => 'Availability, price and history all come from the same record, so what you tell a customer is what the system knows.'],
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
        ['icon' => 'truck', 'h3' => 'Equipment hire', 'p' => 'Tools, machinery and vehicles booked out by the day or the week.'],
        ['icon' => 'calendar', 'h3' => 'Event businesses', 'p' => 'Furniture, staging and gear across overlapping event dates.'],
        ['icon' => 'package', 'h3' => 'Kit and gear rental', 'p' => 'Cameras, instruments and sports equipment with fast turnaround.'],
        ['icon' => 'users', 'h3' => 'Anyone lending on terms', 'p' => 'Keep track of what is out, who has it, and when it is due back.'],
    ],

    'related_eyebrow' => 'Works with',
    'related_h2' => 'What rentals connect to',
    'related' => [
        ['href' => '../customer-management/', 'icon' => 'users', 'h3' => 'Customer management', 'p' => 'Bookings attach to the customer, along with their history and balance.'],
        ['href' => '../invoicing/', 'icon' => 'document', 'h3' => 'Invoicing', 'p' => 'Bill a completed rental without re-entering the dates or the rate.'],
        ['href' => '../inventory-management/', 'icon' => 'package', 'h3' => 'Inventory management', 'p' => 'Track the items you rent alongside the stock you sell.'],
        ['href' => '../expense-revenue-tracking/', 'icon' => 'dollar', 'h3' => 'Expense & revenue tracking', 'p' => 'Paid rentals land in your revenue records, with or without an invoice.'],
    ],

    // Drives both the visible accordion and the FAQPage JSON-LD.
    'faqs' => [
    [
        'q' => 'How does Argo Books track overdue rentals?',
        'a' => 'Argo Books automatically flags rentals as overdue when the return date passes. Color-coded status badges make it easy to spot late returns at a glance, so nothing slips through the cracks. You can see all overdue, active, and completed rentals from a single dashboard.',
    ],
    [
        'q' => 'Can I track deposits and payments for rentals?',
        'a' => 'Yes. Each rental item has a deposit, and a rental adds it up for every unit. When the item comes back, you refund all of the deposit, part of it, or none, and add any late fee or damage as an extra charge. You can create an invoice straight from the rental record, with the customer, charges and deposit filled in, so there\'s no double entry.',
    ],
    [
        'q' => 'Is rental management included in the Free plan?',
        'a' => 'Yes. Rental management is a core feature available on both the Free and Premium plans. You can create and manage rental bookings, track deposits, and monitor return dates at no cost. Premium users additionally benefit from unlimited invoicing directly from rental records.',
    ],
    [
        'q' => 'What types of businesses use rental management in Argo Books?',
        'a' => 'Rental management in Argo Books is designed for any business that lends or rents items: equipment rental companies, tool libraries, party supply rentals, AV equipment providers, and more. If you need to track who has what, when it\'s due back, and what they owe, Argo Books handles it.',
    ],
    ],

    'outro_h2' => 'Stop checking three places for one answer',
    'outro_p' => 'Download Argo Books and put your bookings on one calendar. Free plan, no credit card, and your data stays on your own machine.',
];
