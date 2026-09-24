<?php
// partials/feature-demo.php
//
// The seven animated product mockups, one function, one copy. They were
// written inline in the landing page, copied again into the shared
// feature-tour section for the comparison pages, and would have been copied
// a third time when the feature pages started using them in their heroes.
//
// Markup only. The styling lives in resources/styles/feature-tour.css and the
// animations in resources/scripts/feature-tour.js, both of which any page
// rendering a demo has to load.
//
// Usage:
//   require_once __DIR__ . '/../partials/feature-demo.php';
//   echo argo_feature_demo('ai-receipts');
//
// Keys: ai-receipts, invoices, expenses, customers, predictive, inventory, rental

require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../resources/includes/site-base-path.php';

/**
 * Site base path for asset URLs inside the panels. The markup used to sit in
 * feature-tour.php and read a \ local; inside these functions that
 * variable is out of scope, so it resolved to an empty string and broke the
 * logo on every subfolder install.
 */
function argo_feature_demo_base(): string
{
    return site_base_path();
}

/** True when the key names a demo this file can render. */
function argo_feature_demo_exists(string $key): bool
{
    return in_array($key, argo_feature_demo_keys(), true)
        || in_array($key, argo_feature_demo_hero_keys(), true);
}

/**
 * Demos built for a feature-page hero only. Deliberately separate from
 * argo_feature_demo_keys(), which drives the landing page's tab strip: adding
 * them there would put four new tabs on the home and comparison pages.
 */
function argo_feature_demo_hero_keys(): array
{
    return ['bank-import', 'sheet-import', 'report', 'stripe', 'payroll', 'quotes'];
}

/** Every demo key, in the order the landing page tabs use. */
function argo_feature_demo_keys(): array
{
    return ['ai-receipts', 'invoices', 'expenses', 'customers', 'predictive', 'inventory', 'rental'];
}

/**
 * The inner markup of one demo panel. Returns '' for an unknown key so a
 * caller can fall back to a static image without a fatal.
 */
function argo_feature_demo(string $key): string
{
    if (!argo_feature_demo_exists($key)) {
        return '';
    }
    ob_start();
    switch ($key) {
        case 'ai-receipts': argo_feature_demo_ai_receipts(); break;
        case 'invoices': argo_feature_demo_invoices(); break;
        case 'quotes': argo_feature_demo_quotes(); break;
        case 'expenses': argo_feature_demo_expenses(); break;
        case 'customers': argo_feature_demo_customers(); break;
        case 'predictive': argo_feature_demo_predictive(); break;
        case 'inventory': argo_feature_demo_inventory(); break;
        case 'rental': argo_feature_demo_rental(); break;
        case 'bank-import': argo_feature_demo_bank_import(); break;
        case 'sheet-import': argo_feature_demo_sheet_import(); break;
        case 'report': argo_feature_demo_report(); break;
        case 'stripe': argo_feature_demo_stripe(); break;
        case 'payroll': argo_feature_demo_payroll(); break;
    }
    return (string) ob_get_clean();
}

function argo_feature_demo_ai_receipts(): void
{
    ?>
<div class="tab-content-inner tab-content-inner--solo">
    <div class="tab-content-visual">
        <div class="feature-visual-card invoice-studio-card">
            <div class="invoice-studio">
                <div class="invoice-window">
                    <div class="app-topbar">
                        <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                    </div>
                    <div class="app-body">
                        <div class="app-nav" aria-hidden="true">
                            <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                            <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('receipt', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                        </div>
                        <div class="app-content">
                            <div class="app-page-title">Scan Receipt</div>
                            <div class="scan-stage" id="receiptScan">
                                <div class="scan-receipt">
                                    <div class="rcpt-paper">
                                        <div class="rcpt-store scan-row" data-field="merchant">OFFICE DEPOT</div>
                                        <div class="rcpt-meta scan-row" data-field="date">Store #1284 &middot; Mar 14, 2026</div>
                                        <div class="rcpt-divider"></div>
                                        <div class="rcpt-line scan-row" data-field="item-0"><span>COPY PAPER 5RM</span><span>42.99</span></div>
                                        <div class="rcpt-line scan-row" data-field="item-1"><span>INK CART BK</span><span>34.99</span></div>
                                        <div class="rcpt-line scan-row" data-field="item-2"><span>DESK ORGANIZER</span><span>24.49</span></div>
                                        <div class="rcpt-line scan-row" data-field="item-3"><span>STICKY NOTES 12</span><span>8.99</span></div>
                                        <div class="rcpt-divider"></div>
                                        <div class="rcpt-line rcpt-tax scan-row" data-field="tax"><span>TAX</span><span>8.89</span></div>
                                        <div class="rcpt-line rcpt-total scan-row" data-field="total"><span>TOTAL</span><span>120.35</span></div>
                                        <div class="rcpt-barcode"><svg viewBox="0 0 158 34" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0h2v34h-2z M4 0h1v34h-1z M7 0h3v34h-3z M12 0h1v34h-1z M15 0h2v34h-2z M19 0h1v34h-1z M22 0h3v34h-3z M27 0h1v34h-1z M30 0h2v34h-2z M34 0h1v34h-1z M37 0h3v34h-3z M42 0h2v34h-2z M46 0h1v34h-1z M49 0h2v34h-2z M53 0h3v34h-3z M58 0h1v34h-1z M61 0h1v34h-1z M64 0h3v34h-3z M69 0h2v34h-2z M73 0h1v34h-1z M76 0h2v34h-2z M80 0h1v34h-1z M83 0h3v34h-3z M88 0h1v34h-1z M91 0h2v34h-2z M95 0h3v34h-3z M100 0h1v34h-1z M103 0h2v34h-2z M107 0h1v34h-1z M110 0h3v34h-3z M115 0h2v34h-2z M119 0h1v34h-1z M122 0h2v34h-2z M126 0h3v34h-3z M131 0h1v34h-1z M134 0h1v34h-1z M137 0h2v34h-2z M141 0h3v34h-3z M146 0h1v34h-1z M149 0h2v34h-2z M153 0h1v34h-1z M156 0h2v34h-2z"/></svg></div>
                                    </div>
                                    <div class="scan-beam" id="scanBeam"></div>
                                </div>
                                <div class="scan-form">
                                    <div class="ef-head">
                                        <span class="ef-title">New Expense</span>
                                        <span class="ef-status" id="efStatus"><?= svg_icon('check', 13) ?> Added to Expenses</span>
                                    </div>
                                    <div class="ef-field" data-field="merchant">
                                        <span class="ef-label">Merchant</span>
                                        <span class="ef-value">Office Depot</span>
                                        <span class="ef-check"><?= svg_icon('check', 14) ?></span>
                                    </div>
                                    <div class="ef-field" data-field="date">
                                        <span class="ef-label">Date</span>
                                        <span class="ef-value">Mar 14, 2026</span>
                                        <span class="ef-check"><?= svg_icon('check', 14) ?></span>
                                    </div>
                                    <div class="ef-field" data-field="category">
                                        <span class="ef-label">Category</span>
                                        <span class="ef-value"><span class="ef-pill">Office Supplies</span></span>
                                        <span class="ef-check"><?= svg_icon('check', 14) ?></span>
                                    </div>
                                    <div class="ef-lines">
                                        <div class="ef-line" data-field="item-0"><span>Copy Paper (5 ream)</span><span>$42.99</span></div>
                                        <div class="ef-line" data-field="item-1"><span>Ink Cartridge BK</span><span>$34.99</span></div>
                                        <div class="ef-line" data-field="item-2"><span>Desk Organizer</span><span>$24.49</span></div>
                                        <div class="ef-line" data-field="item-3"><span>Sticky Notes (12pk)</span><span>$8.99</span></div>
                                    </div>
                                    <div class="ef-totals">
                                        <div class="ef-trow" data-field="tax"><span>Tax</span><span>$8.89</span></div>
                                        <div class="ef-trow ef-grand" data-field="total"><span>Total</span><span class="ef-total-val">$120.35</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

/**
 * Quotes hero. Borrows the invoice document markup, since a quote is the same paper in
 * the app. Static on purpose: the colour wheel and template switcher are bound by id to
 * the invoice studio, and a second set of those ids would leave two elements fighting
 * over one set of handlers.
 */
function argo_feature_demo_quotes(): void
{
    ?>
<div class="tab-content-inner tab-content-inner--solo">
    <div class="tab-content-visual">
        <div class="feature-visual-card invoice-studio-card">
            <div class="invoice-studio" id="quoteStudio" style="--inv-accent: 227 79% 58%;">
                <div class="invoice-window">
                    <div class="app-topbar">
                        <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                    </div>
                    <div class="app-body">
                        <div class="app-nav" aria-hidden="true">
                            <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                            <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('document', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                        </div>
                        <div class="app-content">
                            <div class="app-page-title">New Quote</div>
                            <div class="invoice-doc theme-contemporary" id="quoteDoc">
                        <div class="inv-brandbar"></div>
                        <div class="inv-top inv-anim">
                            <div class="inv-brand-group">
                                <div class="inv-brand">
                                    <span class="inv-mark"><svg viewBox="650 48 2400 2400" fill="currentColor" aria-hidden="true"><path fill-rule="nonzero" d="M 1825.109375 1914.148438 L 1295.769531 2027.972656 C 1292.101562 2028.761719 1288.289062 2027.59375 1285.679688 2024.890625 C 1283.089844 2022.1875 1282.070312 2018.335938 1283 2014.699219 L 1357.828125 1722.378906 L 1361.988281 1733.847656 L 715.601562 1267.910156 C 712.378906 1265.589844 710.6875 1261.691406 711.191406 1257.75 C 711.695312 1253.820312 714.3125 1250.46875 718.011719 1249.03125 L 917.507812 1171.558594 L 911.125 1184.988281 L 789.367188 808.660156 C 788.113281 804.78125 789.140625 800.53125 792.023438 797.660156 C 794.90625 794.78125 799.160156 793.769531 803.035156 795.03125 L 1179.679688 918.160156 L 1166.179688 924.648438 L 1242.941406 720.011719 C 1244.339844 716.28125 1247.671875 713.621094 1251.628906 713.089844 C 1255.570312 712.550781 1259.5 714.230469 1261.839844 717.460938 L 1570.75 1143.378906 L 1551.320312 1151.621094 L 1415.289062 379.421875 C 1414.609375 375.519531 1416.101562 371.558594 1419.191406 369.089844 C 1422.28125 366.621094 1426.46875 366.03125 1430.121094 367.550781 L 1651.171875 459.621094 L 1637.519531 464.410156 L 1838.308594 98.511719 C 1840.210938 95.050781 1843.839844 92.898438 1847.789062 92.890625 C 1851.738281 92.890625 1855.378906 95.03125 1857.28125 98.480469 L 2059.390625 464.289062 L 2045.660156 459.570312 L 2266.378906 365.238281 C 2270.019531 363.679688 2274.230469 364.238281 2277.351562 366.699219 C 2280.460938 369.160156 2281.980469 373.121094 2281.308594 377.03125 L 2148.550781 1148.769531 L 2129.089844 1140.609375 L 2436.378906 714 C 2438.710938 710.769531 2442.628906 709.070312 2446.570312 709.589844 C 2450.519531 710.109375 2453.871094 712.75 2455.289062 716.480469 L 2532.820312 920.359375 L 2519.289062 913.929688 L 2895.609375 789.078125 C 2899.480469 787.800781 2903.738281 788.789062 2906.628906 791.660156 C 2909.53125 794.519531 2910.570312 798.769531 2909.339844 802.648438 L 2788.570312 1180.890625 L 2782.050781 1167.460938 L 2983.929688 1242.929688 C 2987.671875 1244.320312 2990.339844 1247.660156 2990.871094 1251.621094 C 2991.410156 1255.570312 2989.71875 1259.5 2986.488281 1261.839844 L 2341.378906 1728.984375 L 2345.511719 1717.496094 L 2422.128906 2012.820312 C 2423.070312 2016.453125 2422.058594 2020.3125 2419.46875 2023.023438 C 2416.878906 2025.734375 2413.070312 2026.910156 2409.398438 2026.132812 L 1879.988281 1914.027344 L 1893.050781 1902.972656 L 1914.019531 2394.578125 C 1914.140625 2397.527344 1913.058594 2400.398438 1911.019531 2402.53125 C 1908.980469 2404.664062 1906.148438 2405.867188 1903.199219 2405.867188 L 1804.46875 2405.867188 C 1801.519531 2405.867188 1798.710938 2404.671875 1796.671875 2402.554688 C 1794.628906 2400.433594 1793.539062 2397.578125 1793.640625 2394.636719 L 1812.011719 1903.15625 L 1825.109375 1914.148438 Z"/></svg></span>
                                    <span class="inv-bizname">Maple &amp; Co.</span>
                                    <div class="inv-status" id="quoteStatus">Accepted</div>
                                </div>
                                <div class="inv-docref">QUOTE &middot; #QT-0042</div>
                            </div>
                        </div>
                        <div class="inv-billto inv-anim">
                            <span class="inv-label">Quote for</span>
                            <span class="inv-client">Sarah Miller</span>
                            <span class="inv-client-sub">Valid until 31 October</span>
                        </div>
                        <div class="inv-table">
                            <div class="inv-row inv-row-head inv-anim">
                                <span>Description</span><span>Qty</span><span>Amount</span>
                            </div>
                            <div class="inv-row inv-item">
                                <span>Logo &amp; brand design</span><span>1</span><span>$600.00</span>
                            </div>
                            <div class="inv-row inv-item">
                                <span>Website build</span><span>1</span><span>$480.00</span>
                            </div>
                            <div class="inv-row inv-item">
                                <span>Hosting (annual)</span><span>1</span><span>$154.00</span>
                            </div>
                        </div>
                        <div class="inv-totals inv-anim">
                            <div class="inv-subtotal"><span>Subtotal</span><span>$1,234.00</span></div>
                            <div class="inv-amountdue"><span>Total</span><span class="inv-total-value">$1,234.00</span></div>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function argo_feature_demo_invoices(): void
{
    ?>
<div class="tab-content-inner tab-content-inner--solo">
    <div class="tab-content-visual">
        <div class="feature-visual-card invoice-studio-card">
            <div class="invoice-studio" id="invoiceStudio" style="--inv-accent: 227 79% 58%;">
                <div class="invoice-window">
                    <div class="app-topbar">
                        <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                    </div>
                    <div class="app-body">
                        <div class="app-nav" aria-hidden="true">
                            <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                            <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('document', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                        </div>
                        <div class="app-content">
                            <div class="app-page-title">New Invoice</div>
                            <div class="invoice-doc theme-contemporary" id="invoiceDoc">
                        <div class="inv-brandbar"></div>
                        <div class="inv-top inv-anim">
                            <div class="inv-brand-group">
                                <div class="inv-brand">
                                    <span class="inv-mark"><svg viewBox="650 48 2400 2400" fill="currentColor" aria-hidden="true"><path fill-rule="nonzero" d="M 1825.109375 1914.148438 L 1295.769531 2027.972656 C 1292.101562 2028.761719 1288.289062 2027.59375 1285.679688 2024.890625 C 1283.089844 2022.1875 1282.070312 2018.335938 1283 2014.699219 L 1357.828125 1722.378906 L 1361.988281 1733.847656 L 715.601562 1267.910156 C 712.378906 1265.589844 710.6875 1261.691406 711.191406 1257.75 C 711.695312 1253.820312 714.3125 1250.46875 718.011719 1249.03125 L 917.507812 1171.558594 L 911.125 1184.988281 L 789.367188 808.660156 C 788.113281 804.78125 789.140625 800.53125 792.023438 797.660156 C 794.90625 794.78125 799.160156 793.769531 803.035156 795.03125 L 1179.679688 918.160156 L 1166.179688 924.648438 L 1242.941406 720.011719 C 1244.339844 716.28125 1247.671875 713.621094 1251.628906 713.089844 C 1255.570312 712.550781 1259.5 714.230469 1261.839844 717.460938 L 1570.75 1143.378906 L 1551.320312 1151.621094 L 1415.289062 379.421875 C 1414.609375 375.519531 1416.101562 371.558594 1419.191406 369.089844 C 1422.28125 366.621094 1426.46875 366.03125 1430.121094 367.550781 L 1651.171875 459.621094 L 1637.519531 464.410156 L 1838.308594 98.511719 C 1840.210938 95.050781 1843.839844 92.898438 1847.789062 92.890625 C 1851.738281 92.890625 1855.378906 95.03125 1857.28125 98.480469 L 2059.390625 464.289062 L 2045.660156 459.570312 L 2266.378906 365.238281 C 2270.019531 363.679688 2274.230469 364.238281 2277.351562 366.699219 C 2280.460938 369.160156 2281.980469 373.121094 2281.308594 377.03125 L 2148.550781 1148.769531 L 2129.089844 1140.609375 L 2436.378906 714 C 2438.710938 710.769531 2442.628906 709.070312 2446.570312 709.589844 C 2450.519531 710.109375 2453.871094 712.75 2455.289062 716.480469 L 2532.820312 920.359375 L 2519.289062 913.929688 L 2895.609375 789.078125 C 2899.480469 787.800781 2903.738281 788.789062 2906.628906 791.660156 C 2909.53125 794.519531 2910.570312 798.769531 2909.339844 802.648438 L 2788.570312 1180.890625 L 2782.050781 1167.460938 L 2983.929688 1242.929688 C 2987.671875 1244.320312 2990.339844 1247.660156 2990.871094 1251.621094 C 2991.410156 1255.570312 2989.71875 1259.5 2986.488281 1261.839844 L 2341.378906 1728.984375 L 2345.511719 1717.496094 L 2422.128906 2012.820312 C 2423.070312 2016.453125 2422.058594 2020.3125 2419.46875 2023.023438 C 2416.878906 2025.734375 2413.070312 2026.910156 2409.398438 2026.132812 L 1879.988281 1914.027344 L 1893.050781 1902.972656 L 1914.019531 2394.578125 C 1914.140625 2397.527344 1913.058594 2400.398438 1911.019531 2402.53125 C 1908.980469 2404.664062 1906.148438 2405.867188 1903.199219 2405.867188 L 1804.46875 2405.867188 C 1801.519531 2405.867188 1798.710938 2404.671875 1796.671875 2402.554688 C 1794.628906 2400.433594 1793.539062 2397.578125 1793.640625 2394.636719 L 1812.011719 1903.15625 L 1825.109375 1914.148438 Z"/></svg></span>
                                    <span class="inv-bizname">Maple &amp; Co.</span>
                                    <div class="inv-status" id="invStatus">Paid</div>
                                </div>
                                <div class="inv-docref">INVOICE &middot; #INV-0042</div>
                            </div>
                        </div>
                        <div class="inv-billto inv-anim">
                            <span class="inv-label">Bill to</span>
                            <span class="inv-client">Sarah Miller</span>
                            <span class="inv-client-sub">123 Hollywood Blvd, Los Angeles</span>
                        </div>
                        <div class="inv-table">
                            <div class="inv-row inv-row-head inv-anim">
                                <span>Description</span><span>Qty</span><span>Amount</span>
                            </div>
                            <div class="inv-row inv-item">
                                <span>Logo &amp; brand design</span><span>1</span><span>$600.00</span>
                            </div>
                            <div class="inv-row inv-item">
                                <span>Website build</span><span>1</span><span>$480.00</span>
                            </div>
                            <div class="inv-row inv-item">
                                <span>Hosting (annual)</span><span>1</span><span>$154.00</span>
                            </div>
                        </div>
                        <div class="inv-totals inv-anim">
                            <div class="inv-subtotal"><span>Subtotal</span><span>$1,234.00</span></div>
                            <div class="inv-amountdue"><span>Amount Due</span><span class="inv-total-value">$1,234.00</span></div>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="studio-panel color-panel panel-hidden" id="colorPanel">
                    <div class="color-wheel" id="colorWheel">
                        <div class="color-thumb" id="colorThumb"></div>
                    </div>
                    <div class="light-slider" id="lightSlider">
                        <div class="light-thumb" id="lightThumb"></div>
                    </div>
                </div>

                <div class="studio-panel template-panel panel-hidden" id="templatePanel">
                    <button class="tmpl-btn" data-theme="theme-modern" type="button">
                        <span class="tmpl-thumb tmpl-modern"></span>Modern
                    </button>
                    <button class="tmpl-btn active" data-theme="theme-contemporary" type="button">
                        <span class="tmpl-thumb tmpl-contemporary"></span>Contemporary
                    </button>
                    <button class="tmpl-btn" data-theme="theme-classic" type="button">
                        <span class="tmpl-thumb tmpl-classic"></span>Classic
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function argo_feature_demo_expenses(): void
{
    ?>
<div class="tab-content-inner tab-content-inner--solo">
    <div class="tab-content-visual">
        <div class="feature-visual-card invoice-studio-card">
            <div class="invoice-studio">
                <div class="invoice-window">
                    <div class="app-topbar">
                        <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                    </div>
                    <div class="app-body">
                        <div class="app-nav" aria-hidden="true">
                            <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                            <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('dollar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                        </div>
                        <div class="app-content">
                            <div class="app-page-title">Overview</div>
                            <div class="exp-stage" id="expenseStage">
                                <div class="exp-inner">
                                    <div class="txn-table">
                                        <div class="txn-head"><span>Date</span><span>Description</span><span>Category</span><span class="txn-amt">Amount</span></div>
                                        <div class="txn-row"><span class="txn-date">Mar 14</span><span class="txn-desc">Client payment</span><span class="txn-cat"><span>Consulting</span></span><span class="txn-amt rev">+$1,200.00</span></div>
                                        <div class="txn-row"><span class="txn-date">Mar 13</span><span class="txn-desc">Office supplies</span><span class="txn-cat"><span>Office</span></span><span class="txn-amt exp">&minus;$120.35</span></div>
                                        <div class="txn-row"><span class="txn-date">Mar 12</span><span class="txn-desc">Website project</span><span class="txn-cat"><span>Consulting</span></span><span class="txn-amt rev">+$2,400.00</span></div>
                                        <div class="txn-row"><span class="txn-date">Mar 11</span><span class="txn-desc">Software license</span><span class="txn-cat"><span>Software</span></span><span class="txn-amt exp">&minus;$49.00</span></div>
                                        <div class="txn-row"><span class="txn-date">Mar 10</span><span class="txn-desc">Product sale</span><span class="txn-cat"><span>Sales</span></span><span class="txn-amt rev">+$340.00</span></div>
                                        <div class="txn-row"><span class="txn-date">Mar 9</span><span class="txn-desc">Utilities</span><span class="txn-cat"><span>Office</span></span><span class="txn-amt exp">&minus;$210.00</span></div>
                                        <div class="txn-row"><span class="txn-date">Mar 8</span><span class="txn-desc">Consulting fee</span><span class="txn-cat"><span>Consulting</span></span><span class="txn-amt rev">+$1,500.00</span></div>
                                    </div>

                                    <div class="exp-chart exp-chart-line" id="expChartLine">
                                        <div class="exp-chart-head">
                                            <span>Revenue vs Expenses</span>
                                            <span class="exp-legend"><i class="lg-dot lg-rev"></i>Rev<i class="lg-dot lg-exp"></i>Exp</span>
                                        </div>
                                        <svg class="exp-line-svg" viewBox="0 0 240 108" preserveAspectRatio="none" aria-hidden="true">
                                            <defs>
                                                <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0" stop-color="#10b981" stop-opacity="0.32"/>
                                                    <stop offset="1" stop-color="#10b981" stop-opacity="0"/>
                                                </linearGradient>
                                                <linearGradient id="expGrad" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0" stop-color="#ef4444" stop-opacity="0.20"/>
                                                    <stop offset="1" stop-color="#ef4444" stop-opacity="0"/>
                                                </linearGradient>
                                            </defs>
                                            <g class="exp-grid">
                                                <line x1="8" y1="24" x2="232" y2="24"/>
                                                <line x1="8" y1="48" x2="232" y2="48"/>
                                                <line x1="8" y1="72" x2="232" y2="72"/>
                                                <line x1="8" y1="96" x2="232" y2="96"/>
                                            </g>
                                            <path d="M12 94 C27 94 41 90 56 90 C71 90 85 92 100 92 C115 92 129 84 144 84 C159 84 173 86 188 86 C203 86 213 78 228 78 L228 104 L12 104 Z" fill="url(#expGrad)"/>
                                            <path d="M12 80 C27 80 41 68 56 68 C71 68 85 72 100 72 C115 72 129 52 144 52 C159 52 173 46 188 46 C203 46 213 28 228 28 L228 104 L12 104 Z" fill="url(#revGrad)"/>
                                            <path class="exp-line-e" d="M12 94 C27 94 41 90 56 90 C71 90 85 92 100 92 C115 92 129 84 144 84 C159 84 173 86 188 86 C203 86 213 78 228 78"/>
                                            <path class="exp-line-r" d="M12 80 C27 80 41 68 56 68 C71 68 85 72 100 72 C115 72 129 52 144 52 C159 52 173 46 188 46 C203 46 213 28 228 28"/>
                                            <g class="exp-dots-e">
                                                <circle class="exp-dot-e" cx="12" cy="94" r="2.1"/><circle class="exp-dot-e" cx="56" cy="90" r="2.1"/><circle class="exp-dot-e" cx="100" cy="92" r="2.1"/><circle class="exp-dot-e" cx="144" cy="84" r="2.1"/><circle class="exp-dot-e" cx="188" cy="86" r="2.1"/><circle class="exp-dot-e" cx="228" cy="78" r="2.1"/>
                                            </g>
                                            <g class="exp-dots-r">
                                                <circle class="exp-dot-r" cx="12" cy="80" r="2.3"/><circle class="exp-dot-r" cx="56" cy="68" r="2.3"/><circle class="exp-dot-r" cx="100" cy="72" r="2.3"/><circle class="exp-dot-r" cx="144" cy="52" r="2.3"/><circle class="exp-dot-r" cx="188" cy="46" r="2.3"/>
                                            </g>
                                            <circle class="exp-pulse" cx="228" cy="28" r="3.6" fill="#10b981"/>
                                            <circle cx="228" cy="28" r="3.6" fill="#10b981" stroke="#fff" stroke-width="1.5"/>
                                        </svg>
                                        <div class="exp-axis"><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span></div>
                                    </div>

                                    <div class="exp-chart exp-chart-bars" id="expChartBars">
                                        <div class="exp-chart-head">
                                            <span>Cash flow</span>
                                            <span class="exp-net">+$<span class="exp-net-val">0</span></span>
                                        </div>
                                        <div class="exp-bars">
                                            <div class="exp-bar-group"><span class="exp-bar exp-bar-rev" style="--h:70%"></span><span class="exp-bar exp-bar-exp" style="--h:44%"></span></div>
                                            <div class="exp-bar-group"><span class="exp-bar exp-bar-rev" style="--h:56%"></span><span class="exp-bar exp-bar-exp" style="--h:38%"></span></div>
                                            <div class="exp-bar-group"><span class="exp-bar exp-bar-rev" style="--h:66%"></span><span class="exp-bar exp-bar-exp" style="--h:50%"></span></div>
                                            <div class="exp-bar-group"><span class="exp-bar exp-bar-rev" style="--h:84%"></span><span class="exp-bar exp-bar-exp" style="--h:46%"></span></div>
                                            <div class="exp-bar-group"><span class="exp-bar exp-bar-rev" style="--h:78%"></span><span class="exp-bar exp-bar-exp" style="--h:54%"></span></div>
                                            <div class="exp-bar-group"><span class="exp-bar exp-bar-rev" style="--h:96%"></span><span class="exp-bar exp-bar-exp" style="--h:48%"></span></div>
                                        </div>
                                        <div class="exp-axis exp-axis-bars"><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function argo_feature_demo_customers(): void
{
    ?>
<div class="tab-content-inner tab-content-inner--solo">
    <div class="tab-content-visual">
        <div class="feature-visual-card invoice-studio-card">
            <div class="invoice-studio">
                <div class="invoice-window">
                    <div class="app-topbar">
                        <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                    </div>
                    <div class="app-body">
                        <div class="app-nav" aria-hidden="true">
                            <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                            <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('users', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                        </div>
                        <div class="app-content">
                            <div class="app-page-title">Customers</div>
                            <div class="cust-stage" id="customerStage">
                                <div class="cust-inner">
                                    <div class="cust-table">
                                        <div class="cust-head"><span>Customer</span><span>Company</span><span class="cust-spent">Total spent</span><span class="cust-status-h">Status</span></div>
                                        <div class="cust-row selected"><span class="cust-who"><span class="cust-av av-1">JD</span><span class="cust-name">Jane Doe</span></span><span class="cust-co">Acme Inc</span><span class="cust-spent">$4,230</span><span class="cust-status"><span class="badge-active">Active</span></span></div>
                                        <div class="cust-row"><span class="cust-who"><span class="cust-av av-2">MS</span><span class="cust-name">Mike Smith</span></span><span class="cust-co">Store Co</span><span class="cust-spent">$2,890</span><span class="cust-status"><span class="badge-active">Active</span></span></div>
                                        <div class="cust-row"><span class="cust-who"><span class="cust-av av-3">AL</span><span class="cust-name">Ana Lopez</span></span><span class="cust-co">Lopez Studio</span><span class="cust-spent">$6,100</span><span class="cust-status"><span class="badge-vip">VIP</span></span></div>
                                        <div class="cust-row"><span class="cust-who"><span class="cust-av av-4">RK</span><span class="cust-name">Raj Kumar</span></span><span class="cust-co">Kumar &amp; Sons</span><span class="cust-spent">$1,450</span><span class="cust-status"><span class="badge-active">Active</span></span></div>
                                        <div class="cust-row"><span class="cust-who"><span class="cust-av av-2">EW</span><span class="cust-name">Emma Wong</span></span><span class="cust-co">Wong Design</span><span class="cust-spent">$3,720</span><span class="cust-status"><span class="badge-active">Active</span></span></div>
                                        <div class="cust-row"><span class="cust-who"><span class="cust-av av-3">TB</span><span class="cust-name">Tom Brown</span></span><span class="cust-co">Brown LLC</span><span class="cust-spent">$980</span><span class="cust-status"><span class="badge-new">New</span></span></div>
                                    </div>

                                    <div class="cust-profile" id="custProfile">
                                        <div class="cust-profile-head">
                                            <span class="cust-av-lg av-1">JD</span>
                                            <div class="cust-profile-id">
                                                <span class="cust-profile-name">Jane Doe</span>
                                                <span class="cust-profile-email">jane@acme.com</span>
                                            </div>
                                        </div>
                                        <div class="cust-profile-stats">
                                            <div class="cps"><span class="cps-val">$<span class="cps-ltv">0</span></span><span class="cps-lbl">Lifetime</span></div>
                                            <div class="cps"><span class="cps-val">12</span><span class="cps-lbl">Orders</span></div>
                                            <div class="cps"><span class="cps-val">2024</span><span class="cps-lbl">Since</span></div>
                                        </div>
                                        <span class="cust-spark-lbl">Purchases</span>
                                        <svg class="cust-spark-svg" viewBox="0 0 220 58" preserveAspectRatio="none" aria-hidden="true">
                                            <defs>
                                                <linearGradient id="custSpark" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0" stop-color="#3f63e8" stop-opacity="0.30"/>
                                                    <stop offset="1" stop-color="#3f63e8" stop-opacity="0"/>
                                                </linearGradient>
                                            </defs>
                                            <path d="M6 44 C24 42 38 36 60 38 C84 40 100 26 130 28 C160 30 184 18 214 10 L214 54 L6 54 Z" fill="url(#custSpark)"/>
                                            <path class="cust-spark-line" d="M6 44 C24 42 38 36 60 38 C84 40 100 26 130 28 C160 30 184 18 214 10"/>
                                            <circle class="exp-pulse" cx="214" cy="10" r="3.4" fill="#3f63e8"/>
                                            <circle cx="214" cy="10" r="3.4" fill="#3f63e8" stroke="#fff" stroke-width="1.4"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function argo_feature_demo_predictive(): void
{
    ?>
<div class="tab-content-inner tab-content-inner--solo">
    <div class="tab-content-visual">
        <div class="feature-visual-card invoice-studio-card">
            <div class="invoice-studio">
                <div class="invoice-window">
                    <div class="app-topbar">
                        <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                    </div>
                    <div class="app-body">
                        <div class="app-nav" aria-hidden="true">
                            <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                            <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('trending-up', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                        </div>
                        <div class="app-content">
                            <div class="app-page-title">Forecast</div>
                            <div class="fc-stage" id="forecastStage">
                                <div class="fc-kpis">
                                    <div class="fc-kpi"><span class="fc-kpi-val">$<span class="fc-next">0</span></span><span class="fc-kpi-lbl">Next month</span></div>
                                    <div class="fc-kpi"><span class="fc-kpi-val fc-up">+18%</span><span class="fc-kpi-lbl">Projected growth</span></div>
                                    <div class="fc-kpi"><span class="fc-kpi-val"><span class="fc-conf">0</span>%</span><span class="fc-kpi-lbl">Confidence</span></div>
                                </div>
                                <div class="fc-chart">
                                    <div class="fc-chart-head">
                                        <span>Sales forecast</span>
                                        <span class="fc-legend"><i class="fc-lg-line"></i>History<i class="fc-lg-dash"></i>Forecast</span>
                                    </div>
                                    <svg class="fc-svg" viewBox="0 0 320 150" preserveAspectRatio="none" aria-hidden="true">
                                        <defs>
                                            <linearGradient id="fcArea" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0" stop-color="#3f63e8" stop-opacity="0.28"/>
                                                <stop offset="1" stop-color="#3f63e8" stop-opacity="0"/>
                                            </linearGradient>
                                            <linearGradient id="fcCone" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0" stop-color="#3f63e8" stop-opacity="0.18"/>
                                                <stop offset="1" stop-color="#3f63e8" stop-opacity="0.03"/>
                                            </linearGradient>
                                        </defs>
                                        <g class="fc-grid">
                                            <line x1="10" y1="34" x2="310" y2="34"/>
                                            <line x1="10" y1="70" x2="310" y2="70"/>
                                            <line x1="10" y1="106" x2="310" y2="106"/>
                                        </g>
                                        <g class="fc-history">
                                            <path d="M10 120 C40 122 60 96 90 92 C120 88 140 70 170 64 C185 61 195 56 200 54 L200 138 L10 138 Z" fill="url(#fcArea)"/>
                                            <path class="fc-hline" d="M10 120 C40 122 60 96 90 92 C120 88 140 70 170 64 C185 61 195 56 200 54"/>
                                        </g>
                                        <g class="fc-forecast">
                                            <path d="M200 54 C225 44 245 32 270 26 C290 23 300 14 310 10 L310 40 C300 36 290 40 270 44 C245 49 225 51 200 54 Z" fill="url(#fcCone)"/>
                                            <path class="fc-fline" d="M200 54 C225 48 245 40 270 36 C290 33 300 26 310 22"/>
                                            <line class="fc-now" x1="200" y1="20" x2="200" y2="138"/>
                                            <circle cx="200" cy="54" r="4.2" fill="#3f63e8" stroke="#fff" stroke-width="1.6"/>
                                            <circle class="exp-pulse" cx="200" cy="54" r="4.2" fill="#3f63e8"/>
                                        </g>
                                    </svg>
                                    <div class="fc-axis"><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span class="fc-fut">May</span><span class="fc-fut">Jun</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function argo_feature_demo_inventory(): void
{
    ?>
<div class="tab-content-inner tab-content-inner--solo">
    <div class="tab-content-visual">
        <div class="feature-visual-card invoice-studio-card">
            <div class="invoice-studio">
                <div class="invoice-window">
                    <div class="app-topbar">
                        <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                    </div>
                    <div class="app-body">
                        <div class="app-nav" aria-hidden="true">
                            <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                            <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('package', 18) ?></span>
                        </div>
                        <div class="app-content">
                            <div class="app-page-title">Inventory</div>
                            <div class="inv-stage" id="inventoryStage">
                                <div class="inv-grid">
                                    <div class="inv-card">
                                        <div class="inv-card-top">
                                            <span class="inv-chip chip-1"><?= svg_icon('package', 18) ?></span>
                                            <span class="inv-meta"><span class="inv-name">Widget Pro</span><span class="inv-sku">WGT-01</span></span>
                                            <span class="inv-badge badge-ok">In stock</span>
                                        </div>
                                        <div class="inv-count"><span class="inv-num" data-target="142">0</span><span class="inv-unit">units</span></div>
                                        <div class="inv-bar"><span class="inv-fill fill-ok" style="--fill: 88%"></span></div>
                                    </div>
                                    <div class="inv-card">
                                        <div class="inv-card-top">
                                            <span class="inv-chip chip-2"><?= svg_icon('package', 18) ?></span>
                                            <span class="inv-meta"><span class="inv-name">Cable Set</span><span class="inv-sku">CBL-04</span></span>
                                            <span class="inv-badge badge-ok">In stock</span>
                                        </div>
                                        <div class="inv-count"><span class="inv-num" data-target="96">0</span><span class="inv-unit">units</span></div>
                                        <div class="inv-bar"><span class="inv-fill fill-ok" style="--fill: 64%"></span></div>
                                    </div>
                                    <div class="inv-card">
                                        <div class="inv-card-top">
                                            <span class="inv-chip chip-3"><?= svg_icon('package', 18) ?></span>
                                            <span class="inv-meta"><span class="inv-name">Basic Kit</span><span class="inv-sku">KIT-09</span></span>
                                            <span class="inv-badge badge-low">Low</span>
                                        </div>
                                        <div class="inv-count"><span class="inv-num" data-target="34">0</span><span class="inv-unit">units</span></div>
                                        <div class="inv-bar"><span class="inv-fill fill-low" style="--fill: 34%"></span></div>
                                    </div>
                                    <div class="inv-card">
                                        <div class="inv-card-top">
                                            <span class="inv-chip chip-4"><?= svg_icon('package', 18) ?></span>
                                            <span class="inv-meta"><span class="inv-name">Power Adapter</span><span class="inv-sku">PWR-11</span></span>
                                            <span class="inv-badge badge-low">Low</span>
                                        </div>
                                        <div class="inv-count"><span class="inv-num" data-target="21">0</span><span class="inv-unit">units</span></div>
                                        <div class="inv-bar"><span class="inv-fill fill-low" style="--fill: 24%"></span></div>
                                    </div>
                                    <div class="inv-card">
                                        <div class="inv-card-top">
                                            <span class="inv-chip chip-5"><?= svg_icon('package', 18) ?></span>
                                            <span class="inv-meta"><span class="inv-name">Deluxe Bundle</span><span class="inv-sku">DLX-22</span></span>
                                            <span class="inv-badge badge-crit">Critical</span>
                                        </div>
                                        <div class="inv-count"><span class="inv-num" data-target="8">0</span><span class="inv-unit">units</span></div>
                                        <div class="inv-bar"><span class="inv-fill fill-crit" style="--fill: 10%"></span></div>
                                    </div>
                                    <div class="inv-card">
                                        <div class="inv-card-top">
                                            <span class="inv-chip chip-6"><?= svg_icon('package', 18) ?></span>
                                            <span class="inv-meta"><span class="inv-name">Starter Pack</span><span class="inv-sku">STR-07</span></span>
                                            <span class="inv-badge badge-ok">In stock</span>
                                        </div>
                                        <div class="inv-count"><span class="inv-num" data-target="73">0</span><span class="inv-unit">units</span></div>
                                        <div class="inv-bar"><span class="inv-fill fill-ok" style="--fill: 52%"></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function argo_feature_demo_rental(): void
{
    ?>
<div class="tab-content-inner tab-content-inner--solo">
    <div class="tab-content-visual">
        <div class="feature-visual-card invoice-studio-card">
            <div class="invoice-studio">
                <div class="invoice-window">
                    <div class="app-topbar">
                        <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                    </div>
                    <div class="app-body">
                        <div class="app-nav" aria-hidden="true">
                            <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                            <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('calendar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                            <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                        </div>
                        <div class="app-content">
                            <div class="app-page-title">Rentals</div>
                            <div class="rent-stage" id="rentalStage">
                                <div class="rent-inner">
                                    <div class="rent-cal">
                                        <div class="rent-cal-head">
                                            <span class="rent-month">December 2025</span>
                                            <span class="rent-nav"><i><?= svg_icon('chevron-left-sm', 13) ?></i><i><?= svg_icon('chevron-right-sm', 13) ?></i></span>
                                        </div>
                                        <div class="rent-grid">
                                            <span class="rent-wd">Su</span><span class="rent-wd">Mo</span><span class="rent-wd">Tu</span><span class="rent-wd">We</span><span class="rent-wd">Th</span><span class="rent-wd">Fr</span><span class="rent-wd">Sa</span>
                                            <span class="rent-day rent-empty"></span><span class="rent-day">1</span><span class="rent-day">2</span><span class="rent-day">3</span><span class="rent-day">4</span><span class="rent-day">5</span><span class="rent-day">6</span>
                                            <span class="rent-day">7</span><span class="rent-day">8</span><span class="rent-day rent-seq">9</span><span class="rent-day rent-seq">10</span><span class="rent-day rent-seq">11</span><span class="rent-day rent-seq">12</span><span class="rent-day">13</span>
                                            <span class="rent-day">14</span><span class="rent-day">15</span><span class="rent-day booked">16</span><span class="rent-day booked">17</span><span class="rent-day">18</span><span class="rent-day">19</span><span class="rent-day">20</span>
                                            <span class="rent-day">21</span><span class="rent-day">22</span><span class="rent-day booked">23</span><span class="rent-day booked">24</span><span class="rent-day">25</span><span class="rent-day">26</span><span class="rent-day">27</span>
                                            <span class="rent-day">28</span><span class="rent-day">29</span><span class="rent-day">30</span><span class="rent-day">31</span><span class="rent-day rent-empty"></span><span class="rent-day rent-empty"></span><span class="rent-day rent-empty"></span>
                                        </div>
                                    </div>

                                    <div class="rent-booking" id="rentBooking">
                                        <div class="rent-bk-head">
                                            <span class="rent-bk-icon"><?= svg_icon('package', 16) ?></span>
                                            <span class="rent-bk-id"><span class="rent-bk-item">HD Projector</span><span class="rent-bk-cust">Sarah Miller</span></span>
                                            <span class="rent-bk-badge">Out</span>
                                        </div>
                                        <div class="rent-bk-row"><span class="rent-bk-lbl">Rental</span><span class="rent-bk-val">Dec 9 &ndash; Dec 12</span></div>
                                        <div class="rent-bk-row"><span class="rent-bk-lbl">Returns</span><span class="rent-bk-val rent-bk-ret">in 3 days</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function argo_feature_demo_bank_import(): void
{
    ?>
    <div class="tab-content-inner tab-content-inner--solo">
        <div class="tab-content-visual">
            <div class="feature-visual-card invoice-studio-card">
                <div class="invoice-studio">
                    <div class="invoice-window">
                        <div class="app-topbar">
                            <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                        </div>
                        <div class="app-body">
                            <div class="app-nav" aria-hidden="true">
                                <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                                <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('receipt', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                            </div>
                            <div class="app-content">
                                <div class="app-page-title">Import Bank Statement</div>
                                <div class="bk-stage" id="bankStage">
                                    <div class="bk-file">
                                        <span class="bk-file-ic"><?= svg_icon('bank', 15) ?></span>
                                        <span class="bk-file-name">statement_july.csv</span>
                                        <span class="bk-file-meta">42 transactions</span>
                                    </div>
                                    <div class="bk-rows">
                                        <div class="bk-row" data-i="0"><span class="bk-desc">SQ *BLUE BOTTLE COFFEE</span><span class="bk-date">Jul 2</span><span class="bk-badge bk-new">New</span><span class="bk-amt exp">−$18.40</span></div>
                                        <div class="bk-row" data-i="1"><span class="bk-desc">ACH DEPOSIT — CLIENT LTD</span><span class="bk-date">Jul 3</span><span class="bk-badge bk-matched">Matched</span><span class="bk-amt rev">+$1,250.00</span></div>
                                        <div class="bk-row" data-i="2"><span class="bk-desc">STAPLES #1123</span><span class="bk-date">Jul 5</span><span class="bk-badge bk-matched">Matched</span><span class="bk-amt exp">−$64.20</span></div>
                                        <div class="bk-row" data-i="3"><span class="bk-desc">VISA — ADOBE</span><span class="bk-date">Jul 6</span><span class="bk-badge bk-new">New</span><span class="bk-amt exp">−$29.99</span></div>
                                        <div class="bk-row" data-i="4"><span class="bk-desc">E-TRANSFER — M. REYES</span><span class="bk-date">Jul 8</span><span class="bk-badge bk-new">New</span><span class="bk-amt rev">+$480.00</span></div>
                                    </div>
                                    <div class="bk-foot">
                                        <span class="bk-count"><b id="bkNew">0</b> new, <b id="bkMatched">0</b> already recorded</span>
                                        <span class="bk-go">Import</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function argo_feature_demo_sheet_import(): void
{
    ?>
    <div class="tab-content-inner tab-content-inner--solo">
        <div class="tab-content-visual">
            <div class="feature-visual-card invoice-studio-card">
                <div class="invoice-studio">
                    <div class="invoice-window">
                        <div class="app-topbar">
                            <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                        </div>
                        <div class="app-body">
                            <div class="app-nav" aria-hidden="true">
                                <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                                <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('document', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                            </div>
                            <div class="app-content">
                                <div class="app-page-title">Import Spreadsheet</div>
                                <div class="sh-stage" id="sheetStage">
                                    <div class="sh-file">
                                        <span class="sh-file-ic"><?= svg_icon('table', 15) ?></span>
                                        <span class="sh-file-name">expenses_2026.xlsx</span>
                                        <span class="sh-file-meta">Sheet 1</span>
                                    </div>
                                    <div class="sh-grid">
                                        <div class="sh-head">
                                            <div class="sh-col" data-i="0"><span class="sh-letter">A</span><span class="sh-name">Date</span><span class="sh-map">→ Date</span></div>
                                            <div class="sh-col" data-i="1"><span class="sh-letter">B</span><span class="sh-name">Supplier</span><span class="sh-map">→ Vendor</span></div>
                                            <div class="sh-col" data-i="2"><span class="sh-letter">C</span><span class="sh-name">Amount</span><span class="sh-map">→ Total</span></div>
                                            <div class="sh-col" data-i="3"><span class="sh-letter">D</span><span class="sh-name">Notes</span><span class="sh-map">→ Category</span></div>
                                        </div>
                                            <div class="sh-row"><span class="sh-cell" data-i="0">03/11/2026</span><span class="sh-cell" data-i="1">Northside Hardware</span><span class="sh-cell" data-i="2">139.37</span><span class="sh-cell" data-i="3">materials</span></div>
                                            <div class="sh-row"><span class="sh-cell" data-i="0">05/11/2026</span><span class="sh-cell" data-i="1">City Fuel</span><span class="sh-cell" data-i="2">82.10</span><span class="sh-cell" data-i="3">fuel</span></div>
                                            <div class="sh-row"><span class="sh-cell" data-i="0">09/11/2026</span><span class="sh-cell" data-i="1">Paper Co</span><span class="sh-cell" data-i="2">41.55</span><span class="sh-cell" data-i="3">office</span></div>
                                            <div class="sh-row"><span class="sh-cell" data-i="0">14/11/2026</span><span class="sh-cell" data-i="1">Northside Hardware</span><span class="sh-cell" data-i="2">206.80</span><span class="sh-cell" data-i="3">materials</span></div>
                                    </div>
                                    <div class="sh-foot"><span class="sh-status" id="shStatus">Reading columns…</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function argo_feature_demo_report(): void
{
    ?>
    <div class="tab-content-inner tab-content-inner--solo">
        <div class="tab-content-visual">
            <div class="feature-visual-card invoice-studio-card">
                <div class="invoice-studio">
                    <div class="invoice-window">
                        <div class="app-topbar">
                            <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                        </div>
                        <div class="app-body">
                            <div class="app-nav" aria-hidden="true">
                                <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                                <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('credit-card', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                            </div>
                            <div class="app-content">
                                <div class="app-page-title">Reports</div>
                                <div class="rp-stage" id="reportStage">
                                    <div class="rp-types">
                                        <span class="rp-type rp-type--on">Profit &amp; loss</span>
                                        <span class="rp-type">Balance sheet</span>
                                        <span class="rp-type">Expenses</span>
                                    </div>
                                    <div class="rp-sheet">
                                        <div class="rp-head"><span>Jan – Jun 2026</span><span class="rp-head-note">All accounts</span></div>
                                        <div class="rp-line " data-i="0"><span>Revenue</span><span class="rp-val">48,210.00</span></div>
                                        <div class="rp-line " data-i="1"><span>Cost of goods</span><span class="rp-val">−19,884.00</span></div>
                                        <div class="rp-line sub" data-i="2"><span>Gross profit</span><span class="rp-val">28,326.00</span></div>
                                        <div class="rp-line " data-i="3"><span>Operating costs</span><span class="rp-val">−9,140.00</span></div>
                                        <div class="rp-line total" data-i="4"><span>Net profit</span><span class="rp-val rp-net">0.00</span></div>
                                    </div>
                                    <div class="rp-foot"><span class="rp-exp" id="rpExport">Export PDF</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function argo_feature_demo_stripe(): void
{
    ?>
    <div class="tab-content-inner tab-content-inner--solo">
        <div class="tab-content-visual">
            <div class="feature-visual-card invoice-studio-card">
                <div class="invoice-studio">
                    <div class="invoice-window">
                        <div class="app-topbar">
                            <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                        </div>
                        <div class="app-body">
                            <div class="app-nav" aria-hidden="true">
                                <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                                <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('credit-card', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('users', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                            </div>
                            <div class="app-content">
                                <div class="app-page-title">Payments</div>
                                <div class="st-stage" id="stripeStage">
                                    <div class="st-rows">
                                        <div class="st-row" data-i="0"><span class="st-ic"><?= svg_icon('credit-card', 13) ?></span><span class="st-who"><b>Card payment</b><i>Sarah Miller · #INV-0042</i></span><span class="st-amt">+$1,234.00</span><span class="st-tick"><?= svg_icon('check', 13) ?></span></div>
                                        <div class="st-row" data-i="1"><span class="st-ic"><?= svg_icon('credit-card', 13) ?></span><span class="st-who"><b>Card payment</b><i>Maple &amp; Co. · #INV-0043</i></span><span class="st-amt">+$480.00</span><span class="st-tick"><?= svg_icon('check', 13) ?></span></div>
                                        <div class="st-row" data-i="2"><span class="st-ic"><?= svg_icon('credit-card', 13) ?></span><span class="st-who"><b>Card payment</b><i>J. Okafor · #INV-0044</i></span><span class="st-amt">+$612.50</span><span class="st-tick"><?= svg_icon('check', 13) ?></span></div>
                                    </div>
                                    <div class="st-payout">
                                        <div class="st-payout-head"><span>Payout to your bank</span><span class="st-payout-badge">3 invoices</span></div>
                                        <div class="st-payout-row"><span>Gross</span><span class="st-gross">$0.00</span></div>
                                        <div class="st-payout-row"><span>Processing fees</span><span class="st-fee">−$0.00</span></div>
                                        <div class="st-payout-row st-payout-net"><span>Net</span><span class="st-net">$0.00</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Pay run review. The deduction columns land after the row they belong to, so
 * the demo shows the figures being worked out rather than arriving pre-filled,
 * which is the part of payroll people are actually buying.
 *
 * The numbers are a real biweekly run under the 2026 rates and add up: gross
 * less CPP, EI and tax equals the net on the same row, and the three nets equal
 * the total in the footer. Anyone who checks is the sort of person who would
 * notice, and payroll figures that do not add up sell the opposite of trust.
 */
function argo_feature_demo_payroll(): void
{
    ?>
    <div class="tab-content-inner tab-content-inner--solo">
        <div class="tab-content-visual">
            <div class="feature-visual-card invoice-studio-card">
                <div class="invoice-studio">
                    <div class="invoice-window">
                        <div class="app-topbar">
                            <span class="app-brand"><img src="<?= argo_feature_demo_base() ?>resources/images/argo-logo/argo-books-icon-transparent.png" alt="" class="app-brand-img">Argo Books</span>
                        </div>
                        <div class="app-body">
                            <div class="app-nav" aria-hidden="true">
                                <span class="app-nav-btn"><?= svg_icon('grid', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('calendar', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('trending-up', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('document', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('receipt', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('dollar', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('credit-card', 18) ?></span>
                                <span class="app-nav-btn app-nav-btn--active"><?= svg_icon('users', 18) ?></span>
                                <span class="app-nav-btn"><?= svg_icon('package', 18) ?></span>
                            </div>
                            <div class="app-content">
                                <div class="app-page-title">Pay Run</div>
                                <div class="pr-stage" id="payrollStage">
                                    <div class="pr-file">
                                        <span class="pr-file-ic"><?= svg_icon('calendar', 15) ?></span>
                                        <span class="pr-file-name">Aug 3 &ndash; Aug 16</span>
                                        <span class="pr-file-meta">Paid Aug 21 &middot; 3 employees</span>
                                    </div>
                                    <div class="pr-grid">
                                        <div class="pr-head">
                                            <span>Employee</span><span>Gross</span><span>CPP</span><span>EI</span><span>Tax</span><span>Net</span>
                                        </div>
                                        <div class="pr-row" data-i="0">
                                            <span class="pr-name">D. Okafor <i>ON</i></span>
                                            <span class="pr-num">2,400.00</span>
                                            <span class="pr-num pr-ded">134.79</span>
                                            <span class="pr-num pr-ded">39.12</span>
                                            <span class="pr-num pr-ded">421.35</span>
                                            <span class="pr-num pr-net">1,804.74</span>
                                        </div>
                                        <div class="pr-row" data-i="1">
                                            <span class="pr-name">S. Miller <i>BC</i></span>
                                            <span class="pr-num">1,950.00</span>
                                            <span class="pr-num pr-ded">108.02</span>
                                            <span class="pr-num pr-ded">31.79</span>
                                            <span class="pr-num pr-ded">285.40</span>
                                            <span class="pr-num pr-net">1,524.79</span>
                                        </div>
                                        <div class="pr-row" data-i="2">
                                            <span class="pr-name">A. Nadeau <i>AB</i></span>
                                            <span class="pr-num">1,600.00</span>
                                            <span class="pr-num pr-ded">87.19</span>
                                            <span class="pr-num pr-ded">26.08</span>
                                            <span class="pr-num pr-ded">213.55</span>
                                            <span class="pr-num pr-net">1,273.18</span>
                                        </div>
                                    </div>
                                    <div class="pr-foot">
                                        <span class="pr-count">Net pay <b id="prNet">$0.00</b></span>
                                        <span class="pr-go">Approve</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
