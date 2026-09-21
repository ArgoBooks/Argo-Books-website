<?php
// invoice-template/data/modern-pdf.php
// /invoice-template/modern-pdf/

require_once __DIR__ . '/../../shared/currencies.php';

return [
  'slug' => 'modern-pdf',
  'kind' => 'style-format',
  'style' => 'modern',
  'format' => 'pdf',

  'h1' => 'Modern PDF invoice template',
  'meta_title' => 'Modern PDF Invoice Template | Free Download | Argo Books',
  'meta_description' => 'Free Modern-style PDF invoice template. Teal accent, clean sans-serif body, ready to customize and download in seconds.',

  'intro_html' => '<p>The Modern style pairs a teal accent with a clean sans-serif body and a transparent header. It reads as contemporary without feeling trendy, which makes it a good fit for creative agencies, software shops, and consultants who want their invoice to feel current. Click "Customize and download PDF" to open the generator with Modern preselected.</p>',

  'body_html' =>
    '<p>The Modern PDF template suits established businesses that want their invoice to look familiar to every recipient. The teal accent draws the eye, the "INVOICE" label is unambiguous at first glance, and the table of line items reads cleanly even when printed on plain paper.</p>'
    . '<p>You can edit every label on the invoice surface. If your business uses "Description" rather than "Service", change the column header. If you charge a flat fee rather than billing by quantity and rate, you can rename those columns too. The PDF download captures whatever you see on screen.</p>',

  'faqs' => [
    ['q' => 'Can I use the Modern template for a freelance invoice?', 'a' => 'Yes. The Modern style works for any business, freelance or otherwise. Fill in your name in the From block, set your terms, and download.'],
    ['q' => 'How do I change the currency on the Modern PDF template?', 'a' => 'Open the generator and pick a currency from the toolbar. The generator covers ' . count(argo_currencies_all()) . ' currencies and the change applies to every total on the invoice.'],
    ['q' => 'Will my logo fit in the Modern header?', 'a' => 'Yes. The generator resizes large logos automatically to keep the saved draft under the local-storage quota. PNG and JPG both work.'],
  ],

  'related_slugs' => ['pdf', 'modern-word', 'modern-excel', 'classic-pdf', 'ribbon-pdf'],
];
