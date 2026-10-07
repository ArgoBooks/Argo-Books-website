<?php
// invoice-template/data/_template.php
//
// Schema-only file documenting the structure of an /invoice-template/{slug}/
// data file. The slug `_template` is excluded from routing by template-page.php
// (it begins with an underscore, which the slug regex disallows).
//
// Every data file in this directory MUST return an associative array with
// the following keys.

return [
  // Slug for canonical URL building. Should match the filename. Examples:
  // 'pdf', 'word', 'classic-pdf', 'ribbon-google-sheets'.
  'slug' => '',

  // 'format-generic' or 'style-format', which drives the layout choices in template-page.php.
  'kind' => 'format-generic',

  // For style-format pages only. One of: classic, modern, formal, elegant, ribbon.
  'style' => null,

  // One of: pdf, word, excel, google-docs, google-sheets.
  'format' => '',

  // Visible page heading (H1).
  'h1' => '',

  // <title> contents. Optional, falls back to "{h1} | Argo Books".
  'meta_title' => null,

  // <meta name="description"> contents. Required: write a real description.
  'meta_description' => '',

  // First section under the H1 (HTML, ~120 words).
  'intro_html' => '',

  // The body section under the H2 "About this template" (HTML, ~120 words).
  'body_html' => '',

  // 3 to 5 FAQ pairs, each {q, a}. Renders into FAQPage JSON-LD plus on-page
  // markup. Plain text only (no HTML in a or q).
  'faqs' => [
    // ['q' => 'Question?', 'a' => 'Answer.'],
  ],

  // Slugs (within this same directory) to cross-link to in the related section.
  // 3 to 8 entries. Pages of the same style or same format are the natural targets.
  'related_slugs' => [],
];
