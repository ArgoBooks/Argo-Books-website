<?php
/**
 * Uninstall survey.
 *
 * The Windows uninstaller opens this page in the browser. Nothing is sent from the machine:
 * the page exists, the person chooses whether to answer it, and it collects nothing that
 * identifies them. Version and platform ride in on the link so the answers can be grouped.
 *
 * Answering posts back to this same page so there is no JavaScript to block and a reload
 * after answering cannot submit twice.
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../rate_limit_helper.php';

// Trimmed: the uninstaller's URL field keeps a trailing line break, which would otherwise
// arrive as part of the platform and match nothing.
$version = isset($_GET['v']) ? substr(preg_replace('/[^0-9A-Za-z.\-]/', '', $_GET['v']), 0, 20) : '';
$platform = strtolower(trim((string) ($_GET['p'] ?? '')));
$platform = in_array($platform, ['windows', 'mac'], true) ? $platform : '';

// The reasons someone actually leaves over, in the order they are worth knowing about. The value
// is stored; the label is what gets read.
$reasons = [
    'missing_feature' => "It didn't do something I need",
    'too_complicated' => 'It was harder to use than I expected',
    'switched'        => 'I went with something else',
    'price'           => "The price didn't work for me",
    'not_needed'      => "I don't need accounting software right now",
    'problem'         => "It didn't work properly on my computer",
    'just_looking'    => 'I was only trying it out',
    'other'           => 'Something else',
];

$answered = isset($_GET['thanks']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = $_POST['reason'] ?? '';
    $comment = trim((string) ($_POST['comment'] ?? ''));

    if (!isset($reasons[$reason])) {
        $error = 'Please pick one of the options.';
    } elseif (rate_limit_hit('uninstall_feedback', get_client_ip())) {
        $error = 'Thanks, we already have your answer.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO uninstall_feedback (reason, comment, app_version, platform, environment)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $reason,
                $comment !== '' ? mb_substr($comment, 0, 1000) : null,
                $version !== '' ? $version : null,
                $platform !== '' ? $platform : null,
                current_environment(),
            ]);

            header('Location: /uninstall/?thanks=1');
            exit;
        } catch (Exception $e) {
            error_log('Uninstall feedback error: ' . $e->getMessage());
            $error = 'That did not save. You can close this page.';
        }
    }
}

$title = $answered ? 'Thanks for telling me' : 'Argo Books has been removed';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Tell us why you removed Argo Books.">
  <meta name="author" content="Argo">
  <meta name="robots" content="noindex, nofollow">

  <meta property="og:title" content="Argo Books - <?= htmlspecialchars($title) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Argo Books">

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
      <div class="success-container">
        <div class="success-content">

          <?php if ($answered): ?>
            <h1>Thanks for telling me</h1>
            <p class="success-message">
              That is genuinely useful. Your books are still on your computer in the file you saved
              them to, and reinstalling picks them back up.
            </p>

            <div class="signature">Evan, Argo Books</div>

            <div class="action-buttons">
              <a href="/" class="btn primary-btn">Visit Argo Books</a>
              <a href="/contact-us" class="btn secondary-btn">Get in touch</a>
            </div>
          <?php else: ?>
            <h1>Argo Books has been removed</h1>
            <p class="success-message">
              Your company file is still on your computer, exactly where you saved it. Nothing was
              deleted, and reinstalling opens it again.
            </p>
            <p class="success-message">
              If you have a moment, what made you remove it? One click is plenty, and it is the
              only thing that tells me what to fix.
            </p>

            <?php if ($error !== ''): ?>
              <p class="uninstall-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="post" action="/uninstall/<?= $version !== '' || $platform !== ''
                ? '?' . http_build_query(array_filter(['v' => $version, 'p' => $platform]))
                : '' ?>" class="uninstall-form">
              <?php foreach ($reasons as $value => $label): ?>
                <label class="uninstall-reason">
                  <input type="radio" name="reason" value="<?= htmlspecialchars($value) ?>">
                  <span><?= htmlspecialchars($label) ?></span>
                </label>
              <?php endforeach; ?>

              <label class="uninstall-comment-label" for="uninstall-comment">
                Anything else you want to add (optional)
              </label>
              <textarea id="uninstall-comment" name="comment" rows="3" maxlength="1000"
                        placeholder="What would have made you keep it?"></textarea>

              <div class="action-buttons">
                <button type="submit" class="btn primary-btn">Send</button>
                <a href="/" class="btn secondary-btn">No thanks</a>
              </div>
            </form>
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
