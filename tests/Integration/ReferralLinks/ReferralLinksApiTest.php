<?php
declare(strict_types=1);

namespace Tests\Integration\ReferralLinks;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\DatabaseTestCase;

require_once PROJECT_ROOT . '/referral_links.php';

/**
 * The owner's referral links API: what it adds, what it refuses, and that running a script twice
 * changes nothing.
 */
final class ReferralLinksApiTest extends DatabaseTestCase
{
    private function add(array $overrides = []): array
    {
        return referral_api_add($this->pdo, $overrides + ['source_code' => 'test-api-link', 'name' => 'Test link', 'category' => 'social']);
    }

    private function saved(string $code): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM referral_links WHERE source_code = ?');
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function test_adding_a_link_saves_it_and_gives_back_the_address_to_share(): void
    {
        $result = $this->add(['target_url' => 'https://argorobots.com/downloads/']);

        $this->assertSame('created', $result['status']);
        $this->assertSame('https://argorobots.com/downloads/?source=test-api-link', $result['url']);
        $saved = $this->saved('test-api-link');
        $this->assertSame('Test link', $saved['name']);
        $this->assertSame('social', $saved['category']);
    }

    public function test_asking_for_the_same_code_twice_leaves_the_first_link_alone(): void
    {
        $this->add(['name' => 'First', 'target_url' => 'https://argorobots.com/downloads/']);

        $again = $this->add(['name' => 'Second', 'target_url' => 'https://argorobots.com/pricing/']);

        $this->assertSame('exists', $again['status']);
        $this->assertSame('https://argorobots.com/downloads/?source=test-api-link', $again['url'], 'the address given back is the one saved');
        $this->assertSame('First', $this->saved('test-api-link')['name']);
    }

    public function test_the_category_is_worked_out_from_the_code_when_none_is_given(): void
    {
        referral_api_add($this->pdo, ['source_code' => 'youtube-test-api', 'name' => 'A video']);

        $this->assertSame('youtube', $this->saved('youtube-test-api')['category']);
    }

    #[DataProvider('badLinks')]
    public function test_a_bad_link_is_refused_and_nothing_is_saved(array $bad): void
    {
        try {
            $this->add($bad);
            $this->fail('the link was accepted');
        } catch (InvalidArgumentException) {
            $this->assertFalse($this->saved((string) ($bad['source_code'] ?? 'test-api-link')));
        }
    }

    public static function badLinks(): array
    {
        return [
            'a code that could never record a visit' => [['source_code' => 'has space']],
            'a code too long for the table' => [['source_code' => str_repeat('a', 101)]],
            'no name' => [['name' => '  ']],
            'an unknown category' => [['category' => 'nonsense']],
            'another site' => [['target_url' => 'https://example.com/']],
            'a site that only looks like ours' => [['target_url' => 'https://argorobots.com.example.com/']],
            'not https' => [['target_url' => 'http://argorobots.com/']],
        ];
    }

    public function test_the_list_counts_installs_from_this_environment_only(): void
    {
        $this->add();
        $add = $this->pdo->prepare("INSERT INTO referral_events (source_code, event_type, environment) VALUES ('test-api-link', 'app_first_run', ?)");
        $add->execute([current_environment()]);
        $add->execute([current_environment() === 'production' ? 'sandbox' : 'production']);

        $links = array_column(referral_api_list($this->pdo), null, 'source_code');

        $this->assertSame(1, (int) $links['test-api-link']['installs']);
    }
}
