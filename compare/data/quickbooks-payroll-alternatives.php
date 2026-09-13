<?php
// compare/data/quickbooks-payroll-alternatives.php
//
// Content for /compare/quickbooks-payroll-alternatives/. Layout lives in
// compare/compare-page.php; the price chart in compare/mockups/.
//
// QuickBooks sells the books and payroll as one bundle, so the figures here are a
// bundle price plus the per-employee fee. An earlier version of this page added an
// Online plan to a separate payroll plan and overstated them by $15 a month at five
// staff, which is the direction that gets a comparison page called out.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Also read by compare/mockups/quickbooks-payroll-alternatives.php.
// The cheapest bundle that includes payroll, which is the like-for-like comparison:
// Argo Books Premium is also the books and payroll together.
$qbp_base    = competitor_price('quickbooks-payroll', 'core-easystart');
$qbp_per     = get_competitors()['quickbooks-payroll']['plans']['core-easystart']['per_employee'];
$qbp_top     = competitor_price('quickbooks-payroll', 'premium-plus');
$qbp_top_per = get_competitors()['quickbooks-payroll']['plans']['premium-plus']['per_employee'];

$qb_five     = payroll_monthly_cost('quickbooks-payroll', 'core-easystart', 5);
$qb_gap_year = ($qb_five - $argo_monthly) * 12;

return [
    'competitor' => 'QuickBooks Payroll',

    'breadcrumb' => 'QuickBooks Payroll alternatives',
    'title' => 'QuickBooks Payroll Alternatives for Canada (2026) | Argo Books',
    'meta_description' => 'Looking for a QuickBooks Payroll alternative in Canada? QuickBooks bills payroll on top of a QuickBooks Online subscription, plus a fee per employee. Argo Books is $' . $argo_monthly . ' CAD flat, books and payroll together.',
    'meta_keywords' => 'QuickBooks Payroll alternatives, QuickBooks Payroll alternative Canada, cheaper than QuickBooks Payroll, Canadian payroll software, T4 software, flat rate payroll, payroll without per employee fees, QuickBooks Online alternative',
    'og_title' => 'QuickBooks Payroll alternatives for Canadian small businesses',
    'og_description' => 'QuickBooks charges for the books, then for payroll, then per employee. Argo Books charges once. Compared on deductions, T4s, Quebec and what each one files for you.',

    'hero_h1' => 'QuickBooks Payroll alternatives',
    'hero_subtitle' => 'The cheapest QuickBooks bundle that includes payroll is $' . $qbp_base . ' a month plus $' . $qbp_per . ' per employee. Argo Books does the books and Canadian payroll together for $' . $argo_monthly . ' CAD flat, with no per-employee fee.',

    'differences_h2' => 'What does QuickBooks Payroll actually cost in Canada?',
    'differences_desc' => 'Both bundle the books and payroll, so the comparison is like for like. QuickBooks starts at $' . $qbp_base . ' a month for Payroll Core with EasyStart, plus $' . $qbp_per . ' per employee, which is $' . number_format($qb_five) . ' at five staff. Argo Books Premium is $' . $argo_monthly . ' at five staff and $' . $argo_monthly . ' at fifty, because there is no per-employee component.',
    'why_h3' => 'Why look at Argo Books instead?',
    'why_list' => [
        '<strong>Hiring does not change the bill.</strong> QuickBooks charges $' . $qbp_per . ' per employee per month on top of the bundle. Argo Books has no headcount component at all, so the gap widens with every person you take on.',
        '<strong>Roughly $' . number_format($qb_gap_year) . ' a year less at five employees</strong>, against QuickBooks\' own regular published rate rather than its introductory offer.',
        '<strong>Quebec handled as its own system.</strong> QPP, QPIP, Quebec income tax and the federal abatement, rather than an approximation of the federal calculation.',
        '<strong>Your staff records stay on your computer.</strong> Social insurance numbers and salaries are written locally instead of held in a cloud account.',
        '<strong>No price creep.</strong> The pattern people leave QuickBooks over is the annual increase on a plan they are already deep into. A flat desktop price is a different arrangement.',
    ],

    // Feature, Argo Free, Argo Premium, QuickBooks Payroll.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month, books + payroll',
    'table_competitor_sub' => '$' . $qbp_base . ' + $' . $qbp_per . '/employee',
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
        ['Accounting included, not a separate plan', 'yes', 'yes', 'no'],
        ['Flat price, unlimited employees', 'yes', 'yes', 'no'],
        ['Works offline', 'yes', 'yes', 'no'],
        ['Staff data stored locally', 'yes', 'yes', 'no'],
        ['Auto bank transaction import', 'no', 'no', 'yes'],
        ['Mobile app', 'no', 'no', 'yes'],
    ],

    'pros_cons_h2' => 'Argo Books vs QuickBooks Payroll: pros &amp; cons',
    'argo_pros' => [
        '<strong>One flat $' . $argo_monthly . ' CAD a month</strong> covering the books and Canadian payroll together',
        '<strong>No per-employee fee</strong>, so hiring does not change the bill',
        '<strong>No headcount component</strong>, so there is no bill that grows when you take on staff',
        '<strong>Staff records held locally</strong> rather than in a cloud account',
        '<strong>Runs offline</strong> as a native desktop app for Windows, macOS, and Linux',
    ],
    'argo_cons' => [
        'You upload the T4 XML and remit the source deductions yourself',
        'No direct deposit, so paying staff is still a bank transfer you make',
        'No automatic bank transaction import yet, which QuickBooks does well',
        'No mobile app, so payroll runs on a desktop',
        'Payroll covers Canada only',
    ],
    'competitor_cons' => [
        '<strong>Priced per employee</strong> on top of the bundle, so the monthly bill grows with every hire',
        '<strong>Priced per head</strong>, so the monthly bill grows every time you hire',
        '<strong>Known for price increases</strong> on plans people have already committed their books to',
    ],
    'competitor_pros' => [
        'Files T4s and remits source deductions to the CRA for you',
        'Direct deposit built in, so staff are paid without a separate transfer',
        'Automatic bank feeds, which Argo Books does not have yet',
        'A large accountant network, so most Canadian bookkeepers already know it',
    ],

    'key_h2' => 'The same job, priced per head',
    'key_desc' => 'QuickBooks calculates Canadian deductions correctly and files for you, and for many businesses that is worth paying for. What people bring to a comparison page is rarely the payroll itself. It is that the bill goes up every time they hire.',
    'key_cards' => [
        ['icon' => 'subscription', 'h3' => 'One price, whatever the headcount', 'p' => 'Books, payroll and staff are the same $' . $argo_monthly . ' a month in Argo Books. There is no plan tier tied to how many people you employ.'],
        ['icon' => 'users', 'h3' => 'Hiring is free', 'p' => 'At $' . $qbp_per . ' per employee a month, taking on four people adds about $' . number_format($qbp_per * 4 * 12) . ' a year to a QuickBooks bill. In Argo Books it adds nothing.'],
        ['icon' => 'map-pin', 'h3' => 'Quebec done properly', 'p' => 'QPP, QPIP, Quebec income tax and the federal abatement calculated separately, and the RL-1 figures worked out at year end.'],
    ],

    'honest' => [
        'QuickBooks Payroll does two things Argo Books does not: it files your T4s with the CRA and it remits your source deductions, and it pays your staff by direct deposit. If you want the deadline to be somebody else\'s responsibility, that service is what the per-employee fee buys, and QuickBooks is a reasonable place to buy it.',
        'It is also the software most Canadian bookkeepers already know, and automatic bank feeds are genuinely useful. If your bookkeeper works in QuickBooks and bank feeds are central to how you close a month, switching to save money is likely to cost you more than it saves.',
        'Argo Books is the better fit if you are comfortable uploading the file and making the payment yourself, and the growing monthly bill is what actually bothers you.',
    ],

    'related' => [
        'argo-books-vs-wagepoint',
        'payworks-alternatives',
        'argo-books-vs-quickbooks',
        'sage-50-alternatives',
        'argo-books-vs-wave',
    ],

    'faqs' => [
        ['q_html' => 'How much is QuickBooks Payroll in Canada?', 'a_html' => '<p>The cheapest bundle that includes payroll is <strong>Payroll Core with EasyStart</strong> at about $' . $qbp_base . ' CAD a month plus $' . $qbp_per . ' per employee, so $' . number_format($qb_five) . ' at five staff. The top bundle, Payroll Premium with Plus, is about $' . $qbp_top . ' plus $' . $qbp_top_per . ' per employee.</p>
                            <p>Watch the headline figure: QuickBooks advertises 90% off for the first six months, and a six-month discount is not the price you pay in year two. The rates above are the regular ones.</p>
                            <p>Argo Books Premium is $' . $argo_monthly . ' CAD a month including the books and payroll together, with no per-employee fee.</p>'],
        ['q_html' => 'Is there a free QuickBooks Payroll alternative?', 'a_html' => '<p>Not for payroll itself. Argo Books has a free tier covering invoicing, expenses, receipt scanning and reports, but Canadian payroll is a Premium feature at $' . $argo_monthly . ' CAD a month.</p>
                            <p>Be sceptical of anything advertising free Canadian payroll. Keeping up with two CRA rate editions a year, Quebec\'s separate system and the T4 filing specification is ongoing work, and software that is not funded to do it is the software that is quietly wrong in February.</p>'],
        ['q_html' => 'Will Argo Books file my T4s with the CRA?', 'a_html' => '<p>No. It prepares the T4 slips and summary as PDFs and builds the XML submission file the CRA accepts, including the T619 transmittal record, so the figures are worked out and the file is ready. You upload it through My Business Account and you make the remittance yourself.</p>
                            <p>QuickBooks does file and remit on your behalf. That is a real difference and it is the main reason to stay.</p>'],
        ['q_html' => 'Can I move my QuickBooks data into Argo Books?', 'a_html' => '<p>Partly. Argo Books imports spreadsheets with AI-assisted column matching, so customers, products, and transaction history exported from QuickBooks as CSV can be brought across. Payroll year-to-date figures are the part to be careful with: they have to carry over exactly or your T4s will be wrong at year end.</p>
                            <p>Because of that, the safest time to switch payroll is the start of a calendar year.</p>'],
        ['q_html' => 'Does Argo Books handle Quebec?', 'a_html' => '<p>Yes. Quebec runs its own income tax, pension plan and parental insurance, so Argo Books calculates QPP, QPIP, Quebec income tax and the federal abatement as a separate system, and works out the RL-1 figures at year end. Employees in different provinces can sit on the same pay run.</p>'],
        ['q_html' => 'What about bank feeds?', 'a_html' => '<p>QuickBooks has automatic bank transaction import and Argo Books does not yet. What Argo Books has is AI-assisted bank statement import, so you bring the statement in as a file rather than connecting the account.</p>
                            <p>If automatic feeds are central to how you work, that is a genuine reason to stay with QuickBooks.</p>'],
    ],

    'cta_h2' => 'Add up what payroll is really costing you',
    'cta_p' => 'Put your headcount into the calculator on our payroll page, including the QuickBooks Online plan underneath it, then download Argo Books free and run a period you have already run.',
];
