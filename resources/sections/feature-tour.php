<?php
// Shared feature-tour section. Consumed by the comparison pages and the trade
// landing pages; the landing page still renders its own inline copy for now.
//
// Optional, set by the including page before the include:
//   $ft_id    the section's id, for a page that already has a #features
//   $ft_skip  one demo to leave out, for a page whose hero already plays it.
//             The demo markup and its script find elements by id, so the same
//             demo cannot appear twice on one page.
require_once __DIR__ . '/../icons.php';
require_once __DIR__ . '/../../partials/feature-demo.php';
require_once __DIR__ . '/../includes/site-base-path.php';
$ft_base = site_base_path();

$ft_tabs = [
    'ai-receipts' => ['receipt-scan-detail', 'Receipt Scanning', 'Snap a photo and your books update instantly'],
    'invoices' => ['document', 'Invoicing', 'Create, send, and track invoices to get paid'],
    'expenses' => ['dollar', 'Expense & Revenue Tracking', 'Every dollar in and out, auto-categorized'],
    'customers' => ['users', 'Customer Management', 'Contacts, purchase history, and balances'],
    'predictive' => ['analytics', 'Predictive Analytics', "See next month's cash flow in advance"],
    'inventory' => ['package', 'Inventory Management', 'Stock counts that stay accurate as you sell'],
    'rental' => ['calendar', 'Rental Management', 'Bookings, availability, and returns tracked'],
];
$ft_keys = array_values(array_diff(argo_feature_demo_keys(), [$ft_skip ?? '']));
?>
    <section id="<?= $ft_id ?? 'features' ?>" class="features-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <h2 class="section-title">The tools your business actually runs on</h2>
                <p class="section-description">Smart receipt scanning, invoicing, analytics, and inventory tracking, all in one easy app.</p>
            </div>

            <div class="features-tabs">
                <div class="features-tabs-nav animate-on-scroll">
<?php foreach ($ft_keys as $ftIndex => $ftKey): [$ftIcon, $ftTitle, $ftSubtitle] = $ft_tabs[$ftKey]; ?>
                    <button class="tab-btn<?= $ftIndex === 0 ? ' active' : '' ?>" data-tab="<?= $ftKey ?>">
                        <div class="tab-icon">
                            <?= svg_icon($ftIcon, 24) ?>
                        </div>
                        <div class="tab-text">
                            <span class="tab-title"><?= htmlspecialchars($ftTitle) ?></span>
                            <span class="tab-subtitle"><?= htmlspecialchars($ftSubtitle) ?></span>
                        </div>
                    </button>
<?php endforeach; ?>
                </div>

                <div class="features-tabs-content">
                    <?php
                    // Panel markup lives in partials/feature-demo.php so the landing page,
                    // these comparison pages, and the feature-page heroes all render the
                    // same demos from one source.
                    foreach ($ft_keys as $ftIndex => $ftKey): ?>
                        <div class="tab-content<?= $ftIndex === 0 ? ' active' : '' ?>" id="tab-<?= $ftKey ?>">
                            <?= argo_feature_demo($ftKey) ?>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>
    </section>
