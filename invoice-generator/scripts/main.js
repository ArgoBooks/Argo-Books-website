// invoice-generator/scripts/main.js
// Wires the static invoice page (index.php) to the state, render, tax, templates,
// and currency modules. Pure vanilla ES module: no deps, no build step.

import { loadDraft, saveDraft, emptyState } from './state.js';
import { renderTotals, renderLineItemAmount } from './render.js';
import { applyTemplate } from './templates.js';
import { formatMoney } from './currency.js';
import { symbolFor as currencySymbolFor } from '../../shared/scripts/currency-format.js';
import { resizeImageDataUrl } from './image-helpers.js';
import { trackEvent } from './tracker.js';

const BASE = (typeof window !== 'undefined' && window.INVGEN_BASE) || '';

// Document-type config (shared engine; invoice defaults when absent). EVP
// prefixes the analytics event names so the estimate tool reports under
// estgen_*; TOOL_PATH is the canonical path for the "Copy share link" URL.
const DOC = (typeof window !== 'undefined' && window.DOC_CONFIG) || {};
const EVP = DOC.eventPrefix || 'invgen';
const TOOL_PATH = DOC.toolPath || '/invoice-generator/';

// ---------- tiny DOM helpers ----------

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

function setFieldValue(el, value) {
  if (!el) return;
  const tag = el.tagName;
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
    // Avoid clobbering an empty editable string with "null"/"undefined" text.
    el.value = value == null ? '' : value;
  } else {
    el.textContent = value == null ? '' : String(value);
  }
}

// ---------- module-scoped state ----------

let state = emptyState();
let bootstrapped = false;

// ---------- toast ----------

function showToast(message) {
  if (!message) return;
  let host = $('.invgen-toast');
  if (!host) {
    host = document.createElement('div');
    host.className = 'invgen-toast';
    host.setAttribute('role', 'status');
    host.setAttribute('aria-live', 'polite');
    document.body.appendChild(host);
  }
  host.textContent = message;
  // Force reflow so the fade-in transition runs each call.
  // eslint-disable-next-line no-unused-expressions
  host.offsetHeight;
  host.classList.add('is-visible');
  clearTimeout(host._hideTimer);
  host._hideTimer = setTimeout(() => {
    host.classList.remove('is-visible');
  }, 4000);
}

function handleSaveResult(result) {
  if (!result) return;
  if (result.droppedLogo) {
    showToast('Your logo was too large to save locally. It will not be remembered after refresh.');
  } else if (result.ok === false) {
    showToast('Could not save your progress locally.');
  }
}

// ---------- hydration ----------

// Set every editable [data-field] on the page from `state`.
function hydrateFromState() {
  // Top-level scalar fields. Output/text fields are populated by renderTotals.
  setFieldValue($('[data-field="template"]'), state.template);
  setFieldValue($('[data-field="currency"]'), state.currency);
  setFieldValue($('[data-field="invoiceNumber"]'), state.invoiceNumber);
  setFieldValue($('[data-field="from"]'), state.from);
  setFieldValue($('[data-field="billTo"]'), state.billTo);
  setFieldValue($('[data-field="date"]'), state.date);
  setFieldValue($('[data-field="paymentTerms"]'), state.paymentTerms);
  setFieldValue($('[data-field="dueDate"]'), state.dueDate);
  setFieldValue($('[data-field="poNumber"]'), state.poNumber);
  setFieldValue($('[data-field="notes"]'), state.notes);
  setFieldValue($('[data-field="terms"]'), state.terms);
  setFieldValue($('[data-field="taxRatePercent"]'), state.taxRatePercent);
  setFieldValue($('[data-field="amountPaid"]'), state.amountPaid);

  // Ship To: shown only when state.shipTo is not null.
  const shipToWrap = $('#field-shipTo-wrap');
  const shipToToggleBtn = $('[data-action="toggle-shipTo"]');
  if (shipToWrap) {
    if (state.shipTo == null) {
      shipToWrap.hidden = true;
      if (shipToToggleBtn) shipToToggleBtn.hidden = false;
    } else {
      shipToWrap.hidden = false;
      if (shipToToggleBtn) shipToToggleBtn.hidden = true;
      setFieldValue($('[data-field="shipTo"]'), state.shipTo);
    }
  }

  // Discount: shown only when state.discount is not null.
  const discountRow = $('#totals-discount-row');
  const discountToggleBtn = $('[data-action="toggle-discount"]');
  if (discountRow) {
    if (state.discount == null) {
      discountRow.hidden = true;
      if (discountToggleBtn) discountToggleBtn.hidden = false;
    } else {
      discountRow.hidden = false;
      if (discountToggleBtn) discountToggleBtn.hidden = true;
      setFieldValue($('[data-field="discount-value"]'), state.discount.value);
    }
  }

  // Shipping: shown only when state.shipping is not null.
  const shippingRow = $('#totals-shipping-row');
  const shippingToggleBtn = $('[data-action="toggle-shipping"]');
  if (shippingRow) {
    if (state.shipping == null) {
      shippingRow.hidden = true;
      if (shippingToggleBtn) shippingToggleBtn.hidden = false;
    } else {
      shippingRow.hidden = false;
      if (shippingToggleBtn) shippingToggleBtn.hidden = true;
      setFieldValue($('[data-field="shipping-value"]'), state.shipping.value);
    }
  }

  // Signature: shown only when state.signature is not null. The block only
  // exists on doc types that opt in (estimate, purchase order).
  const signatureBlock = $('#signature-block');
  const signatureToggleBtn = $('[data-action="toggle-signature"]');
  if (signatureBlock) {
    if (state.signature == null) {
      signatureBlock.hidden = true;
      if (signatureToggleBtn) signatureToggleBtn.hidden = false;
    } else {
      signatureBlock.hidden = false;
      if (signatureToggleBtn) signatureToggleBtn.hidden = true;
    }
  }

  // Line items: replace the table body so a restored draft can show >1 row.
  hydrateLineItems();

  updateAffixes();
  hydrateLabels();
}

// Every editable label on the invoice surface (business title, document
// title, field labels, table headers, totals labels) is wired by data-label.
// We populate the input value from state.labels on hydrate and listen for
// edits to push them back into state.
function hydrateLabels() {
  const labels = state.labels || {};
  $$('[data-label]').forEach((el) => {
    const key = el.getAttribute('data-label');
    if (key in labels) el.value = labels[key];
  });
}

function hydrateLineItems() {
  const tbody = $('[data-line-items-body]');
  if (!tbody) return;
  tbody.innerHTML = '';
  state.lineItems.forEach((li, idx) => {
    tbody.appendChild(createLineItemRow(idx, li));
  });
  // After the DOM is in place, populate computed amounts.
  $$('[data-line-item-index]', tbody).forEach((row) => {
    const idx = Number(row.getAttribute('data-line-item-index'));
    if (state.lineItems[idx]) renderLineItemAmount(row, state.lineItems[idx], state);
  });
}

// Builds a <tr> matching the markup in index.php for one line item.
function createLineItemRow(index, lineItem) {
  const li = lineItem || { description: '', quantity: 1, rate: 0 };
  const tr = document.createElement('tr');
  tr.className = 'line-item';
  tr.setAttribute('data-line-item-index', String(index));

  tr.innerHTML = `
    <td class="col-description" data-label="Description">
      <input type="text" data-field="lineItem-description" placeholder="Description of service" aria-label="Description">
    </td>
    <td class="col-quantity" data-label="Quantity">
      <input type="number" inputmode="numeric" data-field="lineItem-quantity" min="0" step="1" aria-label="Quantity">
    </td>
    <td class="col-rate" data-label="Rate">
      <span class="totals-input-group line-item-money">
        <span class="totals-input-affix">$</span>
        <input type="number" inputmode="decimal" data-field="lineItem-rate" min="0" step="0.01" aria-label="Rate">
      </span>
    </td>
    <td class="col-amount" data-label="Amount">
      <output data-field="lineItem-amount">$0.00</output>
    </td>
    <td class="col-delete">
      <button type="button" class="btn-icon" data-action="delete-line-item" aria-label="Delete line item">&#215;</button>
    </td>
  `;

  $('[data-field="lineItem-description"]', tr).value = li.description == null ? '' : li.description;
  $('[data-field="lineItem-quantity"]', tr).value = li.quantity == null ? '' : li.quantity;
  $('[data-field="lineItem-rate"]', tr).value = li.rate == null ? '' : li.rate;
  return tr;
}

// ---------- save + render pipeline ----------

function rerenderAndSave() {
  renderTotals(state);
  // Also refresh per-row amounts (currency/locale could have changed).
  $$('[data-line-items-body] [data-line-item-index]').forEach((row) => {
    const idx = Number(row.getAttribute('data-line-item-index'));
    if (state.lineItems[idx]) renderLineItemAmount(row, state.lineItems[idx], state);
  });
  handleSaveResult(saveDraft(state));
}

// ---------- top-level field listeners ----------

// Maps a data-field to the state mutator. Each receives the raw input value.
const TOP_LEVEL_FIELDS = {
  invoiceNumber: (v) => { state.invoiceNumber = v; },
  from: (v) => { state.from = v; },
  billTo: (v) => { state.billTo = v; },
  shipTo: (v) => { state.shipTo = v; },
  date: (v) => { state.date = v; },
  paymentTerms: (v) => { state.paymentTerms = v; },
  dueDate: (v) => { state.dueDate = v; },
  poNumber: (v) => { state.poNumber = v; },
  notes: (v) => { state.notes = v; },
  terms: (v) => { state.terms = v; },
  taxRatePercent: (v) => { state.taxRatePercent = Number(v) || 0; },
  amountPaid: (v) => { state.amountPaid = Number(v) || 0; },
  'discount-value': (v) => {
    if (state.discount == null) state.discount = { mode: 'percent', value: 0 };
    state.discount.value = Number(v) || 0;
  },
  'shipping-value': (v) => {
    if (state.shipping == null) state.shipping = { value: 0 };
    state.shipping.value = Number(v) || 0;
  },
};

// Show the right $/% affix around the tax + discount inputs based on the
// current mode, and surface the currency-code suffix ("CAD" / "USD" / "AUD")
// wherever the prefix is $ so the reader can tell ambiguous-dollar currencies
// apart. Called after hydration and after any swap-button click.
function updateAffixes() {
  const taxIsFixed = state.taxRateMode === 'fixed';
  const taxPrefix = $('[data-tax-prefix]');
  const taxSuffix = $('[data-tax-suffix]');
  if (taxPrefix) taxPrefix.hidden = !taxIsFixed;
  if (taxSuffix) taxSuffix.hidden = taxIsFixed;

  const discountIsFixed = state.discount && state.discount.mode === 'fixed';
  const discountPrefix = $('[data-discount-prefix]');
  const discountSuffix = $('[data-discount-suffix]');
  if (discountPrefix) discountPrefix.hidden = !discountIsFixed;
  if (discountSuffix) discountSuffix.hidden = discountIsFixed;

  syncCurrencyCodeSuffix();
}

// Show the ISO code (e.g. "CAD") after each money input when the active
// currency uses "$" as its symbol. For tax and discount rows the code
// only shows in fixed-dollar mode (in percent mode the "%" suffix is what
// belongs on the right). Other rows always show the code when the symbol
// is "$". Non-dollar currencies hide the suffix entirely.
function syncCurrencyCodeSuffix() {
  const symbol = currencySymbolFor(state.currency, state.locale);
  const showForDollar = symbol === '$' && !!state.currency;
  const taxIsFixed = state.taxRateMode === 'fixed';
  const discountIsFixed = state.discount && state.discount.mode === 'fixed';

  $$('[data-currency-code]').forEach((el) => {
    const group = el.closest('.totals-input-group');
    const row = group ? group.closest('.totals-row') : null;
    let show = showForDollar;
    if (row && row.classList.contains('totals-tax')) show = show && taxIsFixed;
    else if (row && row.classList.contains('totals-discount')) show = show && discountIsFixed;
    // Leading non-breaking space so the code reads as " CAD" with a visible
    // gap from the number, even when the affix has zero padding (capture mode).
    el.textContent = show ? ` ${state.currency}` : '';
    el.hidden = !show;
  });
}

// Single input listener at document level. Event delegation covers initial
// rows plus any added later. We branch on the target's data-field, or treat
// it as an editable label edit when data-label is present.
function onAnyInput(ev) {
  const t = ev.target;
  if (!t || !t.matches) return;

  // Editable labels: store under state.labels keyed by the data-label name.
  if (t.matches('[data-label]')) {
    const key = t.getAttribute('data-label');
    if (state.labels && key in state.labels) {
      state.labels[key] = t.value;
      handleSaveResult(saveDraft(state));
    }
    return;
  }

  if (!t.matches('[data-field]')) return;
  const field = t.getAttribute('data-field');
  const value = t.value;

  if (field && field.startsWith('lineItem-')) {
    const row = t.closest('[data-line-item-index]');
    if (!row) return;
    const idx = Number(row.getAttribute('data-line-item-index'));
    const li = state.lineItems[idx];
    if (!li) return;
    if (field === 'lineItem-description') li.description = value;
    else if (field === 'lineItem-quantity') li.quantity = Number(value) || 0;
    else if (field === 'lineItem-rate') li.rate = Number(value) || 0;
    renderLineItemAmount(row, li, state);
    rerenderAndSave();
    return;
  }

  if (field === 'template') {
    state.template = value;
    applyTemplate(state.template);
    handleSaveResult(saveDraft(state));
    trackEvent(`${EVP}_template_changed`, value);
    return;
  }

  if (field === 'currency') {
    applyCurrency(value);
    trackEvent(`${EVP}_currency_changed`, value);
    return;
  }

  const mutator = TOP_LEVEL_FIELDS[field];
  if (mutator) {
    mutator(value);
    rerenderAndSave();
  }
}

// ---------- logo upload ----------

// Logo resize is implemented in image-helpers.js; see the conventions block there.

// First non-empty, trimmed line of state.from, or 'Business logo' fallback.
function logoAltText() {
  const from = typeof state.from === 'string' ? state.from : '';
  const lines = from.split('\n');
  for (const line of lines) {
    const trimmed = line.trim();
    if (trimmed) return trimmed;
  }
  return 'Business logo';
}

function renderLogo() {
  const slotBtn = $('#logo-slot');
  const rendered = $('[data-logo-rendered]');
  if (!rendered) return;

  if (!state.logoDataUrl) {
    rendered.hidden = true;
    rendered.innerHTML = '';
    if (slotBtn) slotBtn.hidden = false;
    return;
  }

  if (slotBtn) slotBtn.hidden = true;
  rendered.hidden = false;
  rendered.innerHTML = '';

  const img = document.createElement('img');
  img.src = state.logoDataUrl;
  img.alt = logoAltText();
  // width/height as numeric attributes to prevent CLS.
  if (state.logoWidth) img.setAttribute('width', String(state.logoWidth));
  if (state.logoHeight) img.setAttribute('height', String(state.logoHeight));
  img.loading = 'eager';
  img.className = 'logo-image';
  rendered.appendChild(img);

  // Single "x" button that only appears on hover/focus. Removes the logo
  // and reveals the "+ Add Your Logo" upload slot again.
  const removeBtn = document.createElement('button');
  removeBtn.type = 'button';
  removeBtn.className = 'logo-remove';
  removeBtn.setAttribute('data-action', 'remove-logo');
  removeBtn.setAttribute('aria-label', 'Remove logo');
  removeBtn.innerHTML = '&#215;';
  removeBtn.addEventListener('click', () => {
    state.logoDataUrl = null;
    state.logoWidth = null;
    state.logoHeight = null;
    handleSaveResult(saveDraft(state));
    renderLogo();
    const btn = $('#logo-slot');
    if (btn) btn.focus();
  });
  rendered.appendChild(removeBtn);
}

function triggerLogoFilePicker() {
  const input = $('#logo-file-input');
  if (!input) return;
  // Reset so picking the same file twice still fires `change`.
  input.value = '';
  input.click();
}

async function handleLogoFileChange(ev) {
  const input = ev.target;
  const file = input && input.files && input.files[0];
  if (!file) return;
  try {
    const { dataUrl, width, height } = await resizeImageDataUrl(file);
    state.logoDataUrl = dataUrl;
    state.logoWidth = width;
    state.logoHeight = height;
    const result = saveDraft(state);
    renderLogo();
    if (result && result.droppedLogo) {
      showToast('Logo too large to save with the invoice; please re-upload before downloading.');
    } else {
      handleSaveResult(result);
    }
    trackEvent(`${EVP}_logo_uploaded`, '');
  } catch (_e) {
    showToast('Could not read that image. Try a different file.');
  }
}

function wireLogoUpload() {
  const slotBtn = $('#logo-slot');
  if (slotBtn) slotBtn.addEventListener('click', triggerLogoFilePicker);
  const input = $('#logo-file-input');
  if (input) input.addEventListener('change', handleLogoFileChange);
}

// ---------- currency ----------

// Locale fallback per currency so Intl.NumberFormat picks a sensible default
// (decimal separators, symbol placement) when the user changes currency.
// Emitted by _fragment.php from shared/currencies.php, which is the website's
// single source of truth for the list. Kept as a lookup with an en-US fallback
// rather than a second copy of the table: if the global is ever missing, every
// currency still formats, just with US separators.
const LOCALE_FOR_CURRENCY = window.ARGO_CURRENCY_LOCALES || {};

// Update the visible currency symbol in every input affix that was a "$"
// prefix (tax / shipping / discount / amount paid / line-item rate).
function syncCurrencyAffixes() {
  const symbol = currencySymbolFor(state.currency, state.locale);
  $$('.totals-input-affix').forEach((el) => {
    // Skip the percent suffixes used by the tax/discount mode swap, and
    // the ISO-code suffix which is managed by syncCurrencyCodeSuffix().
    if (el.hasAttribute('data-tax-suffix') || el.hasAttribute('data-discount-suffix')) return;
    if (el.hasAttribute('data-currency-code')) return;
    el.textContent = symbol;
  });
}

function applyCurrency(code) {
  state.currency = code;
  state.locale = LOCALE_FOR_CURRENCY[code] || 'en-US';
  syncCurrencyAffixes();
  syncCurrencyCodeSuffix();
  rerenderAndSave();
}

// ---------- toggles ----------

function wireToggles() {
  const shipToBtn = $('[data-action="toggle-shipTo"]');
  if (shipToBtn) shipToBtn.addEventListener('click', () => {
    if (state.shipTo != null) return;
    state.shipTo = '';
    const wrap = $('#field-shipTo-wrap');
    if (wrap) wrap.hidden = false;
    shipToBtn.hidden = true;
    const ta = $('[data-field="shipTo"]');
    if (ta) {
      ta.value = '';
      ta.focus();
    }
    handleSaveResult(saveDraft(state));
  });

  const discountBtn = $('[data-action="toggle-discount"]');
  if (discountBtn) discountBtn.addEventListener('click', () => {
    if (state.discount != null) return;
    state.discount = { mode: 'percent', value: 0 };
    const row = $('#totals-discount-row');
    if (row) row.hidden = false;
    discountBtn.hidden = true;
    setFieldValue($('[data-field="discount-value"]'), state.discount.value);
    updateAffixes();
    const valueInput = $('[data-field="discount-value"]');
    if (valueInput) valueInput.focus();
    rerenderAndSave();
  });

  // Tax mode swap: toggles between percent (5%) and fixed dollar amount ($5).
  const taxSwap = $('[data-action="toggle-tax-mode"]');
  if (taxSwap) taxSwap.addEventListener('click', () => {
    state.taxRateMode = state.taxRateMode === 'fixed' ? 'percent' : 'fixed';
    updateAffixes();
    rerenderAndSave();
  });

  // Discount mode swap: toggles between percent and fixed dollar amount.
  const discountSwap = $('[data-action="toggle-discount-mode"]');
  if (discountSwap) discountSwap.addEventListener('click', () => {
    if (state.discount == null) return;
    state.discount.mode = state.discount.mode === 'fixed' ? 'percent' : 'fixed';
    updateAffixes();
    rerenderAndSave();
  });

  const shippingBtn = $('[data-action="toggle-shipping"]');
  if (shippingBtn) shippingBtn.addEventListener('click', () => {
    if (state.shipping != null) return;
    state.shipping = { value: 0 };
    const row = $('#totals-shipping-row');
    if (row) row.hidden = false;
    shippingBtn.hidden = true;
    setFieldValue($('[data-field="shipping-value"]'), state.shipping.value);
    const valueInput = $('[data-field="shipping-value"]');
    if (valueInput) valueInput.focus();
    rerenderAndSave();
  });

  // Signature/acceptance block: present only on opt-in doc types.
  const signatureBtn = $('[data-action="toggle-signature"]');
  if (signatureBtn) signatureBtn.addEventListener('click', () => {
    if (state.signature != null) return;
    state.signature = {};
    const block = $('#signature-block');
    if (block) block.hidden = false;
    signatureBtn.hidden = true;
    handleSaveResult(saveDraft(state));
  });
}

// "Remove" controls live inside the toggle rows themselves; we install them
// lazily so the markup in index.php stays minimal.
function ensureSectionRemoveButtons() {
  installRemoveButton({
    rowSelector: '#totals-discount-row',
    label: 'Remove discount',
    onRemove: () => {
      state.discount = null;
      const row = $('#totals-discount-row');
      if (row) row.hidden = true;
      const btn = $('[data-action="toggle-discount"]');
      if (btn) {
        btn.hidden = false;
        btn.focus();
      }
      rerenderAndSave();
    },
  });

  installRemoveButton({
    rowSelector: '#totals-shipping-row',
    label: 'Remove shipping',
    onRemove: () => {
      state.shipping = null;
      const row = $('#totals-shipping-row');
      if (row) row.hidden = true;
      const btn = $('[data-action="toggle-shipping"]');
      if (btn) {
        btn.hidden = false;
        btn.focus();
      }
      rerenderAndSave();
    },
  });

  installRemoveButton({
    rowSelector: '#field-shipTo-wrap',
    label: 'Remove Ship To',
    onRemove: () => {
      state.shipTo = null;
      const wrap = $('#field-shipTo-wrap');
      if (wrap) wrap.hidden = true;
      const btn = $('[data-action="toggle-shipTo"]');
      if (btn) {
        btn.hidden = false;
        btn.focus();
      }
      handleSaveResult(saveDraft(state));
    },
  });

  installRemoveButton({
    rowSelector: '#signature-block',
    label: 'Remove signature',
    onRemove: () => {
      state.signature = null;
      const block = $('#signature-block');
      if (block) block.hidden = true;
      const btn = $('[data-action="toggle-signature"]');
      if (btn) {
        btn.hidden = false;
        btn.focus();
      }
      handleSaveResult(saveDraft(state));
    },
  });
}

function installRemoveButton({ rowSelector, label, onRemove }) {
  const row = $(rowSelector);
  if (!row) return;
  if (row.querySelector('[data-action="remove-section"]')) return;
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'btn-icon invgen-section-remove';
  btn.setAttribute('data-action', 'remove-section');
  btn.setAttribute('aria-label', label);
  btn.innerHTML = '&#215;';
  btn.addEventListener('click', onRemove);
  row.appendChild(btn);
}

// ---------- line item add / delete ----------

function wireAddLineItem() {
  const addBtn = $('[data-action="add-line-item"]');
  if (!addBtn) return;
  addBtn.addEventListener('click', () => {
    const newLi = { description: '', quantity: 1, rate: 0 };
    state.lineItems.push(newLi);
    const idx = state.lineItems.length - 1;
    const tbody = $('[data-line-items-body]');
    const row = createLineItemRow(idx, newLi);
    tbody.appendChild(row);
    renderLineItemAmount(row, newLi, state);
    rerenderAndSave();
    const firstInput = $('[data-field="lineItem-description"]', row);
    if (firstInput) firstInput.focus();
  });
}

function wireDeleteLineItem() {
  const tbody = $('[data-line-items-body]');
  if (!tbody) return;
  tbody.addEventListener('click', (ev) => {
    const btn = ev.target && ev.target.closest && ev.target.closest('[data-action="delete-line-item"]');
    if (!btn) return;
    const row = btn.closest('[data-line-item-index]');
    if (!row) return;
    const idx = Number(row.getAttribute('data-line-item-index'));
    if (Number.isNaN(idx)) return;

    // Keep at least one line item visually; if it's the last one, just blank it.
    if (state.lineItems.length <= 1) {
      state.lineItems[0] = { description: '', quantity: 1, rate: 0 };
      $('[data-field="lineItem-description"]', row).value = '';
      $('[data-field="lineItem-quantity"]', row).value = 1;
      $('[data-field="lineItem-rate"]', row).value = 0;
      renderLineItemAmount(row, state.lineItems[0], state);
      rerenderAndSave();
      return;
    }

    state.lineItems.splice(idx, 1);
    row.remove();
    // Re-index remaining rows so the click->state mapping stays correct.
    $$('[data-line-item-index]', tbody).forEach((r, newIdx) => {
      r.setAttribute('data-line-item-index', String(newIdx));
    });
    rerenderAndSave();
  });
}

// ---------- download PDF ----------

// Opens the post-download conversion modal. Native <dialog> handles Escape-to-close
// and backdrop click semantics on its own; we only need to wire the Close button.
function showPostDownloadModal() {
  const dialog = document.getElementById('invgen-post-download');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  try {
    dialog.showModal();
  } catch (_e) {
    // showModal throws if the dialog is already open; safe to ignore.
  }
}

function wirePostDownloadModal() {
  const dialog = document.getElementById('invgen-post-download');
  if (!dialog) return;
  const closeBtn = dialog.querySelector('[data-action="close-modal"]');
  if (closeBtn) {
    closeBtn.addEventListener('click', () => dialog.close());
  }
}

// Conversion-pitch CTAs are marked with data-pitch-placement="toolbar|inline|modal"
// in _fragment.php. Fire a single tracked event regardless of which placement
// the user clicks so we can compare placement effectiveness. The visit-to-argorobots
// referral is attributed separately by track_referral.php via the ?source= query
// param baked into each CTA href.
function wirePitchTracking() {
  document.querySelectorAll('[data-pitch-placement]').forEach((el) => {
    el.addEventListener('click', () => {
      const placement = el.getAttribute('data-pitch-placement') || '';
      trackEvent(`${EVP}_cta_clicked`, placement);
    });
  });
}

// Wires the toolbar's Download PDF button. The pdf.js module is loaded lazily
// on first click so the html2pdf bundle does not block the initial page load.
function wireDownloadPdf() {
  const btn = $('[data-action="download-pdf"]');
  if (!btn) return;
  btn.addEventListener('click', async (e) => {
    const button = e.currentTarget;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Generating PDF...';
    let succeeded = false;
    try {
      const { downloadPdf } = await import(`${BASE}/invoice-generator/scripts/pdf.js`);
      await downloadPdf(state);
      succeeded = true;
    } catch (err) {
      console.error('PDF generation failed:', err);
      showToast('Could not generate PDF. Please try again or use Word download.');
    } finally {
      button.disabled = false;
      button.textContent = originalText;
    }
    // Only open the conversion modal on a successful download; failure already
    // surfaces a toast and showing the pitch on top of that would be confusing.
    if (succeeded) showPostDownloadModal();
  });
}

// ---------- download Word ----------

// Wires the toolbar's Download Word button. The docx.js module is loaded lazily
// on first click so the ~790KB of docx vendor code doesn't block the initial page load.
function wireDownloadDocx() {
  const btn = $('[data-action="download-word"]');
  if (!btn) return;
  btn.addEventListener('click', async (e) => {
    const button = e.currentTarget;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Generating Word...';
    try {
      const { downloadDocx } = await import(`${BASE}/invoice-generator/scripts/docx.js`);
      await downloadDocx(state);
    } catch (err) {
      console.error('Word generation failed:', err);
      showToast('Could not generate Word document. Please try again or use PDF download.');
    } finally {
      button.disabled = false;
      button.textContent = originalText;
    }
  });
}

// ---------- copy share link ----------

// Wires the toolbar's Copy share link button. Serializes the whitelisted
// pre-fill fields from state into the canonical /invoice-generator/ URL and
// puts it on the clipboard. Uses navigator.clipboard when available, with a
// textarea-execCommand fallback for non-HTTPS or older browsers.
function wireCopyShareLink() {
  const btn = $('[data-action="copy-share-link"]');
  if (!btn) return;
  btn.addEventListener('click', async (e) => {
    const button = e.currentTarget;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Copying...';
    try {
      const { serializeShareLink } = await import(`${BASE}/invoice-generator/scripts/url-params.js`);
      const shareUrl = serializeShareLink(`${window.location.origin}${BASE}${TOOL_PATH}`, state);
      let copied = false;
      if (navigator.clipboard && window.isSecureContext) {
        try {
          await navigator.clipboard.writeText(shareUrl);
          copied = true;
        } catch (_e) { /* fall through to the textarea fallback */ }
      }
      if (!copied) {
        const ta = document.createElement('textarea');
        ta.value = shareUrl;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { copied = document.execCommand('copy'); }
        catch (_e) { copied = false; }
        finally { document.body.removeChild(ta); }
      }
      showToast(copied ? 'Share link copied to clipboard.' : 'Could not copy. The link is in your address bar.');
      if (copied) {
        try { trackEvent(`${EVP}_share_link_copied`, ''); } catch (_e) { /* tracking is best-effort */ }
      }
    } catch (err) {
      console.error('Share link copy failed:', err);
      showToast('Could not generate a share link. Please try again.');
    } finally {
      button.disabled = false;
      button.textContent = originalText;
    }
  });
}

// ---------- bootstrap ----------

async function init() {
  if (bootstrapped) return;
  bootstrapped = true;

  // 1. Load state from localStorage.
  const nicheDefaults = typeof window !== 'undefined' ? window.INVOICE_NICHE_DEFAULTS : undefined;
  const hadLocalDraftBefore = (() => {
    try {
      return typeof localStorage !== 'undefined'
        && localStorage.getItem('argobooks.invoiceGenerator.draft') !== null;
    } catch (_e) { return false; }
  })();
  state = loadDraft(nicheDefaults);

  // If we landed on a niche page with defaults AND localStorage had no draft, the niche defaults won.
  if (!hadLocalDraftBefore && nicheDefaults && typeof nicheDefaults === 'object') {
    const slug = (typeof window !== 'undefined' && window.INVOICE_NICHE_SLUG)
      ? String(window.INVOICE_NICHE_SLUG)
      : '';
    trackEvent(`${EVP}_niche_default_used`, slug);
  }

  // Share-link URL parameters override the persisted draft for THIS session only.
  try {
    const { parseShareLink } = await import(`${BASE}/invoice-generator/scripts/url-params.js`);
    const { TEMPLATES } = await import(`${BASE}/invoice-generator/scripts/templates.js`);
    const allowedIds = TEMPLATES.map(t => t.id);
    const fromUrl = parseShareLink(window.location.search, allowedIds);
    if (Object.keys(fromUrl).length > 0) {
      Object.assign(state, fromUrl);
      // Currency carries a paired locale that drives Intl.NumberFormat output.
      if (fromUrl.currency) {
        state.locale = LOCALE_FOR_CURRENCY[fromUrl.currency] || 'en-US';
      }
    }
  } catch (_e) {
    // URL-param parsing is best-effort; failure must not break the tool.
  }

  // 2. Apply template immediately so the page paints correctly.
  applyTemplate(state.template);

  // 3. Hydrate every editable field from state. This includes building the
  //    line-item rows from state.lineItems.
  hydrateFromState();

  // 4. Wire all event handlers. Document-level delegation for inputs covers
  //    every existing and future [data-field] element, including new line items.
  document.addEventListener('input', onAnyInput);
  document.addEventListener('change', onAnyInput);

  wireToggles();
  ensureSectionRemoveButtons();
  wireAddLineItem();
  wireDeleteLineItem();
  wireLogoUpload();
  wireDownloadPdf();
  wireDownloadDocx();
  wireCopyShareLink();
  wirePostDownloadModal();
  wirePitchTracking();

  // 5. Initial totals render + logo render from any persisted draft.
  renderTotals(state);
  renderLogo();

  // 6. Sync the currency-derived UI (affix symbols) to the restored state.
  syncCurrencyAffixes();
  renderTotals(state);
}

init();

// Exposed for debug / future tasks (e.g., tests, the PDF/Word generators
// reading the live state). Not part of a stable public API.
if (typeof window !== 'undefined') {
  window.__invgen = {
    getState: () => state,
    showToast,
  };
}
