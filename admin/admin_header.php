<?php
require_once __DIR__ . '/../resources/icons.php';
// Get current page and directory for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Determine base path for assets (different for subdirectories vs root)
$in_subdir = ($current_dir !== 'admin');
$base_path = $in_subdir ? '../' : '';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script>
        // Apply saved theme immediately to prevent flash
        (function() {
            var theme = localStorage.getItem('admin-theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $base_path; ?>../resources/images/argo-logo/argo-icon.ico">
    <title>Admin - Argo Books</title>

    <!-- Preconnect hints -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        // Set Chart.js default text color based on current theme
        (function() {
            var theme = document.documentElement.getAttribute('data-theme') || 'light';
            Chart.defaults.color = theme === 'dark' ? '#ffffff' : '#666666';
        })();
    </script>
    <script src="<?php echo $base_path; ?>../resources/notifications/notifications.js" defer></script>
    <script src="<?php echo $base_path; ?>pagination.js" defer></script>
    <script src="<?php echo $base_path; ?>section-tabs.js" defer></script>

    <link rel="stylesheet" href="<?php echo $base_path; ?>common-style.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>../resources/styles/link.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>../resources/styles/button.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>../resources/notifications/notifications.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>../resources/styles/table-auto-size.css">
</head>

<body>
    <div class="admin-wrapper">
        <!-- Admin Header -->
        <header class="admin-header">
            <!-- BURGER MENU -->
            <input class="menu-btn" type="checkbox" id="menu-btn" onclick="headerToggleMenu()">
            <label class="menu-icon" id="menu-icon" for="menu-btn"><span class="nav-icon"></span></label>

            <div class="header-container">
                <!-- Left: Home button and Logo -->
                <div class="header-left">
                    <a href="<?php echo $base_path; ?>../" class="btn-small btn-home" title="Go to Main Site" aria-label="Go to Main Site">
                        <?= svg_icon('home', 16) ?>
                    </a>

                    <a href="<?php echo $base_path; ?>index.php" class="logo-section">
                        <img src="<?php echo $base_path; ?>../resources/images/argo-logo/argo-icon.ico" alt="Argo Logo" class="header-logo">
                        <span class="header-title">Admin Dashboard</span>
                    </a>
                </div>

                <?php
                // Grouped desktop navigation. Each group renders as a hover dropdown;
                // entries with a single null-keyed item render as a standalone link.
                // Keys are the directory names matched against $current_dir for highlighting.
                $nav_groups = [
                    'Sales' => [
                        'license'  => 'Licenses',
                        'payments' => 'Payment Portal',
                    ],
                    'Growth' => [
                        'marketing-funnel' => 'Funnel',
                        'referral-links'   => 'Referrals',
                        'affiliates'       => 'Affiliates',
                        'outreach'         => 'Outreach',
                        'email-customers'  => 'Email customers',
                    ],
                    'Analytics' => [
                        'app-stats'     => 'App Stats',
                        'website-stats' => 'Website Stats',
                    ],
                    'Community' => [
                        'users'   => 'Users',
                        'reports' => 'Reports',
                    ],
                    'System' => [
                        'crons'        => 'Crons',
                        'import-files' => 'Import files',
                        'settings' => 'Settings',
                    ],
                ];
                ?>
                <!-- Center: Desktop Navigation -->
                <nav class="header-nav desktop-nav">
                    <?php foreach ($nav_groups as $label => $items): ?>
                        <?php if (count($items) === 1): ?>
                            <?php $dir = array_key_first($items); ?>
                            <a href="<?php echo $base_path . $dir; ?>/" class="header-link <?php echo $current_dir === $dir ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($items[$dir]); ?>
                            </a>
                        <?php else: ?>
                            <?php $group_active = array_key_exists($current_dir, $items); ?>
                            <div class="nav-group">
                                <button type="button" class="header-link nav-group-toggle <?php echo $group_active ? 'active' : ''; ?>" aria-haspopup="true" aria-expanded="false">
                                    <?php echo htmlspecialchars($label); ?>
                                    <span class="nav-caret" aria-hidden="true">&#9662;</span>
                                </button>
                                <div class="nav-dropdown" role="menu">
                                    <?php foreach ($items as $dir => $text): ?>
                                        <a href="<?php echo $base_path . $dir; ?>/" role="menuitem" class="nav-dropdown-link <?php echo $current_dir === $dir ? 'active' : ''; ?>">
                                            <?php echo htmlspecialchars($text); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>

                <!-- Right: Desktop Actions (Logout) -->
                <div class="header-right desktop-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light theme">
                        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                        <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    </button>
                    <a href="<?php echo $base_path; ?>logout.php" class="btn btn-small btn-red header-logout-btn">Logout</a>
                </div>

                <!-- Mobile User Name (right side) -->
                <div class="mobile-user">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light theme">
                        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                        <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    </button>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                </div>
            </div>

            <!-- Mobile Dropdown Menu -->
            <div id="menu" class="hamburger-nav-menu">
                <ul>
                    <li>
                        <a href="<?php echo $base_path; ?>../" class="home-link">
                            <?= svg_icon('home', 16) ?>
                            Home
                        </a>
                    </li>
                    <li><a href="<?php echo $base_path; ?>index.php" class="<?php echo $current_dir === 'admin' ? 'active' : ''; ?>">Dashboard</a></li>
                    <?php // Built from the same $nav_groups as the desktop nav, so the two
                          // never drift. On mobile every group is expanded under its label
                          // instead of being a hover dropdown.
                    foreach ($nav_groups as $label => $items): ?>
                        <?php if (count($items) === 1): ?>
                            <?php $dir = array_key_first($items); ?>
                            <li><a href="<?php echo $base_path . $dir; ?>/" class="<?php echo $current_dir === $dir ? 'active' : ''; ?>"><?php echo htmlspecialchars($items[$dir]); ?></a></li>
                        <?php else: ?>
                            <li class="mobile-nav-group-label"><?php echo htmlspecialchars($label); ?></li>
                            <?php foreach ($items as $dir => $text): ?>
                                <li><a href="<?php echo $base_path . $dir; ?>/" class="<?php echo $current_dir === $dir ? 'active' : ''; ?>"><?php echo htmlspecialchars($text); ?></a></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li><a href="<?php echo $base_path; ?>logout.php" class="logout-link">Logout</a></li>
                </ul>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="admin-content">
            <?php if (isset($page_title) || isset($page_description)): ?>
                <div class="page-header">
                    <?php if (isset($page_title)): ?>
                        <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
                    <?php endif; ?>
                    <?php if (isset($page_description)): ?>
                        <p class="page-description"><?php echo htmlspecialchars($page_description); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'info'; ?>">
                    <button type="button" class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
                    <?php
                    echo htmlspecialchars($_SESSION['message']);
                    unset($_SESSION['message']);
                    if (isset($_SESSION['message_type'])) {
                        unset($_SESSION['message_type']);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <script>
                function toggleTheme() {
                    var current = document.documentElement.getAttribute('data-theme') || 'light';
                    var next = current === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('admin-theme', next);

                    // Update Chart.js default text color and re-render all charts
                    if (typeof Chart !== 'undefined') {
                        Chart.defaults.color = next === 'dark' ? '#ffffff' : '#666666';
                        Object.values(Chart.instances).forEach(function(chart) {
                            chart.options.scales && Object.values(chart.options.scales).forEach(function(scale) {
                                if (scale.ticks) scale.ticks.color = Chart.defaults.color;
                                if (scale.title) scale.title.color = Chart.defaults.color;
                            });
                            if (chart.options.plugins && chart.options.plugins.legend && chart.options.plugins.legend.labels) {
                                chart.options.plugins.legend.labels.color = Chart.defaults.color;
                            }
                            chart.update();
                        });
                    }
                }

                const menu = document.getElementById('menu');
                const header = document.querySelector('.admin-header');
                const menuBtn = document.getElementById('menu-btn');
                const menuIcon = document.getElementById('menu-icon');

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

                // Close menu when clicking outside
                document.addEventListener('click', (e) => {
                    // Don't close if clicking menu icon
                    if (menuIcon.contains(e.target)) {
                        return;
                    }

                    // Close only if menu is active and click is outside header
                    if (menu.classList.contains('active') && !header.contains(e.target)) {
                        menuBtn.checked = false;
                        headerToggleMenu();
                    }
                });

                // Close menu when clicking on a link
                const mobileNavLinks = menu.querySelectorAll('a');
                mobileNavLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        menuBtn.checked = false;
                        headerToggleMenu();
                    });
                });

                // Close menu on escape key
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && menu.classList.contains('active')) {
                        menuBtn.checked = false;
                        headerToggleMenu();
                    }
                });
            </script>

<script>
    // Timestamps are printed as UTC so the markup never depends on the server's timezone.
    // This rewrites them to the browser's zone. With JS off the UTC text stays, which is
    // not misleading because it still says UTC.
    (function () {
        const pad = n => String(n).padStart(2, '0');

        function zoneName(d) {
            try {
                const part = new Intl.DateTimeFormat(undefined, { timeZoneName: 'short' })
                    .formatToParts(d).find(p => p.type === 'timeZoneName');
                return part ? part.value : '';
            } catch (e) {
                return '';
            }
        }

        function localizeTimes(root) {
            (root || document).querySelectorAll('time[data-epoch]:not([data-tz-done])').forEach(el => {
                const secs = Number(el.getAttribute('data-epoch'));
                if (!secs) return;
                const d = new Date(secs * 1000);
                if (isNaN(d.getTime())) return;

                // 12-hour with am/pm. The hour is not padded, so it reads 9:05 am rather than 09:05 am.
                const h24 = d.getHours();
                let out = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
                    + ' ' + (h24 % 12 || 12) + ':' + pad(d.getMinutes());
                if (el.dataset.epochSeconds === '1') out += ':' + pad(d.getSeconds());
                out += h24 < 12 ? ' am' : ' pm';

                const zone = zoneName(d);
                el.textContent = zone ? out + ' ' + zone : out;
                el.setAttribute('data-tz-done', '1');
            });
        }

        document.addEventListener('DOMContentLoaded', () => localizeTimes());
        // Exposed so anything rendering rows after load can relabel them.
        window.adminLocalizeTimes = localizeTimes;
    })();
</script>
