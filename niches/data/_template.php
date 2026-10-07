<?php
// niches/data/_template.php
//
// SCHEMA DOCUMENTATION for niche page data files.
//
// Each niche page is driven by a data file at niches/data/{slug}.php that
// returns an array matching the structure below. The slug must match the
// filename and must be lowercase letters, digits, and hyphens only:
// `[a-z0-9-]+`.
//
// To add a new niche page:
//   1. Copy this file to niches/data/{your-slug}.php.
//   2. Fill in every required key. The only optional field is
//      `related_template_slugs`, which may be left as an empty array or
//      omitted entirely. Every other key must be filled in; the template
//      assumes they are all present.
//   3. Reference the new slug from at least 3 other niche data files via
//      `related_slugs`. The niche template prints a dev-mode warning when
//      a page has fewer than 3 outbound links.
//
// Style rules (project-wide, see CLAUDE.md):
//   - No em dashes. Use commas, colons, or periods.
//   - No emojis.
//   - Plain language. No jargon. No fluff.
//   - HTML fields hold trusted author markup. Niches may embed <p>, <ul>,
//     <ol>, <strong>, <em>, <a>. The niche template echoes them verbatim.
//   - Question and answer strings inside `faqs` are plain text. The
//     template renders the question inside <h3> and the answer inside <p>,
//     so do not wrap them in tags.

return [

  // URL slug. Lowercase letters, digits, hyphens. Must match filename. The generic landing page uses slug "generic" and is served at /free-invoice-generator/ without the slug in the URL.
  'slug' => 'generic',

  // The single visible <h1> on the page. Should contain the target keyword.
  // Keep under ~60 characters so it does not wrap awkwardly on mobile.
  'h1' => 'Free Invoice Generator',

  // <title>. Aim for 50 to 60 characters. Include the brand suffix.
  'meta_title' => 'Free Invoice Generator | Argo Books',

  // <meta name="description">. Aim for 140 to 160 characters. Lead with
  // the value, not the brand.
  'meta_description' => 'Free online invoice generator. No signup required. Download PDF or Word.',

  // Trusted HTML for the intro section. 100 to 200 words. Explain what the tool does and who it is for. Niche pages should reference the niche explicitly (for example, "for plumbers").
  'intro_html' => '<p>Replace this with 100 to 200 words of niche-specific intro copy.</p>',

  // 3 to 5 example line items, all niche-appropriate. Used to populate the "Sample line items" section and (in Phase B) to prefill the generator when arriving from this niche.
  'sample_line_items' => [
    ['description' => 'Web design (10 hours)', 'rate' => 80, 'quantity' => 10],
    // Add more entries here.
  ],

  // Trusted HTML for the "Typical payment terms" section. Cover the common terms for this niche (Net 7, Net 15, Net 30, deposit structure, late fees, accepted payment methods).
  'typical_payment_terms_html' => '<p>Most clients use Net 30 terms.</p>',

  // Trusted HTML for the "Tax notes" section. Cover sales tax / GST /
  // VAT considerations relevant to this niche. Do not give legal advice.
  'tax_notes_html' => '<p>Set the tax rate in the generator. The tool does not file taxes for you.</p>',

  // 4 to 6 FAQ pairs. The niche template renders them as <h3>question</h3> followed by <p>answer</p>, and also serializes them into FAQPage JSON-LD for rich results.
  'faqs' => [
    [
      'q' => 'When should I send an invoice?',
      'a' => 'After the work is complete or per your agreed milestones.',
    ],
    // Add 3 to 5 more entries.
  ],

  // Related niche slugs, rendered as links to /free-invoice-generator/{slug}/. Fewer than three triggers a dev-mode warning.
  'related_slugs' => [
    // 'contractors',
    // 'consultants',
  ],

  // Optional slugs of related /invoice-template/ pages.
  'related_template_slugs' => [],

  // Conversion CTA shown at the bottom of the page. Plain text.
  'cta_text' => 'If you want to handle payments, refunds, and track everything, use Argo Books.',

  // ISO 3166-1 alpha-2 for hreflang grouping, where null means the page is not specific to a country.
  'country' => null,

  // Groups country variants. Pages with the same `concept` and different `country` codes alternate- reference each other via hreflang. Keep 'invoice-generator' for free-invoice-generator pages.
  'concept' => 'invoice-generator',

  // Optional pre-fill for the embedded generator. Set to null to leave the generator in its empty state on first visit.
  'generator_defaults' => null,
];
