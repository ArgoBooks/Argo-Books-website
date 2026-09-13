<?php
// subscribe/confirm.php
//
// Double opt-in confirmation landing page. A no-account subscriber (e.g. someone
// who ticked the opt-in box on the Profit Analyzer) lands here from the confirm
// link in their confirmation email. Reuses the unsubscribe page's styling.

require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../email_marketing.php';

$token = isset($_GET['t']) ? trim($_GET['t']) : '';

$state = 'invalid'; // 'confirmed' | 'invalid'
try {
    if ($token !== '' && confirm_subscriber($token) !== null) {
        $state = 'confirmed';
    }
} catch (Exception $e) {
    error_log('Subscription confirm error: ' . $e->getMessage());
    $state = 'invalid';
}

$titles = [
    'confirmed' => "You're subscribed",
    'invalid'   => 'Link expired',
];
$title = $titles[$state];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Confirm your Argo Books email subscription.">
  <meta name="author" content="Argo">
  <meta name="robots" content="noindex, nofollow">

  <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
  <title>Argo Books - <?= htmlspecialchars($title) ?></title>

  <script src="../resources/scripts/main.js"></script>

  <link rel="stylesheet" href="../unsubscribe/style.css">
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

          <?php if ($state === 'confirmed'): ?>
            <p class="success-message">
              You're on the list. We'll send the occasional tip and product update, nothing more.
              You can unsubscribe from any email in one click.
            </p>

            <div class="action-buttons">
              <a href="/" class="btn primary-btn">Visit Argo Books</a>
            </div>

          <?php else: ?>
            <p class="success-message">
              This confirmation link is invalid or has expired. If you still want to subscribe,
              run your spreadsheet through the free Profit Analyzer again and tick the opt-in box,
              or reply to any email from us and we'll sort it out.
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
