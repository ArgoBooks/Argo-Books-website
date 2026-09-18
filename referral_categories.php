<?php
/**
 * Referral link categories. Each referral_links row stores one of these keys in
 * its category column, picked in the admin Create/Edit modal. The order here is
 * the order the admin page lists them in.
 */
function referral_categories(): array
{
    return [
        'paid'      => 'Paid ads',
        'youtube'   => 'YouTube',
        'social'    => 'Social media',
        'website'   => 'Website',
        'invgen'    => 'Invoice generator',
        'loop'      => 'Growth loops',
        'outreach'  => 'Outreach',
        'ai'        => 'AI assistants',
        'directory' => 'Directories',
        'affiliate' => 'Affiliates',
        'other'     => 'Other',
    ];
}

/** A stored key if it is one we know, otherwise 'other'. */
function referral_category_or_other(?string $category): string
{
    return isset(referral_categories()[$category ?? '']) ? $category : 'other';
}

/**
 * Starting category for a link the site creates on its own (auto-detected
 * referrers, affiliates), guessed from the source code. Only a default: the
 * admin can change it in the modal, and the stored value is what counts.
 */
function referral_default_category(string $source_code): string
{
    $code = strtolower($source_code);
    $prefixes = [
        'google-ads-' => 'paid', 'bing-ads-' => 'paid', 'ads-' => 'paid', 'paid-' => 'paid',
        'guide-' => 'website', 'invgen-' => 'invgen', 'loop-' => 'loop',
        'outreach-' => 'outreach', 'social-' => 'social', 'youtube-' => 'youtube',
        'ai-' => 'ai', 'dir-' => 'directory', 'aff-' => 'affiliate',
    ];
    foreach ($prefixes as $prefix => $category) {
        if (strncmp($code, $prefix, strlen($prefix)) === 0) {
            return $category;
        }
    }
    return $code === 'guides-hub' ? 'website' : 'other';
}

/** How a link reads outside its own table: "YouTube - Desktop accounting". */
function referral_display_name(?string $category, ?string $name, string $source_code = ''): string
{
    $name = trim((string)$name) !== '' ? $name : $source_code;
    return referral_categories()[referral_category_or_other($category)] . ' - ' . $name;
}
