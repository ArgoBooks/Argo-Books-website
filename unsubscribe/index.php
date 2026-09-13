<?php
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../db_connect.php';

$state = 'invalid';
$businessName = '';
$unsubscribeUrl = '';

$token = isset($_GET['t']) ? trim($_GET['t']) : '';
$isUndo = isset($_GET['undo']) && $_GET['undo'] === '1';

if ($token !== '' && preg_match('/^[a-f0-9]{8,128}$/', $token)) {
    try {
        $stmt = $pdo->prepare("SELECT id, business_name, email, status FROM outreach_leads WHERE unsubscribe_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lead) {
            $businessName = $lead['business_name'];
            $email = strtolower(trim($lead['email'] ?? ''));
            $unsubscribeUrl = '/unsubscribe?t=' . urlencode($token);

            if ($isUndo) {
                // Re-subscribe: remove suppression and revert status if it matches
                if ($email !== '') {
                    $del = $pdo->prepare("DELETE FROM email_suppressions WHERE email = ? AND context = 'outreach' AND source_id = ?");
                    $del->execute([$email, $lead['id']]);
                }
                $upd = $pdo->prepare("UPDATE outreach_leads SET status = 'contacted' WHERE id = ? AND status = 'not_interested'");
                $upd->execute([$lead['id']]);

                $log = $pdo->prepare("INSERT INTO outreach_activity_log (lead_id, action_type, details) VALUES (?, 'resubscribed', 'Re-subscribed via undo link')");
                $log->execute([$lead['id']]);

                $state = 'resubscribed';
            } else {
                // Unsubscribe
                $alreadySuppressed = false;
                if ($email !== '') {
                    $check = $pdo->prepare("SELECT 1 FROM email_suppressions WHERE email = ? AND context = 'outreach' LIMIT 1");
                    $check->execute([$email]);
                    $alreadySuppressed = (bool) $check->fetchColumn();

                    if (!$alreadySuppressed) {
                        $ins = $pdo->prepare("INSERT INTO email_suppressions (email, context, reason, source_id) VALUES (?, 'outreach', 'one-click unsubscribe', ?)");
                        $ins->execute([$email, $lead['id']]);
                    }
                }

                $upd = $pdo->prepare("UPDATE outreach_leads SET status = 'not_interested' WHERE id = ? AND status NOT IN ('not_interested','onboarded')");
                $upd->execute([$lead['id']]);

                $log = $pdo->prepare("INSERT INTO outreach_activity_log (lead_id, action_type, details) VALUES (?, 'unsubscribed', ?)");
                $log->execute([$lead['id'], $alreadySuppressed ? 'Already unsubscribed (re-visited link)' : 'Unsubscribed via one-click email link']);

                $state = $alreadySuppressed ? 'already_unsubscribed' : 'unsubscribed';
            }
        }
    } catch (Exception $e) {
        error_log('Unsubscribe error: ' . $e->getMessage());
        $state = 'invalid';
    }
}

$titles = [
    'unsubscribed' => "You've been unsubscribed",
    'already_unsubscribed' => "Already unsubscribed",
    'resubscribed' => 'Welcome back',
    'invalid' => 'Link expired',
];
$title = $titles[$state];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage your email preferences for Argo Books outreach.">
  <meta name="author" content="Argo">
  <meta name="robots" content="noindex, nofollow">

  <meta property="og:title" content="Argo Books - <?= htmlspecialchars($title) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Argo Books">

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
              <?php if ($businessName): ?>
                <strong><?= htmlspecialchars($businessName) ?></strong>, you won't receive any more emails from me.
              <?php else: ?>
                You won't receive any more emails from me.
              <?php endif; ?>
              Thanks for taking the time to read, and wishing you the best with your business.
            </p>

            <div class="info-box">
              <h3>What happens next?</h3>
              <ul>
                <li>Your email address has been added to my suppression list</li>
                <li>No further outreach will be sent to you</li>
                <li>This takes effect immediately</li>
              </ul>
            </div>

            <div class="signature">Evan, Argo Books</div>

            <div class="action-buttons">
              <a href="/" class="btn primary-btn">Visit Argo Books</a>
            </div>

            <p class="undo-line">
              Clicked by mistake? <a href="<?= htmlspecialchars($unsubscribeUrl) ?>&amp;undo=1">Re-subscribe</a>
            </p>

          <?php elseif ($state === 'already_unsubscribed'): ?>
            <p class="success-message">
              <?php if ($businessName): ?>
                <strong><?= htmlspecialchars($businessName) ?></strong>, you're already on my suppression list, and you won't receive any more emails from me.
              <?php else: ?>
                You're already on my suppression list, and you won't receive any more emails from me.
              <?php endif; ?>
            </p>

            <div class="signature">Evan, Argo Books</div>

            <div class="action-buttons">
              <a href="/" class="btn primary-btn">Visit Argo Books</a>
            </div>

            <p class="undo-line">
              Changed your mind? <a href="<?= htmlspecialchars($unsubscribeUrl) ?>&amp;undo=1">Re-subscribe</a>
            </p>

          <?php elseif ($state === 'resubscribed'): ?>
            <p class="success-message">
              <?php if ($businessName): ?>
                Welcome back, <strong><?= htmlspecialchars($businessName) ?></strong>. You've been re-subscribed and I can follow up if there's anything I can help with.
              <?php else: ?>
                You've been re-subscribed.
              <?php endif; ?>
            </p>

            <div class="signature">Evan, Argo Books</div>

            <div class="action-buttons">
              <a href="/" class="btn primary-btn">Visit Argo Books</a>
              <a href="<?= htmlspecialchars($unsubscribeUrl) ?>" class="btn secondary-btn">Unsubscribe again</a>
            </div>

          <?php else: ?>
            <p class="success-message">
              This unsubscribe link has expired or is no longer valid. If you're receiving unwanted emails from me, please reply directly and I'll remove you from my list.
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
