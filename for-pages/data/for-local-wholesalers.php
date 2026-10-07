<?php
// for-pages/data/for-local-wholesalers.php
//
// Content for the /for-local-wholesalers/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'track_event' => 'paid_lp_local_wholesalers',
    'cta_source' => 'paid-lp-local-wholesalers',

    'breadcrumb' => 'For Local Wholesalers',
    'title' => 'Argo Books for Local Wholesalers: Inventory, Net-30, and Standing Orders',
    'meta_description' => 'Accounting software for local wholesalers supplying restaurants, retailers, and specialty shops. Inventory, net-30 terms, standing orders, built in. Free desktop app.',
    'meta_keywords' => 'accounting software for wholesalers, wholesale distribution bookkeeping, small wholesaler accounting, local distributor software, inventory and invoicing software wholesale',
    'og_title' => 'Argo Books for Local Wholesalers: Inventory, Net-30, and Standing Orders',
    'og_description' => 'Inventory, net-30 invoicing, and standing orders for local distributors. Free desktop app for Windows, macOS, and Linux.',
    'twitter_description' => 'Inventory, net-30 invoicing, and standing orders for local distributors.',

    'h1' => 'Accounting software for local wholesalers',
    'hero_sub' => 'Built for net-30 invoicing, standing orders, and the inventory that has to be on the truck Tuesday morning. Inventory management is included, not an upsell.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card.',
    'demo' => 'inventory',

    'features_label' => 'Made for Local Wholesalers',
    'features_h2' => 'Stock on the truck, money in the bank',
    'features_desc' => 'A wholesale business is the case of stock on the truck, the customer who promised to pay next week, and the receivables report that tells you whether they actually did. Whether you supply restaurants, retail shops, salons, or specialty stores, the work that pays the bills is the standing order delivered on time and the invoice that gets paid by the due date.',
    'benefits' => [
        ['icon' => 'package', 'h3' => 'Inventory and reorder points, built in', 'p' => 'Track stock levels for every SKU, set a reorder point on the items that move, and Argo Books flags what\'s running low before the standing customer calls asking. Receive new stock, log it against the supplier, and the inventory and the books update together.'],
        ['icon' => 'calendar', 'h3' => 'Net-30, net-60, and standing orders', 'p' => 'Set payment terms when you send the invoice. The due date is calculated, the receivables report shows what\'s overdue versus what\'s still inside its window, and standing orders generate themselves on schedule so nothing slips because a regular customer was off your radar this week.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'Snap a receipt from the manufacturer or the freight bill', 'p' => 'Take a photo of the supplier invoice or the freight bill when stock comes in. Argo Books pulls the vendor, date, and amount automatically. Tag it Inventory Purchase, Freight, or Returns so the cost-of-goods picture lines up with what you actually paid.'],
        ['icon' => 'shield-check', 'h3' => 'Works offline, free tier covers small distributors', 'p' => 'Argo Books runs natively on Windows, macOS, and Linux. No internet needed in the warehouse or on the route, no monthly subscription climbing every year, no website to load when you\'re packing a truck. The free tier covers most small distributors forever.'],
    ],

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books handles inventory, customer accounts, net-30 invoicing, and standing orders for local-scale wholesale. It is not a warehouse management system, it does not do Electronic Data Interchange with national retail chains, and it does not optimize delivery routes. If you sell into Walmart, Loblaws, or Sysco-scale customers, NetSuite, Cin7, or Unleashed are built for that and Argo Books is not the right fit. Payroll is for staff in Canada only. For local distributors with dozens of small accounts, Argo Books is the right size. Free desktop app, inventory built in, books stay simple.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'Most local distributors stay on the free tier. Premium adds predictive analytics for stock and cashflow planning, unlimited invoicing for larger account loads, and priority support.',

    'related' => [
        ['href' => '../for-resellers/', 'icon' => 'tag', 'h3' => 'Resellers', 'p' => 'Cost, margin and stock across everything you list.'],
        ['href' => '../for-repair-shops/', 'icon' => 'wrench', 'h3' => 'Repair shops', 'p' => 'Parts, labour and job history against the customer who booked it.'],
        ['href' => '../for-rental-businesses/', 'icon' => 'calendar', 'h3' => 'Rental businesses', 'p' => 'Bookings, availability and returns on one calendar.'],
        ['href' => '../for-software-companies/', 'icon' => 'code-window', 'h3' => 'Software companies', 'p' => 'Subscriptions, contractor costs and runway.'],
    ],

    'faqs' => [
        ['q_html' => 'Does Argo Books actually track inventory and reorder points?', 'a_html' => '<p>Yes. Inventory Management is a built-in feature, not an add-on.</p>
                            <p>Track stock levels, set reorder points for the SKUs that move, and see what\'s running low before the regulars call asking.</p>'],
        ['q_html' => 'Can I set net-30 or net-60 payment terms?', 'a_html' => '<p>Yes. Set the payment terms on the invoice when you send it.</p>
                            <p>The due date is calculated for you, the invoice carries the terms language, and your receivables report shows what\'s overdue versus what\'s still inside its window.</p>'],
        ['q_html' => 'Can I set up a standing order for my recurring accounts?', 'a_html' => '<p>Yes. Set the customer, the line items, the quantities, and the frequency once.</p>
                            <p>Argo Books generates the invoice on schedule, every week or every month, so the standing accounts never get skipped.</p>'],
        ['q_html' => 'Does it work without internet?', 'a_html' => '<p>Yes. The desktop app runs natively on your computer and does not need an internet connection to log a sale, update stock, or build an invoice.</p>
                            <p>You only need internet when you actually send the invoice or take a payment.</p>'],
        ['q_html' => 'Does Argo Books connect to EDI or my retail customers\' purchase order systems?', 'a_html' => '<p>No. Argo Books does not do Electronic Data Interchange (EDI, the digital purchase-order format big chains require from their suppliers), retail-chain purchase order ingestion, or warehouse management with bin locations and pick paths.</p>
                            <p>If you sell into national chains that require EDI, NetSuite, Cin7, or Unleashed are built for that scale. For local wholesalers serving dozens of small accounts, Argo Books fits.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The free tier covers all core features including inventory management and ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'cta_h2' => 'Ready to know what\'s in stock and who owes you what?',
    'cta_p' => 'Download Argo Books for free. Add your first SKU, set a reorder point, and send a net-30 invoice in under ten minutes.',
];
