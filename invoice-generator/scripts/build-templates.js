// invoice-generator/scripts/build-templates.js
// Generates static .xlsx and .docx invoice templates per visual style
// (classic, modern, formal, elegant, ribbon). The .xlsx files are
// committed to invoice-generator/templates/ and served as direct downloads
// from /invoice-generator/templates/{style}.xlsx. The .docx files are
// dev-only artifacts intended for upload to Google Drive (where each is
// converted to a Google Doc and the resulting "Make a copy" URL is recorded
// in invoice-generator/data/template-assets.json).
//
// Run with:
//   npm run build:templates
//
// Uses the npm packages `exceljs` and `docx` (the same docx library the
// browser-side generator uses, consumed via npm here for Node).

import ExcelJS from 'exceljs';
import {
  Document, Paragraph, TextRun, Table, TableRow, TableCell,
  AlignmentType, WidthType, BorderStyle, ShadingType, HeadingLevel,
  Footer, Packer, ExternalHyperlink,
} from 'docx';
import { writeFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = resolve(__dirname, '..', 'templates');

// Per-style visual cues. The first half of each entry (label, headingFont,
// headingColor, headerFill, accentColor) is consumed by buildDocx and mirrors
// the browser-side TEMPLATE_STYLE map in docx.js. The xlsx* keys are
// consumed by buildXlsx for the polished spreadsheet layout.
const STYLES = {
  classic: {
    label: 'Classic',
    headingFont: 'Arial',
    headingColor: '1A1A1A',
    headerFill: null,
    accentColor: '1A1A1A',
    xlsxBodyFont: 'Arial',
    xlsxHeadingFont: 'Arial',
    xlsxInvoiceColor: '1A1A1A',
    xlsxItemsHeaderFill: '1A1A1A',
    xlsxItemsHeaderText: 'FFFFFF',
    xlsxTotalRowFill: 'F3F4F6',
    xlsxTotalRowText: '111827',
    xlsxAccent: '111827',
    xlsxAccentRuleUnderHeader: false,
  },
  modern: {
    label: 'Modern',
    headingFont: 'Arial',
    headingColor: '2C7A7B',
    headerFill: null,
    accentColor: '2C7A7B',
    xlsxBodyFont: 'Arial',
    xlsxHeadingFont: 'Arial',
    xlsxInvoiceColor: '0F172A',
    xlsxItemsHeaderFill: '0F172A',
    xlsxItemsHeaderText: 'FFFFFF',
    xlsxTotalRowFill: 'F3F4F6',
    xlsxTotalRowText: '0F172A',
    xlsxAccent: '0F172A',
    xlsxAccentRuleUnderHeader: false,
  },
  formal: {
    label: 'Formal',
    headingFont: 'Arial',
    headingColor: '1A1A1A',
    headerFill: null,
    accentColor: 'CCCCCC',
    xlsxBodyFont: 'Arial',
    xlsxHeadingFont: 'Georgia',
    xlsxInvoiceColor: '1E3A5F',
    xlsxItemsHeaderFill: '1E3A5F',
    xlsxItemsHeaderText: 'FFFFFF',
    xlsxTotalRowFill: '1E3A5F',
    xlsxTotalRowText: 'FFFFFF',
    xlsxAccent: '1E3A5F',
    xlsxAccentRuleUnderHeader: false,
  },
  elegant: {
    label: 'Elegant',
    // Georgia serif + indigo accent, mirroring the live tool's Elegant
    // template. The docx export marks the INVOICE wordmark with a thin
    // indigo bottom rule on the right header cell (handled in
    // buildHeaderTable via the same path as Ribbon).
    headingFont: 'Georgia',
    headingColor: '6366F1',
    headerFill: null,
    accentColor: '6366F1',
    xlsxBodyFont: 'Arial',
    xlsxHeadingFont: 'Georgia',
    xlsxInvoiceColor: '4338CA',
    xlsxItemsHeaderFill: 'FFFFFF',
    xlsxItemsHeaderText: '4338CA',
    xlsxTotalRowFill: 'EEF2FF',
    xlsxTotalRowText: '4338CA',
    xlsxAccent: '4338CA',
    xlsxAccentRuleUnderHeader: false,
  },
  ribbon: {
    label: 'Ribbon',
    headingFont: 'Georgia',
    headingColor: '1A1A1A',
    headerFill: null,
    accentColor: '0A2540',
    xlsxBodyFont: 'Arial',
    xlsxHeadingFont: 'Georgia',
    xlsxInvoiceColor: '0A2540',
    xlsxItemsHeaderFill: 'E0F2FE',
    xlsxItemsHeaderText: '0A2540',
    xlsxTotalRowFill: 'F0F9FF',
    xlsxTotalRowText: '0A2540',
    xlsxAccent: '0A2540',
    xlsxAccentRuleUnderHeader: true,
  },
};

// ---------- Excel ----------

// Color palette shared across the polished XLSX layout. Tweak these once to
// shift the muted-text feel without touching the per-style accent colors.
const XLSX_INK    = '111827';   // primary text (gray-900)
const XLSX_INK_2  = '4B5563';   // secondary text (gray-600)
const XLSX_MUTED  = '9CA3AF';   // placeholder italics (gray-400)
const XLSX_HAIR   = 'E5E7EB';   // line item separators (gray-200)
const XLSX_LOGO_B = 'D1D5DB';   // logo dashed border (gray-300)

async function buildXlsx(styleId) {
  const style = STYLES[styleId];
  const workbook = new ExcelJS.Workbook();
  workbook.creator = 'Argo Books';
  workbook.title = `${style.label} Invoice Template`;
  const ws = workbook.addWorksheet('Invoice', {
    pageSetup: {
      paperSize: 1,
      orientation: 'portrait',
      margins: { left: 0.5, right: 0.5, top: 0.5, bottom: 0.5, header: 0.3, footer: 0.3 },
    },
    views: [{ showGridLines: false }],
  });

  // Six-column layout (A-F). Wider Description (A:C merged), tight Qty/Rate,
  // a comfortable Amount column. Totals labels live in D:E, values in F so
  // they sit directly under the Amount column.
  ws.getColumn('A').width = 22;
  ws.getColumn('B').width = 16;
  ws.getColumn('C').width = 12;
  ws.getColumn('D').width = 10;
  ws.getColumn('E').width = 13;
  ws.getColumn('F').width = 16;

  // ---- HEADER --------------------------------------------------------------
  ws.getRow(1).height = 32;

  // Logo placeholder: A1:C5 merged, dashed gray border, soft centered text.
  ws.mergeCells('A1:C5');
  const logoCell = ws.getCell('A1');
  logoCell.value = 'Your logo';
  logoCell.alignment = { horizontal: 'center', vertical: 'middle' };
  logoCell.font = { name: style.xlsxBodyFont, size: 12, italic: true, color: { argb: XLSX_MUTED } };
  logoCell.border = {
    top:    { style: 'dashed', color: { argb: XLSX_LOGO_B } },
    left:   { style: 'dashed', color: { argb: XLSX_LOGO_B } },
    bottom: { style: 'dashed', color: { argb: XLSX_LOGO_B } },
    right:  { style: 'dashed', color: { argb: XLSX_LOGO_B } },
  };

  // INVOICE word: D1:F1 merged, big and right-aligned.
  ws.mergeCells('D1:F1');
  const invoiceCell = ws.getCell('D1');
  invoiceCell.value = 'INVOICE';
  invoiceCell.alignment = { horizontal: 'right', vertical: 'middle' };
  invoiceCell.font = {
    name: style.xlsxHeadingFont,
    size: 28,
    bold: true,
    color: { argb: style.xlsxInvoiceColor },
  };

  // Invoice number: D2:F2 merged, "Invoice #1042" right-aligned in muted text.
  ws.mergeCells('D2:F2');
  const numCell = ws.getCell('D2');
  numCell.value = 'Invoice #1042';
  numCell.alignment = { horizontal: 'right' };
  numCell.font = { name: style.xlsxBodyFont, size: 11, color: { argb: XLSX_INK_2 } };

  // Ribbon-only: thin accent rule across all 6 columns just below the header.
  if (style.xlsxAccentRuleUnderHeader) {
    ['A', 'B', 'C', 'D', 'E', 'F'].forEach((col) => {
      ws.getCell(`${col}5`).border = {
        ...(ws.getCell(`${col}5`).border || {}),
        bottom: { style: 'thin', color: { argb: style.xlsxAccent } },
      };
    });
  }

  // ---- From + Metadata + Bill To ------------------------------------------
  const sectionLabelFont = { name: style.xlsxBodyFont, size: 9, bold: true, color: { argb: XLSX_INK_2 } };
  const bodyBoldFont     = { name: style.xlsxBodyFont, size: 12, bold: true, color: { argb: XLSX_INK } };
  const bodyMutedFont    = { name: style.xlsxBodyFont, size: 11, italic: true, color: { argb: XLSX_MUTED } };

  ws.getCell('A6').value = 'FROM';
  ws.getCell('A6').font = sectionLabelFont;
  ws.getCell('A7').value = 'Your business name';
  ws.getCell('A7').font = bodyBoldFont;
  ws.getCell('A8').value = '123 Main Street';
  ws.getCell('A8').font = bodyMutedFont;
  ws.getCell('A9').value = 'City, State 00000';
  ws.getCell('A9').font = bodyMutedFont;

  // Metadata: D:E label (merged), F value
  const meta = [
    { row: 6, label: 'Date',      value: 'YYYY-MM-DD',  filled: true  },
    { row: 7, label: 'Due Date',  value: 'YYYY-MM-DD',  filled: true  },
    { row: 8, label: 'Terms',     value: 'Net 30',      filled: true  },
    { row: 9, label: 'PO Number', value: '',            filled: false },
  ];
  meta.forEach((m) => {
    ws.mergeCells(`D${m.row}:E${m.row}`);
    const lc = ws.getCell(`D${m.row}`);
    lc.value = m.label;
    lc.alignment = { horizontal: 'right' };
    lc.font = sectionLabelFont;

    const vc = ws.getCell(`F${m.row}`);
    vc.value = m.value;
    vc.alignment = { horizontal: 'right' };
    vc.font = {
      name: style.xlsxBodyFont,
      size: 11,
      italic: !m.filled || /YYYY/.test(m.value),
      color: { argb: m.value ? (/YYYY/.test(m.value) ? XLSX_MUTED : XLSX_INK) : XLSX_MUTED },
    };
  });

  ws.getCell('A11').value = 'BILL TO';
  ws.getCell('A11').font = sectionLabelFont;
  ws.getCell('A12').value = 'Client name';
  ws.getCell('A12').font = bodyBoldFont;
  ws.getCell('A13').value = 'Client address';
  ws.getCell('A13').font = bodyMutedFont;

  // ---- Line items table ---------------------------------------------------
  ws.getRow(15).height = 26;
  ws.mergeCells('A15:C15');
  const itemHeaders = [
    { ref: 'A15', text: 'DESCRIPTION', align: 'left'  },
    { ref: 'D15', text: 'QTY',         align: 'right' },
    { ref: 'E15', text: 'RATE',        align: 'right' },
    { ref: 'F15', text: 'AMOUNT',      align: 'right' },
  ];
  itemHeaders.forEach((h) => {
    const c = ws.getCell(h.ref);
    c.value = h.text;
    c.alignment = { horizontal: h.align, vertical: 'middle', indent: h.align === 'left' ? 1 : 0 };
    c.font = {
      name: style.xlsxHeadingFont,
      size: 10,
      bold: true,
      color: { argb: style.xlsxItemsHeaderText },
    };
    if (style.xlsxItemsHeaderFill !== 'FFFFFF') {
      c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: style.xlsxItemsHeaderFill } };
    }
    c.border = {
      bottom: { style: 'medium', color: { argb: style.xlsxAccent } },
    };
  });

  for (let r = 16; r <= 25; r++) {
    ws.getRow(r).height = 22;
    ws.mergeCells(`A${r}:C${r}`);

    const descCell = ws.getCell(`A${r}`);
    descCell.alignment = { vertical: 'middle', indent: 1 };
    descCell.font = { name: style.xlsxBodyFont, size: 11, color: { argb: XLSX_INK } };

    const qtyCell = ws.getCell(`D${r}`);
    qtyCell.numFmt = '0';
    qtyCell.alignment = { horizontal: 'right', vertical: 'middle' };
    qtyCell.font = { name: style.xlsxBodyFont, size: 11, color: { argb: XLSX_INK } };

    const rateCell = ws.getCell(`E${r}`);
    // Custom currency format with empty third section so blank rows render blank.
    rateCell.numFmt = '"$"#,##0.00;-"$"#,##0.00;';
    rateCell.alignment = { horizontal: 'right', vertical: 'middle' };
    rateCell.font = { name: style.xlsxBodyFont, size: 11, color: { argb: XLSX_INK } };

    // Plain arithmetic formula. Zero results show as blank via the number
    // format trick, so SUM works cleanly without text propagation.
    const amtCell = ws.getCell(`F${r}`);
    amtCell.value = { formula: `D${r}*E${r}` };
    amtCell.numFmt = '"$"#,##0.00;-"$"#,##0.00;';
    amtCell.alignment = { horizontal: 'right', vertical: 'middle' };
    amtCell.font = { name: style.xlsxBodyFont, size: 11, color: { argb: XLSX_INK } };

    // Thin separator under each row across the full table width.
    ['A', 'B', 'C', 'D', 'E', 'F'].forEach((col) => {
      const cell = ws.getCell(`${col}${r}`);
      cell.border = {
        ...(cell.border || {}),
        bottom: { style: 'hair', color: { argb: XLSX_HAIR } },
      };
    });
  }

  // ---- Totals box ---------------------------------------------------------
  // exceljs prepends the leading "=" itself; formula strings here must NOT
  // include it (otherwise Google Sheets sees "==SUM(...)" and errors out).
  const totals = [
    { row: 27, label: 'Subtotal',     formula: 'SUM(F16:F25)', emphasis: 'normal' },
    { row: 28, label: 'Tax Rate (%)', value: 0, numFmt: '0.00', emphasis: 'normal' },
    { row: 29, label: 'Tax',          formula: 'F27*(F28/100)', emphasis: 'normal' },
    { row: 30, label: 'TOTAL',        formula: 'F27+F29',       emphasis: 'total'  },
    { row: 31, label: 'Amount Paid',  value: 0,                 emphasis: 'normal' },
    { row: 32, label: 'Balance Due',  formula: 'F30-F31',       emphasis: 'total'  },
  ];
  totals.forEach((t) => {
    ws.mergeCells(`D${t.row}:E${t.row}`);
    ws.getRow(t.row).height = t.emphasis === 'total' ? 24 : 20;

    const labelCell = ws.getCell(`D${t.row}`);
    labelCell.value = t.label;
    labelCell.alignment = { horizontal: 'right', vertical: 'middle' };
    labelCell.font = {
      name: style.xlsxBodyFont,
      size: t.emphasis === 'total' ? 12 : 10,
      bold: t.emphasis === 'total',
      color: { argb: t.emphasis === 'total' ? style.xlsxTotalRowText : XLSX_INK_2 },
    };

    const valCell = ws.getCell(`F${t.row}`);
    if (t.formula) {
      valCell.value = { formula: t.formula };
    } else {
      valCell.value = t.value;
    }
    valCell.numFmt = t.numFmt || '"$"#,##0.00';
    valCell.alignment = { horizontal: 'right', vertical: 'middle' };
    valCell.font = {
      name: style.xlsxBodyFont,
      size: t.emphasis === 'total' ? 12 : 10,
      bold: t.emphasis === 'total',
      color: { argb: t.emphasis === 'total' ? style.xlsxTotalRowText : XLSX_INK },
    };

    // Total + Balance Due rows: fill across D:E and F to read as one band.
    if (t.emphasis === 'total' && style.xlsxTotalRowFill !== 'FFFFFF') {
      const fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: style.xlsxTotalRowFill } };
      labelCell.fill = fill;
      valCell.fill = fill;
    }

    // Top border on the Total row so it visually closes the totals stack.
    if (t.row === 30) {
      const topRule = { top: { style: 'medium', color: { argb: style.xlsxAccent } } };
      labelCell.border = { ...(labelCell.border || {}), ...topRule };
      valCell.border = { ...(valCell.border || {}), ...topRule };
    }
  });

  // ---- Notes + Terms ------------------------------------------------------
  ws.getCell('A35').value = 'NOTES';
  ws.getCell('A35').font = sectionLabelFont;
  ws.mergeCells('A36:F37');
  const notesCell = ws.getCell('A36');
  notesCell.value = 'Add any notes for the client here.';
  notesCell.alignment = { vertical: 'top', wrapText: true };
  notesCell.font = bodyMutedFont;

  ws.getCell('A39').value = 'TERMS';
  ws.getCell('A39').font = sectionLabelFont;
  ws.mergeCells('A40:F41');
  const termsCell = ws.getCell('A40');
  termsCell.value = 'Payment due within 30 days.';
  termsCell.alignment = { vertical: 'top', wrapText: true };
  termsCell.font = bodyMutedFont;

  // ---- Footer -------------------------------------------------------------
  ws.mergeCells('A45:F45');
  const footerCell = ws.getCell('A45');
  footerCell.value = { text: 'Made with argorobots.com', hyperlink: 'https://argorobots.com/downloads/?source=loop-invgen-excel' };
  footerCell.alignment = { horizontal: 'center' };
  footerCell.font = { name: style.xlsxBodyFont, size: 9, italic: true, color: { argb: XLSX_MUTED } };

  // Set print area so users get a clean one-page PDF when they File > Print.
  ws.pageSetup.printArea = 'A1:F45';

  const outPath = resolve(OUT_DIR, `${styleId}.xlsx`);
  await workbook.xlsx.writeFile(outPath);
  console.log(`Wrote ${outPath}`);
}

// ---------- Word ----------

// Letter (8.5") at default 1" margins = 6.5" content = 9360 twentieths-of-a-
// point (DXA). We use DXA explicitly for every table and cell width because
// WidthType.PERCENTAGE writes OOXML "pct" values that Google Docs interprets
// as 50ths-of-a-percent per spec (LibreOffice and Word are forgiving and
// auto-recover; Google Docs collapses columns to one character wide).
const DXA_FULL = 9360;
const dxa = (pct) => Math.round((DXA_FULL * pct) / 100);

const noBorder = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const cellBorders = { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder };
const tableBorders = {
  top: noBorder, bottom: noBorder, left: noBorder, right: noBorder,
  insideHorizontal: noBorder, insideVertical: noBorder,
};

function buildHeaderTable(style, styleId) {
  const leftChildren = [
    new Paragraph({
      children: [new TextRun({ text: 'From', bold: true, size: 18, color: '666666' })],
      spacing: { after: 40 },
    }),
    new Paragraph({ children: [new TextRun({ text: '[Your business name]', size: 20 })] }),
    new Paragraph({ children: [new TextRun({ text: '[Street address]', size: 20 })] }),
    new Paragraph({ children: [new TextRun({ text: '[City, postcode]', size: 20 })] }),
  ];

  const rightChildren = [
    new Paragraph({
      children: [new TextRun({
        text: 'INVOICE',
        bold: true,
        size: 44,
        font: style.headingFont,
        color: style.headingColor,
      })],
      alignment: AlignmentType.RIGHT,
      heading: HeadingLevel.HEADING_1,
    }),
    new Paragraph({
      children: [new TextRun({ text: '# 1042', size: 22 })],
      alignment: AlignmentType.RIGHT,
    }),
  ];

  const rightCellBorders = {
    top: noBorder,
    // Ribbon and Elegant both anchor their wordmark with a thin accent
    // rule under the INVOICE cell. Other styles either fill the cell
    // (none currently) or leave it borderless.
    bottom: (styleId === 'ribbon' || styleId === 'elegant')
      ? { style: BorderStyle.SINGLE, size: 6, color: style.accentColor }
      : noBorder,
    left: noBorder,
    right: noBorder,
  };

  return new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: [dxa(60), dxa(40)],
    borders: tableBorders,
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
            borders: rightCellBorders,
            shading: style.headerFill
              ? { type: ShadingType.CLEAR, color: 'auto', fill: style.headerFill }
              : undefined,
            children: rightChildren,
          }),
        ],
      }),
    ],
  });
}

function buildPartiesTable() {
  function block(label, lines) {
    const children = [
      new Paragraph({
        children: [new TextRun({ text: label, bold: true, size: 18, color: '666666' })],
        spacing: { after: 40 },
      }),
    ];
    lines.forEach((line) => {
      children.push(new Paragraph({ children: [new TextRun({ text: line, size: 20 })] }));
    });
    return children;
  }

  function metaLine(label, value) {
    return new Paragraph({
      children: [
        new TextRun({ text: `${label}: `, bold: true, size: 18, color: '666666' }),
        new TextRun({ text: value, size: 20 }),
      ],
      alignment: AlignmentType.RIGHT,
      spacing: { after: 40 },
    });
  }

  return new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: [dxa(50), dxa(50)],
    borders: tableBorders,
    rows: [
      new TableRow({
        children: [
          new TableCell({
            width: { size: dxa(50), type: WidthType.DXA },
            borders: cellBorders,
            children: block('Bill To', ['[Client name]', '[Client address]']),
          }),
          new TableCell({
            width: { size: dxa(50), type: WidthType.DXA },
            borders: cellBorders,
            children: [
              metaLine('Date', '[YYYY-MM-DD]'),
              metaLine('Due Date', '[YYYY-MM-DD]'),
              metaLine('Terms', 'Net 30'),
            ],
          }),
        ],
      }),
    ],
  });
}

function buildLineItemsTable(style, styleId) {
  const thinBorder = { style: BorderStyle.SINGLE, size: 4, color: 'DDDDDD' };

  // Column splits: Description 50%, Quantity 10%, Rate 20%, Amount 20%.
  const colWidths = [dxa(50), dxa(10), dxa(20), dxa(20)];

  function headerCell(text, alignment, widthDxa) {
    let fill = 'F3F5F8';
    let textColor = '1A1A1A';
    if (styleId === 'classic') { fill = '1A1A1A'; textColor = 'FFFFFF'; }
    return new TableCell({
      width: { size: widthDxa, type: WidthType.DXA },
      shading: { type: ShadingType.CLEAR, color: 'auto', fill },
      children: [new Paragraph({
        children: [new TextRun({ text, bold: true, size: 20, color: textColor })],
        alignment: alignment || AlignmentType.LEFT,
      })],
    });
  }

  function bodyCell(text, alignment, widthDxa) {
    return new TableCell({
      width: { size: widthDxa, type: WidthType.DXA },
      children: [new Paragraph({
        children: [new TextRun({ text, size: 20 })],
        alignment: alignment || AlignmentType.LEFT,
      })],
    });
  }

  const headerRow = new TableRow({
    tableHeader: true,
    children: [
      headerCell('Description', AlignmentType.LEFT, colWidths[0]),
      headerCell('Quantity', AlignmentType.RIGHT, colWidths[1]),
      headerCell('Rate', AlignmentType.RIGHT, colWidths[2]),
      headerCell('Amount', AlignmentType.RIGHT, colWidths[3]),
    ],
  });

  const sampleRows = [
    ['Service or product description', '1', '$0.00', '$0.00'],
    ['', '', '', ''],
    ['', '', '', ''],
  ].map(([d, q, r, a]) => new TableRow({
    children: [
      bodyCell(d, AlignmentType.LEFT, colWidths[0]),
      bodyCell(q, AlignmentType.RIGHT, colWidths[1]),
      bodyCell(r, AlignmentType.RIGHT, colWidths[2]),
      bodyCell(a, AlignmentType.RIGHT, colWidths[3]),
    ],
  }));

  return new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: colWidths,
    borders: {
      top: thinBorder, bottom: thinBorder, left: thinBorder, right: thinBorder,
      insideHorizontal: thinBorder, insideVertical: thinBorder,
    },
    rows: [headerRow, ...sampleRows],
  });
}

function buildTotalsTable() {
  function row(label, value, bold) {
    return new TableRow({
      children: [
        new TableCell({
          width: { size: dxa(70), type: WidthType.DXA },
          borders: cellBorders,
          children: [new Paragraph({
            children: [new TextRun({
              text: label, bold: !!bold, size: 20,
              color: bold ? '000000' : '555555',
            })],
            alignment: AlignmentType.RIGHT,
          })],
        }),
        new TableCell({
          width: { size: dxa(30), type: WidthType.DXA },
          borders: cellBorders,
          children: [new Paragraph({
            children: [new TextRun({ text: value, bold: !!bold, size: 20 })],
            alignment: AlignmentType.RIGHT,
          })],
        }),
      ],
    });
  }

  return new Table({
    width: { size: DXA_FULL, type: WidthType.DXA },
    columnWidths: [dxa(70), dxa(30)],
    borders: tableBorders,
    rows: [
      row('Subtotal', '$0.00'),
      row('Tax', '$0.00'),
      row('Total', '$0.00', true),
    ],
  });
}

async function buildDocx(styleId) {
  const style = STYLES[styleId];
  const spacer = (after) => new Paragraph({
    children: [new TextRun({ text: '' })],
    spacing: { after: after || 200 },
  });

  const doc = new Document({
    creator: 'Argo Books',
    title: `${style.label} Invoice Template`,
    sections: [{
      properties: {},
      footers: {
        default: new Footer({
          children: [new Paragraph({
            children: [new ExternalHyperlink({
              link: 'https://argorobots.com/downloads/?source=loop-invgen-word',
              children: [new TextRun({ text: 'Made with argorobots.com', size: 18, color: '999999' })],
            })],
            alignment: AlignmentType.CENTER,
          })],
        }),
      },
      children: [
        buildHeaderTable(style, styleId),
        spacer(200),
        buildPartiesTable(),
        spacer(200),
        buildLineItemsTable(style, styleId),
        spacer(200),
        buildTotalsTable(),
      ],
    }],
  });

  const buffer = await Packer.toBuffer(doc);
  const outPath = resolve(OUT_DIR, `${styleId}.docx`);
  await writeFile(outPath, buffer);
  console.log(`Wrote ${outPath}`);
}

async function main() {
  await mkdir(OUT_DIR, { recursive: true });
  const styles = Object.keys(STYLES);
  for (const styleId of styles) {
    await buildXlsx(styleId);
    await buildDocx(styleId);
  }
  console.log(`Done. Generated ${styles.length} .xlsx and ${styles.length} .docx files in ${OUT_DIR}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
