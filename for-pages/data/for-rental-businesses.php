<?php
// for-pages/data/for-rental-businesses.php
//
// Content for the /for-rental-businesses/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'track_event' => 'paid_lp_rental_businesses',
    'cta_source' => 'paid-lp-rental-businesses',

    'breadcrumb' => 'For Rental Businesses',
    'title' => 'Argo Books for Rental Businesses: Rental Tracking and Books, in One App',
    'meta_description' => 'Accounting software for equipment, tool, party, and AV rental businesses. Built-in rental management tracks what\'s out, who has it, and what they owe. Free desktop app.',
    'meta_keywords' => 'accounting software for rental business, rental management software, equipment rental bookkeeping, party rental accounting, tool rental software',
    'og_title' => 'Argo Books for Rental Businesses: Rental Tracking and Books, in One App',
    'og_description' => 'Track what\'s out, who has it, and when it\'s coming back. Rental management built in. Free desktop app.',
    'twitter_description' => 'Track what\'s out, who has it, and when it\'s coming back. Rental management built in.',

    'h1' => 'Accounting software for rental businesses',
    'hero_sub' => 'Built around what you rent, who has it, when it\'s coming back, and what they owe. Rental management is included, not an add-on.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card.',
    'demo' => 'rental',

    'features_label' => 'Made for Rental Businesses',
    'features_h2' => 'Your fleet, your customers, your books, in one app',
    'features_desc' => 'A rental business lives in three places: the yard where the equipment sits, the customer site where the equipment is in use, and the books where the deposit, the rental fee, and any late or damage charges have to land. Whether you rent tools, party tents, scaffolding, AV gear, or bounce houses, Argo Books keeps the three in sync.',
    'benefits' => [
        ['icon' => 'package-detail', 'h3' => 'Track what\'s out, who has it, and when it\'s coming back', 'p' => 'Argo Books has rental management built in. Add an item to your fleet, log it out to a customer with a rental period and rate, and when it comes back, the invoice already knows what\'s owed. No spreadsheet, no sticky notes on the office wall.'],
        ['icon' => 'credit-card', 'h3' => 'Deposit, rental fee, damage hold, all on the right line', 'p' => 'The security deposit shows on the invoice as its own line, the rental is charged at the daily, weekly or monthly rate, and a late-return or damage charge goes on as a separate line when the item comes back. Refund the whole deposit, or keep part of it to cover damage. The customer sees exactly what they paid.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'Snap a receipt when you buy stock for the fleet', 'p' => 'Take a photo of the supplier receipt when you buy a new generator, a new tent, or a new case of replacement parts. Argo Books pulls the vendor, date, and amount automatically. Tag it Fleet Purchase or Repair so when you look at margins next quarter, the numbers are sitting where you put them.'],
        ['icon' => 'shield-check', 'h3' => 'Works offline, free tier covers small fleets', 'p' => 'Argo Books runs natively on Windows, macOS, and Linux. No internet needed in the yard, no monthly subscription climbing every year, no website to wait on when you\'re checking out a customer. The free tier covers most small fleets forever.'],
    ],

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books has rental management for the operating and bookkeeping side, but it is not an online booking platform. It does not run a reservation calendar on your website, send automated pickup-and-return SMS reminders, or handle customer-facing self-service rentals. If those are critical, Booqable, Rentle, or EZRentOut handle the booking, and Argo Books handles the books. It does payroll for Canadian staff, but not for staff outside Canada. If those are dealbreakers, that\'s fair. If they\'re not, the desktop app is free, the rental tracking is built in, and your data stays on your computer.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'Most small rental businesses stay on the free tier. Premium adds predictive analytics for seasonal demand planning, unlimited invoicing, and priority support.',

    'related' => [
        ['href' => '../for-local-wholesalers/', 'icon' => 'truck', 'h3' => 'Local wholesalers', 'p' => 'Stock, supplier orders and trade accounts on terms.'],
        ['href' => '../for-resellers/', 'icon' => 'tag', 'h3' => 'Resellers', 'p' => 'Cost, margin and stock across everything you list.'],
        ['href' => '../for-contractors/', 'icon' => 'hard-hat', 'h3' => 'Contractors', 'p' => 'Deposits, mid-job draws, materials and change orders.'],
        ['href' => '../for-solo-operators/', 'icon' => 'user', 'h3' => 'Solo operators', 'p' => 'One person, one price, books that need no bookkeeper.'],
    ],

    'faqs' => [
        ['q_html' => 'Does Argo Books actually have rental management built in?', 'a_html' => '<p>Yes. Rental Management is a built-in feature, not an add-on. Track items in your fleet, see what\'s out, who has it, and when it\'s due back.</p>
                            <p>When the rental closes, the invoice already knows the rental period and rate.</p>'],
        ['q_html' => 'Can I charge a security deposit separately from the rental fee?', 'a_html' => '<p>Yes. The deposit shows on the invoice as its own line. When the item comes back, refund all of it, or keep part of it to cover damage.</p>
                            <p>The rental fee is a separate line item with its own period and rate.</p>'],
        ['q_html' => 'Can I track late returns and damage charges?', 'a_html' => '<p>Yes. When you process the return, add the late fee or damage as an extra charge with a note, and it goes on the invoice as its own line.</p>
                            <p>To cover damage from a deposit you already collected instead, refund less of the deposit.</p>'],
        ['q_html' => 'Does it work without internet?', 'a_html' => '<p>Yes. The desktop app runs natively on your computer and does not need an internet connection to log a rental, check an item back in, or build an invoice.</p>
                            <p>You only need internet when you actually send the invoice or take a payment.</p>'],
        ['q_html' => 'Does Argo Books take online reservations or send pickup reminders?', 'a_html' => '<p>Not online. You can reserve items for later dates inside Argo Books, and it won\'t let the same unit be booked twice, but customers can\'t book on your website and it doesn\'t send automated SMS reminders.</p>
                            <p>If those are critical, tools like Booqable, Rentle, or EZRentOut handle the booking side, and you can run Argo Books alongside for the bookkeeping.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The free tier covers all core features including rental management and ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'guide_link' => 'Want the bookkeeping side in plain language? Read our guide to <a href="../bookkeeping-for-rental-businesses/">bookkeeping for rental businesses</a>.',

    'cta_h2' => 'Ready to track your fleet and your books in one place?',
    'cta_p' => 'Download Argo Books for free. Add your first rental item, check it out to a customer, and build the closing invoice in under ten minutes.',
];
