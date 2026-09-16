<?php
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../config/pricing.php';
require_once __DIR__ . '/../partials/fonts.php';
$pricing = get_pricing_config();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Argo">

    <!-- SEO Meta Tags -->
    <meta name="description"
        content="Latest updates and new features in Argo Books. Download the latest release.">
    <meta name="keywords"
        content="argo books updates, new features, version history, changelog, release notes, software updates, latest version, product improvements, software updates">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="What's New - Argo Books | Latest Features & Updates">
    <meta property="og:description"
        content="Latest updates and new features in Argo Books. Download the latest release.">
    <meta property="og:url" content="https://argorobots.com/whats-new/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="What's New - Argo Books | Latest Features & Updates">
    <meta name="twitter:description"
        content="Latest updates and new features in Argo Books. Download the latest release.">
    <meta property="og:image" content="https://argorobots.com/resources/images/og/og-home.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="https://argorobots.com/resources/images/og/og-home.png">

    <!-- Additional SEO Meta Tags -->
    <meta name="geo.region" content="CA-SK">
    <meta name="geo.placename" content="Canada">
    <meta name="geo.position" content="52.1579;-106.6702">
    <meta name="ICBM" content="52.1579, -106.6702">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://argorobots.com/whats-new/">

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>What's New - Argo Books | Latest Features & Updates</title>

    <script src="../resources/scripts/main.js"></script>

    <script src="main.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/styles/link.css">
    <link rel="stylesheet" href="../resources/header/style.css">
    <link rel="stylesheet" href="../resources/footer/style.css">
    <!-- Brand typefaces (Fraunces display + IBM Plex Sans body), matched to the rest of the site -->
    <?= argo_font_links('default', '    ') ?>
    <link rel="stylesheet" href="../resources/styles/typography.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>
    <main>

    <section class="hero">
        <div class="hero-content">
            <h1>What's New in Argo Books</h1>
            <p>Discover the latest improvements and features we've added to make managing your business even easier</p>
            <div class="hero-buttons">
                <a href="../downloads" class="btn btn-primary">
                    <?= svg_icon('download', 18) ?>
                    Download Latest
                </a>
                <a href="../older-versions/" class="btn btn-secondary">
                    Older Versions
                </a>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="version-grid">

            <!-- Version 2.0.16 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.16</span>
                        <span class="date-tag">September 16 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Your stock counts as stock, not spending:</strong> Buying stock for a product you track adds to your inventory, and its cost comes off your profit on the day it sells. A month where you restocked heavily no longer looks like a loss, and your Income Statement now shows Cost of Goods Sold and Gross Profit.</li>
                                <li><strong>Book rentals ahead:</strong> A rental that starts later is saved as a reservation and leaves the units on your shelf until you check it out. The same units can't be promised twice for dates that overlap, and you can cancel a reservation in one click.</li>
                                <li><strong>Give back as much of a deposit as you choose:</strong> When a rental comes back, enter how much of the security deposit to return. Whatever you keep is recorded as revenue. If the customer paid the rental invoice online, the refund opens with that amount already filled in, ready to send back through Stripe or Square.</li>
                                <li><strong>Charge for late returns and damage:</strong> Add an extra charge on the return, with a note saying what it is for, and it is added to what the rental comes to.</li>
                                <li><strong>Stock in more than one place:</strong> Move stock between a shop and a storage unit right from Stock Levels page, and choose the location on a sale or purchase when a product is stocked in more than one.</li>
                                <li><strong>Stock by weight and volume:</strong> Choose the unit a product is stocked in, such as kilograms, litres or boxes, and keep part units like 2.5 kg.</li>
                                <li><strong>Add a category, customer or supplier as you type:</strong> Type a name that isn't on the list yet and Argo Books creates it when you save, so you don't have to stop and set it up first.</li>
                                <li><strong>Provinces, states and counties from a list:</strong> Thirteen countries now pick their region from a list, each with its flag, and the label matches the country.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Search by ID:</strong> Typing 20 finds PRD-020, on the products, customers and suppliers pages.</li>
                                <li><strong>Know about the payment portal up front:</strong> Creating an invoice from the rentals page tells you right away when the payment portal isn't set up yet, instead of at the moment you try to send.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>Editing or deleting a sale or purchase returns exactly the stock it moved.</li>
                                <li>Revenue and expenses brought in from Stripe or the Argo Books API now move stock too.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.15 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.15</span>
                        <span class="date-tag">September 12 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Argo Books for Mac:</strong> Download Argo Books on any Mac, Apple silicon or Intel. With Premium, you can open your company file with Touch ID.</li>
                                <li><strong>Add items as you go:</strong> When you record an expense or revenue, type a new item or category right on the line and Argo Books creates it when you save. No more stopping to set up products first.</li>
                                <li><strong>Export a Record of Employment:</strong> Create the ROE file itself, ready to upload to Service Canada.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>An easier start:</strong> For new users, the first screen now puts Create New Company up front, with the sample company right below it.</li>
                                <li><strong>Choose the currency on scanned receipts:</strong> When you review a scanned receipt, you can see the currency and change it before saving.</li>
                                <li><strong>Last name is optional:</strong> You can now add a customer with only a first name.</li>
                                <li><strong>Categories to start with:</strong> A new company starts with categories for its industry.</li>
                                <li><strong>A helping hand in Analytics:</strong> An empty Analytics page points you to adding your first expense.</li>
                                <li><strong>Argo Books speaks your language from the start:</strong> A new install opens in your computer's language when we have that translation.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>A yearly schedule that starts on February 29 now stays on the 29th in leap years.</li>
                                <li>Exporting a PDF receipt saves the original PDF, with every page.</li>
                                <li>Two scanned receipts with the same file name each show their own preview.</li>
                                <li>Changing the return date on a rental updates what it costs.</li>
                                <li>A purchase order that arrived in part can now be received the rest of the way.</li>
                                <li>Undoing a bank match puts the suggestions on the other lines straight back.</li>
                                <li>Editing a recurring schedule's start date moves its next date to match.</li>
                                <li>Resuming a paused recurring schedule picks up from today.</li>
                                <li>Extending a finished recurring schedule starts it again.</li>
                                <li>A refunded invoice no longer shows as overdue.</li>
                                <li>Spreadsheets with day-first dates, like UK dates, import correctly.</li>
                                <li>The archive button on the employee list is no longer cut off.</li>
                                <li>Archiving now has its own icon.</li>
                                <li>The update window shows the Argo Books logo.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.14 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.14</span>
                        <span class="date-tag">September 5 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Recurring expenses and revenue</strong> Set up rent, subscriptions, insurance or a monthly retainer once and Argo Books enters it for you every time it comes round. You stay in charge: each entry arrives marked for review, and you can skip a single month, pause a schedule, or stop it altogether.</li>
                                <li><strong>Photos straight from your iPhone:</strong> Receipt photos taken on an iPhone (HEIC format) now work exactly like any other picture, with no converting or exporting first.</li>
                                <li><strong>Open a company by double-clicking it:</strong> Double-click an Argo Books file and it opens in the app, the way you would expect.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>A smaller download:</strong> The installer is about 20 MB smaller, and takes up 100 MB less on your computer. It also makes setting up Argo Books quicker.</li>
                                <li><strong>Switching currency is much faster:</strong> Changing the currency you work in is faster, even on a slower connection.</li>
                                <li><strong>Move a receipt to the other side of the books:</strong> Filed a receipt as an expense when it was really revenue? Switch it without starting again.</li>
                                <li><strong>Pay stubs and receipts open without a connection:</strong> Opening a PDF inside Argo Books no longer needs the internet.</li>
                                <li><strong>A smoother payment portal email:</strong> Setting and changing the email address for your payment portal is quicker and smoother. And if you used an email for a payment portal in another company, you can use it in a new one: confirm it once and it moves across.</li>
                                <li><strong>"Email me when a customer pays" has moved:</strong> It now sits on the Payment Portal tab in Settings, beside the other emails sent on your behalf, rather than under Notifications.</li>
                                <li><strong>Easier menus:</strong> Click anywhere on a section heading in the sidebar to open or close it, instead of aiming for the small arrow.</li>
                                <li><strong>Finding your way out of the sample company:</strong> The demo company now makes it clear you are looking at example figures, and suggests creating your own company.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.13 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.13</span>
                        <span class="date-tag">August 25 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Connect other apps to your books:</strong> Other software can now send customers, suppliers, invoices and expenses straight into Argo Books. Nothing reaches your books until you have seen it: everything arrives as a list of suggestions you look over and accept, and a single undo reverses a whole import if you change your mind. Argo Books matches customers and suppliers you already have, so the same person does not turn up twice. Switch it on under Settings, Integrations, where you can give each app its own key and take it back whenever you like.</li>
                                <li><strong>Full screen with F11:</strong> Press F11 to fill the whole screen with Argo Books and put everything else out of sight. F11 or Escape brings the window back.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>See what a sync is doing:</strong> A sync now tells you what it's working on while it runs, instead of leaving you watching a spinner.</li>
                                <li><strong>Charts open the way most people want them:</strong> Charts now start in the area style rather than the bar style.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.12 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.12</span>
                        <span class="date-tag">August 18 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Payroll for Canadian businesses:</strong> Pay your staff without a separate payroll service. Argo Books works out CPP, EI and income tax using the CRA's own figures, for every province and territory, then produces pay stubs for your employees. At year end it prepares your T4 slips and the file the CRA needs, plus RL-1 slips if you have Quebec staff. When someone leaves, it gathers the figures for their Record of Employment.</li>
                                <li><strong>Automatic payment reminders:</strong> Argo Books can now chase overdue invoices for you, emailing the customer a payment link 3, 7 and 14 days after the due date and stopping as soon as they pay. The reminders are sent by our servers, so they keep going even when Argo Books is closed. Turn it on in the Payment Portal settings; only invoices that fall due after you switch it on are chased.</li>
                                <li><strong>Know the moment you get paid:</strong> Choose to be emailed whenever a customer pays an invoice online, so you find out without having the app open.</li>
                                <li><strong>Send an invoice again:</strong> Resend any invoice straight from the invoice list, for the customer who says it never arrived.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Payments live on the invoice:</strong> Record a cash or cheque payment on the invoice it belongs to, and edit or remove it from the same place. No more hopping to a separate page and picking the invoice back out of a list.</li>
                                <li><strong>A menu that follows the money:</strong> The sidebar is now split into Expenses and Revenue, so suppliers sit alongside what you buy and customers alongside what you sell.</li>
                                <li><strong>Spreadsheet exports carry more:</strong> Every row now names the supplier or customer name instead of only showing an ID. Invoices bring their line items with them, amounts carry their quantity and currency, and there are new sheets for your employees and your pay runs.</li>
                                <li><strong>Loading screen for exports</strong> A loading screen now appears while a spreadsheet is being exported, instead of freezing.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li><strong>Timestamps show the local time</strong> Some timestamps were showing UTC instead of the local time.</li>
                                <li><strong>Invoices show partial payments</strong> If an invoice was published on the payment portal, then marked as partially payed, the invoice now updates and shows the new amount.</li>
                                <li><strong>Rendering issue</strong> Fixed a rendering issue that could sometimes appear when opening a search box during the tutorial.</li>
                                <li>The usual round of polish and performance work across the app.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.11 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.11</span>
                        <span class="date-tag">July 27 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Password recovery:</strong> If you forget the password to a company file, our support team can now unlock it for you. Until now a forgotten password meant the file stayed closed for good. Your password itself is still private and cannot be read by anyone, including us.</li>
                                <li><strong>Stripe integration:</strong> Connect your Stripe account and bring your payments straight into Argo Books. Sales, tax, discounts, processing fees, and refunds all come across. Argo Books also remembers your Stripe payouts, so when you later import your bank statement it skips the matching deposit instead of counting that income twice.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Much faster startup:</strong> Argo Books opens around 40% faster on Windows, and a loading screen now appears right away so you can see it starting.</li>
                                <li><strong>Setup starts with a receipt scan:</strong> The getting-started guide now begins by scanning a sample receipt, so you can see what Argo Books does within seconds of opening it for the first time.</li>
                                <li><strong>Better with long receipts:</strong> Receipt scanning now handles longer, more detailed receipts with many line items.</li>
                                <li><strong>Clearer settings layout:</strong> Settings tabs have moved to the side, so all the sections are visible at once.</li>
                                <li><strong>Text in dark theme</strong> The text in dark theme is now white instead of gray, improving contrast and making it easier to read.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li><strong>Multiple company workflows:</strong> Working in several company files side by side in different instances of the app is smoother.</li>
                                <li>The usual round of polish and performance work across the app.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.10 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.10</span>
                        <span class="date-tag">July 6 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Redesigned invoice editor:</strong> Build invoices right on the invoice itself, so what you see on screen is exactly what your customer receives. Click any part of the invoice to edit line items, dates, or notes, and pick products and services from a dropdown as you type. No more filling out a separate form and hoping the layout looks right.</li>
                                <li><strong>Recurring invoices:</strong> Set any invoice to repeat on a schedule you choose. Perfect for subscriptions, rent, and any regular income.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Multi-currency precision:</strong> Amounts stay accurate to the cent across every currency, so your totals, charts, and summary cards always agree.</li>
                                <li><strong>Smoother data imports:</strong> Importing from spreadsheets, bank statements, and receipts is more reliable, including unusual date and currency formats.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes &amp; Improvements</h4>
                            <p class="changelog-intro">This release also brings a large round of fixes, performance gains, and general refinements throughout the app. Plenty of small polish to keep everything running smoothly and reliably.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.9 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.9</span>
                        <span class="date-tag">June 29 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Bank statement import:</strong> Import a bank statement and Argo Books turns each line into a categorized expense or revenue, filling in the product, category, and supplier or customer for you. A quick way to catch up on your books or stay on top of them each month, with no bank login or connection required.</li>
                                <li><strong>Sales by Product analytics:</strong> A new Sales by Product tab and report on the Analytics page shows exactly which products bring in the most revenue.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Smarter spreadsheet imports:</strong> The spreadsheet importer is now better at reading unusual spreadsheet layouts and matching entries to your existing data.</li>
                                <li><strong>Quick "Create one":</strong> Add a new category, supplier, customer, or product right from the dropdown you're filling in, without leaving the form.</li>
                                <li><strong>Multi-currency spreadsheet import:</strong> Spreadsheet import now support spreadsheets that use multiple difference currencies.</li>
                                <li><strong>Smoother performance:</strong> The interface stays responsive in more situations. Opening companies is now around 5 times faster.</li>
                                <li><strong>Clearer offline messages:</strong> When a feature needs the internet, you'll see one consistent message across the app.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li><strong>More reliable receipt scanning:</strong> Longer, more detailed receipts now scan more reliably.</li>
                                <li><strong>Readable tooltips in light mode:</strong> Tooltip text now displays clearly against light backgrounds.</li>
                                <li><strong>Clearer save messages:</strong> If your save location isn't available, Argo Books now shows a helpful message explaining what to do.</li>
                                <li>Various stability improvements, and additional fixes.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.8 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.8</span>
                        <span class="date-tag">June 4 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Bank statement matching:</strong> Import a bank statement and Argo Books automatically matches each line against your recorded expenses and revenue, so you can spot anything missing or doubled up in minutes instead of checking line by line. Includes a month calendar view of your matched activity.</li>
                                <li><strong>Email purchase orders to suppliers:</strong> Send a purchase order straight to your supplier as a PDF without leaving Argo Books, and save their email for next time.</li>
                                <li><strong>Inventory on the Balance Sheet:</strong> Your inventory value now appears as a current asset, giving a more complete picture of what your business owns.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Better receipt scanning:</strong> WebP images are now supported, and drag-and-drop is smoother.</li>
                                <li><strong>Smoother first-time setup:</strong> The setup checklist now highlights each step as you go, and the app tour is shorter and clearer.</li>
                                <li><strong>Currency check:</strong> When creating a company, Argo Books gives you a heads-up if the selected currency doesn't match your country.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li><strong>Accurate unsaved-changes indicator:</strong> Undoing back to your last save now clears the unsaved-changes marker in the title bar.</li>
                                <li><strong>Steadier receipt viewer:</strong> The receipt panel keeps its size in fullscreen mode.</li>
                                <li>Various bug fixes and stability improvements.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.7 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.7</span>
                        <span class="date-tag">May 22 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Invoice refunds:</strong> Refund a portal payment without leaving Argo Books. Pick which line items or amount to refund and click send.</li>
                                <li><strong>Send invoices in any currency:</strong> Bill customers in the currency that works for them, no matter where they're based.</li>
                                <li><strong>Refund analytics:</strong> A new Refunds tab in the analytics page shows your refund rate, top reasons, and trends over time.</li>
                                <li><strong>Customer &amp; supplier avatars:</strong> Add a profile picture to any customer or supplier.</li>
                                <li><strong>Rental availability calendar:</strong> Each rental item now has a calendar view of free and booked counts, plus a quick "next available" answer for any quantity you need.</li>
                                <li><strong>Forecast scenarios:</strong> Forecasts now show Conservative, Baseline, and Optimistic ranges instead of a single line, so you can see the uncertainty around future projections.</li>
                                <li><strong>Change payment portal email:</strong> Change the email on your account with secure two-step verification. Completed changes can be reverted from a link in your email for 30 days if something goes wrong.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Faster startup:</strong> Company data and settings now load in parallel, so the app opens noticeably faster.</li>
                                <li><strong>Processing fee explainer:</strong> Processing fee details are now tucked into an info popup, keeping the invoice screen cleaner.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li><strong>Receipt list updates instantly:</strong> Newly scanned receipts now appear on the receipts page right away.</li>
                                <li><strong>Local time on timestamps:</strong> Some timestamps were showing in UTC instead of your local time; they now display correctly.</li>
                                <li>Various bug fixes and stability improvements.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.6 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.6</span>
                        <span class="date-tag">April 15 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Customizable dashboard:</strong> The dashboard now has an Edit button that lets you fully customize the layout. Add, remove, resize, and rearrange widgets to highlight what matters most to your business. Everything works the same by default; customization is there when you want it.</li>
                                <li><strong>Bulk receipt scanning:</strong> Scan multiple receipts at once. Drop or select a batch of files, watch them process in real time, then review and approve them all in a carousel view.</li>
                                <li><strong>PDF receipt scanning:</strong> You can now scan PDF receipts in addition to images.</li>
                                <li><strong>Password strength meter:</strong> A strength indicator now appears when setting or changing your password.</li>
                                <li><strong>Duplicate customer warning:</strong> You'll now see a warning when creating a customer that already exists.</li>
                                <li><strong>Deletion protection:</strong> Records that are referenced by other data (like a customer linked to invoices) can no longer be deleted.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Improved search:</strong> The header search bar now searches across all your data, not just quick actions.</li>
                                <li><strong>Faster performance:</strong> Numerous speed and memory optimizations throughout the app, especially on pages with large datasets. Invoices and PDFs also render much faster.</li>
                                <li><strong>Automatic inventory adjustments:</strong> Inventory now adjusts automatically when you record expenses or revenue, so stock levels stay in sync without manual updates.</li>
                                <li><strong>Quick entity creation:</strong> Creating new items like customers or products now opens on top of your current page instead of navigating away.</li>
                                <li><strong>Phone number validation:</strong> Phone numbers are now validated before saving to help catch typos.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>Various bug fixes and stability improvements.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.5 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.5</span>
                        <span class="date-tag">April 2 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>AI receipt scanning for everyone:</strong> AI-powered receipt scanning is now available in the free plan with a <?= (int) $pricing['free_receipt_scan_monthly_limit'] ?> receipt per month usage limit.</li>
                                <li><strong>Company name in payment portal:</strong> Directly control which business name your customers see in the payment portal. There is now a dedicated control for this in the settings.</li>
                                <li><strong>Export world map to Excel:</strong> The world map on the Analytics page can now be exported to Excel.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>More accurate receipt scanning:</strong> The AI receipt scanner is now significantly better at reading receipts, especially ones with complex layouts.</li>
                                <li><strong>Receipt total mismatch warning:</strong> You'll now see a warning if the scanned line items don't add up to the receipt total, so you can catch errors before saving.</li>
                                <li><strong>Fullscreen receipt viewer:</strong> Toggle fullscreen when reviewing scanned receipts for a better view of the details.</li>
                                <li><strong>Faster currency switching:</strong> Exchange rates are now preloaded in bulk, so changing your default currency is noticeably faster.</li>
                                <li><strong>Faster charts:</strong> The charts now load significantly faster and are more responsive.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>Fixed world map saving as a blank image when using "Save as Image".</li>
                                <li>Fixed invoice line item totals not rounding correctly in some cases.</li>
                                <li>Fixed small visual inconsistencies throughout the app</li>
                                <li>Various bug fixes and stability improvements.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.4 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.4</span>
                        <span class="date-tag">March 26 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Invoice template management:</strong> Browse and manage your invoice templates in a new view with thumbnail previews.</li>
                                <li><strong>Multi-currency accounting reports:</strong> Accounting reports now support multiple currencies.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Undo/redo for invoice templates:</strong> Undo and redo changes when editing invoice templates.</li>
                                <li><strong>Localized tax labels:</strong> Tax labels now automatically match your company's country.</li>
                                <li><strong>Improved offline experience:</strong> Better detection and messaging when working offline, with a pending transaction queue so nothing is lost.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>Fixed pagination arrows not updating correctly.</li>
                                <li>Various bug fixes and stability improvements.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.3 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.3</span>
                        <span class="date-tag">March 15 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Security improvements:</strong> Strengthened security across the application, including password protection for the payment portal settings.</li>
                                <li><strong>Smarter receipt scanning:</strong> AI category suggestions are now more accurate and specific.</li>
                                <li><strong>Google Sheets export:</strong> Added a cancel button during export.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>Fixed an issue with API services not working.</li>
                                <li>Fixed receipt scanner layout and input issues.</li>
                                <li>Various bug fixes and stability improvements.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.2 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.2</span>
                        <span class="date-tag">March 10 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>AI spreadsheet import:</strong> Import spreadsheets in any format and let AI automatically detect the data type and map your columns, so you can bring in your data without reformatting.</li>
                                <li><strong>Accounting reports:</strong> Generate professional accounting reports including Income Statement, Balance Sheet, Cash Flow Statement, General Ledger, AR Aging, and Tax Summary, all with localized tax terminology for your country.</li>
                                <li><strong>Multi-page reports:</strong> Create reports that span multiple pages. All pages display on the canvas at once, and tables that overflow automatically continue onto the next page.</li>
                                <li><strong>Company details on reports:</strong> Reports can now display your company logo, name, address, phone, and email in a header. Enabled by default on accounting templates.</li>
                                <li><strong>Auto-create revenue from invoices:</strong> Sending an invoice now automatically creates a matching revenue entry, so your dashboard and reports stay in sync without manual data entry.</li>
                                <li><strong>Tax analytics:</strong> A new Taxes tab on the Analytics page with charts for tax collected vs. paid, net tax liability, tax rate distribution, and breakdowns by category and product.</li>
                                <li><strong>Fullscreen charts:</strong> A new button in the top-right corner of charts lets you open them in fullscreen for easier viewing and detailed analysis.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Faster tables:</strong> Tables now use virtualization so only visible rows are rendered, resulting in noticeably faster page loads and lower memory usage.</li>
                                <li><strong>Date range label:</strong> The Dashboard and Analytics pages now display the active date span, and "All Time" starts from your earliest transaction instead of a fixed date.</li>
                                <li><strong>Sidebar remembers state:</strong> Sidebar sections stay expanded or collapsed between sessions.</li>
                                <li><strong>Improved version history:</strong> History entries are now nested and show exactly which fields changed.</li>
                                <li><strong>Interactive column resizing:</strong> Adjust column widths directly in the report designer by dragging column edges.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>Several bug fixes for improved stability.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.1 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.1</span>
                        <span class="date-tag">February 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Backup &amp; restore:</strong> Create <code>.argobk</code> backup files of your company data, and restore from them at any time.</li>
                                <li><strong>Version history &amp; audit trail:</strong> Every change to your data is now recorded with timestamps and details. Browse, search, and undo/redo past changes from the new version history panel.</li>
                                <li><strong>New invoice template:</strong> A new modern invoice featuring decorative wave accents.</li>
                                <li><strong>Multi-item rentals:</strong> Rent multiple items in a single transaction, each with its own rate, quantity, and security deposit.</li>
                                <li><strong>Generate invoices from rentals:</strong> Create invoices directly from rental records with all fields auto-populated.</li>
                                <li><strong>Online invoice payments:</strong> Connect your Stripe, PayPal, or Square account and let your customers view and pay invoices online through a payment portal.</li>
                                <li><strong>Receipt grid/table toggle:</strong> Switch between grid and table views on the Receipts page for flexible data browsing.</li>
                                <li><strong>Default product price:</strong> Set a default price for products so new entries start with a pre-filled amount.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Rental payment tracking:</strong> Track paid status and view linked invoices directly from the rentals table.</li>
                                <li><strong>Improved responsiveness:</strong> The interface now adapts more smoothly across different screen sizes.</li>
                                <li><strong>Invoice template color controls:</strong> Added per-template color settings.</li>
                                <li><strong>Improved undo/redo:</strong> Rapid property changes are combined into single entries.</li>
                                <li><strong>Receipt scanner zoom &amp; pan:</strong> Added zoom and pan controls to the receipt scanner preview for easier reviewing.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>Several bug fixes for improved stability.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 2.0.0 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 2.0.0</span>
                        <span class="date-tag">January 2026</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="release-post">
                        <p class="intro">
                            We're excited to announce Argo Books 2.0: a complete rebuild from the ground up. This isn't just an update; it's an entirely new application built with modern technology to give you more power, more flexibility, and a dramatically better experience.
                        </p>

                        <h3>A New Foundation: Cross-Platform and Modern</h3>
                        <p>
                            We've rebuilt Argo Books using Avalonia UI and .NET 10, replacing the old Windows-only WinForms architecture. This means you can now run Argo Books natively on <strong>Windows, macOS, and Linux</strong>. The interface has been completely redesigned with a fresh, modern look that's more intuitive and responsive than ever before.
                        </p>

                        <h3>AI-Powered Receipt Scanning</h3>
                        <p>
                            Say goodbye to manual data entry. Take a photo of any receipt with your phone or upload an image from your computer, and our AI automatically extracts the vendor, date, amount, and individual line items with 99.9% accuracy. It's the fastest way to log expenses and keep your books up to date.
                        </p>

                        <h3>Predictive Analytics</h3>
                        <p>
                            Argo Books now examines your historical business data to forecast future sales patterns and identify seasonal trends. See where your business is heading, not just where it's been. This helps you make smarter decisions about inventory, staffing, and cash flow planning.
                        </p>

                        <h3>Inventory Management</h3>
                        <p>
                            Track your stock levels in real time with the new inventory management system. Set reorder points to get alerts when stock runs low, monitor product movement, and maintain complete visibility over your entire inventory. No more spreadsheets or guesswork.
                        </p>

                        <h3>Rental Management</h3>
                        <p>
                            For businesses that rent equipment or products, we've added a dedicated rental management module. Track availability, manage bookings, and handle the logistics of your rental operations all in one place.
                        </p>

                        <h3>Customer Management</h3>
                        <p>
                            Build stronger relationships with your customers by keeping track of their purchase history, preferences, and contact information. Use this data to provide better service and identify your most valuable customers.
                        </p>

                        <h3>Professional Invoicing</h3>
                        <p>
                            Create professional invoices with customizable templates that match your brand. Set payment terms, track invoice status, and keep all your billing organized in one place.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Version 1.0.7 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 1.0.7</span>
                        <span class="date-tag">August 29 2025</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Security improvements:</strong> Strengthened security across the application.</li>
                                <li><strong>Faster startup:</strong> Optimized the startup sequence so the app opens quicker.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li><strong>Label alignment after language switch:</strong> Some labels weren't aligning correctly after changing the app language.</li>
                                <li><strong>SearchBox positioning:</strong> The SearchBox size and position weren't updating correctly when renaming companies on the Get Started Form.</li>
                                <li><strong>Font scaling in Recent Companies panel:</strong> Font sizes now scale properly across different display settings.</li>
                                <li><strong>Date range panel translation:</strong> The date range panel wasn't translating in some cases.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 1.0.6 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 1.0.6</span>
                        <span class="date-tag">August 17 2025</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>More languages:</strong> Added support for additional languages.</li>
                                <li><strong>Bulk import controls:</strong> Added "Yes to all" and "No to all" buttons in import dialogs so you don't have to respond to each duplicate prompt individually.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li><strong>Application scaling:</strong> Fixed scaling issues on high-DPI displays and different screen resolutions.</li>
                                <li>Various bug fixes and stability improvements.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 1.0.5 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 1.0.5</span>
                        <span class="date-tag">August 7 2025</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Automatic updates:</strong> The app now checks for new versions on startup and prompts you to install them. You may need to download this version manually to enable auto-updates going forward.</li>
                                <li><strong>Company logo:</strong> You can now set a custom company logo that appears in the main interface. Click it or right-click to change it.</li>
                                <li><strong>Version compatibility check:</strong> The app now warns you if you try to open a file saved with a newer version.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Accountant selection:</strong> Your accountant choice is now remembered when opening companies, so you don't have to pick one every time.</li>
                                <li><strong>Return tracking:</strong> Returned products now show which accountant processed the return.</li>
                                <li><strong>Translated logs:</strong> Application logs can now be viewed in other languages.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>Several bug fixes for improved stability.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 1.0.4 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 1.0.4</span>
                        <span class="date-tag">July 20 2025</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Product returns:</strong> Right-click any transaction to process a return. New analytics charts track return patterns and refund amounts.</li>
                                <li><strong>Geographic analytics map:</strong> A world map view showing your business data broken down by country.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Faster charts:</strong> Rebuilt the chart system for much better performance. Charts now load instantly and have smooth animations (which you can turn off in settings).</li>
                                <li><strong>Organized analytics:</strong> Charts are now grouped into tabs for easier navigation.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li><strong>Translation gaps:</strong> Some UI controls weren't being translated in non-English languages.</li>
                                <li>General bug fixes.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 1.0.3 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 1.0.3</span>
                        <span class="date-tag">June 28 2025</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Receipt import/export:</strong> Receipts are now included when importing or exporting spreadsheet data.</li>
                                <li><strong>Export currency selection:</strong> Choose which currency to use when exporting spreadsheets.</li>
                                <li><strong>Currency detection:</strong> Imported spreadsheets are automatically detected for their currency and converted to your default. You can also set it manually if needed.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Better import error handling:</strong> When importing a spreadsheet with bad data, you can now skip the row, retry it, or cancel the whole import.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li>Bug fixes and performance optimizations.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="documentation-link">
                        <p>
                            <strong>For more information about spreadsheet import/export,
                                <a class="link-no-underline" href="../documentation/#spreadsheet-import">visit our
                                    documentation</a></strong>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Version 1.0.2 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 1.0.2</span>
                        <span class="date-tag">June 14 2025</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Currency change loading indicator:</strong> Changing your default currency now shows a loading panel, and the currency symbol updates everywhere automatically.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>Live language switching:</strong> Changing languages now updates all open forms instantly, with no need to close and reopen them.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label fix">Fixes</h4>
                            <ul class="changelog-list">
                                <li><strong>Network check:</strong> Fixed the internet connection check failing on some public/restricted networks.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 1.0.1 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 1.0.1</span>
                        <span class="date-tag">May 10 2025</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <div class="changelog-section">
                            <h4 class="section-label feature">New Features</h4>
                            <ul class="changelog-list">
                                <li><strong>Chart zoom reset:</strong> You can now reset the zoom level on charts, plus improved zooming in general.</li>
                                <li><strong>Offline detection:</strong> The app now shows a clear message when you lose internet instead of confusing error dialogs.</li>
                                <li><strong>Cancel button on loading screens:</strong> You can now cancel out of long-running operations.</li>
                            </ul>
                        </div>
                        <div class="changelog-section">
                            <h4 class="section-label enhancement">Enhancements</h4>
                            <ul class="changelog-list">
                                <li><strong>.NET 9 upgrade:</strong> Migrated to .NET 9, which brings roughly 15&ndash;25% faster performance and 20&ndash;30% lower memory usage across the board.</li>
                                <li><strong>Chart performance:</strong> Separated chart instances for purchases and sales so they don't refresh unnecessarily when switching screens.</li>
                                <li><strong>Faster language switching:</strong> Translations are now downloaded in a single batch instead of multiple API calls.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version 1.0.0 -->
            <div class="version-card">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-tag">Version 1.0.0</span>
                        <span class="date-tag">May 1 2025</span>
                    </div>
                    <?= svg_icon('chevron-down', 24, 'dropdown-arrow', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>

                <div class="version-content">
                    <div class="changelog">
                        <p class="changelog-intro">First stable release of Argo Books.</p>
                        <ul class="changelog-list">
                            <li>Core sales tracking and product management</li>
                            <li>Analytics dashboard with interactive charts for revenue, expenses, and transaction data</li>
                            <li>Receipt manager with date range filtering and bulk selection</li>
                            <li>Spreadsheet import and export (.xlsx), compatible with Excel, Google Sheets, and LibreOffice</li>
                            <li>Multi-language support</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </main>

    <footer class="footer">
        <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
    </footer>
</body>

</html>