<?php
// for-pages/data/for-landscapers.php
//
// Content for the /for-landscapers/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'track_event' => 'paid_lp_landscapers',
    'cta_source' => 'paid-lp-landscapers',

    'breadcrumb' => 'For Landscapers',
    'title' => 'Argo Books for Landscapers: Bookkeeping Built for the Way You Bill',
    'meta_description' => 'Accounting software for landscaping businesses. Built for deposits, materials, and seasonal cashflow, without the bookkeeping headache. Free download for Windows, macOS, and Linux.',
    'meta_keywords' => 'accounting software for landscapers, landscaping bookkeeping software, lawn care accounting, landscaper invoicing software, free accounting software landscaping',
    'og_title' => 'Argo Books for Landscapers: Bookkeeping Built for the Way You Bill',
    'og_description' => 'Deposits, materials, and seasonal cashflow, without the bookkeeping headache. Free desktop app for landscaping businesses.',

    'h1' => 'Accounting software for landscaping businesses',
    'hero_sub' => 'Built for the way you actually bill: deposits, materials, and seasonal cashflow, without the bookkeeping headache.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card.',
    'demo' => 'invoices',

    'features_label' => 'Made for Landscapers',
    'features_h2' => 'We built this for the way landscapers actually work',
    'features_desc' => 'Landscaping isn\'t one job at a time. It\'s a deposit on the install, a draw mid-project, a final balance, a stack of fuel and material receipts, and a winter slowdown that hits every year. Argo Books handles the books so you can stay outside.',
    'benefits' => [
        ['icon' => 'dollar', 'h3' => 'Invoice with a deposit, a draw, and a final balance', 'p' => 'Set a deposit up front, send a draw invoice when site prep or planting is done, and a final balance when the job\'s signed off. Argo Books tracks what\'s been paid on each so you don\'t have to keep a separate spreadsheet of who owes what.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'Snap a receipt at the gas pump, the nursery, or Home Depot', 'p' => 'Take a photo, and Argo Books pulls the vendor, date, and amount automatically. Tag it Fuel, Materials, or Equipment so when you look back in March, you actually know where the money went.'],
        ['icon' => 'user-focused', 'h3' => 'Pay the crew without a separate payroll service', 'p' => 'Enter the hours and Argo Books works out CPP, EI and income tax from the CRA\'s own tables, prints the pay stubs, and puts the wages straight into your books. Seasonal staff coming and going is handled, including the Record of Employment file for ROE Web when someone finishes for the year. Payroll is on Premium and covers Canadian staff.'],
        ['icon' => 'send', 'h3' => 'Invoice the same day you finished the job', 'p' => 'Wrap up a property, open Argo Books at the truck or the kitchen table, hit send. Customers can pay through Stripe or Square, and the deposit on the next job can come in before you start it.'],
    ],

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books is bookkeeping software, not field-service software. It does not do crew scheduling, route optimization, or per-property job costing. If you\'re trying to replace Jobber for those, run them side by side: Jobber for scheduling, Argo Books for your books. Payroll covers Canadian staff only, so a crew outside Canada needs a separate payroll service. If those are dealbreakers, that\'s fair. If they\'re not, the desktop app is free, it works without internet, and your data stays on your computer.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'Most landscaping businesses stay on the free tier. Premium adds predictive analytics for seasonal cashflow planning, unlimited invoicing, and priority support.',

    'related' => [
        ['href' => '../for-contractors/', 'icon' => 'hard-hat', 'h3' => 'Contractors', 'p' => 'Deposits, mid-job draws, materials and change orders.'],
        ['href' => '../for-cleaning-companies/', 'icon' => 'spray-bottle', 'h3' => 'Cleaning companies', 'p' => 'Recurring invoices, supplies and staff cost per contract.'],
        ['href' => '../for-repair-shops/', 'icon' => 'wrench', 'h3' => 'Repair shops', 'p' => 'Parts, labour and job history against the customer who booked it.'],
        ['href' => '../for-solo-operators/', 'icon' => 'user', 'h3' => 'Solo operators', 'p' => 'One person, one price, books that need no bookkeeper.'],
    ],

    'faqs' => [
        ['q_html' => 'Do I need Argo Books year-round, or just during the season?', 'a_html' => '<p>Year-round. Winter is when you sort through receipts, set your next-season prices, and see where last year went.</p>
                            <p>The free tier covers winter use with no monthly fee.</p>'],
        ['q_html' => 'Can I track equipment depreciation?', 'a_html' => '<p>You can record equipment purchases and categorize them, and Argo Books will show you the spend in your reports.</p>
                            <p>It does not run a depreciation schedule the way a tax filing software would. Check with your accountant on the tax side.</p>'],
        ['q_html' => 'Does it work without internet?', 'a_html' => '<p>Yes. The desktop app runs natively on your computer and does not need an internet connection to record expenses or build an invoice.</p>
                            <p>You only need internet when you actually send an invoice or take a payment.</p>'],
        ['q_html' => 'Can I bill a deposit and final balance on the same invoice?', 'a_html' => '<p>Two ways: send a single invoice with the deposit listed at the top and a balance due, or send a deposit invoice now and a balance invoice when the job\'s done.</p>
                            <p>Both work. The second is what most landscapers use for multi-week installs.</p>'],
        ['q_html' => 'Is there a phone app?', 'a_html' => '<p>Not yet. Argo Books is a desktop application for Windows, macOS, and Linux.</p>
                            <p>If you need to send an invoice in the field, you can take receipt photos on your phone and import them when you\'re back at the laptop.</p>'],
        ['q_html' => 'Can I run payroll for my crew?', 'a_html' => '<p>Yes, for Canadian staff. Enter each person\'s hours and Argo Books works out CPP, EI and federal and provincial income tax from the CRA\'s own tables, for every province and territory, then prints the pay stubs and records the wages in your books.</p>
                            <p>Seasonal crews coming and going are handled, including the Record of Employment file for ROE Web when someone finishes for the year, and at year end it prepares your T4 slips and the file the CRA needs. Payroll is part of Premium at $' . $argo_monthly . ' CAD/month, with no per-employee fee. It does not cover staff outside Canada.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The free tier covers all core features and ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'guide_link' => 'Want the bookkeeping side in plain language? Read our guide to <a href="../bookkeeping-for-landscapers/">bookkeeping for landscapers</a>.',

    'cta_h2' => 'Ready to clean up the books before the next season?',
    'cta_p' => 'Download Argo Books for free. Set up your first customer, scan a receipt, and send an invoice in under ten minutes.',
];
