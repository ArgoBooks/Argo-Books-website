<?php
declare(strict_types=1);

namespace Tests\Integration\Portal;

use Tests\Helpers\DatabaseTestCase;

/**
 * The customer's answer is the one thing on a quote that cannot be taken back, so it has to be
 * recorded once and only once, and only while the quote is open.
 */
final class QuoteResponseTest extends DatabaseTestCase
{
    public function test_accepting_records_the_answer_and_flags_it_for_the_app(): void
    {
        $companyId = $this->seedPortalCompany();
        $seeded = $this->seedPortalQuote($companyId, 'QUO-2026-00001');
        $quote = get_quote_by_token($seeded['token']);

        $this->assertTrue(quote_record_response($quote, 'accepted', 'Looks good'));

        $row = $this->quoteRow($seeded['id']);
        $this->assertSame('accepted', $row['status']);
        $this->assertSame('Looks good', $row['response_note']);
        $this->assertNotNull($row['responded_at']);
        $this->assertSame(0, (int) $row['synced_to_argo']);
    }

    public function test_second_answer_is_ignored(): void
    {
        $companyId = $this->seedPortalCompany();
        $seeded = $this->seedPortalQuote($companyId, 'QUO-2026-00002');
        $quote = get_quote_by_token($seeded['token']);

        $this->assertTrue(quote_record_response($quote, 'accepted'));
        $this->assertFalse(quote_record_response($quote, 'declined'));
        $this->assertSame('accepted', $this->quoteRow($seeded['id'])['status']);
    }

    public function test_expired_and_cancelled_quotes_cannot_be_answered(): void
    {
        $companyId = $this->seedPortalCompany();

        $expired = $this->seedPortalQuote($companyId, 'QUO-2026-00003', 100.00, 'sent', date('Y-m-d', strtotime('-1 day')));
        $this->assertFalse(quote_can_respond(get_quote_by_token($expired['token'])));

        $cancelled = $this->seedPortalQuote($companyId, 'QUO-2026-00004', 100.00, 'cancelled');
        $this->assertFalse(quote_can_respond(get_quote_by_token($cancelled['token'])));
    }

    public function test_a_quote_valid_until_today_can_still_be_answered(): void
    {
        $companyId = $this->seedPortalCompany();
        $seeded = $this->seedPortalQuote($companyId, 'QUO-2026-00005', 100.00, 'sent', date('Y-m-d'));

        $this->assertTrue(quote_can_respond(get_quote_by_token($seeded['token'])));
    }

    /** @return array<string, mixed> */
    private function quoteRow(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM portal_quotes WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch();
    }
}
