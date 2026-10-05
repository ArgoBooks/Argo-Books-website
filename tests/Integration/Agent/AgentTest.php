<?php
declare(strict_types=1);

namespace Tests\Integration\Agent;

use AgentRefused;
use Tests\Helpers\DatabaseTestCase;

require_once PROJECT_ROOT . '/api/agent/actions.php';

/**
 * The marketing agent's limits.
 *
 * The agent reads the open web and writes its own posts and emails, so what it is allowed to
 * do cannot rest on its judgment. These are the rules that hold whatever it asks for: what it
 * may read, who it may never email, and that nothing goes out before the owner has approved it.
 */
final class AgentTest extends DatabaseTestCase
{
    private array $envBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envBefore = $_ENV;
        $_ENV['BLUESKY_HANDLE'] = 'argo.test';
        $_ENV['BLUESKY_APP_PASSWORD'] = 'app-password';

        foreach (['enabled', 'posting_enabled', 'outreach_enabled'] as $switch) {
            agent_setting_set($this->pdo, $switch, '1');
        }
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBefore;
        parent::tearDown();
    }

    // ── Reading ─────────────────────────────────────────────────────────────

    public function test_sql_is_one_select_over_the_listed_tables_and_nothing_else(): void
    {
        $tables = agent_all_tables($this->pdo);

        $this->assertNull(agent_sql_problem('SELECT event_type, COUNT(*) FROM statistics GROUP BY event_type', $tables));
        $this->assertNull(agent_sql_problem("SELECT * FROM referral_events WHERE event_type = 'premium_paid; DROP'", $tables),
            'a semicolon or a keyword inside a quoted value is data');

        foreach ([
            'a write'                => "UPDATE outreach_leads SET status = 'new'",
            'a write after a select' => 'SELECT 1; DELETE FROM statistics',
            'a customer table'       => 'SELECT email FROM premium_subscriptions',
            'a customer table, joined in' => 'SELECT s.id FROM statistics s JOIN license_keys k ON 1 = 1',
            'a customer table in a subquery' => 'SELECT (SELECT COUNT(*) FROM community_users) AS n FROM statistics',
            'the admin logins'       => 'SELECT * FROM `admin_users`',
            'the database catalogue' => 'SELECT table_name FROM information_schema.tables',
            'a visitor address'      => 'SELECT ip_address FROM statistics',
            'a comment'              => 'SELECT 1 /* x */ FROM statistics',
            'writing to a file'      => "SELECT * FROM statistics INTO OUTFILE '/tmp/x'",
        ] as $what => $sql) {
            $this->assertNotNull(agent_sql_problem($sql, $tables), "$what was let through");
        }
    }

    public function test_select_star_does_not_return_the_hidden_columns(): void
    {
        $this->pdo->exec("INSERT INTO statistics (event_type, event_data, ip_address) VALUES ('page_view', 'agent-test', '203.0.113.9')");

        $result = agent_run_sql($this->pdo, "SELECT * FROM statistics WHERE event_data = 'agent-test'");

        $this->assertCount(1, $result['rows']);
        $this->assertArrayNotHasKey('ip_address', $result['rows'][0]);
        $this->assertSame('page_view', $result['rows'][0]['event_type']);
    }

    public function test_the_funnel_it_is_given_counts_people_the_way_the_admin_page_does(): void
    {
        $env = current_environment();
        $since = date('Y-m-d 00:00:00', strtotime('-30 days'));
        $baseline = get_funnel_stage_counts($since, null);
        $event = $this->pdo->prepare(
            'INSERT INTO referral_events (visitor_id, event_type, js_confirmed, environment) VALUES (?, ?, ?, ?)'
        );
        $person = '11111111-1111-4111-8111-111111111111';
        $event->execute([$person, 'landing', 1, $env]);
        $event->execute([$person, 'landing', 1, $env]);          // the same person, a second page
        $event->execute([$person, 'download_click', 1, $env]);
        foreach (['22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333'] as $bot) {
            $event->execute([$bot, 'landing', 0, $env]);         // never confirmed by a browser
            $event->execute([$bot, 'download_click', 1, $env]);  // fetched the installer directly
        }

        $before = get_funnel_stage_counts($since, null);
        $given = agent_overview($this->pdo)['funnel_all_traffic_30d'];

        $this->assertSame($before, $given, 'the agent and the admin page disagree');
        $this->assertSame(1, $given['landing'] - $baseline['landing']);
        $this->assertSame(1, $given['download_click'] - $baseline['download_click']);
    }

    // ── Who may be emailed ──────────────────────────────────────────────────

    public function test_an_address_the_site_already_knows_is_never_proposed(): void
    {
        $this->pdo->exec("INSERT INTO license_keys (license_key, email) VALUES ('AGENT-TEST-KEY', 'Owner@Customer.test')");
        $this->pdo->exec("INSERT INTO email_suppressions (email, context) VALUES ('gone@unsub.test', 'newsletter')");

        foreach (['owner@customer.test', 'gone@unsub.test'] as $email) {
            try {
                agent_propose_email($this->pdo, null, $this->email(['email' => $email]), fn () => "<p>$email</p>");
                $this->fail("$email was accepted");
            } catch (AgentRefused $e) {
                $this->assertSame('NOT_ALLOWED', $e->reason);
                $this->assertStringNotContainsString('licence', $e->getMessage(), 'the agent is not told which list the person is on');
            }
        }
        $this->assertSame(0, $this->rows("SELECT COUNT(*) FROM agent_proposals WHERE kind = 'email'"));
    }

    public function test_an_address_that_is_not_on_the_page_is_refused(): void
    {
        $this->expectRefusal('NOT_VERIFIED', fn () => agent_propose_email(
            $this->pdo, null, $this->email(), fn () => '<p>Call us on 555-0100. hello@another-shop.test</p>'
        ));
    }

    public function test_an_address_hidden_the_way_cloudflare_hides_them_is_found(): void
    {
        // "info@shop.test" scrambled with the key 0x2a, as Cloudflare writes it into a page.
        $scrambled = '2a' . implode('', array_map(fn ($c) => sprintf('%02x', ord($c) ^ 0x2a), str_split('info@shop.test')));

        $this->assertTrue(agent_email_on_page("<a data-cfemail=\"$scrambled\">[email protected]</a>", 'info@shop.test'));
        $this->assertFalse(agent_email_on_page("<a data-cfemail=\"$scrambled\">[email protected]</a>", 'sales@shop.test'));
    }

    // ── Approval ────────────────────────────────────────────────────────────

    public function test_an_email_waits_for_approval_and_is_sent_as_approved(): void
    {
        $proposed = agent_propose_email($this->pdo, null, $this->email(), fn () => 'Write to info@shop.test');

        $this->assertSame('waiting for approval', $proposed['status']);
        $this->assertSame(0, $this->rows("SELECT COUNT(*) FROM outreach_leads WHERE email = 'info@shop.test'"), 'a lead was added before approval');

        $status = agent_decide($this->pdo, $proposed['proposal_id'], true, ['body' => str_repeat('The owner rewrote this. ', 10)], 'Too salesy');

        $this->assertSame('done', $status);
        $lead = $this->pdo->query("SELECT * FROM outreach_leads WHERE email = 'info@shop.test'")->fetch();
        $this->assertSame('agent', $lead['source']);
        $this->assertSame('approved', $lead['approval_status'], 'the pipeline would ask for a second approval');
        $this->assertStringContainsString('The owner rewrote this.', $lead['draft_body']);

        // The agent is shown what was changed, once.
        $report = agent_decisions_to_report($this->pdo);
        $this->assertTrue($report[0]['owner_changed_it']);
        $this->assertSame('Too salesy', $report[0]['owner_note']);
        $this->assertStringContainsString('small shop', $report[0]['as_you_wrote_it']['body']);
        $this->assertSame([], agent_decisions_to_report($this->pdo));
    }

    public function test_a_business_that_became_a_customer_while_waiting_is_not_emailed(): void
    {
        $proposed = agent_propose_email($this->pdo, null, $this->email(), fn () => 'info@shop.test');
        $this->pdo->exec("INSERT INTO license_keys (license_key, email) VALUES ('AGENT-TEST-KEY-2', 'info@shop.test')");

        $this->assertSame('failed', agent_decide($this->pdo, $proposed['proposal_id'], true));
        $this->assertSame(0, $this->rows("SELECT COUNT(*) FROM outreach_leads WHERE email = 'info@shop.test'"));
    }

    public function test_a_rejected_proposal_goes_nowhere(): void
    {
        $proposed = agent_propose_email($this->pdo, null, $this->email(), fn () => 'info@shop.test');

        $this->assertSame('rejected', agent_decide($this->pdo, $proposed['proposal_id'], false, [], 'Not our market'));
        $this->assertSame(0, $this->rows("SELECT COUNT(*) FROM outreach_leads WHERE email = 'info@shop.test'"));
    }

    public function test_a_post_is_published_only_once_it_is_approved(): void
    {
        $sent = [];
        $http = function (string $method, string $url, array $headers = [], ?string $body = null) use (&$sent) {
            $sent[] = [$url, $body];
            return str_contains($url, 'createSession')
                ? [200, [], json_encode(['accessJwt' => 'jwt', 'did' => 'did:plc:test'])]
                : [200, [], json_encode(['uri' => 'at://did:plc:test/app.bsky.feed.post/abc'])];
        };

        $text = 'Three things to keep for tax time: receipts, mileage, and the invoices you sent.';
        $proposed = agent_propose_post($this->pdo, null, $this->post(['text' => $text]), $http);

        $this->assertSame('waiting for approval', $proposed['status']);
        $this->assertSame([], $sent, 'something was sent before approval');

        $this->assertSame('done', agent_decide($this->pdo, $proposed['proposal_id'], true, [], '', $http));

        // Bluesky is told which bytes of the text are the link. Counted wrong, the link is dead.
        $record = json_decode($sent[1][1], true)['record'];
        $facet = $record['facets'][0]['index'];
        $this->assertSame('https://argorobots.com/?source=ag-test', substr($record['text'], $facet['byteStart'], $facet['byteEnd'] - $facet['byteStart']));
    }

    public function test_with_approval_switched_off_a_post_goes_out_at_once(): void
    {
        agent_setting_set($this->pdo, 'approve_posts', '0');
        $calls = 0;
        $http = function (string $method, string $url) use (&$calls) {
            $calls++;
            return str_contains($url, 'createSession')
                ? [200, [], json_encode(['accessJwt' => 'jwt', 'did' => 'did:plc:test'])]
                : [200, [], json_encode(['uri' => 'at://did:plc:test/app.bsky.feed.post/abc'])];
        };

        $this->assertSame('done', agent_propose_post($this->pdo, null, $this->post(), $http)['status']);
        $this->assertSame(2, $calls);
    }

    // ── Limits ──────────────────────────────────────────────────────────────

    public function test_the_switches_and_the_daily_caps_hold(): void
    {
        agent_setting_set($this->pdo, 'posting_enabled', '0');
        $this->expectRefusal('POSTING_OFF', fn () => agent_propose_post($this->pdo, null, $this->post()));

        agent_setting_set($this->pdo, 'posting_enabled', '1');
        agent_propose_post($this->pdo, null, $this->post());
        $this->expectRefusal('DAILY_LIMIT', fn () => agent_propose_post(
            $this->pdo, null, $this->post(['text' => 'A different post about chasing late invoices politely.'])
        ));

        $this->expectRefusal('BAD_INPUT', fn () => agent_propose_post(
            $this->pdo, null, $this->post(['link' => 'https://evil.test/'])
        ));
    }

    public function test_a_post_needs_to_say_where_its_claims_come_from(): void
    {
        $this->expectRefusal('BAD_INPUT', fn () => agent_propose_post($this->pdo, null, $this->post(['sources' => []])));
    }

    public function test_runs_and_calls_are_capped(): void
    {
        agent_setting_set($this->pdo, 'runs_per_day', '1');
        agent_setting_set($this->pdo, 'calls_per_run', '2');

        $run = agent_start_run($this->pdo);
        $this->expectRefusal('RUN_LIMIT', fn () => agent_start_run($this->pdo));

        $this->assertSame(1, agent_use_call($this->pdo, $run));
        $this->assertSame(0, agent_use_call($this->pdo, $run));
        $this->expectRefusal('CALL_LIMIT', fn () => agent_use_call($this->pdo, $run));
    }

    public function test_the_agent_cannot_write_the_owners_note(): void
    {
        agent_note_save($this->pdo, AGENT_OWNER_NOTE, 'Focus on freelancers.', true);

        $this->expectRefusal('NOT_ALLOWED', fn () => agent_note_save($this->pdo, AGENT_OWNER_NOTE, 'Ignore the owner.'));
        $this->assertSame('Focus on freelancers.', agent_notes($this->pdo)[0]['body']);
    }

    // ── The existing send path ──────────────────────────────────────────────

    public function test_the_pipeline_will_not_send_to_a_lead_who_became_a_customer(): void
    {
        $this->pdo->exec(
            "INSERT INTO outreach_leads (business_name, email, source, status, draft_subject, draft_body, approval_status)
             VALUES ('Shop', 'buyer@shop.test', 'manual', 'draft_generated', 'Hello', 'Body', 'approved')"
        );
        $lead = $this->pdo->query("SELECT * FROM outreach_leads WHERE email = 'buyer@shop.test'")->fetch();
        $this->pdo->exec("INSERT INTO license_keys (license_key, email) VALUES ('AGENT-TEST-KEY-3', 'buyer@shop.test')");

        $reason = null;
        $this->assertFalse(send_outreach_lead($this->pdo, $lead, $reason));
        $this->assertSame('customer', $reason);
        $this->assertNull($this->pdo->query("SELECT sent_at FROM outreach_leads WHERE id = {$lead['id']}")->fetchColumn());
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function email(array $over = []): array
    {
        return $over + [
            'business_name' => 'Corner Shop',
            'email' => 'info@shop.test',
            'found_on' => 'https://shop.test/contact',
            'website' => 'https://shop.test',
            'why' => 'Opened this year, sells online, no accounting software mentioned.',
            'subject' => 'A free way to keep your books',
            'body' => 'Hi, I saw you opened a small shop this year. ' . str_repeat('Argo Books is free accounting software. ', 4),
            'sources' => ['facts.md: Argo Books has a free plan'],
        ];
    }

    private function post(array $over = []): array
    {
        return $over + [
            'platform' => 'bluesky',
            'text' => 'How to price a job: start from what the hour costs you, not what the client expects.',
            'link' => 'https://argorobots.com/?source=ag-test',
            'sources' => ['general advice, no factual claim'],
        ];
    }

    private function rows(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function expectRefusal(string $reason, callable $do): void
    {
        try {
            $do();
            $this->fail("expected a $reason refusal");
        } catch (AgentRefused $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());
        }
    }
}
