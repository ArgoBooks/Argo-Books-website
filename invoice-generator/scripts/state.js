// invoice-generator/scripts/state.js
// Invoice state model + localStorage persistence.
//
// The engine is shared by the invoice, estimate, and purchase-order generators.
// window.DOC_CONFIG (injected by the page) carries the document-type overrides;
// when it is absent (the invoice standalone page and every niche page) the
// invoice defaults apply, so behavior is unchanged.

const DOC = (typeof window !== 'undefined' && window.DOC_CONFIG) || {};

const STORAGE_KEY = DOC.storageKey || 'argobooks.invoiceGenerator.draft';

export function emptyState() {
  const state = {
    template: 'classic',
    country: 'US',
    currency: 'USD',
    locale: 'en-US',
    logoDataUrl: null,
    logoWidth: null,
    logoHeight: null,
    from: '',
    billTo: '',
    shipTo: null, // null means "Ship To" not shown; '' or content means shown
    invoiceNumber: '1',
    date: todayISO(),
    paymentTerms: 'Net 30',
    dueDate: addDaysISO(todayISO(), 30),
    poNumber: '',
    lineItems: [{ description: '', quantity: 1, rate: 0 }],
    discount: null, // null means hidden; { mode: 'percent'|'fixed', value: number } when shown
    shipping: null, // same pattern: null or { value: number }
    taxRatePercent: 0,
    taxRateMode: 'percent', // 'percent' (rate * subtotal) | 'fixed' (flat dollar amount)
    notes: '',
    terms: '',
    amountPaid: 0,
    signature: null, // null means the acceptance/signature block is hidden; {} when shown
    // Every visible piece of text on the invoice surface is user-editable.
    // The default values are the standard invoice labels; the user can
    // rename any of them (e.g. "Bill To" -> "Client", "Tax" -> "GST").
    labels: {
      businessTitle: '',
      documentTitle: DOC.documentTitle || 'INVOICE',
      from: 'From',
      billTo: 'Bill To',
      shipTo: 'Ship To',
      date: 'Date',
      paymentTerms: 'Payment Terms',
      dueDate: DOC.dueDateLabel || 'Due Date',
      poNumber: 'PO Number',
      description: 'Description',
      quantity: 'Quantity',
      rate: 'Rate',
      amount: 'Amount',
      notes: 'Notes',
      terms: 'Terms',
      subtotal: 'Subtotal',
      tax: 'Tax',
      shipping: 'Shipping',
      discount: 'Discount',
      total: 'Total',
      amountPaid: 'Amount Paid',
      balanceDue: 'Balance Due',
      signatureLabel: 'Accepted by',
      signatureName: 'Signature',
      signatureDate: 'Date',
    },
  };

  // Per-document-type label overrides (e.g. "Bill To" -> "Vendor" for a PO),
  // merged over the defaults above. Only known label keys are applied.
  const overrides = DOC.labelOverrides || {};
  for (const key of Object.keys(overrides)) {
    if (key in state.labels) state.labels[key] = overrides[key];
  }

  return state;
}

function todayISO() {
  return new Date().toISOString().slice(0, 10);
}

function addDaysISO(iso, days) {
  const d = new Date(iso);
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

// Priority: localStorage draft > nicheDefaults > empty state.
// (A.18 introduces nicheDefaults; A.7 ships the parameter as a no-op-friendly default.)
//
// `labels` is merged one level deep, not shallow-replaced: a draft saved before
// a new label existed (e.g. the signature captions) would otherwise clobber the
// whole labels object and blank out the new defaults. Deep-merging backfills
// any new default labels while preserving the user's own renames.
function mergeOver(base, overlay) {
  if (!overlay || typeof overlay !== 'object') return base;
  return {
    ...base,
    ...overlay,
    labels: { ...base.labels, ...(overlay.labels || {}) },
  };
}

export function loadDraft(nicheDefaults) {
  const base = emptyState();
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) return mergeOver(base, JSON.parse(raw));
  } catch (_e) {
    // fall through to defaults
  }
  if (nicheDefaults && typeof nicheDefaults === 'object') {
    return mergeOver(base, nicheDefaults);
  }
  return base;
}

export function saveDraft(state) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    return { ok: true };
  } catch (e) {
    // Likely QuotaExceededError (a huge logo + many line items can push past ~5MB).
    // Fallback: drop the logo (the heaviest field) and try again.
    if (e && e.name === 'QuotaExceededError' && state.logoDataUrl) {
      try {
        const trimmed = { ...state, logoDataUrl: null, logoWidth: null, logoHeight: null };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(trimmed));
        return { ok: true, droppedLogo: true };
      } catch (_e2) {
        // fall through
      }
    }
    return { ok: false, error: e?.name || 'unknown' };
  }
}
