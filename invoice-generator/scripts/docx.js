// invoice-generator/scripts/docx.js
// Generates the invoice as a downloadable Word (.docx) document using docx.js.
// docx.js is pinned at vendor/docx.umd.js (currently 9.5.0, downloaded from
// cdn.jsdelivr.net/npm/docx@9.5.0/dist/index.umd.cjs). When upgrading, re-download
// the file and update this comment.
//
// Word output is intentionally simpler than the PDF; we aim for a clean professional
// document, not a pixel-perfect replica of the on-screen invoice. Fonts default to
// Calibri/Times via the docx defaults, the template's accent color is not honored,
// and layout fidelity (column widths, exact spacing) is best-effort. If a user needs
// a polished, brand-matched invoice they should use the PDF download.

import { computeTotals } from './render.js';
import { formatMoney } from './currency.js';
import { trackEvent } from './tracker.js';

const BASE = (typeof window !== 'undefined' && window.INVGEN_BASE) || '';
const DOCX_SRC = `${BASE}/invoice-generator/vendor/docx.umd.js`;
const COUNTRIES_URL = `${BASE}/invoice-generator/data/countries.json`;

// Document-type config (shared engine; invoice defaults when absent).
const DOC = (typeof window !== 'undefined' && window.DOC_CONFIG) || {};
const FILENAME_PREFIX = DOC.filenamePrefix || 'invoice';
const EVENT_PREFIX = DOC.eventPrefix || 'invgen';
// Gate the invoice-only money fields. Default true preserves invoice output.
// A purchase order keeps Payment Terms but drops Amount Paid / Balance Due,
// so the two are tracked independently.
const SHOW_PAYMENT_TERMS = DOC.showPaymentTerms !== false;
const SHOW_PAYMENT_FIELDS = DOC.showPaymentFields !== false;
// Title-cased document noun for the Word document's <title> metadata.
const DOC_NOUN = FILENAME_PREFIX.charAt(0).toUpperCase() + FILENAME_PREFIX.slice(1);

// Letter (8.5") at default 1" margins = 6.5" content = 9360 twentieths-of-a-
// point (DXA). We use DXA for every table and cell width because the docx
// library's PERCENTAGE width writes OOXML pct values that Google Docs reads
// as 50ths-of-a-percent per spec, collapsing columns to one character wide.
// Word and LibreOffice are forgiving; Google Docs is not.
const DXA_FULL = 9360;
const dxa = (pct) => Math.round((DXA_FULL * pct) / 100);

// Module-level promise caches so repeat clicks reuse the same network requests.
let docxPromise = null;
let countriesPromise = null;

// Per-template visual cues for the Word document. The DOCX engine builds
// documents from scratch (it does not capture the styled DOM), so each
// template needs an explicit code branch. Each entry mirrors the on-screen
// scope of the same id in tool.css: the heading font and color match the
// "INVOICE" wordmark, and headerFill / headerRule add a single distinctive
// banner cue (slate band for Modern, navy band for Formal, etc.).
const TEMPLATE_STYLE = {
  classic: { headingFont: 'Arial',     headingColor: '1A1A1A', headerFill: null,     headerRule: null },
  modern:  { headingFont: 'Segoe UI',  headingColor: '0F172A', headerFill: 'F1F5F9', headerRule: '0F172A' },
  formal:  { headingFont: 'Georgia',   headingColor: '1E3A5F', headerFill: '1E3A5F', headerRule: null },
  elegant: { headingFont: 'Georgia',   headingColor: '6366F1', headerFill: null,     headerRule: '6366F1' },
  ribbon:  { headingFont: 'Open Sans', headingColor: '1A365D', headerFill: null,     headerRule: '1A365D' },
};

function styleForTemplate(templateId) {
  return TEMPLATE_STYLE[templateId] || TEMPLATE_STYLE.classic;
}

// Inject a <script> tag and resolve when it loads. Reject on error so the
// caller can surface a user-readable message (e.g. CDN blocked, ad blocker).
function loadScript(src) {
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[data-invgen-vendor="${src}"]`);
    if (existing) {
      if (existing.dataset.loaded === 'true') {
        resolve();
        return;
      }
      existing.addEventListener('load', () => resolve(), { once: true });
      existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
      return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.async = false;
    script.setAttribute('data-invgen-vendor', src);
    script.addEventListener('load', () => {
      script.dataset.loaded = 'true';
      resolve();
    }, { once: true });
    script.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
    document.head.appendChild(script);
  });
}

// Lazy-load docx.js on first call. The UMD wraps `factory(global.docx = {})`
// so a `window.docx` namespace is populated after the script tag executes.
function loadDocx() {
  if (docxPromise) return docxPromise;
  docxPromise = (async () => {
    await loadScript(DOCX_SRC);
    if (typeof window.docx === 'undefined') {
      throw new Error('docx global missing after script load');
    }
    return window.docx;
  })().catch((err) => {
    // Reset so a later click can retry after the user fixes their network.
    docxPromise = null;
    throw err;
  });
  return docxPromise;
}

function loadCountries() {
  if (countriesPromise) return countriesPromise;
  countriesPromise = fetch(COUNTRIES_URL)
    .then((r) => (r.ok ? r.json() : null))
    .catch(() => null);
  return countriesPromise;
}

function taxLabelForCountry(countriesData, countryCode) {
  if (!countriesData) return 'Tax';
  const entry = countriesData[countryCode] || countriesData.default;
  return (entry && entry.tax_label) || 'Tax';
}

// True when the row has no description, no quantity, and no rate. The user
// often leaves a trailing empty row in the editor; including it in the Word
// document would print a blank line item.
function isEmptyLineItem(li) {
  const description = (li && li.description ? String(li.description) : '').trim();
  const quantity = Number(li && li.quantity) || 0;
  const rate = Number(li && li.rate) || 0;
  return description === '' && quantity === 0 && rate === 0;
}

// Decode a "data:image/png;base64,..." URL to a Uint8Array ready for ImageRun.
// Returns null on any decode error so the caller can omit the logo cleanly.
function dataUrlToBytes(dataUrl) {
  try {
    const comma = String(dataUrl).indexOf(',');
    if (comma === -1) return null;
    const base64 = String(dataUrl).slice(comma + 1);
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes;
  } catch (_e) {
    return null;
  }
}

// Scale logo to fit within 160x80 (matching the PDF's `fit`), preserving aspect.
function logoDimensions(state) {
  const maxW = 160;
  const maxH = 80;
  const w = Number(state.logoWidth) || 0;
  const h = Number(state.logoHeight) || 0;
  if (w <= 0 || h <= 0) return { width: 120, height: 60 };
  const ratio = Math.min(maxW / w, maxH / h, 1);
  return {
    width: Math.max(1, Math.round(w * ratio)),
    height: Math.max(1, Math.round(h * ratio)),
  };
}

function paragraphsFromMultiline(text, opts, d) {
  const { Paragraph, TextRun } = d;
  const raw = typeof text === 'string' ? text : '';
  const lines = raw.length ? raw.split('\n') : [''];
  return lines.map((line) => new Paragraph({
    children: [new TextRun({ text: line, size: (opts && opts.size) || 20 })],
    spacing: { after: (opts && opts.afterEach) || 0 },
  }));
}

function buildHeader(state, d) {
  const { Paragraph, TextRun, Table, TableRow, TableCell, AlignmentType, WidthType, BorderStyle, ImageRun, HeadingLevel } = d;
  const style = styleForTemplate(state.template);

  const noBorder = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
  const cellBorders = {
    top: noBorder, bottom: noBorder, left: noBorder, right: noBorder,
  };

  // Left cell: logo (if present) then "From" block. We keep both in one cell
  // so the header reads top-down with the brand identity above the address.
  const leftChildren = [];
  if (state.logoDataUrl) {
    const bytes = dataUrlToBytes(state.logoDataUrl);
    if (bytes) {
      const dims = logoDimensions(state);
      leftChildren.push(new Paragraph({
        children: [new ImageRun({
          data: bytes,
          transformation: { width: dims.width, height: dims.height },
        })],
        spacing: { after: 120 },
      }));
    }
  }
  leftChildren.push(new Paragraph({
    children: [new TextRun({ text: (state.labels && state.labels.from) || 'From', bold: true, size: 18, color: '666666' })],
    spacing: { after: 40 },
  }));
  paragraphsFromMultiline(state.from || '', { size: 20 }, d).forEach((p) => leftChildren.push(p));

  // Right cell: document-title heading (e.g. "INVOICE" / "ESTIMATE") and
  // number, right-aligned. The heading follows the editable documentTitle
  // label so a renamed title flows through to the Word output.
  const headingText = (state.labels && state.labels.documentTitle) || DOC.documentTitle || 'INVOICE';
  const invoiceNumberText = state.invoiceNumber ? `# ${state.invoiceNumber}` : '';
  const rightChildren = [
    new Paragraph({
      children: [new TextRun({
        text: headingText,
        bold: true,
        size: 44,
        font: style.headingFont,
        color: style.headingColor,
      })],
      alignment: AlignmentType.RIGHT,
      heading: HeadingLevel.HEADING_1,
    }),
    new Paragraph({
      children: [new TextRun({ text: invoiceNumberText, size: 22 })],
      alignment: AlignmentType.RIGHT,
    }),
  ];

  return new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: [dxa(60), dxa(40)],
    borders: {
      top: noBorder, bottom: noBorder, left: noBorder, right: noBorder,
      insideHorizontal: noBorder, insideVertical: noBorder,
    },
    rows: [
      new TableRow({
        children: [
          new TableCell({
            width: { size: dxa(60), type: WidthType.DXA },
            borders: cellBorders,
            children: leftChildren,
          }),
          new TableCell({
            width: { size: dxa(40), type: WidthType.DXA },
            borders: {
              top: noBorder,
              bottom: style.headerRule
                ? { style: BorderStyle.SINGLE, size: 6, color: style.headerRule }
                : noBorder,
              left: noBorder,
              right: noBorder,
            },
            shading: style.headerFill
              ? { type: d.ShadingType.CLEAR, color: 'auto', fill: style.headerFill }
              : undefined,
            children: rightChildren,
          }),
        ],
      }),
    ],
  });
}

function buildPartiesAndMeta(state, d) {
  const { Paragraph, TextRun, Table, TableRow, TableCell, AlignmentType, WidthType, BorderStyle } = d;

  const noBorder = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
  const cellBorders = {
    top: noBorder, bottom: noBorder, left: noBorder, right: noBorder,
  };

  function partyStack(label, value) {
    const children = [
      new Paragraph({
        children: [new TextRun({ text: label, bold: true, size: 18, color: '666666' })],
        spacing: { after: 40 },
      }),
    ];
    paragraphsFromMultiline(value || '', { size: 20 }, d).forEach((p) => children.push(p));
    return children;
  }

  // Word labels follow the editable on-screen labels so a renamed field
  // (e.g. "Bill To" -> "Vendor" on a purchase order) carries into the export.
  const L = state.labels || {};

  const partyCells = [
    new TableCell({ width: { size: dxa(33), type: WidthType.DXA }, borders: cellBorders, children: partyStack(L.billTo || 'Bill To', state.billTo) }),
  ];
  // Ship To: include only when the textbox actually has content. A toggled-on
  // but empty Ship To should not print a stray label.
  const shipToText = state.shipTo == null ? '' : String(state.shipTo).trim();
  if (shipToText) {
    partyCells.push(new TableCell({
      width: { size: dxa(33), type: WidthType.DXA },
      borders: cellBorders,
      children: partyStack(L.shipTo || 'Ship To', shipToText),
    }));
  }

  // Metadata cell: Date / Payment Terms / Due Date / PO Number stacked rows.
  const metaChildren = [];
  const pushMeta = (label, value) => {
    if (value === null || value === undefined || value === '') return;
    metaChildren.push(new Paragraph({
      children: [
        new TextRun({ text: `${label}: `, bold: true, size: 18, color: '666666' }),
        new TextRun({ text: String(value), size: 20 }),
      ],
      alignment: AlignmentType.RIGHT,
      spacing: { after: 40 },
    }));
  };
  pushMeta(L.date || 'Date', state.date);
  if (SHOW_PAYMENT_TERMS) pushMeta(L.paymentTerms || 'Payment Terms', state.paymentTerms);
  pushMeta(L.dueDate || DOC.dueDateLabel || 'Due Date', state.dueDate);
  pushMeta(L.poNumber || 'PO Number', state.poNumber);

  const metaCellWidth = shipToText ? 34 : 67;
  partyCells.push(new TableCell({
    width: { size: dxa(metaCellWidth), type: WidthType.DXA },
    borders: cellBorders,
    children: metaChildren.length ? metaChildren : [new Paragraph('')],
  }));

  const partyColumnWidths = shipToText
    ? [dxa(33), dxa(33), dxa(34)]
    : [dxa(33), dxa(67)];

  return new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: partyColumnWidths,
    borders: {
      top: noBorder, bottom: noBorder, left: noBorder, right: noBorder,
      insideHorizontal: noBorder, insideVertical: noBorder,
    },
    rows: [new TableRow({ children: partyCells })],
  });
}

function buildLineItemsTable(state, d) {
  const { Paragraph, TextRun, Table, TableRow, TableCell, AlignmentType, WidthType, BorderStyle, ShadingType } = d;
  const fmt = (n) => formatMoney(n, state.currency, state.locale);

  const thinBorder = { style: BorderStyle.SINGLE, size: 4, color: 'DDDDDD' };
  const headerShading = { type: ShadingType.CLEAR, color: 'auto', fill: 'F3F5F8' };

  function headerCell(text, alignment) {
    return new TableCell({
      shading: headerShading,
      children: [new Paragraph({
        children: [new TextRun({ text, bold: true, size: 20 })],
        alignment: alignment || AlignmentType.LEFT,
      })],
    });
  }

  function bodyCell(text, alignment) {
    return new TableCell({
      children: [new Paragraph({
        children: [new TextRun({ text, size: 20 })],
        alignment: alignment || AlignmentType.LEFT,
      })],
    });
  }

  // Column headers follow the editable on-screen labels.
  const L = state.labels || {};
  const headerRow = new TableRow({
    tableHeader: true,
    children: [
      headerCell(L.description || 'Description'),
      headerCell(L.quantity || 'Quantity', AlignmentType.RIGHT),
      headerCell(L.rate || 'Rate', AlignmentType.RIGHT),
      headerCell(L.amount || 'Amount', AlignmentType.RIGHT),
    ],
  });

  const dataRows = [];
  (state.lineItems || []).forEach((li) => {
    if (isEmptyLineItem(li)) return;
    const qty = Number(li.quantity) || 0;
    const rate = Number(li.rate) || 0;
    const amount = qty * rate;
    dataRows.push(new TableRow({
      children: [
        bodyCell(li.description || ''),
        bodyCell(String(qty), AlignmentType.RIGHT),
        bodyCell(fmt(rate), AlignmentType.RIGHT),
        bodyCell(fmt(amount), AlignmentType.RIGHT),
      ],
    }));
  });

  return new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: [dxa(50), dxa(10), dxa(20), dxa(20)],
    borders: {
      top: thinBorder, bottom: thinBorder, left: thinBorder, right: thinBorder,
      insideHorizontal: thinBorder, insideVertical: thinBorder,
    },
    rows: [headerRow, ...dataRows],
  });
}

function buildTotals(state, totals, taxLabel, d) {
  const { Paragraph, TextRun, Table, TableRow, TableCell, AlignmentType, WidthType, BorderStyle } = d;
  const fmt = (n) => formatMoney(n, state.currency, state.locale);

  const noBorder = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
  const cellBorders = {
    top: noBorder, bottom: noBorder, left: noBorder, right: noBorder,
  };

  const rows = [];
  const pushRow = (label, value, opts = {}) => {
    const bold = !!opts.bold;
    rows.push(new TableRow({
      children: [
        new TableCell({
          width: { size: dxa(70), type: WidthType.DXA },
          borders: cellBorders,
          children: [new Paragraph({
            children: [new TextRun({ text: label, bold, size: 20, color: bold ? '000000' : '555555' })],
            alignment: AlignmentType.RIGHT,
          })],
        }),
        new TableCell({
          width: { size: dxa(30), type: WidthType.DXA },
          borders: cellBorders,
          children: [new Paragraph({
            children: [new TextRun({ text: value, bold, size: 20 })],
            alignment: AlignmentType.RIGHT,
          })],
        }),
      ],
    }));
  };

  // Totals labels follow the editable on-screen labels.
  const L = state.labels || {};
  pushRow(L.subtotal || 'Subtotal', fmt(totals.subtotal));
  if (state.discount !== null && state.discount !== undefined) {
    pushRow(L.discount || 'Discount', fmt(-totals.discount));
  }
  if (state.shipping !== null && state.shipping !== undefined) {
    pushRow(L.shipping || 'Shipping', fmt(totals.shipping));
  }
  pushRow(taxLabel, fmt(totals.tax));
  pushRow(L.total || 'Total', fmt(totals.total), { bold: true });

  const amountPaid = Number(state.amountPaid) || 0;
  if (SHOW_PAYMENT_FIELDS && amountPaid !== 0) {
    pushRow(L.amountPaid || 'Amount Paid', fmt(amountPaid));
    pushRow(L.balanceDue || 'Balance Due', fmt(totals.balanceDue), { bold: true });
  }

  return new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: [dxa(70), dxa(30)],
    borders: {
      top: noBorder, bottom: noBorder, left: noBorder, right: noBorder,
      insideHorizontal: noBorder, insideVertical: noBorder,
    },
    rows,
  });
}

function buildNotesAndTerms(state, d) {
  const { Paragraph, TextRun } = d;
  const blocks = [];
  const notes = typeof state.notes === 'string' ? state.notes.trim() : '';
  const terms = typeof state.terms === 'string' ? state.terms.trim() : '';

  function section(label, value) {
    const out = [];
    out.push(new Paragraph({
      children: [new TextRun({ text: label, bold: true, size: 18, color: '666666' })],
      spacing: { before: 200, after: 40 },
    }));
    paragraphsFromMultiline(value, { size: 20, afterEach: 40 }, d).forEach((p) => out.push(p));
    return out;
  }

  if (notes) section('Notes', notes).forEach((p) => blocks.push(p));
  if (terms) section('Terms', terms).forEach((p) => blocks.push(p));
  return blocks;
}

function buildBottomRow(state, totals, taxLabel, d) {
  const { Paragraph, Table, TableRow, TableCell, WidthType, BorderStyle } = d;

  const noBorder = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
  const cellBorders = { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder };

  // Left cell: Notes + Terms. Right cell: totals table nested inside.
  const notesAndTerms = buildNotesAndTerms(state, d);
  const leftChildren = notesAndTerms.length ? notesAndTerms : [new Paragraph('')];
  const rightChildren = [buildTotals(state, totals, taxLabel, d)];

  return new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: [dxa(55), dxa(45)],
    borders: {
      top: noBorder, bottom: noBorder, left: noBorder, right: noBorder,
      insideHorizontal: noBorder, insideVertical: noBorder,
    },
    rows: [
      new TableRow({
        children: [
          new TableCell({ width: { size: dxa(55), type: WidthType.DXA }, borders: cellBorders, children: leftChildren }),
          new TableCell({ width: { size: dxa(45), type: WidthType.DXA }, borders: cellBorders, children: rightChildren }),
        ],
      }),
    ],
  });
}

// Optional acceptance / signature block: a heading plus two ruled lines (to
// sign and to date) with captions beneath. Returns [] when not toggled on.
function buildSignature(state, d) {
  if (state.signature == null) return [];
  const { Paragraph, TextRun, Table, TableRow, TableCell, WidthType, BorderStyle } = d;
  const L = state.labels || {};

  const noBorder = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
  const cellBorders = { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder };
  const ruleBorder = { style: BorderStyle.SINGLE, size: 4, color: '000000' };

  // A signing cell: an empty paragraph carrying a bottom border (the line to
  // sign on), then a small caption beneath it.
  function signCell(caption, widthPct) {
    return new TableCell({
      width: { size: dxa(widthPct), type: WidthType.DXA },
      borders: cellBorders,
      children: [
        new Paragraph({ border: { bottom: ruleBorder }, spacing: { before: 260, after: 40 }, children: [new TextRun({ text: '' })] }),
        new Paragraph({ children: [new TextRun({ text: caption, size: 16, color: '666666' })] }),
      ],
    });
  }

  const heading = new Paragraph({
    children: [new TextRun({ text: L.signatureLabel || 'Accepted by', bold: true, size: 20 })],
    spacing: { after: 80 },
  });

  const table = new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: [dxa(55), dxa(10), dxa(35)],
    borders: {
      top: noBorder, bottom: noBorder, left: noBorder, right: noBorder,
      insideHorizontal: noBorder, insideVertical: noBorder,
    },
    rows: [new TableRow({
      children: [
        signCell(L.signatureName || 'Signature', 55),
        new TableCell({ width: { size: dxa(10), type: WidthType.DXA }, borders: cellBorders, children: [new Paragraph('')] }),
        signCell(L.signatureDate || 'Date', 35),
      ],
    })],
  });

  return [heading, table];
}

function buildDocument(state, totals, taxLabel, d) {
  const { Document, Paragraph, TextRun, Footer, AlignmentType } = d;

  const spacer = (after) => new Paragraph({ children: [new TextRun({ text: '' })], spacing: { after: after || 200 } });

  const children = [
    buildHeader(state, d),
    spacer(200),
    buildPartiesAndMeta(state, d),
    spacer(200),
    buildLineItemsTable(state, d),
    spacer(200),
    buildBottomRow(state, totals, taxLabel, d),
  ];

  const signatureEls = buildSignature(state, d);
  if (signatureEls.length) {
    children.push(spacer(320));
    signatureEls.forEach((el) => children.push(el));
  }

  return new Document({
    creator: 'Argo Books',
    title: state.invoiceNumber ? `${DOC_NOUN} ${state.invoiceNumber}` : DOC_NOUN,
    sections: [{
      properties: {},
      footers: {
        default: new Footer({
          children: [new Paragraph({
            children: [new window.docx.ExternalHyperlink({
              link: 'https://argorobots.com/downloads/?source=loop-invgen-word',
              children: [new TextRun({ text: 'Made with argorobots.com', size: 18, color: '999999' })],
            })],
            alignment: AlignmentType.CENTER,
          })],
        }),
      },
      children,
    }],
  });
}

export async function downloadDocx(state) {
  // Kick off countries load in parallel with docx so the first download is faster.
  const countriesPromiseLocal = loadCountries();
  const d = await loadDocx();
  const countriesData = await countriesPromiseLocal;

  const totals = computeTotals(state);
  // Prefer the editable on-screen tax label (what the user sees and the PDF
  // shows) so Word matches; fall back to the country-derived label.
  const taxLabel = (state.labels && state.labels.tax)
    ? state.labels.tax
    : taxLabelForCountry(countriesData, state.country);
  const doc = buildDocument(state, totals, taxLabel, d);

  const blob = await d.Packer.toBlob(doc);

  const safeNumber = (state.invoiceNumber && String(state.invoiceNumber).trim()) || 'draft';
  const filename = `${FILENAME_PREFIX}-${safeNumber}.docx`;

  const url = URL.createObjectURL(blob);
  try {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
  } finally {
    // Allow the browser to start the download before revoking.
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }
  trackEvent(`${EVENT_PREFIX}_docx_downloaded`, state.template || '');
}
