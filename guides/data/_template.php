<?php
// guides/data/_template.php
//
// SCHEMA DOCUMENTATION for article data files.
//
// Each article is driven by a data file at guides/data/{slug}.php that
// returns an array matching the structure below. The slug must match the
// filename and must be lowercase letters, digits, and hyphens only:
// `[a-z0-9-]+`.
//
// Style rules (project-wide, see CLAUDE.md):
//   - No em dashes. Use commas, colons, or periods.
//   - Banned words: "reconciliation", "reconcile".
//   - Never make Argo Books sound buggy. Avoid crash, bug, broken, error,
//     lost, corrupted, vulnerability anywhere near the Argo Books name.
//   - Plain language. If a 12-year-old cannot understand it, rewrite.
//   - No buzzwords (synergy, leverage, revolutionize, seamless, etc.).
//   - 1500-word floor for the rendered body (intro + sections + FAQ
//     answers, HTML tags excluded).
//   - HTML fields hold trusted author markup. Articles may embed <p>, <ul>,
//     <ol>, <strong>, <em>, <a>, <h3> inside section html, <table>. The
//     article template echoes them verbatim.
//   - Question and answer strings inside `faqs` are plain text. The
//     template renders the question inside <h3> and the answer inside <p>,
//     so do not wrap them in tags.

return [

  // URL slug. Lowercase letters, digits, hyphens. Must match filename.
  'slug' => 'how-to-invoice-clients',

  // The single visible <h1> on the page. Should include the primary
  // search-intent keyword. Keep under 70 characters.
  'h1' => 'How to invoice clients: a step-by-step guide',

  // <title>. Aim for 50 to 60 characters. Include the brand suffix.
  'meta_title' => 'How to Invoice Clients: a Step-by-Step Guide | Argo Books',

  // <meta name="description">. Aim for 140 to 160 characters.
  'meta_description' => 'How to invoice clients without missing anything: what to put on the invoice, when to send it, and how to get paid faster.',

  // JSON-LD type. 'Article' or 'HowTo'. HowTo only on genuinely procedural pieces (step-by-step instructions with a defined end state).
  'schema_type' => 'HowTo',

  // Required. Which section of /guides/ the article sits under: invoicing, receipts-expenses, bookkeeping or choosing-software.
  'category' => 'invoicing',

  // Ordering within the category on /guides/. Lower lists first. Use
  // multiples of 10 so new articles can slot between existing ones.
  'hub_weight' => 50,

  // ISO date string. Used for datePublished AND dateModified when
  // `updated` is not set. Set on creation, do not change.
  'published' => '2026-05-30',

  // ISO date string. Used for dateModified and rendered as
  // "Updated YYYY-MM-DD" under the H1. Bump on substantive edits.
  'updated' => '2026-05-30',

  // Optional. Rendered as "N min read" badge under the H1. Estimate at
  // 220 words per minute, rounded up.
  'reading_time_min' => 8,

  // Optional. One extra stylesheet from guides/styles/, a filename only, since most articles are prose and need none.
  'stylesheet' => null,

  // Optional. ISO 8601 duration. Only used when schema_type is 'HowTo'.
  // Example: 'PT15M' for fifteen minutes.
  'total_time_iso8601' => null,

  // Optional. A single headline statistic, rendered as a large highlighted block between the byline and the intro.
  'hero_stat' => [
    'label' => 'Canadian small businesses pay',
    'value' => '$360 to $2,640',
    'unit' => 'CAD per year',
    'footnote' => 'Most land around <strong>$1,320</strong>.',
  ],

  // Trusted HTML for the intro. 100 to 200 words. Hook the reader and
  // tell them what they will learn. Counts toward the 1500-word floor.
  'intro_html' => '<p>Replace this with 100 to 200 words of intro.</p>',

  // Ordered array of content sections. Each becomes a <section> with an <h2> heading and a body of trusted HTML.
  'sections' => [
    [
      // Required. <h2> heading.
      'h2' => 'Step 1: gather your details',

      // Optional. URL fragment. Defaults to "section-N".
      'anchor' => 'gather-details',

      // Required. Trusted HTML for the section body. May include <h3>, <p>, <ul>, <ol>, <strong>, <em>, <a>, <table>. Counts toward the 1500-word floor.
      'html' => '<p>Body of the section.</p>',

      // Optional, only used when schema_type is 'HowTo'.
      // The HowToStep `name` field.
      'step_name' => 'Gather your details',

      // Optional, only used when schema_type is 'HowTo'. The HowToStep `text` field. If omitted, the template falls back to a strip_tags() of the html field.
      'step_text' => 'Pull together your business address, the client details, the invoice number, and what you are billing for.',
    ],
    // Add more entries here.
  ],

  // Optional. Zero-based index into `sections`. The .tool-callout block renders AFTER the section at this index. Set to -1 to disable.
  'callout_after_section_index' => 0,

  // Optional. Text inside the tool callout. Two short lines: a sentence
  // of value plus a button label.
  'tool_callout_text' => 'Open the free invoice generator and fill in the fields as you read.',
  'tool_callout_cta' => 'Open the invoice generator',

  // Optional. Site-relative URL the callout links to, e.g. '/features/receipt-scanning/' or '/downloads/'.
  'tool_callout_url' => '/features/receipt-scanning/',

  // Optional. 4 to 8 FAQ pairs. The template renders them as <h3>question</h3> followed by <p>answer</p>. Counts toward the 1500-word floor (answers only).
  'faqs' => [
    [
      'q' => 'How long should I wait before sending a reminder?',
      'a' => 'Wait until the day after the due date passes, then send a polite reminder.',
    ],
    // Add more entries here.
  ],

  // Required. At least three related niche slugs, or the template prints a dev warning where the generators section goes.
  'related_niche_slugs' => [
    'freelance',
    'contractor',
    'consultant',
  ],

  // Optional. Slugs of other articles in this batch. Used to cross-link
  // the article cluster. Recommended 2 to 4 entries.
  'related_article_slugs' => [
    'net-30-vs-due-on-receipt',
    'invoice-numbering-best-practices',
  ],
];
