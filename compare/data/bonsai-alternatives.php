<?php
// compare/data/bonsai-alternatives.php
//
// Content for /compare/bonsai-alternatives/. The layout lives in
// compare/compare-page.php; everything that makes this page itself is here.
//
// The FAQ entries feed both the visible accordion and the FAQPage JSON-LD, so
// the two cannot drift apart the way the hand-written pairs used to.

if (!defined('ARGO_TEMPLATE_RENDER')) {
    http_response_code(404);
    exit;
}

// Competitor pricing, also read by compare/mockups/bonsai-alternatives.php.
$bonsai_essentials = competitor_price('bonsai', 'essentials'); // 25 CAD per user
$bonsai_premium    = competitor_price('bonsai', 'premium');    // 39 CAD per user

return [
    'competitor' => 'Bonsai',

    'breadcrumb' => 'Bonsai alternatives',
    'title' => 'Bonsai Alternatives for Freelancers: Flat Pricing | Argo Books',
    'meta_description' => 'Bonsai alternatives for freelancers who want one flat price instead of per-user billing. Compare invoicing, expense tracking and real bookkeeping side by side.',
    'meta_keywords' => 'Bonsai alternatives, Bonsai alternative, freelance accounting software, freelancer invoicing software, flat price accounting software',
    'og_title' => 'Bonsai Alternatives: Flat Price, Not Per Seat',
    'og_description' => 'Bonsai bills per user and stops at the client workflow. Here are the alternatives that are one flat price and keep your actual books.',

    'hero_h1' => 'Bonsai alternatives',
    'hero_subtitle' => 'Bonsai bills per user, and invoicing only starts on its middle tier. Argo Books is one price, with the books included.',

    'differences_h2' => 'What\'s the difference between Argo Books and Bonsai?',
    'differences_desc' => 'Bonsai is a freelancer workspace: proposals, contracts, time tracking and client management, with invoicing layered on. Argo Books is bookkeeping software. The pricing shape differs too: Bonsai charges per user every month, Argo Books charges once per business.',
    'why_h3' => 'What changes when you move from Bonsai to Argo Books',
    'why_list' => [
        '<strong>One price, not one price per person.</strong> Bonsai bills per user, so a second person doubles your cost. Argo Books Premium is $' . $argo_monthly . ' CAD/month regardless of headcount.',
        '<strong>Invoicing is not an upgrade.</strong> Bonsai\'s Basic tier has no invoicing at all, so the real comparison starts at Essentials. Argo includes invoicing on the free plan.',
        '<strong>Your actual books.</strong> Expenses, receipts, inventory, financial reports and forecasting, where Bonsai focuses on client and project management.',
        '<strong>Yours, and offline.</strong> A native desktop app for Windows, macOS, and Linux. Your records open with no internet, and your data stays on your machine.',
        '<strong>Priced in CAD.</strong> Bonsai publishes in US dollars, so what a Canadian actually pays moves with the exchange rate.',
    ],

    // Feature, Argo Free, Argo Premium, Bonsai.
    // 'yes' and 'no' render the tick and cross; any other string is a grey pill.
    'table_argo_sub' => '$' . $argo_monthly . ' CAD/month',
    'table_competitor_sub' => 'Essentials: $' . $bonsai_essentials . ' CAD/user',
    'table_rows' => [
        ['Expense & revenue tracking', 'yes', 'yes', 'Limited'],
        ['Financial reports', 'yes', 'yes', 'Limited'],
        ['Invoicing & payments', 'yes', 'yes', 'yes'],
        ['Desktop app (offline-capable)', 'yes', 'yes', 'no'],
        ['No accounting knowledge required', 'yes', 'yes', 'yes'],
        ['Unlimited products', 'yes', 'yes', 'yes'],
        ['Inventory management', 'yes', 'yes', 'no'],
        ['AI receipt scanning', 'yes', 'yes', 'no'],
        ['AI spreadsheet import', 'yes', 'yes', 'no'],
        ['Predictive analytics', 'no', 'yes', 'no'],
        ['Biometric login security', 'no', 'yes', 'no'],
        ['Local data storage', 'yes', 'yes', 'no'],
    ],

    'pros_cons_h2' => 'Argo Books vs Bonsai: pros &amp; cons',
    'argo_pros' => [
        '<strong>Free forever plan</strong> with every core feature, no trial and no credit card',
        '<strong>One flat price</strong> per business rather than per user, so adding people costs nothing',
        '<strong>Works offline</strong> as a native desktop app for Windows, macOS, and Linux, with your data stored locally',
        '<strong>Full bookkeeping</strong>: expenses, inventory, reports and forecasting, plus AI receipt scanning',
        '<strong>Priced in CAD</strong> at $' . $argo_monthly . '/month, so the amount never moves with the exchange rate',
    ],
    'argo_cons' => [
        'No proposals, contracts or e-signing, which is a core part of Bonsai',
        'No built-in time tracking or CRM',
        'Desktop-first, so there\'s no browser or mobile-web access the way a cloud tool offers',
    ],
    'competitor_cons' => [
        '<strong>Per-user pricing</strong>, so costs scale with headcount rather than staying flat',
        '<strong>No invoicing on the Basic tier</strong>, so the entry price is not the real price',
        '<strong>Cloud-only</strong>, with no offline access and your data on their servers',
        '<strong>Limited bookkeeping</strong>: no inventory, no AI receipt scanning, no forecasting',
    ],
    'competitor_pros' => [
        'Proposals, contracts and e-signing built in',
        'Time tracking and task management for project work',
        'Client CRM and scheduling in the same tool',
        'Strong fit for freelancers whose work is project-shaped',
    ],

    'key_h2' => 'One price, and the books to go with it',
    'key_desc' => 'Bonsai is a good client-and-project workspace. It is not bookkeeping software, and its per-user billing means the price you see is per person, per month, before invoicing is even included.',
    'key_cards' => [
        ['icon' => 'dollar', 'h3' => 'Flat pricing, in CAD', 'p' => 'Bonsai Essentials is $' . $bonsai_essentials . ' CAD per user per month. Argo Books Premium is $' . $argo_monthly . ' CAD/month for the business, however many people use it.'],
        ['icon' => 'bolt', 'h3' => 'Works offline', 'p' => 'Bonsai is cloud-only. Argo Books is a desktop app that works without a connection, with your data stored locally on your device.'],
        ['icon' => 'map-pin', 'h3' => 'Made in Canada', 'p' => 'Built in Saskatchewan for Canadian small businesses, with pricing in CAD.'],
    ],

    'honest' => [
        'Bonsai is genuinely good at what it is for. Proposals, contracts, e-signing, time tracking and a client CRM in one place is a real workflow for freelancers, and Argo Books does not do any of that. If your work is project-shaped and the paperwork around winning clients is the painful part, Bonsai earns its price.',
        'It is not bookkeeping software though. There is no inventory, no AI receipt scanning, no forecasting, and its cheapest tier does not include invoicing at all, so the entry price is misleading. Add per-user billing in US dollars and a two-person shop is paying several times what Argo Books costs, for less of the actual accounting.',
    ],


    // Ordered; labels come from argo_compare_index() in compare/compare-lib.php.
    'related' => [
        'argo-books-vs-quickbooks',
        'argo-books-vs-wave',
        'argo-books-vs-freshbooks',
        'argo-books-vs-xero',
        'zipbooks-alternatives',
    ],

    'faqs' => [
        ['q_html' => 'How much does Bonsai cost?', 'a_html' => '<p>Bonsai\'s plans are priced per user per month, starting at Basic and rising through Essentials at $' . $bonsai_essentials . ' CAD, Premium at $' . $bonsai_premium . ' CAD, and Elite. Invoicing starts at the Essentials tier.</p>
                            <p>Argo Books Premium is $' . $argo_monthly . ' CAD/month for the whole business, regardless of how many people use it.</p>'],
        ['q_html' => 'Does Bonsai do bookkeeping?', 'a_html' => '<p>Bonsai includes basic expense and income tracking from the Essentials tier, but it is a client and project workspace rather than accounting software.</p>
                            <p>It has no inventory management, no AI receipt scanning and no financial forecasting.</p>'],
        ['q_html' => 'Does Argo Books have proposals and contracts?', 'a_html' => '<p>No. Argo Books is bookkeeping software: invoicing, expenses, receipts, inventory, reports and forecasting. Proposals, contracts and e-signing are Bonsai\'s strength, not ours.</p>
                            <p>Some businesses use both, with Bonsai for winning work and Argo Books for the books.</p>'],
        ['q_html' => 'Does Argo Books work offline?', 'a_html' => '<p>Yes. Argo Books is a desktop application that runs natively on your computer, so it works even without an internet connection. Your data is stored locally with AES-256 encryption.</p>
                            <p>Bonsai is cloud-based and needs a connection.</p>'],
        ['q_html' => 'Is Argo Books cheaper for a team?', 'a_html' => '<p>Yes, and the gap widens with headcount. Bonsai charges per user, so a three-person business pays three times its listed price. Argo Books Premium is one flat $' . $argo_monthly . ' CAD/month.</p>'],
    ],

    'cta_h2' => 'Ready for flat pricing and real books?',
    'cta_p' => 'Download Argo Books for free and see the difference for yourself.',
];
