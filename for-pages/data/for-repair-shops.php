<?php
// for-pages/data/for-repair-shops.php
//
// Content for the /for-repair-shops/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'track_event' => 'paid_lp_repair_shops',
    'cta_source' => 'paid-lp-repair-shops',

    'breadcrumb' => 'For Repair Shops',
    'title' => 'Argo Books for Repair Shops: Parts, Labor, and the Books, Together',
    'meta_description' => 'Accounting software for auto, appliance, electronics, and small-engine repair shops. Built for diagnostic fees, parts markup, and labor on one invoice. Free desktop app.',
    'meta_keywords' => 'accounting software for repair shops, repair shop bookkeeping, auto repair invoicing software, appliance repair accounting, small engine repair software',
    'og_title' => 'Argo Books for Repair Shops: Parts, Labor, and the Books, Together',
    'og_description' => 'Diagnostic fee, parts at your markup, and labor at your shop rate, on one clean invoice. Free desktop app for repair shops.',
    'twitter_description' => 'Diagnostic fee, parts at your markup, and labor at your shop rate, on one clean invoice.',

    'h1' => 'Accounting software for repair shops',
    'hero_sub' => 'Built for the way you actually bill: the diagnostic fee, parts at your markup, and labor at your shop rate, on one clean invoice.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card.',
    'demo' => 'customers',

    'features_label' => 'Made for Repair Shops',
    'features_h2' => 'Diagnostic fee, parts, labor, paid',
    'features_desc' => 'A repair invoice is the diagnostic fee, the labor at your shop rate, the parts at your markup, and sometimes a deposit before the part even leaves the supplier. Whether you fix cars, appliances, small engines, or electronics, Argo Books handles the books so you can stay at the bench.',
    'benefits' => [
        ['icon' => 'document-lines', 'h3' => 'Diagnostic fee, parts, and labor on one clean invoice', 'p' => 'Itemize the diagnostic fee, each part at the price the customer pays, and labor in hours at your shop rate. The customer sees exactly what they paid for, which cuts the awkward conversation at pickup down to a thank-you and a card swipe.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'Snap a receipt from the parts supplier or hardware store', 'p' => 'Take a photo and Argo Books pulls the vendor, date, and amount automatically. Tag it Parts, Shop Supplies, Tools, or Fluids so when you actually look at margins next quarter, the numbers are sitting where you put them.'],
        ['icon' => 'shield-check', 'h3' => 'Works offline at the bench, your data stays on your computer', 'p' => 'Argo Books runs natively on Windows, macOS, and Linux. No internet needed to log a repair or build an invoice, no monthly subscription climbing every year, no website timing out when the shop wifi flakes. The free tier covers most one- and two-person shops forever.'],
        ['icon' => 'credit-card', 'h3' => 'Get paid before the customer drives off', 'p' => 'Hand over the keys, swipe the card through Stripe or Square, and the invoice is marked paid. Or email the invoice on the spot and the customer can pay from the parking lot. Either way, you don\'t carry the balance home.'],
    ],

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books is bookkeeping software, not shop management software. It does not run a work-order queue, send pickup-ready texts to customers, or look up labor times from a VIN. If you need Shopmonkey, Tekmetric, or RepairShopr for the front-of-shop workflow, run them side by side: those for the queue, Argo Books for your books. Payroll is for staff in Canada only. If those are dealbreakers, that\'s fair. If they\'re not, the desktop app is free, the books stay simple, and your data stays on your computer.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'Most one- and two-person shops stay on the free tier. Premium adds predictive analytics for slow-season planning, unlimited invoicing, and priority support.',

    'related' => [
        ['href' => '../for-auto-detailing/', 'icon' => 'car', 'h3' => 'Auto detailing', 'p' => 'Per-vehicle jobs, products used, and repeat customers.'],
        ['href' => '../for-contractors/', 'icon' => 'hard-hat', 'h3' => 'Contractors', 'p' => 'Deposits, mid-job draws, materials and change orders.'],
        ['href' => '../for-local-wholesalers/', 'icon' => 'truck', 'h3' => 'Local wholesalers', 'p' => 'Stock, supplier orders and trade accounts on terms.'],
        ['href' => '../for-solo-operators/', 'icon' => 'user', 'h3' => 'Solo operators', 'p' => 'One person, one price, books that need no bookkeeper.'],
    ],

    'faqs' => [
        ['q_html' => 'Can I itemize the diagnostic fee, parts, and labor on one invoice?', 'a_html' => '<p>Yes. Add each as its own line: a diagnostic fee, each part with quantity and unit price, and labor in hours at your shop rate.</p>
                            <p>Customers see exactly what they paid for, which cuts down on the "why is the bill this high" conversation at pickup.</p>'],
        ['q_html' => 'Can I mark up parts on the invoice?', 'a_html' => '<p>Yes, two ways. List parts at the price the customer pays (your cost plus markup) and keep the wholesale cost in the expense record. Or itemize parts at cost with a separate handling-and-stocking line.</p>
                            <p>Either approach works. Most shops keep the markup invisible and just show the customer-facing price.</p>'],
        ['q_html' => 'Can I take a deposit on a big repair before ordering parts?', 'a_html' => '<p>Yes. Send a deposit invoice with the parts-cost line, take the payment, and when the repair is done send the final invoice with the deposit already credited.</p>
                            <p>The remaining balance is what the customer pays at pickup.</p>'],
        ['q_html' => 'Does it work without internet at the bench?', 'a_html' => '<p>Yes. The desktop app runs natively on your shop computer and does not need an internet connection to record expenses or build an invoice.</p>
                            <p>You only need internet when you actually send the invoice or take a card payment.</p>'],
        ['q_html' => 'Does Argo Books have work orders or customer text messaging?', 'a_html' => '<p>Not in the way Shopmonkey, Tekmetric, or RepairShopr do. Argo Books handles the books, the invoice, and the receipt of payment.</p>
                            <p>If you need a work-order queue, a customer-facing pickup notification, or a VIN-and-labor-guide library, a dedicated shop management tool is a better fit. Many shops run one of those alongside a simpler bookkeeping tool.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The free tier covers all core features and ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'cta_h2' => 'Ready to close out the books like you close out a repair?',
    'cta_p' => 'Download Argo Books for free. Set up your first customer, scan a parts receipt, and send a repair invoice in under ten minutes.',
];
