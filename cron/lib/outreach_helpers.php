<?php
/**
 * Shared helper functions for outreach automation.
 * Used by both the admin API (admin/outreach/api.php) and the cron pipeline.
 *
 * Contains: log_activity, send_outreach_lead, scrape_email_from_website,
 *           call_gemini, summarize_business, generate_draft_for_lead
 */

// Guard against double-inclusion
if (defined('OUTREACH_HELPERS_LOADED')) return;
define('OUTREACH_HELPERS_LOADED', true);

// ─── Category Pain Points ───

/**
 * Look up 2-3 hand-curated pain points for a lead's category, falling back
 * to a generic '_default' list if the category is not in the map.
 * The map is loaded once per process.
 */
function get_category_pain_points($category)
{
    static $map = null;
    if ($map === null) {
        $map = require __DIR__ . '/category_pain_points.php';
    }
    $key = strtolower(trim((string) $category));
    return $map[$key] ?? $map['_default'];
}

// ─── Activity Logging ───

function log_activity($pdo, $lead_id, $action_type, $details = null)
{
    $stmt = $pdo->prepare("INSERT INTO outreach_activity_log (lead_id, action_type, details) VALUES (?, ?, ?)");
    $stmt->execute([$lead_id, $action_type, $details]);
}

// ─── Who outreach must never reach ───

/**
 * Says why an address must not get outreach, or null when it may.
 *
 * Outreach is cold email to strangers. Someone who already pays, holds a licence, has an
 * account or signed up for the newsletter is not a stranger, and a cold pitch to them reads
 * as the site not knowing who its own customers are. Anyone who unsubscribed is refused too,
 * whatever they unsubscribed from.
 *
 * Checked when a lead is added and again when each email is sent, because a lead can become a
 * customer in between.
 */
function outreach_known_contact($pdo, $email): ?string
{
    $email = strtolower(trim((string) $email));
    if ($email === '') {
        return null;
    }

    $lists = [
        'unsubscribed'          => 'SELECT 1 FROM email_suppressions WHERE LOWER(email) = ? LIMIT 1',
        'paying subscriber'     => 'SELECT 1 FROM premium_subscriptions WHERE LOWER(email) = ? LIMIT 1',
        'licence holder'        => 'SELECT 1 FROM license_keys WHERE LOWER(email) = ? LIMIT 1',
        'account holder'        => 'SELECT 1 FROM community_users WHERE LOWER(email) = ? LIMIT 1',
        'newsletter subscriber' => 'SELECT 1 FROM marketing_subscribers WHERE LOWER(email) = ? LIMIT 1',
    ];
    foreach ($lists as $reason => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            return $reason;
        }
    }
    return null;
}

// ─── Send a Single Outreach Email ───

/**
 * Send an outreach email for a lead and update its DB status.
 * Returns true on success, false on failure.
 *
 * The optional &$reason out-param disambiguates non-success outcomes for
 * callers that need to distinguish a real send failure from a benign skip
 * (race-condition guard, suppression list, invalid email). Possible values:
 *   'sent':          email delivered, lead marked contacted
 *   'already_sent':  atomic claim lost (another process already sent it)
 *   'suppressed':    email is on the outreach suppression list
 *   'customer':      the address belongs to someone the site already knows
 *   'invalid_email': lead's email is missing or malformed
 *   'smtp_failed':   SMTP/transport failure (the only "real failure" reason)
 * Existing callers that ignore the param still get the correct bool result.
 */
function send_outreach_lead($pdo, $lead, &$reason = null)
{
    $id = $lead['id'];
    $email = $lead['email'];

    // Atomic claim BEFORE sending. Without this, two simultaneous callers
    // (admin "Send" tab + cron stepSendEmails, or two tabs) can both pass
    // their pre-fetch sent_at check and both invoke SMTP.
    //
    // This sets sent_at = NOW() up front so only one process wins. If the
    // SMTP send subsequently fails, we restore sent_at = NULL below so the
    // lead remains sendable. The remaining failure mode is a process crash
    // between this claim and the actual send. sent_at would stay set with
    // no email having gone out. That's preferable to duplicate sends to a
    // prospect, and is recoverable by manually clearing sent_at.
    $claimStmt = $pdo->prepare(
        "UPDATE outreach_leads SET sent_at = NOW() WHERE id = ? AND sent_at IS NULL"
    );
    $claimStmt->execute([$id]);
    if ($claimStmt->rowCount() === 0) {
        log_activity($pdo, $id, 'email_skipped_already_sent', 'Skipped send: lead already sent (race-condition guard)');
        $reason = 'already_sent';
        return false;
    }

    // Skip if this email is on the suppression list. Release the claim so the
    // lead's state isn't misleadingly "sent" when nothing actually went out.
    if (!empty($email)) {
        $suppStmt = $pdo->prepare("SELECT 1 FROM email_suppressions WHERE email = ? AND context = 'outreach' LIMIT 1");
        $suppStmt->execute([strtolower(trim($email))]);
        if ($suppStmt->fetchColumn()) {
            $pdo->prepare("UPDATE outreach_leads SET sent_at = NULL WHERE id = ?")->execute([$id]);
            log_activity($pdo, $id, 'email_skipped_suppressed', 'Skipped send: email is on outreach suppression list (' . $email . ')');
            $reason = 'suppressed';
            return false;
        }

        $known = outreach_known_contact($pdo, $email);
        if ($known !== null) {
            $pdo->prepare("UPDATE outreach_leads SET sent_at = NULL, status = 'disqualified', disqualified_reason = 'existing_contact' WHERE id = ?")->execute([$id]);
            log_activity($pdo, $id, 'email_skipped_customer', 'Skipped send: the address belongs to an existing contact (' . $known . ')');
            $reason = 'customer';
            return false;
        }
    }

    // Generate and persist an unsubscribe token if we don't have one yet
    $unsubscribeToken = $lead['unsubscribe_token'] ?? null;
    if (empty($unsubscribeToken)) {
        $unsubscribeToken = bin2hex(random_bytes(32));
        $tokStmt = $pdo->prepare("UPDATE outreach_leads SET unsubscribe_token = ? WHERE id = ?");
        $tokStmt->execute([$unsubscribeToken, $id]);
    }

    // Build the per-lead tracking URL so clicks can be attributed to the lead.
    $sourceCode = 'outreach-' . $id;
    $trackedUrl = 'https://argorobots.com/?source=' . $sourceCode;
    $unsubUrl = 'https://argorobots.com/unsubscribe?t=' . $unsubscribeToken;

    // Personal name, not the brand: this is 1:1 outreach and the body opens with
    // "I'm Evan, the developer of Argo Books". It also has to match the From name
    // used by send_followup_row(), since follow-ups thread as Re: replies into
    // this same conversation and a display name that changes mid-thread looks
    // broken to the recipient.
    $fromName = 'Evan';
    $preheader = null;
    $format = 'html';

    // Creator/affiliate outreach ships without an unsubscribe line by request,
    // so the defensive re-injection below is skipped for those leads. Every
    // other lead type still gets it if the draft body somehow lost the link.
    $isCreatorLead = strtolower((string) ($lead['source'] ?? '')) === 'creator_auto';

    if ($format === 'plain') {
        // Plain text: keep URLs bare so they remain clickable in plain-text
        // clients while still carrying the tracking source param. No HTML
        // escaping, no <a> wrapping.
        $body = (string) $lead['draft_body'];
        $body = preg_replace('#https?://argorobots\.com/?(?![\w?/])#', $trackedUrl, $body);
        $body = str_replace('{UNSUBSCRIBE_URL}', $unsubUrl, $body);
        if (!$isCreatorLead && strpos($body, 'unsubscribe?t=') === false) {
            $unsubLine = "\n\nNot interested? " . $unsubUrl . " and I'll stop emailing you.";
            $replaced = preg_replace('#(\nAll the best)#i', $unsubLine . "\n$1", $body, 1);
            if ($replaced !== null && strpos($replaced, 'unsubscribe?t=') !== false) {
                $body = $replaced;
            } else {
                $body .= $unsubLine;
            }
        }
        $finalBody = $body;
    } else {
        $anchorHtml = '<a href="' . htmlspecialchars($trackedUrl) . '" style="color:#3b82f6;text-decoration:underline">argorobots.com</a>';
        $escapedBody = htmlspecialchars($lead['draft_body']);
        $escapedBody = preg_replace('#https?://argorobots\.com/?(?![\w?/])#', $anchorHtml, $escapedBody);

        $unsubAnchor = '<a href="' . htmlspecialchars($unsubUrl) . '" style="color:#6b7280;text-decoration:underline">unsubscribe</a>';
        // Drafts since the unsubscribe-URL fix carry the real bare URL in
        // their body. Wrap any occurrences in the styled "unsubscribe" anchor.
        $escapedBody = preg_replace(
            '#https?://argorobots\.com/unsubscribe\?t=[a-f0-9]+#i',
            $unsubAnchor,
            $escapedBody
        );
        // Defensive: also handle the legacy {UNSUBSCRIBE_URL} placeholder for
        // any drafts created before the substitution moved to draft time.
        $escapedBody = str_replace('{UNSUBSCRIBE_URL}', $unsubAnchor, $escapedBody);

        if (!$isCreatorLead && strpos($escapedBody, 'unsubscribe?t=') === false) {
            $unsubLine = "\n\n<span style=\"color:#9ca3af;font-size:13px\">Not interested? " . $unsubAnchor . " and I'll stop emailing you.</span>";
            $replaced = preg_replace('#(\nAll the best)#i', $unsubLine . "\n$1", $escapedBody, 1);
            if ($replaced !== null && strpos($replaced, 'unsubscribe?t=') !== false) {
                $escapedBody = $replaced;
            } else {
                $escapedBody .= $unsubLine;
            }
        }

        $finalBody = '<p>' . nl2br($escapedBody) . '</p>';
    }

    $messageId = null;
    $result = send_styled_email(
        $email,
        $lead['draft_subject'],
        $finalBody,
        '',
        'contact@argorobots.com',
        $fromName,
        'contact@argorobots.com',
        [],
        $preheader,
        $format,
        $messageId
    );

    if ($result) {
        // sent_at was already set by the upfront claim. Update the rest of
        // the post-send fields here. The COALESCE on original_message_id and
        // next_followup_due_at means a re-send (manual resend or retry after
        // a transient failure) won't overwrite the original Message-ID or
        // push out the follow-up schedule.
        $stmt = $pdo->prepare("UPDATE outreach_leads SET
            status = CASE WHEN status NOT IN ('replied','interested','not_interested','onboarded','email_bounced') THEN 'contacted' ELSE status END,
            first_contact_date = COALESCE(first_contact_date, NOW()),
            last_contact_date = NOW(),
            original_message_id = COALESCE(original_message_id, ?),
            next_followup_due_at = COALESCE(next_followup_due_at, DATE_ADD(NOW(), INTERVAL 5 DAY))
            WHERE id = ?");
        $stmt->execute([$messageId, $id]);
        $reason = 'sent';
        return true;
    }

    // SMTP send failed: release the claim so retries / manual re-send work.
    $pdo->prepare("UPDATE outreach_leads SET sent_at = NULL WHERE id = ?")->execute([$id]);
    $reason = 'smtp_failed';
    return false;
}

/**
 * After a first-touch email is sent, create one outreach_followups row per
 * touch in the active sequence config. scheduled_for is computed cumulatively
 * from days_after_prev.
 *
 * Idempotent at the row level via UNIQUE KEY (lead_id, touch_number).
 * A re-call (e.g. after a manual resend) will silently no-op on already-
 * existing rows.
 *
 * Returns the number of rows actually inserted.
 */
function schedule_followups_for_lead($pdo, int $leadId): int
{
    // Read the active sequence config from outreach_pipeline_state. If unset
    // or empty, no follow-ups are scheduled (sequence disabled).
    $stmt = $pdo->prepare("SELECT state_value FROM outreach_pipeline_state WHERE state_key = 'followup_sequence_config'");
    $stmt->execute();
    $row = $stmt->fetch();
    $configJson = $row ? $row['state_value'] : null;
    if (!$configJson) return 0;

    $config = json_decode($configJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('schedule_followups_for_lead: followup_sequence_config contains invalid JSON (' . json_last_error_msg() . '), no follow-ups scheduled for lead #' . $leadId);
        return 0;
    }
    if (!is_array($config) || empty($config)) return 0;

    // The lead's first-touch sent_at: needed as the anchor for scheduled_for.
    $leadStmt = $pdo->prepare("SELECT sent_at FROM outreach_leads WHERE id = ?");
    $leadStmt->execute([$leadId]);
    $leadRow = $leadStmt->fetch();
    if (!$leadRow || !$leadRow['sent_at']) return 0;

    $anchor = strtotime($leadRow['sent_at']);
    if ($anchor === false) return 0;

    $cumulativeDays = 0;
    $inserted = 0;

    $insertStmt = $pdo->prepare(
        "INSERT IGNORE INTO outreach_followups
            (lead_id, touch_number, scheduled_for, status)
         VALUES (?, ?, ?, 'scheduled')"
    );

    foreach ($config as $entry) {
        if (!is_array($entry) || !isset($entry['touch'], $entry['days_after_prev'])) continue;
        $touchNumber = (int) $entry['touch'];
        $daysAfterPrev = max(1, (int) $entry['days_after_prev']);
        $cumulativeDays += $daysAfterPrev;
        $scheduledFor = date('Y-m-d H:i:s', $anchor + $cumulativeDays * 86400);

        $insertStmt->execute([$leadId, $touchNumber, $scheduledFor]);
        if ($insertStmt->rowCount() > 0) {
            $inserted++;
        }
    }

    return $inserted;
}

/**
 * Generates a follow-up draft for a single outreach_followups row using Gemini.
 *
 * Determines the per-touch intent by reading the default_intent for this
 * touch_number from followup_sequence_config.
 *
 * Prompts Gemini with the original first-touch subject + body for context so
 * the follow-up reads as a coherent continuation rather than a fresh pitch.
 *
 * On success: writes draft_subject/draft_body/drafted_at, flips status to
 * 'drafted', and returns true.
 * On failure: increments draft_attempts, may flip status to 'failed' if
 * attempts >= 3, and returns false. Does NOT throw.
 */
function draft_followup_via_gemini($pdo, array $followupRow): bool
{
    $followupId = (int) $followupRow['id'];
    $leadId = (int) $followupRow['lead_id'];
    $touchNumber = (int) $followupRow['touch_number'];

    // Fetch lead context (business name, summary, original first-touch subject/body, category)
    $leadStmt = $pdo->prepare("SELECT business_name, business_summary, category, city, draft_subject, draft_body FROM outreach_leads WHERE id = ?");
    $leadStmt->execute([$leadId]);
    $lead = $leadStmt->fetch();
    if (!$lead) {
        // Lead deleted: mark followup failed permanently
        $pdo->prepare("UPDATE outreach_followups SET status = 'failed', draft_attempts = draft_attempts + 1 WHERE id = ?")
            ->execute([$followupId]);
        return false;
    }

    // Determine intent: default_intent for this touch from the sequence config
    $intent = null;
    $cfgStmt = $pdo->prepare("SELECT state_value FROM outreach_pipeline_state WHERE state_key = 'followup_sequence_config'");
    $cfgStmt->execute();
    $cfgRow = $cfgStmt->fetch();
    if ($cfgRow) {
        $cfg = json_decode((string) $cfgRow['state_value'], true);
        if (is_array($cfg)) {
            foreach ($cfg as $entry) {
                if (is_array($entry) && isset($entry['touch'], $entry['default_intent']) && (int) $entry['touch'] === $touchNumber) {
                    $intent = (string) $entry['default_intent'];
                    break;
                }
            }
        }
    }
    if ($intent === null || trim($intent) === '') {
        $intent = 'gentle bump'; // last-resort fallback
    }

    // Total touches in the configured sequence (for "touch N of M" context to Gemini)
    $totalStmt = $pdo->prepare("SELECT state_value FROM outreach_pipeline_state WHERE state_key = 'followup_sequence_config'");
    $totalStmt->execute();
    $totalRow = $totalStmt->fetch();
    $totalTouches = 1;
    if ($totalRow) {
        $cfg = json_decode((string) $totalRow['state_value'], true);
        if (is_array($cfg)) {
            $totalTouches = count($cfg) + 1; // +1 because touch 1 isn't in the config
        }
    }

    $bizName = trim((string) ($lead['business_name'] ?? ''));
    $bizSummary = trim((string) ($lead['business_summary'] ?? ''));
    $category = trim((string) ($lead['category'] ?? ''));
    $origSubject = trim((string) ($lead['draft_subject'] ?? ''));
    $origBody = trim((string) ($lead['draft_body'] ?? ''));

    $prompt = "You are writing a follow-up email in an ongoing cold-outreach sequence.\n\n"
        . "Context:\n"
        . "- This is touch $touchNumber of " . ($totalTouches) . " in the sequence.\n"
        . "- The recipient has not replied to any prior touch.\n"
        . "- Recipient business: " . ($bizName !== '' ? $bizName : '(unknown)') . "\n"
        . ($category !== '' ? "- Category: $category\n" : '')
        . ($bizSummary !== '' ? "- About them: $bizSummary\n" : '')
        . "\n"
        . "Original first-touch subject: $origSubject\n"
        . "Original first-touch body:\n---\n$origBody\n---\n"
        . "\n"
        . "Intent for THIS follow-up: $intent\n"
        . "\n"
        . "Write a brief follow-up email (max ~120 words) that:\n"
        . "- Sounds like a continuation of the original, not a fresh pitch.\n"
        . "- Reflects the intent above (do not literally quote the intent text).\n"
        . "- Is from Evan at Argo Books (free accounting software for small Canadian businesses).\n"
        . "- Includes 'argorobots.com' once, bare URL (not formatted).\n"
        . "- Mentions the unsubscribe option once with the placeholder {UNSUBSCRIBE_URL} (no other text around the placeholder).\n"
        . "- Signs off 'All the best,\\nEvan\\nArgo Books'.\n"
        . "\n"
        . "Output as JSON exactly: {\"subject\": \"...\", \"body\": \"...\"}\n"
        . "The subject should NOT include 'Re:'; that prefix is added automatically.";

    // call_gemini($systemPrompt, $userPrompt) returns ['content' => '...'] on success
    // or ['error' => '...'] on failure.
    $geminiResult = call_gemini('', $prompt);
    $geminiText = (is_array($geminiResult) && isset($geminiResult['content'])) ? (string) $geminiResult['content'] : '';
    if ($geminiText === '') {
        $pdo->prepare("UPDATE outreach_followups SET draft_attempts = draft_attempts + 1, status = CASE WHEN draft_attempts + 1 >= 3 THEN 'failed' ELSE status END WHERE id = ?")
            ->execute([$followupId]);
        return false;
    }

    // Strip any markdown fences Gemini sometimes wraps JSON in
    $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($geminiText));
    $parsed = json_decode($cleaned, true);
    if (!is_array($parsed) || !isset($parsed['subject'], $parsed['body'])) {
        $pdo->prepare("UPDATE outreach_followups SET draft_attempts = draft_attempts + 1, status = CASE WHEN draft_attempts + 1 >= 3 THEN 'failed' ELSE status END WHERE id = ?")
            ->execute([$followupId]);
        return false;
    }

    $subject = trim((string) $parsed['subject']);
    $body = trim((string) $parsed['body']);

    // Always Re:-prefix the subject. Gemini was told not to include "Re:" itself
    // (see prompt), but be idempotent in case it did anyway.
    if (stripos($subject, 're:') !== 0) {
        $subject = 'Re: ' . $subject;
    }

    $pdo->prepare(
        "UPDATE outreach_followups
         SET draft_subject = ?, draft_body = ?, drafted_at = NOW(), status = 'drafted', draft_attempts = draft_attempts + 1
         WHERE id = ?"
    )->execute([$subject, $body, $followupId]);

    return true;
}

/**
 * Halt all pre-sent follow-ups for a single lead. Used by admin actions
 * (manual halt from the UI). Returns count of rows updated.
 */
function halt_followups_for_lead($pdo, int $leadId, string $haltReason = 'manual'): int
{
    $stmt = $pdo->prepare(
        "UPDATE outreach_followups
         SET status = 'halted', halt_reason = ?
         WHERE lead_id = ?
           AND status IN ('scheduled', 'drafted', 'approved')"
    );
    $stmt->execute([$haltReason, $leadId]);
    return $stmt->rowCount();
}

/**
 * Bulk halt: mark pre-sent follow-up rows as halted for any lead in a
 * stop-condition status, or whose email is in email_suppressions (context='outreach').
 *
 * Called from stepHaltFollowups on each cron tick. Idempotent: only touches
 * rows still in scheduled/drafted/approved state.
 *
 * Returns an associative array of halt_reason => count for logging.
 */
function halt_followups_bulk($pdo): array
{
    // Halt rows for leads in reply-equivalent stop statuses
    $repliedStmt = $pdo->prepare(
        "UPDATE outreach_followups f
         JOIN outreach_leads l ON l.id = f.lead_id
         SET f.status = 'halted', f.halt_reason = 'replied'
         WHERE f.status IN ('scheduled', 'drafted', 'approved')
           AND l.status IN ('replied', 'interested', 'not_interested', 'onboarded')"
    );
    $repliedStmt->execute();
    $repliedCount = $repliedStmt->rowCount();

    // Halt rows for leads with bounced/complained emails
    $bouncedStmt = $pdo->prepare(
        "UPDATE outreach_followups f
         JOIN outreach_leads l ON l.id = f.lead_id
         SET f.status = 'halted', f.halt_reason = 'bounced'
         WHERE f.status IN ('scheduled', 'drafted', 'approved')
           AND l.status = 'email_bounced'"
    );
    $bouncedStmt->execute();
    $bouncedCount = $bouncedStmt->rowCount();

    // Halt rows for leads whose email is in the outreach suppression list (unsubscribed)
    $suppStmt = $pdo->prepare(
        "UPDATE outreach_followups f
         JOIN outreach_leads l ON l.id = f.lead_id
         JOIN email_suppressions s ON LOWER(s.email) = LOWER(l.email) AND s.context = 'outreach'
         SET f.status = 'halted', f.halt_reason = 'unsubscribed'
         WHERE f.status IN ('scheduled', 'drafted', 'approved')"
    );
    $suppStmt->execute();
    $unsubCount = $suppStmt->rowCount();

    // Halt rows for leads who have since become a customer, a subscriber or an account holder.
    $customerCount = 0;
    $pending = $pdo->query(
        "SELECT DISTINCT l.id, l.email FROM outreach_followups f
           JOIN outreach_leads l ON l.id = f.lead_id
          WHERE f.status IN ('scheduled', 'drafted', 'approved') AND l.email IS NOT NULL AND l.email != ''"
    )->fetchAll();
    foreach ($pending as $lead) {
        if (outreach_known_contact($pdo, $lead['email']) !== null) {
            $customerCount += halt_followups_for_lead($pdo, (int) $lead['id'], 'customer');
        }
    }

    return [
        'replied' => $repliedCount,
        'bounced' => $bouncedCount,
        'unsubscribed' => $unsubCount,
        'customer' => $customerCount,
    ];
}

/**
 * Send a single approved follow-up row. Atomic-claims the row by flipping
 * status=approved → status=sent. Threading headers point at the PREVIOUS
 * touch's message_id (or the original first-touch's message_id if this is
 * touch 2) so the whole sequence stays in one inbox thread.
 *
 * Returns true on send success. On failure, releases the atomic claim
 * (status returns to 'approved') so the next cron tick retries.
 *
 * &$reason out-param: 'sent' | 'not_eligible' | 'invalid_email' | 'smtp_failed' | 'lead_missing'
 */
function send_followup_row($pdo, array $followupRow, ?string &$reason = null): bool
{
    $followupId = (int) $followupRow['id'];
    $leadId = (int) $followupRow['lead_id'];
    $touchNumber = (int) $followupRow['touch_number'];

    // Fetch lead for email + unsubscribe token + first-touch context
    $leadStmt = $pdo->prepare("SELECT email, business_name, unsubscribe_token, original_message_id FROM outreach_leads WHERE id = ?");
    $leadStmt->execute([$leadId]);
    $lead = $leadStmt->fetch();
    if (!$lead) {
        $reason = 'lead_missing';
        return false;
    }

    $email = trim((string) $lead['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pdo->prepare("UPDATE outreach_followups SET status = 'failed', halt_reason = 'invalid_email' WHERE id = ?")
            ->execute([$followupId]);
        $reason = 'invalid_email';
        return false;
    }

    // Atomic claim: only one process can flip approved → sent
    $claim = $pdo->prepare(
        "UPDATE outreach_followups
         SET status = 'sent', sent_at = NOW()
         WHERE id = ? AND status = 'approved'"
    );
    $claim->execute([$followupId]);
    if ($claim->rowCount() === 0) {
        $reason = 'not_eligible';
        return false; // race-lost, already sent, or halted between fetch and claim
    }

    // Ensure unsubscribe token exists
    $unsubToken = $lead['unsubscribe_token'] ?? null;
    if (empty($unsubToken)) {
        $unsubToken = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE outreach_leads SET unsubscribe_token = ? WHERE id = ?")
            ->execute([$unsubToken, $leadId]);
    }
    $unsubUrl = 'https://argorobots.com/unsubscribe?t=' . $unsubToken;

    // Threading: previous touch's message_id, or first-touch's if this is touch 2
    $prevMessageId = null;
    if ($touchNumber > 2) {
        $prevStmt = $pdo->prepare("SELECT message_id FROM outreach_followups WHERE lead_id = ? AND touch_number = ? AND status = 'sent'");
        $prevStmt->execute([$leadId, $touchNumber - 1]);
        $prevRow = $prevStmt->fetch();
        if ($prevRow && !empty($prevRow['message_id'])) {
            $prevMessageId = (string) $prevRow['message_id'];
        }
    }
    if ($prevMessageId === null) {
        // Fall back to first-touch's message_id
        $prevMessageId = trim((string) ($lead['original_message_id'] ?? ''));
        if ($prevMessageId === '') $prevMessageId = null;
    }
    $threadingHeaders = [];
    if ($prevMessageId !== null) {
        $threadingHeaders['In-Reply-To'] = $prevMessageId;
        $threadingHeaders['References']  = $prevMessageId;
    }

    // Build tracked URL + render body HTML (same pattern as send_outreach_lead)
    $trackedUrl = 'https://argorobots.com/?source=outreach-' . $leadId . '-fu' . $touchNumber;
    $anchorHtml = '<a href="' . htmlspecialchars($trackedUrl) . '" style="color:#3b82f6;text-decoration:underline">argorobots.com</a>';
    $unsubAnchor = '<a href="' . htmlspecialchars($unsubUrl) . '" style="color:#6b7280;text-decoration:underline">unsubscribe</a>';

    $rawBody = (string) ($followupRow['draft_body'] ?? '');
    $rawBody = str_replace('{UNSUBSCRIBE_URL}', $unsubUrl, $rawBody);

    $escaped = htmlspecialchars($rawBody);
    $escaped = preg_replace('#https?://argorobots\.com/?(?![\w?/])#', $anchorHtml, $escaped);
    $escaped = preg_replace('#https?://argorobots\.com/unsubscribe\?t=[a-f0-9]+#i', $unsubAnchor, $escaped);

    if (strpos($escaped, 'unsubscribe?t=') === false) {
        $unsubLine = "\n\n<span style=\"color:#9ca3af;font-size:13px\">Not interested? " . $unsubAnchor . " and I'll stop emailing you.</span>";
        $escaped .= $unsubLine;
    }

    $finalBody = '<p>' . nl2br($escaped) . '</p>';

    $messageId = null;
    $result = send_styled_email(
        $email,
        (string) ($followupRow['draft_subject'] ?? 'Following up'),
        $finalBody,
        '',
        'contact@argorobots.com',
        'Evan',
        'contact@argorobots.com',
        $threadingHeaders,
        'Quick bump on my last note',
        'html',
        $messageId
    );

    if ($result) {
        // Record the message_id so the NEXT touch can thread off it
        $pdo->prepare("UPDATE outreach_followups SET message_id = ? WHERE id = ?")
            ->execute([$messageId, $followupId]);

        // Update lead-level activity timestamp + counters for backward-compat
        $pdo->prepare(
            "UPDATE outreach_leads
             SET last_contact_date = NOW(),
                 last_followup_at = NOW(),
                 followup_count = followup_count + 1
             WHERE id = ?"
        )->execute([$leadId]);

        log_activity($pdo, $leadId, 'followup_sent', "Follow-up #$touchNumber delivered");
        $reason = 'sent';
        return true;
    }

    // SMTP failed: release the claim
    $pdo->prepare("UPDATE outreach_followups SET status = 'approved', sent_at = NULL WHERE id = ?")
        ->execute([$followupId]);
    $reason = 'smtp_failed';
    return false;
}

// ─── Newness / business-age detection ───

/**
 * Scan website HTML for an explicit business-founding year. Looks for
 * copyright, "since YYYY", "established YYYY", "founded YYYY", and
 * "YYYY-present" patterns and returns the OLDEST plausible founding year
 * (1900..current year) found, or null if no signal is detected.
 *
 * Shared by the Shopify evaluator (business_too_old reject) and the Google
 * Places newness gate. An established business that just refreshed its site
 * still advertises its real age, which is exactly the "20-year-old shop that
 * already has accounting software" we want to skip.
 */
function detect_storefront_founded_year(string $html): ?int
{
    // Decode entities so "&copy; 2006" footers match the © pattern below.
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $currentYear = (int) date('Y');
    $oldestYear = null;

    $patterns = [
        '/©.{0,80}?(\d{4})/s',                                    // © 1995, © Acme Co 1995-2026
        '/\bcopyright\b.{0,80}?(\d{4})/is',                       // copyright Acme Co 1995
        '/\bsince\s+(\d{4})\b/i',                                 // since 1995
        '/\b(?:est(?:ablished)?\.?)\s+(?:in\s+)?(\d{4})\b/i',     // est. 1995, established in 1995
        '/\bfounded\s+(?:in\s+)?(\d{4})\b/i',                     // founded 1995, founded in 1995
        '/\b(\d{4})\s*[-–—]\s*(?:present|today|now)\b/i',         // 1995-present
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $yearStr) {
                $year = (int) $yearStr;
                if ($year >= 1900 && $year <= $currentYear) {
                    if ($oldestYear === null || $year < $oldestYear) {
                        $oldestYear = $year;
                    }
                }
            }
        }
    }

    return $oldestYear;
}

/**
 * Detect "this is an established business" signals from website HTML so the
 * newness gate can skip operators that have clearly been around for years
 * (and almost certainly already run accounting software). Pure / network-free.
 *
 * Returns:
 *   [
 *     'founded_year'     => int|null,   // oldest founding year detected
 *     'years_experience' => int|null,   // largest "N years experience/in business" claim
 *     'phrase'           => string|null // short snippet of the matched claim (for the log/reason)
 *   ]
 *
 * Examples it catches: "serving the community since 1998", "© 2003",
 * "20+ years experience", "over 25 years in business", "30 years of experience".
 * Sparse / new / "now open" / "launching" sites match nothing and return all
 * nulls, so they pass through.
 */
function detect_established_signals(string $html): array
{
    $foundedYear = detect_storefront_founded_year($html);

    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $yearsExperience = null;
    $phrase = null;

    // "20+ years", "over 25 years", "more than 30 years", "25 years of experience",
    // "20 years in business". Require the word "year(s)" plus an experience/business
    // qualifier nearby so we don't match unrelated "N years" copy.
    $expPatterns = [
        '/\b(?:over|more than|nearly|almost)?\s*(\d{1,2})\s*\+?\s*years?\s+(?:of\s+)?(?:experience|expertise|in\s+business|serving|in\s+the\s+industry|of\s+service)\b/i',
        '/\b(?:experience|expertise|in\s+business|serving|in\s+the\s+industry)\s+(?:for\s+)?(?:over|more than)?\s*(\d{1,2})\s*\+?\s*years?\b/i',
    ];
    foreach ($expPatterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $n = (int) $m[1];
                if ($n >= 1 && $n <= 99 && ($yearsExperience === null || $n > $yearsExperience)) {
                    $yearsExperience = $n;
                    $phrase = trim(preg_replace('/\s+/', ' ', $m[0]));
                    if (strlen($phrase) > 120) $phrase = substr($phrase, 0, 120);
                }
            }
        }
    }

    return [
        'founded_year'     => $foundedYear,
        'years_experience' => $yearsExperience,
        'phrase'           => $phrase,
    ];
}

/**
 * Fetch a website and return both the raw HTML and a cleaned, capped text
 * version. Extracted from summarize_business() so a single fetch can feed
 * both the newness check and the AI summary. Returns null on fetch failure
 * or when the page has too little readable text to be useful.
 *
 * @return array{html:string, text:string}|null
 */
function fetch_website_text($website): ?array
{
    if (empty($website)) return null;

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0',
            'follow_location' => true,
            'max_redirects' => 3,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $html = @file_get_contents($website, false, $context);
    if (!$html) return null;

    // Strip scripts, styles, and tags to get readable text
    $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
    $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim(mb_substr($text, 0, 3000)); // Cap at 3000 chars

    if (strlen($text) < 50) return null;

    return ['html' => $html, 'text' => $text];
}

// ─── Layer 3: AI Size Gate ───

/**
 * Ask Gemini to classify whether a lead is a small independent business
 * (good fit for Argo Books outreach) vs. a chain location / corporate
 * office / government / large institution (bad fit, skip).
 *
 * Returns one of:
 *   ['classification' => 'small_independent', 'reason' => '...']
 *   ['classification' => 'chain_or_corp',     'reason' => '...']
 *   ['classification' => 'uncertain',          'reason' => '...']
 *   ['error' => 'message']                                              (API or parse failure)
 *
 * On 'error', callers should pass-through (do NOT reject) so a Gemini
 * outage can't block legitimate outreach. Layers 1+2 already catch the
 * obvious cases; Layer 3 is opportunistic insurance.
 */
function classify_lead_size_with_ai($lead)
{
    $name = trim((string) ($lead['business_name'] ?? ''));
    if ($name === '') {
        return ['error' => 'missing business_name'];
    }

    $website = trim((string) ($lead['website'] ?? ''));
    $city = trim((string) ($lead['city'] ?? ''));
    $address = trim((string) ($lead['address'] ?? ''));
    $category = trim((string) ($lead['category'] ?? ''));

    $details = "Business name: " . $name;
    if ($city !== '')     $details .= "\nCity: " . $city;
    if ($address !== '')  $details .= "\nAddress: " . $address;
    if ($website !== '')  $details .= "\nWebsite: " . $website;
    if ($category !== '') $details .= "\nSearch category: " . $category;

    $systemPrompt = <<<PROMPT
You are classifying outreach leads for Argo Books, a simple bookkeeping and
invoicing app for small independent businesses (solo founders, family-run
shops, small partnerships).

Decide whether this business is a good outreach fit. Classify as:

  "small_independent" — owner-operator, family-run, or small partnership.
       The kind of business where one person makes the buying decision and
       might personally try a new bookkeeping app.

  "chain_or_corp" — any of:
     * a location of a national or international chain or franchise
       (e.g. Tim Hortons, McDonald's, Subway, Walmart, Starbucks)
     * a subsidiary, branch, or office of a publicly-traded company
     * an airline lounge, bank branch, telco store, hotel of a major brand
     * a government office, embassy, ministry, crown corporation
     * a hospital, university, large school, public library
     * a large religious or charitable institution

  "uncertain" — name and details are genuinely ambiguous and you cannot tell.

Heuristics:
  - Recognisable brand name in the business name -> almost always chain_or_corp
  - "[Brand] [City] location" or "[Brand] Airport" -> chain_or_corp
  - A unique name like "Joe's Plumbing" or "Le Macaron Bakery" -> small_independent
  - Government domain (.gc.ca, .gov, provincial .gov.ca subdomains) -> chain_or_corp
  - When genuinely torn, prefer "uncertain" over guessing.

Respond with ONLY a JSON object, no preamble:
{"classification": "small_independent" | "chain_or_corp" | "uncertain", "reason": "one short sentence"}
PROMPT;

    $result = call_gemini($systemPrompt, $details);
    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }

    $content = trim((string) ($result['content'] ?? ''));
    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);

    $parsed = json_decode($content, true);
    if (!is_array($parsed) || !isset($parsed['classification'])) {
        return ['error' => 'AI returned non-JSON or missing classification field'];
    }

    $cls = strtolower(trim((string) $parsed['classification']));
    if (!in_array($cls, ['small_independent', 'chain_or_corp', 'uncertain'], true)) {
        return ['error' => 'AI returned unknown classification: ' . $cls];
    }

    $reason = trim((string) ($parsed['reason'] ?? ''));
    if ($reason === '') $reason = '(no reason given)';
    if (strlen($reason) > 240) $reason = substr($reason, 0, 240);

    return ['classification' => $cls, 'reason' => $reason];
}

/**
 * Ask Gemini whether a business looks NEW/early vs ESTABLISHED, based on its
 * website text. This is the AI half of the newness gate: the cheap regex in
 * detect_established_signals() catches explicit "since 1998 / 20+ years" copy;
 * this catches softer signals (long client lists, decades of awards, "trusted
 * for generations", a mature product catalogue) that have no literal year.
 *
 * Returns one of:
 *   ['maturity' => 'new',         'reason' => '...']
 *   ['maturity' => 'established', 'reason' => '...']
 *   ['maturity' => 'unknown',     'reason' => '...']
 *   ['error' => 'message']                              (API or parse failure)
 *
 * On 'error' or 'unknown', callers should pass-through (do NOT reject): the
 * goal is to skew newer, not to block legitimate outreach on a Gemini hiccup.
 */
function classify_business_maturity_with_ai($businessName, $websiteText)
{
    $name = trim((string) $businessName);
    $text = trim((string) $websiteText);
    if ($text === '') {
        return ['error' => 'missing website text'];
    }
    if (strlen($text) > 3000) {
        $text = substr($text, 0, 3000);
    }

    $systemPrompt = <<<PROMPT
You are screening outreach leads for Argo Books, simple bookkeeping software
aimed at NEW and early-stage small businesses that likely haven't settled on
accounting software yet. We want to SKIP businesses that are clearly long
established (they almost always already run QuickBooks/Xero/Sage).

Read the website text and judge how mature the business is:

  "new"         — recently launched, "now open", "coming soon", sparse site,
                  one or two offerings, no history, no long client roster.
  "established" — clearly been operating for many years: talks about decades
                  of service, "trusted since", generations, long awards history,
                  hundreds of past projects/clients, a deep product catalogue.
  "unknown"     — genuinely can't tell from the text.

When torn between "new" and "unknown", prefer "unknown". Only answer
"established" when the text gives clear evidence of long operation.

Respond with ONLY a JSON object, no preamble:
{"maturity": "new" | "established" | "unknown", "reason": "one short sentence"}
PROMPT;

    $details = $name !== '' ? "Business name: {$name}\n\nWebsite text:\n{$text}" : "Website text:\n{$text}";

    $result = call_gemini($systemPrompt, $details);
    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }

    $content = trim((string) ($result['content'] ?? ''));
    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);

    $parsed = json_decode($content, true);
    if (!is_array($parsed) || !isset($parsed['maturity'])) {
        return ['error' => 'AI returned non-JSON or missing maturity field'];
    }

    $maturity = strtolower(trim((string) $parsed['maturity']));
    if (!in_array($maturity, ['new', 'established', 'unknown'], true)) {
        return ['error' => 'AI returned unknown maturity value: ' . $maturity];
    }

    $reason = trim((string) ($parsed['reason'] ?? ''));
    if ($reason === '') $reason = '(no reason given)';
    if (strlen($reason) > 240) $reason = substr($reason, 0, 240);

    return ['maturity' => $maturity, 'reason' => $reason];
}

/**
 * Mark a lead as disqualified by the auto-filter. Sets status='disqualified',
 * stores a short reason tag, appends a human-readable note, and writes an
 * activity log entry. Idempotent: re-marking the same lead is a no-op (no
 * extra notes appended) so the backfill script can safely re-run.
 */
function disqualify_lead($pdo, $leadId, $reasonTag, $detailMessage)
{
    $leadId = (int) $leadId;
    if ($leadId <= 0) return;

    $reasonTag = substr((string) $reasonTag, 0, 80);

    // Idempotency: if already disqualified with the same tag, do nothing.
    $check = $pdo->prepare("SELECT status, disqualified_reason FROM outreach_leads WHERE id = ?");
    $check->execute([$leadId]);
    $row = $check->fetch();
    if ($row && $row['status'] === 'disqualified' && $row['disqualified_reason'] === $reasonTag) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE outreach_leads
        SET status = 'disqualified',
            disqualified_reason = ?,
            approval_status = 'not_drafted',
            notes = CONCAT(COALESCE(notes, ''),
                           CASE WHEN COALESCE(notes,'') = '' THEN '' ELSE '\n' END,
                           '[Auto-filter ', ?, ' ', ?, '] ', ?)
        WHERE id = ?
    ");
    $stmt->execute([
        $reasonTag,
        $reasonTag,
        date('Y-m-d H:i'),
        $detailMessage,
        $leadId,
    ]);

    log_activity($pdo, $leadId, 'lead_disqualified', "{$reasonTag}: {$detailMessage}");
}

// ─── Email Scraping Helper ───

/**
 * Public entry point for scraping a contact email out of a website. Wraps the
 * actual scraper with a 30-day result cache (table: outreach_scrape_cache) so
 * the same URL doesn't get re-downloaded every cron run. Many businesses show
 * up under multiple search queries, and most sites have no scrape-able email
 * at all. A cached NULL is a valid result ("tried, found nothing") so we don't
 * re-attempt for 30 days; after that, the entry expires and we re-check (sites
 * sometimes add an email later).
 */
function scrape_email_from_website($url)
{
    if (empty($url)) return null;

    // Normalize once and use the same value for the cache key AND the live
    // scrape, otherwise leading/trailing whitespace produces avoidable scrape
    // failures and cache mismatches between what we look up and what we store.
    $url = rtrim(trim($url), '/');
    if ($url === '') return null;

    // URLs over 255 chars bypass the cache (column limit) but still get scraped.
    $cacheable = (strlen($url) <= 255);

    if ($cacheable) {
        global $pdo;
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT email FROM outreach_scrape_cache
                    WHERE url = ? AND last_attempted_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                    LIMIT 1");
                $stmt->execute([$url]);
                $row = $stmt->fetch();
                if ($row !== false) {
                    return $row['email']; // string OR null, both are valid cached outcomes
                }
            } catch (PDOException $e) {
                // Table missing or query failed: fall through to live scrape.
            }
        }
    }

    $email = _scrape_email_from_website_uncached($url);

    if ($cacheable) {
        global $pdo;
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO outreach_scrape_cache (url, email, last_attempted_at)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE email = VALUES(email), last_attempted_at = NOW()");
                $stmt->execute([$url, $email]);
            } catch (PDOException $e) {
                // Cache write failure shouldn't fail the scrape itself.
            }
        }
    }

    return $email;
}

/**
 * Live email scraper. Don't call directly: use scrape_email_from_website()
 * so results get cached.
 */
function _scrape_email_from_website_uncached($url)
{
    if (empty($url)) return null;

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0',
            'follow_location' => true,
            'max_redirects' => 3,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $falsePositives = ['example.com', 'sentry.io', 'wixpress.com', 'wordpress.org', 'w3.org', 'schema.org', 'googleapis.com', 'gravatar.com'];

    // Clean an extracted email: decode URL encoding, strip non-ASCII and whitespace
    $cleanEmail = function($email) {
        $email = urldecode($email);
        // Strip any non-ASCII characters (emojis, special chars, zero-width spaces, etc.)
        $email = preg_replace('/[^\x20-\x7E]/', '', $email);
        $email = trim($email);
        // Validate it still looks like an email after cleaning
        if (preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
            return $email;
        }
        return null;
    };

    // Helper to extract email from HTML
    $extractEmail = function($html) use ($falsePositives, $cleanEmail) {
        // URL-decode the HTML so mailto:%20info@... becomes mailto: info@...
        $decodedHtml = urldecode($html);

        // Look for mailto: links first (most reliable)
        if (preg_match_all('/mailto:\s*([^\s"\'<>]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', $decodedHtml, $matches)) {
            foreach ($matches[1] as $raw) {
                $email = $cleanEmail($raw);
                if (!$email) continue;
                $dominated = false;
                foreach ($falsePositives as $fp) { if (str_contains(strtolower($email), $fp)) { $dominated = true; break; } }
                if (!$dominated) return $email;
            }
        }
        // Fallback: email patterns in text (strip HTML tags first to avoid matching attributes)
        $text = strip_tags($decodedHtml);
        // Remove common non-ASCII clutter (emojis, zero-width chars) before matching
        $text = preg_replace('/[^\x20-\x7E\n\r\t]/', ' ', $text);
        if (preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            foreach ($matches[0] as $raw) {
                $email = $cleanEmail($raw);
                if (!$email) continue;
                $dominated = false;
                foreach ($falsePositives as $fp) { if (str_contains(strtolower($email), $fp)) { $dominated = true; break; } }
                if (!$dominated) return $email;
            }
        }
        return null;
    };

    // Try homepage first
    $html = @file_get_contents($url, false, $context);
    if ($html) {
        $email = $extractEmail($html);
        if ($email) return $email;

        // Find contact page links in the HTML
        // Parse base URL properly for resolving relative links
        $parsed = parse_url($url);
        $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        $basePath = rtrim($url, '/');
        $contactPaths = [];

        // Match all <a> tags - check both href path AND link text for contact-related keywords
        $contactKeywords = 'contact|about|about-us|contact-us|connect|get-in-touch|reach-us|reach out';
        if (preg_match_all('/<a\s[^>]*href=["\']([^"\'#][^"\']*)["\'][^>]*>(.*?)<\/a>/is', $html, $linkMatches, PREG_SET_ORDER)) {
            foreach ($linkMatches as $m) {
                $href = $m[1];
                $text = strip_tags($m[2]);
                // Match if href OR link text contains contact keywords
                if (!preg_match('/' . $contactKeywords . '/i', $href) && !preg_match('/' . $contactKeywords . '/i', $text)) continue;
                // Skip mailto/tel/javascript
                if (preg_match('/^(mailto:|tel:|javascript:)/i', $href)) continue;

                // Resolve relative URLs
                if (str_starts_with($href, 'http')) {
                    $contactPaths[] = $href;
                } elseif (str_starts_with($href, '/')) {
                    $contactPaths[] = $origin . $href;
                } else {
                    $contactPaths[] = $basePath . '/' . $href;
                }
            }
        }

        // Fallback: try common paths if none found in links
        if (empty($contactPaths)) {
            $contactPaths = [
                $basePath . '/contact',
                $basePath . '/contact-us',
                $basePath . '/about',
            ];
        }

        // Try each contact page
        foreach (array_unique(array_slice($contactPaths, 0, 3)) as $contactUrl) {
            $contactHtml = @file_get_contents($contactUrl, false, $context);
            if ($contactHtml) {
                $email = $extractEmail($contactHtml);
                if ($email) return $email;
            }
        }
    }

    return null;
}

// ─── Gemini Call ───

function call_gemini($systemPrompt, $userPrompt)
{
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    if (empty($apiKey)) {
        return ['error' => 'Gemini API key not configured'];
    }

    $model = $_ENV['GEMINI_MODEL'] ?? 'gemini-3.1-flash-lite';

    $payload = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 4000,
        ],
    ];
    if (!empty($systemPrompt)) {
        $payload['system_instruction'] = ['parts' => [['text' => $systemPrompt]]];
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'Gemini request failed';
        return ['error' => $errorMsg];
    }

    $result = json_decode($response, true);

    // Treat MAX_TOKENS as an error so callers don't accept truncated output.
    // Gemini 2.5 Flash has thinking enabled by default and thinking tokens
    // count against maxOutputTokens, so a partial response is a real risk.
    $finishReason = $result['candidates'][0]['finishReason'] ?? '';
    if ($finishReason === 'MAX_TOKENS') {
        return ['error' => 'Gemini output truncated (MAX_TOKENS): increase maxOutputTokens or shorten the prompt'];
    }

    return ['content' => $result['candidates'][0]['content']['parts'][0]['text'] ?? ''];
}

// ─── Business Summarization ───

function summarize_business($website, $prefetchedText = null)
{
    // Reuse already-fetched page text when the caller has it (the draft flow
    // fetches once for the newness check), otherwise fetch now.
    if ($prefetchedText !== null && trim((string) $prefetchedText) !== '') {
        $text = trim((string) $prefetchedText);
    } else {
        if (empty($website)) return null;
        $fetched = fetch_website_text($website);
        if ($fetched === null) return null;
        $text = $fetched['text'];
    }

    if (strlen($text) < 50) return null;

    $result = call_gemini(
        "You summarize businesses based on their website content. Respond with ONLY a concise summary (3-5 sentences) covering:
1. What specific services or products they offer
2. Who their typical customers are
3. How they likely handle billing (e.g. do they invoice clients, do project quotes, charge hourly, sell products, etc.)
4. Any pain points a simple bookkeeping/invoicing tool could solve for them (e.g. tracking job expenses, sending invoices, managing payments)
Be specific and factual based on the website content. Do not include any other text or preamble.",
        "Website content from $website:\n\n$text"
    );

    return $result['content'] ?? null;
}

// ─── Lead enrichment from a website ───

/**
 * Given just a website URL, best-effort auto-fill the fields needed to create
 * an outreach lead: business name, email, phone, category, city, and a short
 * summary. Used by the admin "Add Lead" flow (paste a URL, we fill the rest).
 *
 * Combines the cheap signals (contact-page email scrape, <title> tag) with a
 * single Gemini extraction call over the page text. Everything is best-effort:
 * any field we can't determine comes back null, and the caller still creates
 * the lead (a name falls back to the domain) so the admin can edit afterward.
 *
 * Returns an associative array; never throws.
 */
function enrich_lead_from_website($url): array
{
    $url = trim((string) $url);
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $out = [
        'website'          => $url,
        'business_name'    => null,
        'email'            => null,
        'phone'            => null,
        'category'         => null,
        'city'             => null,
        'address'          => null,
        'business_summary' => null,
    ];
    if ($url === '') return $out;

    // Email from the contact-page scraper (cached).
    $email = scrape_email_from_website($url);
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $out['email'] = $email;
    }

    // Page text + raw HTML (single fetch, shared below).
    $fetched = fetch_website_text($url);
    $host = parse_url($url, PHP_URL_HOST) ?: $url;
    $domainName = ucwords(str_replace(['-', '_'], ' ', preg_replace('/\.[a-z.]+$/i', '', preg_replace('/^www\./', '', $host))));

    if ($fetched === null) {
        // Couldn't fetch the page: still give the caller a usable name.
        $out['business_name'] = $domainName !== '' ? $domainName : $host;
        return $out;
    }

    // <title> as a quick business-name candidate.
    if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $fetched['html'], $tm)) {
        $title = html_entity_decode(trim($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($title !== '') $out['business_name'] = mb_substr($title, 0, 200);
    }

    // One Gemini call to pull structured fields out of the page text.
    $systemPrompt = <<<'PROMPT'
You extract structured business details from website text. Return ONLY a JSON
object with exactly these keys, using null (not a guess) when the text doesn't
say:
{"business_name": string|null, "category": string|null, "city": string|null, "address": string|null, "phone": string|null, "summary": string|null}

- business_name: the business's real name, cleaned of taglines/slogans.
- category: a short industry label (e.g. "plumber", "bakery", "law firm").
- city: the city they operate in, if stated.
- address: a full street address, if stated.
- phone: a single primary phone number, if present.
- summary: 2-3 sentences on what they do and how they likely bill clients.
No preamble, JSON only.
PROMPT;

    $res = call_gemini($systemPrompt, "Website: {$url}\n\n" . $fetched['text']);
    if (empty($res['error']) && !empty($res['content'])) {
        $content = preg_replace('/^```json\s*/i', '', trim((string) $res['content']));
        $content = preg_replace('/\s*```$/', '', $content);
        $parsed = json_decode($content, true);
        if (is_array($parsed)) {
            if (!empty($parsed['business_name'])) $out['business_name']    = mb_substr(trim((string) $parsed['business_name']), 0, 200);
            if (!empty($parsed['category']))      $out['category']         = mb_substr(trim((string) $parsed['category']), 0, 100);
            if (!empty($parsed['city']))          $out['city']             = mb_substr(trim((string) $parsed['city']), 0, 100);
            if (!empty($parsed['address']))       $out['address']          = mb_substr(trim((string) $parsed['address']), 0, 255);
            if (!empty($parsed['phone']))         $out['phone']            = mb_substr(trim((string) $parsed['phone']), 0, 40);
            if (!empty($parsed['summary']))       $out['business_summary'] = mb_substr(trim((string) $parsed['summary']), 0, 1000);
        }
    }

    if (empty($out['business_name'])) {
        $out['business_name'] = $domainName !== '' ? $domainName : $host;
    }

    return $out;
}

// ─── Draft Generation ───

/**
 * Generate an AI email draft for a lead. Saves draft to DB.
 * Returns ['success' => true, 'subject' => ..., 'body' => ...] or ['error' => ...].
 */
/**
 * Normalize a lead's name for use in an email greeting.
 *
 * Names arrive from CSV imports and website scrapes, so the casing is whatever
 * the source had ("monica razak", "JANE DOE"). A lowercase first name in the
 * greeting is the clearest mail-merge tell in the whole email, so re-case it.
 *
 * Only names that are entirely lower or entirely upper case get touched.
 * Anything already mixed is returned as-is, so deliberate casing survives
 * ("McDonald", "van der Berg", "DeAndre"). Hyphens and apostrophes are treated
 * as word separators, so "mary-jane o'brien" becomes "Mary-Jane O'Brien".
 * Names needing an internal capital ("mcdonald") come back as "Mcdonald",
 * which needs a name list to do properly and is still better than lowercase.
 */
function outreach_display_name($raw): string
{
    $name = trim((string) $raw);
    if ($name === '') {
        return '';
    }

    $letters = preg_replace('/[^\p{L}]/u', '', $name);
    if ($letters === '') {
        return $name;
    }

    $isUniformCase = mb_strtolower($letters, 'UTF-8') === $letters
        || mb_strtoupper($letters, 'UTF-8') === $letters;
    if (!$isUniformCase) {
        return $name;
    }

    return preg_replace_callback(
        '/\p{L}[\p{L}\p{M}]*/u',
        function ($m) {
            return mb_strtoupper(mb_substr($m[0], 0, 1, 'UTF-8'), 'UTF-8')
                . mb_strtolower(mb_substr($m[0], 1, null, 'UTF-8'), 'UTF-8');
        },
        $name
    );
}

function generate_draft_for_lead($pdo, $lead)
{
    $id = $lead['id'];

    // ─── Layer 3: AI Size Gate ───
    // Catch chains/corporations/institutions that slipped past Layer 1+2
    // before we spend Gemini tokens on summary + draft. Failures here
    // pass-through (do NOT block drafting) so a transient Gemini outage
    // can't stall the pipeline. Layers 1+2 are the strong guards.
    //
    // Scope: only auto-discovered leads (sources ending in '_auto' such as
    // 'google_places_auto' and 'shopify_auto'). Manually-added leads
    // (source='manual', 'csv_import', etc.) bypass the gate on the
    // assumption that the admin has already vetted them. The gate re-runs
    // on every draft regeneration for an auto lead: the Gemini Flash call
    // is cheap enough (~$0.0001/lead) that we don't bother caching the
    // verdict in a column.
    $sourceVal = strtolower((string) ($lead['source'] ?? ''));
    // Editorial (roundup) leads are publications, not small businesses, so the
    // AI size gate and the newness gate below don't apply. They also arrive with
    // their article context pre-summarized, so the SMB summary call is skipped.
    $isEditorial = ($sourceVal === 'editorial_auto');
    // Creator/affiliate-partner leads are content creators and publications, not
    // small businesses, so they skip the SMB size/newness gates just like
    // editorial. Their pitch is an affiliate-recruitment email, not feedback.
    $isCreator = ($sourceVal === 'creator_auto');
    $isAutoLead = str_ends_with($sourceVal, '_auto') && !$isEditorial && !$isCreator;
    if ($isAutoLead) {
        $gate = classify_lead_size_with_ai($lead);
        if (isset($gate['error'])) {
            log_activity($pdo, $id, 'size_gate_skipped', 'AI gate error, proceeding with draft: ' . $gate['error']);
        } elseif ($gate['classification'] === 'chain_or_corp' || $gate['classification'] === 'uncertain') {
            disqualify_lead(
                $pdo,
                $id,
                'ai_' . $gate['classification'],
                'AI size gate: ' . $gate['reason']
            );
            return [
                'success' => true,
                'disqualified' => true,
                'reason' => 'ai_' . $gate['classification'],
                'detail' => $gate['reason'],
            ];
        }
        // classification === 'small_independent' falls through to drafting.
    }

    // ─── Creator/affiliate outreach: fixed template, no AI ───
    // The AI drafts for creators kept inventing specifics about the channel
    // (fake claims about their content, audience, etc.). A plain template with
    // the creator's name inserted is honest and predictable, so creators skip
    // the whole Gemini path below.
    if ($isCreator) {
        $creatorName = outreach_display_name($lead['contact_name'] ?? '')
            ?: outreach_display_name($lead['business_name'] ?? '');
        $greeting = $creatorName !== '' ? "Hi {$creatorName}," : 'Hi there,';

        $subject = 'Partnership idea for your audience';
        $body =
            $greeting . "\n\n"
            . "I'm Evan, the developer of Argo Books, a free, simple bookkeeping and accounting app for small businesses. It runs on Windows, macOS, and Linux, and is easy to use.\n\n"
            . "I think your audience would be a good fit, so I'd like to invite you to join our affiliate program. You'd earn 50% recurring commission on the first 12 months of Premium for anyone who upgrades through your referral link. It's free to join, with a real-time dashboard and PayPal payouts: https://argorobots.com/affiliates\n\n"
            . "I can give you Premium access to Argo Books so you can try it yourself and see whether it's a fit for your audience.\n\n"
            . "If you're interested, just reply and I'll send over the details.\n\n"
            // No unsubscribe line here by request. The send-time fallback in
            // send_outreach_lead() is also skipped for creator leads, otherwise
            // it would re-append one. Note this is a deliberate departure from
            // CASL, which requires an unsubscribe mechanism in every commercial
            // electronic message including cold B2B outreach.
            . "Thanks,\nEvan\nArgo Books";

        // Same per-lead unsubscribe substitution the AI path uses further down.
        $unsubscribeToken = $lead['unsubscribe_token'] ?? null;
        if (empty($unsubscribeToken)) {
            $unsubscribeToken = bin2hex(random_bytes(32));
            $tokStmt = $pdo->prepare("UPDATE outreach_leads SET unsubscribe_token = ? WHERE id = ?");
            $tokStmt->execute([$unsubscribeToken, $id]);
        }
        $unsubUrl = 'https://argorobots.com/unsubscribe?t=' . $unsubscribeToken;
        $body = str_replace('{UNSUBSCRIBE_URL}', $unsubUrl, $body);

        $stmt = $pdo->prepare("UPDATE outreach_leads SET draft_subject = ?, draft_body = ?, drafted_at = NOW(), status = CASE WHEN status IN ('new','approved') THEN 'draft_generated' ELSE status END WHERE id = ?");
        $stmt->execute([$subject, $body, $id]);

        return ['success' => true, 'subject' => $subject, 'body' => $body];
    }

    // ─── Editorial (roundup) outreach: fixed template, no AI ───
    // Same reasoning as creators: these emails don't need to differ per
    // recipient, and the AI tended to invent article specifics (fake titles,
    // tools the list doesn't actually cover). A plain template with the author
    // name and their article URL inserted is honest, predictable, and free.
    if ($isEditorial) {
        $authorName = outreach_display_name($lead['contact_name'] ?? '');
        $greeting = $authorName !== '' ? "Hi {$authorName}," : 'Hi there,';
        $articleUrl = trim((string) ($lead['website'] ?? ''));
        $articleRef = $articleUrl !== ''
            ? "I came across your accounting software roundup ({$articleUrl})"
            : "I came across your accounting software roundup";

        $subject = 'A free option for your software roundup';
        $body =
            $greeting . "\n\n"
            . $articleRef . " and thought Argo Books might be worth adding.\n\n"
            . "I'm Evan, the developer. Argo Books is a free, simple bookkeeping and invoicing app for small businesses, no accounting knowledge needed. It is a desktop app for Windows, macOS, and Linux, so unlike the cloud tools that usually fill these lists, the data stays on the user's own computer. It has a genuinely free tier with no user cap.\n\n"
            . "Happy to send whatever would help, a short blurb in your format, screenshots, or a feature list, and I'd gladly add a link to your article from our site. If your roundup uses affiliate links, we also have an affiliate program, 50% recurring on a referral's first 12 months: https://argorobots.com/affiliates\n\n"
            . "You can see it here: https://argorobots.com/\n\n"
            . "If you think it's a fit, just reply and I'll send what you need.\n\n"
            . "Thanks,\nEvan\nArgo Books\n\n"
            . "Not the right contact, or not interested? {UNSUBSCRIBE_URL} and I won't follow up.";

        // Same per-lead unsubscribe substitution the AI path uses further down.
        $unsubscribeToken = $lead['unsubscribe_token'] ?? null;
        if (empty($unsubscribeToken)) {
            $unsubscribeToken = bin2hex(random_bytes(32));
            $tokStmt = $pdo->prepare("UPDATE outreach_leads SET unsubscribe_token = ? WHERE id = ?");
            $tokStmt->execute([$unsubscribeToken, $id]);
        }
        $unsubUrl = 'https://argorobots.com/unsubscribe?t=' . $unsubscribeToken;
        $body = str_replace('{UNSUBSCRIBE_URL}', $unsubUrl, $body);

        $stmt = $pdo->prepare("UPDATE outreach_leads SET draft_subject = ?, draft_body = ?, drafted_at = NOW(), status = CASE WHEN status IN ('new','approved') THEN 'draft_generated' ELSE status END WHERE id = ?");
        $stmt->execute([$subject, $body, $id]);

        return ['success' => true, 'subject' => $subject, 'body' => $body];
    }

    // ─── Newness gate ───
    // Argo Books targets NEW / early businesses that likely haven't settled on
    // accounting software yet. Skip auto-discovered leads whose own website
    // shows clear "established" signals (old founding year, "20+ years
    // experience", decades of service). Two layers: a free regex pass, then an
    // AI judgment for softer signals. Both pass-through on failure so a fetch
    // miss or Gemini hiccup never blocks legitimate outreach.
    //
    // We fetch the homepage once here and hand the text to summarize_business
    // below, so this adds at most one HTTP GET (and only at first draft, when
    // no business_summary is stored yet). On regenerate, the lead was already
    // vetted, so we skip the re-check.
    $prefetchedSiteText = null;
    if ($isAutoLead && empty($lead['business_summary']) && !empty($lead['website'])) {
        $fetched = fetch_website_text($lead['website']);
        if ($fetched !== null) {
            $prefetchedSiteText = $fetched['text'];

            $maxAgeYears = (int) ($_ENV['OUTREACH_ESTABLISHED_MAX_AGE_YEARS'] ?? 8);
            if ($maxAgeYears < 1) $maxAgeYears = 8;
            $currentYear = (int) date('Y');

            // Layer A: cheap regex on the page HTML.
            $signals = detect_established_signals($fetched['html']);
            $establishedDetail = null;
            if ($signals['founded_year'] !== null && $signals['founded_year'] <= ($currentYear - $maxAgeYears)) {
                $age = $currentYear - $signals['founded_year'];
                $establishedDetail = "Website shows founding year {$signals['founded_year']} (~{$age} years old)";
            } elseif ($signals['years_experience'] !== null && $signals['years_experience'] >= $maxAgeYears) {
                $establishedDetail = "Website advertises " . $signals['years_experience'] . "+ years"
                    . ($signals['phrase'] ? " (\"{$signals['phrase']}\")" : '');
            }
            if ($establishedDetail !== null) {
                disqualify_lead($pdo, $id, 'established_business', 'Newness gate: ' . $establishedDetail);
                return [
                    'success' => true,
                    'disqualified' => true,
                    'reason' => 'established_business',
                    'detail' => $establishedDetail,
                ];
            }

            // Layer B: AI maturity judgment for softer signals with no literal year.
            $maturity = classify_business_maturity_with_ai($lead['business_name'] ?? '', $prefetchedSiteText);
            if (isset($maturity['error'])) {
                log_activity($pdo, $id, 'newness_gate_skipped', 'AI maturity error, proceeding: ' . $maturity['error']);
            } elseif ($maturity['maturity'] === 'established') {
                disqualify_lead($pdo, $id, 'ai_established_business', 'AI newness gate: ' . $maturity['reason']);
                return [
                    'success' => true,
                    'disqualified' => true,
                    'reason' => 'ai_established_business',
                    'detail' => $maturity['reason'],
                ];
            }
            // 'new' / 'unknown' fall through to drafting.
        }
    }

    // Reserved override hooks, kept empty so the prompt template below
    // interpolates to nothing.
    $abSubjectOverride = '';
    $abBodyOverride = '';
    $abCtaOverride = '';

    // Generate a business summary if we don't have one yet.
    $summary = $lead['business_summary'] ?? null;
    if (empty($summary) && !empty($lead['website']) && !$isEditorial && !$isCreator) {
        // Reuse the page text fetched by the newness gate above (if any) so we
        // don't fetch the same homepage twice.
        $summary = summarize_business($lead['website'], $prefetchedSiteText);
        if ($summary) {
            $stmt = $pdo->prepare("UPDATE outreach_leads SET business_summary = ? WHERE id = ?");
            $stmt->execute([$summary, $id]);
        }
    }

    $country = strtoupper(trim($lead['country'] ?? 'CA'));
    $isCanadian = ($country === 'CA');
    $isLocal = false;
    $city = strtolower(trim($lead['city'] ?? ''));
    $province = strtolower(trim($lead['province'] ?? ''));
    if ($isCanadian && ($province === 'saskatchewan' || $province === 'sk' || in_array($city, ['saskatoon','regina','prince albert','moose jaw','swift current','yorkton','north battleford','estevan','weyburn','martensville','warman','humboldt','melfort','meadow lake','lloydminster']))) {
        $isLocal = true;
    }

    $isSaskatoon = $isLocal && in_array($city, ['saskatoon','martensville','warman']);

    if ($isLocal) {
        $localInstruction = "- The business is in Saskatchewan. Evan is a local Saskatchewan software developer based in Saskatoon. ALWAYS mention being local, e.g. \"I'm a local Saskatoon software developer\" or \"As a fellow Saskatchewan business\". This local connection is important, make it feel personal.";
    } elseif ($isCanadian) {
        $localInstruction = "- The business is outside Saskatchewan but in Canada. Evan is a Canadian software developer. Say \"Canadian software developer\" or \"fellow Canadian small business\", do NOT say \"local\" and do NOT mention Saskatoon or Saskatchewan.";
    } else {
        $localInstruction = "- The business is in the United States. Evan is an independent software developer (he is Canadian, but do NOT lead with that, since it adds nothing for a US recipient and can read as out-of-place). Say \"independent software developer\" or \"solo developer\". Do NOT say \"local\", do NOT mention Saskatoon or Saskatchewan, and do NOT say \"fellow Canadian\".";
    }

    $inPersonInstruction = $isSaskatoon
        ? "\n- IMPORTANT: Since this business is in the Saskatoon area, you MUST include an offer for an in-person visit to help them get set up. Work it in naturally, e.g. \"Since I'm right here in Saskatoon, I'd be happy to stop by and help you get set up in person\" or \"I could even swing by to walk you through it\". This is a key selling point for local businesses."
        : "\n- Do NOT mention any in-person visits or stopping by. The business is not in Saskatoon.";

    // Pull industry-level pain points for this category. These are typical
    // day-to-day headaches for the trade, NOT claims about this business.
    $painPoints = get_category_pain_points($lead['category'] ?? '');
    $painPointsList = '';
    foreach ($painPoints as $pp) {
        $painPointsList .= "  * " . $pp . "\n";
    }
    $categoryLabel = !empty($lead['category']) ? $lead['category'] : 'this industry';
    $painPointsInstruction = "\n- Small businesses in the '" . $categoryLabel . "' category commonly deal with things like:\n"
        . $painPointsList
        . "You MAY gently allude to ONE of these as something Argo Books can help with, phrased as a general industry pattern (e.g. \"businesses like yours often deal with X\"), NEVER as an assertion about this specific business. Pick at most one. If none fit naturally, skip them entirely.";

    $systemPrompt = "You are helping write a brief, personal outreach email from Evan, the developer behind Argo Books, to a small business. The goal is to get honest product feedback on Argo Books, a bookkeeping and invoicing app for small businesses.

About Argo Books:
- It is a simple bookkeeping and invoicing app designed so you do not need any accounting knowledge at all
- Built specifically for small businesses, not a bloated enterprise tool
- Features include invoicing, expense tracking, and simple bookkeeping
- Evan is " . ($isLocal ? "a local independent software developer based in Saskatoon" : ($isCanadian ? "a Canadian independent software developer" : "an independent software developer")) . " building this specifically for small businesses

Rules:
- Keep it very short (2-3 short paragraphs max, under 100 words ideally)
- Sound human, friendly, and genuine, not like marketing spam
$localInstruction
$inPersonInstruction
- Get to the point quickly in the first sentence - say why you are emailing. Do NOT open with generic filler like \"I hope this message finds you well\" or vague flattery like \"I admire your work\"
- Use the business name in the greeting (e.g. \"Hi LVM Landscaping\" or \"Hi [business name]\" if available)

PERSONALIZATION (this is critical):
- You may reference the business's industry/category to explain why Argo Books could be useful (e.g. \"running a landscaping business usually means a lot of invoicing\")
- NEVER claim to know specific details about how the business operates, what tools they use, what payment methods they accept, or how they handle their finances. You do NOT know these things. Do NOT say things like \"I know you handle donations\" or \"I see you use e-transfers\" or \"I noticed you do quotes\". This comes across as creepy and dishonest
- Instead of asserting facts about their business, use general industry knowledge. Say things like \"businesses like yours often deal with...\" or \"in the [industry] space, invoicing can be a hassle\" rather than \"I know you do X\"
- Only reference Argo Books features that are relevant to their general industry. Do not list every feature
- If a business summary is provided, use it ONLY to understand their industry and tailor which Argo features to mention. Do NOT parrot back details from the summary as if you personally know about their business
- EXCEPTION: if the summary lists a \"Recently added product\" name, you MAY reference it ONCE in a natural way (e.g. \"loved the [product name] on your store\"). This is public information from their store, not creepy. Keep it brief and only if it flows naturally; do not force it
- If no summary is available, keep it more general but still mention their industry/category if known
$painPointsInstruction

- Briefly describe Argo Books as a simple bookkeeping and invoicing app that requires no accounting knowledge. Do NOT just say \"check it out\" without explaining what it is
- Mention you are looking for honest feedback from small business owners
- Mention offering a free 1-year premium license in exchange for feedback$abCtaOverride
- Use a casual but professional tone
- NEVER use placeholders like [Your Name], [Your Title], [Your Company], etc.
- ALWAYS include the website link https://argorobots.com/ in the email body. This is required in every single email, no exceptions
- NEVER use em dashes in the email. Use commas, periods, or regular hyphens instead
- The subject line should sound like a short personal email one human would send to one specific business owner, NOT marketing copy. Keep it under 7 words, reference the recipient's business if natural, and avoid clickbait hooks like \"A surprising way to...\", \"The secret to...\", \"How [business] can...\", or anything that pattern-matches as a sales template. Lowercase is fine. Good examples: \"Quick question about [business name]\", \"Thought of you guys\", \"feedback on Argo Books?\", \"[business name]: bookkeeping question\". Bad examples: \"A surprising way to save time for [business]\", \"Unlock growth for [business]\", \"Transform your bookkeeping today\"$abSubjectOverride
- You MUST include the line \"You can check it out here: https://argorobots.com/\" (or similar natural phrasing with that exact URL) somewhere in the email body, ideally after mentioning what Argo Books is
- End the email body with a line like \"Feel free to reply to this email if you have any questions!\" or similar, before the sign-off
- After that line, add ONE short, respectful unsubscribe line on its own paragraph, such as: \"Not interested? {UNSUBSCRIBE_URL} and I'll stop emailing you.\" The literal token {UNSUBSCRIBE_URL} will be replaced with a tracked unsubscribe link before sending. Include it verbatim, and do NOT invent or replace the placeholder yourself. Keep the tone soft, brief, and non-pushy.
- Always sign off with three separate lines: \"All the best,\" then \"Evan\" then \"Argo Books\" (each on its own line, separated by \\n)$abBodyOverride

Return your response as JSON with two fields:
{\"subject\": \"the email subject line\", \"body\": \"the email body text (plain text, use \\n for line breaks)\"}

Return ONLY the JSON, no other text.";

    $details = "Business: {$lead['business_name']}";
    if ($lead['category']) $details .= "\nCategory/Industry: {$lead['category']}";
    if ($lead['city']) $details .= "\nCity: {$lead['city']}";
    if ($isLocal) $details .= "\nLocal: Yes, this business is in Saskatchewan (same province as Evan)";
    if ($lead['website']) $details .= "\nWebsite: {$lead['website']}";
    if ($lead['contact_name']) $details .= "\nContact person: {$lead['contact_name']}";
    if ($summary) $details .= "\nBusiness summary: $summary";

    $result = call_gemini($systemPrompt, $details);

    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }

    // Parse JSON response from AI
    $content = trim($result['content']);
    // Strip markdown code fences if present
    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);

    $parsed = json_decode($content, true);
    if (!$parsed || !isset($parsed['subject']) || !isset($parsed['body'])) {
        // AI returned invalid/truncated JSON. Save a clear placeholder body
        // (not the raw broken response) and mark needs_review so the admin
        // sees what happened and can regenerate.
        $fallbackSubject = "Quick question for {$lead['business_name']}";
        $placeholderBody = "[Draft generation failed: Gemini returned malformed or truncated output. Click Regenerate to try again.]";
        $stmt = $pdo->prepare("UPDATE outreach_leads SET draft_subject = ?, draft_body = ?, drafted_at = NOW(), approval_status = 'needs_review', status = CASE WHEN status IN ('new','approved') THEN 'draft_generated' ELSE status END WHERE id = ?");
        $stmt->execute([$fallbackSubject, $placeholderBody, $id]);
        log_activity($pdo, $id, 'draft_needs_review', 'Gemini returned malformed JSON (likely truncated).');

        return ['success' => true, 'needs_review' => true, 'subject' => $fallbackSubject, 'body' => $placeholderBody];
    }

    // Ensure the website URL is in the body. Inject before sign-off if AI omitted it
    if (stripos($parsed['body'], 'argorobots.com') === false) {
        $parsed['body'] = preg_replace(
            '/(Feel free to|Don\'t hesitate|Let me know|Reply to this)/i',
            "You can check it out here: https://argorobots.com/\n\n$1",
            $parsed['body'],
            1
        );
        // If regex didn't match, append before sign-off
        if (stripos($parsed['body'], 'argorobots.com') === false) {
            $parsed['body'] = preg_replace(
                '/(\nAll the best)/i',
                "\n\nYou can check it out here: https://argorobots.com/\n$1",
                $parsed['body'],
                1
            );
        }

        // Final fallback: if URL is still missing, append it at the end
        if (stripos($parsed['body'], 'argorobots.com') === false) {
            $parsed['body'] .= "\n\nYou can check it out here: https://argorobots.com/";
        }
    }

    // Substitute the {UNSUBSCRIBE_URL} placeholder with the real per-lead URL
    // now (was previously deferred to send time). Saving the real URL means
    // the admin sees the actual link when reviewing the draft instead of the
    // raw placeholder. The send-side still handles the placeholder defensively
    // for any old drafts that pre-date this change.
    $unsubscribeToken = $lead['unsubscribe_token'] ?? null;
    if (empty($unsubscribeToken)) {
        $unsubscribeToken = bin2hex(random_bytes(32));
        $tokStmt = $pdo->prepare("UPDATE outreach_leads SET unsubscribe_token = ? WHERE id = ?");
        $tokStmt->execute([$unsubscribeToken, $id]);
    }
    $unsubUrl = 'https://argorobots.com/unsubscribe?t=' . $unsubscribeToken;
    $parsed['body'] = str_replace('{UNSUBSCRIBE_URL}', $unsubUrl, $parsed['body']);

    // Save draft to lead.
    $stmt = $pdo->prepare("UPDATE outreach_leads SET draft_subject = ?, draft_body = ?, drafted_at = NOW(), status = CASE WHEN status IN ('new','approved') THEN 'draft_generated' ELSE status END WHERE id = ?");
    $stmt->execute([$parsed['subject'], $parsed['body'], $id]);

    return ['success' => true, 'subject' => $parsed['subject'], 'body' => $parsed['body']];
}
