<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Tab partials (load render + POST handlers)
require_once __DIR__ . '/tabs/settings.php';
require_once __DIR__ . '/tabs/followups.php';

// POSTs from the tab forms are handled before any output so a header() redirect still works, and each must carry the session csrf_token.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tab'])) {
    $postedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!$sessionToken || !$postedToken || !hash_equals($sessionToken, $postedToken)) {
        $_SESSION['message'] = 'Session expired or invalid request token. Please try again.';
        $_SESSION['message_type'] = 'error';
        header('Location: index.php?tab=' . urlencode($_POST['tab'])); exit;
    }
    $postTab = $_POST['tab'];
    if ($postTab === 'settings') {
        settings_tab_handle_post($pdo);
    }
}

// Determine active tab from ?tab=
$activeTab = $_GET['tab'] ?? 'leads';
$allowedTabs = ['leads', 'followups', 'settings'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'leads';
}

// Set page variables for the header
$page_title = "Business Outreach";
$page_description = "Generate outreach emails and track leads";

include __DIR__ . '/../admin_header.php';
?>

<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="../../resources/styles/checkbox.css">

<?php
// Determine which channel is active. Legacy URLs without a channel param
// (including old Reddit links) fall back to email.
$activeChannel = $_GET['channel'] ?? 'email';
if (!in_array($activeChannel, ['email', 'editorial', 'creator'], true)) {
    $activeChannel = 'email';
}
?>

<!-- Channel-level tabs -->
<div class="channel-tabs">
    <button class="channel-tab <?php echo $activeChannel === 'email' ? 'active' : ''; ?>" data-channel="email">Email</button>
    <button class="channel-tab <?php echo $activeChannel === 'editorial' ? 'active' : ''; ?>" data-channel="editorial">Editorial Partners</button>
    <button class="channel-tab <?php echo $activeChannel === 'creator' ? 'active' : ''; ?>" data-channel="creator">Creator Partners</button>
</div>

<!-- Email channel -->
<div class="channel-pane <?php echo $activeChannel === 'email' ? 'active' : ''; ?>" data-channel-pane="email">

<!-- Page-level tabs -->
<div class="section-tabs">
    <button class="section-tab <?php echo $activeTab === 'leads' ? 'active' : ''; ?>" data-tab="leads">Leads</button>
    <button class="section-tab <?php echo $activeTab === 'followups' ? 'active' : ''; ?>" data-tab="followups">Follow-ups</button>
    <button class="section-tab <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" data-tab="settings">Settings</button>
</div>

<div id="leads" class="tab-content <?php echo $activeTab === 'leads' ? 'active' : ''; ?>">

<!-- Pipeline Running Banner -->
<div id="pipelineBanner" style="display:none; background:#fff3cd; color:#856404; border:1px solid #ffc107; border-radius:6px; padding:12px 16px; margin-bottom:16px; font-weight:500;">
    The outreach cron pipeline is currently running. Sending and drafting are temporarily disabled to prevent conflicts.
</div>

<!-- Dashboard Stats -->
<div class="stats-grid" id="statsRow">
    <div class="stat-card">
        <h3>Total Leads</h3>
        <div class="stat-value" id="statTotal">0</div>
    </div>
    <div class="stat-card">
        <h3>New</h3>
        <div class="stat-value stat-new" id="statNew">0</div>
    </div>
    <div class="stat-card">
        <h3>Drafts Pending</h3>
        <div class="stat-value stat-pending" id="statDraftsPending">0</div>
    </div>
    <div class="stat-card">
        <h3>Contacted</h3>
        <div class="stat-value stat-contacted" id="statContacted">0</div>
    </div>
    <div class="stat-card">
        <h3>Replied</h3>
        <div class="stat-value stat-replied" id="statReplied">0</div>
    </div>
    <div class="stat-card">
        <h3>Interested</h3>
        <div class="stat-value stat-interested" id="statInterested">0</div>
    </div>
    <div class="stat-card">
        <h3>Clicked</h3>
        <div class="stat-value stat-clicked" id="statClicked">0</div>
    </div>
</div>

<!-- Leads Management -->
<div class="panel">
    <div class="panel-header">
        <h2>Leads</h2>
        <div class="panel-actions">
            <button class="btn btn-small btn-blue" onclick="showAddLeadModal()">+ Add Lead</button>
            <button class="btn btn-small btn-blue" onclick="showImportCSVModal()">Import CSV</button>
            <button class="btn btn-small btn-blue" onclick="window.open('api.php?action=export_csv', '_blank')">Export CSV</button>
        </div>
    </div>

    <!-- Filters -->
    <div class="control-bar">
            <div class="control-group">
                <span class="control-label">Search</span>
                <input type="text" class="control-input" id="filterSearch" placeholder="Name, email, city..." oninput="debounceLoadLeads()">
            </div>
            <div class="control-group">
                <span class="control-label">Status</span>
                <select class="control-select" id="filterStatus" onchange="loadLeads()">
                    <option value="">All</option>
                    <option value="new">New</option>
                    <option value="draft_generated">Draft Generated</option>
                    <option value="contacted">Contacted</option>
                    <option value="replied">Replied</option>
                    <option value="interested">Interested</option>
                    <option value="not_interested">Not Interested</option>
                    <option value="onboarded">Onboarded</option>
                    <option value="disqualified">Disqualified</option>
                </select>
            </div>
            <div class="control-group">
                <span class="control-label">Response</span>
                <select class="control-select" id="filterResponse" onchange="loadLeads()">
                    <option value="">All</option>
                    <option value="no_response">No Response</option>
                    <option value="positive">Positive</option>
                    <option value="neutral">Neutral</option>
                    <option value="negative">Negative</option>
                </select>
            </div>
            <div class="control-group">
                <span class="control-label">Company Size</span>
                <select class="control-select" id="filterCompanySize" onchange="loadLeads()">
                    <option value="">All Sizes</option>
                    <option value="small">Small</option>
                    <option value="medium">Medium</option>
                    <option value="large">Large</option>
                </select>
            </div>
            <div class="control-group">
                <span class="control-label">Source</span>
                <select class="control-select" id="filterSource" onchange="loadLeads()">
                    <option value="">All</option>
                    <option value="google_places">Google Places</option>
                    <option value="shopify">Shopify</option>
                    <option value="manual">Manual</option>
                    <option value="csv_import">CSV</option>
                </select>
            </div>
            <div class="control-group">
                <span class="control-label">Sort</span>
                <select class="control-select" id="filterSort" onchange="loadLeads()">
                    <option value="date_added_desc">Newest First</option>
                    <option value="date_added_asc">Oldest First</option>

                    <option value="last_contact_desc">Last Contacted</option>
                    <option value="business_name_asc">Name A-Z</option>
                </select>
            </div>
    </div>

    <!-- Bulk Actions -->
    <div class="bulk-actions-bar" id="bulkActionsBar" style="display:none;">
        <span><strong id="selectedCount">0</strong> selected</span>
        <button class="btn btn-small btn-blue" id="btnDraftSelected" onclick="bulkGenerateDrafts()">Draft Selected</button>
        <button class="btn btn-small btn-blue" onclick="openBulkSendModal()">Send Email</button>
        <button class="btn btn-small btn-blue" onclick="bulkDeleteLeads()">Delete Selected</button>
    </div>

    <!-- Bulk Draft Progress -->
    <div class="bulk-draft-progress" id="bulkDraftProgress" style="display:none;">
        <span class="bulk-draft-spinner"></span>
        <span id="bulkDraftProgressText"></span>
        <button class="btn btn-small btn-neutral" id="btnCancelDraft" onclick="cancelBulkDrafts()" style="margin-left:8px;">Cancel</button>
    </div>

    <!-- Leads Table -->
    <div class="leads-table-wrapper">
        <table class="data-table leads-table">
            <thead>
                <tr>
                    <th class="checkbox-column"><div class="checkbox"><input type="checkbox" id="leadsSelectAll" onchange="toggleLeadCheckboxes(this)"><label for="leadsSelectAll"></label></div></th>
                    <th>Business</th>
                    <th>Website</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Category</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Clicked</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="leadsTableBody">
                <tr><td colspan="12" class="empty-state">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

</div> <!-- /#leads -->

<div id="followups" class="tab-content <?php echo $activeTab === 'followups' ? 'active' : ''; ?>">
    <?php followups_tab_render($pdo); ?>
</div>

<div id="settings" class="tab-content <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
    <?php settings_tab_render($pdo); ?>
</div>

</div> <!-- /.channel-pane[data-channel-pane="email"] -->

<!-- Editorial channel -->
<div class="channel-pane <?php echo $activeChannel === 'editorial' ? 'active' : ''; ?>" data-channel-pane="editorial">

    <div id="editorial-leads" class="tab-content active">
        <!-- Filters -->
        <div class="control-bar">
            <div class="control-group">
                <span class="control-label">Search</span>
                <input type="text" class="control-input" id="edFilterSearch" placeholder="Outlet, author, email..." oninput="debounceLoadEditorialLeads()">
            </div>
            <div class="control-group">
                <span class="control-label">Status</span>
                <select class="control-select" id="edFilterStatus" onchange="loadEditorialLeads()">
                    <option value="">All</option>
                    <option value="new">New</option>
                    <option value="draft_generated">Draft Generated</option>
                    <option value="contacted">Contacted</option>
                    <option value="replied">Replied</option>
                    <option value="interested">Interested</option>
                    <option value="not_interested">Not Interested</option>
                    <option value="onboarded">Onboarded</option>
                    <option value="disqualified">Disqualified</option>
                </select>
            </div>
            <div class="control-group">
                <span class="control-label">Response</span>
                <select class="control-select" id="edFilterResponse" onchange="loadEditorialLeads()">
                    <option value="">All</option>
                    <option value="no_response">No Response</option>
                    <option value="positive">Positive</option>
                    <option value="neutral">Neutral</option>
                    <option value="negative">Negative</option>
                </select>
            </div>
            <div class="control-group">
                <span class="control-label">Sort</span>
                <select class="control-select" id="edFilterSort" onchange="loadEditorialLeads()">
                    <option value="date_added_desc">Newest First</option>
                    <option value="date_added_asc">Oldest First</option>
                    <option value="last_contact_desc">Last Contacted</option>
                    <option value="business_name_asc">Name A-Z</option>
                </select>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions-bar" id="edBulkActionsBar" style="display:none;">
            <span><strong id="edSelectedCount">0</strong> selected</span>
            <button class="btn btn-small btn-blue" id="edBtnDraftSelected" onclick="bulkGenerateEditorialDrafts()">Draft Selected</button>
            <button class="btn btn-small btn-blue" onclick="openEditorialBulkSend()">Send Email</button>
            <button class="btn btn-small btn-blue" onclick="bulkDeleteEditorialLeads()">Delete Selected</button>
        </div>

        <!-- Bulk Draft Progress -->
        <div class="bulk-draft-progress" id="edBulkDraftProgress" style="display:none;">
            <span class="bulk-draft-spinner"></span>
            <span id="edBulkDraftProgressText"></span>
            <button class="btn btn-small btn-neutral" id="edBtnCancelDraft" onclick="cancelBulkDrafts()" style="margin-left:8px;">Cancel</button>
        </div>

        <div class="leads-table-wrapper">
            <table class="data-table editorial-leads-table">
                <thead>
                    <tr>
                        <th class="checkbox-column"><div class="checkbox"><input type="checkbox" id="edLeadsSelectAll" onchange="toggleEditorialLeadCheckboxes(this)"><label for="edLeadsSelectAll"></label></div></th>
                        <th>Outlet</th>
                        <th>Article</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Clicked</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="editorialLeadsTableBody"></tbody>
            </table>
        </div>
    </div>
</div> <!-- /.channel-pane[data-channel-pane="editorial"] -->

<div class="channel-pane <?php echo $activeChannel === 'creator' ? 'active' : ''; ?>" data-channel-pane="creator">

    <div id="creator-leads" class="tab-content active">
        <!-- Filters -->
        <div class="control-bar">
            <div class="control-group">
                <span class="control-label">Search</span>
                <input type="text" class="control-input" id="crFilterSearch" placeholder="Creator, email..." oninput="debounceLoadCreatorLeads()">
            </div>
            <div class="control-group">
                <span class="control-label">Status</span>
                <select class="control-select" id="crFilterStatus" onchange="loadCreatorLeads()">
                    <option value="">All</option>
                    <option value="new">New</option>
                    <option value="draft_generated">Draft Generated</option>
                    <option value="contacted">Contacted</option>
                    <option value="replied">Replied</option>
                    <option value="interested">Interested</option>
                    <option value="not_interested">Not Interested</option>
                    <option value="onboarded">Onboarded</option>
                    <option value="disqualified">Disqualified</option>
                </select>
            </div>
            <div class="control-group">
                <span class="control-label">Response</span>
                <select class="control-select" id="crFilterResponse" onchange="loadCreatorLeads()">
                    <option value="">All</option>
                    <option value="no_response">No Response</option>
                    <option value="positive">Positive</option>
                    <option value="neutral">Neutral</option>
                    <option value="negative">Negative</option>
                </select>
            </div>
            <div class="control-group">
                <span class="control-label">Sort</span>
                <select class="control-select" id="crFilterSort" onchange="loadCreatorLeads()">
                    <option value="date_added_desc">Newest First</option>
                    <option value="date_added_asc">Oldest First</option>
                    <option value="last_contact_desc">Last Contacted</option>
                    <option value="business_name_asc">Name A-Z</option>
                </select>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions-bar" id="crBulkActionsBar" style="display:none;">
            <span><strong id="crSelectedCount">0</strong> selected</span>
            <button class="btn btn-small btn-blue" id="crBtnDraftSelected" onclick="bulkGenerateCreatorDrafts()">Draft Selected</button>
            <button class="btn btn-small btn-blue" onclick="openCreatorBulkSend()">Send Email</button>
            <button class="btn btn-small btn-blue" onclick="bulkDeleteCreatorLeads()">Delete Selected</button>
        </div>

        <!-- Bulk Draft Progress -->
        <div class="bulk-draft-progress" id="crBulkDraftProgress" style="display:none;">
            <span class="bulk-draft-spinner"></span>
            <span id="crBulkDraftProgressText"></span>
            <button class="btn btn-small btn-neutral" id="crBtnCancelDraft" onclick="cancelBulkDrafts()" style="margin-left:8px;">Cancel</button>
        </div>

        <div class="leads-table-wrapper">
            <table class="data-table creator-leads-table">
                <thead>
                    <tr>
                        <th class="checkbox-column"><div class="checkbox"><input type="checkbox" id="crLeadsSelectAll" onchange="toggleCreatorLeadCheckboxes(this)"><label for="crLeadsSelectAll"></label></div></th>
                        <th>Creator</th>
                        <th>Platform</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Clicked</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="creatorLeadsTableBody"></tbody>
            </table>
        </div>
    </div>
</div> <!-- /.channel-pane[data-channel-pane="creator"] -->

<!-- Lead Detail Modal -->
<div id="leadDetailModal" class="modal" style="display:none;">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="detailModalTitle">Lead Details</h3>
            <button class="modal-close" onclick="closeModal('leadDetailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('tabInfo', this)">Info</button>
                <button class="tab" onclick="switchTab('tabDraft', this)">Email Draft</button>
                <button class="tab" onclick="switchTab('tabActivity', this)">Activity</button>
                <button class="tab" onclick="switchTab('tabFollowups', this); loadLeadFollowups();">Follow-ups</button>
            </div>

            <!-- Info Tab -->
            <div id="tabInfo" class="tab-content active">
                <div class="detail-grid">
                    <div class="form-group">
                        <label>Business Name</label>
                        <input type="text" id="detailBusinessName">
                    </div>
                    <div class="form-group">
                        <label>Contact Name</label>
                        <input type="text" id="detailContactName">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="detailEmail">
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <div class="input-with-btn">
                            <input type="url" id="detailWebsite">
                            <button class="btn btn-small btn-blue" onclick="openWebsite()" title="Open in new tab">Open</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" id="detailCategory">
                    </div>
                    <div class="form-group">
                        <label>Source</label>
                        <input type="text" id="detailSource" readonly>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="detailStatus">
                            <option value="new">New</option>
                            <option value="draft_generated">Draft Generated</option>
                            <option value="contacted">Contacted</option>
                            <option value="replied">Replied</option>
                            <option value="interested">Interested</option>
                            <option value="not_interested">Not Interested</option>
                            <option value="onboarded">Onboarded</option>
                            <option value="disqualified">Disqualified</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Company Size</label>
                        <select id="detailCompanySize">
                            <option value="">Unknown</option>
                            <option value="small">Small</option>
                            <option value="medium">Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Page URL</label>
                        <input type="url" id="detailContactPageUrl">
                    </div>
                </div>
                <div class="form-group full-width">
                    <label>Notes</label>
                    <textarea id="detailNotes" rows="4" placeholder="Add notes about this lead..."></textarea>
                </div>
                <div class="detail-actions">
                    <button class="btn btn-red" onclick="deleteCurrentLead()">Delete Lead</button>
                    <button class="btn btn-blue" onclick="saveLeadDetails()">Save Changes</button>
                </div>
            </div>

            <!-- Draft Tab -->
            <div id="tabDraft" class="tab-content">
                <div class="draft-section">
                    <div class="draft-status-bar" id="draftStatusBar"></div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" id="draftSubject" placeholder="Email subject...">
                    </div>
                    <div class="form-group">
                        <label>Message Body</label>
                        <textarea id="draftBody" rows="12" placeholder="Email body..."></textarea>
                    </div>
                    <div class="draft-actions">
                        <button class="btn btn-blue" onclick="generateDraft()" id="btnGenerate">Generate Draft</button>
                        <button class="btn btn-blue" onclick="saveDraft()" id="btnSaveDraft">Save Draft</button>
                        <button class="btn btn-blue" onclick="sendEmail()" id="btnSend" disabled>Send Email</button>
                        <button class="btn btn-blue btn-small draft-copy-btn" onclick="copyDraft(this)">Copy</button>
                    </div>
                    <div class="draft-info" id="draftInfo"></div>
                    <div class="form-group" style="margin-top:16px;">
                        <label>Follow-up</label>
                        <div id="followupStatus" class="text-muted" style="margin-top:4px; font-size:13px;">—</div>
                    </div>
                </div>
            </div>

            <!-- Activity Tab -->
            <div id="tabActivity" class="tab-content">
                <div id="activityTimeline" class="activity-timeline">
                    <p class="empty-state-text">Loading activity...</p>
                </div>
            </div>

            <!-- Follow-ups Tab -->
            <div id="tabFollowups" class="tab-content">
                <div id="leadFollowupsList">
                    <p class="empty-state-text">Loading follow-ups...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Lead Modal -->
<div id="addLeadModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Add New Lead</h3>
            <button class="modal-close" onclick="closeModal('addLeadModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-top:0; font-size:13px;">
                Just paste the business's website. We'll fetch the page and auto-fill the name, email, phone, category, city, and a short summary. You can edit anything afterward by opening the lead.
            </p>
            <div class="form-group full-width">
                <label>Website <span class="required">*</span></label>
                <input type="url" id="addWebsite" placeholder="example.com">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-blue" onclick="closeModal('addLeadModal')">Cancel</button>
            <button class="btn btn-blue" id="btnAddLead" onclick="createLead()">Add Lead</button>
        </div>
    </div>
</div>

<!-- CSV Import Modal -->
<div id="csvImportModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Import Leads from CSV</h3>
            <button class="modal-close" onclick="closeModal('csvImportModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Upload a CSV file with lead data. The file should have headers matching: Business Name, Contact Name, Email, Phone, Website, Address, Category, City, Notes.</p>
            <div class="form-group">
                <label for="csvFile">CSV File</label>
                <input type="file" id="csvFile" accept=".csv">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-blue" onclick="closeModal('csvImportModal')">Cancel</button>
            <button class="btn btn-blue" onclick="importCSV()">Import</button>
        </div>
    </div>
</div>

<!-- Bulk Send Email Modal -->
<div id="bulkSendModal" class="modal" style="display:none;">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Send Emails</h3>
            <button class="modal-close" onclick="closeBulkSendModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding:0;">
            <div id="bulkSendStatus" class="bulk-send-status"></div>
            <div id="bulkSendList" class="bulk-send-list"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-blue" onclick="closeBulkSendModal()">Cancel</button>
            <button class="btn btn-blue" id="btnBulkSend" disabled>Send All</button>
        </div>
    </div>
</div>

<!-- Creator: paste email modal -->
<div id="creatorEmailModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Paste the creator's email</h3>
            <button class="modal-close" onclick="closeModal('creatorEmailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-top:0; font-size:13px;">
                On the channel page that just opened, reveal the email (solve the captcha if asked), then paste it here.
            </p>
            <div class="form-group full-width">
                <label>Email <span class="required">*</span></label>
                <input type="email" id="creatorEmailInput" placeholder="creator@example.com" autocomplete="off"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();saveCreatorEmail();}">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-blue" onclick="closeModal('creatorEmailModal')">Cancel</button>
            <button class="btn btn-blue" onclick="saveCreatorEmail()">Save email</button>
        </div>
    </div>
</div>

<script src="outreach.js"></script>

        </main>
    </div>
</body>

</html>
