<?php
// compare/data/payworks-alternatives.php
//
// Content for /compare/payworks-alternatives/. Layout lives in
// compare/compare-page.php; the price chart in compare/mockups/.
//
// Payworks quotes per client and publishes no rate card, so this page argues
// service-versus-software and treats its figures as indicative throughout.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Also read by compare/mockups/payworks-alternatives.php.
$pw_base = competitor_price('payworks', 'payroll');
$pw_per  = get_competitors()['payworks']['plans']['payroll']['per_employee'];

$pw_at_ten   = payroll_monthly_cost('payworks', 'payroll', 10);
$pw_gap_year = ($pw_at_ten - $argo_monthly) * 12;

return [
    'competitor' => 'Payworks',

    'breadcrumb' => 'Payworks alternatives',
    'title' => 'Payworks Alternatives for Small Canadian Businesses | Argo Books',
    'meta_description' => 'Looking for a Payworks alternative? Payworks is a quoted service contract billed per employee. Argo Books is desktop software with Canadian payroll built in at $' . $argo_monthly . ' CAD flat, with T4s and Quebec included.',
    'meta_keywords' => 'Payworks alternatives, Payworks alternative Canada, cheaper payroll than Payworks, Canadian payroll software small business, flat rate payroll Canada, T4 software, payroll for two employees Canada',
    'og_title' => 'Payworks alternatives for small Canadian businesses',
    'og_description' => 'Payworks is a quoted payroll service aimed at established employers. Argo Books is software you download, with Canadian payroll, T4s and Quebec at one flat price.',

    'hero_h1' => 'Payworks alternatives',
    'hero_subtitle' => 'Payworks is a managed payroll service: a quote, an onboarding process, and a bill that scales with headcount. Argo Books is software you download and run, with Canadian payroll, T4s and Quebec included at $' . $argo_monthly . ' CAD a month.',

    'differences_h2' => 'What\'s the difference between Argo Books and Payworks?',
    'differences_desc' => 'Payworks sells a service. You are quoted a rate, you are onboarded, and there is a person on the other end who handles remittances and filings for you. Argo Books sells software. You download it, the deductions are calculated on your own computer, and you do the filing yourself. Payworks does not publish a rate card, so the figures here are typical small-business rates from third-party reviews rather than a quote you can hold them to.',
    'why_h3' => 'Why look at Argo Books instead?',
    'why_list' => [
        '<strong>No quote and no onboarding.</strong> Download it, add your people, run a period. There is no sales process between you and finding out whether it works.',
        '<strong>A flat price that is published.</strong> $' . $argo_monthly . ' CAD a month, the same for everyone, with no headcount component and nothing to negotiate.',
        '<strong>Payroll is not a separate service.</strong> The same price covers invoicing, expenses, receipt scanning, reports and inventory, so payday and the books are one system.',
        '<strong>Right-sized for a very small payroll.</strong> A managed service\'s base fee is most of the bill when you are paying two or three people, which is where a flat software price makes the most difference.',
        '<strong>Staff records stay on your machine</strong>, rather than being held by a payroll provider.',
    ],

    // Feature, Argo Free, Argo Premium, Payworks.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month, unlimited employees',
    'table_competitor_sub' => 'Quoted, roughly $' . $pw_base . ' + $' . $pw_per . '/employee',
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
        ['Time tracking &amp; scheduling', 'no', 'no', 'yes'],
        ['HR and benefits administration', 'no', 'no', 'yes'],
        ['Published price, no quote needed', 'yes', 'yes', 'no'],
        ['Flat price, unlimited employees', 'yes', 'yes', 'no'],
        ['Accounting included in the price', 'yes', 'yes', 'no'],
        ['Works offline', 'yes', 'yes', 'no'],
    ],

    'pros_cons_h2' => 'Argo Books vs Payworks: pros &amp; cons',
    'argo_pros' => [
        '<strong>A published flat price</strong>, $' . $argo_monthly . ' CAD a month, with nothing to negotiate',
        '<strong>No sales process</strong>, so you can test it against a period you have already run before speaking to anyone',
        '<strong>Payroll and accounting together</strong>, rather than a payroll service alongside separate books',
        '<strong>Sized for two or three staff</strong>, where a managed service\'s base fee dominates the bill',
        '<strong>Staff records held locally</strong> on your own computer',
    ],
    'argo_cons' => [
        'No managed service, so remittances and T4 filing stay your responsibility',
        'No direct deposit, time tracking, scheduling or HR administration',
        'No account manager to call, which established employers often want',
        'Desktop only, with no mobile app for running payroll away from your computer',
        'Payroll covers Canada only',
    ],
    'competitor_cons' => [
        '<strong>Priced per employee</strong> on top of a base fee, so the bill grows with headcount',
        '<strong>No published rate card</strong>, so comparing means requesting a quote first',
        '<strong>Built for established employers</strong>, which makes it heavy for a business paying two or three people',
    ],
    'competitor_pros' => [
        'A managed service: remittances and filings are handled for you',
        'Direct deposit, time tracking, scheduling and HR administration in one place',
        'A named support contact rather than a help centre',
        'A long-established Canadian provider with a strong reputation among larger small businesses',
    ],

    'key_h2' => 'Two very different purchases',
    'key_desc' => 'This is less a feature comparison than a choice about what you are buying. A managed payroll service costs more and takes the work away. Software costs less and leaves the filing with you. Neither is the right answer in general, and the deciding factor is usually headcount.',
    'key_cards' => [
        ['icon' => 'users', 'h3' => 'Where the base fee bites', 'p' => 'On a two-person payroll, most of a managed service\'s bill is the base fee rather than the work. That is the size where a flat software price makes the clearest difference.'],
        ['icon' => 'document-lines', 'h3' => 'Nothing to negotiate', 'p' => 'A published price means you can compare without a call, a quote or a discovery process. Download it and run a pay period you already know the answer to.'],
        ['icon' => 'map-pin', 'h3' => 'Quebec done properly', 'p' => 'QPP, QPIP, Quebec income tax and the federal abatement calculated separately, and the RL-1 figures worked out at year end.'],
    ],

    'honest' => [
        'Payworks is a well-regarded Canadian provider and it does considerably more than payroll: time tracking, scheduling, HR and benefits administration, plus remittances and filings handled on your behalf. If you employ enough people that those things are jobs rather than tasks, a managed service is the right purchase and Argo Books is not competing for it.',
        'Argo Books is aimed at the other end: a business paying two, three or five people, where a base fee plus a per-head charge buys mostly arithmetic you could do yourself if the software were reliable. If you want an account manager and somebody else responsible for the CRA deadline, stay with the service.',
    ],

    'related' => [
        'argo-books-vs-wagepoint',
        'quickbooks-payroll-alternatives',
        'sage-50-alternatives',
        'argo-books-vs-quickbooks',
        'argo-books-vs-xero',
    ],

    'faqs' => [
        ['q_html' => 'How much does Payworks cost?', 'a_html' => '<p>Payworks quotes per client rather than publishing a rate card, so the honest answer is that it depends on your headcount, pay frequency and which modules you take. Third-party reviews in 2026 put small-business payroll at roughly $' . $pw_base . ' CAD a month plus about $' . $pw_per . ' per employee, but treat that as an indication and get your own quote.</p>
                            <p>Argo Books Premium is $' . $argo_monthly . ' CAD a month, published, with no per-employee component.</p>'],
        ['q_html' => 'Is Argo Books a payroll service or payroll software?', 'a_html' => '<p>Software. It calculates CPP, EI and income tax from the CRA\'s own tables on your computer, produces pay stubs, posts the wages to your books, and prepares T4 slips with the XML file the CRA accepts. You upload that file and make the remittance yourself.</p>
                            <p>Payworks is a service: it does the remitting and the filing for you. That is the difference the price reflects.</p>'],
        ['q_html' => 'I only pay two people. Is a payroll service worth it?', 'a_html' => '<p>That is the size where it is worth doing the arithmetic. On a two-person payroll most of a managed service\'s monthly bill is the base fee rather than anything to do with the work, and the actual task is a calculation, a pay stub and a remittance on a known date.</p>
                            <p>If you are comfortable making that payment yourself, software is a reasonable answer at that size. If payroll is the thing you most want off your desk, it is not.</p>'],
        ['q_html' => 'Does Argo Books handle Quebec?', 'a_html' => '<p>Yes. Quebec administers its own income tax, pension plan and parental insurance, so Argo Books calculates QPP, QPIP, Quebec income tax and the federal abatement separately from the federal system, and works out the RL-1 figures at year end. One pay run can include staff in different provinces.</p>'],
        ['q_html' => 'What about time tracking and scheduling?', 'a_html' => '<p>Argo Books does not do either. Payworks does, and if your crew\'s hours come from a scheduling system that feeds payroll directly, that integration is worth real money and Argo Books will not replace it.</p>
                            <p>Argo Books takes hours as an entry on the pay run, which suits a small team whose hours you already know.</p>'],
        ['q_html' => 'Can I try it before committing?', 'a_html' => '<p>Yes, and it is the right way to evaluate any payroll change. Download Argo Books free, add your people, and enter a pay period you have already run through Payworks. Compare the deductions line by line. Payroll is the one category where a feature list tells you nothing and a matching net pay figure tells you everything.</p>'],
    ],

    'cta_h2' => 'Compare it against a period you have already run',
    'cta_p' => 'Download Argo Books free, enter a pay period you have run through Payworks, and check the deductions line by line before you change anything.',
];
