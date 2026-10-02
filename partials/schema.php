<?php
// partials/schema.php
//
// Builders for the JSON-LD every page emits. The BreadcrumbList in particular
// was hand-written in 47 files, each repeating the same @context, @type,
// position numbering, and absolute-URL construction.
//
// Usage:
//   require_once __DIR__ . '/../partials/schema.php';
//   echo argo_breadcrumb_schema([
//       'Home'      => '/',
//       'Features'  => '/features/',
//       'Invoicing' => '/features/invoicing/',
//   ]);
//
// Keys are the crumb labels, values are site-relative paths (or absolute URLs,
// which are passed through). Positions are numbered from the array order, so a
// crumb cannot be inserted without the numbering following it.
//
// See read-me/Tool-page-standards.md for which schema each page type carries.

const ARGO_SITE_URL = 'https://argorobots.com';

/** Absolute canonical URL for a site-relative path. Absolute input passes through. */
function argo_abs_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return ARGO_SITE_URL . '/' . ltrim($path, '/');
}

/** The BreadcrumbList node, for embedding in a schema @graph. */
function argo_breadcrumb_node(array $crumbs): array
{
    $items = [];
    $position = 1;
    foreach ($crumbs as $name => $path) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $name,
            'item' => argo_abs_url((string)$path),
        ];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/** BreadcrumbList as its own JSON-LD document, ready to echo inside a script tag. */
function argo_breadcrumb_schema(array $crumbs): string
{
    return json_encode(
        ['@context' => 'https://schema.org'] + argo_breadcrumb_node($crumbs),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}
