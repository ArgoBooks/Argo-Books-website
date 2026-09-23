<?php
require_once __DIR__ . '/../includes/site-base-path.php';
$base = site_base_path();

// Pages that are themselves the download destination set $hide_header_cta = true.
$show_header_cta = empty($hide_header_cta);
?>
<!-- BURGER MENU -->
<input class="menu-btn" type="checkbox" id="menu-btn" onclick="headerToggleMenu()">
<label class="menu-icon" id="menu-icon" for="menu-btn"><span class="nav-icon"></span></label>

<div class="header-inner">
  <!-- LOGO -->
  <a href="<?= $base ?>">
    <img class="logo" id="logo" alt="Argo Books Logo" src="<?= $base ?>resources/images/argo-logo/argo-logo-white.png">
  </a>

  <div class="menu-container" id="menu-container">
    <!-- MENU -->
    <nav aria-label="Main navigation">
      <ul class="menu">
        <li class="has-dropdown">
          <a class="features" href="<?= $base ?>features/">Features
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6,9 12,15 18,9"/></svg>
          </a>
          <div class="dropdown-menu">
            <div class="dropdown-split">
              <div class="dropdown-side">
                <div class="dropdown-columns">
                  <div class="dropdown-col">
                    <span class="dropdown-col-header">Automation</span>
                    <a class="dropdown-item" href="<?= $base ?>features/receipt-scanning/">Receipt Scanning</a>
                    <a class="dropdown-item" href="<?= $base ?>features/bank-statement-import/">Bank Statement Import</a>
                    <a class="dropdown-item" href="<?= $base ?>features/spreadsheet-import/">Spreadsheet Import</a>
                    <a class="dropdown-item" href="<?= $base ?>features/predictive-analytics/">Predictive Analytics</a>
                  </div>
                  <div class="dropdown-col">
                    <span class="dropdown-col-header">Financial Tools</span>
                    <a class="dropdown-item" href="<?= $base ?>features/expense-revenue-tracking/">Expense & Revenue Tracking</a>
                    <a class="dropdown-item" href="<?= $base ?>features/invoicing/">Invoicing</a>
                    <a class="dropdown-item" href="<?= $base ?>features/payroll/">Payroll</a>
                    <a class="dropdown-item" href="<?= $base ?>features/report-builder/">Report Builder</a>
                  </div>
                  <div class="dropdown-col">
                    <span class="dropdown-col-header">Operations</span>
                    <a class="dropdown-item" href="<?= $base ?>features/inventory-management/">Inventory Management</a>
                    <a class="dropdown-item" href="<?= $base ?>features/rental-management/">Rental Management</a>
                    <a class="dropdown-item" href="<?= $base ?>features/customer-management/">Customer Management</a>
                  </div>
                </div>
                <a class="dropdown-all-features" href="<?= $base ?>features/">
                  View all features
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
              </div>
              <div class="dropdown-vline"></div>
              <div class="dropdown-side">
                <div class="dropdown-columns">
                  <div class="dropdown-col">
                    <span class="dropdown-col-header">Integrations</span>
                    <a class="dropdown-item" href="<?= $base ?>integrations/stripe/">Stripe</a>
                    <a class="dropdown-item" href="<?= $base ?>integrations/argo-books-api/">Argo Books API</a>
                  </div>
                </div>
                <a class="dropdown-all-features" href="<?= $base ?>integrations/">
                  View all integrations
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
              </div>
            </div>
          </div>
        </li>
        <li><a class="pricing" href="<?= $base ?>pricing/">Pricing</a></li>
        <li><a class="whats-new" href="<?= $base ?>whats-new/">What's new</a></li>
        <li><a class="about-us" href="<?= $base ?>about-us/">About us</a></li>
        <li><a class="documentation" href="<?= $base ?>documentation/">Documentation</a></li>
        <li><a class="community" href="<?= $base ?>community/">Community</a></li>
        <li><a class="contact-us" href="<?= $base ?>contact-us/">Contact us</a></li>
      </ul>
    </nav>
  </div>

  <!-- Account Button -->
  <div class="right-container">
    <?php if ($show_header_cta): ?>
    <a class="header-cta" href="<?= $base ?>downloads/">Download</a>
    <?php endif; ?>
    <a id="account-button" class="account-button" href="<?= $base ?>community/users/profile.php">
      <div class="account-avatar">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
      </div>
    </a>
  </div>
</div>

<div id="menu" class="hamburger-nav-menu">
  <nav aria-label="Mobile navigation">
    <ul>
      <li><a href="<?= $base ?>features/">Features</a></li>
      <li><a href="<?= $base ?>pricing/">Pricing</a></li>
      <li><a href="<?= $base ?>whats-new/">What's new</a></li>
      <li><a href="<?= $base ?>about-us/">About us</a></li>
      <li><a href="<?= $base ?>documentation/">Documentation</a></li>
      <li><a href="<?= $base ?>community/">Community</a></li>
      <li><a href="<?= $base ?>contact-us/">Contact Us</a></li>
      <li><a href="<?= $base ?>community/users/login.php" id="mobile-account-link">My Account</a></li>
    </ul>
  </nav>
</div>

<script>
  const menu = document.getElementById('menu');
  const header = document.querySelector('header');
  const menuBtn = document.getElementById('menu-btn');
  const menuIcon = document.getElementById('menu-icon');
  let lastScroll = 0;

  function headerToggleMenu() {
    if (!menu.classList.contains('active')) {
      // Opening the menu
      menu.classList.add('active');

      const currentMenuHeight = menu.scrollHeight;
      menu.style.height = currentMenuHeight + 'px';

      document.body.classList.add('menu-open');
    } else {
      // Closing the menu
      menu.classList.remove('active');
      menu.style.height = '0';
      document.body.classList.remove('menu-open');
    }
  }

  // Sticky header functionality
  window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;

    // Add sticky class only after scrolling past header height
    if (currentScroll > header.offsetHeight) {
      header.classList.add('sticky');

      // Show header when scrolling up, hide when scrolling down
      if (currentScroll < lastScroll) {
        header.classList.add('show');
      } else {
        header.classList.remove('show');
      }
    } else {
      header.classList.remove('sticky', 'show');
    }

    lastScroll = currentScroll;
  });

  // Close menu when clicking outside
  document.addEventListener('click', (e) => {
    // Don't close if clicking header
    if (menuIcon.contains(e.target)) {
      return;
    }

    // Close only if menu is active and click is outside
    if (menu.classList.contains('active') && !header.contains(e.target)) {
      menuBtn.checked = false;
      headerToggleMenu();
    }
  });

  // Handle window resize to ensure menu works properly
  window.addEventListener('resize', function () {
    // If menu is open and we resize to desktop, close it
    if (window.innerWidth > 900 && menu.classList.contains('active')) {
      menuBtn.checked = false;
      headerToggleMenu();
    }
  });

  // Dropdown touch support for mobile/tablet
  (function() {
    var dropdownLi = document.querySelector('.has-dropdown');
    if (!dropdownLi) return;
    var dropdownLink = dropdownLi.querySelector(':scope > a');

    dropdownLink.addEventListener('click', function(e) {
      // Only intercept on touch devices
      if (!('ontouchstart' in window || navigator.maxTouchPoints > 0)) return;
      if (dropdownLi.classList.contains('dropdown-open')) {
        // Second tap: navigate
        return;
      }
      // First tap: toggle dropdown open
      e.preventDefault();
      dropdownLi.classList.add('dropdown-open');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!dropdownLi.contains(e.target)) {
        dropdownLi.classList.remove('dropdown-open');
      }
    });
  })();
</script>
