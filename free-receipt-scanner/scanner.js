// free-receipt-scanner/scanner.js
// Tool-isolated client logic: fingerprint, Turnstile, upload, scan request,
// review rendering (two-column with receipt preview + zoom), inline editing,
// CSV/JSON export, and the daily-limit upsell.
//
// FingerprintJS is loaded with a NON-FATAL dynamic import: if its CDN is blocked
// or down, the tool still works (the server enforces IP + global caps anyway).
// A static top-level import would abort the whole module on failure and leave
// every button dead.

const cfg = window.RS_CONFIG || {};
const base = cfg.base || ''; // '' in production, '/argo-books-website' on Laragon
const stage = document.getElementById('rs-stage');
const fileInput = document.getElementById('rs-file-input');
const dropzone = document.getElementById('rs-dropzone');
const scansLeftEl = document.getElementById('rs-scans-left');

let turnstileToken = '';
let turnstileWidgetId = null;
let currentReceipt = null;
let bulkResults = null;   // [{receipt, imgUrl, name}] while reviewing a bulk batch
let bulkIndex = 0;
let bulkNote = '';
const tokenWaiters = [];
const RS_MAX_BULK = 10;
let scanPass = ''; // short-lived server pass; reused so bulk scans run concurrently
let scanAbort = null;     // AbortController for the in-flight scan(s)
let scanCancelled = false;
const fpPromise = (async () => {
  try {
    const { default: FingerprintJS } = await import('https://openfpcdn.io/fingerprintjs/v4');
    const fp = await FingerprintJS.load();
    const r = await fp.get();
    return r.visitorId;
  } catch (e) {
    return ''; // fingerprint is a nice-to-have; server still enforces IP + global caps
  }
})();

function setState(name) { stage.setAttribute('data-state', name); }
function show(id, on) { const el = document.getElementById(id); if (el) el.hidden = !on; }
function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
function money(n) { return (Number(n) || 0).toFixed(2); }

// --- Local "scans left today" display (server is the real gate) ---
function todayKey() { return 'rs_scans_' + new Date().toISOString().slice(0, 10); }
function scansUsed() { return parseInt(localStorage.getItem(todayKey()) || '0', 10); }
function bumpScans() { localStorage.setItem(todayKey(), String(scansUsed() + 1)); refreshScansLeft(); }
function refreshScansLeft() {
  const left = Math.max(0, (cfg.perVisitor || 0) - scansUsed());
  if (scansLeftEl) scansLeftEl.textContent = String(left);
}
refreshScansLeft();

// Close the download dropdown on any outside click.
document.addEventListener('click', () => {
  const m = document.getElementById('rs-dl-menu');
  if (m && !m.hidden) {
    m.hidden = true;
    document.getElementById('rs-dl-btn')?.setAttribute('aria-expanded', 'false');
  }
});

// --- Turnstile mount ---
// api.js was loaded with ?onload=onloadTurnstileCallback&render=explicit, so
// Cloudflare calls window.onloadTurnstileCallback when it is ready. Because this
// module and api.js can finish in either order, we both expose the callback AND
// try an immediate render in case turnstile is already present.
function renderTurnstile() {
  if (!cfg.turnstileSiteKey || !window.turnstile || turnstileWidgetId !== null) return;
  turnstileWidgetId = window.turnstile.render('#rs-turnstile', {
    sitekey: cfg.turnstileSiteKey,
    callback: (t) => { turnstileToken = t; tokenWaiters.splice(0).forEach((fn) => fn(t)); },
    'error-callback': () => { turnstileToken = ''; },
    'expired-callback': () => { turnstileToken = ''; },
  });
}
window.onloadTurnstileCallback = renderTurnstile;
renderTurnstile();

// Turnstile tokens are single-use. Get the current token (waiting for the widget
// if needed), and reset after each request so the next scan gets a fresh one.
// With no widget (e.g. local dev, where the server skips Turnstile), resolves ''.
function waitForToken(timeoutMs = 9000) {
  if (turnstileToken) return Promise.resolve(turnstileToken);
  if (!cfg.turnstileSiteKey || !window.turnstile || turnstileWidgetId === null) return Promise.resolve('');
  return new Promise((resolve) => {
    let done = false;
    const fin = (t) => { if (!done) { done = true; resolve(t || ''); } };
    tokenWaiters.push(fin);
    setTimeout(() => fin(turnstileToken), timeoutMs);
  });
}
function resetToken() {
  turnstileToken = '';
  if (window.turnstile && turnstileWidgetId !== null) { try { window.turnstile.reset(turnstileWidgetId); } catch (e) {} }
}

// Attach auth to a request: reuse the server scan pass if we have one (lets
// requests run concurrently), otherwise a single-use Turnstile token. Returns
// true if a token was used, so the caller knows to reset it afterward.
async function appendAuth(fd) {
  if (scanPass) { fd.append('scan_pass', scanPass); return false; }
  const t = await waitForToken();
  if (t) fd.append('turnstile_token', t);
  return true;
}

// --- File selection ---
// The whole dropzone (icon, text, and the "Choose a file" pill) opens the picker.
dropzone?.addEventListener('click', () => fileInput.click());
dropzone?.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); } });
fileInput?.addEventListener('change', () => handleFiles(fileInput.files));
document.getElementById('rs-sample')?.addEventListener('click', (e) => { e.preventDefault(); loadSample(); });
document.getElementById('rs-cancel')?.addEventListener('click', cancelScan);

// Cancel an in-progress scan (single or bulk) and return to the upload state.
function cancelScan() {
  scanCancelled = true;
  if (scanAbort) { try { scanAbort.abort(); } catch (e) {} }
  show('rs-scanning', false); // hide the overlay (it's outside the stage)
  setBulkOverlay(false);
  resetToUpload();
}
function revokeItems(items) { items.forEach((i) => { if (i.imgUrl) URL.revokeObjectURL(i.imgUrl); }); }

// One image -> single scan; several -> bulk.
function handleFiles(fileList) {
  const files = [...(fileList || [])].filter((f) => f && f.type && f.type.startsWith('image/'));
  if (!files.length) return;
  if (files.length === 1) { bulkResults = null; scan(files[0]); }
  else bulkScan(files.slice(0, RS_MAX_BULK), files.length);
}

['dragover', 'dragenter'].forEach(ev => dropzone?.addEventListener(ev, (e) => {
  e.preventDefault(); dropzone.classList.add('rs-dragover');
}));
['dragleave', 'drop'].forEach(ev => dropzone?.addEventListener(ev, (e) => {
  e.preventDefault(); dropzone.classList.remove('rs-dragover');
}));
dropzone?.addEventListener('drop', (e) => handleFiles(e.dataTransfer?.files));

// --- The scan request ---
async function scan(file) {
  bulkResults = null; bulkNote = '';
  setBulkOverlay(false);
  setState('scanning'); show('rs-upload', false); show('rs-scanning', true);
  show('rs-review', false); show('rs-limit', false);

  // Keep an object URL of the uploaded image for the review preview pane.
  if (window.__rsImg && window.__rsImg.startsWith('blob:')) URL.revokeObjectURL(window.__rsImg);
  window.__rsImg = URL.createObjectURL(file);

  scanCancelled = false;
  scanAbort = new AbortController();
  const fd = new FormData();
  fd.append('receipt', file);
  fd.append('fingerprint', await fpPromise);
  const usedToken = await appendAuth(fd);

  let res, data;
  try {
    res = await fetch(base + '/api/receipt/scan.php', { method: 'POST', body: fd, signal: scanAbort.signal });
    data = await res.json();
  } catch (e) {
    if (usedToken) resetToken();
    if (scanCancelled || e.name === 'AbortError') return; // cancelled: UI already reset
    return rsError('Something went wrong. Please try again.');
  }
  if (usedToken) resetToken();
  if (data && data.scan_pass) scanPass = data.scan_pass;

  if (res.ok && data.ok) {
    bumpScans();
    renderReview(data.receipt);
    setState('review'); show('rs-scanning', false); show('rs-review', true);
    return;
  }

  if (res.status === 429) {
    renderLimit(data);
    setState('limit'); show('rs-scanning', false); show('rs-limit', true);
    return;
  }

  rsError(data?.message || 'That scan did not work. Please try a clearer photo.');
}

// --- Bulk scanning: one request per receipt, with live progress. The first
// receipt authenticates with Turnstile and the server returns a reusable pass;
// the rest run CONCURRENTLY using that pass. Each successful scan counts toward
// the daily limit; receipts beyond it come back 429 and are marked skipped. ---
async function bulkScan(files, totalSelected) {
  bulkResults = null; bulkNote = '';
  scanCancelled = false;
  scanAbort = new AbortController();
  const items = files.map((f) => ({ file: f, name: f.name, imgUrl: URL.createObjectURL(f), status: 'queued', receipt: null, msg: '' }));
  setState('scanning'); show('rs-upload', false); show('rs-review', false); show('rs-limit', false); show('rs-scanning', true);
  showBulkProgress(items, totalSelected);

  async function scanOne(it) {
    if (scanCancelled) return;
    it.status = 'scanning'; updateBulkProgress(items);
    try {
      const fd = new FormData();
      fd.append('receipt', it.file);
      fd.append('fingerprint', await fpPromise);
      const usedToken = await appendAuth(fd);
      const res = await fetch(base + '/api/receipt/scan.php', { method: 'POST', body: fd, signal: scanAbort.signal });
      const data = await res.json();
      if (usedToken) resetToken();
      if (data && data.scan_pass) scanPass = data.scan_pass;
      if (res.ok && data.ok) { it.status = 'done'; it.receipt = data.receipt; bumpScans(); }
      else if (res.status === 429) { it.status = 'limit'; it.msg = data.message || ''; }
      else { it.status = 'error'; it.msg = (data && data.message) || 'Failed'; }
    } catch (e) {
      if (e.name !== 'AbortError') resetToken();
      it.status = scanCancelled ? 'queued' : 'error';
      if (!scanCancelled) it.msg = 'Network error';
    }
    if (!scanCancelled) updateBulkProgress(items);
  }

  // First receipt obtains the pass; the remainder fan out concurrently.
  await scanOne(items[0]);
  if (scanCancelled) { revokeItems(items); return; }
  const rest = items.slice(1);
  const concurrency = (scanPass || !cfg.turnstileSiteKey) ? 3 : 1;
  let idx = 0;
  const worker = async () => { while (idx < rest.length) { if (scanCancelled) return; await scanOne(rest[idx++]); } };
  await Promise.all(Array.from({ length: Math.min(concurrency, Math.max(rest.length, 1)) }, worker));
  if (scanCancelled) { revokeItems(items); return; }
  finishBulk(items);
}

function finishBulk(items) {
  const ok = items.filter((i) => i.status === 'done');
  items.forEach((i) => { if (i.status !== 'done' && i.imgUrl) URL.revokeObjectURL(i.imgUrl); });
  show('rs-scanning', false);
  setBulkOverlay(false);

  if (!ok.length) {
    // All failed: show the upsell only if it was actually the daily limit;
    // genuine read failures return to upload with a message so they can retry.
    if (items.some((i) => i.status === 'limit')) {
      renderLimit({ error: 'rate_limited', message: "You've used your free scans for today." });
      setState('limit'); show('rs-upload', false); show('rs-limit', true);
    } else {
      rsError("We couldn't read those receipts. Try clearer, well-lit photos.");
    }
    return;
  }

  bulkResults = ok.map((i) => ({ receipt: i.receipt, imgUrl: i.imgUrl, name: i.name }));
  bulkIndex = 0;
  const skipped = items.length - ok.length;
  const limited = items.some((i) => i.status === 'limit');
  bulkNote = skipped ? `${skipped} of ${items.length} couldn't be scanned${limited ? ' because the daily limit was reached' : ''}.` : '';
  window.__rsImg = bulkResults[0].imgUrl;
  renderReview(bulkResults[0].receipt);
  setState('review'); show('rs-upload', false); show('rs-review', true);
}

function bulkGo(delta) {
  if (!bulkResults) return;
  syncFromForm(); // current receipt object is shared with bulkResults[bulkIndex], so edits persist
  bulkIndex = Math.max(0, Math.min(bulkResults.length - 1, bulkIndex + delta));
  window.__rsImg = bulkResults[bulkIndex].imgUrl;
  renderReview(bulkResults[bulkIndex].receipt);
}

// --- Bulk progress overlay ---
function setBulkOverlay(isBulk) {
  const b = document.getElementById('rs-bulk');
  if (b) b.hidden = !isBulk;
  if (!isBulk) {
    const t = document.getElementById('rs-overlay-title');
    const s = document.getElementById('rs-overlay-sub');
    if (t) t.textContent = 'Reading your receipt…';
    if (s) s.textContent = 'Pulling out every line, tax, and total. This takes a few seconds.';
  }
}
function showBulkProgress(items, totalSelected) {
  const t = document.getElementById('rs-overlay-title');
  const s = document.getElementById('rs-overlay-sub');
  if (t) t.textContent = `Scanning ${items.length} receipt${items.length > 1 ? 's' : ''}…`;
  const extra = totalSelected > items.length ? ` (first ${items.length} of ${totalSelected})` : '';
  if (s) s.textContent = `This can take a moment${extra}.`;
  const b = document.getElementById('rs-bulk');
  if (b) b.hidden = false;
  updateBulkProgress(items);
}
function updateBulkProgress(items) {
  const done = items.filter((i) => i.status === 'done' || i.status === 'error' || i.status === 'limit').length;
  const fill = document.getElementById('rs-bulk-fill');
  if (fill) fill.style.width = Math.round((done / items.length) * 100) + '%';
  const list = document.getElementById('rs-bulk-list');
  if (!list) return;
  const label = (i) => i.status === 'done' ? '✓' : i.status === 'scanning' ? '…' : i.status === 'limit' ? 'limit' : i.status === 'error' ? 'failed' : '';
  list.innerHTML = items.map((i) => `<li class="rs-bulk-item rs-bulk-${i.status}"><span class="rs-bulk-name">${esc(i.name)}</span><span class="rs-bulk-st">${label(i)}</span></li>`).join('');
}

function rsError(msg) {
  setBulkOverlay(false);
  setState('upload'); show('rs-scanning', false); show('rs-upload', true);
  alert(msg);
  resetToken();
  fileInput.value = '';
}

function resetToUpload() {
  if (bulkResults) bulkResults.forEach((b) => { if (b.imgUrl) URL.revokeObjectURL(b.imgUrl); });
  bulkResults = null; bulkIndex = 0; bulkNote = '';
  setState('upload'); show('rs-review', false); show('rs-limit', false); show('rs-upload', true);
  resetToken(); fileInput.value = '';
}

// --- Review rendering ---
function renderReview(r) {
  currentReceipt = r;
  const conf = Math.round((r.confidence || 0) * 100);
  const badge = (r.confidence || 0) >= 0.85 ? ['rs-badge-high', 'High']
              : (r.confidence || 0) >= 0.6 ? ['rs-badge-med', 'Medium']
              : ['rs-badge-low', 'Low'];
  const discountTotal = (r.discounts || []).reduce((s, d) => s + (Number(d.amount) || 0), 0);

  const items = (r.lineItems || []).map((li, i) => `
    <tr data-i="${i}">
      <td><input class="rs-li-desc" value="${esc(li.description)}"></td>
      <td><input class="rs-li-qty" type="number" step="any" value="${esc(li.quantity)}"></td>
      <td><input class="rs-li-unit" type="number" step="any" value="${esc(li.unitPrice)}"></td>
      <td><input class="rs-li-total" type="number" step="any" value="${esc(li.totalPrice)}"></td>
      <td><button class="rs-li-del" title="Remove item" data-i="${i}">&times;</button></td>
    </tr>`).join('');

  const bulk = bulkResults && bulkResults.length > 1;
  document.getElementById('rs-review').innerHTML = `
    ${bulk ? `<div class="rs-carousel">
      <button type="button" id="rs-prev" class="rs-cbtn" ${bulkIndex === 0 ? 'disabled' : ''} aria-label="Previous receipt">‹</button>
      <span class="rs-cpos">Receipt ${bulkIndex + 1} of ${bulkResults.length}${bulkResults[bulkIndex].name ? ` · ${esc(bulkResults[bulkIndex].name)}` : ''}</span>
      <button type="button" id="rs-next" class="rs-cbtn" ${bulkIndex === bulkResults.length - 1 ? 'disabled' : ''} aria-label="Next receipt">›</button>
    </div>` : ''}
    ${bulkNote ? `<div class="rs-bulk-note">${esc(bulkNote)}</div>` : ''}
    <div class="rs-conf">
      <span class="rs-conf-label">Extraction confidence</span>
      <span class="rs-conf-val">${conf}%</span>
      <span class="rs-badge ${badge[0]}">${badge[1]}</span>
      <span class="rs-conf-tag">Scanned with Argo Books</span>
    </div>

    <div class="rs-review-cols">
      <div class="rs-preview">
        <div class="rs-preview-scroll">
          <img id="rs-preview-img" src="${window.__rsImg || ''}" alt="Your receipt">
        </div>
        <div class="rs-preview-toolbar">
          <button id="rs-zoom-out" class="rs-zoom-btn" title="Zoom out" aria-label="Zoom out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M8 11h6M21 21l-4.3-4.3"/></svg></button>
          <input type="range" id="rs-zoom-range" min="20" max="400" value="100" aria-label="Zoom level">
          <button id="rs-zoom-in" class="rs-zoom-btn" title="Zoom in" aria-label="Zoom in"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M11 8v6M8 11h6M21 21l-4.3-4.3"/></svg></button>
          <span id="rs-zoom-pct" class="rs-zoom-pct">100%</span>
          <button id="rs-zoom-fit" class="rs-zoom-btn" title="Fit to width" aria-label="Fit to width"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3m13-5v3a2 2 0 0 1-2 2h-3"/></svg></button>
        </div>
      </div>

      <div class="rs-data">
        <h3 class="rs-section-title">Extracted information</h3>
        <div class="rs-fields">
          <label class="rs-fld s6">Supplier<input id="rs-f-supplier" value="${esc(r.supplierName)}"></label>
          <label class="rs-fld s3">Date<input id="rs-f-date" value="${esc(r.transactionDate)}" placeholder="YYYY-MM-DD"></label>
          <label class="rs-fld s3"><span>Total <span class="rs-req">*</span></span><input id="rs-f-total" type="number" step="any" value="${esc(r.totalAmount)}"></label>
          <label class="rs-fld s2">Subtotal<input id="rs-f-subtotal" type="number" step="any" value="${esc(r.subtotal)}"></label>
          <label class="rs-fld s2">Tax<input id="rs-f-tax" type="number" step="any" value="${esc(r.taxTotal)}"></label>
          <label class="rs-fld s2">Discount<input id="rs-f-discount" type="number" step="any" value="${discountTotal.toFixed(2)}"></label>
          <label class="rs-fld s3">Currency<input id="rs-f-currency" value="${esc(r.currencyCode)}"></label>
          <label class="rs-fld s3">Payment<input id="rs-f-payment" value="${esc(r.paymentMethod)}"></label>
        </div>

        <div class="rs-li-head">
          <h3 class="rs-section-title">Line items</h3>
          <button id="rs-li-add" class="rs-btn rs-btn-ghost rs-btn-sm">+ Add item</button>
        </div>
        <table class="rs-li-table">
          <thead><tr><th>Product</th><th>Qty</th><th>Unit price</th><th>Total</th><th></th></tr></thead>
          <tbody id="rs-li-body">${items}</tbody>
        </table>
      </div>
    </div>

    <div class="rs-review-foot">
      <span class="rs-foot-info">Review the extracted data, then download it.</span>
      <div class="rs-foot-actions">
        <button id="rs-again" class="rs-btn rs-btn-ghost">Scan another</button>
        <div class="rs-dl">
          <button id="rs-dl-btn" class="rs-btn rs-btn-primary" aria-haspopup="true" aria-expanded="false">Download <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="m6 9 6 6 6-6"/></svg></button>
          <div class="rs-dl-menu" id="rs-dl-menu" hidden>
            ${bulk ? `<button type="button" data-fmt="allxlsx">Download all (Excel)</button>
            <button type="button" data-fmt="allcsv">Download all (CSV)</button>
            <button type="button" data-fmt="alljson">Download all (JSON)</button>
            <div class="rs-dl-sep"></div>` : ''}
            <button type="button" data-fmt="xlsx">${bulk ? 'This receipt (Excel)' : 'Download Excel'}</button>
            <button type="button" data-fmt="csv">${bulk ? 'This receipt (CSV)' : 'Download CSV'}</button>
            <button type="button" data-fmt="json">${bulk ? 'This receipt (JSON)' : 'Download JSON'}</button>
            <button type="button" data-fmt="copy">Copy JSON</button>
          </div>
        </div>
      </div>
    </div>

    <div class="rs-upsell">
      Want this saved and filed as an expense automatically?
      <a href="${base}/downloads/?source=receipt-scanner-review">Get Argo Books for free</a>.
    </div>`;

  wireReview();
  wireZoom();
}

// Zoom the receipt preview with a bottom slider (matches the desktop app).
function wireZoom() {
  const img = document.getElementById('rs-preview-img');
  const range = document.getElementById('rs-zoom-range');
  const pct = document.getElementById('rs-zoom-pct');
  if (!img || !range) return;
  const apply = (v) => {
    v = Math.max(20, Math.min(400, Math.round(v)));
    img.style.width = v + '%';
    range.value = v;
    pct.textContent = v + '%';
  };
  document.getElementById('rs-zoom-out').addEventListener('click', () => apply(parseInt(range.value, 10) - 20));
  document.getElementById('rs-zoom-in').addEventListener('click', () => apply(parseInt(range.value, 10) + 20));
  document.getElementById('rs-zoom-fit').addEventListener('click', () => apply(100));
  range.addEventListener('input', () => apply(parseInt(range.value, 10)));
  apply(100);
}

function syncFromForm() {
  if (!currentReceipt) return;
  currentReceipt.supplierName = document.getElementById('rs-f-supplier').value;
  currentReceipt.transactionDate = document.getElementById('rs-f-date').value;
  currentReceipt.currencyCode = document.getElementById('rs-f-currency').value;
  currentReceipt.paymentMethod = document.getElementById('rs-f-payment').value;
  currentReceipt.subtotal = parseFloat(document.getElementById('rs-f-subtotal').value) || 0;
  currentReceipt.taxTotal = parseFloat(document.getElementById('rs-f-tax').value) || 0;
  currentReceipt.totalAmount = parseFloat(document.getElementById('rs-f-total').value) || 0;
  const disc = document.getElementById('rs-f-discount');
  if (disc) currentReceipt.discountTotal = parseFloat(disc.value) || 0;
  currentReceipt.lineItems = [...document.querySelectorAll('#rs-li-body tr')].map(tr => ({
    description: tr.querySelector('.rs-li-desc').value,
    quantity: parseFloat(tr.querySelector('.rs-li-qty').value) || 0,
    unitPrice: parseFloat(tr.querySelector('.rs-li-unit').value) || 0,
    totalPrice: parseFloat(tr.querySelector('.rs-li-total').value) || 0,
    confidence: 1,
  }));
}

function wireReview() {
  document.getElementById('rs-again').addEventListener('click', resetToUpload);
  document.getElementById('rs-prev')?.addEventListener('click', () => bulkGo(-1));
  document.getElementById('rs-next')?.addEventListener('click', () => bulkGo(1));
  document.getElementById('rs-li-add').addEventListener('click', () => {
    syncFromForm();
    currentReceipt.lineItems.push({ description: '', quantity: 1, unitPrice: 0, totalPrice: 0, confidence: 1 });
    renderReview(currentReceipt);
  });
  document.querySelectorAll('.rs-li-del').forEach(b => b.addEventListener('click', () => {
    syncFromForm();
    currentReceipt.lineItems.splice(parseInt(b.dataset.i, 10), 1);
    renderReview(currentReceipt);
  }));

  // Download dropdown (CSV / JSON / copy).
  const dlBtn = document.getElementById('rs-dl-btn');
  const dlMenu = document.getElementById('rs-dl-menu');
  dlBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const opening = dlMenu.hidden;
    dlMenu.hidden = !opening;
    dlBtn.setAttribute('aria-expanded', String(opening));
  });
  dlMenu.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
    syncFromForm();
    const json = JSON.stringify(currentReceipt, null, 2);
    const fmt = b.dataset.fmt;
    if (fmt === 'allxlsx') downloadXlsx(bulkResults.map((x) => x.receipt), 'receipts');
    else if (fmt === 'xlsx') downloadXlsx([currentReceipt], 'receipt');
    else if (fmt === 'allcsv') downloadBlob(allCsv(), 'receipts.csv', 'text/csv');
    else if (fmt === 'alljson') downloadBlob(JSON.stringify(bulkResults.map((x) => x.receipt), null, 2), 'receipts.json', 'application/json');
    else if (fmt === 'csv') downloadBlob(toCsv(currentReceipt), 'receipt.csv', 'text/csv');
    else if (fmt === 'json') downloadBlob(json, 'receipt.json', 'application/json');
    else navigator.clipboard.writeText(json);
    dlMenu.hidden = true;
    dlBtn.setAttribute('aria-expanded', 'false');
  }));
}

// Build the .xlsx server-side (Summary + Line items sheets) and download it.
async function downloadXlsx(receipts, baseName) {
  try {
    const res = await fetch(base + '/api/receipt/export.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ receipts }),
    });
    if (!res.ok) throw new Error('export failed');
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = baseName + '.xlsx'; a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    alert("Couldn't build the Excel file. Try CSV instead.");
  }
}

// Combine every reviewed receipt into one CSV (each preceded by a label row).
function allCsv() {
  return bulkResults.map((b, i) => `# Receipt ${i + 1}${b.name ? ' - ' + b.name : ''}\r\n` + toCsv(b.receipt)).join('\r\n\r\n');
}

function toCsv(r) {
  const cell = (v) => {
    let s = String(v == null ? '' : v);
    // Neutralize formula injection (=, +, -, @), but keep real numbers numeric.
    if (/^[=+\-@\t\r]/.test(s) && !/^[+-]?\d/.test(s)) s = "'" + s;
    return /[",\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
  };
  const discount = r.discountTotal != null ? r.discountTotal : (r.discounts || []).reduce((s, d) => s + (Number(d.amount) || 0), 0);
  const rows = [
    ['Field', 'Value'], ['Supplier', r.supplierName || ''], ['Date', r.transactionDate || ''],
    ['Subtotal', money(r.subtotal)], ['Tax', money(r.taxTotal)], ['Discount', money(discount)], ['Total', money(r.totalAmount)],
    ['Currency', r.currencyCode || ''], ['Payment Method', r.paymentMethod || ''], [],
    ['Type', 'Description', 'Quantity', 'Unit Price', 'Total'],
    ...(r.lineItems || []).map(li => ['Item', li.description, li.quantity, money(li.unitPrice), money(li.totalPrice)]),
    ...(r.taxes || []).map(t => ['Tax', t.name, '', '', money(t.amount)]),
    ...(r.discounts || []).map(d => ['Discount', d.name, '', '', money(-(Number(d.amount) || 0))]),
  ];
  return rows.map(row => row.map(cell).join(',')).join('\r\n');
}

function downloadBlob(text, name, type) {
  const url = URL.createObjectURL(new Blob([text], { type }));
  const a = document.createElement('a'); a.href = url; a.download = name; a.click();
  URL.revokeObjectURL(url);
}

// --- Sample receipt (renders the review with no API call) ---
function loadSample() {
  bulkResults = null; bulkNote = '';
  const r = sampleReceipt();
  if (window.__rsImg && window.__rsImg.startsWith('blob:')) URL.revokeObjectURL(window.__rsImg);
  window.__rsImg = sampleReceiptImage(r);
  setState('review'); show('rs-upload', false); show('rs-scanning', false); show('rs-limit', false);
  renderReview(r); show('rs-review', true);
}

function sampleReceipt() {
  return {
    supplierName: 'Maple Grocery Co.',
    transactionDate: '2026-06-18',
    subtotal: 42.31,
    taxes: [{ name: 'GST', amount: 2.12 }, { name: 'PST', amount: 2.96 }],
    taxTotal: 5.08,
    discounts: [{ name: 'Member savings', amount: 1.50 }],
    totalAmount: 45.89,
    currencyCode: 'CAD',
    paymentMethod: 'Credit Card',
    confidence: 0.96,
    lineItems: [
      { description: 'Organic Bananas', quantity: 1, unitPrice: 1.89, totalPrice: 1.89, confidence: 0.97 },
      { description: 'Whole Milk 2L', quantity: 1, unitPrice: 4.49, totalPrice: 4.49, confidence: 0.95 },
      { description: 'Sourdough Loaf', quantity: 1, unitPrice: 5.25, totalPrice: 5.25, confidence: 0.94 },
      { description: 'Free-Range Eggs (12)', quantity: 1, unitPrice: 6.99, totalPrice: 6.99, confidence: 0.96 },
      { description: 'Aged Cheddar 400g', quantity: 1, unitPrice: 8.49, totalPrice: 8.49, confidence: 0.93 },
      { description: 'Roma Tomatoes', quantity: 2, unitPrice: 2.20, totalPrice: 4.40, confidence: 0.90 },
      { description: 'Olive Oil 500ml', quantity: 1, unitPrice: 10.80, totalPrice: 10.80, confidence: 0.92 },
    ],
  };
}

// Render a photo-realistic thermal receipt from the sample data: cream paper on
// a surface, drop shadow, faint paper noise, vignette, and a slight camera angle.
// Generated from the data so the preview always matches the extracted fields.
function sampleReceiptImage(r) {
  const xml = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const PW = 250, L = 18, R = PW - 18, cx = PW / 2;
  const mono = `font-family="'Courier New', 'Courier', monospace"`;
  const dash = (yy) => `<line x1="${L}" y1="${yy}" x2="${R}" y2="${yy}" stroke="#a59f90" stroke-width="1" stroke-dasharray="2 3"/>`;

  let y, c = '';
  c += `<text x="${cx}" y="30" text-anchor="middle" font-size="15" font-weight="700" ${mono} fill="#2a2a2a">${xml(r.supplierName.toUpperCase())}</text>`;
  c += `<text x="${cx}" y="44" text-anchor="middle" font-size="8" ${mono} fill="#555">123 MAIN ST · ANYTOWN</text>`;
  c += `<text x="${cx}" y="55" text-anchor="middle" font-size="8" ${mono} fill="#555">TEL (555) 012-3456</text>`;
  y = 72; c += dash(y); y += 15;
  c += `<text x="${L}" y="${y}" font-size="8" ${mono} fill="#444">${xml(r.transactionDate)}  14:32</text>`;
  c += `<text x="${R}" y="${y}" text-anchor="end" font-size="8" ${mono} fill="#444">RCPT #0428</text>`;
  y += 13; c += dash(y); y += 18;

  r.lineItems.forEach((li) => {
    let name = li.description.toUpperCase();
    if (name.length > 20) name = name.slice(0, 20);
    c += `<text x="${L}" y="${y}" font-size="9.5" ${mono} fill="#222">${xml(name)}</text>`;
    c += `<text x="${R}" y="${y}" text-anchor="end" font-size="9.5" ${mono} fill="#222">${li.totalPrice.toFixed(2)}</text>`;
    y += 16;
  });
  y += 2; c += dash(y); y += 17;

  const row = (label, val, big) => {
    const fs = big ? 12 : 9.5, fw = big ? 'font-weight="700"' : '', col = big ? '#111' : '#333';
    const s = `<text x="${L}" y="${y}" font-size="${fs}" ${mono} ${fw} fill="${col}">${xml(label)}</text>`
            + `<text x="${R}" y="${y}" text-anchor="end" font-size="${fs}" ${mono} ${fw} fill="${col}">${xml(val)}</text>`;
    y += big ? 20 : 15; return s;
  };
  c += row('SUBTOTAL', r.subtotal.toFixed(2));
  (r.discounts || []).forEach((d) => { c += row(d.name.toUpperCase(), '-' + d.amount.toFixed(2)); });
  r.taxes.forEach((t) => { c += row(t.name, t.amount.toFixed(2)); });
  y += 2; c += dash(y); y += 18;
  c += row('TOTAL ' + r.currencyCode, r.totalAmount.toFixed(2), true);
  y += 2;
  c += `<text x="${L}" y="${y}" font-size="8.5" ${mono} fill="#444">${xml((r.paymentMethod || '').toUpperCase())}</text>`;
  c += `<text x="${R}" y="${y}" text-anchor="end" font-size="8.5" ${mono} fill="#444">${r.totalAmount.toFixed(2)}</text>`;
  y += 22;
  c += `<text x="${cx}" y="${y}" text-anchor="middle" font-size="8.5" ${mono} fill="#444">THANK YOU FOR SHOPPING</text>`;
  y += 20;

  // Faux barcode.
  let bx = L + 14;
  [3,1,2,1,1,3,2,1,1,2,3,1,2,2,1,1,3,1,2,1,1,2,1,3,2,1,1,2,3,1,2,1,1,2].forEach((w, i) => {
    if (i % 2 === 0) c += `<rect x="${bx}" y="${y}" width="${w}" height="26" fill="#222"/>`;
    bx += w + 1;
  });
  y += 26 + 9;
  c += `<text x="${cx}" y="${y}" text-anchor="middle" font-size="7" ${mono} fill="#555">0 042800 31337 5</text>`;

  const PH = y + 10;
  const CW = PW + 96, CH = PH + 84;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${CW}" height="${CH}" viewBox="0 0 ${CW} ${CH}">`
    + `<defs>`
    + `<linearGradient id="rs-surf" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#eef0f3"/><stop offset="1" stop-color="#dadee4"/></linearGradient>`
    + `<linearGradient id="rs-pap" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fdfbf6"/><stop offset="1" stop-color="#f1ede2"/></linearGradient>`
    + `<radialGradient id="rs-vig" cx="50%" cy="40%" r="75%"><stop offset="62%" stop-color="#000000" stop-opacity="0"/><stop offset="100%" stop-color="#37301f" stop-opacity="0.12"/></radialGradient>`
    + `<filter id="rs-sh" x="-40%" y="-40%" width="180%" height="180%"><feDropShadow dx="0" dy="7" stdDeviation="10" flood-color="#16161a" flood-opacity="0.30"/></filter>`
    + `<filter id="rs-noise"><feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" stitchTiles="stitch" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 0.05 0"/></filter>`
    + `</defs>`
    + `<rect width="${CW}" height="${CH}" fill="url(#rs-surf)"/>`
    + `<g transform="rotate(-1.6 ${CW / 2} ${CH / 2})"><g transform="translate(${(CW - PW) / 2} 34)">`
    + `<rect width="${PW}" height="${PH}" rx="3" fill="url(#rs-pap)" filter="url(#rs-sh)"/>`
    + `<rect width="${PW}" height="${PH}" rx="3" filter="url(#rs-noise)"/>`
    + `<rect width="${PW}" height="${PH}" rx="3" fill="url(#rs-vig)"/>`
    + c
    + `</g></g></svg>`;
  return 'data:image/svg+xml,' + encodeURIComponent(svg);
}

function renderLimit(d) {
  const capacity = d.error === 'capacity';
  document.getElementById('rs-limit').innerHTML = `
    <div class="rs-limit-card">
      <h2>${capacity ? 'Free scanning is at capacity today' : "You've used your free scans for today"}</h2>
      <p>${esc(d.message || '')}</p>
      <div class="rs-limit-cta">
        <a class="rs-btn rs-btn-primary" href="${base}/downloads/?source=receipt-scanner-limit">Get Argo Books free</a>
        <a class="rs-btn rs-btn-ghost" href="${base}/pricing/?source=receipt-scanner-limit">See Premium</a>
      </div>
    </div>`;
}
