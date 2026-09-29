<?php
// Referral tracking: capture ?source so article/ad clicks landing here attribute.
require_once __DIR__ . '/../../partials/schema.php';
require_once __DIR__ . '/../../partials/faq.php';
require_once __DIR__ . '/../../track_referral.php';
require_once __DIR__ . '/../../resources/icons.php';
require_once __DIR__ . '/../../config/pricing.php';
require_once __DIR__ . '/../../partials/fonts.php';
$argo_monthly = (int) get_pricing_config()['premium_monthly_price'];
$argo_yearly = (int) get_pricing_config()['premium_yearly_price'];

// One array drives both the visible accordion and the FAQPage schema.
$faqs = [
    [
        'q' => 'What is the Argo Books API for?',
        'a' => 'Connecting a system that has no ready-made integration. If you run your own online store, a booking system, a point of sale, or an in-house tool, it can send sales, expenses, customers and suppliers straight into your books instead of you retyping them.',
    ],
    [
        'q' => 'Do I need a developer to use it?',
        'a' => 'To wire it up the first time, usually yes, unless you are comfortable writing a small script. After that it runs on its own. If you already pay someone to look after your website or shop, this is a short job for them.',
    ],
    [
        'q' => 'Can something be added to my books without me seeing it?',
        'a' => 'No. Everything sent to the API waits in a review list. You open Argo Books, look at what arrived, and choose to import it. Nothing reaches your books until you say so, and an import can be undone in one step.',
    ],
    [
        'q' => 'What can an app do with the key I give it?',
        'a' => 'A key can add to your review list and read what has been sent through the API for that company. It cannot read the rest of your books, cannot change records you have already imported, and cannot touch your bank or payment accounts. Keys are per company rather than per app, so a second app holding its own key can see what the first one sent. Give each app its own key anyway, so you can switch one off without disturbing the others.',
    ],
    [
        'q' => 'Does it cost anything?',
        'a' => "The API is part of Premium, which is \${$argo_monthly} a month or \${$argo_yearly} a year. You can download Argo Books and try the rest of it free first. You can hold up to ten active keys per company at a time, and revoking one frees a slot.",

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

    <!-- SEO Meta Tags -->
    <meta name="description" content="Connect your own store, booking system or in-house tool to Argo Books. Send sales, expenses, customers and suppliers straight into your books, and review everything before it is imported.">
    <meta name="keywords" content="Argo Books API, accounting API, accounting software API, send sales to accounting software, custom accounting integration, bookkeeping API, developer API accounting">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Argo Books API | Argo Books">
    <meta property="og:description" content="Connect anything you already run. Send sales and expenses into your books from your own systems, and approve everything before it lands.">
    <meta property="og:url" content="https://argorobots.com/integrations/argo-books-api/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Argo Books API | Argo Books">
    <meta name="twitter:description" content="Connect anything you already run. Send sales and expenses into your books from your own systems, and approve everything before it lands.">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Additional SEO Meta Tags -->
    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/integrations/argo-books-api/">

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", "Integrations" => "/integrations/", "Argo Books API" => "/integrations/argo-books-api/"]) ?></script>

    <!-- FAQ Schema, built from the same array as the accordion further down -->
    <script type="application/ld+json"><?= argo_faq_schema($faqs) ?></script>

    <!-- SoftwareApplication Schema -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "SoftwareApplication",
            "name": "Argo Books",
            "applicationCategory": "BusinessApplication",
            "operatingSystem": "Windows, macOS, Linux",
            "offers": {
                "@type": "Offer",
                "price": "0",
                "priceCurrency": "CAD",
                "description": "Free plan available. Premium for $<?= $argo_monthly ?>/month."
            },
            "description": "Connect your own store, booking system or in-house tool to Argo Books. Send sales, expenses, customers and suppliers straight into your books, and review everything before it is imported.",
            "featureList": "Developer API, Custom integrations, Reviewed imports, Per-app access keys"
        }
    </script>

    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
    <title>Argo Books API | Argo Books</title>

    <script src="../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/styles/faq.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">
    <?= argo_font_links('editorial', '    ') ?>
    <link rel="stylesheet" href="../../resources/styles/typography.css">
    <link rel="stylesheet" href="../../features/feature-page.css">

    <!-- Page-local only. Every other feature page shows a picture of the app;
         for an API the honest subject matter is the request and what comes
         back, so the hero is a real call rather than a mockup. The review-list
         panel below is built in markup too, since no screenshot of it exists
         that would not go stale the first time the app is restyled. -->
    <style>
        .api-code {
            background: var(--gray-900);
            border-radius: 14px;
            padding: 20px 22px;
            overflow-x: auto;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.28);
        }

        .api-code pre {
            margin: 0;
            font-family: 'IBM Plex Mono', Consolas, monospace;
            font-size: 0.82rem;
            line-height: 1.75;
            color: #e2e8f0;
            white-space: pre;
        }

        .api-code .c-dim { color: #64748b; }
        .api-code .c-key { color: #7dd3fc; }
        .api-code .c-str { color: #a7f3d0; }
        .api-code .c-num { color: #fcd34d; }
        .api-code .c-cmd { color: #f8fafc; font-weight: 600; }

        .api-code-bar {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 14px;
        }

        .api-code-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #334155;
        }

        .api-code-label {
            margin-left: 6px;
            font-family: 'IBM Plex Mono', Consolas, monospace;
            font-size: 0.7rem;
            letter-spacing: 0.04em;
            color: #f8fafc;
        }

        /* An imported sale, as it ends up in the books. Built in markup
           rather than shot as an image so it cannot drift from the real thing. */
        .api-record {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.09);
        }

        .api-record-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 15px 18px;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .api-record-title {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .api-record-sub {
            display: block;
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 3px;
        }

        .api-record-pill {
            font-size: 0.66rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--emerald-100);
            color: var(--emerald-700);
            white-space: nowrap;
        }

        .api-record-body { padding: 6px 18px 14px; }

        .api-record-line {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: baseline;
            padding: 8px 0;
            font-size: 0.85rem;
            color: var(--gray-700);
        }

        .api-record-line span:last-child {
            font-family: 'IBM Plex Mono', Consolas, monospace;
            color: var(--gray-900);
            white-space: nowrap;
        }

        .api-record-line.is-sub {
            border-top: 1px solid var(--gray-200);
            margin-top: 6px;
            padding-top: 12px;
        }

        .api-record-line.is-total {
            border-top: 1px solid var(--gray-200);
            margin-top: 4px;
            padding-top: 12px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .api-record-qty {
            color: var(--gray-400);
            font-size: 0.8rem;
        }

        .api-record-foot {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: baseline;
            padding: 13px 18px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        .api-record-foot span:last-child {
            font-family: 'IBM Plex Mono', Consolas, monospace;
            color: var(--gray-700);
            white-space: nowrap;
        }

        /* Developer strip */
        .api-dev-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 14px;
            margin-top: 26px;
        }

        .api-dev-item {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 16px 18px;
            background: #fff;
        }

        .api-dev-item h3 {
            font-size: 0.9rem;
            margin: 0 0 5px;
            color: var(--gray-900);
        }

        .api-dev-item p {
            font-size: 0.83rem;
            line-height: 1.6;
            color: var(--gray-500);
            margin: 0;
        }

        .api-dev-item code {
            font-family: 'IBM Plex Mono', Consolas, monospace;
            font-size: 0.8rem;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 5px;
            padding: 1px 5px;
            color: var(--gray-700);
        }

        @media (max-width: 640px) {
            .api-code { padding: 16px; }
            .api-code pre { font-size: 0.74rem; }
        }
    </style>
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../resources/header/header.php'; ?>
    </header>
    <main>

    <!-- =============================================
         HERO
         ============================================= -->
    <section class="fp-hero hero">
        <div class="hero-bg" aria-hidden="true"></div>
        <div class="fp-wrap">
            <div class="fp-hero-grid">
                <div>
                    <h1>Connect anything<br>you already run.</h1>
                    <p class="fp-hero-sub">Your own online store, booking system, point of sale, or an in-house tool. Anything that already records your sales can send them straight to your books, so you stop entering the same numbers twice.</p>
                    <div class="fp-hero-act">
                        <a href="../../downloads/" class="fp-btn fp-btn-primary">
                            <span>Download free</span>
                            <?= svg_icon('arrow-right', 17) ?>
                        </a>
                        <a href="../../documentation/pages/api/overview.php" class="fp-textlink">Read the developer docs</a>
                    </div>
                    <p class="fp-hero-facts">Part of Premium, $<?= $argo_monthly ?> a month. Nothing is added to your books until you approve it.</p>
                </div>

                <div class="fp-hero-demo">
                    <div class="api-code">
                        <div class="api-code-bar" aria-hidden="true">
                            <span class="api-code-dot"></span>
                            <span class="api-code-dot"></span>
                            <span class="api-code-dot"></span>
                            <span class="api-code-label">Recording a sale</span>
                        </div>
<pre><span class="c-cmd">curl</span> https://argorobots.com/v1/revenue \
  <span class="c-dim">-H</span> <span class="c-str">"Authorization: Bearer ab_..."</span> \
  <span class="c-dim">-d</span> <span class="c-str">'{
    "description": "Order #1042",
    "amount": 11300,
    "currency": "cad",
    "occurred_on": "<?= date('Y-m-d') ?>"
  }'</span>

<span class="c-dim">{</span>
  <span class="c-key">"id"</span>: <span class="c-str">"rev_136ace96eaf4d428"</span>,
  <span class="c-key">"amount"</span>: <span class="c-num">11300</span>,
  <span class="c-key">"import"</span>: <span class="c-dim">{</span> <span class="c-key">"status"</span>: <span class="c-str">"pending"</span> <span class="c-dim">}</span>
<span class="c-dim">}</span>
</pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         HOW IT WORKS
         ============================================= -->
    <section class="fp-section">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">How it works</div>
                <h2 class="fp-h2">Set it up once, then it just arrives</h2>
                <p class="fp-lede">The point is not that data moves. It is that you stop being the thing that moves it.</p>
            </div>
            <div class="fp-steps fp-reveal">
                <div class="fp-step">
                    <div class="fp-step-n">Step 1</div>
                    <h3>Create a key</h3>
                    <p>In Argo Books, open Settings, then Integrations, and create a key for the app you want to connect. Each app gets its own, so you can switch one off without disturbing the rest.</p>
                </div>
                <div class="fp-step">
                    <div class="fp-step-n">Step 2</div>
                    <h3>Your system sends its sales</h3>
                    <p>Whoever looks after your website or shop points it at Argo Books using that key. From then on it sends every sale, expense, customer and supplier across on its own.</p>
                </div>
                <div class="fp-step">
                    <div class="fp-step-n">Step 3</div>
                    <h3>You review and import</h3>
                    <p>What arrives waits in a list. You open it when you are ready, check it over, and import in one click. If it does not look right, you turn it down.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         WHAT ARRIVES. The concrete answer to "what do I
         actually get", which is the question anyone
         weighing this up is really asking.
         ============================================= -->
    <section class="fp-section" style="background: var(--gray-50)">
        <div class="fp-wrap">
            <div class="fp-split fp-reveal">
                <div class="fp-split-text">
                    <div class="fp-eyebrow">What arrives</div>
                    <h2 class="fp-h2">A sale lands with its detail intact</h2>
                    <p class="fp-lede">A record typed up at the end of the month is usually a date and a total, because that is all anyone has the patience for. Your systems already know far more than that. Sending it across keeps the parts that turn out to matter later.</p>
                    <ul class="fp-list">
                        <li><?= svg_icon('check', 17) ?><span>Tax kept separate from the sale, so filing adds up</span></li>
                        <li><?= svg_icon('check', 17) ?><span>Line items preserved, so you can see what actually sold</span></li>
                        <li><?= svg_icon('check', 17) ?><span>The customer attached, so their history builds itself</span></li>
                        <li><?= svg_icon('check', 17) ?><span>Platform fees recorded as their own expense, so your margin is real</span></li>
                    </ul>
                </div>
                <div class="fp-split-media">
                    <div class="api-record" role="img" aria-label="An imported sale in Argo Books: Order 1042 for Priya Raman, two line items totalling 100 dollars, 13 dollars of tax, a 113 dollar total, and a 3 dollar 58 processing fee recorded separately as an expense">
                        <div class="api-record-head">
                            <span class="api-record-title">Order #1042
                                <span class="api-record-sub">Priya Raman &middot; from your online store</span>
                            </span>
                            <span class="api-record-pill">Imported</span>
                        </div>
                        <div class="api-record-body">
                            <div class="api-record-line">
                                <span>Widget <span class="api-record-qty">&times; 2</span></span>
                                <span>$80.00</span>
                            </div>
                            <div class="api-record-line">
                                <span>Gift wrap <span class="api-record-qty">&times; 1</span></span>
                                <span>$20.00</span>
                            </div>
                            <div class="api-record-line is-sub">
                                <span>Subtotal</span>
                                <span>$100.00</span>
                            </div>
                            <div class="api-record-line">
                                <span>Tax</span>
                                <span>$13.00</span>
                            </div>
                            <div class="api-record-line is-total">
                                <span>Total</span>
                                <span>$113.00</span>
                            </div>
                        </div>
                        <div class="api-record-foot">
                            <span>Processing fee, recorded as an expense</span>
                            <span>$3.58</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         The page's one mid-page CTA.
         ============================================= -->
    <section class="fp-midcta">
        <div class="fp-wrap fp-midcta-in">
            <div>
                <h2>Stop copying numbers between systems</h2>
                <p>Try Argo Books free, then turn on the API with Premium for $<?= $argo_monthly ?> a month. Your books stay on your own computer.</p>
            </div>
            <a href="../../downloads/" class="fp-btn fp-btn-primary">
                <span>Download free</span>
                <?= svg_icon('arrow-right', 17) ?>
            </a>
        </div>
    </section>

    <!-- =============================================
         BENEFITS
         ============================================= -->
    <section class="fp-section">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">Why it matters</div>
                <h2 class="fp-h2">What changes when your systems talk to your books</h2>
            </div>
            <div class="fp-benefits fp-reveal">
                <div class="fp-benefit">
                    <div class="fp-benefit-ic"><?= svg_icon('bolt', 20) ?></div>
                    <h3>No more retyping</h3>
                    <p>The sale is already recorded somewhere. Entering it a second time by hand is work that adds nothing except the chance of a typo.</p>
                </div>
                <div class="fp-benefit">
                    <div class="fp-benefit-ic"><?= svg_icon('key', 20) ?></div>
                    <h3>One key per app</h3>
                    <p>Give each system its own key. Switching off the one you stopped using takes a click and leaves everything else running.</p>
                </div>
                <div class="fp-benefit">
                    <div class="fp-benefit-ic"><?= svg_icon('shield', 20) ?></div>
                    <h3>A key opens one door</h3>
                    <p>A key reaches your review list and nothing else. It cannot browse the books you already have, change what you have imported, or touch your bank and payment accounts.</p>
                </div>
                <div class="fp-benefit">
                    <div class="fp-benefit-ic"><?= svg_icon('check', 20, '', 2.4) ?></div>
                    <h3>Details come with it</h3>
                    <p>Tax, discounts, line items, the customer and the supplier all travel together, so an imported sale looks like one you entered carefully yourself.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         FOR DEVELOPERS. Separate block on purpose: the
         owner reading this page and the person wiring it
         up are usually two different people.
         ============================================= -->
    <section class="fp-section" style="background: var(--gray-50)">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">For developers</div>
                <h2 class="fp-h2">Send this page to whoever builds it</h2>
                <p class="fp-lede">A conventional REST API. Predictable URLs, JSON in and out, and errors that tell you exactly which field is wrong.</p>
            </div>
            <div class="api-dev-grid fp-reveal">
                <div class="api-dev-item">
                    <h3>Objects</h3>
                    <p>Customers, suppliers, categories, products, expenses, revenue and refunds, with line items.</p>
                </div>
                <div class="api-dev-item">
                    <h3>Auth</h3>
                    <p>A merchant-issued key as <code>Bearer</code>. Read and write scopes, revocable at any time.</p>
                </div>
                <div class="api-dev-item">
                    <h3>Safe retries</h3>
                    <p><code>Idempotency-Key</code> required on every create, so a timeout cannot record a sale twice.</p>
                </div>
                <div class="api-dev-item">
                    <h3>Money</h3>
                    <p>Integers in the currency's smallest unit. Decimals are refused rather than rounded.</p>
                </div>
                <div class="api-dev-item">
                    <h3>Webhooks</h3>
                    <p>Signed callbacks when the owner imports, declines, or undoes what you sent.</p>
                </div>
            </div>
            <div class="fp-head-c fp-reveal" style="margin-top: 30px">
                <a href="../../documentation/pages/api/overview.php" class="fp-btn fp-btn-primary">
                    <span>Read the developer docs</span>
                    <?= svg_icon('arrow-right', 17) ?>
                </a>
            </div>
        </div>
    </section>

    <!-- =============================================
         PRIVACY
         ============================================= -->
    <section class="fp-section">
        <div class="fp-wrap">
            <div class="fp-split fp-split-flip fp-reveal">
                <div class="fp-split-text">
                    <div class="fp-eyebrow">Privacy</div>
                    <h2 class="fp-h2">Your books stay on your computer</h2>
                    <p class="fp-lede">Connecting an app does not move your accounts online. The API is a doorway into the review list on your own machine, and what an app sends sits there until you bring it in. The books themselves are never copied to our servers.</p>
                    <ul class="fp-list">
                        <li><?= svg_icon('check', 17) ?><span>Records and documents stored locally</span></li>
                        <li><?= svg_icon('check', 17) ?><span>A connected app cannot read your existing books</span></li>
                        <li><?= svg_icon('check', 17) ?><span>Your data moves and backs up like any other file</span></li>
                    </ul>
                </div>
                <div class="fp-split-media">
                    <img src="../../resources/images/privacy-local-storage.svg"
                         alt="The Argo Books folder open on a local disk, showing receipts, invoices and the database file stored on this computer"
                         loading="lazy" width="600" height="500">
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         WHO IT'S FOR
         ============================================= -->
    <section class="fp-section-tight">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">Who it's for</div>
                <h2 class="fp-h2">Worth it when your setup is your own</h2>
            </div>
            <div class="fp-who fp-reveal">
                <div class="fp-who-item">
                    <h3><?= svg_icon('globe', 19) ?> Custom online stores</h3>
                    <p>A shop built for you rather than bought off the shelf.</p>
                </div>
                <div class="fp-who-item">
                    <h3><?= svg_icon('calendar', 19) ?> Booking and scheduling</h3>
                    <p>Appointments and jobs that already produce a price.</p>
                </div>
                <div class="fp-who-item">
                    <h3><?= svg_icon('database', 19) ?> In-house tools</h3>
                    <p>The internal system nobody else sells an integration for.</p>
                </div>
                <div class="fp-who-item">
                    <h3><?= svg_icon('users', 19) ?> Anyone with a developer</h3>
                    <p>If someone maintains your site, this is a short job for them.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         FAQ. Accordion and schema both come from $faqs.
         ============================================= -->
    <section class="fp-faq">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">Questions</div>
                <h2 class="fp-h2">Before you download</h2>
            </div>
            <?= argo_faq_grid($faqs) ?>
        </div>
    </section>

    <!-- =============================================
         RELATED
         ============================================= -->
    <section class="fp-section">
        <div class="fp-wrap">
            <div class="fp-head-c fp-reveal">
                <div class="fp-eyebrow fp-eyebrow-c">Works with</div>
                <h2 class="fp-h2">What the API feeds into</h2>
            </div>
            <div class="fp-related fp-reveal">
                <a href="../../features/expense-revenue-tracking/" class="fp-rel-card">
                    <div class="fp-rel-ic"><?= svg_icon('dollar', 20) ?></div>
                    <h3>Expense &amp; revenue tracking</h3>
                    <p>Imported sales and costs land in your records with their detail intact.</p>
                </a>
                <a href="../../features/customer-management/" class="fp-rel-card">
                    <div class="fp-rel-ic"><?= svg_icon('users', 20) ?></div>
                    <h3>Customer management</h3>
                    <p>Customers arrive with their sales already attached to them.</p>
                </a>
                <a href="../stripe/" class="fp-rel-card">
                    <div class="fp-rel-ic"><?= svg_icon('credit-card', 20) ?></div>
                    <h3>Stripe integration</h3>
                    <p>Taking card payments through Stripe needs no code at all.</p>
                </a>
                <a href="../../features/report-builder/" class="fp-rel-card">
                    <div class="fp-rel-ic"><?= svg_icon('report', 20) ?></div>
                    <h3>Report builder</h3>
                    <p>Everything that comes in through the API counts in your reports.</p>
                </a>
            </div>
        </div>
    </section>

    </main>

    <!-- Final CTA and footer share one dark block. dark-section-wrapper is what
         lets the footer's orbs bleed up past the footer's own box. -->
    <div class="dark-section-wrapper fp-outro">
        <section class="fp-outro-cta cta-section">
            <div class="fp-wrap">
                <h2>Let your systems do the bookkeeping</h2>
                <p>Download Argo Books, upgrade to Premium, create a key, and point your tools at it. Your data stays on your own machine.</p>
                <div class="fp-btns">
                    <a href="../../downloads/" class="fp-btn fp-btn-primary">
                        <span>Download free</span>
                        <?= svg_icon('arrow-right', 17) ?>
                    </a>
                    <a href="../../documentation/pages/api/overview.php" class="fp-btn fp-btn-onnavy">
                        <span>Developer docs</span>
                    </a>
                </div>
            </div>
        </section>

        <footer class="footer">
            <?php include __DIR__ . '/../../resources/footer/footer.php'; ?>
        </footer>
    </div>

    <script defer src="../../resources/scripts/reveal.js"></script>
</body>

</html>
