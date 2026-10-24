<?php
declare(strict_types=1);

namespace Tests\Integration\Agent;

use AgentRefused;
use Tests\Helpers\DatabaseTestCase;

require_once PROJECT_ROOT . '/api/agent/actions.php';

/**
 * Reddit replies. The agent drafts them and the owner posts them, so the rules that matter are
 * the ones that keep a reply from reading as spam: no link, no repeats, and a few a day.
 */
final class RedditReplyTest extends DatabaseTestCase
{
    private array $envBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envBefore = $_ENV;
        $_ENV['APP_ENV'] = 'production';
        agent_setting_set($this->pdo, 'enabled', '1');
        agent_setting_set($this->pdo, 'replies_enabled', '1');
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBefore;
        parent::tearDown();
    }

    public function test_a_reply_with_any_link_or_web_address_is_refused(): void
    {
        foreach ([
            'You could try it at https://argorobots.com, it is free to start with.',
            'There is one at www.argorobots.com that does receipts and invoices for you.',
            'Have a look at argorobots.com, it reads receipts and keeps the books offline.',
            'QuickBooks.com has a comparison page but it is fairly biased toward their plans.',
        ] as $text) {
            $this->expectRefusal('BAD_INPUT', fn () => agent_propose_reply($this->pdo, null, $this->reply(['text' => $this->padded($text)])));
        }
        $this->assertSame(0, $this->rows("SELECT COUNT(*) FROM agent_proposals WHERE kind = 'reply'"));
    }

    public function test_only_a_reddit_thread_can_be_replied_to(): void
    {
        foreach (['https://example.com/r/smallbusiness/comments/abc123/x/', 'https://www.reddit.com/r/smallbusiness/', 'not a url'] as $url) {
            $this->expectRefusal('BAD_INPUT', fn () => agent_propose_reply($this->pdo, null, $this->reply(['thread_url' => $url])));
        }
    }

    public function test_one_reply_per_thread_and_never_the_same_words_twice(): void
    {
        $this->assertSame('waiting for the owner to post it', agent_propose_reply($this->pdo, null, $this->reply())['status']);

        $this->expectRefusal('DUPLICATE', fn () => agent_propose_reply($this->pdo, null, $this->reply([
            'text' => $this->padded('A different answer to the same thread, which is still one too many.'),
        ])));
        $this->expectRefusal('DUPLICATE', fn () => agent_propose_reply($this->pdo, null, $this->reply([
            'thread_url' => 'https://www.reddit.com/r/Bookkeeping/comments/zzz999/other/',
        ])));
    }

    public function test_replies_are_capped_a_day_and_in_each_subreddit(): void
    {
        agent_setting_set($this->pdo, 'replies_per_day', '3');

        agent_propose_reply($this->pdo, null, $this->reply(['thread_url' => 'https://www.reddit.com/r/smallbusiness/comments/aaa111/x/', 'text' => $this->padded('First.')]));
        agent_propose_reply($this->pdo, null, $this->reply(['thread_url' => 'https://www.reddit.com/r/SmallBusiness/comments/aaa222/x/', 'text' => $this->padded('Second.')]));
        $this->expectRefusal('DAILY_LIMIT', fn () => agent_propose_reply($this->pdo, null, $this->reply([
            'thread_url' => 'https://www.reddit.com/r/smallbusiness/comments/aaa333/x/', 'text' => $this->padded('Third, same subreddit.'),
        ])));

        agent_propose_reply($this->pdo, null, $this->reply(['thread_url' => 'https://www.reddit.com/r/Bookkeeping/comments/bbb111/x/', 'text' => $this->padded('Third, elsewhere.')]));
        $this->expectRefusal('DAILY_LIMIT', fn () => agent_propose_reply($this->pdo, null, $this->reply([
            'thread_url' => 'https://www.reddit.com/r/Etsy/comments/ccc111/x/', 'text' => $this->padded('Fourth.'),
        ])));
    }

    public function test_marking_a_reply_posted_records_the_owners_wording_and_contacts_nothing(): void
    {
        $proposed = agent_propose_reply($this->pdo, null, $this->reply());
        $network = function () {
            throw new \LogicException('Marking a reply posted must not contact any platform.');
        };

        $edited = $this->padded('The owner reworded this before posting it himself.');
        $this->assertSame('done', agent_decide($this->pdo, $proposed['proposal_id'], true, ['text' => $edited], 'Too long', $network));

        $proposal = agent_proposal($this->pdo, $proposed['proposal_id']);
        $this->assertSame($edited, $proposal['payload']['text']);
        $this->assertSame('owner', json_decode((string) $proposal['result'], true)['posted_by']);
    }

    public function test_switched_off_it_neither_reads_reddit_nor_drafts(): void
    {
        agent_setting_set($this->pdo, 'replies_enabled', '0');
        $network = fn () => throw new \LogicException('Nothing may be fetched while Reddit replies are off.');

        $this->expectRefusal('REPLIES_OFF', fn () => agent_reddit_search($this->pdo, null, ['subreddit' => 'smallbusiness', 'query' => 'accounting'], $network));
        $this->expectRefusal('REPLIES_OFF', fn () => agent_propose_reply($this->pdo, null, $this->reply()));
    }

    public function test_a_search_reads_the_subreddit_feed_and_marks_threads_already_drafted(): void
    {
        agent_propose_reply($this->pdo, null, $this->reply());
        $asked = [];
        $http = function (string $method, string $url, array $headers = []) use (&$asked) {
            $asked = [$url, $headers];
            return [200, [], $this->feed()];
        };

        $found = agent_reddit_search($this->pdo, null, ['subreddit' => 'r/smallbusiness', 'query' => 'accounting software'], $http);

        $this->assertStringStartsWith('https://www.reddit.com/r/smallbusiness/search.rss?', $asked[0]);
        $this->assertStringContainsString('restrict_sr=1', $asked[0]);
        $this->assertContains('User-Agent: ' . AGENT_REDDIT_USER_AGENT, $asked[1]);

        $this->assertCount(2, $found['threads']);
        $this->assertSame('Which accounting software for a cleaning business?', $found['threads'][0]['title']);
        $this->assertSame('maria_cleans', $found['threads'][0]['author']);
        $this->assertTrue($found['threads'][0]['already_drafted']);
        $this->assertFalse($found['threads'][1]['already_drafted']);
        $this->assertStringNotContainsString('submitted by', $found['threads'][0]['opening']);
    }

    public function test_a_thread_comes_back_as_the_question_and_its_replies(): void
    {
        $thread = agent_reddit_thread($this->pdo, null, ['url' => 'https://old.reddit.com/r/smallbusiness/comments/ABC123/which/'], fn () => [200, [], $this->feed()]);

        $this->assertSame('https://www.reddit.com/r/smallbusiness/comments/abc123/', $thread['url']);
        $this->assertSame('Which accounting software for a cleaning business?', $thread['title']);
        $this->assertStringContainsString('three staff', $thread['question']);
        $this->assertCount(1, $thread['replies']);
    }

    public function test_reddit_refusals_tell_the_agent_what_to_do(): void
    {
        $this->expectRefusal('REDDIT_BUSY', fn () => agent_reddit_search($this->pdo, null, ['subreddit' => 'smallbusiness', 'query' => 'books'], fn () => [429, [], '']));
        $this->expectRefusal('REDDIT_BLOCKED', fn () => agent_reddit_search($this->pdo, null, ['subreddit' => 'smallbusiness', 'query' => 'books'], fn () => [403, [], '']));
        $this->assertSame(2, $this->rows("SELECT COUNT(*) FROM agent_actions WHERE action = 'reddit_read' AND status = 'failed'"));
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function reply(array $over = []): array
    {
        return $over + [
            'thread_url' => 'https://www.reddit.com/r/smallbusiness/comments/abc123/which_accounting_software/',
            'thread_title' => 'Which accounting software for a cleaning business?',
            'text' => $this->padded('For three staff and mostly invoices, a spreadsheet stops working once you chase late payers.'),
            'why' => 'Cleaning business choosing software; invoicing and receipts fit.',
            'sources' => ['facts.md: free plan, invoicing'],
        ];
    }

    private function padded(string $text): string
    {
        return $text . ' Full disclosure, I make Argo Books, a desktop app that does this without an account.';
    }

    private function feed(): string
    {
        $entry = fn (string $id, string $title, string $author, string $html) =>
            '<entry><author><name>/u/' . $author . '</name></author>'
            . '<content type="html">' . htmlspecialchars($html, ENT_QUOTES | ENT_XML1) . '</content>'
            . '<link href="https://www.reddit.com/r/smallbusiness/comments/' . $id . '/x/" />'
            . '<published>2026-10-21T17:59:43+00:00</published><title>' . htmlspecialchars($title, ENT_XML1) . '</title></entry>';

        return '<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom">'
            . $entry('abc123', 'Which accounting software for a cleaning business?', 'maria_cleans',
                '<div class="md"><p>We have three staff and I do invoices in Excel.</p></div> submitted by &#32; <a href="x"> /u/maria_cleans </a> &#32; <span><a href="x">[link]</a></span> &#32; <span><a href="x">[comments]</a></span>')
            . $entry('def456', '/u/helper on Which accounting software', 'helper', '<p>Try a free one first.</p>')
            . '</feed>';
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
