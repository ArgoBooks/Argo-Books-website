<?php
/**
 * What the marketing agent may do to the outside world: make tracked links, research, and
 * propose posts and outreach emails.
 *
 * A post or an email is never sent straight from a request. It becomes a proposal. While
 * approval is on for its kind, the proposal waits on the admin page for the owner; with
 * approval off it is carried out at once. Either way it goes through the same code, so there
 * is one path to check and one record of what went out.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/platforms.php';

// ─── Tracked links ───

/**
 * Makes a referral link, so each thing the agent tries can be told apart in the numbers. Its
 * codes start with "ag-" to keep them apart from the ones made by hand. A link sends nothing
 * to anyone, so it needs no approval.
 *
 * @return array{source_code: string, url: string}
 */
function agent_create_link(PDO $pdo, ?int $runId, array $in): array
{
    $code = strtolower(trim((string) ($in['source_code'] ?? '')));
    $name = trim((string) ($in['name'] ?? ''));
    $target = trim((string) ($in['target_url'] ?? 'https://argorobots.com/'));

    if (!preg_match('/^ag-[a-z0-9][a-z0-9-]{2,60}$/', $code)) {
        throw new AgentRefused('BAD_INPUT', 'source_code starts with "ag-", then lowercase letters, digits and hyphens.');
    }
    if ($name === '') {
        throw new AgentRefused('BAD_INPUT', 'Give the link a name that says what it is for.');
    }
    if (!agent_is_site_url($target)) {
        throw new AgentRefused('BAD_INPUT', 'target_url must be a page on https://argorobots.com.');
    }
    agent_require_room($pdo, 'link_create', 'links_per_day', 'new links');

    try {
        $pdo->prepare('INSERT INTO referral_links (source_code, name, category, target_url) VALUES (?, ?, ?, ?)')
            ->execute([$code, mb_substr($name, 0, 255), 'agent', $target]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            throw new AgentRefused('DUPLICATE', 'That source_code is already in use.', 409);
        }
        throw $e;
    }

    $url = $target . (str_contains($target, '?') ? '&' : '?') . 'source=' . rawurlencode($code);
    agent_log($pdo, $runId, 'link_create', 'ok', ['source_code' => $code, 'name' => $name, 'url' => $url]);
    return ['source_code' => $code, 'url' => $url];
}

// ─── Research ───

/**
 * Asks Gemini a question and lets it answer from live Google results.
 *
 * This is one of two ways the agent can search; the other is its own web search. Which is
 * better is decided by the test described in read-me/Marketing-agent.md. Whatever comes back
 * is a lead to follow, not a fact: an email address found this way is still checked against
 * the page it is said to be on before anyone is written to.
 *
 * @return array{answer: string, sources: array<int, array{title: string, url: string}>}
 */
function agent_research(PDO $pdo, ?int $runId, string $question, ?callable $http = null): array
{
    $question = trim($question);
    if (mb_strlen($question) < 10 || mb_strlen($question) > 2000) {
        throw new AgentRefused('BAD_INPUT', 'A research question is between 10 and 2000 characters.');
    }
    $key = $_ENV['GEMINI_API_KEY'] ?? '';
    if ($key === '') {
        throw new AgentRefused('NOT_CONFIGURED', 'Research through Gemini is not set up. Use your own web search.', 503);
    }
    agent_require_room($pdo, 'research', 'research_per_day', 'research questions');

    $model = $_ENV['GEMINI_RESEARCH_MODEL'] ?? $_ENV['GEMINI_MODEL'] ?? 'gemini-3.1-flash-lite';
    [$status, , $body] = ($http ?? 'agent_http')('POST',
        "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . rawurlencode($key),
        ['Content-Type: application/json'],
        json_encode([
            'contents' => [['role' => 'user', 'parts' => [['text' => $question]]]],
            'tools' => [['google_search' => new stdClass()]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 4000],
        ]));

    $data = agent_json($body);
    $candidate = $data['candidates'][0] ?? null;
    if ($status !== 200 || $candidate === null) {
        agent_log($pdo, $runId, 'research', 'failed', ['question' => $question, 'http_status' => $status]);
        throw new AgentRefused('RESEARCH_FAILED', 'Gemini answered ' . $status . ': ' . ($data['error']['message'] ?? 'no answer') . '. Use your own web search.', 502);
    }

    $answer = '';
    foreach ($candidate['content']['parts'] ?? [] as $part) {
        $answer .= (string) ($part['text'] ?? '');
    }
    $sources = [];
    foreach ($candidate['groundingMetadata']['groundingChunks'] ?? [] as $chunk) {
        if (!empty($chunk['web']['uri'])) {
            $sources[] = ['title' => (string) ($chunk['web']['title'] ?? ''), 'url' => (string) $chunk['web']['uri']];
        }
    }

    agent_log($pdo, $runId, 'research', 'ok', ['question' => mb_substr($question, 0, 300), 'sources' => count($sources)]);
    return ['answer' => $answer, 'sources' => $sources];
}

// ─── Checking an address is really on the page ───

/**
 * Loads a public web page and returns its HTML, or null if it cannot be loaded.
 *
 * The address comes from the agent, which reads the open web and can be misled. So this will
 * only talk to public hosts: a page that names the server itself or a private address is
 * refused, at every redirect, and the connection is pinned to the address that was checked.
 */
function agent_fetch_page(string $url): ?string
{
    for ($hop = 0; $hop < 4; $hop++) {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        $public = array_values(array_filter($ips, fn ($ip) => filter_var(
            $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false));
        if (!$ips || count($public) !== count($ips)) {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ch = curl_init($url);
        $location = null;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ArgoBooksBot/1.0; +https://argorobots.com)',
            CURLOPT_RESOLVE => ["$host:$port:{$public[0]}"],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$location) {
                if (stripos($line, 'location:') === 0) {
                    $location = trim(substr($line, 9));
                }
                return strlen($line);
            },
            // A page is a few hundred kilobytes. Anything past 2 MB is not one worth reading.
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => fn ($ch, $total, $downloaded) => $downloaded > 2000000 ? 1 : 0,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 300 && $status < 400 && $location) {
            // A relative redirect stays on the same host.
            $url = preg_match('#^https?://#i', $location) ? $location
                : "$scheme://$host" . ($location[0] === '/' ? '' : '/') . $location;
            continue;
        }
        return ($status === 200 && is_string($body)) ? $body : null;
    }
    return null;
}

/**
 * Whether an email address is written on a page. Looks at the page as text, at mailto links,
 * and at Cloudflare's scrambled form, which is how many small sites hide addresses from
 * scrapers while still showing them to people.
 */
function agent_email_on_page(string $html, string $email): bool
{
    $email = strtolower(trim($email));
    $text = strtolower(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (str_contains($text, $email) || str_contains(rawurldecode($text), $email)) {
        return true;
    }

    preg_match_all('/(?:data-cfemail="|email-protection#)([0-9a-f]{6,})/', $text, $scrambled);
    foreach ($scrambled[1] as $hex) {
        $key = hexdec(substr($hex, 0, 2));
        $plain = '';
        for ($i = 2; $i + 1 < strlen($hex); $i += 2) {
            $plain .= chr(hexdec(substr($hex, $i, 2)) ^ $key);
        }
        if (strtolower($plain) === $email) {
            return true;
        }
    }
    return false;
}

// ─── Proposals ───

function agent_proposal(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM agent_proposals WHERE id = ? AND environment = ?');
    $stmt->execute([$id, current_environment()]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }
    $row['payload'] = json_decode((string) $row['payload'], true) ?: [];
    return $row;
}

/** Proposals of a kind made today that were not turned down, for the daily caps. */
function agent_proposed_today(PDO $pdo, string $kind, ?string $platform = null): int
{
    $sql = "SELECT COUNT(*) FROM agent_proposals
             WHERE environment = ? AND kind = ? AND status != 'rejected' AND created_at >= CURDATE()";
    $params = [current_environment(), $kind];
    if ($platform !== null) {
        $sql .= ' AND platform = ?';
        $params[] = $platform;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** The same words with spacing and case ignored, to catch a post or email sent twice. */
function agent_text_hash(string $text): string
{
    return hash('sha256', mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text))));
}

/**
 * Where each claim in a post or email comes from. Required on every proposal: it cannot prove
 * a claim, but it makes the check happen and leaves something to audit.
 *
 * @return string[]
 */
function agent_sources(array $in): array
{
    $sources = array_values(array_filter(array_map(
        fn ($s) => mb_substr(trim((string) $s), 0, 500),
        is_array($in['sources'] ?? null) ? $in['sources'] : []
    )));
    if (!$sources) {
        throw new AgentRefused('BAD_INPUT', 'Give "sources": for each claim, the facts-file line or the page it comes from. For advice that states no fact, say so in one entry.');
    }
    return array_slice($sources, 0, 12);
}

function agent_save_proposal(PDO $pdo, ?int $runId, string $kind, ?string $platform, array $payload, string $hash): int
{
    $pdo->prepare(
        'INSERT INTO agent_proposals (environment, run_id, kind, platform, payload, text_hash) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([current_environment(), $runId, $kind, $platform,
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $hash]);
    return (int) $pdo->lastInsertId();
}

/**
 * Proposes one post on one platform.
 *
 * @return array{proposal_id: int, status: string}
 */
function agent_propose_post(PDO $pdo, ?int $runId, array $in, ?callable $http = null): array
{
    if (!agent_on($pdo, 'posting_enabled')) {
        throw new AgentRefused('POSTING_OFF', 'Posting is switched off for the agent.', 403);
    }

    $platform = strtolower(trim((string) ($in['platform'] ?? '')));
    $text = trim((string) ($in['text'] ?? ''));
    $link = trim((string) ($in['link'] ?? ''));

    if (!isset(AGENT_PLATFORM_LIMITS[$platform])) {
        throw new AgentRefused('BAD_INPUT', 'platform is one of: ' . implode(', ', array_keys(AGENT_PLATFORM_LIMITS)) . '.');
    }
    if (!(agent_connections($pdo)[$platform]['connected'] ?? false)) {
        throw new AgentRefused('NOT_CONNECTED', ucfirst($platform) . ' is not connected. Say so in your summary and leave it out today.', 409);
    }
    if ($link !== '' && !agent_is_site_url($link)) {
        throw new AgentRefused('BAD_INPUT', 'link must be a page on https://argorobots.com, normally one of your tracked links.');
    }
    $length = mb_strlen($text) + ($link !== '' && $platform === 'bluesky' ? mb_strlen($link) + 2 : 0);
    if (mb_strlen($text) < 20 || $length > AGENT_PLATFORM_LIMITS[$platform]) {
        throw new AgentRefused('BAD_INPUT', ucfirst($platform) . ' takes 20 to ' . AGENT_PLATFORM_LIMITS[$platform] . " characters, link included. This is $length.");
    }
    $sources = agent_sources($in);

    $limit = agent_limit($pdo, 'posts_per_day');
    if (agent_proposed_today($pdo, 'post', $platform) >= $limit) {
        throw new AgentRefused('DAILY_LIMIT', "Today's limit of $limit post(s) on $platform is used up.", 429);
    }

    $hash = agent_text_hash($text);
    $seen = $pdo->prepare(
        "SELECT 1 FROM agent_proposals WHERE environment = ? AND kind = 'post' AND platform = ? AND text_hash = ?
            AND status != 'rejected' AND created_at >= NOW() - INTERVAL 90 DAY LIMIT 1"
    );
    $seen->execute([current_environment(), $platform, $hash]);
    if ($seen->fetchColumn()) {
        throw new AgentRefused('DUPLICATE', 'That exact post was already proposed on this platform in the last 90 days.', 409);
    }

    $id = agent_save_proposal($pdo, $runId, 'post', $platform, [
        'text' => $text,
        'link' => $link,
        'sources' => $sources,
        'about' => in_array($in['about'] ?? '', ['useful', 'product'], true) ? $in['about'] : 'useful',
        'experiment_key' => mb_substr(trim((string) ($in['experiment_key'] ?? '')), 0, 60),
    ], $hash);

    return agent_after_proposing($pdo, $id, 'approve_posts', $http);
}

/**
 * Proposes a first outreach email to a business the agent found.
 *
 * Everything that could make the email a mistake is checked now, so the owner is never asked
 * to approve one that would be refused anyway: the address is not a known contact, the
 * business is not already on the list, and the address is really on the page it is said to
 * be on.
 *
 * @param callable|null $fetch stands in for agent_fetch_page in tests
 * @return array{proposal_id: int, status: string}
 */
function agent_propose_email(PDO $pdo, ?int $runId, array $in, ?callable $fetch = null): array
{
    if (!agent_on($pdo, 'outreach_enabled')) {
        throw new AgentRefused('OUTREACH_OFF', 'Outreach is switched off for the agent.', 403);
    }

    $name = trim((string) ($in['business_name'] ?? ''));
    $email = strtolower(trim((string) ($in['email'] ?? '')));
    $foundOn = trim((string) ($in['found_on'] ?? ''));
    $website = trim((string) ($in['website'] ?? ''));
    $why = trim((string) ($in['why'] ?? ''));
    $subject = trim((string) ($in['subject'] ?? ''));
    $body = trim((string) ($in['body'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new AgentRefused('BAD_INPUT', 'An email needs a business_name and a valid email address.');
    }
    if (!filter_var($foundOn, FILTER_VALIDATE_URL)) {
        throw new AgentRefused('BAD_INPUT', 'found_on is the full URL of the page where you read the address.');
    }
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        throw new AgentRefused('BAD_INPUT', 'website must be a full URL, or left out.');
    }
    if ($why === '') {
        throw new AgentRefused('BAD_INPUT', 'Say in "why" how you found this business and why Argo Books fits it.');
    }
    if ($subject === '' || mb_strlen($subject) > 150) {
        throw new AgentRefused('BAD_INPUT', 'The subject is 1 to 150 characters.');
    }
    if (mb_strlen($body) < 150 || mb_strlen($body) > 2000) {
        throw new AgentRefused('BAD_INPUT', 'The body is 150 to 2000 characters. Short is better.');
    }
    $sources = agent_sources($in);

    $limit = agent_limit($pdo, 'emails_per_day');
    if (agent_proposed_today($pdo, 'email') >= $limit) {
        throw new AgentRefused('DAILY_LIMIT', "Today's limit of $limit outreach email(s) is used up.", 429);
    }

    $refusal = agent_email_blocked($pdo, $email, $website);
    if ($refusal !== null) {
        agent_log($pdo, $runId, 'email_propose', 'refused', ['business_name' => $name, 'reason' => $refusal[0]]);
        throw new AgentRefused($refusal[1], $refusal[2], 409);
    }
    $pending = $pdo->prepare(
        "SELECT 1 FROM agent_proposals WHERE environment = ? AND kind = 'email' AND status IN ('pending', 'approved')
            AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.email')) = ? LIMIT 1"
    );
    $pending->execute([current_environment(), $email]);
    if ($pending->fetchColumn()) {
        throw new AgentRefused('DUPLICATE', 'An email to that address is already waiting.', 409);
    }

    $html = ($fetch ?? 'agent_fetch_page')($foundOn);
    if ($html === null || !agent_email_on_page($html, $email)) {
        agent_log($pdo, $runId, 'email_propose', 'refused', ['business_name' => $name, 'reason' => 'address not on the page', 'found_on' => $foundOn]);
        throw new AgentRefused('NOT_VERIFIED', 'That address is not on the page you gave. Open the page yourself and read the address from it; if it is not there, leave this business out.', 409);
    }

    $country = strtoupper(trim((string) ($in['country'] ?? 'CA')));
    $id = agent_save_proposal($pdo, $runId, 'email', null, [
        'business_name' => mb_substr($name, 0, 255),
        'contact_name' => mb_substr(trim((string) ($in['contact_name'] ?? '')), 0, 255),
        'email' => $email,
        'found_on' => $foundOn,
        'website' => $website,
        'category' => mb_substr(trim((string) ($in['category'] ?? '')), 0, 100),
        'city' => mb_substr(trim((string) ($in['city'] ?? '')), 0, 100),
        'country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : 'CA',
        'why' => mb_substr($why, 0, 2000),
        'subject' => $subject,
        'body' => $body,
        'sources' => $sources,
        'experiment_key' => mb_substr(trim((string) ($in['experiment_key'] ?? '')), 0, 60),
    ], agent_text_hash($email));

    return agent_after_proposing($pdo, $id, 'approve_emails', null);
}

/**
 * Why an address or business cannot be emailed, or null when it can.
 *
 * @return array{0: string, 1: string, 2: string}|null [what to record, code, what to tell the agent]
 */
function agent_email_blocked(PDO $pdo, string $email, string $website): ?array
{
    $known = outreach_known_contact($pdo, $email);
    if ($known !== null) {
        // The agent is told it cannot, not why: which list a person is on is theirs to know.
        return [$known, 'NOT_ALLOWED', 'That address cannot be contacted. Do not look for another address for the same business.'];
    }

    $dupe = $pdo->prepare('SELECT 1 FROM outreach_leads WHERE LOWER(email) = ?' . ($website !== '' ? ' OR website = ?' : '') . ' LIMIT 1');
    $dupe->execute($website !== '' ? [$email, $website] : [$email]);
    if ($dupe->fetchColumn()) {
        return ['already a lead', 'DUPLICATE', 'That business is already on the outreach list.'];
    }
    return null;
}

/** Leaves a new proposal waiting, or carries it out when approval is off for its kind. */
function agent_after_proposing(PDO $pdo, int $id, string $approvalSetting, ?callable $http): array
{
    if (agent_on($pdo, $approvalSetting)) {
        return ['proposal_id' => $id, 'status' => 'waiting for approval'];
    }
    $pdo->prepare("UPDATE agent_proposals SET status = 'approved', decided_at = NOW() WHERE id = ?")->execute([$id]);
    return ['proposal_id' => $id, 'status' => agent_carry_out($pdo, $id, $http)];
}

/**
 * The owner's decision on a waiting proposal.
 *
 * @param array $edits fields of the payload the owner rewrote (text, or subject and body)
 * @return string the proposal's status afterwards
 */
function agent_decide(PDO $pdo, int $id, bool $approve, array $edits = [], string $note = '', ?callable $http = null): string
{
    $proposal = agent_proposal($pdo, $id);
    if ($proposal === null || $proposal['status'] !== 'pending') {
        throw new AgentRefused('NOT_PENDING', 'That proposal is not waiting for a decision.', 409);
    }

    $note = mb_substr(trim($note), 0, 500);
    if (!$approve) {
        $pdo->prepare("UPDATE agent_proposals SET status = 'rejected', owner_note = ?, decided_at = NOW() WHERE id = ?")
            ->execute([$note, $id]);
        return 'rejected';
    }

    $payload = $proposal['payload'];
    $changed = [];
    foreach (['text', 'subject', 'body'] as $field) {
        if (isset($edits[$field]) && trim((string) $edits[$field]) !== '' && trim((string) $edits[$field]) !== ($payload[$field] ?? '')) {
            $changed[$field] = $payload[$field] ?? '';
            $payload[$field] = trim((string) $edits[$field]);
        }
    }
    if ($changed) {
        // What the agent first wrote is kept, so it can be shown what was changed.
        $payload['as_written'] = $changed;
    }

    $pdo->prepare(
        "UPDATE agent_proposals SET status = 'approved', payload = ?, edited = ?, owner_note = ?, decided_at = NOW() WHERE id = ?"
    )->execute([json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $changed ? 1 : 0, $note, $id]);

    return agent_carry_out($pdo, $id, $http);
}

/**
 * Carries out an approved proposal: publishes the post, or puts the business on the outreach
 * list with its email ready to send.
 *
 * @return string 'done' or 'failed'
 */
function agent_carry_out(PDO $pdo, int $id, ?callable $http = null): string
{
    $proposal = agent_proposal($pdo, $id);
    if ($proposal === null || $proposal['status'] !== 'approved') {
        throw new AgentRefused('NOT_APPROVED', 'Only an approved proposal can be carried out.', 409);
    }
    $payload = $proposal['payload'];
    $runId = $proposal['run_id'] === null ? null : (int) $proposal['run_id'];

    try {
        if ($proposal['kind'] === 'post') {
            $postId = agent_publish($pdo, (string) $proposal['platform'], $payload['text'], $payload['link'] ?? '', $http ?? 'agent_http');
            $result = ['post_id' => $postId];
            agent_log($pdo, $runId, 'post', 'ok', ['proposal_id' => $id, 'platform' => $proposal['platform'], 'post_id' => $postId]);
        } else {
            $result = ['lead_id' => agent_add_lead($pdo, $payload)];
            agent_log($pdo, $runId, 'email', 'ok', ['proposal_id' => $id, 'lead_id' => $result['lead_id'], 'business_name' => $payload['business_name']]);
        }
        $status = 'done';
    } catch (AgentPlatformFailed | AgentRefused $e) {
        $result = ['error' => $e->getMessage()];
        $status = 'failed';
        agent_log($pdo, $runId, $proposal['kind'], 'failed', ['proposal_id' => $id, 'error' => $e->getMessage()]);
    }

    $pdo->prepare('UPDATE agent_proposals SET status = ?, result = ? WHERE id = ?')
        ->execute([$status, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $id]);
    return $status;
}

/**
 * Puts a business on the outreach list with the email the agent wrote already approved. The
 * existing pipeline only drafts for a lead that has no email, and sends any that is approved,
 * so it sends this one as it stands: same daily cap, same tracked link, same unsubscribe line.
 *
 * The contact check runs again here. Days can pass between proposing and approving, and the
 * business may have become a customer.
 */
function agent_add_lead(PDO $pdo, array $payload): int
{
    $refusal = agent_email_blocked($pdo, $payload['email'], (string) ($payload['website'] ?? ''));
    if ($refusal !== null) {
        throw new AgentRefused($refusal[1], 'Not added: ' . $refusal[0] . '.', 409);
    }

    $pdo->prepare(
        "INSERT INTO outreach_leads
            (business_name, contact_name, email, website, category, city, country, source, status, notes,
             draft_subject, draft_body, drafted_at, approval_status, approved_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'agent', 'draft_generated', ?, ?, ?, NOW(), 'approved', NOW())"
    )->execute([
        $payload['business_name'],
        ($payload['contact_name'] ?? '') ?: null,
        $payload['email'],
        ($payload['website'] ?? '') ?: null,
        ($payload['category'] ?? '') ?: null,
        ($payload['city'] ?? '') ?: null,
        $payload['country'] ?? 'CA',
        $payload['why'] ?? null,
        $payload['subject'],
        $payload['body'],
    ]);
    $leadId = (int) $pdo->lastInsertId();
    log_activity($pdo, $leadId, 'lead_created', 'Lead and first email added by the marketing agent');
    return $leadId;
}

// ─── Follow-ups for the agent's own leads ───

/** The follow-ups coming due for leads the agent added, with what was sent before. */
function agent_followups_due(PDO $pdo): array
{
    return $pdo->query(
        "SELECT f.id AS followup_id, f.touch_number, f.scheduled_for, l.business_name, l.category, l.city,
                l.draft_subject AS first_subject, l.draft_body AS first_body, l.sent_at AS first_sent_at
           FROM outreach_followups f JOIN outreach_leads l ON l.id = f.lead_id
          WHERE l.source = 'agent' AND f.status = 'scheduled' AND f.scheduled_for <= NOW() + INTERVAL 2 DAY
          ORDER BY f.scheduled_for LIMIT 30"
    )->fetchAll();
}

/**
 * Stores the follow-up the agent wrote. While email approval is on it lands in the outreach
 * page's own Follow-ups queue for review, which is where follow-ups have always been approved.
 */
function agent_write_followup(PDO $pdo, ?int $runId, array $in): string
{
    $id = (int) ($in['followup_id'] ?? 0);
    $body = trim((string) ($in['body'] ?? ''));
    if (mb_strlen($body) < 80 || mb_strlen($body) > 1500) {
        throw new AgentRefused('BAD_INPUT', 'A follow-up is 80 to 1500 characters.');
    }

    $stmt = $pdo->prepare(
        "SELECT f.id, l.draft_subject FROM outreach_followups f JOIN outreach_leads l ON l.id = f.lead_id
          WHERE f.id = ? AND l.source = 'agent' AND f.status = 'scheduled'"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row === false) {
        throw new AgentRefused('NOT_FOUND', 'That follow-up is not one of yours waiting to be written.', 404);
    }

    $status = agent_on($pdo, 'approve_emails') ? 'drafted' : 'approved';
    $subject = trim((string) ($in['subject'] ?? '')) ?: 'Re: ' . $row['draft_subject'];
    $pdo->prepare('UPDATE outreach_followups SET draft_subject = ?, draft_body = ?, drafted_at = NOW(), status = ? WHERE id = ?')
        ->execute([mb_substr($subject, 0, 500), $body, $status, $id]);

    agent_log($pdo, $runId, 'followup', 'ok', ['followup_id' => $id, 'status' => $status]);
    return $status === 'drafted' ? 'waiting for approval' : 'approved';
}

// ─── What a run starts with ───

/**
 * What the owner decided about earlier proposals, shown to the agent once. An edit or a
 * rejection is the clearest teaching it gets.
 */
function agent_decisions_to_report(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT id, kind, platform, status, edited, owner_note, payload, result, decided_at FROM agent_proposals
          WHERE environment = ? AND status != 'pending' AND told_agent_at IS NULL ORDER BY id LIMIT 40"
    );
    $stmt->execute([current_environment()]);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $row) {
        $payload = json_decode((string) $row['payload'], true) ?: [];
        $out[] = [
            'proposal_id' => (int) $row['id'],
            'kind' => $row['kind'],
            'platform' => $row['platform'],
            'outcome' => $row['status'],
            'owner_changed_it' => (bool) $row['edited'],
            'as_you_wrote_it' => $payload['as_written'] ?? null,
            'as_it_went_out' => array_intersect_key($payload, array_flip(['text', 'subject', 'body'])),
            'owner_note' => $row['owner_note'],
            'result' => json_decode((string) $row['result'], true),
        ];
    }
    if ($rows) {
        $ids = implode(',', array_map(fn ($r) => (int) $r['id'], $rows));
        $pdo->exec("UPDATE agent_proposals SET told_agent_at = NOW() WHERE id IN ($ids)");
    }
    return $out;
}

/** How recent posts have done, fetched fresh and kept with the proposal. */
function agent_refresh_post_numbers(PDO $pdo, ?callable $http = null): array
{
    $stmt = $pdo->prepare(
        "SELECT id, platform, payload, result, decided_at FROM agent_proposals
          WHERE environment = ? AND kind = 'post' AND status = 'done' AND decided_at >= NOW() - INTERVAL 14 DAY
          ORDER BY id DESC LIMIT 20"
    );
    $stmt->execute([current_environment()]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $payload = json_decode((string) $row['payload'], true) ?: [];
        $result = json_decode((string) $row['result'], true) ?: [];
        $numbers = agent_post_numbers($pdo, (string) $row['platform'], (string) ($result['post_id'] ?? ''), $http ?? 'agent_http');
        if ($numbers !== null) {
            $result['numbers'] = $numbers;
            $pdo->prepare('UPDATE agent_proposals SET result = ? WHERE id = ?')->execute([json_encode($result), $row['id']]);
        }
        $out[] = [
            'proposal_id' => (int) $row['id'],
            'platform' => $row['platform'],
            'posted_at' => $row['decided_at'],
            'text' => mb_substr((string) ($payload['text'] ?? ''), 0, 160),
            'link' => $payload['link'] ?? '',
            'experiment_key' => $payload['experiment_key'] ?? '',
            'numbers' => $result['numbers'] ?? 'this platform does not report them; judge it by its tracked link',
        ];
    }
    return $out;
}

/**
 * Everything a run starts with, in one answer: the instructions, the facts, the memory, the
 * numbers and what has changed since last time.
 */
function agent_brief(PDO $pdo, int $runId, ?callable $http = null): array
{
    $http ??= 'agent_http';
    agent_renew_threads($pdo, $http);

    $notes = agent_notes($pdo);
    $ownerNote = '';
    foreach ($notes as $i => $note) {
        if ($note['name'] === AGENT_OWNER_NOTE) {
            $ownerNote = $note['body'];
            unset($notes[$i]);
        }
    }

    $connections = agent_connections($pdo);
    $waiting = $pdo->prepare("SELECT kind, COUNT(*) AS n FROM agent_proposals WHERE environment = ? AND status = 'pending' GROUP BY kind");
    $waiting->execute([current_environment()]);

    return [
        'run_id' => $runId,
        'today' => date('Y-m-d'),
        'playbook' => agent_playbook(),
        'facts' => agent_facts(),
        'plans_and_prices' => agent_live_plans(),
        'site_pages' => agent_site_pages(),
        'note_from_the_owner' => $ownerNote,
        'your_notes' => array_values($notes),
        'recent_journal' => agent_journal_recent($pdo),
        'owner_decisions_since_last_run' => agent_decisions_to_report($pdo),
        'still_waiting_for_the_owner' => $waiting->fetchAll(PDO::FETCH_KEY_PAIR),
        'recent_posts' => agent_refresh_post_numbers($pdo, $http),
        'followups_to_write' => agent_on($pdo, 'outreach_enabled') ? agent_followups_due($pdo) : [],
        'numbers' => agent_overview($pdo),
        'readable_tables' => agent_schema($pdo),
        'what_is_switched_on' => [
            'posting' => agent_on($pdo, 'posting_enabled'),
            'outreach' => agent_on($pdo, 'outreach_enabled'),
            'outreach_sending' => agent_outreach_sending($pdo),
            'approved_emails_not_sent_yet' => agent_emails_not_sent($pdo),
            'posts_wait_for_approval' => agent_on($pdo, 'approve_posts'),
            'emails_wait_for_approval' => agent_on($pdo, 'approve_emails'),
            'sql' => ($_ENV['AGENT_DB_USER'] ?? '') !== '',
            'research_through_gemini' => ($_ENV['GEMINI_API_KEY'] ?? '') !== '',
            'platforms' => array_map(fn ($c) => $c['connected'], $connections),
        ],
        'limits_today' => [
            'calls_this_run' => agent_limit($pdo, 'calls_per_run'),
            'posts_per_platform' => agent_limit($pdo, 'posts_per_day'),
            'outreach_emails' => agent_limit($pdo, 'emails_per_day'),
            'new_links' => agent_limit($pdo, 'links_per_day'),
            'research_questions' => agent_limit($pdo, 'research_per_day'),
        ],
    ];
}

// ─── The daily update ───

/** The agent's summary as HTML: a line starting "- " is a list item, any other line a paragraph. */
function agent_summary_html(string $summary): string
{
    $html = '';
    $items = [];
    $lines = preg_split('/\R+/', trim($summary));
    $lines[] = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            $items[] = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            continue;
        }
        if ($items) {
            $html .= '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
            $items = [];
        }
        if ($line !== '') {
            $html .= '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }
    return $html;
}

/**
 * The email that tells the owner what a run did, as [subject, html], or null for an unknown run.
 *
 * It is read every morning, so it leads with what needs him and stays short: the posts and
 * emails waiting, the agent's own summary, and one line of counts from the site's record. The
 * full text of each proposal and the full record are on the admin page.
 */
function agent_update_email(PDO $pdo, int $runId): ?array
{
    $env = current_environment();
    $run = $pdo->prepare('SELECT * FROM agent_runs WHERE id = ? AND environment = ?');
    $run->execute([$runId, $env]);
    $run = $run->fetch();
    if ($run === false) {
        return null;
    }

    $esc = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $count = fn (int $n, string $one) => "$n $one" . ($n === 1 ? '' : 's');

    $pending = $pdo->prepare("SELECT kind, platform, payload FROM agent_proposals WHERE environment = ? AND status = 'pending' ORDER BY id");
    $pending->execute([$env]);
    $pending = $pending->fetchAll();
    $needs = [];
    foreach (array_slice($pending, 0, 8) as $row) {
        $p = json_decode((string) $row['payload'], true) ?: [];
        $needs[] = $row['kind'] === 'post'
            ? 'Post for ' . ucfirst((string) $row['platform']) . ': ' . mb_strimwidth((string) ($p['text'] ?? ''), 0, 90, '...')
            : 'Email to ' . ($p['business_name'] ?? '') . (empty($p['city']) ? '' : ', ' . $p['city']) . ': ' . ($p['subject'] ?? '');
    }
    if (count($pending) > 8) {
        $needs[] = 'And ' . (count($pending) - 8) . ' more.';
    }
    foreach (agent_connections($pdo) as $platform => $connection) {
        if ($connection['expires_at'] !== null && $connection['expires_at'] - time() < 10 * 86400) {
            $needs[] = $connection['expires_at'] > time()
                ? ucfirst($platform) . ' sign-in runs out on ' . date('F j', $connection['expires_at']) . ' and needs connecting again'
                : ucfirst($platform) . ' sign-in has run out, so nothing is being posted there';
        }
    }
    $notSent = agent_emails_not_sent($pdo);
    if ($notSent > 0 && !agent_outreach_sending($pdo)) {
        $needs[] = $count($notSent, 'approved email') . ' cannot go out, because outreach is switched off in Admin, Outreach, Settings';
    }

    $html = '';
    if ($needs) {
        $html .= '<h3>Waiting for you</h3><ul><li>' . implode('</li><li>', array_map($esc, $needs)) . '</li></ul>';
        $html .= '<p><a href="https://argorobots.com/admin/agent/">Review and approve</a></p>';
    }
    $html .= '<h3>What it did</h3>' . agent_summary_html($run['summary'] ?: 'It finished without writing a summary.');

    $actions = $pdo->prepare("SELECT action, COUNT(*) FROM agent_actions WHERE run_id = ? AND environment = ? AND status = 'ok' GROUP BY action");
    $actions->execute([$runId, $env]);
    $actions = $actions->fetchAll(PDO::FETCH_KEY_PAIR);
    $proposed = $pdo->prepare('SELECT kind, COUNT(*) FROM agent_proposals WHERE run_id = ? AND environment = ? GROUP BY kind');
    $proposed->execute([$runId, $env]);
    $proposed = $proposed->fetchAll(PDO::FETCH_KEY_PAIR);
    $did = [];
    foreach (['research' => 'research question', 'link_create' => 'new link'] as $action => $noun) {
        if (!empty($actions[$action])) {
            $did[] = $count((int) $actions[$action], $noun);
        }
    }
    foreach (['email', 'post'] as $kind) {
        if (!empty($proposed[$kind])) {
            $did[] = $count((int) $proposed[$kind], $kind) . ' written';
        }
    }
    $html .= '<p style="color:#666;">Run ' . (int) $runId . ($did ? ': ' . implode(', ', $did) : '') . '. '
           . 'The full record is on the <a href="https://argorobots.com/admin/agent/?tab=runs">Agent page</a>.</p>';

    return ['Marketing agent: ' . ($pending ? count($pending) . ' waiting for you' : date('M j')), $html];
}

/** Sends the update when a run finishes. */
function agent_send_update(PDO $pdo, int $runId): bool
{
    $email = agent_update_email($pdo, $runId);
    if ($email === null) {
        return false;
    }
    $sent = send_styled_email(admin_notification_email(), $email[0], $email[1]);
    if ($sent) {
        $pdo->prepare('UPDATE agent_runs SET emailed_at = NOW() WHERE id = ?')->execute([$runId]);
    }
    return (bool) $sent;
}
