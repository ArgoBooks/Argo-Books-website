<?php
declare(strict_types=1);

namespace Tests\Integration\Agent;

use AgentRefused;
use Tests\Helpers\DatabaseTestCase;

require_once PROJECT_ROOT . '/api/agent/actions.php';

/**
 * YouTube replies. The agent finds comments asking for software Argo Books fits and drafts a
 * reply; the site reads the comment from YouTube itself, so the owner sees what is really there.
 */
final class YouTubeReplyTest extends DatabaseTestCase
{
    private array $envBefore;

    /** @var array<int, string> every address the stand-in for YouTube was asked for */
    private array $asked = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->envBefore = $_ENV;
        $_ENV['APP_ENV'] = 'production';
        $_ENV['YOUTUBE_API_KEY'] = 'test-key';
        agent_setting_set($this->pdo, 'enabled', '1');
        agent_setting_set($this->pdo, 'replies_enabled', '1');
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBefore;
        parent::tearDown();
    }

    public function test_without_a_key_it_says_youtube_is_not_set_up(): void
    {
        unset($_ENV['YOUTUBE_API_KEY']);
        $this->expectRefusal('NOT_CONFIGURED', fn () => agent_youtube_search($this->pdo, null, ['query' => 'quickbooks desktop alternative'], $this->youtube()));
        $this->assertSame([], $this->asked);
    }

    public function test_a_search_finds_videos_with_their_views_and_comment_counts(): void
    {
        $found = agent_youtube_search($this->pdo, null, ['query' => 'quickbooks desktop alternative', 'period' => 'month'], $this->youtube());

        $this->assertStringStartsWith('https://www.googleapis.com/youtube/v3/search?', $this->asked[0]);
        $this->assertStringContainsString('key=test-key', $this->asked[0]);
        $this->assertStringContainsString('publishedAfter=', $this->asked[0]);
        $this->assertSame([[
            'video_id' => 'dizuDlq4LC4',
            'title' => "Ditch QuickBooks: Top 2 Alternatives You'll Need",
            'channel' => 'Jamie Trull',
            'published' => '2026-07-01T12:00:00Z',
            'views' => 173821,
            'comments' => 412,
        ]], $found['videos']);
    }

    public function test_comments_come_with_their_replies_and_whether_one_was_drafted(): void
    {
        agent_propose_reply($this->pdo, null, $this->reply(), $this->youtube());

        $comments = agent_youtube_comments($this->pdo, null, ['video_id' => 'dizuDlq4LC4'], $this->youtube())['comments'];

        $this->assertSame('Ugx-offline-question-1', $comments[0]['comment_id']);
        $this->assertSame('what about quickbooks OFFLINE alternatives?', $comments[0]['text']);
        $this->assertTrue($comments[0]['already_drafted']);
        $this->assertSame([['author' => '@someone', 'text' => 'Try Wave.']], $comments[0]['replies']);
        $this->assertFalse($comments[1]['already_drafted']);
    }

    public function test_a_reply_keeps_the_comment_as_youtube_has_it_and_a_link_to_it(): void
    {
        $proposed = agent_propose_reply($this->pdo, null, $this->reply(), $this->youtube());

        $this->assertSame('waiting for the owner to post it', $proposed['status']);
        $p = agent_proposal($this->pdo, $proposed['proposal_id']);
        $this->assertSame('youtube', $p['platform']);
        $this->assertSame('what about quickbooks OFFLINE alternatives?', $p['payload']['comment_text']);
        $this->assertSame('@desktopfan', $p['payload']['comment_author']);
        $this->assertSame('https://www.youtube.com/watch?v=dizuDlq4LC4&lc=Ugx-offline-question-1', $p['payload']['thread_url']);
        $this->assertSame("Ditch QuickBooks: Top 2 Alternatives You'll Need", $p['payload']['thread_title']);
    }

    public function test_a_youtube_reply_follows_the_same_rules_as_a_reddit_one(): void
    {
        $this->expectRefusal('BAD_INPUT', fn () => agent_propose_reply($this->pdo, null, $this->reply([
            'text' => $this->padded('There is a free one at argorobots.com that works offline.'),
        ]), $this->youtube()));

        agent_propose_reply($this->pdo, null, $this->reply(), $this->youtube());
        $this->expectRefusal('DUPLICATE', fn () => agent_propose_reply($this->pdo, null, $this->reply([
            'text' => $this->padded('A second answer to the same comment.'),
        ]), $this->youtube()));
    }

    public function test_replies_are_capped_under_each_video_and_apart_from_reddit(): void
    {
        agent_setting_set($this->pdo, 'replies_per_day', '1');
        agent_propose_reply($this->pdo, null, [
            'thread_url' => 'https://www.reddit.com/r/Bookkeeping/comments/abc123/x/', 'thread_title' => 'Which software?',
            'text' => $this->padded('A Reddit reply that uses up the Reddit allowance.'), 'why' => 'Asked for software.', 'sources' => ['facts.md'],
        ]);

        agent_propose_reply($this->pdo, null, $this->reply(['comment_id' => 'Ugx-offline-question-1']), $this->youtube());
        agent_propose_reply($this->pdo, null, $this->reply(['comment_id' => 'Ugx-roastery-question-2', 'text' => $this->padded('Second under this video.')]), $this->youtube());
        $this->expectRefusal('DAILY_LIMIT', fn () => agent_propose_reply($this->pdo, null, $this->reply([
            'comment_id' => 'Ugx-third-question-33', 'text' => $this->padded('Third under the same video.'),
        ]), $this->youtube()));
    }

    public function test_youtube_refusals_tell_the_agent_what_to_do(): void
    {
        $this->expectRefusal('YOUTUBE_QUOTA', fn () => agent_youtube_search($this->pdo, null, ['query' => 'accounting'], $this->failing(403, 'quotaExceeded')));
        $this->expectRefusal('NOT_FOUND', fn () => agent_youtube_comments($this->pdo, null, ['video_id' => 'dizuDlq4LC4'], $this->failing(403, 'commentsDisabled')));
        $this->expectRefusal('NOT_CONFIGURED', fn () => agent_youtube_search($this->pdo, null, ['query' => 'accounting'], $this->failing(400, 'keyInvalid')));
        $this->assertSame(3, (int) $this->pdo->query("SELECT COUNT(*) FROM agent_actions WHERE action = 'youtube_read' AND status = 'failed'")->fetchColumn());
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** A stand-in for the YouTube Data API that answers search, videos and commentThreads. */
    private function youtube(): callable
    {
        $comment = fn (string $id, string $author, string $text, array $replies = []) => [
            'id' => $id,
            'snippet' => ['videoId' => 'dizuDlq4LC4', 'topLevelComment' => ['snippet' => [
                'authorDisplayName' => $author, 'textOriginal' => $text, 'publishedAt' => '2026-08-01T10:00:00Z', 'likeCount' => 3,
            ]]],
            'replies' => ['comments' => array_map(fn ($r) => ['snippet' => ['authorDisplayName' => $r[0], 'textOriginal' => $r[1]]], $replies)],
        ];
        $comments = [
            'Ugx-offline-question-1' => $comment('Ugx-offline-question-1', '@desktopfan', 'what about quickbooks OFFLINE alternatives?', [['@someone', 'Try Wave.']]),
            'Ugx-roastery-question-2' => $comment('Ugx-roastery-question-2', '@roaster', 'Looking for free accounting software for my small coffee roastery'),
            'Ugx-third-question-33' => $comment('Ugx-third-question-33', '@third', 'Can you transfer QuickBooks Pro Desktop to any of these?'),
        ];

        return function (string $method, string $url) use ($comments) {
            $this->asked[] = $url;
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $path = basename((string) parse_url($url, PHP_URL_PATH));
            $data = match (true) {
                $path === 'search' => ['items' => [['id' => ['videoId' => 'dizuDlq4LC4'], 'snippet' => [
                    'title' => 'Ditch QuickBooks: Top 2 Alternatives You&#39;ll Need', 'channelTitle' => 'Jamie Trull', 'publishedAt' => '2026-07-01T12:00:00Z',
                ]]]],
                $path === 'videos' => ['items' => [['id' => 'dizuDlq4LC4',
                    'snippet' => ['title' => 'Ditch QuickBooks: Top 2 Alternatives You&#39;ll Need', 'channelTitle' => 'Jamie Trull'],
                    'statistics' => ['viewCount' => '173821', 'commentCount' => '412'],
                ]]],
                $path === 'commentThreads' && isset($q['id']) => ['items' => isset($comments[$q['id']]) ? [$comments[$q['id']]] : []],
                $path === 'commentThreads' => ['items' => array_values($comments)],
            };
            return [200, [], json_encode($data)];
        };
    }

    private function failing(int $status, string $reason): callable
    {
        return fn () => [$status, [], json_encode(['error' => ['message' => 'refused', 'errors' => [['reason' => $reason]]]])];
    }

    private function reply(array $over = []): array
    {
        return $over + [
            'platform' => 'youtube',
            'comment_id' => 'Ugx-offline-question-1',
            'text' => $this->padded('If offline matters most, a desktop app that keeps the file on your own computer is the closest thing to Desktop.'),
            'why' => 'Asked for an offline QuickBooks alternative.',
            'sources' => ['facts.md: desktop app, works offline'],
        ];
    }

    private function padded(string $text): string
    {
        return $text . ' Full disclosure, I make Argo Books, which works that way.';
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
