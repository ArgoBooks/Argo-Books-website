<?php
// compare/data/argo-books-vs-wagepoint.php
//
// Content for /compare/argo-books-vs-wagepoint/. Layout lives in
// compare/compare-page.php; the price chart in compare/mockups/.
//
// Wagepoint is a payroll service, not an accounting tool, so this page argues
// price shape rather than feature count and concedes the rows Wagepoint wins.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Also read by compare/mockups/argo-books-vs-wagepoint.php.
$wp_solo_base = competitor_price('wagepoint', 'solo');
$wp_unl_base  = competitor_price('wagepoint', 'unlimited');
$wp_solo_per  = get_competitors()['wagepoint']['plans']['solo']['per_employee'];
$wp_unl_per   = get_competitors()['wagepoint']['plans']['unlimited']['per_employee'];

// Five people, on Wagepoint's cheaper plan that allows more than one pay run.
$wp_at_five   = payroll_monthly_cost('wagepoint', 'unlimited', 5);
$wp_gap_year  = ($wp_at_five - $argo_monthly) * 12;

return [
    'competitor' => 'Wagepoint',

    'breadcrumb' => 'Argo Books vs Wagepoint',
    'title' => 'Argo Books vs Wagepoint: Flat-Rate Canadian Payroll | Argo Books',
    'meta_description' => 'Argo Books vs Wagepoint for Canadian payroll. Wagepoint bills a monthly base plus a fee per employee; Argo Books is $' . $argo_monthly . ' CAD flat however many people you pay. CPP, EI, T4s and Quebec compared.',
    'meta_keywords' => 'Argo Books vs Wagepoint, Wagepoint alternative, Canadian payroll software, flat rate payroll Canada, payroll without per employee fees, T4 software Canada, CPP EI calculator, small business payroll Canada',
    'og_title' => 'Argo Books vs Wagepoint: payroll without the per-employee fee',
    'og_description' => 'Wagepoint charges a base fee plus a few dollars per person per month. Argo Books charges one flat price. Compared on price, deductions, T4s and what each one actually files for you.',

    'hero_h1' => 'Argo Books vs Wagepoint',
    'hero_subtitle' => 'Both run Canadian payroll properly, Quebec included. The difference is the bill: Wagepoint charges a base fee plus a few dollars per employee every month, and Argo Books charges $' . $argo_monthly . ' CAD flat however many people you pay.',

    'differences_h2' => 'What\'s the difference between Argo Books and Wagepoint?',
    'differences_desc' => 'Wagepoint is a dedicated cloud payroll service: it calculates the deductions, files with the CRA and moves the money for you, and prices per employee. Argo Books is desktop accounting software with payroll built in: it calculates the same deductions and prepares the same slips, but you do the filing and the payment, and the price does not change when you hire.',
    'why_h3' => 'What changes when you move from Wagepoint to Argo Books',
    'why_list' => [
        '<strong>The bill stops growing when you hire.</strong> Wagepoint Unlimited is $' . $wp_unl_base . ' plus $' . $wp_unl_per . ' per employee per month. At five people that is $' . number_format($wp_at_five) . ' a month; Argo Books is $' . $argo_monthly . ' at five people and $' . $argo_monthly . ' at fifty.',
        '<strong>Payroll is not a separate subscription.</strong> The same $' . $argo_monthly . ' covers invoicing, expenses, receipt scanning, reports and inventory. With Wagepoint you are paying for payroll on top of whatever keeps your books.',
        '<strong>Your staff data stays on your computer.</strong> Names, social insurance numbers and salaries are written to your own machine rather than held by a payroll provider. Wagepoint is cloud only.',
        '<strong>The wages are already in your books.</strong> Approving a run posts the payroll expense directly, so payday matches your bank statement without an export or an integration in between.',
        '<strong>It works offline.</strong> A desktop app for Windows, macOS, and Linux, so a pay run does not depend on a connection or on a provider being up.',
    ],

    // Feature, Argo Free, Argo Premium, Wagepoint.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month, unlimited employees',
    'table_competitor_sub' => 'Unlimited: $' . $wp_unl_base . ' + $' . $wp_unl_per . '/employee',
    'table_rows' => [
        ['CPP, EI &amp; income tax from CRA tables', 'no', 'yes', 'yes'],
        ['Every province and territory', 'no', 'yes', 'yes'],
        ['Quebec deductions (QPP, QPIP)', 'no', 'yes', 'yes'],
        // Revenu Quebec accepts a software-printed RL-1 only from software it has
        // certified, and requires XML above five slips. Argo Books produces the
        // figures on a worksheet; it cannot file them.
        ['RL-1 slips filed with Revenu Quebec', 'no', 'no', 'yes'],
        ['Pay stubs', 'no', 'yes', 'yes'],
        ['T4 slips &amp; summary (PDF)', 'no', 'yes', 'yes'],
        ['T4 XML for CRA filing', 'no', 'yes', 'yes'],
        ['Files the T4s for you', 'no', 'no', 'yes'],
        ['Remits source deductions for you', 'no', 'no', 'yes'],
        ['Direct deposit to staff', 'no', 'no', 'yes'],
        ['ROE earnings &amp; hours worked out for you', 'no', 'yes', 'yes'],
        ['Flat price, unlimited employees', 'yes', 'yes', 'no'],
        ['Accounting included in the price', 'yes', 'yes', 'no'],
        ['Works offline', 'yes', 'yes', 'no'],
        ['Staff data stored locally', 'yes', 'yes', 'no'],
        ['Payroll outside Canada', 'no', 'no', 'no'],
    ],

    'pros_cons_h2' => 'Argo Books vs Wagepoint: pros &amp; cons',
    'argo_pros' => [
        '<strong>One flat price</strong>, $' . $argo_monthly . ' CAD a month whether you pay two people or twenty',
        '<strong>Payroll and accounting in one price</strong>, rather than payroll billed on top of your books',
        '<strong>Staff records stay on your machine</strong>, not with a payroll provider',
        '<strong>Deductions calculated locally</strong> from the CRA\'s own tables, with Quebec handled as its own system',
        '<strong>Wages post straight to your books</strong> when a run is approved, with no export step',
    ],
    'argo_cons' => [
        'You upload the T4 XML and make the remittance yourself; Wagepoint does both for you',
        'No direct deposit, so paying staff is still a bank transfer you make',
        'Records of Employment come out as a file you upload to ROE Web yourself, rather than being filed for you',
        'Desktop only, with no mobile app for running payroll away from your computer',
    ],
    'competitor_cons' => [
        '<strong>Priced per employee</strong>, so the monthly bill grows every time you hire',
        '<strong>Payroll only</strong>, so you are still paying separately for the software that keeps your books',
        '<strong>Cloud only</strong>, so your staff records and salaries live with the provider',
    ],
    'competitor_pros' => [
        'Files your T4s and remits source deductions to the CRA for you',
        'Direct deposit, so staff are paid without a separate bank transfer',
        'Issues Records of Employment properly, rather than a worksheet',
        'A dedicated payroll company with support staff who do only payroll',
    ],

    'key_h2' => 'Same deductions, different bill',
    'key_desc' => 'Both work the deductions out from the CRA\'s published formulas, and both handle Quebec\'s separate system. What differs is how you are charged, how much of the filing is done for you, and where your staff data sits.',
    'key_cards' => [
        ['icon' => 'users', 'h3' => 'Flat, not per head', 'p' => 'Wagepoint Unlimited is $' . $wp_unl_base . ' plus $' . $wp_unl_per . ' per employee a month. Argo Books is $' . $argo_monthly . ' with no headcount component, so the gap widens with every hire.'],
        ['icon' => 'lock', 'h3' => 'Records stay local', 'p' => 'Payroll holds the most sensitive data in a business. Argo Books writes it to your own computer instead of uploading it to a provider.'],
        ['icon' => 'map-pin', 'h3' => 'Quebec done properly', 'p' => 'QPP, QPIP, Quebec income tax and the federal abatement, calculated separately rather than approximated, and the RL-1 figures worked out at year end.'],
    ],

    'honest' => [
        'Wagepoint is a genuinely good payroll service and it does two things Argo Books does not: it files your T4s with the CRA and it remits your source deductions. If you want payroll off your desk entirely, and you would rather pay somebody to be responsible for the filing deadline, Wagepoint is the better purchase and the per-employee fee is what that service costs.',
        'Argo Books makes sense when you are content to upload the file and make the payment yourself, and you would rather not pay a growing monthly fee for the arithmetic. It calculates the same deductions, produces the same slips and builds the XML the CRA accepts, for a price that does not move when you hire.',
    ],

    'related' => [
        'quickbooks-payroll-alternatives',
        'payworks-alternatives',
        'argo-books-vs-quickbooks',
        'argo-books-vs-wave',
        'sage-50-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'Is Argo Books cheaper than Wagepoint?', 'a_html' => '<p>At almost any headcount, yes, because the two are priced differently rather than at different levels. Wagepoint Unlimited is $' . $wp_unl_base . ' a month plus $' . $wp_unl_per . ' per employee; the Solo plan is $' . $wp_solo_base . ' plus $' . $wp_solo_per . ' but allows only one pay run a month. Argo Books Premium is <strong>$' . $argo_monthly . ' CAD a month</strong> with no per-employee component, and that price also covers invoicing, expenses, receipt scanning and reports.</p>
                            <p>At five employees on Wagepoint Unlimited the difference is roughly $' . number_format($wp_gap_year) . ' a year. Use the calculator on our <a href="../../payroll/">payroll page</a> with your own headcount.</p>'],
        ['q_html' => 'Does Argo Books file T4s with the CRA like Wagepoint does?', 'a_html' => '<p>No, and this is the main thing to weigh. Argo Books prepares the T4 slips and summary as PDFs and builds the XML submission file the CRA accepts, including the T619 transmittal record. You upload that file through My Business Account and you make the remittance yourself.</p>
                            <p>Wagepoint files and remits on your behalf. If having somebody else responsible for the February deadline is worth the per-employee fee to you, that is a fair reason to choose Wagepoint.</p>'],
        ['q_html' => 'Does Argo Books handle Quebec payroll?', 'a_html' => '<p>Yes. Quebec administers its own income tax, pension plan and parental insurance, so it is a separate calculation rather than a variation on the federal one. Argo Books calculates QPP, QPIP, Quebec income tax and the federal abatement, and works out the RL-1 figures at year end. Staff in different provinces can appear on the same pay run.</p>'],
        ['q_html' => 'What happens when the CRA changes the rates?', 'a_html' => '<p>Nothing on your side. New tables take effect on 1 January and 1 July each year, and Argo Books fetches the edition covering a pay date the first time it needs it. There is no update to install.</p>
                            <p>If it cannot get that edition it says so and refuses to calculate, rather than quietly using the previous period\'s figures. A wrong deduction is the kind of error nothing downstream catches.</p>'],
        ['q_html' => 'Can I switch from Wagepoint mid-year?', 'a_html' => '<p>You can, but be careful about it. Your year-to-date figures have to carry across correctly or the T4s will be wrong at year end, so the safest points are the start of a calendar year or the start of a quarter.</p>
                            <p>Whenever you switch, run one pay period in both and compare the deductions line by line before you cancel anything.</p>'],
        ['q_html' => 'Does Argo Books do direct deposit?', 'a_html' => '<p>No. Argo Books works out what each person is owed, produces the pay stub and records the expense in your books. Moving the money is still a transfer you make from your bank. Wagepoint includes direct deposit, which is a real convenience if you are paying more than a handful of people.</p>'],
    ],

    'cta_h2' => 'Work out what you are paying per head',
    'cta_p' => 'Put your own headcount into the calculator and see the yearly difference, then download Argo Books free and run a period you have already run in Wagepoint.',
];
