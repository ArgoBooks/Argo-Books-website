<?php
// invoice-template/data/formal-pdf.php
// /invoice-template/formal-pdf/

require_once __DIR__ . '/../../shared/currencies.php';

return [
  'slug' => 'formal-pdf',
  'kind' => 'style-format',
  'style' => 'formal',
  'format' => 'pdf',

  'h1' => 'Formal PDF invoice template',
  'meta_title' => 'Formal PDF Invoice Template | Free Download | Argo Books',
  'meta_description' => 'Free Formal-style PDF invoice template. Stripped-down typography, lots of whitespace, ready to customize and download in seconds.',

  'intro_html' => '<p>The Formal style strips the document back to plain typography and lots of whitespace. The header is transparent and borders disappear, leaving the content to do the work. It suits brands that already have strong visual identity elsewhere and want the invoice to stay out of the way. Click "Customize and download PDF" to open the generator with Formal preselected.</p>',

  'body_html' =>
    '<p>The Formal PDF template suits established businesses that want their invoice to look familiar to every recipient. The whitespace lets the content breathe, the "INVOICE" label is unambiguous at first glance, and the table of line items reads cleanly even when printed on plain paper.</p>'
    . '<p>You can edit every label on the invoice surface. If your business uses "Description" rather than "Service", change the column header. If you charge a flat fee rather than billing by quantity and rate, you can rename those columns too. The PDF download captures whatever you see on screen.</p>',

  'faqs' => [
    ['q' => 'Can I use the Formal template for a freelance invoice?', 'a' => 'Yes. The Formal style works for any business, freelance or otherwise. Fill in your name in the From block, set your terms, and download.'],
    ['q' => 'How do I change the currency on the Formal PDF template?', 'a' => 'Open the generator and pick a currency from the toolbar. The generator covers ' . count(argo_currencies_all()) . ' currencies and the change applies to every total on the invoice.'],
    ['q' => 'Will my logo fit in the Formal header?', 'a' => 'Yes. The generator resizes large logos automatically to keep the saved draft under the local-storage quota. PNG and JPG both work.'],
  ],

  'related_slugs' => ['pdf', 'formal-word', 'formal-excel', 'classic-pdf', 'modern-pdf'],
];
