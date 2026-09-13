<?php
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../track_referral.php';
require_once __DIR__ . '/../config/pricing.php';
require_once __DIR__ . '/../community/affiliate/affiliate_functions.php';
require_once __DIR__ . '/../partials/fonts.php';

$hold_days = affiliate_hold_days();

// Real numbers drive both the copy and the calculator, so the page can never
// drift from actual pricing. Commission is 50% for the first 12 months.
$pricing         = get_pricing_config();
$premium_monthly = (float) $pricing['premium_monthly_price'];   // e.g. 10.00
$premium_yearly  = (float) $pricing['premium_yearly_price'];    // e.g. 100.00
$rate            = 0.50;
$commission_rate_pct = (int) round($rate * 100);
$window_months   = 12;
// Referral attribution window: how long a click keeps crediting the affiliate.
// Pulled from the same env-backed setting the checkout enforces, so the copy can
// never claim a window the commission logic doesn't actually honor.
$cookie_days     = affiliate_attribution_days();
$c_month         = $premium_monthly * $rate;                    // per-month commission, monthly plan
$c_year          = $premium_yearly * $rate;                     // per-customer commission, yearly plan

$fmt = function (float $n): string {
    // Whole dollars for these round figures; drop trailing .00.
    return '$' . number_format($n, ($n == floor($n)) ? 0 : 2);
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Argo">

    <meta name="description" content="Join the Argo Books affiliate program and earn <?php echo $commission_rate_pct; ?>% recurring commission for every customer you refer, for their first 12 months. Free to join, PayPal payouts.">
    <meta name="keywords" content="Argo Books affiliate program, accounting software affiliate, recurring commission, refer and earn">

    <meta property="og:title" content="Affiliate Program: Earn <?php echo $commission_rate_pct; ?>% Recurring | Argo Books">
    <meta property="og:description" content="Earn <?php echo $commission_rate_pct; ?>% commission for every customer you refer to Argo Books, for their first 12 months.">
    <meta property="og:url" content="https://argorobots.com/affiliates/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Affiliate Program: Earn <?php echo $commission_rate_pct; ?>% Recurring | Argo Books">
    <meta name="twitter:description" content="Earn <?php echo $commission_rate_pct; ?>% commission for every customer you refer to Argo Books, for their first 12 months.">

    <link rel="canonical" href="https://argorobots.com/affiliates/">

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Affiliate Program: Earn <?php echo $commission_rate_pct; ?>% Recurring | Argo Books</title>

    <?= argo_font_links('dashboard', '    ') ?>

    <script src="../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
</head>

<body class="aff-page">
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>

    <main>
        <!-- ============ HERO: thesis + live earnings calculator ============ -->
        <section class="aff-hero">
            <div class="aff-hero-grid" aria-hidden="true"></div>
            <div class="aff-hero-inner">
                <div class="aff-hero-copy">
                    <h1 class="aff-headline">Earn <?php echo $commission_rate_pct; ?>% of every payment for 12 months</h1>
                    <p class="aff-lede">
                        Share your referral link. When someone subscribes to Argo Books Premium through it,
                        you get <strong><?php echo $commission_rate_pct; ?>% of each payment</strong> they make in their first 12 months.
                    </p>
                    <div class="aff-hero-actions">
                        <a href="../community/affiliate/" class="aff-btn aff-btn-primary">
                            <span>Become an affiliate</span>
                            <?php echo svg_icon('arrow-right', 18); ?>
                        </a>
                        <a href="#how" class="aff-btn aff-btn-ghost">See how it works</a>
                    </div>
                    <ul class="aff-trust">
                        <li><?php echo svg_icon('circle-check', 16); ?> Free to join</li>
                        <li><?php echo svg_icon('circle-check', 16); ?> Paid via PayPal</li>
                        <li><?php echo svg_icon('circle-check', 16); ?> No quotas</li>
                    </ul>
                </div>

                <!-- Signature: the page does the accounting for you. -->
                <div class="aff-calc" id="affCalc"
                     data-cmonth="<?php echo $c_month; ?>"
                     data-cyear="<?php echo $c_year; ?>"
                     data-monthly="<?php echo $premium_monthly; ?>"
                     data-yearly="<?php echo $premium_yearly; ?>">
                    <div class="aff-calc-head">
                        <span class="aff-calc-title">Your earnings</span>
                        <div class="aff-calc-toggle" role="tablist" aria-label="Subscription plan">
                            <button type="button" class="aff-calc-tab is-active" role="tab" aria-selected="true" data-plan="monthly">Monthly plan</button>
                            <button type="button" class="aff-calc-tab" role="tab" aria-selected="false" data-plan="yearly">Yearly plan</button>
                        </div>
                    </div>

                    <div class="aff-calc-readout">
                        <span class="aff-calc-big" id="affBig" aria-live="polite"><?php echo $fmt($c_month * 10); ?></span>
                        <span class="aff-calc-cur">CAD</span>
                        <span class="aff-calc-unit" id="affUnit">/ month</span>
                    </div>
                    <p class="aff-calc-sub" id="affSub">
                        <?php echo $fmt($c_month * 12 * 10); ?> over their first year
                    </p>

                    <div class="aff-calc-control">
                        <div class="aff-calc-control-label">
                            <label for="affRefs">Customers you refer</label>
                            <output id="affRefsOut" for="affRefs">10</output>
                        </div>
                        <input type="range" id="affRefs" min="1" max="50" value="10" step="1"
                               aria-label="Number of customers you refer">
                        <div class="aff-calc-scale"><span>1</span><span>50+</span></div>
                    </div>

                    <p class="aff-calc-fine" id="affFine">
                        Based on Argo Books Premium at <?php echo $fmt($premium_monthly); ?>/mo. You keep
                        <?php echo $commission_rate_pct; ?>% (<?php echo $fmt($c_month); ?>) of every monthly payment,
                        for 12 months. Figures in CAD.
                    </p>
                </div>
            </div>
        </section>

        <!-- ============ HOW IT WORKS: a real three-step sequence ============ -->
        <section class="aff-steps" id="how">
            <div class="aff-container">
                <div class="aff-section-head">
                    <h2>How it works</h2>
                </div>
                <ol class="aff-step-list">
                    <li class="aff-step">
                        <h3>Apply</h3>
                        <p>Create a free Argo account and tell us how you plan to promote. Most applications are reviewed within a day or two.</p>
                    </li>
                    <li class="aff-step">
                        <h3>Share your link</h3>
                        <p>Get a unique referral link and drop it in your videos, posts, newsletter, or client emails. Every click is tracked back to you.</p>
                    </li>
                    <li class="aff-step">
                        <h3>Get paid</h3>
                        <p>Earn <?php echo $commission_rate_pct; ?>% of every payment your referrals make in their first 12 months, paid to your PayPal.</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ============ WHY JOIN: the terms, stated plainly ============ -->
        <section class="aff-why">
            <div class="aff-container">
                <div class="aff-section-head">
                    <h2>Program terms</h2>
                </div>
                <div class="aff-why-grid">
                    <div class="aff-card">
                        <span class="aff-card-figure"><?php echo $commission_rate_pct; ?>%</span>
                        <h3>Commission rate</h3>
                        <p><?php echo $commission_rate_pct; ?>% of each payment a referred customer makes, before payment processing fees. That's <?php echo $fmt($c_month); ?> a month per customer on monthly Premium, or <?php echo $fmt($c_year); ?> per customer on yearly.</p>
                    </div>
                    <div class="aff-card">
                        <span class="aff-card-figure">12<span class="aff-card-figure-unit">mo</span></span>
                        <h3>Commission period</h3>
                        <p>You earn on every payment a referred customer makes in the first 12 months of their subscription, not only the first one.</p>
                    </div>
                    <div class="aff-card">
                        <span class="aff-card-icon"><?php echo svg_icon('clock', 26); ?></span>
                        <h3><?php echo (int) $cookie_days; ?>-day referral cookie</h3>
                        <p>When someone clicks your link, you're credited if they subscribe within the next <?php echo (int) $cookie_days; ?> days.</p>
                    </div>
                    <div class="aff-card">
                        <span class="aff-card-icon"><?php echo svg_icon('analytics', 26); ?></span>
                        <h3>Affiliate dashboard</h3>
                        <p>See your clicks, signups, paying customers, and what you're owed.</p>
                    </div>
                    <div class="aff-card">
                        <span class="aff-card-icon"><?php echo svg_icon('dollar', 26); ?></span>
                        <h3>PayPal payouts</h3>
                        <p>Commission is paid to your PayPal in CAD after a <?php echo (int) $hold_days; ?>-day hold that covers the refund window.</p>
                    </div>
                    <div class="aff-card">
                        <span class="aff-card-icon"><?php echo svg_icon('circle-check', 26); ?></span>
                        <h3>Free to join</h3>
                        <p>No cost and no sales quota. Apply with a free Argo account.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ WHO IT'S FOR ============ -->
        <section class="aff-audience">
            <div class="aff-container">
                <div class="aff-section-head">
                    <h2>Who it's for</h2>
                </div>
                <div class="aff-audience-grid">
                    <div class="aff-audience-item"><?php echo svg_icon('bank', 22); ?><span>Bookkeepers &amp; accountants setting up client books</span></div>
                    <div class="aff-audience-item"><?php echo svg_icon('play', 22); ?><span>Creators &amp; YouTubers in finance and small business</span></div>
                    <div class="aff-audience-item"><?php echo svg_icon('pencil', 22); ?><span>Bloggers &amp; reviewers writing software roundups</span></div>
                    <div class="aff-audience-item"><?php echo svg_icon('users', 22); ?><span>LinkedIn creators posting to a small-business audience</span></div>
                </div>
            </div>
        </section>

        <!-- ============ FAQ ============ -->
        <section class="aff-faq">
            <div class="aff-container aff-faq-inner">
                <div class="aff-section-head">
                    <h2>Questions</h2>
                </div>
                <div class="aff-faq-list">
                    <details class="aff-faq-item">
                        <summary>How much do I earn?<?php echo svg_icon('chevron-down', 20); ?></summary>
                        <p><?php echo $commission_rate_pct; ?>% of every payment for the first 12 months of each subscription. On monthly Premium that's <?php echo $fmt($c_month); ?> per customer, every month. On yearly Premium it's <?php echo $fmt($c_year); ?> per customer.</p>
                    </details>
                    <details class="aff-faq-item">
                        <summary>Who can join?<?php echo svg_icon('chevron-down', 20); ?></summary>
                        <p>Anyone with an audience of small-business owners or self-employed people. You don't need a huge following, just a genuine way to reach people who'd benefit from Argo Books.</p>
                    </details>
                    <details class="aff-faq-item">
                        <summary>When and how do I get paid?<?php echo svg_icon('chevron-down', 20); ?></summary>
                        <p>Commission is paid to your PayPal once it clears a <?php echo (int) $hold_days; ?>-day hold and you've reached a payout. The hold is the refund window: if a sale is refunded or charged back in that time, no commission is earned on it. Your dashboard shows what's pending, what's cleared and available, and what's been paid.</p>
                    </details>
                    <details class="aff-faq-item">
                        <summary>How are referrals tracked?<?php echo svg_icon('chevron-down', 20); ?></summary>
                        <p>Your unique link tags every visitor you send. The tag lasts <?php echo (int) $cookie_days; ?> days, so if they subscribe any time in that window the sale is credited to you automatically, and you earn on their payments for their first 12 months.</p>
                    </details>
                    <details class="aff-faq-item">
                        <summary>Where do I track everything?<?php echo svg_icon('chevron-down', 20); ?></summary>
                        <p>In your affiliate dashboard. It shows your clicks, signups, paying customers, total earned, what's been paid, and what you're still owed. Open it any time from this page or from your Argo Books profile.</p>
                    </details>
                    <details class="aff-faq-item">
                        <summary>Does it cost anything to join?<?php echo svg_icon('chevron-down', 20); ?></summary>
                        <p>No. Joining is free and there are no quotas. Create a free Argo account and apply.</p>
                    </details>
                </div>
            </div>
        </section>

        <!-- ============ FINAL CTA: flows into the dark footer ============ -->
        <section class="aff-final">
            <div class="aff-container aff-final-inner">
                <h2>Apply to the affiliate program</h2>
                <p>Applications are reviewed by hand, usually within a day or two.</p>
                <a href="../community/affiliate/" class="aff-btn aff-btn-primary aff-btn-lg">
                    <span>Become an affiliate</span>
                    <?php echo svg_icon('arrow-right', 18); ?>
                </a>
                <p class="aff-final-note">Free to join. Sign in or create a free Argo account to apply. See the <a href="../legal/affiliate-terms.php" class="link">Affiliate Program Terms</a>.</p>
            </div>
        </section>
    </main>

    <footer class="footer">
        <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
    </footer>

    <script>
        (function () {
            var calc = document.getElementById('affCalc');
            if (!calc) return;

            var cMonth  = parseFloat(calc.dataset.cmonth);
            var cYear   = parseFloat(calc.dataset.cyear);
            var monthly = parseFloat(calc.dataset.monthly);
            var yearly  = parseFloat(calc.dataset.yearly);

            var refs   = document.getElementById('affRefs');
            var refsOut = document.getElementById('affRefsOut');
            var bigEl  = document.getElementById('affBig');
            var unitEl = document.getElementById('affUnit');
            var subEl  = document.getElementById('affSub');
            var fineEl = document.getElementById('affFine');
            var tabs   = calc.querySelectorAll('.aff-calc-tab');

            var plan = 'monthly';
            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // Exact money: whole dollars stay clean, half-dollars show cents,
            // so the per-payment figure ($7.50) can never contradict the total.
            function money(n) {
                var r = Math.round(n * 100) / 100;
                var opts = Number.isInteger(r) ? {} : { minimumFractionDigits: 2, maximumFractionDigits: 2 };
                return '$' + r.toLocaleString('en-CA', opts);
            }

            // Count-up so the ledger figure animates as inputs change. Frames
            // round to whole dollars to avoid jittery decimals; the final value
            // snaps to the exact amount.
            var rafId = null;
            function setBig(target) {
                if (reduce) { bigEl.textContent = money(target); return; }
                var start = parseFloat((bigEl.textContent || '0').replace(/[^0-9.]/g, '')) || 0;
                var t0 = null, dur = 450;
                if (rafId) cancelAnimationFrame(rafId);
                function tick(ts) {
                    if (t0 === null) t0 = ts;
                    var p = Math.min((ts - t0) / dur, 1);
                    var eased = 1 - Math.pow(1 - p, 3);
                    var val = start + (target - start) * eased;
                    bigEl.textContent = (p < 1) ? ('$' + Math.round(val).toLocaleString('en-CA')) : money(target);
                    if (p < 1) rafId = requestAnimationFrame(tick);
                }
                rafId = requestAnimationFrame(tick);
            }

            function render() {
                var n = parseInt(refs.value, 10);
                refsOut.textContent = n;
                if (plan === 'monthly') {
                    setBig(cMonth * n);
                    unitEl.textContent = '/ month';
                    subEl.textContent = money(cMonth * 12 * n) + ' over their first year';
                    fineEl.innerHTML = 'Based on Argo Books Premium at ' + money(monthly) + '/mo. You keep 50% ('
                        + money(cMonth) + ') of every monthly payment, for 12 months. Figures in CAD.';
                } else {
                    setBig(cYear * n);
                    unitEl.textContent = '/ year';
                    subEl.textContent = money(cYear) + ' per yearly subscriber, paid upfront';
                    fineEl.innerHTML = 'Based on Argo Books Premium at ' + money(yearly) + '/yr. You keep 50% ('
                        + money(cYear) + ') of each yearly subscription. Figures in CAD.';
                }
            }

            refs.addEventListener('input', render);
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    if (tab.classList.contains('is-active')) return;
                    tabs.forEach(function (t) {
                        t.classList.remove('is-active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    tab.classList.add('is-active');
                    tab.setAttribute('aria-selected', 'true');
                    plan = tab.dataset.plan;
                    render();
                });
            });

            render();
        })();
    </script>
</body>

</html>
