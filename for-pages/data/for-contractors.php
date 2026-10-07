<?php
// for-pages/data/for-contractors.php
//
// Content for the /for-contractors/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'track_event' => 'paid_lp_contractors',
    'cta_source' => 'paid-lp-contractors',

    'breadcrumb' => 'For Contractors',
    'title' => 'Argo Books for Contractors: Bookkeeping Built for Progress Billing',
    'meta_description' => 'Accounting software for general contractors and tradespeople. Built for progress billing, materials, and change orders. Free desktop app for Windows, macOS, and Linux.',
    'meta_keywords' => 'accounting software for contractors, contractor bookkeeping software, construction invoicing software, contractor accounting app, free accounting software contractor',
    'og_title' => 'Argo Books for Contractors: Bookkeeping Built for Progress Billing',
    'og_description' => 'Deposits, mid-job draws, materials, and change orders, without the bookkeeping headache. Free desktop app for contractors.',
    'twitter_description' => 'Deposits, mid-job draws, materials, and change orders, without the bookkeeping headache.',
    'hero_link_label' => 'See what\'s included',
    'related_eyebrow' => 'Other trades',

    'h1' => 'Accounting software<br>for contractors',
    'hero_sub' => 'Built for progress billing: deposits, mid-job draws, materials, and change orders, without the bookkeeping headache.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card, and your books stay on your own computer.',
    'demo' => 'invoices',

    'features_label' => 'Made for Contractors',
    'features_h2' => 'Built for the way contractors actually get paid',
    'features_desc' => 'A contractor invoice isn\'t one number on one piece of paper. It\'s a deposit before any work starts, a draw when the framing is up or the rough-in is done, change orders the homeowner asked for after the bid, materials at cost or with a markup, and a final balance with the deposit and draws already credited. Argo Books handles the books so you can stay on the tools.',
    'benefits' => [
        ['icon' => 'clipboard-check', 'h3' => 'Bill a deposit, a mid-job draw, and a final balance', 'p' => 'Send a deposit invoice before the first day, a draw invoice when framing or rough-in is signed off, and a final balance with the deposit and draws already credited. Argo Books tracks what\'s been paid on each, so the closing balance is exactly what\'s still owed.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'Snap a receipt from Home Depot, the lumber yard, or the supply house', 'p' => 'Take a photo and Argo Books pulls the vendor, date, and amount automatically. Tag it Materials, Subcontractor, Equipment Rental, or Permit so when the customer asks for an itemized statement, you can answer in two minutes instead of two hours.'],
        ['icon' => 'user-focused', 'h3' => 'Pay the crew without a separate payroll service', 'p' => 'Enter the hours and Argo Books works out CPP, EI and income tax from the CRA\'s own tables, prints the pay stubs, and puts the wages straight into your books. Different provinces on the same run is fine, and your T4s are ready in January. Payroll is on Premium and covers Canadian staff.'],
        ['icon' => 'bolt', 'h3' => 'Send the final invoice the day you wrap', 'p' => 'Walk through with the customer, open Argo Books, and send the final invoice before you pack the truck. Customers can pay through Stripe or Square, so the balance can clear before the deposit on the next job needs to land.'],
    ],

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books is bookkeeping software, not construction-management software. It does not do job costing per project, crew scheduling, or bid and estimating. If you\'re trying to replace Buildertrend, CoConstruct, or QuickBooks Contractor for those, run them side by side: those for the project, Argo Books for your books.',
        'Payroll covers Canadian staff only, so a crew outside Canada needs a separate payroll service. If those are dealbreakers, that\'s fair. If they\'re not, the desktop app is free, it works without internet at the job trailer, and your data stays on your computer.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'Most solo contractors and small crews stay on the free tier. Premium adds predictive analytics for cashflow planning between jobs, unlimited invoicing, and priority support.',

    'related' => [
        ['href' => '../for-landscapers/', 'icon' => 'leaf', 'h3' => 'Landscapers', 'p' => 'Seasonal cash flow, materials at cost, and recurring maintenance billing.'],
        ['href' => '../for-repair-shops/', 'icon' => 'wrench', 'h3' => 'Repair shops', 'p' => 'Parts, labour and job history tracked against the customer who booked it.'],
        ['href' => '../for-cleaning-companies/', 'icon' => 'spray-bottle', 'h3' => 'Cleaning companies', 'p' => 'Recurring invoices, supplies, and staff costs per contract.'],
        ['href' => '../for-solo-operators/', 'icon' => 'user', 'h3' => 'Solo operators', 'p' => 'One person, one price, and books that do not need a bookkeeper.'],
    ],

    'faqs' => [
        ['q_html' => 'Can I bill a deposit, a mid-job draw, and a final balance?', 'a_html' => '<p>Yes. Most contractors send three invoices on a multi-week job: a deposit invoice before work begins, a draw invoice at a milestone like framing or rough-in, and a final invoice when the work is signed off.</p>
                            <p>Argo Books tracks what\'s been paid on each so the final balance lines up with what the customer still owes.</p>'],
        ['q_html' => 'Can I bill change orders without re-issuing the original invoice?', 'a_html' => '<p>Yes. Add each change order as its own line item on the next progress invoice, or send a separate change-order invoice.</p>
                            <p>Keeping changes on their own lines makes it easy for the customer to see exactly what they signed off on versus the original scope.</p>'],
        ['q_html' => 'Can I track materials and labor separately?', 'a_html' => '<p>Yes. List materials and labor on separate lines of the invoice, or roll materials into a single marked-up line if that\'s how you priced the bid.</p>
                            <p>On the expense side, scan the supply-house receipt and tag it Materials, Equipment, or Subcontractor so the report later actually means something.</p>'],
        ['q_html' => 'Does it work without internet at the job site?', 'a_html' => '<p>Yes. The desktop app runs natively on your computer and does not need an internet connection to record expenses or build an invoice.</p>
                            <p>You only need internet when you actually send the invoice or take a payment.</p>'],
        ['q_html' => 'Does Argo Books do job costing per project?', 'a_html' => '<p>Not the way QuickBooks Contractor or a dedicated job-costing tool does. Argo Books tracks expenses by category and revenue by customer and invoice, which covers most solo contractors and small crews.</p>
                            <p>If you need a true per-project P&L across labor, materials, subs, and overhead, a job-costing tool is a better fit. Many contractors run a simpler bookkeeping tool alongside their estimating or scheduling software.</p>'],
        ['q_html' => 'Can I run payroll for my crew?', 'a_html' => '<p>Yes, for Canadian staff. Enter each person\'s hours and Argo Books works out CPP, EI and federal and provincial income tax from the CRA\'s own tables, for every province and territory, then prints the pay stubs and records the wages in your books.</p>
                            <p>At year end it prepares your T4 slips and the file the CRA needs, and when someone finishes it gathers the figures for their Record of Employment. Payroll is part of Premium at $' . $argo_monthly . ' CAD/month, with no per-employee fee. It does not cover staff outside Canada.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The free tier covers all core features and ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'guide_link' => 'Want the bookkeeping side in plain language? Read our guide to <a href="../bookkeeping-for-contractors/">bookkeeping for contractors</a>.',

    'cta_h2' => 'Ready to clean up the books before the next bid?',
    'cta_p' => 'Download Argo Books for free. Set up your first customer, scan a supply-house receipt, and send a progress invoice in under ten minutes.',
];
