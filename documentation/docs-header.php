<?php
require_once __DIR__ . '/../resources/icons.php';
/**
 * Documentation Page Header Component
 *
 * Usage: Set $pageTitle, $pageDescription, $currentPage, and $pageCategory before including
 */

// Default values
if (!isset($pageTitle)) {
    $pageTitle = 'Documentation';
}
if (!isset($pageDescription)) {
    $pageDescription = 'Argo Books documentation and user guide.';
}

// Determine base path for resources based on page location
$resourcePath = isset($pageCategory) ? '../../../' : '../';
$docsPath = isset($pageCategory) ? '../../' : '';

$fullTitle = $pageTitle . ' - Argo Books Documentation';

// Each sub-page points at itself, so search engines are not told the index is the canonical page for all of them.
$canonicalUrl = 'https://argorobots.com/documentation/';
if (isset($pageCategory) && isset($currentPage)) {
    $canonicalUrl = 'https://argorobots.com/documentation/pages/'
        . $pageCategory . '/' . $currentPage . '.php';
}

// Category display names and colors
$categoryInfo = [
    'getting-started' => ['name' => 'Getting Started', 'color' => 'emerald'],
    'features' => ['name' => 'Core Features', 'color' => 'blue'],
    'integrations' => ['name' => 'Integrations', 'color' => 'sky'],
    'api' => ['name' => 'Developer API', 'color' => 'blue'],
    'reference' => ['name' => 'Reference', 'color' => 'amber'],
    'security' => ['name' => 'Security', 'color' => 'purple']
];

$currentCategory = $categoryInfo[$pageCategory] ?? ['name' => 'Documentation', 'color' => 'blue'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Argo">

    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="keywords"
        content="argo books documentation, argo books tutorial, business software guide, <?php echo htmlspecialchars(strtolower($pageTitle)); ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($fullTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Argo Books">
    <meta property="og:locale" content="en_CA">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($fullTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($pageDescription); ?>">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">

    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $resourcePath; ?>resources/images/argo-logo/argo-icon.ico">
    <title><?php echo htmlspecialchars($fullTitle); ?></title>

    <script src="<?php echo $resourcePath; ?>resources/scripts/main.js"></script>
    <script src="<?php echo $resourcePath; ?>resources/scripts/levenshtein.js"></script>
    <script src="<?php echo $resourcePath; ?>resources/scripts/site-search.js"></script>
    <script src="<?php echo $docsPath; ?>search.js"></script>
    <script src="<?php echo $docsPath; ?>main.js" defer></script>
    <script src="<?php echo $resourcePath; ?>resources/scripts/code-block.js" defer></script>

    <link rel="stylesheet" href="<?php echo $docsPath; ?>style.css">
    <link rel="stylesheet" href="<?php echo $resourcePath; ?>resources/styles/site-search.css">
    <link rel="stylesheet" href="<?php echo $resourcePath; ?>resources/styles/custom-colors.css">
    <link rel="stylesheet" href="<?php echo $resourcePath; ?>resources/styles/link.css">
    <link rel="stylesheet" href="<?php echo $resourcePath; ?>resources/styles/button.css">
    <link rel="stylesheet" href="<?php echo $resourcePath; ?>resources/header/style.css">
    <link rel="stylesheet" href="<?php echo $resourcePath; ?>resources/footer/style.css">
</head>

<body class="docs-page">
    <header>
        <?php include __DIR__ . '/../resources/header/header.php'; ?>
    </header>

    <div class="docs-layout">
        <!-- Sidebar -->
        <?php include $docsPath . 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="docs-main-content">
            <!-- Page Title -->
            <h1 class="docs-page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
