<?php
// features/data/payroll.php
//
// Content for /features/payroll/. Layout lives in
// features/feature-page.php.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

$argo_yearly = (int) get_pricing_config()['premium_yearly_price'];

return [
    'breadcrumb' => 'Payroll',
    'title' => 'Payroll for Canadian Businesses | Argo Books',
    'meta_description' => 'Run Canadian payroll in Argo Books. CPP, EI and income tax worked out from the CRA\'s own tables for every province, pay stubs for your staff, and T4s at year end, plus an RL-1 worksheet for Quebec and the Record of Employment file for ROE Web.',
    'meta_keywords' => 'Canadian payroll software, small business payroll Canada, CPP EI calculator, T4 software, payroll deductions Canada, RL-1 Quebec payroll, record of employment, desktop payroll software, T4 XML filing',
    'og_title' => 'Payroll for Canadian Businesses | Argo Books',
    'og_description' => 'Pay your staff without a separate payroll service. CPP, EI and income tax from the CRA\'s own tables, pay stubs, and T4s at year end.',
    'offer_price' => $argo_monthly,
    'offer_description' => 'Payroll is included with Premium at $' . $argo_monthly . '/month. Free plan available for the rest of Argo Books.',
    'feature_list' => 'Canadian payroll deductions, Pay stubs, T4 slips and XML, RL-1 worksheet for Quebec, Record of Employment XML for ROE Web',

    'h1' => 'Pay your staff,<br>without a payroll service.',
    'hero_sub' => 'Argo Books works out CPP, EI and income tax from the CRA\'s own tables, prints the pay stubs, posts the wages to your books, and has your T4s ready in January.',
    'hero_facts' => 'Canadian payroll, on Premium at $' . $argo_monthly . ' CAD a month. Every figure is worked out on your own computer.',
    'demo' => 'payroll',

    'steps_h2' => 'Three steps, every payday',
    'steps_lede' => 'You enter what someone earned. Everything after that is arithmetic, and the arithmetic is the part you are paying somebody else for today.',
    'steps' => [
        ['h3' => 'Add your people once', 'p' => 'Province, salary or hourly rate, how often they are paid, and the claim amounts from their TD1. That is the setup, and it is the last time you type it.'],
        ['h3' => 'Enter the pay period', 'p' => 'Hours for anyone hourly, plus any bonus or vacation pay. Deductions and net pay appear as you type, for everyone on the run at once.'],
        ['h3' => 'Approve it', 'p' => 'Pay stubs are ready to hand over, the wages land in your books as expenses, and you are told what the CRA is owed and when.'],
    ],

    'splits_before_cta' => [
        [
            'banner' => 'PRODUCT BLOCK: the deduction engine',
            'bg' => true,
            'eyebrow' => 'The figures',
            'h2' => 'Every province and territory, Quebec included',
            'lede' => 'Quebec administers its own income tax, pension plan and parental insurance, so it is a second calculation rather than a variation on the first one. Argo Books does both. Everywhere else, CPP and the second CPP contribution, EI, federal tax and provincial tax are each tracked against their annual maximums, so contributions stop in the period they are meant to stop rather than at the end of the year.',
            'list' => [
                'Every province and territory, and Quebec\'s separate QPP and QPIP',
                'Annual maximums tracked, so a ceiling reached mid-period is handled',
                'Salaried or hourly, weekly through to monthly, mixed in one run',
                'Bonuses taxed as one-off pay rather than as a raise',
            ],
            'img' => '../../resources/images/features/payroll-deductions.svg',
            'img_alt' => 'An Argo Books pay stub showing gross pay, the CPP, EI, federal and provincial tax deducted, and the resulting net pay',
            'img_w' => 600, 'img_h' => 500,
        ],
    ],

    // Points at /payroll/ rather than /pricing/, because that page has the calculator for a per- employee bill against a flat one.
    'midcta_h2' => 'Payroll without the per-employee bill',
    'midcta_p' => 'Included with Premium at $' . $argo_monthly . ' a month, however many people you pay. See what you are paying per head today.',
    'midcta_href' => '../../payroll/',
    'midcta_label' => 'Compare the cost',

    'benefits_h2' => 'The parts of payroll that go wrong quietly',
    'benefits' => [
        ['icon' => 'calendar-dots', 'h3' => 'You are told what is due, and when', 'p' => 'A regular remitter pays by the 15th of the month after payday. Argo Books names that date and the amount that belongs to it, rather than leaving you to work out which month you are paying for.'],
        ['icon' => 'refresh', 'h3' => 'Rate changes arrive on their own', 'p' => 'The CRA reissues its tables every January and July. The new edition is fetched the first time you need it, so there is nothing to install and no chance of running January on last year\'s figures.'],
        ['icon' => 'shield-check', 'h3' => 'It stops rather than guesses', 'p' => 'If it does not hold the table covering a pay date, it says so and refuses to calculate. A wrong deduction on someone\'s pay is the kind of error nothing downstream catches.'],
        ['icon' => 'dollar', 'h3' => 'The wages are already in your books', 'p' => 'Approving a run records what each person was actually paid as an expense, so payday matches your bank statement instead of being a figure you copy across from somewhere else.'],
    ],

    'splits_after_benefits' => [
        [
            'banner' => 'PRODUCT BLOCK: year end',
            'bg' => true,
            'flip' => true,
            'eyebrow' => 'Year end',
            'h2' => 'Nothing to add up in January',
            'lede' => 'The slips are built from the pay runs you already approved, so there is no year to reassemble: T4s as PDFs to hand out and as the XML file the CRA accepts, and an RL-1 worksheet for Quebec staff from the same screen. You still upload the file and make the payment yourself.',
            'list' => [
                'T4 slips and summary as PDFs, plus the CRA\'s XML with its transmittal record',
                'An RL-1 worksheet and summary for Quebec staff, to key into My Account',
                'Amendments and cancellations chosen per employee, not all or nothing',
                'A Record of Employment file for ROE Web when somebody leaves',
            ],
            'img' => '../../resources/images/features/payroll-year-end.svg',
            'img_alt' => 'An Argo Books year end screen showing a T4 slip with its boxes filled in, and the slips, summary and CRA XML file ready to export',
            'img_w' => 600, 'img_h' => 500,
        ],
        [
            'banner' => 'PRIVACY',
            'eyebrow' => 'Privacy',
            'h2' => 'Your staff records stay on your computer',
            'lede' => 'Payroll holds the most sensitive data in the business: names, social insurance numbers and what everyone earns. Argo Books is a desktop application, so those records are written to your own machine rather than uploaded to a payroll provider, and the deduction figures are calculated there too.',
            'list' => [
                'Employee records and pay history stored locally',
                'No third-party payroll provider holding your staff data',
                'Your data moves and backs up like any other file',
            ],
            'img' => '../../resources/images/privacy-local-storage.svg',
            'img_alt' => 'The Argo Books folder open on a local disk, showing receipts, invoices and the database file stored on this computer',
            'img_w' => 600, 'img_h' => 500,
        ],
    ],

    'who_h2' => 'Built for a small Canadian payroll',
    'who' => [
        ['icon' => 'users', 'h3' => 'Two or three staff', 'p' => 'Where a payroll service\'s monthly base fee is most of what you would pay.'],
        ['icon' => 'user', 'h3' => 'Owner-managers', 'p' => 'Paying yourself a salary, with the CPP and EI exemptions that go with it.'],
        ['icon' => 'wrench', 'h3' => 'Trades and services', 'p' => 'Hourly crews whose hours change every period.'],
        ['icon' => 'map-pin', 'h3' => 'Staff in more than one province', 'p' => 'Different provinces on the same pay run, Quebec included.'],
    ],

    'related_eyebrow' => 'Works with',
    'related_h2' => 'What payroll touches',
    'related' => [
        ['href' => '../expense-revenue-tracking/', 'icon' => 'dollar', 'h3' => 'Expense &amp; revenue tracking', 'p' => 'Where the wages land once a run is approved.'],
        ['href' => '../bank-statement-import/', 'icon' => 'bank', 'h3' => 'Bank statement import', 'p' => 'Payday shows up on the statement already recorded.'],
        ['href' => '../report-builder/', 'icon' => 'report', 'h3' => 'Report builder', 'p' => 'Wages counted in your income statement like any other cost.'],
        ['href' => '../predictive-analytics/', 'icon' => 'analytics', 'h3' => 'Predictive analytics', 'p' => 'Your largest recurring outgoing, in the forecast.'],
    ],

    // Drives both the visible accordion and the FAQPage JSON-LD.
    'faqs' => [
    [
        'q' => 'Which provinces and territories does payroll cover?',
        'a' => 'All of them. Argo Books calculates federal and provincial income tax, CPP and EI for every province and territory, and Quebec is handled through its own system: QPP, QPIP, Quebec income tax and the federal abatement, and an RL-1 worksheet at year end. Payroll is Canada only, so it cannot pay staff in the United States or anywhere else.',
    ],
    [
        'q' => 'Is payroll included in the Free plan?',
        'a' => "No, payroll is a Premium feature. Premium is \${$argo_monthly} CAD a month, or \${$argo_yearly} a year, and includes everything else Premium covers rather than being priced per employee or per pay run. Most payroll services charge a monthly base fee plus a few dollars per person on top, so the comparison is worth doing with your own headcount.",
    ],
    [
        'q' => 'Where do the tax figures come from?',
        'a' => 'From the CRA\'s own payroll deduction formulas, and from Revenu Quebec for Quebec. None of the rates are written into the app: each edition is a data file that Argo Books checks and loads, and every figure is verified against the published tables before it ships.',
    ],
    [
        'q' => 'The CRA changes the rates twice a year. What do I have to do?',
        'a' => 'Nothing. New rates take effect on 1 January and 1 July each year, and Argo Books fetches the new edition the first time you run a pay run that needs it. There is no update to install and no table to type in. If it cannot get the new edition, it tells you and declines to calculate rather than quietly using last period\'s figures.',
    ],
    [
        'q' => 'Does Argo Books file my T4s or send money to the CRA?',
        'a' => 'No. It prepares the T4 slips and summary as PDFs and builds the XML file the CRA accepts, so the figures are worked out and the file is ready, but you upload it and you make the payment yourself. Argo Books also tells you what is owed and the date it is due, so the remittance is not a number you have to work out.',
    ],
    [
        'q' => 'Can it produce a Record of Employment?',
        'a' => 'Yes. When somebody stops being paid, Argo Books builds the ROE file that ROE Web accepts as an upload. It gathers the earnings and hours for the right number of pay periods, which is the part that otherwise means adding up 27 periods by hand on a five day deadline. You choose the reason for issuing, since only you know why they left, and you upload the file yourself.',
    ],
    [
        'q' => 'Can I pay hourly staff, or people on different schedules?',
        'a' => 'Yes. Each employee is salaried or hourly, on a weekly, biweekly, semi-monthly or monthly schedule, and one pay run can include people on different provinces and different rates. Bonuses and vacation pay are entered on the run itself.',
    ],
    ],

    'outro_h2' => 'Run your next payday in Argo Books',
    'outro_p' => 'Download it free, add your people, and see the deductions before you pay for anything. Payroll is included with Premium at $' . $argo_monthly . ' CAD a month.',
];
