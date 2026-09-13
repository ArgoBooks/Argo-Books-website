<?php require_once __DIR__ . '/../../resources/icons.php';
require_once __DIR__ . '/../../partials/fonts.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,follow">
  <meta name="description" content="">
  <meta name="keywords" content="">
  <meta name="author" content="Argo">

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="Argo Books - Message Sent">
  <meta property="og:description" content="Your message has been sent to Argo Books support.">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Argo Books">
  <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <!-- Twitter Meta Tags -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Argo Books - Message Sent">
  <meta name="twitter:description" content="Your message has been sent to Argo Books support.">
  <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

  <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
  <title>Argo Books - Message Sent</title>

  <script src="../../resources/scripts/main.js"></script>

  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
  <link rel="stylesheet" href="../../resources/header/style.css">
  <link rel="stylesheet" href="../../resources/footer/style.css">
  <?= argo_font_links('default', '  ') ?>
  <link rel="stylesheet" href="../../resources/styles/typography.css">
</head>

<body>
  <header>
    <?php include __DIR__ . '/../../resources/header/header.php'; ?>
  </header>
  <main>

  <section class="hero">
    <div class="hero-inner">
      <div class="success-icon">
        <?= svg_icon('circle-check', null, '', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
      </div>
      <h1>Got it, your message is sent</h1>
      <p class="success-message">Thanks for writing. I read every message myself and will reply to the email
        address you gave.</p>
    </div>
  </section>

  <section class="first">
    <div class="success-container">
      <div class="success-content">
        <div class="info-box">
          <h3>What happens next?</h3>
          <ul>
            <li>Your message is in the Argo Books support inbox</li>
            <li>I typically reply within 1-8 business hours</li>
          </ul>
        </div>

        <div class="action-buttons">
          <a href="/" class="btn primary-btn">Return to Home</a>
          <a href="../" class="btn secondary-btn">Back to Contact</a>
        </div>
      </div>
    </div>
  </section>

  </main>

  <footer class="footer">
    <?php include __DIR__ . '/../../resources/footer/footer.php'; ?>
  </footer>
</body>

</html>