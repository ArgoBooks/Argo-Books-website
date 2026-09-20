<?php
declare(strict_types=1);

namespace Tests\Unit\Portal;

use PHPUnit\Framework\TestCase;

/**
 * Re-publishing a quote must never lose the customer's answer. Losing it would show the business
 * a quote still waiting while the customer believes they accepted it.
 */
final class QuotePublishStatusTest extends TestCase
{
    public function test_new_quote_is_sent(): void
    {
        $this->assertSame(
            ['status' => 'sent', 'clearResponse' => true],
            quote_publish_status(null, 'sent')
        );
    }

    public function test_an_answer_survives_a_resend(): void
    {
        foreach ([0, 1] as $syncedToArgo) {
            $existing = ['status' => 'accepted', 'synced_to_argo' => $syncedToArgo];

            $this->assertSame(
                ['status' => 'accepted', 'clearResponse' => false],
                quote_publish_status($existing, 'sent'),
                "synced_to_argo = {$syncedToArgo}"
            );
        }
    }

    public function test_only_a_revision_reopens_an_answered_quote(): void
    {
        $existing = ['status' => 'declined', 'synced_to_argo' => 1];

        $this->assertSame(
            ['status' => 'sent', 'clearResponse' => true],
            quote_publish_status($existing, 'sent', true)
        );
    }

    public function test_an_unanswered_quote_is_just_resent(): void
    {
        $existing = ['status' => 'sent', 'synced_to_argo' => 1];

        $this->assertSame(
            ['status' => 'sent', 'clearResponse' => true],
            quote_publish_status($existing, 'sent')
        );
    }

    public function test_cancelling_always_wins(): void
    {
        $existing = ['status' => 'accepted', 'synced_to_argo' => 0];

        $this->assertSame(
            ['status' => 'cancelled', 'clearResponse' => false],
            quote_publish_status($existing, 'cancelled')
        );
    }
}
