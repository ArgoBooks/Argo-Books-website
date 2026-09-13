<?php require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/fonts.php'; ?>
<?php require_once __DIR__ . '/../resources/icons.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Argo">

    <!-- SEO Meta Tags -->
    <meta name="description"
        content="Leave a review for Argo Books on Capterra. Learn what to expect: how Capterra verifies reviewers, what gets published, and what stays private.">
    <meta name="keywords"
        content="argo books review, capterra review, leave a review, software review, accounting software review">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Leave a Review - Argo Books">
    <meta property="og:description"
        content="Help others discover Argo Books. Leave a review on Capterra and see what to expect from their verification process.">
    <meta property="og:url" content="https://argorobots.com/review/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Leave a Review - Argo Books">
    <meta name="twitter:description"
        content="Help others discover Argo Books. Leave a review on Capterra and see what to expect from their verification process.">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/review/">

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json"><?= argo_breadcrumb_schema(["Home" => "/", "Leave a Review" => "/review/"]) ?></script>

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Leave a Review - Argo Books</title>

    <script src="../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="../resources/styles/marketing-sections.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
    <!-- Brand typefaces (Fraunces display + IBM Plex Sans body), matched to the rest of the site -->
    <?= argo_font_links('default', '    ') ?>
    <link rel="stylesheet" href="../resources/styles/typography.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>
    <main>

    <?php
        $capterra_url = 'https://reviews.capterra.com/products/new/b879ef0f-634f-414f-bd7d-e8e818d409c1/?utm_source=vp&utm_campaign=vendor_request';
    ?>

    <!-- =============================================
         HERO
         ============================================= -->
    <section class="hero hero--rise">
        <div class="container">
            <h1>Help others discover Argo Books</h1>
            <p class="hero-subtitle">
                If Argo Books has saved you time or simplified your books, a review on Capterra is one of the most useful ways to give back. It helps small businesses find software that fits the way they actually work.
            </p>
            <div class="review-prefer-contact">
                <?= svg_icon('message-circle', 18) ?>
                <span>
                    Having an issue? <a href="../contact-us/">Tell us first. We'd love to fix it.</a>
                </span>
            </div>
            <div class="hero-ctas">
                <a href="<?= htmlspecialchars($capterra_url) ?>" target="_blank" rel="noopener" class="btn-cta btn-cta-primary">
                    <span>Leave a Review on Capterra</span>
                    <?= svg_icon('arrow-top-right', 18) ?>
                </a>
                <a href="#what-to-expect" class="btn-cta btn-cta-outline">
                    <span>See What to Expect</span>
                </a>
            </div>
        </div>
    </section>

    <!-- =============================================
         WHAT IS CAPTERRA?
         ============================================= -->
    <section class="feature-detail-section rise-section">
        <div class="container">
            <div class="rise-sheet">
            <div class="feature-detail">
                <div class="feature-detail-text">
                    <h2>What Capterra is</h2>
                    <p>
                        Capterra is a software review site owned by Gartner. It lets people compare business tools side by side using reviews from verified users, and people researching accounting software often check it before they pick a product.
                    </p>
                    <ul class="feature-checklist">
                        <li>
                            <?= svg_icon('check', 20) ?>
                            <span>Capterra is independent: we don't pay for placement or curate reviews</span>
                        </li>
                        <li>
                            <?= svg_icon('check', 20) ?>
                            <span>Honest, balanced reviews help us improve and tell us what's working</span>
                        </li>
                    </ul>
                </div>
                <div class="feature-detail-visual">
                    <div class="review-note">
                        <p>I build Argo Books on my own, so reviews are one of the few ways I hear how it holds up in someone else's business. Please be honest, including about what's missing or annoying. Critical reviews tell me what to fix next.</p>
                        <p class="review-note-sign">Evan, founder of Argo Books</p>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         WHAT YOU'LL SEE
         ============================================= -->
    <section id="what-to-expect" class="feature-detail-section" style="background: var(--gray-50);">
        <div class="container">
            <div class="feature-detail reversed">
                <div class="feature-detail-text">
                    <h2>Verifying you're a real person</h2>
                    <p>
                        Before publishing your review, Capterra needs to confirm you're genuinely a user of Argo Books and not a bot, a competitor, or someone connected to us. You'll see this verification screen near the end of the form, and you can choose either path.
                    </p>
                    <ul class="feature-checklist">
                        <li>
                            <?= svg_icon('check', 20) ?>
                            <span><strong>Sign in with LinkedIn:</strong> the fastest path. Capterra uses your LinkedIn profile to confirm you're real.</span>
                        </li>
                        <li>
                            <?= svg_icon('check', 20) ?>
                            <span><strong>Continue without LinkedIn:</strong> enter your name and a contact email manually. Capterra may follow up by email if they have questions.</span>
                        </li>
                    </ul>
                    <div class="review-callout">
                        <?= svg_icon('info', 18) ?>
                        <span>The most common reason a review doesn't get published is that Capterra couldn't verify the reviewer. LinkedIn or accurate contact info helps.</span>
                    </div>
                </div>
                <div class="feature-detail-visual">
                    <div class="review-screenshot-frame">
                        <img src="../resources/images/review/capterra-publish-screen.png"
                             alt="Capterra's 'Improve your chances of getting published' screen, showing a Continue with LinkedIn button and three tips for writing a great review: be specific and relevant, be authentic, and be balanced."
                             loading="lazy"
                             onerror="this.parentElement.classList.add('review-screenshot-frame-missing')">
                        <div class="review-screenshot-fallback">
                            <?= svg_icon('shield-check', 56) ?>
                            <h3>Identity verification</h3>
                            <p>Sign in with LinkedIn or continue with manual entry. Both work, and both are private.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         TIPS FOR A GREAT REVIEW
         ============================================= -->
    <section class="benefits-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Tips for a useful review</h2>
                <p class="section-desc">Capterra shows these three tips on the verification screen.</p>
            </div>
            <div class="benefits-grid benefits-grid--three">
                <div class="benefit-card">
                    <div class="benefit-card-icon">
                        <?= svg_icon('search', 22) ?>
                    </div>
                    <h3>Be specific &amp; relevant</h3>
                    <p>Share concrete examples of features you liked or disliked. Details from your day-to-day use are more useful to readers than general impressions.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-card-icon">
                        <?= svg_icon('user', 22) ?>
                    </div>
                    <h3>Be authentic</h3>
                    <p>Write in your own words about your genuine experience. Capterra asks people not to use AI tools to generate review content.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-card-icon">
                        <?= svg_icon('analytics', 22) ?>
                    </div>
                    <h3>Be balanced</h3>
                    <p>Cover what works and what could be better. A review that only praises is harder for other owners to judge.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         WHAT'S PUBLISHED VS PRIVATE
         ============================================= -->
    <section class="feature-detail-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">What's shared, and what stays private</h2>
                <p class="section-desc">Capterra publishes enough information to make a review credible, without exposing your full identity or contact details.</p>
            </div>
            <div class="review-privacy-grid">
                <div class="review-privacy-card review-privacy-public">
                    <div class="review-privacy-card-icon">
                        <?= svg_icon('eye', 22) ?>
                    </div>
                    <h3>Shown publicly</h3>
                    <ul>
                        <li><?= svg_icon('check', 18) ?><span>Your first name</span></li>
                        <li><?= svg_icon('check', 18) ?><span>Your job role or function</span></li>
                        <li><?= svg_icon('check', 18) ?><span>Your industry</span></li>
                        <li><?= svg_icon('check', 18) ?><span>Your company size</span></li>
                        <li><?= svg_icon('check', 18) ?><span>How long you've used Argo Books</span></li>
                        <li><?= svg_icon('check', 18) ?><span>A profile photo (only in some cases)</span></li>
                    </ul>
                </div>
                <div class="review-privacy-card review-privacy-private">
                    <div class="review-privacy-card-icon">
                        <?= svg_icon('lock', 22) ?>
                    </div>
                    <h3>Kept private</h3>
                    <ul>
                        <li><?= svg_icon('check', 18) ?><span>Your last name</span></li>
                        <li><?= svg_icon('check', 18) ?><span>Your email address</span></li>
                        <li><?= svg_icon('check', 18) ?><span>Your company name (unless you choose to share it)</span></li>
                        <li><?= svg_icon('check', 18) ?><span>Anything else you don't explicitly enter into the form</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================
         WHAT HAPPENS AFTER YOU SUBMIT: 3 steps
         ============================================= -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">What happens after you submit</h2>
            </div>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>You submit your review</h3>
                    <p>Fill in the rating, what you like, what could be better, and how you use Argo Books. Choose LinkedIn or manual verification at the end.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>Capterra verifies you</h3>
                    <p>Their quality assurance team manually confirms you're a real person and that the review fits their community guidelines. They may email you with a quick follow-up question.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>Your review goes live</h3>
                    <p>Once approved, your review appears on the Argo Books listing on Capterra.</p>
                </div>
            </div>
        </div>
    </section>

    </main>

    <!-- =============================================
         FINAL CTA + Footer (dark wrapper)
         ============================================= -->
    <div class="dark-section-wrapper">
        <section class="cta-section">
            <div class="container">
                <div class="cta-card">
                    <h2>Leave a review on Capterra</h2>
                    <p>Your review helps other small business owners decide whether Argo Books fits them. Thank you for taking the time.</p>
                    <div class="cta-buttons">
                        <a href="<?= htmlspecialchars($capterra_url) ?>" target="_blank" rel="noopener" class="btn-cta btn-cta-primary">
                            <span>Leave a Review on Capterra</span>
                            <?= svg_icon('arrow-top-right', 18) ?>
                        </a>
                        <a href="../contact-us/" class="btn-cta btn-cta-ghost">
                            <span>Or Send Us Feedback Directly</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <footer class="footer">
            <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
        </footer>
    </div>

</body>

</html>
