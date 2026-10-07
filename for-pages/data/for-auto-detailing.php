<?php
// for-pages/data/for-auto-detailing.php
//
// Content for the /for-auto-detailing/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'track_event' => 'paid_lp_auto_detailing',
    'cta_source' => 'paid-lp-auto-detailing',

    'breadcrumb' => 'For Auto Detailing',
    'title' => 'Argo Books for Auto Detailing: Packages, Add-Ons, and the Books, in One App',
    'meta_description' => 'Accounting software for mobile detailers, shop-based detailers, and ceramic coating specialists. Built for tiered packages, add-ons, and recurring memberships. Free desktop app.',
    'meta_keywords' => 'accounting software for auto detailing, mobile detailer bookkeeping, ceramic coating invoicing, detail shop accounting, auto detailing business software',
    'og_title' => 'Argo Books for Auto Detailing: Packages, Add-Ons, and the Books, in One App',
    'og_description' => 'Tiered packages, ceramic coating jobs, supply receipts, and recurring memberships. Free desktop app for detailers.',
    'twitter_description' => 'Tiered packages, ceramic coating jobs, supply receipts, and recurring memberships.',

    'h1' => 'Accounting software for auto detailing',
    'hero_sub' => 'Built for tiered packages, ceramic coating jobs, and the supply receipts that quietly add up. From the express wash to the full multi-day correction.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card.',
    'demo' => 'invoices',

    'features_label' => 'Made for Detailers',
    'features_h2' => 'Tiered packages, real margins, less paperwork',
    'features_desc' => 'Detailing is the package menu (express, full, ceramic coating), the up-charge when the back seat has more dog hair than fabric, and the supply stack that keeps growing in the trailer. Mobile or shop-based, solo or with a few hands, the work that builds the business is repeat customers paying premium for premium work. Argo Books keeps the books, so your time goes to the cars.',
    'benefits' => [
        ['icon' => 'document-lines', 'h3' => 'Base package and add-ons on one clean invoice', 'p' => 'Express, Full, or Ceramic Coating on the top line. Pet hair, heavy dirt, headlight restoration, or engine bay each on their own line. The customer sees the base price and what the extras added, which keeps the up-charge conversation short and the bill itemized.'],
        ['icon' => 'send', 'h3' => 'Quotes the customer accepts online', 'p' => 'Price a ceramic coating or a paint correction and send it as a link. The customer accepts or declines on their phone, with nothing to install and no account to make. An accepted quote becomes a draft invoice in one click, and quotes are unlimited on the free plan.'],
        ['icon' => 'refresh', 'h3' => 'Recurring invoices for memberships and fleet accounts', 'p' => 'Monthly maintenance memberships and weekly fleet washes run on the same schedule. Set the client, the package, and the frequency once, and a draft invoice is waiting for you every cycle, ready to check and send.'],
        ['icon' => 'credit-card', 'h3' => 'Card payments straight from the invoice', 'p' => 'On Premium, every invoice carries a link the customer can pay by card. The money goes through your own Stripe or Square account, and a paid invoice is recorded as revenue without a second entry.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'Snap a receipt from the detail supply house or the gas station', 'p' => 'Take a photo and Argo Books pulls the vendor, date, and amount automatically. Tag it Supplies, Ceramic Products, Fuel, or Equipment so you can actually see what the supply stack costs you each month and price the next package accordingly.'],
        ['icon' => 'user', 'h3' => 'Every customer\'s history in one place', 'p' => 'Each customer has one record: contact details, every job you have invoiced them for, and what they still owe. When a regular calls, you can see what they had last time before you quote the next one.'],
        ['icon' => 'bar-chart', 'h3' => 'See which packages earn, and hand tax time to your accountant', 'p' => 'Sales by Product shows which packages and add-ons bring in the money. The Income Statement and Tax Summary are built in and free. At year end, send your accountant the statements, every transaction, and the receipts in one go.'],
        ['icon' => 'shield-check', 'h3' => 'Works offline in the driveway, free tier covers solo detailers', 'p' => 'Argo Books runs natively on Windows, macOS, and Linux. No internet needed in the customer\'s driveway, no monthly subscription climbing every year. Mobile detailers can build the invoice with no signal, send it when they\'re back in coverage. The free tier covers most solo detailers forever.'],
    ],

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books is bookkeeping software, not booking software. It does not run a customer-facing booking calendar, take online appointments through your website, or send "on the way" texts before you arrive. Mobile Tech RX, Urable, and DetailPlus handle that side. It also does not run a dedicated ceramic coating warranty database. If you need either, run them alongside Argo Books: those for booking and warranties, Argo Books for the books. Payroll is for staff in Canada only. If those are dealbreakers, that\'s fair. If they\'re not, the desktop app is free, the books stay simple, and your data stays on your computer.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'Most solo detailers and one-shop operations stay on the free tier. Premium adds predictive analytics for slow-season planning, unlimited invoicing, and priority support.',

    'related' => [
        ['href' => '../for-repair-shops/', 'icon' => 'wrench', 'h3' => 'Repair shops', 'p' => 'Parts, labour and job history against the customer who booked it.'],
        ['href' => '../for-cleaning-companies/', 'icon' => 'spray-bottle', 'h3' => 'Cleaning companies', 'p' => 'Recurring invoices, supplies and staff cost per contract.'],
        ['href' => '../for-solo-operators/', 'icon' => 'user', 'h3' => 'Solo operators', 'p' => 'One person, one price, books that need no bookkeeper.'],
        ['href' => '../for-contractors/', 'icon' => 'hard-hat', 'h3' => 'Contractors', 'p' => 'Deposits, mid-job draws, materials and change orders.'],
    ],

    'faqs' => [
        ['q_html' => 'Can I list a base package and add-ons on the same invoice?', 'a_html' => '<p>Yes. The base package (Express, Full, Ceramic Coating) goes on the top line. Add-ons (pet hair, heavy dirt, headlight restoration, engine bay) each get their own line.</p>
                            <p>The customer sees what the base price was and what the extras added, which keeps the up-charge conversation short.</p>'],
        ['q_html' => 'Can I set up recurring monthly invoices for membership clients?', 'a_html' => '<p>Yes. Set the client, the package, and the frequency once. Argo Books generates the invoice on schedule for monthly maintenance memberships or weekly fleet accounts.</p>
                            <p>You stop forgetting to bill the regulars.</p>'],
        ['q_html' => 'Can I track ceramic coating warranty information per customer?', 'a_html' => '<p>You can record warranty details on the invoice notes and on the customer record, so the information lives with the customer history. Argo Books does not run a dedicated warranty database with expiration alerts.</p>
                            <p>If that level of warranty tracking is critical, a detail-specific tool like Urable handles it, and you can keep Argo Books for the books.</p>'],
        ['q_html' => 'Does it work without internet (mobile detailers in driveways)?', 'a_html' => '<p>Yes. The desktop app runs natively on your laptop and does not need an internet connection to build the invoice in the driveway.</p>
                            <p>You only need internet when you actually send the invoice or take a card payment.</p>'],
        ['q_html' => 'Does Argo Books have an online booking calendar?', 'a_html' => '<p>No. Argo Books does not run a customer-facing booking calendar or accept reservations through your website.</p>
                            <p>Mobile Tech RX, Urable, and DetailPlus handle that side. Run them alongside Argo Books: those for booking, Argo Books for the books.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The free tier covers all core features and ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'guide_link' => 'Want the bookkeeping side in plain language? Read our guide to <a href="../bookkeeping-for-auto-detailing/">bookkeeping for auto detailing</a>.',

    'cta_h2' => 'Ready to bill like the work is worth it?',
    'cta_p' => 'Download Argo Books for free. Set up your first package, scan a supply receipt, and send a detailing invoice in under ten minutes.',
];
