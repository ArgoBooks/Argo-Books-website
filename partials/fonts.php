<?php

/**
 * Web font <link> tags for the public site.
 *
 * One copy of the preconnect pair and the Google Fonts URL for the whole site.
 * Per-page copies drift apart, and then changing a typeface or a weight means
 * finding and editing more than fifty <head> blocks, and missing one showed up
 * as a page rendering in a fallback face.
 *
 * The four sets below are the ones that were actually in use, kept apart on
 * purpose. Merging them would either ship italic and monospace faces to pages
 * that never ask for them, or drop faces from the pages that do. Pick the set a
 * page needs; add a new one here rather than inlining a URL in a page.
 *
 * The preconnects matter: without them the browser cannot open the connection
 * to fonts.gstatic.com until it has parsed the stylesheet from
 * fonts.googleapis.com. The project's .htaccess CSP already allows both hosts.
 */

/** Google Fonts stylesheet URL per set, as an HTML attribute value. */
function argo_font_sets(): array
{
    return [
        // Fraunces display + IBM Plex Sans body. The site default.
        'default' => 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap',

        // Adds Fraunces italic and IBM Plex Mono, for pages that set code and
        // pull-quotes: the feature and integration pages.
        'editorial' => 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&amp;family=IBM+Plex+Mono:wght@400;500;600&amp;family=IBM+Plex+Sans:wght@400;500;600;700&amp;display=swap',

        // Heavier Fraunces only, plus Plex Mono for the payout figures.
        'dashboard' => 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600;700&display=swap',

        // Lighter Fraunces on its own, for the long-form guide hub.
        'editorial-light' => 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500;9..144,600&display=swap',
    ];
}

/**
 * The preconnect pair and the stylesheet link for one set.
 *
 * $indent is the leading whitespace to put before the second and third tags, so
 * the emitted markup lines up with the rest of the <head> it lands in.
 */
function argo_font_links(string $set = 'default', string $indent = '    '): string
{
    $sets = argo_font_sets();
    $href = $sets[$set] ?? $sets['default'];

    return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
        . $indent . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
        . $indent . '<link rel="stylesheet" href="' . $href . '">';
}
