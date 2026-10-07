<?php
/**
 * SmartScreen / download-warning walkthrough, shared by the downloads page and
 * the paid landing pages.
 *
 * Renders the hidden #downloadGuides block: the browser's "keep the download"
 * steps (when we have an illustrated guide for it) followed by the Windows
 * launch step, as one continuous numbered list. The including page's JS
 * un-hides it after a Windows download click.
 *
 * Include where the block should appear in the DOM. Optional variable before
 * including:
 *   $guide_asset_base (string) path prefix to the site root, default '../'
 *     (both the downloads page and the for-... landing pages sit one level deep).
 *
 * Styles: resources/styles/smartscreen-guide.css (link it in <head>).
 */

require_once __DIR__ . '/../icons.php';

if (!function_exists('detectBrowserForGuide')) {
    /** Detect browser for the SmartScreen guide (Windows downloads only). */
    function detectBrowserForGuide(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $chUa = $_SERVER['HTTP_SEC_CH_UA'] ?? '';

        if (stripos($chUa, 'Brave') !== false) return 'brave';
        if (stripos($ua, 'Edg/') !== false) return 'edge';
        if (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera/') !== false) return 'opera';
        if (stripos($ua, 'Vivaldi/') !== false) return 'vivaldi';
        if (stripos($ua, 'Firefox/') !== false) return 'firefox';
        if (stripos($ua, 'Chrome/') !== false) return 'chrome';
        return 'unknown';
    }
}

$guide_asset_base = $guide_asset_base ?? '../';

// Add a browser by dropping a key here and saving screenshots to resources/images/smartscreen- guide/<browser>-step-N.svg.
$smartScreenGuides = [
    'edge' => [
        'browser_name' => 'Microsoft Edge',
        'intro' => 'Edge sometimes flags new apps before they are widely downloaded. Here\'s how to keep the installer:',
        'steps' => [
            [
                'title' => 'Open the Downloads panel, hover the file, and click the ⋯ menu',
                'image' => $guide_asset_base . 'resources/images/smartscreen-guide/edge-step-1.svg',
                'alt'   => 'Edge Downloads panel showing the Argo Books installer with the more-options menu',
            ],
            [
                'title' => 'Choose Keep from the menu',
                'image' => $guide_asset_base . 'resources/images/smartscreen-guide/edge-step-2.svg',
                'alt'   => 'Edge download menu with the Keep option highlighted',
            ],
            [
                'title' => 'Click the arrow next to Delete, then choose Keep anyway',
                'image' => $guide_asset_base . 'resources/images/smartscreen-guide/edge-step-3.svg',
                'alt'   => 'Edge confirmation dialog with the Keep anyway button',
            ],
        ],
    ],
];

// Windows launch step. Appended as the final step of the walkthrough (after the browser's "keep" steps), because the "Windows protected your PC" prompt appears when the installer is opened.
$windowsLaunchStep = [
    'title' => 'Open the installer. If Windows shows "Windows protected your PC", click More info, then Run anyway.',
    'image' => $guide_asset_base . 'resources/images/smartscreen-guide/windows-step.svg',
    'alt'   => 'Windows protected your PC dialog showing Argo Books Installer, publisher Evan Di Placido, with the Run anyway button highlighted',
];

$browserKey = detectBrowserForGuide();
$smartScreenGuide = $smartScreenGuides[$browserKey] ?? null;

if ($smartScreenGuide) {
    $guideHeading = $smartScreenGuide['browser_name'] . ' has an extra confirmation step';
    $guideIntro   = $smartScreenGuide['intro'];
    $guideSteps   = $smartScreenGuide['steps'];
} else {
    // Browser without a keep-guide: still show the Windows launch step alone.
    $guideHeading = 'Opening Argo Books on Windows';
    $guideIntro   = 'Windows may warn you because Argo Books is a newer app. After the download finishes, here is the last step to open it:';
    $guideSteps   = [];
}
// Append the Windows launch step as the final numbered step.
$guideSteps[] = $windowsLaunchStep;
?>
<div class="download-guides" id="downloadGuides" data-browser="<?php echo htmlspecialchars($browserKey); ?>" style="--step-count: <?php echo count($guideSteps); ?>;" hidden>
    <div class="smartscreen-guide">
        <div class="smartscreen-guide-header">
            <div class="smartscreen-status">
                <?= svg_icon('check', 16) ?>
                <span>Your download is starting</span>
            </div>
            <h2><?php echo htmlspecialchars($guideHeading); ?></h2>
            <p><?php echo htmlspecialchars($guideIntro); ?></p>
        </div>
        <ol class="smartscreen-steps" style="--step-count: <?php echo count($guideSteps); ?>;">
            <?php foreach ($guideSteps as $i => $step): ?>
            <li class="smartscreen-step">
                <?php if (count($guideSteps) > 1): ?>
                <div class="smartscreen-step-number"><?php echo $i + 1; ?></div>
                <?php endif; ?>
                <p class="smartscreen-step-title"><?php echo htmlspecialchars($step['title']); ?></p>
                <?php if (!empty($step['image'])): ?>
                <div class="smartscreen-step-image">
                    <img src="<?php echo htmlspecialchars($step['image']); ?>" alt="<?php echo htmlspecialchars($step['alt'] ?? ''); ?>" loading="lazy">
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</div>
