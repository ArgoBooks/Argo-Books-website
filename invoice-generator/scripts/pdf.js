// invoice-generator/scripts/pdf.js
// Generates the invoice as a single-page PDF by capturing the live .invoice
// element. Template-specific styling is delivered entirely through CSS
// variables and rules in tool.css; html2canvas captures the styled DOM, so
// adding a new template only requires CSS work. Do not add per-template
// branching here.
//
// Uses html2canvas (1.4.1) for the bitmap capture and jsPDF (2.5.2)
// to compose the document. Both are vendored separately:
//   - vendor/html2canvas.min.js   (from cdn.jsdelivr.net/npm/html2canvas@1.4.1)
//   - vendor/jspdf.umd.min.js     (from cdn.jsdelivr.net/npm/jspdf@2.5.2)
// When upgrading, re-download both files and update this comment.
//
// We capture from the live DOM via the onclone callback so the PDF is a
// pixel-faithful copy of what the user sees, with a few targeted cleanups:
//   - <input>/<textarea> elements are replaced with <span>/<div> carrying
//     their CURRENT live value plus the original element's computed styles.
//     html2canvas does not reliably render dirty input values; using static
//     spans sidesteps that and guarantees the text shows up.
//   - Field blocks whose user-editable value is empty are hidden (Ship To
//     when no recipient typed, Notes when empty, PO Number when empty, etc.)
//   - Tax and Amount Paid rows hide when their value is zero.
//   - "+ Line Item", "+ Discount", "+ Shipping", "+ Ship To", logo upload
//     placeholder, remove "x" controls, mode-swap arrows, the line-items
//     delete column, and the editable label hover/focus borders are all
//     stripped via the .invgen-capturing CSS scope (see tool.css).
//
// Final output is scaled to fit on ONE Letter page so the invoice never
// paginates. Footer reads "Made with argorobots.com" centered on the page.

import { trackEvent } from './tracker.js';

const BASE = (typeof window !== 'undefined' && window.INVGEN_BASE) || '';
const HTML2CANVAS_SRC = `${BASE}/invoice-generator/vendor/html2canvas.min.js`;
const JSPDF_SRC = `${BASE}/invoice-generator/vendor/jspdf.umd.min.js`;

// Document-type config (shared engine; invoice defaults when absent).
const DOC = (typeof window !== 'undefined' && window.DOC_CONFIG) || {};
const FILENAME_PREFIX = DOC.filenamePrefix || 'invoice';
const EVENT_PREFIX = DOC.eventPrefix || 'invgen';

let libsPromise = null;

function loadScript(src) {
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing && existing.dataset.invgenLoaded === '1') { resolve(); return; }
    const s = existing || document.createElement('script');
    s.onload = () => { s.dataset.invgenLoaded = '1'; resolve(); };
    s.onerror = () => reject(new Error(`Failed to load ${src}`));
    if (!existing) {
      s.src = src;
      document.head.appendChild(s);
    }
  });
}

function loadLibs() {
  if (libsPromise) return libsPromise;
  libsPromise = (async () => {
    const tasks = [];
    if (typeof window.html2canvas === 'undefined') tasks.push(loadScript(HTML2CANVAS_SRC));
    if (typeof window.jspdf === 'undefined') tasks.push(loadScript(JSPDF_SRC));
    if (tasks.length) await Promise.all(tasks);
    const h2c = window.html2canvas;
    const jsPDF = window.jspdf && window.jspdf.jsPDF;
    if (!h2c) throw new Error('html2canvas did not register on window');
    if (!jsPDF) throw new Error('jsPDF did not register on window');
    return { html2canvas: h2c, jsPDF };
  })().catch((err) => { libsPromise = null; throw err; });
  return libsPromise;
}

// Visual properties copied from each live input/textarea to its static
// replacement so the snapshot renders text identically.
const VISUAL_STYLE_PROPS = [
  'fontSize', 'fontWeight', 'fontFamily', 'fontStyle',
  'letterSpacing', 'color', 'textAlign', 'textTransform',
  'lineHeight', 'padding', 'margin',
  'width', 'minWidth', 'maxWidth',
  'height', 'minHeight', 'maxHeight',
  'border', 'borderRadius',
  'background', 'backgroundColor',
  'boxSizing', 'verticalAlign',
];

function prepareCloneForCapture(invoice, clonedDoc) {
  const clonedInvoice = clonedDoc.querySelector('.invoice');
  if (!clonedInvoice) return;

  const liveInputs = Array.from(invoice.querySelectorAll('input, textarea'));
  const cloneInputs = Array.from(clonedInvoice.querySelectorAll('input, textarea'));

  liveInputs.forEach((live, i) => {
    const cloned = cloneInputs[i];
    if (!cloned) return;

    // File inputs (the logo picker) carry a "C:\fakepath\..." value the
    // browser exposes for security. Never render that as text; the chosen
    // logo is drawn separately via [data-logo-rendered]. Drop the clone.
    if (live.type === 'file') { cloned.remove(); return; }

    const isTextarea = cloned.tagName === 'TEXTAREA';
    const value = live.value;
    const replacement = clonedDoc.createElement(isTextarea ? 'div' : 'span');

    if (cloned.className) replacement.className = cloned.className;
    Array.from(cloned.attributes).forEach((attr) => {
      if (attr.name.startsWith('data-')) replacement.setAttribute(attr.name, attr.value);
    });

    const liveStyles = window.getComputedStyle(live);
    VISUAL_STYLE_PROPS.forEach((p) => {
      replacement.style[p] = liveStyles[p];
    });
    replacement.style.display = isTextarea ? 'block' : 'inline-block';
    if (isTextarea) {
      replacement.style.whiteSpace = 'pre-wrap';
      replacement.style.wordWrap = 'break-word';
    }

    replacement.textContent = value;
    cloned.replaceWith(replacement);
  });

  // Hide blocks whose data-field value is empty (Ship To, Notes, PO Number, etc.).
  clonedInvoice.querySelectorAll('.meta-block, .meta-field-row, .notes-block').forEach((block) => {
    const field = block.querySelector('[data-field]');
    if (!field) return;
    if (!(field.textContent || '').trim()) block.style.display = 'none';
  });

  // Snug the "#" prefix against the invoice number in the PDF. On-screen the
  // number sits right-aligned in a 140px box (so a long number has room),
  // which leaves an awkward gap on the printed page. Override here so the
  // value sits flush next to the "#".
  const invNum = clonedInvoice.querySelector('[data-field="invoiceNumber"]');
  if (invNum) {
    invNum.style.width = 'auto';
    invNum.style.minWidth = '0';
    invNum.style.maxWidth = 'none';
    invNum.style.textAlign = 'left';
    // Match the prefix's padding/line-height exactly so "#" and "1" share a baseline.
    invNum.style.padding = '0';
    invNum.style.lineHeight = '1';
  }

  // Hide totals rows whose user-editable value is zero.
  [['.totals-tax', 'taxRatePercent'], ['.totals-paid', 'amountPaid']].forEach(([sel, fieldName]) => {
    const row = clonedInvoice.querySelector(sel);
    if (!row) return;
    const input = row.querySelector(`[data-field="${fieldName}"]`);
    if (!input) return;
    const v = parseFloat(input.textContent || '0');
    if (!v) row.style.display = 'none';
  });
}

export async function downloadPdf(state) {
  const invoice = document.querySelector('.invoice');
  if (!invoice) throw new Error('Invoice element not found');

  const { html2canvas, jsPDF } = await loadLibs();

  invoice.classList.add('invgen-capturing');

  try {
    const canvas = await html2canvas(invoice, {
      scale: 2,
      useCORS: true,
      logging: false,
      backgroundColor: '#ffffff',
      onclone: (clonedDoc) => prepareCloneForCapture(invoice, clonedDoc),
    });

    // Letter, in mm. Compute fit-to-page scaling so the invoice always
    // lands on a single page, centered horizontally with a small top margin.
    const pageWidthMm = 215.9;
    const pageHeightMm = 279.4;
    const marginMm = 8;
    const footerMm = 8;
    const availW = pageWidthMm - marginMm * 2;
    const availH = pageHeightMm - marginMm * 2 - footerMm;

    const imgAspect = canvas.width / canvas.height;
    const availAspect = availW / availH;
    let drawW, drawH;
    if (imgAspect >= availAspect) {
      drawW = availW;
      drawH = drawW / imgAspect;
    } else {
      drawH = availH;
      drawW = drawH * imgAspect;
    }

    const pdf = new jsPDF({ unit: 'mm', format: 'letter', orientation: 'portrait', compress: true });
    const x = (pageWidthMm - drawW) / 2;
    const y = marginMm;
    pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', x, y, drawW, drawH);

    pdf.setFontSize(8);
    pdf.setTextColor(140);
    // A link rather than plain text, so installs from it show on the referral links page.
    const footerText = 'Made with argorobots.com';
    const footerW = pdf.getTextWidth(footerText);
    pdf.textWithLink(footerText, (pageWidthMm - footerW) / 2, pageHeightMm - 5, { url: 'https://argorobots.com/downloads/?source=loop-invgen-pdf' });

    const number = (state.invoiceNumber || '').toString().trim() || 'draft';
    pdf.save(`${FILENAME_PREFIX}-${number}.pdf`);

    trackEvent(`${EVENT_PREFIX}_pdf_downloaded`, state.template);
  } finally {
    invoice.classList.remove('invgen-capturing');
  }
}
