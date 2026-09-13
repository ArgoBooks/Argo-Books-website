<?php
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../email_marketing.php';

$state = 'invalid';
$displayName = '';
$resubscribeUrl = '';
$category_label = '';

$tokenUser = isset($_GET['u']) ? trim($_GET['u']) : '';
$tokenLicense = isset($_GET['l']) ? trim($_GET['l']) : '';
$tokenSubscriber = isset($_GET['s']) ? trim($_GET['s']) : '';
$rawContext = isset($_GET['c']) ? trim($_GET['c']) : 'all';
$isUndo = isset($_GET['undo']) && $_GET['undo'] === '1';

$context_labels = [
    'product_updates'  => 'product updates',
    'tips_onboarding'  => 'tips & onboarding',
    'reviews'          => 'review requests',
    'promotions'       => 'promotions & offers',
    'community_digest' => 'community digest',
    'all'              => 'all marketing emails',
];
$contexts = marketing_contexts();

try {
    if ($tokenUser !== '' && preg_match('/^[a-f0-9]{24,128}$/', $tokenUser)) {
        // Community-user-scoped flow
        $context = isset($context_labels[$rawContext]) ? $rawContext : 'all';
        $category_label = $context_labels[$context];

        $stmt = $pdo->prepare('SELECT id, username, email FROM community_users WHERE email_pref_unsubscribe_token = ? LIMIT 1');
        $stmt->execute([$tokenUser]);
        $user = $stmt->fetch();

        if ($user) {
            $displayName = $user['username'];
            $email = strtolower(trim($user['email']));
            $resubscribeUrl = '/unsubscribe/marketing.php?u=' . urlencode($tokenUser) . '&c=' . urlencode($context);

            // Build the list of pref columns we'll touch.
            $columns = ($context === 'all') ? array_values($contexts) : [$contexts[$context]];

            if ($isUndo) {
                // Re-subscribe: flip prefs back on, drop suppressions
                $assignments = implode(', ', array_map(fn($c) => "$c = 1", $columns));
                $stmt = $pdo->prepare("UPDATE community_users SET $assignments WHERE id = ?");
                $stmt->execute([$user['id']]);

                $deleteContexts = ($context === 'all')
                    ? array_merge(array_keys($contexts), ['all_marketing'])
                    : [$context, 'all_marketing'];
                $placeholders = implode(',', array_fill(0, count($deleteContexts), '?'));
                $stmt = $pdo->prepare("DELETE FROM email_suppressions WHERE email = ? AND context IN ($placeholders)");
                $stmt->execute(array_merge([$email], $deleteContexts));

                $state = 'resubscribed';
            } else {
                // Unsubscribe
                $assignments = implode(', ', array_map(fn($c) => "$c = 0", $columns));
                $stmt = $pdo->prepare("UPDATE community_users SET $assignments WHERE id = ?");
                $stmt->execute([$user['id']]);

                $insertContexts = ($context === 'all') ? ['all_marketing'] : [$context];
                $alreadySuppressed = true;
                foreach ($insertContexts as $ctx) {
                    $check = $pdo->prepare('SELECT 1 FROM email_suppressions WHERE email = ? AND context = ? LIMIT 1');
                    $check->execute([$email, $ctx]);
                    if (!$check->fetchColumn()) {
                        $alreadySuppressed = false;
                        $ins = $pdo->prepare('INSERT INTO email_suppressions (email, context, reason, source_id) VALUES (?, ?, ?, ?)');
                        $ins->execute([$email, $ctx, 'one-click unsubscribe (community user)', $user['id']]);
                    }
                }

                $state = $alreadySuppressed ? 'already_unsubscribed' : 'unsubscribed';
            }
        }
    } elseif ($tokenLicense !== '' && preg_match('/^[a-f0-9]{24,128}$/', $tokenLicense)) {
        // License-only flow (review/feedback emails)
        $category_label = 'all marketing emails';

        $stmt = $pdo->prepare('SELECT id, email FROM license_keys WHERE review_email_token = ? LIMIT 1');
        $stmt->execute([$tokenLicense]);
        $license = $stmt->fetch();

        if ($license) {
            $email = strtolower(trim($license['email']));
            $resubscribeUrl = '/unsubscribe/marketing.php?l=' . urlencode($tokenLicense);

            if ($isUndo) {
                $stmt = $pdo->prepare("DELETE FROM email_suppressions WHERE email = ? AND context IN ('reviews','all_marketing')");
                $stmt->execute([$email]);
                $state = 'resubscribed';
            } else {
                $alreadySuppressed = true;
                foreach (['reviews', 'all_marketing'] as $ctx) {
                    $check = $pdo->prepare('SELECT 1 FROM email_suppressions WHERE email = ? AND context = ? LIMIT 1');
                    $check->execute([$email, $ctx]);
                    if (!$check->fetchColumn()) {
                        $alreadySuppressed = false;
                        $ins = $pdo->prepare('INSERT INTO email_suppressions (email, context, reason, source_id) VALUES (?, ?, ?, ?)');
                        $ins->execute([$email, $ctx, 'one-click unsubscribe (license holder)', $license['id']]);
                    }
                }
                $state = $alreadySuppressed ? 'already_unsubscribed' : 'unsubscribed';
            }
        }
    } elseif ($tokenSubscriber !== '' && preg_match('/^[a-f0-9]{24,128}$/', $tokenSubscriber)) {
        // No-account subscriber flow (marketing_subscribers, e.g. Profit Analyzer opt-ins)
        $category_label = 'tips & product updates';

        $stmt = $pdo->prepare('SELECT id, email, context, status FROM marketing_subscribers WHERE unsubscribe_token = ? LIMIT 1');
        $stmt->execute([$tokenSubscriber]);
        $sub = $stmt->fetch();

        if ($sub) {
            $email = strtolower(trim($sub['email']));
            $resubscribeUrl = '/unsubscribe/marketing.php?s=' . urlencode($tokenSubscriber);

            if ($isUndo) {
                // Re-subscribe: they're actively clicking "undo" here, which is itself
                // a fresh opt-in, so confirm directly rather than re-sending a confirm email.
                $upd = $pdo->prepare("UPDATE marketing_subscribers SET status = 'confirmed', confirmed_at = NOW(), unsubscribed_at = NULL WHERE id = ?");
                $upd->execute([$sub['id']]);

                $del = $pdo->prepare('DELETE FROM email_suppressions WHERE email = ? AND context IN (?, ?)');
                $del->execute([$email, $sub['context'], 'all_marketing']);

                $state = 'resubscribed';
            } else {
                $already = ($sub['status'] === 'unsubscribed');
                unsubscribe_subscriber_by_token($tokenSubscriber);
                $state = $already ? 'already_unsubscribed' : 'unsubscribed';
            }
        }
    }
} catch (Exception $e) {
    error_log('Marketing unsubscribe error: ' . $e->getMessage());
    $state = 'invalid';
}

$titles = [
    'unsubscribed'         => "You've been unsubscribed",
    'already_unsubscribed' => 'Already unsubscribed',
    'resubscribed'         => 'Welcome back',
    'invalid'              => 'Link expired',
];
$title = $titles[$state];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage your Argo Books email preferences.">
  <meta name="author" content="Argo">
  <meta name="robots" content="noindex, nofollow">

  <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
  <title>Argo Books - <?= htmlspecialchars($title) ?></title>

  <script src="../resources/scripts/main.js"></script>

  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="../resources/styles/custom-colors.css">
  <link rel="stylesheet" href="../resources/header/style.css">
  <link rel="stylesheet" href="../resources/header/dark.css">
  <link rel="stylesheet" href="../resources/footer/style.css">
</head>

<body>
  <header>
    <?php include __DIR__ . '/../resources/header/header.php'; ?>
  </header>
  <main>
    <section class="first">
      <div class="success-container state-<?= htmlspecialchars($state) ?>">
        <div class="success-content">
          <h1><?= htmlspecialchars($title) ?></h1>

          <?php if ($state === 'unsubscribed'): ?>
            <p class="success-message">
              <?php if ($displayName): ?>
                <strong><?= htmlspecialchars($displayName) ?></strong>, you won't receive <?= htmlspecialchars($category_label) ?> from Argo Books.
              <?php else: ?>
                You won't receive <?= htmlspecialchars($category_label) ?> from Argo Books.
              <?php endif; ?>
              We'll still send transactional emails (receipts, password resets, etc.) since those are required for your account.
            </p>

            <div class="action-buttons">
              <a href="/" class="btn primary-btn">Visit Argo Books</a>
            </div>

            <p class="undo-line">
              Clicked by mistake? <a href="<?= htmlspecialchars($resubscribeUrl) ?>&amp;undo=1">Re-subscribe</a>
            </p>

          <?php elseif ($state === 'already_unsubscribed'): ?>
            <p class="success-message">
              You're already unsubscribed from <?= htmlspecialchars($category_label) ?>.
            </p>

            <div class="action-buttons">
              <a href="/" class="btn primary-btn">Visit Argo Books</a>
            </div>

            <p class="undo-line">
              Changed your mind? <a href="<?= htmlspecialchars($resubscribeUrl) ?>&amp;undo=1">Re-subscribe</a>
            </p>

          <?php elseif ($state === 'resubscribed'): ?>
            <p class="success-message">
              Welcome back. We'll start sending <?= htmlspecialchars($category_label) ?> again. You can change this anytime in your email preferences.
            </p>

            <div class="action-buttons">
              <a href="/" class="btn primary-btn">Visit Argo Books</a>
              <a href="<?= htmlspecialchars($resubscribeUrl) ?>" class="btn secondary-btn">Unsubscribe again</a>
            </div>

          <?php else: ?>
            <p class="success-message">
              This unsubscribe link is invalid or has expired. If you'd like to manage your email preferences, log in to your community account and visit the email preferences page, or reply to any email from us and we'll handle it.
            </p>

            <div class="action-buttons">
              <a href="/" class="btn primary-btn">Visit Argo Books</a>
              <a href="/contact-us" class="btn secondary-btn">Contact Support</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
  </footer>
</body>

</html>
