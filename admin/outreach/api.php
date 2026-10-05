<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_sender.php';

// Admin auth check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Load shared outreach helpers (scrape_email_from_website, call_gemini, etc.)
require_once __DIR__ . '/../../cron/lib/outreach_helpers.php';

// CSRF protection for state-changing (non-GET) requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $csrfSession = $_SESSION['csrf_token'] ?? null;
    $csrfRequest = $_POST['csrf_token'] ?? '';
    if (empty($csrfRequest)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfRequest = $input['csrf_token'] ?? '';
    }
    if (!$csrfSession || !$csrfRequest || !hash_equals($csrfSession, $csrfRequest)) {
        outreach_log("CSRF token validation failed for action: " . ($_GET['action'] ?? 'unknown'));
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

// Release the session lock now that auth + CSRF have been read. PHP's default
// file session handler holds an EXCLUSIVE lock from session_start() until the
// request ends, so without this a long-running action (drafting a batch of
// emails takes a while) blocks every other request carrying the same session
// cookie at its own session_start() — admin pages AND public pages like the
// landing page would hang until it finished. No handler below writes to
// $_SESSION, so closing it here is safe.
session_write_close();

// Ensure tables exist

// Check if the cron pipeline is currently running
function is_pipeline_running(): bool
{
    $lockFile = __DIR__ . '/../../cron/logs/outreach_pipeline.lock';
    if (!file_exists($lockFile)) {
        return false;
    }
    $fp = @fopen($lockFile, 'r');
    if (!$fp) {
        return false;
    }
    // If we can get the lock, the pipeline is NOT running
    $locked = !flock($fp, LOCK_EX | LOCK_NB);
    fclose($fp);
    return $locked;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Block actions that conflict with a running pipeline
$pipelineActions = ['send_email', 'generate_draft', 'regenerate_followup'];
if (in_array($action, $pipelineActions) && is_pipeline_running()) {
    echo json_encode([
        'success' => false,
        'message' => 'The outreach pipeline cron job is currently running. Please wait a few minutes and try again.'
    ]);
    exit;
}

switch ($action) {
    // Lead CRUD
    case 'get_leads':
        get_leads($pdo);
        break;
    case 'get_lead':
        get_lead($pdo);
        break;
    case 'create_lead_from_website':
        create_lead_from_website($pdo);
        break;
    case 'update_lead':
        update_lead($pdo);
        break;
    case 'delete_lead':
        delete_lead($pdo);
        break;
    case 'get_stats':
        get_stats($pdo);
        break;

    // Creator leads
    case 'creator_set_email':
        creator_set_email($pdo);
        break;

    // AI draft
    case 'generate_draft':
        generate_draft($pdo);
        break;
    // Email workflow
    case 'send_email':
        send_outreach_email($pdo);
        break;
    case 'bulk_get_leads':
        bulk_get_leads($pdo);
        break;

    // Activity
    case 'get_activity':
        get_activity($pdo);
        break;

    // AI classification
    case 'classify_company_sizes':
        classify_company_sizes($pdo);
        break;

    // CSV
    case 'export_csv':
        export_csv($pdo);
        break;
    case 'import_csv':
        import_csv($pdo);
        break;

    // Follow-ups
    case 'get_followups':
        get_followups($pdo);
        break;
    case 'approve_followup':
        approve_followup($pdo);
        break;
    case 'regenerate_followup':
        regenerate_followup($pdo);
        break;
    case 'skip_followup':
        skip_followup($pdo);
        break;
    case 'halt_followup_sequence':
        halt_followup_sequence($pdo);
        break;
    case 'bulk_approve_followups':
        bulk_approve_followups($pdo);
        break;
    case 'bulk_skip_followups':
        bulk_skip_followups($pdo);
        break;
    case 'bulk_halt_followups':
        bulk_halt_followups($pdo);
        break;
    case 'get_followups_for_lead':
        get_followups_for_lead($pdo);
        break;
    case 'save_followup_draft':
        save_followup_draft($pdo);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}


// log_activity() is provided by cron/lib/outreach_helpers.php

// ─── JSON response helper ───

function json_response($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ─── File-based error logging ───

function outreach_log($message)
{
    // Sanitize message to avoid log injection (strip newlines)
    $sanitizedMessage = preg_replace('/[\r\n]+/', ' ', (string) $message);
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[outreach][$timestamp] " . $sanitizedMessage;
    // Use PHP's error_log to avoid writing to web-accessible directory
    @error_log($entry);
}

// ─── Lead CRUD ───

function get_leads($pdo)
{
    $status = $_GET['status'] ?? '';
    $response_status = $_GET['response_status'] ?? '';
    $company_size = $_GET['company_size'] ?? '';
    $source = $_GET['source'] ?? '';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'date_added_desc';

    $where = [];
    $params = [];

    if ($status) {
        $where[] = 'ol.status = ?';
        $params[] = $status;
    }
    if ($response_status) {
        $where[] = 'ol.response_status = ?';
        $params[] = $response_status;
    }
    if ($company_size) {
        $where[] = 'ol.company_size = ?';
        $params[] = $company_size;
    }
    // Source filter groups UI + cron variants together (google_places and
    // google_places_auto both match "google_places"; shopify_auto matches
    // "shopify") so admins don't have to think about the channel split.
    if ($source) {
        if ($source === 'google_places') {
            $where[] = "ol.source IN ('google_places', 'google_places_auto')";
        } elseif ($source === 'shopify') {
            $where[] = "ol.source IN ('shopify', 'shopify_auto')";
        } else {
            $where[] = 'ol.source = ?';
            $params[] = $source;
        }
    } else {
        // Editorial and Creator leads have their own channel tabs (their source
        // is requested explicitly there). Keep them out of the default Email
        // leads list so the channels stay organized and separate.
        $where[] = "ol.source NOT IN ('editorial_auto', 'creator_auto')";
    }
    if ($search) {
        $where[] = '(ol.business_name LIKE ? OR ol.email LIKE ? OR ol.contact_name LIKE ? OR ol.city LIKE ? OR ol.category LIKE ?)';
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s, $s, $s]);
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $orderMap = [
        'date_added_desc' => 'ol.date_added DESC',
        'date_added_asc' => 'ol.date_added ASC',
        'last_contact_desc' => 'ol.last_contact_date DESC',
        'business_name_asc' => 'ol.business_name ASC',
        'status_asc' => 'ol.status ASC',
    ];
    $orderBy = $orderMap[$sort] ?? 'ol.date_added DESC';

    // Match the current source code ("outreach-42") plus any legacy variant-tagged
    // codes from before A/B was removed ("outreach-42-v7") so old clicks still count.
    $stmt = $pdo->prepare("SELECT ol.*, MIN(rv.visited_at) AS clicked_at FROM outreach_leads ol LEFT JOIN referral_visits rv ON (rv.source_code = CONCAT('outreach-', ol.id) OR rv.source_code LIKE CONCAT('outreach-', ol.id, '-v%')) $whereClause GROUP BY ol.id ORDER BY $orderBy");
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    json_response(['success' => true, 'leads' => $leads]);
}

function get_lead($pdo)
{
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM outreach_leads WHERE id = ?");
    $stmt->execute([$id]);
    $lead = $stmt->fetch();

    if (!$lead) {
        json_response(['success' => false, 'message' => 'Lead not found'], 404);
    }

    json_response(['success' => true, 'lead' => $lead]);
}

function bulk_get_leads($pdo)
{
    $idsParam = $_GET['ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $idsParam)));

    if (empty($ids)) {
        json_response(['success' => false, 'message' => 'No IDs provided'], 400);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM outreach_leads WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $leads = $stmt->fetchAll();

    json_response(['success' => true, 'leads' => $leads]);
}

/**
 * Create a lead from just a website URL: fetch the site, auto-fill business
 * name / email / phone / category / city / summary via enrich_lead_from_website,
 * then insert. Backs the slimmed-down "Add Lead" modal (one website field).
 */
function create_lead_from_website($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $website = trim((string) ($data['website'] ?? ''));

    if ($website === '') {
        json_response(['success' => false, 'message' => 'Website is required'], 400);
    }
    if (!preg_match('#^https?://#i', $website)) {
        $website = 'https://' . $website;
    }
    if (!filter_var($website, FILTER_VALIDATE_URL)) {
        json_response(['success' => false, 'message' => "That doesn't look like a valid website URL"], 400);
    }

    // Enrichment fetches the site + makes one Gemini call, so give it room.
    @set_time_limit(60);
    $enriched = enrich_lead_from_website($website);

    $finalWebsite = $enriched['website'] ?: $website;
    $email = $enriched['email'] ?: null;

    // Dedup by website (and email when we found one), like the other channels.
    if ($email) {
        $check = $pdo->prepare("SELECT id FROM outreach_leads WHERE website = ? OR email = ? LIMIT 1");
        $check->execute([$finalWebsite, $email]);
    } else {
        $check = $pdo->prepare("SELECT id FROM outreach_leads WHERE website = ? LIMIT 1");
        $check->execute([$finalWebsite]);
    }
    if ($existing = $check->fetchColumn()) {
        json_response([
            'success'          => false,
            'message'          => 'A lead with this website or email already exists',
            'existing_lead_id' => (int) $existing,
        ], 409);
    }

    $stmt = $pdo->prepare("INSERT INTO outreach_leads
        (business_name, email, phone, website, address, category, city, source, status, business_summary)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'manual', 'new', ?)");
    $stmt->execute([
        $enriched['business_name'] ?: $finalWebsite,
        $email,
        $enriched['phone'] ?: null,
        $finalWebsite,
        $enriched['address'] ?: null,
        $enriched['category'] ?: null,
        $enriched['city'] ?: null,
        $enriched['business_summary'] ?: null,
    ]);

    $id = (int) $pdo->lastInsertId();
    log_activity($pdo, $id, 'lead_created', 'Lead auto-added from website: ' . $finalWebsite);

    json_response([
        'success'  => true,
        'id'       => $id,
        'enriched' => $enriched,
        'message'  => 'Lead added: ' . ($enriched['business_name'] ?: $finalWebsite),
    ]);
}

function update_lead($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = (int)($data['id'] ?? 0);

    if (!$id) {
        json_response(['success' => false, 'message' => 'Lead ID is required'], 400);
    }

    $fields = [
        'business_name', 'contact_name', 'email', 'phone', 'website', 'address',
        'category', 'city', 'source', 'status', 'response_status',
        'notes', 'feedback_summary', 'offer_sent',
        'draft_subject', 'draft_body', 'contact_page_url',
        'first_contact_date', 'last_contact_date', 'company_size',
    ];

    $setClauses = [];
    $params = [];
    $changes = [];

    foreach ($fields as $field) {
        if (array_key_exists($field, $data)) {
            $setClauses[] = "$field = ?";
            $value = $data[$field];
            if ($value === '') $value = null;
            if ($field === 'offer_sent') $value = $value ? 1 : 0;
            $params[] = $value;
            $changes[] = $field;
        }
    }

    if (empty($setClauses)) {
        json_response(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $params[] = $id;
    $sql = "UPDATE outreach_leads SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Log notable changes
    if (in_array('status', $changes)) {
        log_activity($pdo, $id, 'status_changed', 'Status changed to: ' . $data['status']);
    }
    if (in_array('notes', $changes)) {
        log_activity($pdo, $id, 'notes_updated', 'Notes updated');
    }

    json_response(['success' => true, 'message' => 'Lead updated']);
}

function delete_lead($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = (int)($data['id'] ?? 0);

    if (!$id) {
        outreach_log("Delete failed: no lead ID provided");
        json_response(['success' => false, 'message' => 'Lead ID is required'], 400);
    }

    try {
        // Check lead exists before deleting anything
        $stmt = $pdo->prepare("SELECT id FROM outreach_leads WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            outreach_log("Delete failed: lead ID $id not found");
            json_response(['success' => false, 'message' => 'Lead not found'], 404);
        }

        $pdo->beginTransaction();

        // Delete activity log entries first
        $stmt = $pdo->prepare("DELETE FROM outreach_activity_log WHERE lead_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM outreach_leads WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        json_response(['success' => true, 'message' => 'Lead deleted']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        outreach_log("Delete failed for lead ID $id: " . $e->getMessage());
        json_response(['success' => false, 'message' => 'Failed to delete lead'], 500);
    }
}

function get_stats($pdo)
{
    $stats = [];
    $rows = $pdo->query("SELECT
        COUNT(*) as total,
        SUM(status = 'new') as new_leads,
        SUM(status = 'draft_generated') as drafts_pending,
        SUM(status = 'contacted') as contacted,
        SUM(status = 'replied') as replied,
        SUM(status = 'interested') as interested
    FROM outreach_leads")->fetch();

    // Count distinct leads clicked. SUBSTRING_INDEX collapses any legacy
    // variant-tagged code "outreach-42-v7" → "outreach-42" so a lead counts once.
    $rows['clicked'] = $pdo->query("SELECT COUNT(DISTINCT SUBSTRING_INDEX(rv.source_code, '-v', 1)) FROM referral_visits rv WHERE rv.source_code LIKE 'outreach-%'")->fetchColumn();

    json_response([
        'success' => true,
        'stats' => $rows,
        'pipeline_running' => is_pipeline_running(),
    ]);
}

// Apply an email captured via the lead's "Get email" button back onto a creator
// lead. Accepts a single {lead_id, email}, or {results: [{lead_id, url, email}]}.
function creator_set_email($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (is_array($data) && array_is_list($data)) {
        // Raw top-level array (the helper's output.json posted directly).
        $results = $data;
    } else {
        $results = $data['results'] ?? null;
        if (!is_array($results)) {
            // Single-item form.
            if (isset($data['email'])) {
                $results = [['lead_id' => $data['lead_id'] ?? null, 'url' => $data['url'] ?? null, 'email' => $data['email']]];
            } else {
                json_response(['success' => false, 'message' => 'Provide results[] or a single {lead_id/url, email}'], 400);
            }
        }
    }

    $updated = 0;
    $skipped = 0;
    foreach ($results as $row) {
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }

        $leadId = (int) ($row['lead_id'] ?? 0);
        $url    = trim((string) ($row['url'] ?? ''));

        if ($leadId > 0) {
            $stmt = $pdo->prepare("UPDATE outreach_leads SET email = ? WHERE id = ? AND source = 'creator_auto' AND (email IS NULL OR email = '')");
            $stmt->execute([$email, $leadId]);
        } elseif ($url !== '') {
            $stmt = $pdo->prepare("UPDATE outreach_leads SET email = ? WHERE website = ? AND source = 'creator_auto' AND (email IS NULL OR email = '')");
            $stmt->execute([$email, $url]);
        } else {
            $skipped++;
            continue;
        }

        if ($stmt->rowCount() > 0) {
            $updated++;
        } else {
            $skipped++;
        }
    }

    json_response(['success' => true, 'updated' => $updated, 'skipped' => $skipped]);
}

// ─── AI Draft Generation ───
// Core logic (call_gemini, summarize_business, generate_draft_for_lead) lives in cron/lib/outreach_helpers.php

function generate_draft($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM outreach_leads WHERE id = ?");
    $stmt->execute([$id]);
    $lead = $stmt->fetch();

    if (!$lead) {
        json_response(['success' => false, 'message' => 'Lead not found'], 404);
    }

    $result = generate_draft_for_lead($pdo, $lead);

    if (isset($result['error'])) {
        json_response(['success' => false, 'message' => $result['error']], 500);
    }

    // The AI size gate (Layer 3 of the outreach auto-filter) can decide
    // mid-draft that this lead is a chain/corp/institution and disqualify it
    // instead of returning a draft. log_activity + status update were already
    // done inside disqualify_lead(); surface it to the admin with a 409 so
    // the UI can show "Disqualified" rather than render an empty subject/body.
    if (!empty($result['disqualified'])) {
        json_response([
            'success' => false,
            'disqualified' => true,
            'reason' => $result['reason'] ?? 'auto_filter',
            'message' => 'Lead disqualified by the auto-filter (' . ($result['reason'] ?? 'auto_filter') . '): ' . ($result['detail'] ?? ''),
        ], 409);
    }

    log_activity($pdo, $id, 'draft_generated', 'AI draft generated');

    json_response(['success' => true, 'subject' => $result['subject'], 'body' => $result['body']]);
}

// ─── Email workflow ───

function send_outreach_email($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM outreach_leads WHERE id = ?");
    $stmt->execute([$id]);
    $lead = $stmt->fetch();

    if (!$lead) {
        json_response(['success' => false, 'message' => 'Lead not found'], 404);
    }

    if (empty($lead['email'])) {
        json_response(['success' => false, 'message' => 'No email address for this lead'], 400);
    }

    if (empty($lead['draft_subject']) || empty($lead['draft_body'])) {
        json_response(['success' => false, 'message' => 'No draft to send'], 400);
    }

    // Guard against sending to disqualified leads. The auto-filter (chain
    // domain, place type, AI size gate) caught this lead for a reason; if
    // the admin really wants to override, they can clear status='disqualified'
    // on the row directly. Refusing here keeps the UI's bulk-send flow safe.
    if (($lead['status'] ?? '') === 'disqualified') {
        $reasonTag = $lead['disqualified_reason'] ?? 'unspecified';
        json_response([
            'success' => false,
            'message' => 'Lead was disqualified by the auto-filter (' . $reasonTag . '). Clear the disqualification first if you really want to send.'
        ], 409);
    }

    // Guard against re-sending to the same lead. Cold-outreach resends are
    // a spam-filter red flag and we never want this to happen by accident,
    // whether from the detail modal, the bulk-send flow, or a stray API call.
    //
    // Exception: if the previous send bounced (status='email_bounced'), the
    // earlier attempt didn't reach a real inbox, so a deliberate retry after
    // the admin has fixed the address isn't a duplicate. Reset sent_at and
    // status so send_outreach_lead's atomic claim works and the post-send
    // CASE in cron/lib/outreach_helpers.php can promote status back to
    // 'contacted'. The suppression list still blocks sends to the bounced
    // address itself; if the admin didn't actually fix the email, the new
    // send attempt will be caught and refused as 'suppressed' instead.
    if (!empty($lead['sent_at'])) {
        if (($lead['status'] ?? '') === 'email_bounced') {
            $pdo->prepare("UPDATE outreach_leads SET sent_at = NULL, status = 'approved' WHERE id = ?")
                ->execute([$id]);
            $lead['sent_at'] = null;
            $lead['status']  = 'approved';
            log_activity($pdo, $id, 'resend_after_bounce', 'Admin retrying send after previous bounce; email is now: ' . $lead['email']);
        } else {
            json_response([
                'success' => false,
                'message' => 'This lead was already emailed on ' . $lead['sent_at'] . '. Outreach does not resend to the same address.'
            ], 409);
        }
    }

    $reason = null;
    if (send_outreach_lead($pdo, $lead, $reason)) {
        log_activity($pdo, $id, 'email_sent', 'Outreach email sent to: ' . $lead['email']);
        json_response(['success' => true, 'message' => 'Email sent successfully']);
    }

    // Skip outcomes (already sent / suppressed) are logged inside
    // send_outreach_lead, so don't double-log them as failures here. Map each
    // to an honest user-facing message and HTTP code.
    if ($reason === 'already_sent') {
        json_response([
            'success' => false,
            'message' => 'This lead was just sent by the automated pipeline. No action needed.',
        ], 409);
    }
    if ($reason === 'suppressed') {
        json_response([
            'success' => false,
            'message' => 'This email is on the outreach suppression list (previous unsubscribe). Skipped.',
        ], 409);
    }

    // Genuine failure (smtp_failed or unknown).
    log_activity($pdo, $id, 'email_failed', 'Email send failed for: ' . $lead['email']);
    json_response(['success' => false, 'message' => 'Failed to send email. Check SMTP configuration.'], 500);
}

// ─── Activity log ───

function get_activity($pdo)
{
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM outreach_activity_log WHERE lead_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$id]);
    $activity = $stmt->fetchAll();

    json_response(['success' => true, 'activity' => $activity]);
}

// ─── CSV Export/Import ───

function export_csv($pdo)
{
    $status = $_GET['status'] ?? '';
    $where = '';
    $params = [];
    if ($status) {
        $where = 'WHERE status = ?';
        $params[] = $status;
    }

    $stmt = $pdo->prepare("SELECT * FROM outreach_leads $where ORDER BY date_added DESC");
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="outreach_leads_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    $headers = ['ID', 'Business Name', 'Contact Name', 'Email', 'Phone', 'Website', 'Address',
        'Category', 'City', 'Source', 'Status', 'Response Status',
        'Date Added', 'First Contact', 'Last Contact', 'Offer Sent',
        'Notes', 'Feedback Summary', 'Draft Subject', 'Draft Body'];
    fputcsv($output, $headers);

    foreach ($leads as $lead) {
        fputcsv($output, [
            $lead['id'], $lead['business_name'], $lead['contact_name'], $lead['email'],
            $lead['phone'], $lead['website'], $lead['address'], $lead['category'],
            $lead['city'], $lead['source'], $lead['status'], $lead['response_status'],
            $lead['date_added'], $lead['first_contact_date'],
            $lead['last_contact_date'], $lead['offer_sent'],
            $lead['notes'], $lead['feedback_summary'], $lead['draft_subject'], $lead['draft_body'],
        ]);
    }

    fclose($output);
    exit;
}

function import_csv($pdo)
{
    if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'No CSV file uploaded or upload error'], 400);
    }

    $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$file) {
        json_response(['success' => false, 'message' => 'Could not open CSV file'], 500);
    }

    $headerRow = fgetcsv($file);
    if (!$headerRow) {
        json_response(['success' => false, 'message' => 'Empty CSV file'], 400);
    }

    // Normalize headers
    $headerMap = array_map(function ($h) {
        return strtolower(trim(str_replace([' ', '-'], '_', $h)));
    }, $headerRow);

    $fieldMap = [
        'business_name' => ['business_name', 'business', 'name', 'company', 'company_name'],
        'contact_name' => ['contact_name', 'contact', 'contact_person'],
        'email' => ['email', 'email_address', 'e_mail'],
        'phone' => ['phone', 'phone_number', 'telephone'],
        'website' => ['website', 'url', 'web'],
        'address' => ['address', 'street_address', 'location'],
        'category' => ['category', 'industry', 'type', 'business_type'],
        'city' => ['city', 'town', 'area'],
        'notes' => ['notes', 'note', 'comments'],
    ];

    $columnIndex = [];
    foreach ($fieldMap as $field => $aliases) {
        foreach ($aliases as $alias) {
            $idx = array_search($alias, $headerMap);
            if ($idx !== false) {
                $columnIndex[$field] = $idx;
                break;
            }
        }
    }

    if (!isset($columnIndex['business_name'])) {
        json_response(['success' => false, 'message' => 'CSV must have a Business Name column'], 400);
    }

    $imported = 0;
    $skipped = 0;
    // Pre-prepare the dedup lookup so we don't re-prepare per row
    $dedupStmt = $pdo->prepare("SELECT id FROM outreach_leads WHERE LOWER(email) = LOWER(?) LIMIT 1");
    while (($row = fgetcsv($file)) !== false) {
        $businessName = trim($row[$columnIndex['business_name']] ?? '');
        if (empty($businessName)) continue;

        $email = isset($columnIndex['email']) ? trim($row[$columnIndex['email']] ?? '') : '';

        // Dedup by email so re-importing the same CSV (or one that overlaps
        // with previously imported leads) doesn't create duplicate rows that
        // would each get their own outreach email.
        if ($email !== '') {
            $dedupStmt->execute([$email]);
            if ($dedupStmt->fetchColumn()) {
                $skipped++;
                continue;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO outreach_leads
            (business_name, contact_name, email, phone, website, address, category, city, source, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'csv_import', ?)");

        $stmt->execute([
            $businessName,
            isset($columnIndex['contact_name']) ? trim($row[$columnIndex['contact_name']] ?? '') ?: null : null,
            $email !== '' ? $email : null,
            isset($columnIndex['phone']) ? trim($row[$columnIndex['phone']] ?? '') ?: null : null,
            isset($columnIndex['website']) ? trim($row[$columnIndex['website']] ?? '') ?: null : null,
            isset($columnIndex['address']) ? trim($row[$columnIndex['address']] ?? '') ?: null : null,
            isset($columnIndex['category']) ? trim($row[$columnIndex['category']] ?? '') ?: null : null,
            isset($columnIndex['city']) ? trim($row[$columnIndex['city']] ?? '') ?: null : null,
            isset($columnIndex['notes']) ? trim($row[$columnIndex['notes']] ?? '') ?: null : null,
        ]);

        $id = $pdo->lastInsertId();
        log_activity($pdo, $id, 'lead_created', 'Imported from CSV: ' . $businessName);
        $imported++;
    }

    fclose($file);
    $message = "Imported $imported leads from CSV";
    if ($skipped > 0) {
        $message .= " ($skipped skipped as duplicates)";
    }
    json_response(['success' => true, 'imported' => $imported, 'skipped' => $skipped, 'message' => $message]);
}

// ─── Follow-up endpoints ───

function get_followups($pdo)
{
    $view = $_GET['view'] ?? 'pending_review';
    $validViews = ['pending_review', 'approved', 'upcoming', 'sent', 'halted'];
    if (!in_array($view, $validViews, true)) {
        $view = 'pending_review';
    }

    $sql = "SELECT f.*, l.business_name, l.email AS lead_email, l.city, l.draft_subject AS original_subject
            FROM outreach_followups f
            JOIN outreach_leads l ON l.id = f.lead_id
            WHERE ";

    switch ($view) {
        case 'pending_review':
            $sql .= "f.status = 'drafted' AND f.scheduled_for <= DATE_ADD(NOW(), INTERVAL 2 DAY)";
            $sql .= " ORDER BY f.scheduled_for ASC";
            break;
        case 'approved':
            $sql .= "f.status = 'approved'";
            $sql .= " ORDER BY f.scheduled_for ASC";
            break;
        case 'upcoming':
            $sql .= "f.status = 'scheduled'";
            $sql .= " ORDER BY f.scheduled_for ASC";
            break;
        case 'sent':
            $sql .= "f.status = 'sent' AND f.sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $sql .= " ORDER BY f.sent_at DESC";
            break;
        case 'halted':
            $sql .= "f.status IN ('halted','failed','skipped') AND f.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $sql .= " ORDER BY f.updated_at DESC";
            break;
    }
    $sql .= " LIMIT 200";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'view' => $view, 'rows' => $rows]);
}

function approve_followup($pdo)
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid id']); return;
    }
    $stmt = $pdo->prepare("UPDATE outreach_followups SET status = 'approved'
    WHERE id = ? AND status = 'drafted'
      AND draft_subject IS NOT NULL AND draft_subject <> ''
      AND draft_body IS NOT NULL AND draft_body <> ''");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Row not in drafted state, or draft subject/body is empty']); return;
    }
    echo json_encode(['success' => true]);
}

function regenerate_followup($pdo)
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid id']); return;
    }
    $stmt = $pdo->prepare("SELECT * FROM outreach_followups WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Not found']); return;
    }
    if (!in_array($row['status'], ['drafted', 'failed'], true)) {
        echo json_encode(['success' => false, 'message' => 'Can only regenerate drafted or failed rows']); return;
    }

    // Reset attempts so regen has a fresh budget
    $pdo->prepare("UPDATE outreach_followups SET draft_attempts = 0, status = 'scheduled' WHERE id = ?")
        ->execute([$id]);
    // Re-fetch with the updated state
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $ok = draft_followup_via_gemini($pdo, $row);
    if ($ok) {
        // Return the new draft for the UI
        $newRow = $pdo->prepare("SELECT draft_subject, draft_body FROM outreach_followups WHERE id = ?");
        $newRow->execute([$id]);
        $r = $newRow->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'draft_subject' => $r['draft_subject'], 'draft_body' => $r['draft_body']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gemini draft failed']);
    }
}

function skip_followup($pdo)
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid id']); return;
    }
    $stmt = $pdo->prepare("UPDATE outreach_followups SET status = 'skipped', halt_reason = 'manual' WHERE id = ? AND status IN ('drafted','approved','scheduled')");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Row already sent or halted']); return;
    }
    echo json_encode(['success' => true]);
}

function halt_followup_sequence($pdo)
{
    $leadId = (int) ($_POST['lead_id'] ?? 0);
    if ($leadId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid lead_id']); return;
    }
    $count = halt_followups_for_lead($pdo, $leadId, 'manual');
    echo json_encode(['success' => true, 'halted_count' => $count]);
}

function bulk_approve_followups($pdo)
{
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    $ids = array_filter($ids, fn($i) => $i > 0);
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No ids']); return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE outreach_followups SET status = 'approved'
    WHERE status = 'drafted'
      AND draft_subject IS NOT NULL AND draft_subject <> ''
      AND draft_body IS NOT NULL AND draft_body <> ''
      AND id IN ($placeholders)");
    $stmt->execute(array_values($ids));
    echo json_encode(['success' => true, 'approved_count' => $stmt->rowCount()]);
}

function bulk_skip_followups($pdo)
{
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    $ids = array_filter($ids, fn($i) => $i > 0);
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No ids']); return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE outreach_followups SET status = 'skipped', halt_reason = 'manual' WHERE status IN ('drafted','approved','scheduled') AND id IN ($placeholders)");
    $stmt->execute(array_values($ids));
    echo json_encode(['success' => true, 'skipped_count' => $stmt->rowCount()]);
}

function bulk_halt_followups($pdo)
{
    $leadIds = array_map('intval', (array) ($_POST['lead_ids'] ?? []));
    $leadIds = array_filter($leadIds, fn($i) => $i > 0);
    if (empty($leadIds)) {
        echo json_encode(['success' => false, 'message' => 'No lead_ids']); return;
    }
    $total = 0;
    foreach ($leadIds as $lid) {
        $total += halt_followups_for_lead($pdo, $lid, 'manual');
    }
    echo json_encode(['success' => true, 'halted_count' => $total]);
}

function get_followups_for_lead($pdo)
{
    $leadId = (int) ($_GET['lead_id'] ?? 0);
    if ($leadId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid lead_id']); return;
    }
    $stmt = $pdo->prepare("SELECT f.* FROM outreach_followups f WHERE f.lead_id = ? ORDER BY f.touch_number ASC");
    $stmt->execute([$leadId]);
    echo json_encode(['success' => true, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ─── AI Company Size Classification ───

function classify_company_sizes($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $businesses = $data['businesses'] ?? [];

    if (empty($businesses)) {
        json_response(['success' => false, 'message' => 'No businesses to classify'], 400);
    }

    // Build a list of businesses with their details for AI classification
    $businessList = [];
    foreach ($businesses as $i => $biz) {
        $entry = ($i + 1) . '. ' . ($biz['business_name'] ?? 'Unknown');
        if (!empty($biz['category'])) $entry .= ' (Category: ' . $biz['category'] . ')';
        if (!empty($biz['address'])) $entry .= ' - ' . $biz['address'];
        if (!empty($biz['website'])) $entry .= ' [' . $biz['website'] . ']';
        $businessList[] = $entry;
    }

    $systemPrompt = "You classify businesses by company size. For each business in the list, determine if it is 'small', 'medium', or 'large' based on available information.

Guidelines:
- Small: Solo operators, freelancers, local mom-and-pop shops, single-location businesses with likely fewer than 20 employees. Most local service businesses (plumbers, landscapers, cleaners) are small.
- Medium: Businesses with multiple locations, established regional presence, or likely 20-200 employees. Regional chains, mid-size professional firms, established contractors with large teams.
- Large: Major corporations, national/international chains, franchises of well-known brands, businesses with likely 200+ employees.

When in doubt, lean toward 'small' for local businesses found via Google Places search.

Return ONLY a JSON array of size classifications in the same order as the input list.
Example: [\"small\", \"medium\", \"small\", \"large\"]";

    $userPrompt = "Classify these businesses by size:\n\n" . implode("\n", $businessList);

    $result = call_gemini($systemPrompt, $userPrompt);

    if (isset($result['error'])) {
        json_response(['success' => false, 'message' => $result['error']], 500);
    }

    $content = trim($result['content']);
    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);

    $sizes = json_decode($content, true);

    if (!is_array($sizes)) {
        json_response(['success' => false, 'message' => 'Failed to parse AI classification response'], 500);
    }

    // Validate and normalize sizes
    $validSizes = ['small', 'medium', 'large'];
    $normalized = [];
    foreach ($sizes as $size) {
        $s = strtolower(trim($size));
        $normalized[] = in_array($s, $validSizes) ? $s : 'small';
    }

    json_response(['success' => true, 'sizes' => $normalized]);
}

function save_followup_draft($pdo)
{
    $id = (int) ($_POST['id'] ?? 0);
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($id <= 0 || $subject === '' || $body === '') {
        echo json_encode(['success' => false, 'message' => 'Missing fields']); return;
    }
    if (strlen($subject) > 500) {
        echo json_encode(['success' => false, 'message' => 'Subject too long (max 500 chars)']); return;
    }
    if (strlen($body) > 10000) {
        echo json_encode(['success' => false, 'message' => 'Body too long (max 10000 chars)']); return;
    }
    $stmt = $pdo->prepare("UPDATE outreach_followups SET draft_subject = ?, draft_body = ? WHERE id = ? AND status = 'drafted'");
    $stmt->execute([$subject, $body, $id]);
    echo json_encode(['success' => true]);
}
