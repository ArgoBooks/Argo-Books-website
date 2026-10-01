<?php
// invoice-generator/_fragment.php
// Reusable invoice editor surface. Included by:
//  : invoice-generator/index.php (the standalone tool page)
//  : niches/niche-page.php (niche landing pages that embed the tool)
//
// This file is plain HTML markup (with optional PHP for conditional defaults).
// It does NOT set $body_content or call the layout: callers do that.
// The wiring script (main.js) is loaded once per page by the including page,
// via $extra_scripts in the layout.
//
// Conversion-pitch CTAs:
//   The including page sets $invgen_ref to a referral source code (e.g.
//   'invgen-tool' for the standalone tool, 'invgen-{slug}' for niche pages).
//   That value goes into the ?source= query param, which track_referral.php
//   reads on the destination (argorobots.com/) to attribute the visit.
//   Falls back to 'invgen-tool' when callers forget to set it.
require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../shared/currencies.php';
require_once __DIR__ . '/doc-config.php';
// Document-type config drives copy, accessibility labels, conversion-CTA
// attribution, and which invoice-only fields render. Defaults to 'invoice'
// so niche pages (which embed this fragment without a $doc_type) are unchanged.
$dc = invgen_doc_config($doc_type ?? 'invoice');
$invgen_ref = $invgen_ref ?? $dc['default_ref'];
$ref_qs = '?source=' . htmlspecialchars($invgen_ref) . '&amp;utm_source=' . htmlspecialchars($dc['utm_source']) . '&amp;utm_medium=tool&amp;utm_campaign=phase1';
// The site-header (logo bar) is rendered once for every tool page by layout.php,
// so this fragment no longer emits its own.
?>
<div class="invoice-app" data-template="classic">

  <?php if (!empty($show_tool_hero)): ?>
  <section class="site-hero">
    <h1 class="site-hero-title"><?= htmlspecialchars($dc['hero_title']) ?></h1>
    <p class="site-hero-tagline"><?= htmlspecialchars($dc['hero_tagline']) ?></p>
  </section>
  <?php endif; ?>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text"><?= htmlspecialchars($dc['banner_text']) ?></span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?><?= htmlspecialchars($dc['cta_href']) ?><?= $ref_qs ?>&amp;placement=banner">Try Argo Books <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <header class="toolbar" role="toolbar" aria-label="<?= htmlspecialchars($dc['toolbar_aria']) ?>">
    <div class="toolbar-controls">
      <label class="toolbar-field">
        <span class="toolbar-label">Template</span>
        <select data-field="template" aria-label="<?= htmlspecialchars($dc['template_aria']) ?>">
          <option value="classic">Classic</option>
          <option value="modern">Modern</option>
          <option value="formal">Formal</option>
          <option value="elegant">Elegant</option>
          <option value="ribbon">Ribbon</option>
        </select>
      </label>

      <label class="toolbar-field">
        <span class="toolbar-label">Currency</span>
        <select data-field="currency" aria-label="Currency">
          <?= argo_currency_options() ?>
        </select>
      </label>
    </div>

    <div class="toolbar-actions">
      <button type="button" id="download-pdf" class="btn btn-primary" data-action="download-pdf">Download PDF</button>
      <button type="button" id="download-word" class="btn btn-secondary" data-action="download-word">Download Word</button>
      <button type="button" id="copy-share-link" class="btn btn-secondary" data-action="copy-share-link" aria-label="Copy a shareable link to this pre-filled invoice">Copy share link</button>
    </div>
  </header>

  <main class="invoice" aria-label="<?= htmlspecialchars($dc['editor_aria']) ?>">

    <!-- Watercolor wave decoration: visible only for [data-template="ribbon"].
         Three overlapping wave paths, each filled with a vertical gradient at
         low opacity, give the soft watercolor look from the desktop app's
         Ribbon template. Hidden by default via CSS. -->
    <div class="invoice-ribbon-deco" aria-hidden="true">
      <svg viewBox="0 0 280 1100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="invgenRibbonAccent" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stop-color="#86efac" stop-opacity="0.85"/>
            <stop offset="25%" stop-color="#86efac" stop-opacity="1"/>
            <stop offset="50%" stop-color="#86efac" stop-opacity="0.55"/>
            <stop offset="75%" stop-color="#86efac" stop-opacity="1"/>
            <stop offset="100%" stop-color="#86efac" stop-opacity="0.7"/>
          </linearGradient>
          <linearGradient id="invgenRibbonPrimary" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stop-color="#7dd3fc" stop-opacity="0.7"/>
            <stop offset="25%" stop-color="#7dd3fc" stop-opacity="0.9"/>
            <stop offset="50%" stop-color="#7dd3fc" stop-opacity="0.5"/>
            <stop offset="75%" stop-color="#7dd3fc" stop-opacity="0.9"/>
            <stop offset="100%" stop-color="#7dd3fc" stop-opacity="0.85"/>
          </linearGradient>
          <linearGradient id="invgenRibbonSecondary" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stop-color="#fde68a" stop-opacity="0.85"/>
            <stop offset="25%" stop-color="#fde68a" stop-opacity="1"/>
            <stop offset="50%" stop-color="#fde68a" stop-opacity="0.6"/>
            <stop offset="75%" stop-color="#fde68a" stop-opacity="1"/>
            <stop offset="100%" stop-color="#fde68a" stop-opacity="0.75"/>
          </linearGradient>
          <path id="invgenRibbonWave" d="M140,0 C170,80 190,160 190,250 C190,340 150,420 150,510 C150,600 190,680 190,770 C190,860 150,940 150,1030 C150,1120 190,1200 190,1290 C190,1380 150,1460 150,1550 C150,1640 190,1720 190,1810 C190,1900 150,1980 150,2070 L10,2070 C10,1980 50,1900 50,1810 C50,1720 10,1640 10,1550 C10,1460 50,1380 50,1290 C50,1200 10,1120 10,1030 C10,940 50,860 50,770 C50,680 10,600 10,510 C10,420 50,340 50,250 C50,160 30,80 0,0 Z"/>
        </defs>
        <use href="#invgenRibbonWave" fill="url(#invgenRibbonAccent)" opacity="0.225" transform="translate(-50, 0)"/>
        <use href="#invgenRibbonWave" fill="url(#invgenRibbonPrimary)" opacity="0.225" transform="translate(-10, -130)"/>
        <use href="#invgenRibbonWave" fill="url(#invgenRibbonSecondary)" opacity="0.225" transform="translate(30, -260)"/>
      </svg>
    </div>

    <section class="invoice-header">
      <div class="invoice-header-left">
        <button type="button" id="logo-slot" data-action="upload-logo" data-field="logo" aria-label="Add your logo">+ Add Your Logo</button>
        <input type="file" id="logo-file-input" accept="image/*" hidden>
        <div class="logo-rendered" data-logo-rendered hidden></div>
        <input type="text" class="editable-text editable-business-title" data-label="businessTitle" placeholder="Your business name" aria-label="Business name">
      </div>
      <div class="invoice-header-right">
        <input type="text" class="editable-text editable-document-title" data-label="documentTitle" aria-label="Document title">
        <label class="invoice-number-field">
          <span class="invoice-number-prefix">#</span>
          <input type="text" data-field="invoiceNumber" placeholder="1" aria-label="<?= htmlspecialchars($dc['number_aria']) ?>">
        </label>
      </div>
    </section>

    <section class="invoice-meta">
      <div class="meta-parties">
        <div class="meta-block meta-from">
          <input type="text" class="editable-text editable-label" data-label="from" aria-label="From label">
          <!-- rows="4" because this placeholder is four lines, one more than the
               Bill To / Ship To ones. At rows="3" the last line was cut off. -->
          <textarea id="field-from" data-field="from" rows="4" placeholder="Your business name&#10;Address&#10;City, State ZIP&#10;EIN/Tax ID (optional)"></textarea>
        </div>

        <div class="meta-bill-ship">
          <div class="meta-block meta-billto">
            <input type="text" class="editable-text editable-label" data-label="billTo" aria-label="Bill To label">
            <textarea id="field-billTo" data-field="billTo" rows="3" placeholder="<?= $dc['recipient_placeholder'] ?>"></textarea>
          </div>

          <div class="meta-block meta-shipto-col">
            <span class="editable-label meta-label-spacer" aria-hidden="true">&nbsp;</span>
            <div id="field-shipTo-wrap" class="meta-shipto" hidden>
              <input type="text" class="editable-text editable-label" data-label="shipTo" aria-label="Ship To label">
              <textarea id="field-shipTo" data-field="shipTo" rows="3" placeholder="Recipient name&#10;Address&#10;City, State ZIP"></textarea>
            </div>
            <div class="meta-shipto-toggle">
              <button type="button" class="btn btn-add" data-action="toggle-shipTo" aria-controls="field-shipTo-wrap">+ Ship To</button>
            </div>
          </div>
        </div>
      </div>

      <div class="meta-info">
        <div class="meta-field-row">
          <input type="text" class="editable-text editable-label" data-label="date" aria-label="Date label">
          <input type="date" id="field-date" data-field="date">
        </div>
        <?php if ($dc['show_payment_terms']): ?>
        <div class="meta-field-row">
          <input type="text" class="editable-text editable-label" data-label="paymentTerms" aria-label="Payment Terms label">
          <input type="text" id="field-paymentTerms" data-field="paymentTerms" placeholder="Net 30">
        </div>
        <?php endif; ?>
        <div class="meta-field-row">
          <input type="text" class="editable-text editable-label" data-label="dueDate" aria-label="Due Date label">
          <input type="date" id="field-dueDate" data-field="dueDate">
        </div>
        <div class="meta-field-row">
          <input type="text" class="editable-text editable-label" data-label="poNumber" aria-label="PO Number label">
          <input type="text" id="field-poNumber" data-field="poNumber" placeholder="Optional">
        </div>
      </div>
    </section>

    <section class="invoice-items">
      <table class="line-items" role="table" aria-label="Line items">
        <thead>
          <tr>
            <th scope="col" class="col-description"><input type="text" class="editable-text editable-label editable-th" data-label="description" aria-label="Description column label"></th>
            <th scope="col" class="col-quantity"><input type="text" class="editable-text editable-label editable-th" data-label="quantity" aria-label="Quantity column label"></th>
            <th scope="col" class="col-rate"><input type="text" class="editable-text editable-label editable-th" data-label="rate" aria-label="Rate column label"></th>
            <th scope="col" class="col-amount"><input type="text" class="editable-text editable-label editable-th" data-label="amount" aria-label="Amount column label"></th>
            <th scope="col" class="col-delete"><span class="visually-hidden">Delete</span></th>
          </tr>
        </thead>
        <tbody data-line-items-body>
          <tr class="line-item" data-line-item-index="0">
            <td class="col-description" data-label="Description">
              <input type="text" data-field="lineItem-description" placeholder="Description of service" aria-label="Description">
            </td>
            <td class="col-quantity" data-label="Quantity">
              <input type="number" inputmode="numeric" data-field="lineItem-quantity" value="1" min="0" step="1" aria-label="Quantity">
            </td>
            <td class="col-rate" data-label="Rate">
              <span class="totals-input-group line-item-money">
                <span class="totals-input-affix">$</span>
                <input type="number" inputmode="decimal" data-field="lineItem-rate" value="0" min="0" step="0.01" aria-label="Rate">
              </span>
            </td>
            <td class="col-amount" data-label="Amount">
              <output data-field="lineItem-amount">$0.00</output>
            </td>
            <td class="col-delete">
              <button type="button" class="btn-icon" data-action="delete-line-item" aria-label="Delete line item">&#215;</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="line-items-actions">
        <button type="button" class="btn btn-add" data-action="add-line-item">+ Line Item</button>
      </div>

    </section>

    <section class="invoice-footer">
      <div class="invoice-notes">
        <div class="notes-block">
          <input type="text" class="editable-text editable-label" data-label="notes" aria-label="Notes label">
          <textarea id="field-notes" data-field="notes" rows="3" placeholder="Notes, any relevant information not already covered"></textarea>
        </div>
        <div class="notes-block">
          <input type="text" class="editable-text editable-label" data-label="terms" aria-label="Terms label">
          <textarea id="field-terms" data-field="terms" rows="3" placeholder="Terms and conditions, late fees, payment methods, delivery schedule"></textarea>
        </div>
      </div>

      <div class="invoice-totals" role="group" aria-label="<?= htmlspecialchars($dc['totals_aria']) ?>">
        <div class="totals-row totals-subtotal">
          <input type="text" class="editable-text editable-totals-label" data-label="subtotal" aria-label="Subtotal label">
          <output data-field="subtotal" class="totals-value">$0.00</output>
        </div>

        <div class="totals-row totals-tax">
          <input type="text" class="editable-text editable-totals-label" data-label="tax" aria-label="Tax label">
          <span class="totals-input-group">
            <span class="totals-input-affix" data-tax-prefix hidden>$</span>
            <input type="number" inputmode="decimal" data-field="taxRatePercent" value="0" min="0" step="0.001" aria-label="Tax">
            <span class="totals-input-affix totals-input-affix-right" data-tax-suffix>%</span>
            <span class="totals-input-affix totals-input-affix-right" data-currency-code hidden></span>
            <button type="button" class="totals-input-swap" data-action="toggle-tax-mode" aria-label="Switch tax between percent and fixed amount" title="Switch between percent and fixed amount">&#x21c4;</button>
          </span>
        </div>

        <div id="totals-shipping-row" class="totals-row totals-shipping" hidden>
          <input type="text" class="editable-text editable-totals-label" data-label="shipping" aria-label="Shipping label">
          <span class="totals-input-group">
            <span class="totals-input-affix">$</span>
            <input type="number" inputmode="decimal" data-field="shipping-value" value="0" min="0" step="0.01" aria-label="Shipping">
            <span class="totals-input-affix totals-input-affix-right" data-currency-code hidden></span>
          </span>
        </div>

        <div id="totals-discount-row" class="totals-row totals-discount" hidden>
          <input type="text" class="editable-text editable-totals-label" data-label="discount" aria-label="Discount label">
          <span class="totals-input-group">
            <span class="totals-input-affix" data-discount-prefix hidden>$</span>
            <input type="number" inputmode="decimal" data-field="discount-value" value="0" min="0" step="0.01" aria-label="Discount">
            <span class="totals-input-affix totals-input-affix-right" data-discount-suffix>%</span>
            <span class="totals-input-affix totals-input-affix-right" data-currency-code hidden></span>
            <button type="button" class="totals-input-swap" data-action="toggle-discount-mode" aria-label="Switch discount between percent and fixed amount" title="Switch between percent and fixed amount">&#x21c4;</button>
          </span>
        </div>

        <div class="totals-toggle-row">
          <button type="button" class="totals-toggle" data-action="toggle-shipping" aria-controls="totals-shipping-row">+ Shipping</button>
          <button type="button" class="totals-toggle" data-action="toggle-discount" aria-controls="totals-discount-row">+ Discount</button>
        </div>

        <div class="totals-row totals-total">
          <input type="text" class="editable-text editable-totals-label" data-label="total" aria-label="Total label">
          <output data-field="total" class="totals-value">$0.00</output>
        </div>

        <?php if ($dc['show_payment_fields']): ?>
        <div class="totals-row totals-paid">
          <input type="text" class="editable-text editable-totals-label" data-label="amountPaid" aria-label="Amount Paid label">
          <span class="totals-input-group">
            <span class="totals-input-affix">$</span>
            <input type="number" inputmode="decimal" data-field="amountPaid" value="0" min="0" step="0.01" aria-label="Amount paid">
            <span class="totals-input-affix totals-input-affix-right" data-currency-code hidden></span>
          </span>
        </div>

        <div class="totals-row totals-balance">
          <input type="text" class="editable-text editable-totals-label" data-label="balanceDue" aria-label="Balance Due label">
          <output data-field="balance-due" class="totals-value">$0.00</output>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <?php if (!empty($dc['show_signature'])): ?>
    <section class="invoice-signature">
      <div id="signature-block" class="signature-block" hidden>
        <input type="text" class="editable-text editable-label signature-heading" data-label="signatureLabel" aria-label="Signature section label">
        <div class="signature-row">
          <div class="signature-field">
            <span class="signature-line" aria-hidden="true"></span>
            <input type="text" class="editable-text editable-label signature-caption" data-label="signatureName" aria-label="Signature line label">
          </div>
          <div class="signature-field signature-field-date">
            <span class="signature-line" aria-hidden="true"></span>
            <input type="text" class="editable-text editable-label signature-caption" data-label="signatureDate" aria-label="Signature date label">
          </div>
        </div>
      </div>
      <div class="signature-toggle-row">
        <button type="button" class="btn btn-add" data-action="toggle-signature" aria-controls="signature-block">+ Signature</button>
      </div>
    </section>
    <?php endif; ?>

  </main>

  <dialog id="invgen-post-download" class="invgen-modal" aria-labelledby="invgen-modal-title">
    <h2 id="invgen-modal-title"><?= htmlspecialchars($dc['modal_heading']) ?></h2>
    <p>If you want to handle payments, refunds, and track everything, use Argo Books.</p>
    <div class="invgen-modal-actions">
      <a href="<?= INVGEN_BASE ?>/pricing/?source=<?= htmlspecialchars($invgen_ref) ?>&amp;utm_source=<?= htmlspecialchars($dc['utm_source']) ?>&amp;utm_medium=tool&amp;utm_campaign=phase1&amp;placement=modal" data-pitch-placement="modal" class="btn btn-primary">Get Argo Books</a>
      <button type="button" class="btn btn-secondary" data-action="close-modal">Close</button>
    </div>
  </dialog>
</div>

<?php /* Currency locales for Intl.NumberFormat, from shared/currencies.php so
         main.js does not carry its own copy of the list. Emitted here rather
         than in a page head because every caller of this fragment needs it and
         main.js loads after the body. */ ?>
<script>window.ARGO_CURRENCY_LOCALES = <?= json_encode(argo_currency_locales(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
