<?php
// /payroll/ - dedicated landing page for the Canadian payroll buyer.
//
// Not the same page as /features/payroll/, which explains the feature to
// somebody already on the site. This is the destination for payroll search and
// payroll video, and it argues one point: every Canadian payroll provider bills
// a base fee plus a fee per employee, and Argo Books does not.
//
// Provider prices come from config/competitors.json via get_payroll_competitors().
// Those entries carry VERIFY notes naming the vendor page to check.

require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../config/pricing.php';
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../track_referral.php';
require_once __DIR__ . '/../partials/fonts.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('payroll_landing');
}

$pricing      = get_pricing_config();
$argo_monthly = (int) $pricing['premium_monthly_price'];
$argo_yearly  = (int) $pricing['premium_yearly_price'];

$providers = get_payroll_competitors();

// The headcount the page renders on first paint. Chosen because it is the size
// where the per-employee fee has clearly overtaken the base fee but the
// business is still small enough to be doing payroll itself.
$default_headcount = 5;

// Each provider's cheapest plan that allows unlimited pay runs, not its
// cheapest plan outright: Wagepoint and QuickBooks both sell a Solo tier capped
// at one pay run a month, which a business paying biweekly cannot buy.
$rows = [];
foreach ($providers as $slug => $brand) {
    $pick = null;
    foreach ($brand['plans'] as $plan_key => $plan) {
        $cost = payroll_monthly_cost($slug, $plan_key, $default_headcount);
        if ($cost === null) {
            continue;
        }
        $candidate = [
            'key'   => $plan_key,
            'label' => $plan['label'],
            'base'  => (float) ($plan['monthly'] ?? 0),
            'per'   => (float) ($plan['per_employee'] ?? 0),
            'limit' => $plan['_limit'] ?? '',
            'cost'  => $cost,
        ];
        if ($pick === null) {
            $pick = $candidate;
            continue;
        }
        // An uncapped plan always beats a capped one; between two of the same
        // kind, the cheaper wins.
        $capped_now  = $candidate['limit'] !== '';
        $capped_pick = $pick['limit'] !== '';
        if ($capped_pick && !$capped_now) {
            $pick = $candidate;
        } elseif ($capped_pick === $capped_now && $candidate['cost'] < $pick['cost']) {
            $pick = $candidate;
        }
    }
    if ($pick !== null) {
        $rows[] = ['name' => $brand['name'], 'slug' => $slug] + $pick;
    }
}
usort($rows, fn($a, $b) => $a['cost'] <=> $b['cost']);

// Handed to the calculator script so the arithmetic in the browser and the
// arithmetic in PHP read the same figures.
$calc_data = [
    'argo'      => $argo_monthly,
    'providers' => array_map(fn($r) => [
        'name'  => $r['name'],
        'plan'  => $r['label'],
        'base'  => $r['base'],
        'per'   => $r['per'],
        'limit' => $r['limit'],
    ], $rows),
];

$page_url = 'https://argorobots.com/payroll/';
$title    = 'Canadian Payroll Software, Flat Rate | T4s and CPP/EI | Argo Books';
$meta_description = 'Canadian payroll without the per-employee bill. CPP, EI and income tax from the CRA\'s own tables, every province plus Quebec, pay stubs, and T4 slips with the XML the CRA accepts. $' . $argo_monthly . ' CAD a month, however many people you pay.';

// Purchase-decision questions only. /features/payroll/ covers how the feature
// works and emits its own FAQPage JSON-LD, so nothing asked there is repeated
// here; duplicate FAQ markup on one domain splits its own authority.
$faqs = [
    [
        'q' => 'What does it cost if I have ten employees?',
        'a' => 'The same as if you have one. Payroll is part of Argo Books Premium at $' . $argo_monthly . ' CAD a month, or $' . $argo_yearly . ' a year, with no per-employee fee and no per-pay-run fee. Every other Canadian payroll provider charges a monthly base fee plus a few dollars per person per month, which is why the gap in the comparison above widens with every person you hire.',
    ],
    [
        'q' => 'Do I need a separate accounting subscription as well?',
        'a' => 'No, and this is where the comparison above understates the difference. Payroll providers do payroll, so you are also paying for whatever keeps your books, and QuickBooks Payroll specifically is an add-on that requires a QuickBooks Online subscription underneath it. Argo Books Premium is one charge covering the payroll, the ledger, invoicing, expenses, receipt scanning and reports. The free tier covers everything except payroll, so you can set the company up before paying anything.',
    ],
    [
        'q' => 'What does Argo Books not do that a payroll service does?',
        'a' => 'Three things, and they are the reason a service costs more. It does not file your T4s: it builds the slips and the XML submission the CRA accepts, including the T619 transmittal record, and you upload it through My Business Account. It does not remit your source deductions: it tells you the amount and the date it is due, and you make the payment. And it has no direct deposit, so paying staff is still a transfer from your bank. If you want the February deadline to be somebody else\'s responsibility, that is worth paying a per-employee fee for and you should.',
    ],
    [
        'q' => 'How do I check it is calculating correctly before I trust it?',
        'a' => 'Run a pay period you have already run somewhere else and compare line by line: gross, CPP, EI, federal tax, provincial tax, net. If the net pay matches to the cent, the calculation matches. This is the only test of payroll software worth doing, it takes about ten minutes, and it works before you have paid for anything because you can set the company up and enter your people on the free plan.',
    ],
    [
        'q' => 'Can I switch mid-year?',
        'a' => 'You can, and it is the question to think hardest about with any payroll change. Your year-to-date figures have to carry across exactly or the T4s will be wrong at year end, so the safest moves are the start of a calendar year or the start of a quarter. Whichever point you pick, run one period in both systems and compare before you cancel anything.',
    ],
    [
        'q' => 'Is my staff data sent anywhere?',
        'a' => 'No. Argo Books is a desktop application, so employee records, social insurance numbers and pay history are written to your own computer and the deductions are calculated there. There is no payroll provider holding your staff data and no account to create. Your books move and back up like any other file, which also means the backups are yours to keep.',
    ],
    [
        'q' => 'Is there a genuinely free Canadian payroll option?',
        'a' => 'Not one worth putting your filings through. Keeping current with two CRA rate editions a year, Quebec\'s separate system, and a T4 specification that changes inside the year is continuous work, and software nobody funds to do that is the software that is quietly wrong in February. Argo Books charges $' . $argo_monthly . ' a month for payroll because that maintenance has to be paid for. The free tier covers the rest of the app indefinitely.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Argo">

    <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta name="keywords" content="Canadian payroll software, payroll software Canada, T4 software, T4 XML filing, CPP EI calculator, small business payroll Canada, flat rate payroll, payroll without per employee fees, Wagepoint alternative, QuickBooks Payroll alternative, RL-1 Quebec payroll, desktop payroll software">

    <meta property="og:title" content="Canadian payroll without the per-employee bill">
    <meta property="og:description" content="CPP, EI and income tax from the CRA's own tables, every province plus Quebec, and T4 slips with the XML the CRA accepts. One flat price, however many people you pay.">
    <meta property="og:url" content="<?= $page_url ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Canadian payroll without the per-employee bill">
    <meta name="twitter:description" content="CPP, EI and income tax from the CRA's own tables, every province plus Quebec, and T4 slips with the XML the CRA accepts. One flat price, however many people you pay.">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">

    <link rel="canonical" href="<?= $page_url ?>">

    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", "Payroll" => "/payroll/"]) ?></script>
    <script type="application/ld+json"><?= argo_faq_schema($faqs) ?></script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title><?= htmlspecialchars($title) ?></title>

    <script src="../resources/scripts/main.js"></script>

    <?= argo_font_links('default', '    ') ?>

    <link rel="stylesheet" href="../resources/styles/marketing-sections.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
    <link rel="stylesheet" href="../resources/styles/faq.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
    <link rel="stylesheet" href="../resources/styles/typography.css">
    <link rel="stylesheet" href="style.css">
</head>

<body class="payroll-page">
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>

    <main>

        <!-- Shared .hero. The site header is position:absolute with white nav
             text until it goes sticky, so a light hero hides the navigation. -->
        <section class="hero">
            <div class="container pr-hero-grid">
                <div class="pr-hero-copy animate-fade-in">
                    <h1>Payroll without the<br>per-employee bill.</h1>
                    <p class="hero-subtitle">
                        CPP, EI and income tax worked out from the CRA's own tables for every province and territory,
                        Quebec included. Pay stubs on payday, T4s in January, and the XML file the CRA accepts.
                    </p>
                    <p class="pr-price-line">
                        <strong>$<?= $argo_monthly ?> CAD a month.</strong> Two employees or twenty.
                    </p>
                    <div class="hero-ctas">
                        <a href="../downloads/?source=payroll" class="btn-cta btn-cta-primary">
                            <span>Download free</span><?= svg_icon('arrow-right', 18) ?>
                        </a>
                        <a href="#cost" class="btn-cta btn-cta-outline"><span>What you pay now</span></a>
                    </div>
                    <p class="pr-hero-note">Set the company up and add your people on the free plan. Payroll needs Premium.</p>
                </div>

                <!-- Real markup rather than a screenshot: the numbers are the argument. -->
                <div class="pr-stub-frame animate-fade-in">
                <aside class="pr-stub" aria-label="Example pay stub">
                    <div class="pr-stub-head">
                        <span class="pr-stub-title">Semi-monthly pay</span>
                        <span class="pr-stub-tag">Example</span>
                    </div>
                    <div class="pr-stub-row pr-stub-gross">
                        <span>Gross pay</span><span>$2,400.00</span>
                    </div>
                    <div class="pr-stub-deductions">
                        <div class="pr-stub-row"><span>CPP contribution</span><span>&minus;$132.14</span></div>
                        <div class="pr-stub-row"><span>EI premium</span><span>&minus;$39.36</span></div>
                        <div class="pr-stub-row"><span>Federal tax</span><span>&minus;$258.90</span></div>
                        <div class="pr-stub-row"><span>Provincial tax</span><span>&minus;$121.45</span></div>
                    </div>
                    <div class="pr-stub-row pr-stub-net">
                        <span>Net pay</span><span>$1,848.15</span>
                    </div>
                    <div class="pr-stub-foot">
                        <?= svg_icon('shield-check', 15) ?>
                        <span>Calculated on your computer, from the CRA table covering this pay date.</span>
                    </div>
                </aside>
                </div>
            </div>
        </section>

        <!-- The calculator. This is the page. -->
        <section class="pr-cost" id="cost">
            <div class="container">
                <div class="section-header animate-on-scroll">
                    <span class="section-label">The comparison</span>
                    <h2>Every other Canadian provider charges you per person</h2>
                    <p class="section-desc">
                        A base fee plus a few dollars per employee per month is the standard shape of payroll pricing in
                        Canada. It is a reasonable model for a vendor and an expensive one for you, because the bill grows
                        every time you hire. Set your headcount and see the year.
                    </p>
                </div>

                <div class="pr-calc" data-calc='<?= htmlspecialchars(json_encode($calc_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>'>
                    <div class="pr-calc-control">
                        <label for="pr-headcount">People on your payroll</label>
                        <div class="pr-calc-input">
                            <button type="button" class="pr-step" data-step="-1" aria-label="One fewer employee">&minus;</button>
                            <input type="number" id="pr-headcount" name="headcount" min="1" max="50" step="1"
                                   value="<?= $default_headcount ?>" inputmode="numeric">
                            <button type="button" class="pr-step" data-step="1" aria-label="One more employee">+</button>
                        </div>
                        <input type="range" id="pr-headcount-range" min="1" max="50" step="1"
                               value="<?= $default_headcount ?>" aria-label="People on your payroll">
                    </div>

                    <div class="pr-table-scroll">
                    <table class="pr-calc-table">
                        <caption class="pr-visually-hidden">Monthly and yearly payroll cost by provider at the selected headcount</caption>
                        <thead>
                            <tr>
                                <th scope="col">Provider</th>
                                <th scope="col">How it is priced</th>
                                <th scope="col" class="pr-num">Per month</th>
                                <th scope="col" class="pr-num">Per year</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="pr-calc-argo" data-argo>
                                <th scope="row">
                                    Argo Books <span class="pr-plan">Premium</span>
                                </th>
                                <td>$<?= $argo_monthly ?> flat, unlimited employees</td>
                                <td class="pr-num" data-cell="monthly">$<?= $argo_monthly ?></td>
                                <td class="pr-num" data-cell="yearly">$<?= number_format($argo_monthly * 12) ?></td>
                            </tr>
<?php foreach ($rows as $r): ?>
                            <tr data-provider="<?= htmlspecialchars($r['name']) ?>">
                                <th scope="row">
                                    <?= htmlspecialchars($r['name']) ?> <span class="pr-plan"><?= htmlspecialchars($r['label']) ?></span>
                                </th>
                                <td>
                                    $<?= rtrim(rtrim(number_format($r['base'], 2), '0'), '.') ?> + $<?= rtrim(rtrim(number_format($r['per'], 2), '0'), '.') ?> per employee
<?php if ($r['limit'] !== ''): ?>
                                    <span class="pr-limit"><?= htmlspecialchars($r['limit']) ?></span>
<?php endif; ?>
                                </td>
                                <td class="pr-num" data-cell="monthly">$<?= number_format($r['cost']) ?></td>
                                <td class="pr-num" data-cell="yearly">$<?= number_format($r['cost'] * 12) ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <p class="pr-calc-saving" data-saving>
                        At <span data-saving-count><?= $default_headcount ?></span> <span data-saving-noun><?= $default_headcount === 1 ? 'person' : 'people' ?></span>, the cheapest of those costs
                        <strong data-saving-amount>$<?= number_format(($rows[0]['cost'] - $argo_monthly) * 12) ?></strong>
                        more a year than Argo Books.
                    </p>

                    <p class="pr-calc-note">
                        Provider prices are published small-business rates for payroll only, taken at each vendor's
                        cheapest plan that allows unlimited pay runs, because Argo Books does not cap them either.
                        Wagepoint and QuickBooks both sell a tier around $20 cheaper that is limited to one pay run a
                        month, which is worth having if you pay monthly. QuickBooks Payroll also sits on top of a
                        QuickBooks Online subscription, so its real cost is higher than the figure shown, and Payworks
                        quotes per client rather than publishing a rate card. Check the current rate with any vendor
                        before you switch.
                    </p>
                </div>
            </div>
        </section>

        <!-- Compliance. The reason payroll gets bought at all. -->
        <section class="pr-deadline">
            <div class="container pr-deadline-grid">
                <div class="pr-deadline-copy">
                    <span class="section-label pr-label-light">Year end</span>
                    <h2>T4s are due the last day of February</h2>
                    <p>
                        That deadline does not move, and the penalty for filing late is charged per slip. It is also the
                        moment a year of small payroll shortcuts becomes visible at once, because the slips are built from
                        every pay period you ran, not from a summary you can adjust in January.
                    </p>
                    <p>
                        In Argo Books the slips come from the pay runs you already approved. There is no year to
                        reassemble.
                    </p>
                    <ul class="pr-check-list">
                        <li><?= svg_icon('check', 15) ?> T4 slips and summary as PDFs to hand out</li>
                        <li><?= svg_icon('check', 15) ?> The CRA's XML submission, with its T619 transmittal record</li>
                        <li><?= svg_icon('check', 15) ?> Quebec RL-1 figures worked out on a worksheet to re-key</li>
                        <li><?= svg_icon('check', 15) ?> Amendments and cancellations chosen per employee</li>
                    </ul>
                </div>
                <div class="pr-t4">
                    <div class="pr-t4-head">
                        <span>T4</span>
                        <span class="pr-t4-year">Statement of Remuneration Paid</span>
                    </div>
                    <div class="pr-t4-grid">
                        <div class="pr-t4-box"><span class="pr-t4-num">14</span><span class="pr-t4-label">Employment income</span><span class="pr-t4-val">57,600.00</span></div>
                        <div class="pr-t4-box"><span class="pr-t4-num">16</span><span class="pr-t4-label">CPP contributions</span><span class="pr-t4-val">3,171.36</span></div>
                        <div class="pr-t4-box"><span class="pr-t4-num">18</span><span class="pr-t4-label">EI premiums</span><span class="pr-t4-val">944.64</span></div>
                        <div class="pr-t4-box"><span class="pr-t4-num">22</span><span class="pr-t4-label">Income tax deducted</span><span class="pr-t4-val">9,128.40</span></div>
                        <div class="pr-t4-box"><span class="pr-t4-num">24</span><span class="pr-t4-label">EI insurable earnings</span><span class="pr-t4-val">57,600.00</span></div>
                        <div class="pr-t4-box"><span class="pr-t4-num">26</span><span class="pr-t4-label">CPP pensionable earnings</span><span class="pr-t4-val">57,600.00</span></div>
                    </div>
                    <div class="pr-t4-foot"><?= svg_icon('document-download', 15) ?> Slips, summary and XML, from the runs you approved</div>
                </div>
            </div>
        </section>

        <!-- Payroll buyers are buying correctness, so this names the parts that
             are usually wrong. -->
        <section class="pr-detail">
            <div class="container">
                <div class="section-header animate-on-scroll">
                    <span class="section-label">The arithmetic</span>
                    <h2>The parts of payroll that go wrong quietly</h2>
                    <p class="section-desc">
                        Payroll software is not judged on features. It is judged on whether a number was right eleven
                        months ago. These are the places that decision actually gets made.
                    </p>
                </div>
                <div class="pr-cards">
                    <article class="pr-card">
                        <span class="pr-card-icon"><?= svg_icon('map-pin', 20) ?></span>
                        <h3>Quebec is a second system, not a variation</h3>
                        <p>
                            Quebec runs its own income tax, pension plan and parental insurance. Argo Books calculates QPP,
                            QPIP, Quebec income tax and the federal abatement separately, and works the RL-1 figures out for you. Staff in
                            different provinces can sit on the same pay run.
                        </p>
                    </article>
                    <article class="pr-card">
                        <span class="pr-card-icon"><?= svg_icon('refresh', 20) ?></span>
                        <h3>Rates change twice a year, on their own</h3>
                        <p>
                            The CRA reissues its tables every January and July. Each edition is a data file fetched the
                            first time a pay period needs it, so there is nothing to install and no chance of running
                            January on last year's figures.
                        </p>
                    </article>
                    <article class="pr-card">
                        <span class="pr-card-icon"><?= svg_icon('alert-triangle', 20) ?></span>
                        <h3>It stops rather than guesses</h3>
                        <p>
                            If it does not hold the table covering a pay date, it says so and declines to calculate. A
                            wrong deduction on someone's pay is the kind of error nothing downstream catches, so refusing
                            is the safer failure.
                        </p>
                    </article>
                    <article class="pr-card">
                        <span class="pr-card-icon"><?= svg_icon('calendar-dots', 20) ?></span>
                        <h3>Remittances come with their date</h3>
                        <p>
                            A regular remitter pays by the 15th of the month after payday. Argo Books names that date and
                            the amount belonging to it, rather than leaving you to work out which month you are paying for.
                        </p>
                    </article>
                    <article class="pr-card">
                        <span class="pr-card-icon"><?= svg_icon('lock', 20) ?></span>
                        <h3>Staff records stay on your computer</h3>
                        <p>
                            Payroll holds the most sensitive data in the business: names, social insurance numbers and what
                            everyone earns. Argo Books is a desktop app, so those records are written to your machine
                            rather than uploaded to a provider.
                        </p>
                    </article>
                    <article class="pr-card">
                        <span class="pr-card-icon"><?= svg_icon('dollar', 20) ?></span>
                        <h3>Wages are already in your books</h3>
                        <p>
                            Approving a run records what each person was actually paid as an expense, so payday matches
                            your bank statement instead of being a figure you copy across from a separate payroll service.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Losing an unsuitable buyer here is cheaper than a refund in February. -->
        <section class="pr-honest">
            <div class="container">
                <div class="pr-honest-box">
                    <h2>Where Argo Books is the wrong choice</h2>
                    <ul>
                        <li><strong>You pay staff outside Canada.</strong> Payroll is Canada only. It cannot run a US or overseas payroll, and it is not going to.</li>
                        <li><strong>You want the filing and the remittance done for you.</strong> Argo Books prepares the slips and the XML and tells you what is owed and when. You upload it and you pay it. A full-service provider does both, and that is worth money to some businesses.</li>
                        <li><strong>You need direct deposit run from the payroll tool.</strong> Argo Books works out the pay and records it. Moving the money is still your bank.</li>
                        <li><strong>You file RL-1 slips in Quebec.</strong> The deductions are worked out and the RL-1 figures are put on a worksheet, but Revenu Qu&eacute;bec accepts a printed slip only from software it has certified, and requires XML above five slips. Neither exists here, so the Quebec slips are keyed into My Account by hand.</li>
                    </ul>
                    <p class="pr-honest-close">
                        If any of those matter more to you than the per-employee bill, one of the providers in the table
                        above is genuinely the better answer, and it is cheaper to find that out now.
                    </p>
                </div>
            </div>
        </section>

        <!-- Comparisons -->
        <section class="pr-compare">
            <div class="container">
                <div class="section-header animate-on-scroll">
                    <span class="section-label">Side by side</span>
                    <h2>Compared with what you are probably using</h2>
                </div>
                <div class="pr-compare-grid">
                    <a class="pr-compare-card" href="../compare/argo-books-vs-wagepoint/">
                        <h3>Argo Books vs Wagepoint <?= svg_icon('arrow-top-right', 15) ?></h3>
                        <p>The closest comparison on price. Where the flat fee overtakes $20 plus $4 a head.</p>
                    </a>
                    <a class="pr-compare-card" href="../compare/quickbooks-payroll-alternatives/">
                        <h3>QuickBooks Payroll alternatives <?= svg_icon('arrow-top-right', 15) ?></h3>
                        <p>Payroll billed on top of a QuickBooks Online subscription, against one flat price.</p>
                    </a>
                    <a class="pr-compare-card" href="../compare/payworks-alternatives/">
                        <h3>Payworks alternatives <?= svg_icon('arrow-top-right', 15) ?></h3>
                        <p>A quoted service contract, against software you download and run yourself.</p>
                    </a>
                    <a class="pr-compare-card" href="../features/payroll/">
                        <h3>How payroll works <?= svg_icon('arrow-right-sm', 15) ?></h3>
                        <p>The full feature walkthrough: pay runs, deductions, stubs and year end.</p>
                    </a>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="pr-faq">
            <div class="container">
                <div class="section-header animate-on-scroll">
                    <span class="section-label">Questions</span>
                    <h2>What payroll buyers ask</h2>
                </div>
                <?= argo_faq_grid($faqs) ?>
            </div>
        </section>

    </main>

    <!-- The footer's styles are all scoped under .footer, and every page pairs
         it with the closing CTA in this dark wrapper. Both are load-bearing. -->
    <div class="dark-section-wrapper">
        <section class="cta-section">
            <div class="container">
                <div class="cta-card animate-on-scroll">
                    <h2>Run one pay period before you pay anything</h2>
                    <p>
                        Download Argo Books, add your people, and enter a period you have already run somewhere else.
                        Compare the deductions line by line. That is the only test of payroll software that means
                        anything.
                    </p>
                    <div class="cta-buttons">
                        <a href="../downloads/?source=payroll-footer" class="btn-cta btn-cta-primary">
                            <span>Download for Windows, macOS, or Linux</span><?= svg_icon('arrow-right', 18) ?>
                        </a>
                        <a href="../pricing/" class="btn-cta btn-cta-ghost"><span>See pricing</span></a>
                    </div>
                    <p class="pr-cta-note">Free plan for the rest of Argo Books. Payroll is on Premium at $<?= $argo_monthly ?> CAD a month, or $<?= $argo_yearly ?> a year.</p>
                </div>
            </div>
        </section>

        <footer class="footer">
            <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
        </footer>
    </div>

    <!-- .animate-on-scroll sits at opacity 0 until this adds .animate-visible. -->
    <script defer src="../resources/scripts/reveal.js"></script>
    <script src="calculator.js" defer></script>
</body>

</html>
