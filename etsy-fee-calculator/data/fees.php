<?php
// etsy-fee-calculator/data/fees.php
//
// Single source of truth for every Etsy fee rate used on the page. index.php
// renders the country comparison table from this array AND emits it to the
// client as window.ETSY_FEES, so the visible table and the calculator can never
// disagree.
//
// Rates verified 2026-07-30. When Etsy changes a rate, edit here only, then bump
// 'verified'. The page prints that date so visitors can see how current it is.
//
// Sources:
//   https://www.etsy.com/legal/fees/
//   https://help.etsy.com/hc/en-us/articles/360000343968-Payment-Processing-Fees
//   https://help.etsy.com/hc/en-us/articles/360000338367-How-Etsy-s-Offsite-Ads-Work

return [
    'verified' => '2026-07-30',

    // Charged identically regardless of where the shop is based.
    'shared' => [
        // Etsy bills the listing fee in USD, so sellers outside the US see a converted amount on their bill. The field is editable on the page for exactly that reason.
        'listing_fee'             => 0.20,
        'transaction_pct'         => 0.065,   // of item price + shipping charged + gift wrap
        'offsite_ads_under'       => 0.15,    // under $10k in trailing-365-day sales
        'offsite_ads_over'        => 0.12,    // at or above $10k (participation becomes mandatory)
        'offsite_ads_threshold'   => 10000,
        'offsite_ads_cap'         => 100.00,  // $100 USD maximum per order
        'currency_conversion_pct' => 0.025,   // when listing currency != payout currency
    ],

    // Payment processing and the regulatory operating fee are set by the
    // seller's country. 'processing_note' documents what the numbers cover.
    'countries' => [
        'US' => [
            'name'            => 'United States',
            'currency'        => 'USD',
            'symbol'          => '$',
            'locale'          => 'en-US',
            'processing_pct'  => 0.030,
            'processing_flat' => 0.25,
            'regulatory_pct'  => 0.0,
            'note'            => null,
        ],
        'CA' => [
            'name'            => 'Canada',
            'currency'        => 'CAD',
            'symbol'          => '$',
            'locale'          => 'en-CA',
            'processing_pct'  => 0.030,
            'processing_flat' => 0.25,
            'regulatory_pct'  => 0.0115,
            'note'            => 'Canada carries a 1.15% regulatory operating fee on the full order total.',
        ],
        'GB' => [
            'name'            => 'United Kingdom',
            'currency'        => 'GBP',
            'symbol'          => '£',
            'locale'          => 'en-GB',
            'processing_pct'  => 0.040,
            'processing_flat' => 0.20,
            'regulatory_pct'  => 0.0032,
            'note'            => 'UK sellers pay a higher processing rate than the US, plus a 0.32% regulatory operating fee.',
        ],
        'AU' => [
            'name'            => 'Australia',
            'currency'        => 'AUD',
            'symbol'          => '$',
            'locale'          => 'en-AU',
            'processing_pct'  => 0.030,
            'processing_flat' => 0.25,
            'regulatory_pct'  => 0.0,
            'note'            => 'Etsy adds 10% GST to its service fees for Australian sellers. Sellers registered for GST claim that back on their BAS, so it is not counted as a cost here.',
        ],
    ],
];
