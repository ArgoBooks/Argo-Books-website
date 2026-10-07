<?php
// for-pages/data/for-resellers.php
//
// Content for the /for-resellers/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'track_event' => 'paid_lp_resellers',
    'cta_source' => 'paid-lp-resellers',

    'breadcrumb' => 'For Resellers',
    'title' => 'Argo Books for Resellers: Cost of Goods, Sourcing Receipts, and Real Margins',
    'meta_description' => 'Accounting software for online resellers and thrift flippers. Track cost of goods, sourcing receipts, and margins by channel. Free desktop app for Windows, macOS, and Linux.',
    'meta_keywords' => 'accounting software for resellers, ebay reseller bookkeeping, amazon fba accounting, thrift flipper accounting, online reseller tax software',
    'og_title' => 'Argo Books for Resellers: Cost of Goods, Sourcing Receipts, and Real Margins',
    'og_description' => 'Track what every item cost you, where it sold, and what\'s left in inventory. Free desktop app for resellers.',
    'twitter_description' => 'Track what every item cost you, where it sold, and what\'s left in inventory.',

    'h1' => 'Accounting software for resellers',
    'hero_sub' => 'Track what every item cost you, where it sold, and what the margin actually was. Sourcing receipts, inventory, and the tax-time picture, all in one app.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card.',
    'demo' => 'inventory',

    'features_label' => 'Made for Resellers',
    'features_h2' => 'Buy low, sell higher, remember exactly what each one cost',
    'features_desc' => 'Reselling is the garage sale at 7 AM, the auction lot at noon, the wholesale pallet on Tuesday, and a shelf in the garage that\'s worth more than it looks. At tax time, the IRS wants clean cost-of-goods numbers. Argo Books tracks what you paid for each item, what you sold it for, and what\'s still sitting in inventory, so the margin is real and the deductions are real.',
    'benefits' => [
        ['icon' => 'shopping-bag', 'h3' => 'Every item, from purchase to sale', 'p' => 'Add an item to inventory at the price you paid: the thrift price, the auction-lot unit cost, the wholesale per-piece. When it sells, log the sale at the price you got. The cost-of-goods number that flows into your taxes is exactly what you spent, not an estimate.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'Snap a receipt from the thrift store, the auction, or the wholesale lot', 'p' => 'Take a photo and Argo Books pulls the vendor, date, and amount automatically. Tag it Sourcing, Shipping Supplies, or Vehicle so when the year wraps up, every deductible expense is sitting in a category, not in a shoebox.'],
        ['icon' => 'bar-chart', 'h3' => 'See the margin before tax time, not after', 'p' => 'Argo Books shows revenue, cost of goods, and the gap between them in real time. You stop running the business on vibes. Slow-selling categories show up as slow. Profitable ones get more shelf space.'],
        ['icon' => 'shield-check', 'h3' => 'Works offline, free tier covers solo resellers', 'p' => 'Argo Books runs natively on Windows, macOS, and Linux. No internet needed in the garage or at the auction, no monthly subscription climbing every year. The free tier covers most side-hustle and solo full-time resellers forever.'],
    ],

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books does not connect directly to eBay, Amazon, Etsy, or Mercari. It does not pull your marketplace sales in automatically, it does not print shipping labels, and it does not sync inventory across channels. If you sell at high volume and need that automation, tools like A2X, Link My Books, or QuickBooks Commerce are built for it. For solo and side-hustle resellers who can spend ten minutes a week logging sales by hand or importing a marketplace CSV, Argo Books gives you the cost-of-goods, margin, and tax-prep picture without the integration costs. Free desktop app, no monthly fee creeping up, your data stays on your computer.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'Most resellers stay on the free tier. Premium adds predictive analytics so you can see which categories are trending up and which are dying, unlimited invoicing, and priority support.',

    'related' => [
        ['href' => '../for-local-wholesalers/', 'icon' => 'truck', 'h3' => 'Local wholesalers', 'p' => 'Stock, supplier orders and trade accounts on terms.'],
        ['href' => '../for-rental-businesses/', 'icon' => 'calendar', 'h3' => 'Rental businesses', 'p' => 'Bookings, availability and returns on one calendar.'],
        ['href' => '../for-software-companies/', 'icon' => 'code-window', 'h3' => 'Software companies', 'p' => 'Subscriptions, contractor costs and runway.'],
        ['href' => '../for-solo-operators/', 'icon' => 'user', 'h3' => 'Solo operators', 'p' => 'One person, one price, books that need no bookkeeper.'],
    ],

    'faqs' => [
        ['q_html' => 'Can I track what I paid for each item versus what it sold for?', 'a_html' => '<p>Yes. Add the item to inventory at the price you paid (the thrift price, the auction lot share, the wholesale unit cost), and when it sells, log the sale.</p>
                            <p>The cost-of-goods number for your taxes lines up with what you actually spent.</p>'],
        ['q_html' => 'Can I record sales across eBay, Amazon, and Facebook Marketplace?', 'a_html' => '<p>Yes, by tagging each sale with the channel as the customer or category. Argo Books does not pull the sale in automatically from those platforms, so you enter them manually or import a CSV the marketplace gives you.</p>
                            <p>Many resellers do this weekly and treat it like an end-of-week routine.</p>'],
        ['q_html' => 'Can I track mileage to sourcing trips as an expense?', 'a_html' => '<p>Yes. Log mileage as an expense line with the date and the trip distance, tag it Sourcing or Vehicle, and the totals show up on the expense report.</p>
                            <p>At tax time, the mileage deduction is sitting where you put it.</p>'],
        ['q_html' => 'Does it work without internet at a garage sale or auction?', 'a_html' => '<p>Yes. The desktop app runs natively on your laptop and does not need an internet connection to scan receipts, add inventory, or build a record.</p>
                            <p>Take photos at the auction, enter them later that night at the kitchen table.</p>'],
        ['q_html' => 'Does Argo Books sync with my eBay or Amazon account automatically?', 'a_html' => '<p>No. Argo Books does not connect directly to eBay, Amazon, Etsy, or Mercari APIs.</p>
                            <p>If you sell at high volume and need automated sync, A2X, Link My Books, or QuickBooks Commerce integrate. For a side hustle or solo reseller, a weekly manual or CSV-import workflow with Argo Books gives you clean cost-of-goods and tax-prep numbers without paying for an integration.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The free tier covers all core features including inventory management and ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'cta_h2' => 'Ready to know your margin in real time?',
    'cta_p' => 'Download Argo Books for free. Add your first item to inventory, scan a sourcing receipt, and log a sale in under ten minutes.',
];
