<?php
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../resources/format.php';
require_once __DIR__ . '/../track_referral.php';
require_once __DIR__ . '/../partials/fonts.php';

track_referral_event('downloads_page');

$hide_header_cta = true;
/**
 * macOS-only download-link suffix carrying the visitor's install token.
 *
 * Windows and Linux recover the token from the downloaded filename that
 * serveFile() builds in get_avalonia_installer.php: the Windows installer reads
 * it during install, and on Linux the AppImage *is* the executable, so the name
 * survives. On macOS the download is a .zip the user expands, and the extracted
 * "Argo Books.app" carries none of the archive's name, so the token is gone by
 * first run and the install reports as unattributed.
 *
 * Putting it in the query string instead means the browser records it as the
 * download's source URL, which macOS stores on the file as the
 * com.apple.metadata:kMDItemWhereFroms attribute. FirstRunReporter reads it back
 * from there. Nothing server-side consumes ?t; the token is still derived from
 * the cookie when the filename is built.
 */
function macInstallTokenQuery(): string
{
    $visitor_id = $_COOKIE[ARGO_VISITOR_COOKIE] ?? null;
    if (!$visitor_id || !preg_match('/^[0-9a-f-]{36}$/i', $visitor_id)) {
        return '';
    }
    $token = referral_install_token($visitor_id);
    return $token === '' ? '' : '?t=' . urlencode($token);
}

// Load system requirements from JSON
function getSystemRequirements()
{
    $jsonPath = '../resources/data/system-requirements.json';
    if (file_exists($jsonPath)) {
        $json = file_get_contents($jsonPath);
        return json_decode($json, true);
    }
    return [];
}

// Platform file patterns for Avalonia builds
$avaloniaPatterns = [
    'windows'     => 'Argo Books Installer V.{version}.exe',
    'macos-arm64' => 'ArgoBooks-{version}-osx-arm64.zip',
    'macos-x64'   => 'ArgoBooks-{version}-osx-x64.zip',
    'linux'       => 'ArgoBooks-{version}-linux-x64.AppImage',
];

// Get latest version information from filesystem
function getLatestVersion()
{
    $basePath = '../resources/downloads/';

    if (!is_dir($basePath)) {
        return null;
    }

    $versions = [];
    foreach (scandir($basePath) as $folder) {
        if ($folder === '.' || $folder === '..') continue;
        if (!is_dir($basePath . $folder)) continue;
        if (!preg_match('/^\d+\.\d+\.\d+/', $folder)) continue;
        $versions[] = $folder;
    }

    if (empty($versions)) {
        return null;
    }

    usort($versions, function ($a, $b) {
        return version_compare($b, $a);
    });

    $latest = $versions[0];

    // Gather per-platform file sizes
    global $avaloniaPatterns;
    $platforms = [];
    foreach ($avaloniaPatterns as $platform => $pattern) {
        $filename = str_replace('{version}', $latest, $pattern);
        $filepath = $basePath . $latest . '/' . $filename;
        if (file_exists($filepath)) {
            $platforms[$platform] = [
                'filename' => $filename,
                'filesize' => filesize($filepath),
            ];
        }
    }

    return [
        'version'   => $latest,
        'platforms' => $platforms,
    ];
}


$latestVersion = getLatestVersion();
$systemRequirements = getSystemRequirements();

// The SmartScreen guide config, detection, and markup live in the shared
// partial resources/smartscreen-guide/guide.php (also used by the paid
// landing pages); it's included below where the block renders.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Argo">

    <!-- SEO Meta Tags -->
    <!-- Platform wording here has to match the page. The title and description are what
         Google prints in the SERP and what link previews and crawlers read, so a Mac user
         arriving from search has to find the download the snippet promised. Which of the two
         Mac builds to take is the card's job, not the snippet's. -->
    <meta name="description"
        content="Download Argo Books free for Windows, macOS, and Linux. Simple bookkeeping software for small businesses, with easy invoicing, expense tracking, and financial reports.">
    <meta name="keywords"
        content="argo books download, bookkeeping software, Windows, macOS, Mac, Linux, free accounting software, small business software, invoice software">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Download Argo Books | Windows, macOS & Linux">
    <meta property="og:description"
        content="Download Argo Books for your platform. Free bookkeeping software with invoicing, expense tracking, and financial reports.">
    <meta property="og:url" content="https://argorobots.com/downloads/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Download Argo Books | Windows, macOS & Linux">
    <meta name="twitter:description"
        content="Download Argo Books for your platform. Free bookkeeping software with invoicing, expense tracking, and financial reports.">
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
    <link rel="canonical" href="https://argorobots.com/downloads/">

    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Download Argo Books | Windows, macOS & Linux</title>

    <script src="../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../resources/styles/smartscreen-guide.css">
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
            <h1>Download Argo Books</h1>
            <p>Get started for free. No account required.</p>
        </div>
    </section>

    <div class="container">
        <?php
        // One shape for every platform: a version tag when there is a build, then a button per
        // download with its own size on it. The size sits on the button rather than beside the
        // version because macOS ships two builds of different sizes, and a single figure up
        // there could only ever have been right for one of them.
        //
        // A build with no file in the version folder shows a disabled button rather than
        // vanishing. An absent button reads as an oversight and leaves someone wondering
        // whether they missed it; a button that says so answers the question. What it must not
        // do is link anywhere, which is what the Intel one did before that build existed.
        $platformCards = [
            [
                'class' => 'platform-windows',
                'name'  => 'Windows',
                'desc'  => 'For Windows 10 and later',
                'icon'  => svg_icon('windows'),
                'builds' => [
                    ['key' => 'windows', 'slug' => 'win', 'label' => 'Download for Windows'],
                ],
                'help' => null,
            ],
            [
                'class' => 'platform-macos',
                'name'  => 'macOS',
                'desc'  => 'For macOS 14 Sonoma and later',
                'icon'  => svg_icon('apple'),
                'builds' => [
                    ['key' => 'macos-arm64', 'slug' => 'mac-arm64', 'label' => 'Apple Silicon'],
                    ['key' => 'macos-x64',   'slug' => 'mac-intel', 'label' => 'Intel'],
                ],
                // Only worth asking when there are two answers. The browser cannot tell them
                // apart: Safari and Chrome both report an Intel user agent on Apple Silicon.
                'help' => ['id' => 'macInstallHelp', 'text' => 'Which one do I need?', 'min_builds' => 2],
                // macOS only: Windows and Linux recover the visitor's install token from the
                // downloaded filename, but a Mac expands the .zip and the extracted .app keeps
                // none of the archive's name. In the query string the browser records it as the
                // download's source URL, which macOS stores on the file for FirstRunReporter.
                'install_token' => true,
            ],
            [
                'class' => 'platform-linux',
                'name'  => 'Linux',
                'desc'  => 'Ubuntu, Debian, Fedora &amp; more (AppImage)',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="' . getPlatformIconPath('linux') . '"/></svg>',
                'builds' => [
                    ['key' => 'linux', 'slug' => 'linux', 'label' => 'Download for Linux'],
                ],
                'help' => ['id' => 'linuxInstallHelp', 'text' => 'Installation instructions', 'min_builds' => 1],
            ],
        ];
        ?>
        <div class="platform-grid">
            <?php foreach ($platformCards as $card): ?>
                <?php
                $available = array_values(array_filter(
                    $card['builds'],
                    fn($build) => isset($latestVersion['platforms'][$build['key']])
                ));
                ?>
                <div class="platform-card <?php echo $card['class']; ?>">
                    <div class="platform-icon"><?= $card['icon'] ?></div>
                    <div class="platform-info">
                        <h2><?php echo $card['name']; ?></h2>
                        <p class="platform-desc"><?= $card['desc'] ?></p>
                        <?php if ($available): ?>
                            <div class="version-details">
                                <span class="version-tag">V.<?php echo htmlspecialchars($latestVersion['version']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="platform-actions">
                        <?php foreach ($card['builds'] as $build): ?>
                            <?php $file = $latestVersion['platforms'][$build['key']] ?? null; ?>
                            <?php if ($file): ?>
                                <a href="../download/avalonia/<?php echo $build['slug'] . (!empty($card['install_token']) ? macInstallTokenQuery() : ''); ?>"
                                   class="btn btn-blue download-btn"
                                   data-platform="<?php echo $build['key']; ?>">
                                    <?= svg_icon('download', null, 'btn-icon') ?>
                                    <?php echo $build['label']; ?> &middot; <?php echo formatFileSize($file['filesize']); ?>
                                </a>
                            <?php else: ?>
                                <span class="btn btn-blue download-btn disabled" aria-disabled="true">
                                    <?= svg_icon('download', null, 'btn-icon') ?>
                                    <?php echo $build['label']; ?> &middot; Not available
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($card['help'] && count($card['builds']) >= $card['help']['min_builds']): ?>
                            <button type="button" class="install-help-link" id="<?php echo $card['help']['id']; ?>"><?php echo $card['help']['text']; ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Post-download walkthrough: the browser's "keep" steps (when the browser
             warns) followed by a final Windows launch step, as one continuous
             numbered list. Revealed after a Windows download click. -->
        <?php include __DIR__ . '/../resources/smartscreen-guide/guide.php'; ?>

        <!-- What you get after installing. Sits between the download buttons and the
             requirements because this is where the "should I run an unknown installer"
             hesitation lands, and the page had nothing but text to answer it. -->
        <div class="preview-section">
            <img class="preview-image"
                 src="../resources/images/laptop-coffee-800.webp"
                 srcset="../resources/images/laptop-coffee-800.webp 800w, ../resources/images/laptop-coffee-1200.webp 1200w, ../resources/images/laptop-coffee-1600.webp 1600w"
                 sizes="(max-width: 900px) 100vw, 560px"
                 width="1200" height="900"
                 alt="Argo Books running on a laptop, showing the dashboard with total revenue, expenses, outstanding invoices and recent transactions"
                 loading="lazy" decoding="async">
            <div class="preview-copy">
                <h2>What you get</h2>
                <p>Argo Books opens straight onto your dashboard: revenue, expenses, profit and everything still outstanding, in one place. There is no account to create and no trial clock. Install it, open it, and your books stay on your computer.</p>
            </div>
        </div>

        <!-- System Requirements -->
        <div class="requirements-section">
            <h2>System Requirements</h2>
            <div class="requirements-grid">
                <?php foreach ($systemRequirements as $platform => $data): ?>
                <div class="requirement-card">
                    <h3>
                        <svg viewBox="0 0 24 24" fill="currentColor" class="req-icon">
                            <path d="<?php echo getPlatformIconPath($platform); ?>"/>
                        </svg>
                        <?php echo htmlspecialchars($data['name']); ?>
                    </h3>
                    <ul>
                        <?php foreach ($data['requirements'] as $req): ?>
                        <li><?php echo htmlspecialchars($req); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Additional Links -->
        <div class="additional-section">
            <div class="additional-card">
                <div class="additional-icon">
                    <?= svg_icon('clock') ?>
                </div>
                <div class="additional-content">
                    <h3>Looking for older versions?</h3>
                    <p>Access previous releases of Argo Books for compatibility or testing purposes.</p>
                    <a href="../older-versions/" class="link-arrow">
                        View older versions
                        <?= svg_icon('arrow-right-sm') ?>
                    </a>
                </div>
            </div>
            <div class="additional-card">
                <div class="additional-icon">
                    <?= svg_icon('document-lines') ?>
                </div>
                <div class="additional-content">
                    <h3>Need help getting started?</h3>
                    <p>Check out our documentation for installation guides and tutorials.</p>
                    <a href="../documentation/" class="link-arrow">
                        View documentation
                        <?= svg_icon('arrow-right-sm') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    </main>

    <!-- Which Mac build? The Apple menu is the only place a user can check, and the
         browser cannot answer it for them. -->
    <div class="install-modal" id="macInstallModal">
        <div class="install-modal-backdrop"></div>
        <div class="install-modal-content" role="dialog" aria-modal="true" aria-labelledby="macInstallModalTitle">
            <button class="install-modal-close" aria-label="Close">&times;</button>
            <h2 id="macInstallModalTitle">Apple Silicon or Intel?</h2>
            <ol class="install-modal-steps">
                <li>Open the <strong>Apple menu</strong> in the top-left corner of your screen.</li>
                <li>Choose <strong>About This Mac</strong>.</li>
                <li>Look at the <strong>Chip</strong> or <strong>Processor</strong> line.</li>
                <li>A chip starting with <strong>Apple</strong> (M1, M2, M3, M4) means Apple Silicon. Anything listing an <strong>Intel</strong> processor means Intel.</li>
            </ol>
            <p class="install-modal-alt">Every Mac sold since late 2020 is Apple Silicon.</p>
            <p class="install-modal-note">Downloaded the wrong one? It simply won't open, and nothing is installed. Grab the other build instead. See the <a href="../documentation/pages/getting-started/installation.php">full installation guide</a> for more.</p>
        </div>
    </div>

    <!-- Linux installation instructions modal -->
    <div class="install-modal" id="linuxInstallModal">
        <div class="install-modal-backdrop"></div>
        <div class="install-modal-content" role="dialog" aria-modal="true" aria-labelledby="linuxInstallModalTitle">
            <button class="install-modal-close" aria-label="Close">&times;</button>
            <h2 id="linuxInstallModalTitle">Installing on Linux</h2>
            <ol class="install-modal-steps">
                <li>Download the AppImage file.</li>
                <li>Right-click the downloaded file and choose <strong>Properties</strong>.</li>
                <li>In the <strong>Permissions</strong> tab, check <strong>"Allow executing file as program"</strong> (the wording varies slightly between distros).</li>
                <li>Double-click the file to launch Argo Books.</li>
            </ol>
            <p class="install-modal-alt">Prefer the terminal? Run <code>chmod +x ArgoBooks-<?php echo $latestVersion ? htmlspecialchars($latestVersion['version']) : 'X.X.X'; ?>-linux-x64.AppImage</code> instead.</p>
            <p class="install-modal-note">AppImages are self-contained: there's nothing else to install, and you can keep the file anywhere you like. See the <a href="../documentation/pages/getting-started/installation.php">full installation guide</a> for more.</p>
        </div>
    </div>

    <footer class="footer">
        <?php include __DIR__ . '/../resources/footer/footer.php'; ?>
    </footer>

    <script>
        const downloadGuides = document.getElementById('downloadGuides');

        // Add download tracking + reveal SmartScreen guide for Windows downloads
        document.querySelectorAll('.download-btn:not(.disabled)').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                const platform = this.getAttribute('data-platform');
                if (platform && typeof gtag !== 'undefined') {
                    gtag('event', 'download_click', {
                        'event_category': 'software',
                        'event_label': 'argo_books_' + platform,
                        'platform': platform
                    });

                    // Google Ads conversion: fire on the download click
                    gtag('event', 'conversion', {'send_to': 'AW-17210317271/niGZCJv2vbkbENezwo5A'});
                }

                if (platform === 'windows' && downloadGuides) {
                    downloadGuides.hidden = false;
                    requestAnimationFrame(function() {
                        downloadGuides.querySelectorAll('.smartscreen-guide')
                            .forEach(function(g) { g.classList.add('is-visible'); });
                        setTimeout(function() {
                            const targetY = downloadGuides.getBoundingClientRect().top
                                + window.pageYOffset - 130;
                            window.scrollTo({ top: targetY, behavior: 'smooth' });
                        }, 120);
                    });
                }
            });
        });

        // Install-help modals: Linux permissions, and which Mac build to take.
        function wireInstallModal(triggerId, modalId) {
            const modal = document.getElementById(modalId);
            const trigger = document.getElementById(triggerId);
            if (!modal || !trigger) return;

            function close() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }

            trigger.addEventListener('click', function() {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
            modal.querySelector('.install-modal-close').addEventListener('click', close);
            modal.querySelector('.install-modal-backdrop').addEventListener('click', close);
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    close();
                }
            });
        }

        wireInstallModal('linuxInstallHelp', 'linuxInstallModal');
        wireInstallModal('macInstallHelp', 'macInstallModal');
    </script>
</body>

</html>
