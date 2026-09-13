<?php
/**
 * Landing page for the "Verify email" button in the licence activation email.
 *
 * One click, no code to type: the token in the link is the proof. Verification is a data quality
 * flag and nothing more, so this page never grants, checks or withholds Premium. Someone who
 * never clicks it keeps everything they paid for.
 */

require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../db_connect.php';

$state = 'invalid';
$email = '';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

try {
    if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
        $stmt = $pdo->prepare('
            SELECT id, customer_email, customer_email_verified_at
            FROM premium_subscription_keys
            WHERE customer_email_token = ?
            LIMIT 1
        ');
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if ($row) {
            $email = (string) $row['customer_email'];

            if ($row['customer_email_verified_at'] !== null) {
                // Clicking twice is normal: mail clients prefetch links, and people forward
                // themselves the email. It is not an error and must not look like one.
                $state = 'already_verified';
            } else {
                $stmt = $pdo->prepare('
                    UPDATE premium_subscription_keys
                    SET customer_email_verified_at = NOW()
                    WHERE id = ?
                ');
                $stmt->execute([$row['id']]);
                $state = 'verified';
            }
        }
    }
} catch (PDOException $e) {
    error_log('License email verification failed: ' . $e->getMessage());
    $state = 'error';
}

$titles = [
    'verified'         => 'Email verified',
    'already_verified' => 'Already verified',
    'invalid'          => 'This link is not valid',
    'error'            => 'Something went wrong',
];
$title = $titles[$state];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Confirm your email address for Argo Books Premium.">
  <meta name="author" content="Argo">
  <meta name="robots" content="noindex, nofollow">

  <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
  <title>Argo Books - <?= htmlspecialchars($title) ?></title>

  <script src="../resources/scripts/main.js"></script>

  <link rel="stylesheet" href="../unsubscribe/style.css">
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

          <?php if ($state === 'verified'): ?>
            <p class="success-message">
              Thanks, <strong><?= htmlspecialchars($email) ?></strong> is confirmed. We can now reach
              you about your licence.
            </p>
            <p class="success-message">
              Your Premium features were unlocked the moment you activated, so there is nothing
              else to do. You can close this page and carry on.
            </p>
          <?php elseif ($state === 'already_verified'): ?>
            <p class="success-message">
              <strong><?= htmlspecialchars($email) ?></strong> was already confirmed, so you are all
              set. You can close this page.
            </p>
          <?php elseif ($state === 'error'): ?>
            <p class="success-message">
              We could not confirm your email just now. Your Premium licence is active either way,
              so nothing is at risk. Please try the link again in a few minutes.
            </p>
          <?php else: ?>
            <p class="success-message">
              This verification link is not valid. It may have been mistyped, or it may belong to a
              licence that has since been re-issued.
            </p>
            <p class="success-message">
              This does not affect your Premium licence, which stays active whether or not your
              email is confirmed. If you need a hand, get in touch and we will sort it out.
            </p>
          <?php endif; ?>

          <div class="action-buttons">
            <a href="../" class="btn primary-btn">Go to Argo Books</a>
            <?php if ($state === 'invalid' || $state === 'error'): ?>
              <a href="../contact-us/" class="btn secondary-btn">Contact support</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
</body>

</html>
