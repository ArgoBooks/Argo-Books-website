<?php
// for-pages/data/for-software-companies.php
//
// Content for the /for-software-companies/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

$stripe_page  = '../integrations/stripe/';
$stripe_docs  = '../documentation/pages/integrations/stripe-integration.php';

return [
    'track_event' => 'paid_lp_software_companies',
    'cta_source' => 'paid-lp-software-companies',

    'breadcrumb' => 'For Software Companies',
    'title' => 'Argo Books for Software and SaaS Companies: Stripe Revenue Straight Into Your Books',
    'meta_description' => 'Accounting software for software and SaaS companies. Connect Stripe with a read-only key and import charges, fees, tax, refunds, and payouts straight into your books. Free desktop app for Windows, macOS, and Linux.',
    'meta_keywords' => 'accounting software for saas, saas bookkeeping software, stripe accounting software, accounting software for software companies, indie hacker bookkeeping, stripe to accounting import',
    'og_title' => 'Argo Books for Software and SaaS Companies: Stripe Revenue Straight Into Your Books',
    'og_description' => 'Connect Stripe with a read-only key. Charges become revenue, processing fees become expenses, refunds and payouts sort themselves out.',
    'twitter_description' => 'Connect Stripe with a read-only key and stop copying charges into a spreadsheet.',
    'features_id' => 'stripe',
    'hero_link_label' => 'See the Stripe Integration',

    'h1' => 'Accounting software for software and SaaS companies',
    'hero_sub' => 'Your revenue already lives in Stripe. Connect it with a read-only key and Argo Books turns every charge into a proper book entry: sales, processing fees, tax, discounts, customers, and refunds.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card.',
    'demo' => 'expenses',

    'features_label' => 'Made for Software Companies',
    'features_h2' => 'You built the product. The bookkeeping should not be the hard part',
    'features_desc' => 'Running a software business means your entire income statement is sitting in one place: Stripe. Hundreds of small charges, a processing fee on every one of them, refunds, tax on some of it, and a payout every few days that lands in your bank as a single lump sum. Most founders end up exporting a CSV every month and cleaning it up by hand. Argo Books reads it straight from Stripe instead, and it is the only free desktop accounting app that does.',
    'benefits' => [
        ['icon' => 'code-window', 'h3' => 'Every charge becomes a real book entry', 'p' => 'Not a lump sum. Each Stripe charge imports with the product name, the customer (created for you if they are new), the sales tax, and any discount you applied. Processing fees are recorded as expenses linked to the sale, so revenue stays gross and your margin is honest.'],
        ['icon' => 'bank', 'h3' => 'Payouts that don\'t get counted twice', 'p' => 'Argo Books remembers every Stripe payout it has seen. When you import your bank statement later, the matching deposit is skipped instead of landing as a second copy of the same revenue. This is the mistake that quietly inflates a founder\'s numbers every year, and here it just doesn\'t happen.'],
        ['icon' => 'shield-check', 'h3' => 'Read-only, reviewed, and undoable', 'p' => 'The key can only read. The sync is on demand, never automatic. Argo shows you a summary of the sales and fees it found and imports nothing until you confirm, and any sync can be undone in one step. You are never one wrong click away from a messy ledger.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'The cost side of a software business', 'p' => 'Hosting, domains, API credits, error monitoring, design contractors, ad spend. Snap or drop in the receipt and Argo pulls the vendor, date, and amount automatically. Tag it once and your real cost base is sitting next to your Stripe revenue instead of scattered across a dozen inboxes.'],
    ],
    'features_intro_2' => [
        'label' => 'The Stripe Integration',
        'h2' => 'Connect once with a read-only key',
        'desc' => 'Create a restricted key in your Stripe dashboard with read access to balance transactions, charges, and payouts. Paste it into Settings, Integrations. That is the whole setup. Argo can see your Stripe activity and can never move a dollar of it.',
    ],
    'features_note' => 'See the <a href="' . $stripe_page . '" class="link">full Stripe integration walkthrough</a>, or jump straight to the <a href="' . $stripe_docs . '" class="link">setup steps in the docs</a>.',

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books does the books, not the billing. Filter revenue to any date range for your monthly or yearly total, which is your MRR and ARR if you bill monthly only. It won\'t split a yearly prepayment over twelve months, so a month where someone pays up front looks unusually big, and it won\'t report churn. Stripe\'s dashboard covers both. Stripe also keeps handling plans, upgrades, and failed payments, and you click sync when you want new activity pulled in. What Argo does is turn a pile of Stripe charges into books you can hand to an accountant, free, on your own computer.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'The Stripe integration is part of the core app, not a paid tier. Premium adds predictive analytics for forecasting where revenue is heading, unlimited invoicing for the enterprise deals you bill directly, and priority support.',

    'related' => [
        ['href' => '../for-solo-operators/', 'icon' => 'user', 'h3' => 'Solo operators', 'p' => 'One person, one price, books that need no bookkeeper.'],
        ['href' => '../for-resellers/', 'icon' => 'tag', 'h3' => 'Resellers', 'p' => 'Cost, margin and stock across everything you list.'],
        ['href' => '../for-rental-businesses/', 'icon' => 'calendar', 'h3' => 'Rental businesses', 'p' => 'Bookings, availability and returns on one calendar.'],
        ['href' => '../for-local-wholesalers/', 'icon' => 'truck', 'h3' => 'Local wholesalers', 'p' => 'Stock, supplier orders and trade accounts on terms.'],
    ],

    'faqs' => [
        ['q_html' => 'How does the Stripe connection work?', 'a_html' => '<p>You create a restricted key in your Stripe dashboard with read access to balance transactions, charges, and payouts, then paste it into Settings, Integrations in Argo Books.</p>
                            <p>The key is read-only, so Argo can see your Stripe activity but can never move money, issue refunds, or change anything in your Stripe account.</p>'],
        ['q_html' => 'What actually gets imported from Stripe?', 'a_html' => '<p>Each Stripe charge becomes a revenue entry with the product name, the customer (created automatically if they are new), any sales tax, and any discount. Processing fees are recorded as expenses linked to the sale. Refunds mark the original sale as returned.</p>
                            <p>Revenue is recorded gross with the fee as a separate expense, so your books stay standard.</p>'],
        ['q_html' => 'Will my Stripe payouts get double-counted when I import my bank statement?', 'a_html' => '<p>No. Argo Books remembers every Stripe payout it has seen, so when you later import your bank statement the matching deposit is skipped instead of being added as a second piece of revenue.</p>
                            <p>This is the part people usually get wrong by hand.</p>'],
        ['q_html' => 'Is the sync automatic?', 'a_html' => '<p>No, and that is deliberate. You click Sync now and Argo shows you a summary of the sales and fees it found before anything is written.</p>
                            <p>Nothing is imported until you confirm, and a sync can be undone in one step.</p>'],
        ['q_html' => 'Can I see my MRR and ARR?', 'a_html' => '<p>Indirectly, and for a lot of software businesses that is enough. You can report revenue over any window you like: This Month, Last 30 Days, This Year, Last 365 Days, or a custom start and end date. If you bill monthly only, your monthly revenue total is your MRR and your yearly total is your ARR, read straight off the dashboard.</p>
                            <p>The gap opens up if you sell annual plans or one-time charges. Argo records an annual charge in full on the day it clears rather than spreading it over twelve months, so a monthly total spikes whenever someone prepays, and a true MRR figure normalizes that away. Stripe\'s own dashboard already gives you the normalized run rate, and there is no churn or cohort retention reporting in Argo Books.</p>'],
        ['q_html' => 'Does it handle deferred revenue on annual plans?', 'a_html' => '<p>No. An annual plan is recorded as revenue on the date the charge went through, not spread across twelve months.</p>
                            <p>If you are on cash-basis accounting, which most small software businesses are, that is exactly what you want. If your accountant needs accrual-basis deferred revenue schedules, Argo Books will not produce them.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The Stripe integration is part of the core app, not a paid add-on, and the free tier includes ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'cta_h2' => 'Ready to stop cleaning up Stripe exports?',
    'cta_p' => 'Download Argo Books for free. Create a read-only Stripe key, run your first sync, and see a month of charges land as real book entries in under ten minutes.',
];
