<?php
// for-pages/data/for-solo-operators.php
//
// Content for the /for-solo-operators/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'track_event' => 'paid_lp_solo_operators',
    'cta_source' => 'paid-lp-solo-operators',

    'breadcrumb' => 'For Solo Operators',
    'title' => 'Argo Books for Solo Operators with Inventory: One Person, All the Hats',
    'meta_description' => 'Accounting software for solo operators with inventory: candle makers, soap makers, jewelers, garage workshops, single-person retail. Track materials, finished goods, and margins. Free desktop app.',
    'meta_keywords' => 'accounting software for solo business with inventory, small product maker bookkeeping, craft business accounting, etsy maker accounting, single owner inventory software',
    'og_title' => 'Argo Books for Solo Operators with Inventory: One Person, All the Hats',
    'og_description' => 'Materials, finished products, and real margins for one-person businesses. Free desktop app.',
    'twitter_description' => 'Materials, finished products, and real margins for one-person businesses.',

    'h1' => 'Accounting software for solo operators with inventory',
    'hero_sub' => 'Built for one person doing all the jobs: materials, finished goods, customer sales, and the receipts that keep your taxes honest.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card.',
    'demo' => 'ai-receipts',

    'features_label' => 'Made for Solo Operators',
    'features_h2' => 'When you\'re the maker, the packer, the seller, and the bookkeeper',
    'features_desc' => 'A small batch of candles, a tray of soap, a shelf of leather goods, a garage shop turning out one piece at a time. When one person does all the jobs, the books are the job that always gets pushed to Sunday night. Argo Books tracks materials, finished inventory, and sales without making you learn double-entry to do it.',
    'benefits' => [
        ['icon' => 'package-detail', 'h3' => 'Raw materials and finished goods, tracked together', 'p' => 'Track wax, fragrance, jars, and wicks as raw materials. Track your candle line as finished products. When you batch a hundred, record the materials used and the count produced. Inventory always reflects what\'s actually on the shelf, not what was there last spring.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'Snap a receipt from the supplier or the craft store', 'p' => 'Take a photo and Argo Books pulls the vendor, date, and amount automatically. Tag it Materials, Packaging, Shipping Supplies, or Booth Fees so when tax time comes, every deductible expense is sitting in a category.'],
        ['icon' => 'pie-chart', 'h3' => 'See your margin per product, not just per month', 'p' => 'Argo Books shows the gap between what each product cost you to make and what it sold for. Slow-margin items show up as slow. The bestsellers tell you what to make more of. You stop pricing based on vibes and start pricing based on what actually works.'],
        ['icon' => 'shield-check', 'h3' => 'Works offline at the craft fair, free tier covers solo operators', 'p' => 'Argo Books runs natively on Windows, macOS, and Linux. No internet needed at the market booth, no monthly subscription climbing every year, no website to load when the venue wifi is gone. The free tier covers most solo operators forever.'],
    ],

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books does not connect directly to Shopify, Etsy, Square, or other e-commerce platforms. It does not print shipping labels and it does not calculate sales tax across every state or province automatically. If you sell at high volume online and need that automation built in, Shopify\'s or Square\'s built-in accounting may fit better. For solo operators selling at markets, in local boutiques, and through one online shop they update weekly, Argo Books gives you the inventory, margins, and bookkeeping picture without monthly fees stacking up. Free desktop app, your data stays on your computer.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'Most solo operators stay on the free tier. Premium adds predictive analytics so you can see which products are trending up and which are dying, unlimited invoicing, and priority support.',

    'related' => [
        ['href' => '../for-contractors/', 'icon' => 'hard-hat', 'h3' => 'Contractors', 'p' => 'Deposits, mid-job draws, materials and change orders.'],
        ['href' => '../for-landscapers/', 'icon' => 'leaf', 'h3' => 'Landscapers', 'p' => 'Seasonal cash flow, materials at cost, recurring maintenance.'],
        ['href' => '../for-cleaning-companies/', 'icon' => 'spray-bottle', 'h3' => 'Cleaning companies', 'p' => 'Recurring invoices, supplies and staff cost per contract.'],
        ['href' => '../for-repair-shops/', 'icon' => 'wrench', 'h3' => 'Repair shops', 'p' => 'Parts, labour and job history against the customer who booked it.'],
    ],

    'faqs' => [
        ['q_html' => 'Can I track raw materials and finished goods separately?', 'a_html' => '<p>Yes. Argo Books has inventory management built in. Track wax, fragrance oils, wicks, and jars as raw materials, and your candle line as finished products.</p>
                            <p>When you batch-make a hundred candles, record the materials used and the finished count, so the inventory shows what\'s actually on the shelf.</p>'],
        ['q_html' => 'Can I see margin per product?', 'a_html' => '<p>Yes. Record the unit cost when you produce the item and the sale price when it sells, and Argo Books shows the gap.</p>
                            <p>Slow-margin products show up as slow, profitable ones get more attention. You stop pricing based on what feels right and start pricing based on what works.</p>'],
        ['q_html' => 'Can I record cash sales from craft fairs and markets?', 'a_html' => '<p>Yes. Log a batch sale at the end of the market day with the total revenue, quantity per product, and the day\'s expenses (booth fee, parking, fuel).</p>
                            <p>Inventory drops, revenue lands, and the day\'s costs are deducted before tax time, not at it.</p>'],
        ['q_html' => 'Does it work without internet at a craft fair?', 'a_html' => '<p>Yes. The desktop app runs natively on your laptop and does not need an internet connection to log a sale, update inventory, or scan a receipt.</p>
                            <p>You only need internet when you actually send an invoice or take a card payment.</p>'],
        ['q_html' => 'Does Argo Books sync with my Shopify or Etsy shop?', 'a_html' => '<p>No. Argo Books does not connect directly to Shopify, Etsy, Square, or other e-commerce platforms. It also does not print shipping labels or calculate sales tax across every jurisdiction automatically.</p>
                            <p>If you sell at high volume online, Shopify or Square\'s built-in accounting may fit better. For solo operators selling at markets, in local boutiques, and through one online shop they update weekly, Argo Books gives you the books without the monthly fees.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The free tier covers all core features including inventory management and ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'cta_h2' => 'Ready to know what each batch actually earned you?',
    'cta_p' => 'Download Argo Books for free. Track your first raw material, log your first finished batch, and see margin per product in under ten minutes.',
];
