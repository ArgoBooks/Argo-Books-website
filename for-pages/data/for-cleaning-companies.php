<?php
// for-pages/data/for-cleaning-companies.php
//
// Content for the /for-cleaning-companies/ paid-ad landing page.
// Layout lives in for-pages/lp-page.php.
//
// The FAQ entries drive both the visible accordion and the FAQPage JSON-LD,
// so the two cannot drift apart.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

return [
    'track_event' => 'paid_lp_cleaning_companies',
    'cta_source' => 'paid-lp-cleaning-companies',

    'breadcrumb' => 'For Cleaning Companies',
    'title' => 'Argo Books for Cleaning Companies: Recurring Invoices and Real Numbers',
    'meta_description' => 'Accounting software for residential and commercial cleaning companies. Built for recurring invoices, supply costs, and same-day billing. Free desktop app for Windows, macOS, and Linux.',
    'meta_keywords' => 'accounting software for cleaning companies, cleaning business bookkeeping, janitorial accounting software, residential cleaning invoicing, recurring invoice software cleaning',
    'og_title' => 'Argo Books for Cleaning Companies: Recurring Invoices and Real Numbers',
    'og_description' => 'Recurring invoices, marked-up supplies, and same-day billing, without the bookkeeping headache. Free desktop app.',
    'twitter_description' => 'Recurring invoices, marked-up supplies, and same-day billing, without the bookkeeping headache.',

    'h1' => 'Accounting software for cleaning companies',
    'hero_sub' => 'Built for recurring invoices, supply costs, and the difference between a profitable client and one that\'s quietly losing you money.',
    'hero_facts' => 'Free desktop app for Windows, macOS, and Linux. No account, no credit card.',
    'demo' => 'customers',

    'features_label' => 'Made for Cleaning Companies',
    'features_h2' => 'Recurring clients, real numbers, no spreadsheet',
    'features_desc' => 'A cleaning business is one client at 9 AM, three more in the afternoon, the same houses next week, and a stack of supply receipts on the dashboard of the car. Residential or commercial, solo or with a crew, the work that pays the bills is the recurring contract that\'s billed on time, every time. Argo Books handles the books so you can keep cleaning.',
    'benefits' => [
        ['icon' => 'refresh', 'h3' => 'Recurring invoices for the same client every week or month', 'p' => 'Set the client, the amount, and the frequency once. Argo Books builds the invoice on schedule, every week or every month, with the same line items and the same total. You stop forgetting to bill the residential routes, and the commercial accounts come in clean every cycle.'],
        ['icon' => 'receipt-scan-detail', 'h3' => 'Snap a receipt from Costco, the chemical supplier, or the equipment store', 'p' => 'Take a photo and Argo Books pulls the vendor, date, and amount automatically. Tag it Chemicals, Paper Goods, Equipment, or Vehicle so when you raise your rates next year, you can show the customer where the cost actually went up.'],
        ['icon' => 'send', 'h3' => 'Send the one-time deep clean invoice from the driveway', 'p' => 'Finish the move-out clean, sit in the truck for two minutes, open Argo Books, hit send. Customers can pay through Stripe or Square, and the deposit on next week\'s recurring is already on autopilot.'],
        ['icon' => 'user-focused', 'h3' => 'Pay your cleaners without a separate payroll service', 'p' => 'Enter the hours and Argo Books works out CPP, EI and income tax from the CRA\'s own tables, prints the pay stubs, and puts the wages straight into your books. Staff on different schedules or in different provinces can go on the same run, and your T4s are ready in January. Payroll is on Premium and covers Canadian staff.'],
    ],

    'honest_h3' => 'What Argo Books isn\'t',
    'honest' => [
        'Argo Books is bookkeeping software, not field-service software. It does not run a cleaning calendar, dispatch crews to addresses, send "on the way" texts to clients, or run a per-property profit-and-loss. If you need Jobber, ZenMaid, or Maidily for scheduling and crew routing, run them alongside Argo Books: those for the schedule, Argo Books for the books. Payroll covers Canadian staff only, so a crew outside Canada needs a separate payroll service. If those are dealbreakers, that\'s fair. If they\'re not, the desktop app is free, it works offline, the recurring invoices run themselves, and your data stays on your computer.',
    ],

    'pricing_h2' => 'Start free, upgrade only if you need more',
    'pricing_intro' => 'Most cleaning businesses stay on the free tier. Premium adds predictive analytics for slow-month planning, unlimited invoicing for larger commercial routes, and priority support.',

    'related' => [
        ['href' => '../for-contractors/', 'icon' => 'hard-hat', 'h3' => 'Contractors', 'p' => 'Deposits, mid-job draws, materials and change orders.'],
        ['href' => '../for-landscapers/', 'icon' => 'leaf', 'h3' => 'Landscapers', 'p' => 'Seasonal cash flow, materials at cost, recurring maintenance.'],
        ['href' => '../for-solo-operators/', 'icon' => 'user', 'h3' => 'Solo operators', 'p' => 'One person, one price, books that need no bookkeeper.'],
        ['href' => '../for-auto-detailing/', 'icon' => 'car', 'h3' => 'Auto detailing', 'p' => 'Per-vehicle jobs, products used, and repeat customers.'],
    ],

    'faqs' => [
        ['q_html' => 'Can I set up a recurring invoice for the same client every week or month?', 'a_html' => '<p>Yes. Set the client, the amount, and the frequency once, and Argo Books generates the invoice on schedule.</p>
                            <p>The client gets the same clean invoice every time, you get a payment record every time, and you stop forgetting to bill the recurring residential.</p>'],
        ['q_html' => 'Can I bill supplies as a line on the invoice?', 'a_html' => '<p>Yes. List supplies as their own line, either at cost or with a small markup for sourcing and handling.</p>
                            <p>Many commercial cleaners build supplies into the base rate and never itemize. Residential one-offs sometimes itemize for transparency. Both work in Argo Books.</p>'],
        ['q_html' => 'Can I see which clients or properties are most profitable?', 'a_html' => '<p>You can see total revenue per customer, and total spend per category. Argo Books does not run a per-property profit-and-loss the way a dedicated job-costing tool does, so a ten-house route gets one combined view.</p>
                            <p>If knowing the margin on a single property is critical, a job-costing tool is a better fit.</p>'],
        ['q_html' => 'Does it work without internet?', 'a_html' => '<p>Yes. The desktop app runs natively on your computer and does not need an internet connection to log a cleaning, scan a receipt, or build an invoice.</p>
                            <p>You only need internet when you actually send the invoice or take a payment.</p>'],
        ['q_html' => 'Does Argo Books schedule cleanings or send arrival texts?', 'a_html' => '<p>No. Argo Books does not run a scheduling calendar, dispatch crews, or send "on the way" texts.</p>
                            <p>Jobber, ZenMaid, and Maidily are built for that side. Run them alongside Argo Books: those for the schedule, Argo Books for the books.</p>'],
        ['q_html' => 'Can I run payroll for my cleaners?', 'a_html' => '<p>Yes, for Canadian staff. Enter each person\'s hours and Argo Books works out CPP, EI and federal and provincial income tax from the CRA\'s own tables, for every province and territory, then prints the pay stubs and records the wages in your books.</p>
                            <p>Cleaners on different schedules can go on the same pay run, and at year end it prepares your T4 slips and the file the CRA needs. Payroll is part of Premium at $' . $argo_monthly . ' CAD/month, with no per-employee fee. It does not cover staff outside Canada.</p>'],
        ['q_html' => 'Is it really free?', 'a_html' => '<p>Yes, forever. The free tier covers all core features and ' . $free_invoices . ' invoices per month.</p>
                            <p>Premium ($' . $argo_monthly . ' CAD/month) adds predictive analytics, unlimited invoicing, and priority support. No credit card to start.</p>'],
    ],

    'guide_link' => 'Want the bookkeeping side in plain language? Read our guide to <a href="../bookkeeping-for-cleaning-companies/">bookkeeping for cleaning companies</a>.',

    'cta_h2' => 'Ready to put the recurring routes on autopilot?',
    'cta_p' => 'Download Argo Books for free. Set up your first client, build a recurring weekly invoice, and scan a supply receipt in under ten minutes.',
];
