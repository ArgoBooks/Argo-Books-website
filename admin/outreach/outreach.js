// ─── State ───
let currentLeadId = null;
let debounceTimer = null;
let leadsPaginator = null;

// ─── URL Param Helpers ───
function getFilterParams() {
    return new URLSearchParams(window.location.search);
}

function updateUrlParams() {
    // Start from the current URL so non-filter params (tab, etc.) survive.
    const params = new URLSearchParams(window.location.search);
    const search = document.getElementById('filterSearch').value.trim();
    const status = document.getElementById('filterStatus').value;
    const response = document.getElementById('filterResponse').value;
    const companySize = document.getElementById('filterCompanySize').value;
    const source = document.getElementById('filterSource').value;
    const sort = document.getElementById('filterSort').value;

    const setOrDelete = (key, value) => value ? params.set(key, value) : params.delete(key);
    setOrDelete('search', search);
    setOrDelete('status', status);
    setOrDelete('response', response);
    setOrDelete('company_size', companySize);
    setOrDelete('source', source);
    setOrDelete('sort', sort && sort !== 'date_added_desc' ? sort : '');

    const qs = params.toString();
    const newUrl = qs ? window.location.pathname + '?' + qs : window.location.pathname;
    window.history.replaceState({}, '', newUrl);
}

function restoreFiltersFromUrl() {
    const params = getFilterParams();
    if (params.has('search')) document.getElementById('filterSearch').value = params.get('search');
    if (params.has('status')) document.getElementById('filterStatus').value = params.get('status');
    if (params.has('response')) document.getElementById('filterResponse').value = params.get('response');
    if (params.has('company_size')) document.getElementById('filterCompanySize').value = params.get('company_size');
    if (params.has('source')) document.getElementById('filterSource').value = params.get('source');
    if (params.has('sort')) document.getElementById('filterSort').value = params.get('sort');
}

// ─── Init ───
document.addEventListener('DOMContentLoaded', function () {
    restoreFiltersFromUrl();
    loadStats();
    loadLeads();
});

// ─── API Helper ───
async function api(action, options = {}) {
    const { method = 'GET', body = null, params = {} } = options;
    let url = `api.php?action=${action}`;

    if (method === 'GET' && Object.keys(params).length) {
        const qs = new URLSearchParams(params).toString();
        url += '&' + qs;
    }

    const fetchOptions = { method };
    if (method !== 'GET') {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (body instanceof FormData) {
            body.append('csrf_token', csrfToken);
            fetchOptions.body = body;
        } else {
            const payload = Object.assign({}, body || {}, { csrf_token: csrfToken });
            fetchOptions.headers = { 'Content-Type': 'application/json' };
            fetchOptions.body = JSON.stringify(payload);
        }
    }

    const res = await fetch(url, fetchOptions);
    if (action === 'export_csv') return res;

    if (res.status === 401) {
        // Session expired, redirect to login
        window.location.href = '../login.php';
        throw new Error('Session expired. Redirecting to login...');
    }

    if (!res.ok) {
        let msg = `Server error (${res.status})`;
        try { const err = await res.json(); msg = err.message || msg; } catch(e) {}
        throw new Error(msg);
    }

    return await res.json();
}

function notify(message, type) {
    // Non-blocking toast instead of a native alert box. notifications.js (loaded
    // by admin_header.php) watches for .success-message/.error-message nodes and
    // removes them when the fadeInOut animation ends.
    const el = document.createElement('div');
    el.className = type === 'error' ? 'error-message' : 'success-message';
    el.textContent = message;
    document.body.appendChild(el);
}

// ─── Stats ───
async function loadStats() {
    try {
        const data = await api('get_stats');
        if (data.success) {
            const s = data.stats;
            document.getElementById('statTotal').textContent = s.total || 0;
            document.getElementById('statNew').textContent = s.new_leads || 0;
            document.getElementById('statDraftsPending').textContent = s.drafts_pending || 0;
            document.getElementById('statContacted').textContent = s.contacted || 0;
            document.getElementById('statReplied').textContent = s.replied || 0;
            document.getElementById('statInterested').textContent = s.interested || 0;
            document.getElementById('statClicked').textContent = s.clicked || 0;

            // Show/hide pipeline running banner
            const banner = document.getElementById('pipelineBanner');
            if (banner) {
                banner.style.display = data.pipeline_running ? 'block' : 'none';
            }
        }
    } catch (e) {
        notify(e.message, 'error');
    }
}

// ─── Leads Table ───
function debounceLoadLeads() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadLeads, 300);
}

async function loadLeads() {
    const params = {};
    const search = document.getElementById('filterSearch').value.trim();
    const status = document.getElementById('filterStatus').value;
    const response = document.getElementById('filterResponse').value;
    const companySize = document.getElementById('filterCompanySize').value;
    const source = document.getElementById('filterSource').value;
    const sort = document.getElementById('filterSort').value;

    if (search) params.search = search;
    if (status) params.status = status;
    if (response) params.response_status = response;
    if (companySize) params.company_size = companySize;
    if (source) params.source = source;
    if (sort) params.sort = sort;

    // Persist filters to URL
    updateUrlParams();

    try {
        const data = await api('get_leads', { params });
        const tbody = document.getElementById('leadsTableBody');

        if (!data.success || !data.leads.length) {
            tbody.innerHTML = '<tr><td colspan="12" class="empty-state">No leads found</td></tr>';
            updateBulkBar();
            return;
        }

        tbody.innerHTML = data.leads.map(lead => `
            <tr class="lead-row">
                <td class="checkbox-column" onclick="event.stopPropagation()">
                    <div class="checkbox"><input type="checkbox" class="lead-check" value="${lead.id}" id="lead-check-${lead.id}" data-has-draft="${lead.draft_subject ? '1' : ''}" onchange="updateBulkBar()"><label for="lead-check-${lead.id}"></label></div>
                </td>
                <td>
                    <strong>${esc(lead.business_name)}</strong>
                    ${lead.contact_name ? '<br><small>' + esc(lead.contact_name) + '</small>' : ''}
                </td>
                <td>${lead.website ? '<a class="website-link" href="' + esc(lead.website) + '" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()">' + esc(lead.website.replace(/^https?:\/\//, '').replace(/\?.*$/, '')) + '</a>' : '<span class="text-muted">—</span>'}</td>
                <td>${lead.email ? esc(lead.email) : '<span class="text-muted">—</span>'}</td>
                <td>${lead.phone ? esc(lead.phone) : '<span class="text-muted">—</span>'}</td>
                <td>${esc(lead.city || '')}</td>
                <td>${esc(lead.category || '')}</td>
                <td>${formatSource(lead.source)}</td>
                <td><span class="badge badge-status-${lead.status || 'new'}">${formatStatus(lead.status || 'new')}</span></td>
                <td>${lead.sent_at ? formatDateTime(lead.sent_at) : '<span class="text-muted">—</span>'}</td>
                <td>${lead.clicked_at ? formatDateTime(lead.clicked_at) : '<span class="text-muted">—</span>'}</td>
                <td onclick="event.stopPropagation()">
                    <div class="actions-cell">
                        <button class="btn btn-small btn-blue" onclick="openLeadDetail(${lead.id})" title="View">View</button>
                        ${!lead.draft_subject && !['contacted','replied','interested','not_interested','onboarded'].includes(lead.status) ? `<button class="btn btn-small btn-blue" onclick="quickGenerateDraft(${lead.id}, this)" title="Generate Draft">Draft</button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('');

        // Reset select-all checkbox
        const selectAll = document.getElementById('leadsSelectAll');
        if (selectAll) selectAll.checked = false;
        updateBulkBar();

        // Initialize or reset pagination
        const leadsTable = document.querySelector('.leads-table');
        if (leadsTable) {
            if (!leadsPaginator) {
                leadsPaginator = new TablePaginator(leadsTable, { perPage: 25 });
            } else {
                leadsPaginator.reset();
            }
        }
    } catch (e) {
        notify(e.message, 'error');
    }
}

// Refresh whichever leads tables are currently populated. The lead detail modal
// (and quick-draft) can be opened from the Email, Editorial, or Creator tab, so
// after a status change we refresh all of them; the editorial/creator tables only
// reload if they've been opened (their paginator exists), keeping this cheap.
function refreshLeadViews() {
    loadLeads();
    if (typeof editorialLeadsPaginator !== 'undefined' && editorialLeadsPaginator) loadEditorialLeads();
    if (typeof creatorLeadsPaginator !== 'undefined' && creatorLeadsPaginator) loadCreatorLeads();
}

// ─── Bulk Select ───
function toggleLeadCheckboxes(master) {
    document.querySelectorAll('.lead-check').forEach(cb => cb.checked = master.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.lead-check:checked');
    const bar = document.getElementById('bulkActionsBar');
    const count = document.getElementById('selectedCount');
    const selectAll = document.getElementById('leadsSelectAll');
    const allBoxes = document.querySelectorAll('.lead-check');

    if (bar) bar.style.display = checked.length ? 'flex' : 'none';
    if (count) count.textContent = checked.length;

    // Update select-all indeterminate state
    if (selectAll && allBoxes.length) {
        if (checked.length === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (checked.length === allBoxes.length) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    }

    // Switch to "Redraft Selected" if all selected leads already have drafts
    const draftBtn = document.getElementById('btnDraftSelected');
    if (draftBtn) {
        const allDrafted = checked.length > 0 && Array.from(checked).every(cb => cb.dataset.hasDraft === '1');
        draftBtn.textContent = allDrafted ? 'Redraft Selected' : 'Draft Selected';
    }
}

function getSelectedLeadIds() {
    return Array.from(document.querySelectorAll('.lead-check:checked')).map(cb => parseInt(cb.value));
}

let bulkDraftCancelled = false;

function cancelBulkDrafts() {
    bulkDraftCancelled = true;
    // Disable whichever cancel button is currently visible (email or editorial).
    ['btnCancelDraft', 'edBtnCancelDraft'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn && btn.offsetParent !== null) { btn.disabled = true; btn.textContent = 'Cancelling...'; }
    });
}

/**
 * Shared bulk-draft engine used by both the Email and Editorial leads tables.
 * The two tables use distinct checkbox classes/IDs and distinct progress/button
 * element IDs, so the caller passes those in; everything else is id-based and
 * identical. Concurrency of 3, per-row live updates, cancellable.
 */
async function runBulkDrafts(opts) {
    const {
        ids, checkClass, checkIdPrefix,
        progressId, progressTextId, cancelBtnId, draftBtnId, onDone,
    } = opts;
    if (!ids.length) return;
    if (!confirm(`Generate drafts for ${ids.length} lead(s)?`)) return;

    bulkDraftCancelled = false;
    const total = ids.length;
    let success = 0, fail = 0;
    const progressEl = document.getElementById(progressId);
    const progressText = document.getElementById(progressTextId);
    const cancelBtn = document.getElementById(cancelBtnId);
    if (progressEl) progressEl.style.display = 'flex';
    if (cancelBtn) { cancelBtn.disabled = false; cancelBtn.textContent = 'Cancel'; }

    // Disable the draft button during processing
    const draftBtn = document.getElementById(draftBtnId);
    if (draftBtn) draftBtn.disabled = true;

    function updateProgress() {
        const done = success + fail;
        if (progressText) progressText.textContent = `Drafting: ${done} of ${total} complete` + (fail ? ` (${fail} failed)` : '');
    }
    updateProgress();

    // Process drafts concurrently (3 at a time)
    const CONCURRENCY = 3;
    async function processDraft(id) {
        if (bulkDraftCancelled) return;

        const row = document.querySelector(`#${checkIdPrefix}${id}`)?.closest('tr');
        const draftCellBtn = row?.querySelector('.actions-cell .btn-blue[title="Generate Draft"]');
        if (draftCellBtn) {
            draftCellBtn.disabled = true;
            draftCellBtn.textContent = 'Drafting...';
        }

        try {
            const result = await api('generate_draft', { method: 'POST', body: { id } });
            if (bulkDraftCancelled) {
                if (draftCellBtn) { draftCellBtn.disabled = false; draftCellBtn.textContent = 'Draft'; }
                return;
            }
            if (result.success) {
                success++;
                if (draftCellBtn) draftCellBtn.remove();
                if (row) {
                    const badge = row.querySelector('.badge');
                    if (badge && !['contacted','replied','interested','not_interested','onboarded'].includes(badge.textContent.trim().toLowerCase().replace(/\s+/g, '_'))) {
                        badge.className = 'badge badge-status-draft_generated';
                        badge.textContent = 'Draft Generated';
                    }
                    const cb = row.querySelector(checkClass);
                    if (cb) cb.dataset.hasDraft = '1';
                }
            } else {
                fail++;
                if (draftCellBtn) { draftCellBtn.disabled = false; draftCellBtn.textContent = 'Draft'; }
            }
        } catch {
            fail++;
            if (draftCellBtn) { draftCellBtn.disabled = false; draftCellBtn.textContent = 'Draft'; }
        }
        updateProgress();
    }

    // Run in batches of CONCURRENCY
    for (let i = 0; i < ids.length; i += CONCURRENCY) {
        if (bulkDraftCancelled) break;
        const batch = ids.slice(i, i + CONCURRENCY);
        await Promise.all(batch.map(id => processDraft(id)));
    }

    // Done
    const cancelled = bulkDraftCancelled;
    const doneMsg = cancelled
        ? `Cancelled: ${success} drafted` + (fail ? `, ${fail} failed` : '') + `, ${total - success - fail} skipped`
        : `Done: ${success} drafted` + (fail ? `, ${fail} failed` : '');
    if (progressText) progressText.textContent = doneMsg;
    // Hide the progress bar as soon as the batch finishes.
    if (progressEl) progressEl.style.display = 'none';

    if (draftBtn) draftBtn.disabled = false;
    notify(doneMsg, success ? 'success' : 'error');
    loadStats();
    if (onDone) onDone();
}

function bulkGenerateDrafts() {
    return runBulkDrafts({
        ids: getSelectedLeadIds(),
        checkClass: '.lead-check', checkIdPrefix: 'lead-check-',
        progressId: 'bulkDraftProgress', progressTextId: 'bulkDraftProgressText',
        cancelBtnId: 'btnCancelDraft', draftBtnId: 'btnDraftSelected',
        onDone: updateBulkBar,
    });
}

async function bulkDeleteLeads() {
    const ids = getSelectedLeadIds();
    if (!ids.length) return;
    if (!confirm(`Delete ${ids.length} lead(s)? This cannot be undone.`)) return;

    let success = 0, fail = 0;
    let lastError = '';
    for (const id of ids) {
        try {
            const result = await api('delete_lead', { method: 'POST', body: { id } });
            if (result.success) success++; else { fail++; lastError = result.message || 'Unknown error'; }
        } catch (err) { fail++; lastError = err.message; }
    }
    if (fail > 0) {
        notify(`Deleted: ${success}, Failed: ${fail}. Last error: ${lastError}`, 'error');
    } else {
        notify(`Successfully deleted ${success} lead(s)`, 'success');
    }
    loadLeads();
    loadStats();
}

// ─── Bulk Send Email ───
let bulkSendLeads = [];

async function openBulkSendModal(idsArg) {
    const ids = (Array.isArray(idsArg) && idsArg.length) ? idsArg : getSelectedLeadIds();
    if (!ids.length) { notify('No leads selected', 'error'); return; }

    const statusEl = document.getElementById('bulkSendStatus');
    const listEl = document.getElementById('bulkSendList');
    const sendBtn = document.getElementById('btnBulkSend');

    statusEl.textContent = 'Loading leads...';
    listEl.innerHTML = '';
    sendBtn.disabled = true;
    sendBtn.textContent = 'Send All';
    sendBtn.onclick = executeBulkSend;
    showModal('bulkSendModal');

    try {
        const data = await api('bulk_get_leads', { params: { ids: ids.join(',') } });
        if (!data.success) { statusEl.textContent = data.message; return; }

        bulkSendLeads = data.leads;

        // Filter out leads without email
        const withEmail = bulkSendLeads.filter(l => l.email);
        const withoutEmail = bulkSendLeads.filter(l => !l.email);

        // Render all leads
        renderBulkSendList();

        // Auto-generate drafts for leads that don't have one (3 at a time)
        const needsDraft = withEmail.filter(l => !l.draft_subject || !l.draft_body);
        if (needsDraft.length) {
            statusEl.textContent = `Generating drafts for ${needsDraft.length} lead(s)...`;
            const CONCURRENCY = 3;
            async function processSendDraft(lead) {
                const itemEl = document.getElementById('bulk-send-item-' + lead.id);
                if (itemEl) {
                    itemEl.querySelector('.bulk-send-item-draft').innerHTML = '<span class="bulk-send-item-generating">Generating draft...</span>';
                }
                try {
                    const result = await api('generate_draft', { method: 'POST', body: { id: lead.id } });
                    if (result.success) {
                        lead.draft_subject = result.subject;
                        lead.draft_body = result.body;
                        if (itemEl) renderBulkSendItemDraft(itemEl, lead);
                    } else {
                        if (itemEl) {
                            itemEl.querySelector('.bulk-send-item-draft').innerHTML = '<span class="bulk-send-item-error">Failed to generate draft</span>';
                        }
                    }
                } catch (e) {
                    if (itemEl) {
                        itemEl.querySelector('.bulk-send-item-draft').innerHTML = '<span class="bulk-send-item-error">Error: ' + esc(e.message) + '</span>';
                    }
                }
            }
            for (let i = 0; i < needsDraft.length; i += CONCURRENCY) {
                const batch = needsDraft.slice(i, i + CONCURRENCY);
                await Promise.all(batch.map(lead => processSendDraft(lead)));
            }
        }

        const sendable = withEmail.filter(l => l.draft_subject && l.draft_body);
        statusEl.textContent = `${sendable.length} email(s) ready to send` +
            (withoutEmail.length ? `, ${withoutEmail.length} skipped (no email)` : '');
        sendBtn.disabled = sendable.length === 0;

    } catch (e) {
        statusEl.textContent = 'Error: ' + e.message;
    }
}

function renderBulkSendList() {
    const listEl = document.getElementById('bulkSendList');
    listEl.innerHTML = bulkSendLeads.map(lead => {
        const hasEmail = !!lead.email;
        const hasDraft = lead.draft_subject && lead.draft_body;
        return `<div class="bulk-send-item" id="bulk-send-item-${lead.id}">
            <div class="bulk-send-item-header">
                <strong>${esc(lead.business_name)}</strong>
                <span class="bulk-send-item-email">${hasEmail ? esc(lead.email) : '<span class="bulk-send-item-no-email">No email address</span>'}</span>
            </div>
            <div class="bulk-send-item-draft">
                ${!hasEmail ? '<span class="bulk-send-item-no-email">Will be skipped (no email address)</span>' :
                  hasDraft ? `<div class="bulk-send-item-subject">Subject: ${esc(lead.draft_subject)}</div><div class="bulk-send-item-body">${esc(lead.draft_body)}</div>` :
                  '<span class="bulk-send-item-generating">Draft will be generated...</span>'}
            </div>
        </div>`;
    }).join('');
}

function renderBulkSendItemDraft(itemEl, lead) {
    itemEl.querySelector('.bulk-send-item-draft').innerHTML =
        `<div class="bulk-send-item-subject">Subject: ${esc(lead.draft_subject)}</div><div class="bulk-send-item-body">${esc(lead.draft_body)}</div>`;
}

async function executeBulkSend() {
    const sendBtn = document.getElementById('btnBulkSend');
    const statusEl = document.getElementById('bulkSendStatus');
    const sendable = bulkSendLeads.filter(l => l.email && l.draft_subject && l.draft_body);

    if (!sendable.length) return;

    sendBtn.disabled = true;
    sendBtn.textContent = 'Sending...';

    let success = 0, fail = 0;
    for (const lead of sendable) {
        statusEl.textContent = `Sending ${success + fail + 1} of ${sendable.length}...`;
        try {
            const result = await api('send_email', { method: 'POST', body: { id: lead.id } });
            if (result.success) success++; else fail++;
        } catch { fail++; }
    }

    notify(`Sent: ${success}` + (fail ? `, Failed: ${fail}` : ''), success ? 'success' : 'error');
    closeBulkSendModal();

    if (success > 0) {
        loadLeads();
        loadStats();
        // Refresh the editorial/creator leads tables too if they were ever
        // populated, so a bulk send launched from those tabs reflects the new status.
        if (editorialLeadsPaginator) loadEditorialLeads();
        if (creatorLeadsPaginator) loadCreatorLeads();
    }
}

function closeBulkSendModal() {
    closeModal('bulkSendModal');
    bulkSendLeads = [];
}

// ─── Lead Detail Modal ───
async function openLeadDetail(id) {
    currentLeadId = id;
    try {
        const data = await api('get_lead', { params: { id } });
        if (!data.success) { notify(data.message, 'error'); return; }

        const lead = data.lead;
        document.getElementById('detailModalTitle').textContent = lead.business_name;

        // Info tab
        document.getElementById('detailBusinessName').value = lead.business_name || '';
        document.getElementById('detailContactName').value = lead.contact_name || '';
        document.getElementById('detailEmail').value = lead.email || '';
        document.getElementById('detailWebsite').value = lead.website || '';
        document.getElementById('detailCategory').value = lead.category || '';
        document.getElementById('detailSource').value = lead.source || 'manual';
        document.getElementById('detailStatus').value = lead.status || 'new';

        document.getElementById('detailCompanySize').value = lead.company_size || '';
        document.getElementById('detailContactPageUrl').value = lead.contact_page_url || '';
        document.getElementById('detailNotes').value = lead.notes || '';

        // Meta info
        // Meta info removed from UI

        // Draft tab
        document.getElementById('draftSubject').value = lead.draft_subject || '';
        document.getElementById('draftBody').value = lead.draft_body || '';
        updateDraftStatus(lead);
        updateFollowupStatus(lead);

        // Reset to info tab (scope to the modal, since the page now has its own .tab bar)
        switchTab('tabInfo', document.querySelector('#leadDetailModal .tab'));

        loadActivity(id);

        showModal('leadDetailModal');
    } catch (e) {
        notify(e.message, 'error');
    }
}

function updateDraftStatus(lead) {
    const bar = document.getElementById('draftStatusBar');
    const sendBtn = document.getElementById('btnSend');
    const genBtn = document.getElementById('btnGenerate');
    const saveBtn = document.getElementById('btnSaveDraft');

    let statusHtml = '';
    const isSent = ['contacted','replied','interested','not_interested','onboarded'].includes(lead.status);
    const isDisqualified = lead.status === 'disqualified';

    // Editing the draft only makes sense before it's sent or disqualified.
    const locked = isDisqualified || (isSent && lead.sent_at);
    if (saveBtn) saveBtn.style.display = locked ? 'none' : '';

    // Lock the subject/body fields too, not just hide the Save button, so a sent
    // draft cannot be typed into, which would look editable without saving.
    const subjectEl = document.getElementById('draftSubject');
    const bodyEl = document.getElementById('draftBody');
    if (subjectEl) subjectEl.readOnly = locked;
    if (bodyEl) bodyEl.readOnly = locked;

    if (isDisqualified) {
        const reasonTag = lead.disqualified_reason ? ` (${escapeHtml(lead.disqualified_reason)})` : '';
        statusHtml = `<span class="badge badge-status-disqualified">Disqualified</span>${reasonTag}`;
        sendBtn.disabled = true;
        genBtn.style.display = 'none';
        sendBtn.style.display = 'none';
    } else if (isSent && lead.sent_at) {
        statusHtml = `<span class="badge badge-status-contacted">Sent</span> on ${formatDateTime(lead.sent_at)}`;
        sendBtn.disabled = true;
        genBtn.style.display = 'none';
        sendBtn.style.display = 'none';
    } else if (lead.draft_subject || lead.draft_body) {
        statusHtml = '<span class="badge badge-status-draft_generated">Draft Ready</span>';
        sendBtn.disabled = !lead.email;
        genBtn.style.display = '';
        sendBtn.style.display = '';
        genBtn.textContent = 'Regenerate Draft';
        if (!lead.email) statusHtml += ' <span class="text-muted">(no email address)</span>';
    } else {
        statusHtml = '<span class="badge badge-status-new">No Draft</span>';
        sendBtn.disabled = true;
        genBtn.style.display = '';
        sendBtn.style.display = '';
        genBtn.textContent = 'Generate Draft';
    }

    if (lead.drafted_at) {
        statusHtml += ` Drafted: ${formatDateTime(lead.drafted_at)}`;
    }

    bar.innerHTML = statusHtml;

    // Info section
    let info = '';
    if (!lead.email) info = 'No email address. Email sending is disabled for this lead.';
    document.getElementById('draftInfo').textContent = info;
}

function updateFollowupStatus(lead) {
    const el = document.getElementById('followupStatus');
    if (!el) return;

    const replied = ['replied', 'interested', 'not_interested', 'onboarded'].includes(lead.status);

    if ((lead.followup_count | 0) > 0 && lead.last_followup_at) {
        el.textContent = 'Follow-up #' + lead.followup_count + ' sent ' + formatDateTime(lead.last_followup_at);
    } else if (replied) {
        el.textContent = 'Lead responded, no follow-up needed.';
    } else if (lead.next_followup_due_at) {
        const due = new Date(lead.next_followup_due_at);
        if (due <= new Date()) {
            el.textContent = 'Follow-up due now (will send on next pipeline run).';
        } else {
            el.textContent = 'Follow-up scheduled for ' + formatDateTime(lead.next_followup_due_at);
        }
    } else if (lead.sent_at) {
        el.textContent = 'No follow-up scheduled (legacy send; backfill migration may not have run).';
    } else {
        el.textContent = 'Not sent yet. Follow-up will be scheduled at send time.';
    }
}

function openWebsite() {
    let url = document.getElementById('detailWebsite').value.trim();
    if (!url) return;
    try {
        if (!/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(url)) url = 'https://' + url;
        const parsed = new URL(url);
        if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
            window.open(parsed.href, '_blank', 'noopener,noreferrer');
        }
    } catch (e) { /* invalid URL */ }
}

async function saveLeadDetails() {
    const data = {
        id: currentLeadId,
        business_name: document.getElementById('detailBusinessName').value,
        contact_name: document.getElementById('detailContactName').value,
        email: document.getElementById('detailEmail').value,
        website: document.getElementById('detailWebsite').value,
        category: document.getElementById('detailCategory').value,
        status: document.getElementById('detailStatus').value,
        company_size: document.getElementById('detailCompanySize').value,
        contact_page_url: document.getElementById('detailContactPageUrl').value,
        notes: document.getElementById('detailNotes').value,
    };

    // Also save draft fields if modified
    data.draft_subject = document.getElementById('draftSubject').value;
    data.draft_body = document.getElementById('draftBody').value;

    try {
        const result = await api('update_lead', { method: 'POST', body: data });
        if (result.success) {
            closeModal('leadDetailModal');
            refreshLeadViews();
            loadStats();
        } else {
            notify(result.message, 'error');
        }
    } catch (e) {
        notify(e.message, 'error');
    }
}

async function deleteCurrentLead() {
    if (!confirm('Are you sure you want to delete this lead?')) return;
    try {
        const result = await api('delete_lead', { method: 'POST', body: { id: currentLeadId } });
        notify(result.message, result.success ? 'success' : 'error');
        if (result.success) {
            closeModal('leadDetailModal');
            refreshLeadViews();
            loadStats();
        }
    } catch (e) {
        notify(e.message, 'error');
    }
}

// ─── Add Lead ───
function showAddLeadModal() {
    document.getElementById('addWebsite').value = '';
    showModal('addLeadModal');
}

async function createLead() {
    const website = document.getElementById('addWebsite').value.trim();
    if (!website) { notify('Website is required', 'error'); return; }

    // Enrichment fetches the site + makes an AI call, so it takes a few seconds.
    const btn = document.getElementById('btnAddLead');
    const originalText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Fetching…'; }

    try {
        const result = await api('create_lead_from_website', { method: 'POST', body: { website } });
        if (result.success) {
            closeModal('addLeadModal');
            notify(result.message || 'Lead added', 'success');
            loadLeads();
            loadStats();
        } else {
            notify(result.message, 'error');
        }
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = originalText; }
    }
}

// ─── Draft Generation ───
async function generateDraft() {
    if (!currentLeadId) return;
    const btn = document.getElementById('btnGenerate');
    btn.disabled = true;
    btn.textContent = 'Generating...';

    try {
        const data = await api('generate_draft', { method: 'POST', body: { id: currentLeadId } });
        btn.disabled = false;
        btn.textContent = 'Regenerate Draft';

        if (data.success) {
            document.getElementById('draftSubject').value = data.subject;
            document.getElementById('draftBody').value = data.body;
            // Refresh draft status without switching tabs
            const leadData = await api('get_lead', { params: { id: currentLeadId } });
            if (leadData.success) updateDraftStatus(leadData.lead);
            loadActivity(currentLeadId);
        } else {
            notify(data.message, 'error');
        }
    } catch (e) {
        btn.disabled = false;
        btn.textContent = 'Regenerate Draft';
        notify(e.message, 'error');
    }
}

async function quickGenerateDraft(id, btn) {
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Drafting...';
    }
    try {
        const result = await api('generate_draft', { method: 'POST', body: { id } });
        if (result.success) {
            refreshLeadViews();
            loadStats();
        } else {
            notify(result.message, 'error');
            if (btn) { btn.disabled = false; btn.textContent = 'Draft'; }
        }
    } catch (err) {
        notify('Draft failed: ' + err.message, 'error');
        if (btn) { btn.disabled = false; btn.textContent = 'Draft'; }
    }
}

// ─── Email Workflow ─── Persist the current subject/body from the Draft tab.
async function saveDraft() {
    if (!currentLeadId) return;
    const subject = document.getElementById('draftSubject').value;
    const body = document.getElementById('draftBody').value;
    const btn = document.getElementById('btnSaveDraft');
    const original = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
    try {
        const result = await api('update_lead', {
            method: 'POST',
            body: { id: currentLeadId, draft_subject: subject, draft_body: body }
        });
        if (result.success) {
            notify('Draft saved', 'success');
            // A draft typed from scratch (Send was disabled) should now be sendable.
            const sendBtn = document.getElementById('btnSend');
            const hasEmail = (document.getElementById('detailEmail').value || '').trim() !== '';
            if (sendBtn && (subject.trim() || body.trim()) && hasEmail) sendBtn.disabled = false;
            refreshLeadViews();
        } else {
            notify(result.message, 'error');
        }
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = original; }
    }
}

async function sendEmail() {
    if (!currentLeadId) return;
    if (!confirm('Send this email now?')) return;

    const btn = document.getElementById('btnSend');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    try {
        // Persist any edits first so we send exactly what's shown in the textareas.
        await api('update_lead', {
            method: 'POST',
            body: {
                id: currentLeadId,
                draft_subject: document.getElementById('draftSubject').value,
                draft_body: document.getElementById('draftBody').value
            }
        });

        const result = await api('send_email', { method: 'POST', body: { id: currentLeadId } });
        btn.textContent = 'Send Email';

        if (result.success) {
            openLeadDetail(currentLeadId);
            refreshLeadViews();
            loadStats();
        } else {
            notify(result.message, 'error');
            btn.disabled = false;
        }
    } catch (e) {
        btn.textContent = 'Send Email';
        btn.disabled = false;
        notify(e.message, 'error');
    }
}


function copyDraft(btn) {
    const subject = document.getElementById('draftSubject').value;
    const body = document.getElementById('draftBody').value;
    const text = `Subject: ${subject}\n\n${body}`;

    const copied = () => {
        const original = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => btn.textContent = original, 1000);
    };

    navigator.clipboard.writeText(text).then(copied).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        copied();
    });
}

// ─── AI Enrichment ───
// ─── Activity ───
async function loadActivity(id) {
    try {
        const data = await api('get_activity', { params: { id } });
        const container = document.getElementById('activityTimeline');

        if (!data.success || !data.activity.length) {
            container.innerHTML = '<p class="empty-state-text">No activity yet</p>';
            return;
        }

        container.innerHTML = data.activity.map(a => `
            <div class="activity-item">
                <div class="activity-dot"></div>
                <div class="activity-content">
                    <strong>${formatActionType(a.action_type)}</strong>
                    ${a.details ? '<span class="activity-details">' + esc(a.details) + '</span>' : ''}
                    <span class="activity-time">${formatDateTime(a.created_at)}</span>
                </div>
            </div>
        `).join('');
    } catch (e) {
        notify(e.message, 'error');
    }
}

// ─── CSV ───
function showImportCSVModal() {
    document.getElementById('csvFile').value = '';
    showModal('csvImportModal');
}

async function importCSV() {
    const fileInput = document.getElementById('csvFile');
    if (!fileInput.files.length) { notify('Please select a CSV file', 'error'); return; }

    const formData = new FormData();
    formData.append('csv_file', fileInput.files[0]);

    try {
        const result = await api('import_csv', { method: 'POST', body: formData });

        notify(result.message, result.success ? 'success' : 'error');
        if (result.success) {
            closeModal('csvImportModal');
            loadLeads();
            loadStats();
        }
    } catch (e) {
        notify(e.message, 'error');
    }
}

// ─── Modal Helpers ───
function showModal(id) {
    document.getElementById(id).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modals on backdrop click (only if mousedown also started on the backdrop)
let modalMouseDownTarget = null;
document.addEventListener('mousedown', function (e) {
    modalMouseDownTarget = e.target;
});
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal') && modalMouseDownTarget === e.target) {
        e.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

// Close modals on Escape
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => {
            if (m.style.display === 'flex') {
                m.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// ─── Modal tab switching (Info / Email Draft / Activity inside the lead-detail modal) ───
// Page-level tabs are handled by admin/section-tabs.js. This function is only
// for the modal's inner tabs (.tabs inside .modal-body) and is scoped to its
// own container so it doesn't clobber the page-level section-tabs.
function switchTab(tabId, btn) {
    const tabsContainer = btn.closest('.tabs');
    if (!tabsContainer) return;
    const scope = tabsContainer.parentElement;
    scope.querySelectorAll(':scope > .tab-content').forEach(t => t.classList.remove('active'));
    tabsContainer.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    const target = document.getElementById(tabId);
    if (target) target.classList.add('active');
    btn.classList.add('active');
}

// ─── Formatting Helpers ───
function esc(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatStatus(status) {
    return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatSource(source) {
    switch (source) {
        case 'google_places':      return 'Google Places';
        case 'google_places_auto': return 'Google Places <small style="color:#888">· auto</small>';
        case 'shopify_auto':       return 'Shopify';
        case 'manual':             return 'Manual';
        case 'csv_import':         return 'CSV';
        default:                   return source ? esc(source) : '<span class="text-muted">—</span>';
    }
}


function formatActionType(type) {
    return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-CA', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

// ─── Follow-ups tab ───

let currentFollowupView = 'pending_review';

window.switchFollowupsView = function(button, view) {
    document.querySelectorAll('[data-fu-view]').forEach(b => b.classList.remove('active'));
    button.classList.add('active');
    currentFollowupView = view;
    loadFollowups();
};

async function loadFollowups() {
    const container = document.getElementById('followupsContainer');
    container.innerHTML = '<p class="empty-state">Loading...</p>';
    try {
        const data = await api('get_followups&view=' + encodeURIComponent(currentFollowupView));
        if (!data.success) {
            container.innerHTML = '<p class="empty-state">Error loading: ' + (data.message || 'unknown') + '</p>';
            return;
        }
        if (!data.rows.length) {
            const emptyMessages = {
                'pending_review': 'No follow-ups awaiting review. Drafts appear here ~1 day before each scheduled send.',
                'approved': 'No approved follow-ups waiting in the send queue.',
                'upcoming': 'No follow-ups scheduled. New ones are created as leads receive their first email.',
                'sent': 'No follow-ups sent in the last 30 days.',
                'halted': 'No halted or failed follow-ups in the last 30 days.',
            };
            container.innerHTML = '<p class="empty-state">' + (emptyMessages[currentFollowupView] || 'No rows.') + '</p>';
            updateFollowupsBulkBar();
            return;
        }
        container.innerHTML = data.rows.map(r => renderFollowupRow(r, currentFollowupView)).join('');
        if (currentFollowupView === 'pending_review') {
            var badge = document.getElementById('fuCountPending');
            if (badge) badge.textContent = data.rows.length;
        }
        updateFollowupsBulkBar();
    } catch (e) {
        container.innerHTML = '<p class="empty-state">Error loading follow-ups: ' + e.message + '</p>';
    }
}

function renderFollowupRow(r, view) {
    const cityLabel = r.city ? ' (' + escapeHtml(r.city) + ')' : '';
    const scheduledStr = r.scheduled_for ? formatScheduled(r.scheduled_for) : '';
    const sentStr = r.sent_at ? formatScheduled(r.sent_at) : '';
    const haltReason = r.halt_reason ? ' · Reason: ' + escapeHtml(r.halt_reason) : '';

    let actions = '';
    let bodyEditor = '';
    let checkboxCell = '';

    if (view === 'pending_review') {
        checkboxCell = '<input type="checkbox" class="fu-row-check" data-fu-id="' + r.id + '" data-lead-id="' + r.lead_id + '" onchange="updateFollowupsBulkBar()">';
        bodyEditor = '<input type="text" class="fu-subject" data-id="' + r.id + '" value="' + escapeHtml(r.draft_subject || '') + '" style="width:100%; margin-bottom:6px;">' +
            '<textarea class="fu-body" data-id="' + r.id + '" rows="6" style="width:100%; font-family:inherit;">' + escapeHtml(r.draft_body || '') + '</textarea>';
        actions = '<button class="btn btn-small btn-blue" onclick="approveFollowup(' + r.id + ')">Approve & queue</button>' +
            ' <button class="btn btn-small btn-blue" onclick="regenerateFollowup(' + r.id + ')">Regenerate draft</button>' +
            ' <button class="btn btn-small btn-neutral" onclick="skipFollowup(' + r.id + ')">Skip this touch</button>' +
            ' <button class="btn btn-small btn-red" onclick="haltFollowupSequence(' + r.lead_id + ')">Halt sequence</button>';
    } else if (view === 'approved') {
        actions = '<button class="btn btn-small btn-neutral" onclick="skipFollowup(' + r.id + ')">Skip</button>' +
            ' <button class="btn btn-small btn-red" onclick="haltFollowupSequence(' + r.lead_id + ')">Halt sequence</button>';
    } else if (view === 'upcoming') {
        actions = '<button class="btn btn-small btn-red" onclick="haltFollowupSequence(' + r.lead_id + ')">Halt sequence</button>';
    }

    return '<div class="panel" style="margin-bottom:12px;">' +
        '<div class="panel-content">' +
            '<div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">' +
                (checkboxCell ? '<div>' + checkboxCell + '</div>' : '') +
                '<div style="flex:1;">' +
                    '<strong>' + escapeHtml(r.business_name || 'Unknown') + '</strong>' + cityLabel +
                    ' &nbsp;·&nbsp; Touch ' + r.touch_number +
                    (scheduledStr ? ' &nbsp;·&nbsp; Scheduled ' + scheduledStr : '') +
                    (sentStr ? ' &nbsp;·&nbsp; Sent ' + sentStr : '') +
                    haltReason +
                '</div>' +
            '</div>' +
            (bodyEditor ? '<div style="margin-bottom:8px;">' + bodyEditor + '</div>' :
                (r.draft_subject ? '<div style="font-size:13px; color:#666;">Subject: ' + escapeHtml(r.draft_subject) + '</div>' : '')) +
            (actions ? '<div style="margin-top:10px;">' + actions + '</div>' : '') +
        '</div>' +
    '</div>';
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]);
}

function formatScheduled(dt) {
    try {
        const d = new Date(dt.replace(' ', 'T'));
        const now = new Date();
        const diffMs = d.getTime() - now.getTime();
        const diffHours = diffMs / 3600000;
        if (Math.abs(diffHours) < 48) {
            const h = Math.round(diffHours);
            if (h === 0) return 'now';
            if (h > 0) return 'in ' + h + 'h';
            return Math.abs(h) + 'h ago';
        }
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return dt;
    }
}

window.approveFollowup = async function(id) {
    // Save any inline subject/body edits first
    const subjectInput = document.querySelector('.fu-subject[data-id="' + id + '"]');
    const bodyInput = document.querySelector('.fu-body[data-id="' + id + '"]');
    if (subjectInput && bodyInput) {
        const saveResult = await api('save_followup_draft', { method: 'POST', body: { id: id, subject: subjectInput.value, body: bodyInput.value } });
        if (!saveResult.success) {
            alert('Save failed: ' + (saveResult.message || 'unknown') + '. Not approving.');
            return;
        }
    }
    const data = await api('approve_followup', { method: 'POST', body: { id: id } });
    if (data.success) loadFollowups();
    else alert('Approve failed: ' + (data.message || 'unknown'));
};

window.regenerateFollowup = async function(id) {
    if (!confirm('Regenerate this follow-up via Gemini? Current draft will be replaced.')) return;
    const data = await api('regenerate_followup', { method: 'POST', body: { id: id } });
    if (data.success) loadFollowups();
    else alert('Regenerate failed: ' + (data.message || 'unknown'));
};

window.skipFollowup = async function(id) {
    if (!confirm('Skip this follow-up touch? The next touch in the sequence will still be sent on schedule.')) return;
    const data = await api('skip_followup', { method: 'POST', body: { id: id } });
    if (data.success) loadFollowups();
    else alert('Skip failed: ' + (data.message || 'unknown'));
};

window.haltFollowupSequence = async function(leadId) {
    if (!confirm('Halt the entire follow-up sequence for this lead? No more follow-ups will be sent.')) return;
    const data = await api('halt_followup_sequence', { method: 'POST', body: { lead_id: leadId } });
    if (data.success) loadFollowups();
    else alert('Halt failed: ' + (data.message || 'unknown'));
};

window.updateFollowupsBulkBar = function() {
    const checks = document.querySelectorAll('.fu-row-check:checked');
    const bar = document.getElementById('fuBulkActionsBar');
    document.getElementById('fuSelectedCount').textContent = checks.length;
    bar.style.display = checks.length > 0 ? 'flex' : 'none';
};

window.bulkApproveFollowups = async function() {
    const ids = Array.from(document.querySelectorAll('.fu-row-check:checked')).map(c => parseInt(c.dataset.fuId));
    if (!ids.length) return;
    const data = await api('bulk_approve_followups', { method: 'POST', body: { ids: ids } });
    if (data.success) { alert('Approved ' + data.approved_count + ' follow-up(s).'); loadFollowups(); }
    else alert('Bulk approve failed: ' + (data.message || 'unknown'));
};

window.bulkSkipFollowups = async function() {
    const ids = Array.from(document.querySelectorAll('.fu-row-check:checked')).map(c => parseInt(c.dataset.fuId));
    if (!ids.length) return;
    if (!confirm('Skip ' + ids.length + ' follow-up(s)?')) return;
    const data = await api('bulk_skip_followups', { method: 'POST', body: { ids: ids } });
    if (data.success) { alert('Skipped ' + data.skipped_count + ' follow-up(s).'); loadFollowups(); }
    else alert('Bulk skip failed: ' + (data.message || 'unknown'));
};

window.bulkHaltFollowupSequences = async function() {
    const leadIds = Array.from(new Set(Array.from(document.querySelectorAll('.fu-row-check:checked')).map(c => parseInt(c.dataset.leadId))));
    if (!leadIds.length) return;
    if (!confirm('Halt the follow-up sequence for ' + leadIds.length + ' lead(s)? This stops ALL remaining follow-ups for these leads.')) return;
    const data = await api('bulk_halt_followups', { method: 'POST', body: { lead_ids: leadIds } });
    if (data.success) { alert('Halted ' + data.halted_count + ' follow-up row(s).'); loadFollowups(); }
    else alert('Bulk halt failed: ' + (data.message || 'unknown'));
};

// Auto-load when the followups tab activates
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.section-tab[data-tab="followups"]').forEach(btn => {
        btn.addEventListener('click', () => setTimeout(loadFollowups, 0));
    });
    // Also auto-load on page load if we're already on the tab (e.g. ?tab=followups)
    if (window.location.search.indexOf('tab=followups') !== -1) {
        loadFollowups();
    }
});

window.loadLeadFollowups = async function() {
    const list = document.getElementById('leadFollowupsList');
    list.innerHTML = '<p class="empty-state-text">Loading...</p>';
    // currentLeadId is the global declared at outreach.js:2 and set by
    // openLeadDetail() at outreach.js:527 whenever a lead modal opens.
    if (!currentLeadId) {
        list.innerHTML = '<p class="empty-state-text">No lead selected.</p>';
        return;
    }
    const data = await api('get_followups_for_lead&lead_id=' + currentLeadId);
    if (!data.success) {
        list.innerHTML = '<p class="empty-state-text">Error: ' + (data.message || 'unknown') + '</p>';
        return;
    }
    if (!data.rows.length) {
        list.innerHTML = '<p class="empty-state-text">No follow-ups scheduled for this lead. Sequence is configured in Settings; rows are created when the first-touch email sends.</p>';
        return;
    }
    list.innerHTML = '<table class="data-table"><thead><tr><th>Touch</th><th>Status</th><th>Scheduled</th><th>Sent</th><th>Halt reason</th></tr></thead><tbody>' +
        data.rows.map(r =>
            '<tr>' +
                '<td>' + r.touch_number + '</td>' +
                '<td>' + escapeHtml(r.status) + '</td>' +
                '<td>' + (r.scheduled_for || '—') + '</td>' +
                '<td>' + (r.sent_at || '—') + '</td>' +
                '<td>' + (r.halt_reason ? escapeHtml(r.halt_reason) : '—') + '</td>' +
            '</tr>'
        ).join('') +
    '</tbody></table>';
};

// Editorial leads list (scoped to source=editorial_auto).
let editorialLeadsPaginator = null;

// ─── Editorial Leads: bulk select (distinct class/IDs from the Email tab so the
// two tables' selections never collide, since both live in the DOM at once) ───
function toggleEditorialLeadCheckboxes(master) {
    document.querySelectorAll('.ed-lead-check').forEach(cb => cb.checked = master.checked);
    updateEditorialBulkBar();
}

function updateEditorialBulkBar() {
    const checked = document.querySelectorAll('.ed-lead-check:checked');
    const bar = document.getElementById('edBulkActionsBar');
    const count = document.getElementById('edSelectedCount');
    const selectAll = document.getElementById('edLeadsSelectAll');
    const allBoxes = document.querySelectorAll('.ed-lead-check');

    if (bar) bar.style.display = checked.length ? 'flex' : 'none';
    if (count) count.textContent = checked.length;

    if (selectAll && allBoxes.length) {
        if (checked.length === 0) { selectAll.checked = false; selectAll.indeterminate = false; }
        else if (checked.length === allBoxes.length) { selectAll.checked = true; selectAll.indeterminate = false; }
        else { selectAll.checked = false; selectAll.indeterminate = true; }
    }

    const draftBtn = document.getElementById('edBtnDraftSelected');
    if (draftBtn) {
        const allDrafted = checked.length > 0 && Array.from(checked).every(cb => cb.dataset.hasDraft === '1');
        draftBtn.textContent = allDrafted ? 'Redraft Selected' : 'Draft Selected';
    }
}

function getSelectedEditorialLeadIds() {
    return Array.from(document.querySelectorAll('.ed-lead-check:checked')).map(cb => parseInt(cb.value));
}

function bulkGenerateEditorialDrafts() {
    return runBulkDrafts({
        ids: getSelectedEditorialLeadIds(),
        checkClass: '.ed-lead-check', checkIdPrefix: 'ed-lead-check-',
        progressId: 'edBulkDraftProgress', progressTextId: 'edBulkDraftProgressText',
        cancelBtnId: 'edBtnCancelDraft', draftBtnId: 'edBtnDraftSelected',
        onDone: updateEditorialBulkBar,
    });
}

function openEditorialBulkSend() {
    openBulkSendModal(getSelectedEditorialLeadIds());
}

async function bulkDeleteEditorialLeads() {
    const ids = getSelectedEditorialLeadIds();
    if (!ids.length) return;
    if (!confirm(`Delete ${ids.length} lead(s)? This cannot be undone.`)) return;

    let success = 0, fail = 0, lastError = '';
    for (const id of ids) {
        try {
            const result = await api('delete_lead', { method: 'POST', body: { id } });
            if (result.success) success++; else { fail++; lastError = result.message || 'Unknown error'; }
        } catch (err) { fail++; lastError = err.message; }
    }
    if (fail > 0) notify(`Deleted: ${success}, Failed: ${fail}. Last error: ${lastError}`, 'error');
    else notify(`Successfully deleted ${success} lead(s)`, 'success');
    loadEditorialLeads();
    loadStats();
}

function debounceLoadEditorialLeads() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadEditorialLeads, 300);
}

async function loadEditorialLeads() {
    const tbody = document.getElementById('editorialLeadsTableBody');
    if (!tbody) return;

    const params = { source: 'editorial_auto' };
    const searchEl = document.getElementById('edFilterSearch');
    const statusEl = document.getElementById('edFilterStatus');
    const responseEl = document.getElementById('edFilterResponse');
    const sortEl = document.getElementById('edFilterSort');
    if (searchEl && searchEl.value.trim()) params.search = searchEl.value.trim();
    if (statusEl && statusEl.value) params.status = statusEl.value;
    if (responseEl && responseEl.value) params.response_status = responseEl.value;
    params.sort = (sortEl && sortEl.value) ? sortEl.value : 'date_added_desc';

    try {
        const data = await api('get_leads', { params });
        if (!data.success || !data.leads.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No editorial leads found.</td></tr>';
            if (editorialLeadsPaginator) editorialLeadsPaginator.reset();
            const selectAll = document.getElementById('edLeadsSelectAll');
            if (selectAll) selectAll.checked = false;
            updateEditorialBulkBar();
            return;
        }
        tbody.innerHTML = data.leads.map(lead => `
            <tr class="lead-row">
                <td class="checkbox-column" onclick="event.stopPropagation()">
                    <div class="checkbox"><input type="checkbox" class="ed-lead-check" value="${lead.id}" id="ed-lead-check-${lead.id}" data-has-draft="${lead.draft_subject ? '1' : ''}" onchange="updateEditorialBulkBar()"><label for="ed-lead-check-${lead.id}"></label></div>
                </td>
                <td>
                    <strong>${esc(lead.business_name)}</strong>
                    ${lead.contact_name ? '<br><small>' + esc(lead.contact_name) + '</small>' : ''}
                </td>
                <td>${lead.website ? '<a class="website-link" href="' + esc(lead.website) + '" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()">' + esc(lead.website.replace(/^https?:\/\//, '').replace(/\?.*$/, '')) + '</a>' : '<span class="text-muted">—</span>'}</td>
                <td>${lead.email ? esc(lead.email) : '<span class="text-muted">—</span>'}</td>
                <td><span class="badge badge-status-${lead.status || 'new'}">${formatStatus(lead.status || 'new')}</span></td>
                <td>${lead.sent_at ? formatDateTime(lead.sent_at) : '<span class="text-muted">—</span>'}</td>
                <td>${lead.clicked_at ? formatDateTime(lead.clicked_at) : '<span class="text-muted">—</span>'}</td>
                <td onclick="event.stopPropagation()">
                    <div class="actions-cell">
                        <button class="btn btn-small btn-blue" onclick="openLeadDetail(${lead.id})" title="View">View</button>
                        ${!lead.draft_subject && !['contacted','replied','interested','not_interested','onboarded'].includes(lead.status) ? `<button class="btn btn-small btn-blue" onclick="quickGenerateDraft(${lead.id}, this)" title="Generate Draft">Draft</button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('');

        const selectAll = document.getElementById('edLeadsSelectAll');
        if (selectAll) selectAll.checked = false;
        updateEditorialBulkBar();

        const table = document.querySelector('.editorial-leads-table');
        if (table) {
            if (!editorialLeadsPaginator) editorialLeadsPaginator = new TablePaginator(table, { perPage: 25 });
            else editorialLeadsPaginator.reset();
        }
    } catch (e) {
        notify(e.message, 'error');
    }
}


// === Creators and affiliate partners ===
// Mirrors the editorial channel with its own filters and bulk actions, scoped to source=creator_auto.

let creatorLeadsPaginator = null;

// ─── Creator Leads: bulk select (distinct .cr-lead-check class / cr* IDs) ───
function toggleCreatorLeadCheckboxes(master) {
    document.querySelectorAll('.cr-lead-check').forEach(cb => cb.checked = master.checked);
    updateCreatorBulkBar();
}

function updateCreatorBulkBar() {
    const checked = document.querySelectorAll('.cr-lead-check:checked');
    const bar = document.getElementById('crBulkActionsBar');
    const count = document.getElementById('crSelectedCount');
    const selectAll = document.getElementById('crLeadsSelectAll');
    const allBoxes = document.querySelectorAll('.cr-lead-check');

    if (bar) bar.style.display = checked.length ? 'flex' : 'none';
    if (count) count.textContent = checked.length;

    if (selectAll && allBoxes.length) {
        if (checked.length === 0) { selectAll.checked = false; selectAll.indeterminate = false; }
        else if (checked.length === allBoxes.length) { selectAll.checked = true; selectAll.indeterminate = false; }
        else { selectAll.checked = false; selectAll.indeterminate = true; }
    }

    const draftBtn = document.getElementById('crBtnDraftSelected');
    if (draftBtn) {
        const allDrafted = checked.length > 0 && Array.from(checked).every(cb => cb.dataset.hasDraft === '1');
        draftBtn.textContent = allDrafted ? 'Redraft Selected' : 'Draft Selected';
    }
}

function getSelectedCreatorLeadIds() {
    return Array.from(document.querySelectorAll('.cr-lead-check:checked')).map(cb => parseInt(cb.value));
}

function bulkGenerateCreatorDrafts() {
    return runBulkDrafts({
        ids: getSelectedCreatorLeadIds(),
        checkClass: '.cr-lead-check', checkIdPrefix: 'cr-lead-check-',
        progressId: 'crBulkDraftProgress', progressTextId: 'crBulkDraftProgressText',
        cancelBtnId: 'crBtnCancelDraft', draftBtnId: 'crBtnDraftSelected',
        onDone: updateCreatorBulkBar,
    });
}

function openCreatorBulkSend() {
    openBulkSendModal(getSelectedCreatorLeadIds());
}

async function bulkDeleteCreatorLeads() {
    const ids = getSelectedCreatorLeadIds();
    if (!ids.length) return;
    if (!confirm(`Delete ${ids.length} lead(s)? This cannot be undone.`)) return;

    let success = 0, fail = 0, lastError = '';
    for (const id of ids) {
        try {
            const result = await api('delete_lead', { method: 'POST', body: { id } });
            if (result.success) success++; else { fail++; lastError = result.message || 'Unknown error'; }
        } catch (err) { fail++; lastError = err.message; }
    }
    if (fail > 0) notify(`Deleted: ${success}, Failed: ${fail}. Last error: ${lastError}`, 'error');
    else notify(`Successfully deleted ${success} lead(s)`, 'success');
    loadCreatorLeads();
    loadStats();
}

// Grab a creator's email when none was auto-found: open the page where the email lives (the channel About page for YouTube) in a new tab, then paste it back onto the lead.
let creatorEmailBtn = null;
let creatorEmailLeadId = null;

function getCreatorEmail(btn) {
    const leadId = parseInt(btn.dataset.leadId, 10);
    let target = btn.dataset.website || '';
    if (/youtube\.com/i.test(target)) {
        target = target.replace(/\/+$/, '');
        if (!/\/about$/i.test(target)) target += '/about';
    }
    if (target) window.open(target, '_blank', 'noopener');

    creatorEmailBtn = btn;
    creatorEmailLeadId = leadId;
    const input = document.getElementById('creatorEmailInput');
    if (input) input.value = '';
    showModal('creatorEmailModal');
    if (input) setTimeout(() => input.focus(), 50);
}

async function saveCreatorEmail() {
    const input = document.getElementById('creatorEmailInput');
    const email = (input ? input.value : '').trim();
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
        notify('That does not look like an email address', 'error');
        return;
    }
    const btn = creatorEmailBtn;
    const leadId = creatorEmailLeadId;
    closeModal('creatorEmailModal');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving…';
    }
    try {
        const res = await api('creator_set_email', { method: 'POST', body: { lead_id: leadId, email } });
        if (res.success && res.updated > 0) {
            notify('Email saved', 'success');
            loadCreatorLeads();
        } else {
            notify(res.message || 'Could not save the email', 'error');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Get email';
            }
        }
    } catch (e) {
        notify(e.message, 'error');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Get email';
        }
    }
}

function debounceLoadCreatorLeads() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadCreatorLeads, 300);
}

async function loadCreatorLeads() {
    const tbody = document.getElementById('creatorLeadsTableBody');
    if (!tbody) return;

    const params = { source: 'creator_auto' };
    const searchEl = document.getElementById('crFilterSearch');
    const statusEl = document.getElementById('crFilterStatus');
    const responseEl = document.getElementById('crFilterResponse');
    const sortEl = document.getElementById('crFilterSort');
    if (searchEl && searchEl.value.trim()) params.search = searchEl.value.trim();
    if (statusEl && statusEl.value) params.status = statusEl.value;
    if (responseEl && responseEl.value) params.response_status = responseEl.value;
    params.sort = (sortEl && sortEl.value) ? sortEl.value : 'date_added_desc';

    try {
        const data = await api('get_leads', { params });
        if (!data.success || !data.leads.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No partner leads found.</td></tr>';
            if (creatorLeadsPaginator) creatorLeadsPaginator.reset();
            const selectAll = document.getElementById('crLeadsSelectAll');
            if (selectAll) selectAll.checked = false;
            updateCreatorBulkBar();
            return;
        }
        tbody.innerHTML = data.leads.map(lead => {
            const platform = (lead.category || '').replace(/^creator:/, '') || '—';
            return `
            <tr class="lead-row">
                <td class="checkbox-column" onclick="event.stopPropagation()">
                    <div class="checkbox"><input type="checkbox" class="cr-lead-check" value="${lead.id}" id="cr-lead-check-${lead.id}" data-has-draft="${lead.draft_subject ? '1' : ''}" onchange="updateCreatorBulkBar()"><label for="cr-lead-check-${lead.id}"></label></div>
                </td>
                <td>
                    <strong>${esc(lead.business_name)}</strong>
                    ${lead.website ? '<br><a class="website-link" href="' + esc(lead.website) + '" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()">' + esc(lead.website.replace(/^https?:\/\//, '').replace(/\?.*$/, '')) + '</a>' : ''}
                </td>
                <td><span class="badge">${esc(platform)}</span></td>
                <td>${lead.email ? esc(lead.email) : '<span class="text-muted">—</span>'}</td>
                <td><span class="badge badge-status-${lead.status || 'new'}">${formatStatus(lead.status || 'new')}</span></td>
                <td>${lead.sent_at ? formatDateTime(lead.sent_at) : '<span class="text-muted">—</span>'}</td>
                <td>${lead.clicked_at ? formatDateTime(lead.clicked_at) : '<span class="text-muted">—</span>'}</td>
                <td onclick="event.stopPropagation()">
                    <div class="actions-cell">
                        <button class="btn btn-small btn-blue" onclick="openLeadDetail(${lead.id})" title="View">View</button>
                        ${!lead.email
                            ? `<button class="btn btn-small btn-blue" data-lead-id="${lead.id}" data-website="${escapeHtml(lead.website || '')}" onclick="getCreatorEmail(this)" title="Open the channel and paste the email">Get email</button>`
                            : (!lead.draft_subject && !['contacted','replied','interested','not_interested','onboarded'].includes(lead.status)
                                ? `<button class="btn btn-small btn-blue" onclick="quickGenerateDraft(${lead.id}, this)" title="Generate Draft">Draft</button>`
                                : '')}
                    </div>
                </td>
            </tr>`;
        }).join('');

        const selectAll = document.getElementById('crLeadsSelectAll');
        if (selectAll) selectAll.checked = false;
        updateCreatorBulkBar();

        const table = document.querySelector('.creator-leads-table');
        if (table) {
            if (!creatorLeadsPaginator) creatorLeadsPaginator = new TablePaginator(table, { perPage: 25 });
            else creatorLeadsPaginator.reset();
        }
    } catch (e) {
        notify(e.message, 'error');
    }
}


// === Channel switching (Email, Editorial Partners, Creator Partners) ===

const CHANNEL_LISTS = { editorial: loadEditorialLeads, creator: loadCreatorLeads };

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.channel-tab[data-channel]').forEach(btn => {
        btn.addEventListener('click', function () {
            switchChannel(btn.dataset.channel);
        });
    });

    const shown = document.querySelector('.channel-pane.active');
    const load = shown && CHANNEL_LISTS[shown.dataset.channelPane];
    if (load) load();
});

function switchChannel(channel) {
    document.querySelectorAll('.channel-tab').forEach(b => {
        b.classList.toggle('active', b.dataset.channel === channel);
    });
    document.querySelectorAll('.channel-pane').forEach(p => {
        p.classList.toggle('active', p.dataset.channelPane === channel);
    });

    // Sync URL. Only the email channel has tabs of its own.
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('channel', channel);
        if (channel !== 'email') url.searchParams.delete('tab');
        history.replaceState({}, '', url.toString());
    } catch (e) { /* ignore */ }

    if (CHANNEL_LISTS[channel]) CHANNEL_LISTS[channel]();
}
