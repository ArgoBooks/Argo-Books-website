<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_marketing.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../resources/icons.php';

require_login();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Load current prefs
$stmt = $pdo->prepare('SELECT email, email_pref_product_updates, email_pref_tips_onboarding,
                              email_pref_reviews, email_pref_promotions, email_pref_community_digest
                       FROM community_users WHERE id = ?');
$stmt->execute([$user_id]);
$prefs = $stmt->fetch();

if (!$prefs) {
    // Logged in but no row, shouldn't happen, but bail safely
    header('Location: login.php');
    exit;
}

// Check whether this user is also a license-key holder (drives the "reviews" notice)
$stmt = $pdo->prepare('SELECT 1 FROM license_keys WHERE email = ? LIMIT 1');
$stmt->execute([$prefs['email']]);
$is_license_holder = (bool) $stmt->fetchColumn();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $product_updates  = isset($_POST['email_pref_product_updates']) ? 1 : 0;
        $tips_onboarding  = isset($_POST['email_pref_tips_onboarding']) ? 1 : 0;
        $reviews          = isset($_POST['email_pref_reviews']) ? 1 : 0;
        $promotions       = isset($_POST['email_pref_promotions']) ? 1 : 0;
        $community_digest = isset($_POST['email_pref_community_digest']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare('UPDATE community_users
                                   SET email_pref_product_updates = ?,
                                       email_pref_tips_onboarding = ?,
                                       email_pref_reviews = ?,
                                       email_pref_promotions = ?,
                                       email_pref_community_digest = ?
                                   WHERE id = ?');
            $stmt->execute([$product_updates, $tips_onboarding, $reviews, $promotions, $community_digest, $user_id]);

            // A one-click unsubscribe leaves a row in email_suppressions, which the send-time gate reads first, so opting in here has to clear those rows.
            $opted_in_contexts = [];
            if ($product_updates)  $opted_in_contexts[] = 'product_updates';
            if ($tips_onboarding)  $opted_in_contexts[] = 'tips_onboarding';
            if ($reviews)          $opted_in_contexts[] = 'reviews';
            if ($promotions)       $opted_in_contexts[] = 'promotions';
            if ($community_digest) $opted_in_contexts[] = 'community_digest';

            $email_lc = strtolower(trim($prefs['email']));
            if (count($opted_in_contexts) > 0) {
                // If any category is opted-in, the blanket suppression must go.
                $del = $pdo->prepare("DELETE FROM email_suppressions WHERE email = ? AND context = 'all_marketing'");
                $del->execute([$email_lc]);

                $placeholders = implode(',', array_fill(0, count($opted_in_contexts), '?'));
                $del = $pdo->prepare("DELETE FROM email_suppressions WHERE email = ? AND context IN ($placeholders)");
                $del->execute(array_merge([$email_lc], $opted_in_contexts));
            }

            $prefs['email_pref_product_updates']  = $product_updates;
            $prefs['email_pref_tips_onboarding']  = $tips_onboarding;
            $prefs['email_pref_reviews']          = $reviews;
            $prefs['email_pref_promotions']       = $promotions;
            $prefs['email_pref_community_digest'] = $community_digest;

            $success_message = 'Email preferences updated successfully.';
        } catch (PDOException $e) {
            error_log('email_preferences update failed: ' . $e->getMessage());
            $error_message = 'Failed to update email preferences. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
    <title>Email Preferences - Argo Community</title>

    <script src="../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="email_preferences-style.css">
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/checkbox.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/styles/link.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">
    <link rel="stylesheet" href="../../resources/header/dark.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../resources/header/header.php'; ?>
    </header>

    <div class="wrapper">
        <a href="profile.php" class="link-no-underline back-link prefs-back-link">
            <?= svg_icon('arrow-back', 16) ?>
            Back to Profile
        </a>

        <div class="prefs-container">
            <div class="prefs-header">
                <h1>Email Preferences</h1>
                <p class="subtitle">Choose which emails you'd like to receive from Argo Books.</p>
            </div>

            <?php if ($success_message): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form method="post" class="prefs-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="prefs-section">
                    <h2>Account &amp; transactional emails</h2>
                    <ul class="transactional-list">
                        <li><span class="always-on-tag">Always on</span><span class="label">Payment receipts</span></li>
                        <li><span class="always-on-tag">Always on</span><span class="label">Subscription renewal &amp; failed payment notices</span></li>
                    </ul>
                    <div class="info-note">
                        These keep your account, payments, and recovery working. Turning them off would break things like password resets and receipt delivery.
                    </div>
                </div>

                <div class="prefs-section">
                    <h2>Marketing emails</h2>

                    <div class="checkbox">
                        <input type="checkbox" id="email_pref_product_updates" name="email_pref_product_updates"
                               <?php echo $prefs['email_pref_product_updates'] ? 'checked' : ''; ?>>
                        <label for="email_pref_product_updates">Product updates</label>
                    </div>
                    <p class="setting-description">New features, release notes, and what's new in Argo Books.</p>

                    <div class="checkbox">
                        <input type="checkbox" id="email_pref_tips_onboarding" name="email_pref_tips_onboarding"
                               <?php echo $prefs['email_pref_tips_onboarding'] ? 'checked' : ''; ?>>
                        <label for="email_pref_tips_onboarding">Tips &amp; onboarding</label>
                    </div>
                    <p class="setting-description">How-to guides and getting-started nudges, mostly useful when you're new.</p>

                    <div class="checkbox">
                        <input type="checkbox" id="email_pref_reviews" name="email_pref_reviews"
                               <?php echo $prefs['email_pref_reviews'] ? 'checked' : ''; ?>>
                        <label for="email_pref_reviews">Review requests</label>
                    </div>
                    <p class="setting-description">An occasional ask to leave a review on Capterra or share feedback directly. Sent rarely.</p>

                    <?php if ($is_license_holder): ?>
                        <div class="review-note">
                            <strong>Heads up:</strong> if you've purchased Argo Books, we may still ask you once for a review even with this off, since you're a paying customer. You can unsubscribe from that email itself when you receive it.
                        </div>
                    <?php endif; ?>

                    <div class="checkbox">
                        <input type="checkbox" id="email_pref_promotions" name="email_pref_promotions"
                               <?php echo $prefs['email_pref_promotions'] ? 'checked' : ''; ?>>
                        <label for="email_pref_promotions">Promotions &amp; offers</label>
                    </div>
                    <p class="setting-description">Discount codes and Premium upsells. Sent rarely.</p>

                    <div class="checkbox">
                        <input type="checkbox" id="email_pref_community_digest" name="email_pref_community_digest"
                               <?php echo $prefs['email_pref_community_digest'] ? 'checked' : ''; ?>>
                        <label for="email_pref_community_digest">Community digest</label>
                    </div>
                    <p class="setting-description">Replies to your community posts and comments.</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-blue">Save Preferences</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <?php include __DIR__ . '/../../resources/footer/footer.php'; ?>
    </footer>
</body>

</html>
