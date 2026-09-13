<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../community_functions.php';
require_once __DIR__ . '/../users/user_functions.php';
require_once __DIR__ . '/affiliate_functions.php';
require_once __DIR__ . '/affiliate_emails.php';
require_once __DIR__ . '/../../partials/fonts.php';

// Affiliates are community members, so reuse community auth.
require_login();

$env = current_environment();
$user_id = (int) $_SESSION['user_id'];
$user = get_user($user_id);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';
if (isset($_SESSION['affiliate_success'])) {
    $success_message = $_SESSION['affiliate_success'];
    unset($_SESSION['affiliate_success']);
}

$affiliate = get_affiliate_for_user($user_id, $env);

// Handle the application submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!is_string($posted_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $posted_token)) {
        $error_message = 'Invalid request. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'apply') {
        if ($affiliate) {
            $error_message = 'You have already applied.';
        } else {
            $payout_email = trim($_POST['payout_email'] ?? '');
            $reason = trim($_POST['application_reason'] ?? '');
            $promo_url = trim($_POST['promo_url'] ?? '');

            // Applicant may request the tail of their link: ?source=aff-<tail>.
            $desired_tail = affiliate_normalize_code($_POST['desired_code'] ?? '');
            $source_code = null;

            if (!filter_var($payout_email, FILTER_VALIDATE_EMAIL)) {
                $error_message = 'Enter a valid PayPal email so we can pay your commission.';
            } elseif ($reason === '') {
                $error_message = 'Tell us a little about how you plan to promote Argo Books.';
            } elseif ($promo_url !== '' && !filter_var($promo_url, FILTER_VALIDATE_URL)) {
                $error_message = 'The promotion link does not look like a valid URL.';
            } elseif (empty($_POST['agree_terms'])) {
                $error_message = 'Please agree to the Affiliate Program Terms to apply.';
            } elseif ($desired_tail !== '' && (strlen($desired_tail) < 2 || strlen($desired_tail) > 46)) {
                $error_message = 'Your referral link must be 2 to 46 characters (letters, numbers, hyphens).';
            } elseif ($desired_tail !== '' && affiliate_source_code_taken('aff-' . $desired_tail)) {
                $error_message = 'That referral link is already taken. Please choose another.';
            } else {
                // Reserve the source_code now; the referral_links row is created
                // on approval so the link stays dead until then. Blank tail falls
                // back to an auto-generated aff-<username> code.
                $source_code = $desired_tail !== '' ? 'aff-' . $desired_tail : generate_affiliate_source_code($user['username']);
                try {
                    $stmt = $pdo->prepare('INSERT INTO affiliates (user_id, source_code, status, payout_method, payout_email, application_reason, promo_url, environment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$user_id, $source_code, 'pending', 'paypal', $payout_email, $reason, $promo_url, $env]);
                    send_affiliate_application_received_email($user['email'], $user['username']);
                    // Let the admin know there's an application to review.
                    send_affiliate_admin_notification_email($user['username'], $user['email'], $promo_url, $reason);
                    $_SESSION['affiliate_success'] = 'Application submitted. We\'ll email you once it\'s reviewed.';
                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    // UNIQUE(user_id, environment) race, or a code collision.
                    $error_message = 'You have already applied, or that link is taken. Please reload.';
                }
            }
        }
    }
}

$status = $affiliate['status'] ?? 'none';

// Compute dashboard figures only when approved.
if ($status === 'approved') {
    $money = affiliate_money_summary($affiliate, $env);
    $stats = get_affiliate_stats($affiliate['source_code'], $env);
    $referral_url = affiliate_referral_url($affiliate['source_code']);
    $hold_days = affiliate_hold_days();
    $available = max(0.0, $money['owed']); // seasoned commission not yet paid out
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliate Program - Argo Books</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">

    <script src="../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="affiliate.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/link.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">
    <?= argo_font_links('default', '    ') ?>
    <link rel="stylesheet" href="../../resources/styles/typography.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../resources/header/header.php'; ?>
    </header>

    <main>
        <?php
        // State-aware hero copy (same centered dark hero as the community page).
        $hero = [
            'none'      => ['Become an Argo Books affiliate', 'Earn 50% commission for every customer you refer, for their first 12 months.'],
            'approved'  => ['Your affiliate dashboard', 'Your referral link, clicks, signups, and commission.'],
            'pending'   => ['Application under review', 'Your application has been received.'],
            'rejected'  => ['Affiliate application', 'An update on your application.'],
            'suspended' => ['Affiliate account paused', 'Your referral link is currently inactive.'],
        ][$status];
        ?>
        <div class="aff-hero">
            <div class="aff-hero-content">
                <h1><?php echo htmlspecialchars($hero[0]); ?></h1>
                <p><?php echo htmlspecialchars($hero[1]); ?></p>
            </div>
        </div>

        <div class="aff-wrap">
            <?php if ($success_message): ?><div class="aff-alert aff-alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
            <?php if ($error_message): ?><div class="aff-alert aff-alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

            <?php if ($status === 'none'): ?>
                <!-- ================= APPLY ================= -->
                <section class="aff-card">
                    <h2 class="aff-card-title">Apply to join</h2>
                    <p class="aff-card-note">You'll earn 50% of every payment your referrals make, for the first 12 months of each subscription. We review every application by hand, usually within a day or two, and your unique link appears right here once you're in.</p>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="apply">

                        <div class="aff-field">
                            <label for="payout_email">PayPal email for payouts</label>
                            <input type="email" name="payout_email" id="payout_email" required value="<?php echo htmlspecialchars($_POST['payout_email'] ?? $user['email']); ?>">
                            <small>Where we'll send your commission.</small>
                        </div>

                        <div class="aff-field">
                            <label for="promo_url">Where will you promote? <span class="aff-optional">(optional)</span></label>
                            <input type="url" name="promo_url" id="promo_url" placeholder="https://youtube.com/@yourchannel" value="<?php echo htmlspecialchars($_POST['promo_url'] ?? ''); ?>">
                            <small>Your channel, page, or website.</small>
                        </div>

                        <div class="aff-field">
                            <label for="desired_code">Choose your referral link <span class="aff-optional">(optional)</span></label>
                            <div class="aff-code-input">
                                <span class="aff-code-prefix">argorobots.com/?source=aff-</span>
                                <input type="text" name="desired_code" id="desired_code" pattern="[A-Za-z0-9\-]{2,46}" placeholder="yourname" value="<?php echo htmlspecialchars($_POST['desired_code'] ?? ''); ?>">
                            </div>
                            <small>Letters, numbers, and hyphens. Leave blank and we'll use your username.</small>
                        </div>

                        <div class="aff-field">
                            <label for="application_reason">How do you plan to promote Argo Books?</label>
                            <textarea name="application_reason" id="application_reason" rows="4" required placeholder="Tell us about your audience and where you'll share your link."><?php echo htmlspecialchars($_POST['application_reason'] ?? ''); ?></textarea>
                        </div>

                        <label class="aff-terms-agree">
                            <input type="checkbox" name="agree_terms" value="1" required <?php echo !empty($_POST['agree_terms']) ? 'checked' : ''; ?>>
                            <span>I agree to the <a href="../../legal/affiliate-terms.php" target="_blank" rel="noopener" class="link">Affiliate Program Terms</a>.</span>
                        </label>

                        <button type="submit" class="btn btn-blue aff-form-submit">Apply to the program</button>
                    </form>
                </section>

            <?php elseif ($status === 'approved'): ?>
                <!-- ================= DASHBOARD ================= -->
                <div class="aff-linkbox">
                    <span class="aff-linkbox-label">Your referral link, share it anywhere</span>
                    <div class="aff-linkbox-row">
                        <input type="text" id="refLink" readonly value="<?php echo htmlspecialchars($referral_url); ?>">
                        <button type="button" class="btn btn-blue aff-copy" id="copyBtn" onclick="copyRefLink()">Copy link</button>
                    </div>
                </div>

                <div class="aff-stats">
                    <div class="aff-stat">
                        <div class="aff-stat-num"><?php echo number_format($stats['clicks']); ?></div>
                        <div class="aff-stat-label">Clicks</div>
                    </div>
                    <div class="aff-stat">
                        <div class="aff-stat-num"><?php echo number_format($stats['signups']); ?></div>
                        <div class="aff-stat-label">Signups</div>
                    </div>
                    <div class="aff-stat">
                        <div class="aff-stat-num"><?php echo number_format($stats['paying']); ?></div>
                        <div class="aff-stat-label">Paying customers</div>
                    </div>
                </div>

                <div class="aff-earn">
                    <div class="aff-earn-card featured">
                        <div class="aff-earn-label">Available now</div>
                        <div class="aff-earn-num">$<?php echo number_format($available, 2); ?></div>
                        <div class="aff-earn-foot">Ready to pay out</div>
                    </div>
                    <div class="aff-earn-card">
                        <div class="aff-earn-label">Pending
                            <span class="aff-info" tabindex="0" role="button" aria-label="Why is commission pending?" title="Commission is held for <?php echo (int) $hold_days; ?> days after each payment in case the customer gets a refund. After that it moves to Available.">i</span>
                        </div>
                        <div class="aff-earn-num">$<?php echo number_format($money['pending'], 2); ?></div>
                        <div class="aff-earn-foot">Clears after <?php echo (int) $hold_days; ?> days</div>
                    </div>
                    <div class="aff-earn-card">
                        <div class="aff-earn-label">Paid out</div>
                        <div class="aff-earn-num">$<?php echo number_format($money['paid'], 2); ?></div>
                        <div class="aff-earn-foot">All time</div>
                    </div>
                </div>

                <p class="aff-fineprint">You've earned <strong>$<?php echo number_format($money['earned'], 2); ?></strong> in total. Figures are in CAD: 50% of the subscription price, before payment processing fees, for the first 12 months of each referred subscription. New commission is held for <?php echo (int) $hold_days; ?> days (the refund window) before it can be paid, and commission on a payment that's later refunded is not paid. Payouts are sent by PayPal to <strong><?php echo htmlspecialchars($affiliate['payout_email'] ?: $user['email']); ?></strong>. If your PayPal account receives money in a different currency or country, PayPal's currency-conversion and cross-border fees apply and are taken out of the amount you receive, they are not covered by Argo Books. Receiving in CAD avoids these fees. See the <a href="../../legal/affiliate-terms.php" target="_blank" rel="noopener" class="link">Affiliate Program Terms</a>. Questions? <a href="../../contact-us/" class="link">Contact us</a>.</p>

            <?php else: // pending / rejected / suspended ?>
                <section class="aff-card aff-status">
                    <?php if ($status === 'pending'): ?>
                        <div class="aff-status-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <p>Thanks for applying. We review applications manually, usually within a day or two, and we'll email <strong><?php echo htmlspecialchars($user['email']); ?></strong> when there's a decision.</p>
                    <?php elseif ($status === 'rejected'): ?>
                        <div class="aff-status-icon muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </div>
                        <p>Thanks for your interest. We weren't able to approve your application at this time.</p>
                        <?php if (!empty($affiliate['review_notes'])): ?><p class="aff-status-note"><?php echo htmlspecialchars($affiliate['review_notes']); ?></p><?php endif; ?>
                    <?php else: // suspended ?>
                        <div class="aff-status-icon muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="10" y1="9" x2="10" y2="15"/><line x1="14" y1="9" x2="14" y2="15"/></svg>
                        </div>
                        <p>Your referral link is currently inactive. Any commission you've already earned is safe. <a href="../../contact-us/" class="link">Contact us</a> if you think this is a mistake.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer">
        <?php include __DIR__ . '/../../resources/footer/footer.php'; ?>
    </footer>

    <script>
        function copyRefLink() {
            const input = document.getElementById('refLink');
            const btn = document.getElementById('copyBtn');
            function showCopied() {
                btn.textContent = 'Copied';
                btn.classList.add('copied');
                setTimeout(function () {
                    btn.textContent = 'Copy link';
                    btn.classList.remove('copied');
                }, 1600);
            }
            // Clipboard API copies without touching the text selection. Only the
            // legacy fallback needs to select the field.
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(showCopied).catch(function () {
                    input.select();
                    document.execCommand('copy');
                    showCopied();
                });
            } else {
                input.select();
                document.execCommand('copy');
                showCopied();
            }
        }
    </script>
</body>

</html>
