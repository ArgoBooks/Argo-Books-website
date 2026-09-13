<?php require_once __DIR__ . '/../includes/site-base-path.php'; $base = site_base_path(); ?>
<div class="footer-content">
  <div class="footer-section">
    <img src="<?= $base ?>resources/images/argo-logo/argo-logo-white.png" alt="Argo Books Logo" class="footer-logo">
    <p>Made in Saskatoon, Canada <img src="<?= $base ?>resources/images/canada-flag.svg" alt="Canadian flag" width="24" height="12" style="vertical-align: middle; display: inline-block; margin-left: 4px;"></p>
  </div>
  <div class="footer-section">
    <h3>Features</h3>
    <ul class="footer-links">
      <li><a href="<?= $base ?>features/receipt-scanning/">AI Receipt Scanning</a></li>
      <li><a href="<?= $base ?>features/expense-revenue-tracking/">Expense & Revenue</a></li>
      <li><a href="<?= $base ?>features/predictive-analytics/">Predictive Analytics</a></li>
      <li><a href="<?= $base ?>features/inventory-management/">Inventory Management</a></li>
      <li><a href="<?= $base ?>features/invoicing/">Invoicing</a></li>
      <li><a href="<?= $base ?>payroll/">Canadian Payroll</a></li>
      <li><a href="<?= $base ?>features/">View All Features</a></li>
    </ul>
  </div>
  <div class="footer-section">
    <h3>Who It's For</h3>
    <ul class="footer-links">
      <li><a href="<?= $base ?>for-contractors/">For Contractors</a></li>
      <li><a href="<?= $base ?>for-landscapers/">For Landscapers</a></li>
      <li><a href="<?= $base ?>for-cleaning-companies/">For Cleaning Companies</a></li>
      <li><a href="<?= $base ?>who-its-for/">View All Industries</a></li>
    </ul>
  </div>
  <div class="footer-section">
    <h3>Compare</h3>
    <ul class="footer-links">
      <li><a href="<?= $base ?>compare/">Compare Argo Books</a></li>
      <li><a href="<?= $base ?>best-quickbooks-alternatives/">Best QuickBooks Alternatives</a></li>
    </ul>
  </div>
  <div class="footer-section">
    <h3>Legal</h3>
    <ul class="footer-links">
      <li><a href="<?= $base ?>legal/terms.php">Terms of Service</a></li>
      <li><a href="<?= $base ?>legal/privacy.php">Privacy Policy</a></li>
      <li><a href="<?= $base ?>legal/refund.php">Refund Policy</a></li>
      <li><a href="<?= $base ?>legal/eula.php">EULA</a></li>
    </ul>
  </div>
  <div class="footer-section">
    <h3>Company</h3>
    <ul class="footer-links">
      <li><a href="<?= $base ?>about-us/">About Us</a></li>
      <li><a href="<?= $base ?>contact-us/">Contact Us</a></li>
      <li><a href="<?= $base ?>guides/">Guides</a></li>
      <li><a href="<?= $base ?>tools/">Tools</a></li>
      <li><a href="<?= $base ?>affiliates/">Affiliate Program</a></li>
      <li><a href="<?= $base ?>review/">Leave a Review</a></li>
    </ul>
  </div>
</div>
<div class="footer-bottom">
  <p>&copy; 2026 Argo Books. All Rights Reserved.</p>
</div>
<?php
// JS-confirmation beacon (bot filter for the marketing funnel); shared with
// shared/layout.php via the single partial.
$confirm_url_js = json_encode(($base ?? '/') . 'api/referral/confirm.php');
include __DIR__ . '/../referral-confirm-beacon.php';
?>
