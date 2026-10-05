<?php
declare(strict_types=1);

namespace Tests\Integration\Cron;

use Tests\Helpers\DatabaseTestCase;

/**
 * Which overdue invoices get a reminder, and that nobody is sent the same one twice.
 *
 * These emails go to a merchant's customers, from a database that production and sandbox
 * share. The mistakes that matter are chasing someone who has paid, chasing from a test
 * invoice, and sending the same reminder again.
 */
final class InvoiceReminderTest extends DatabaseTestCase
{
    /** A company that turned reminders on two months ago. */
    private function companyWithReminders(array $overrides = []): int
    {
        $companyId = $this->seedPortalCompany();
        $columns = array_merge([
            'reminders_enabled'    => 1,
            'reminders_enabled_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            'locked'               => 0,
        ], $overrides);

        $set = implode(', ', array_map(static fn (string $c) => "$c = ?", array_keys($columns)));
        $this->pdo->prepare("UPDATE portal_companies SET $set WHERE id = ?")
            ->execute([...array_values($columns), $companyId]);

        return $companyId;
    }

    /** An unpaid invoice that fell due the given number of days ago. Returns its row id. */
    private function invoice(int $companyId, string $invoiceId, int $daysOverdue, array $overrides = []): int
    {
        $this->seedPortalInvoice($companyId, $invoiceId, 200.00);
        $columns = array_merge([
            'due_date'       => date('Y-m-d', strtotime("-$daysOverdue days")),
            'customer_email' => 'customer@example.test',
        ], $overrides);

        $set = implode(', ', array_map(static fn (string $c) => "$c = ?", array_keys($columns)));
        $this->pdo->prepare("UPDATE portal_invoices SET $set WHERE company_id = ? AND invoice_id = ?")
            ->execute([...array_values($columns), $companyId, $invoiceId]);

        $stmt = $this->pdo->prepare('SELECT id FROM portal_invoices WHERE company_id = ? AND invoice_id = ?');
        $stmt->execute([$companyId, $invoiceId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<string> The invoice ids a run would consider, for the given companies. */
    private function candidateIds(int ...$companyIds): array
    {
        $ids = [];
        foreach (portal_reminder_candidates($this->pdo, current_environment()) as $row) {
            if (in_array((int) $row['company_id'], $companyIds, true)) {
                $ids[] = $row['invoice_id'];
            }
        }
        sort($ids);
        return $ids;
    }

    public function test_only_an_unpaid_overdue_invoice_in_this_environment_is_chased(): void
    {
        $company = $this->companyWithReminders();
        $otherEnvironment = current_environment() === 'production' ? 'sandbox' : 'production';

        $this->invoice($company, 'INV-CHASE', 5);
        $this->invoice($company, 'INV-PAID', 5, ['status' => 'paid', 'balance_due' => 0]);
        $this->invoice($company, 'INV-CANCELLED', 5, ['status' => 'cancelled']);
        $this->invoice($company, 'INV-OTHER-ENV', 5, ['environment' => $otherEnvironment]);
        $this->invoice($company, 'INV-NOT-YET', 2);
        $this->invoice($company, 'INV-TOO-OLD', 60);
        $this->invoice($company, 'INV-PENNIES', 5, ['balance_due' => 0.40]);
        $this->invoice($company, 'INV-NO-EMAIL', 5, ['customer_email' => '']);

        $this->assertSame(['INV-CHASE'], $this->candidateIds($company));
    }

    public function test_a_company_that_is_off_locked_or_only_just_opted_in_chases_nobody(): void
    {
        $off = $this->companyWithReminders(['reminders_enabled' => 0]);
        $locked = $this->companyWithReminders(['locked' => 1]);
        // Turning reminders on must not release a backlog: this invoice fell due before it did.
        $justOptedIn = $this->companyWithReminders(['reminders_enabled_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]);

        $this->invoice($off, 'INV-OFF', 5);
        $this->invoice($locked, 'INV-LOCKED', 5);
        $this->invoice($justOptedIn, 'INV-BACKLOG', 5);

        $this->assertSame([], $this->candidateIds($off, $locked, $justOptedIn));
    }

    public function test_the_stage_follows_how_overdue_the_invoice_is(): void
    {
        $this->assertNull(portal_reminder_stage_for(2));
        $this->assertSame(1, portal_reminder_stage_for(3));
        $this->assertSame(1, portal_reminder_stage_for(6));
        $this->assertSame(2, portal_reminder_stage_for(7));
        $this->assertSame(3, portal_reminder_stage_for(14));
        // A cron that missed a fortnight sends the one reminder it has reached, not all three.
        $this->assertSame(3, portal_reminder_stage_for(40));
    }

    public function test_a_stage_already_sent_is_not_sent_again_and_stages_never_go_back(): void
    {
        $company = $this->companyWithReminders();
        $invoice = $this->invoice($company, 'INV-STAGES', 8);
        $this->sentReminder($invoice, $company, stage: 2, sentAt: '-5 days');

        $this->assertNull(portal_reminder_stage_due($this->pdo, $invoice, 8), 'stage 2 has gone out');
        // The due date was moved later after the reminder went out, so it now looks like stage 1.
        $this->assertNull(portal_reminder_stage_due($this->pdo, $invoice, 4), 'stage 1 is behind what was sent');
        $this->assertSame(3, portal_reminder_stage_due($this->pdo, $invoice, 14));
    }

    public function test_two_reminders_are_never_sent_within_two_days_of_each_other(): void
    {
        $company = $this->companyWithReminders();
        $invoice = $this->invoice($company, 'INV-GAP', 7);
        $this->sentReminder($invoice, $company, stage: 1, sentAt: '-1 day');

        $this->assertNull(portal_reminder_stage_due($this->pdo, $invoice, 7));
        $this->assertSame(2, portal_reminder_stage_due($this->pdo, $invoice, 7, strtotime('+2 days')));
    }

    public function test_only_one_run_can_claim_a_stage(): void
    {
        $company = $this->companyWithReminders();
        $this->invoice($company, 'INV-CLAIM', 5);
        $candidate = array_values(array_filter(
            portal_reminder_candidates($this->pdo, current_environment()),
            static fn (array $row) => (int) $row['company_id'] === $company
        ))[0];

        $this->assertNotNull(portal_reminder_claim($this->pdo, $candidate, 1));
        $this->assertNull(portal_reminder_claim($this->pdo, $candidate, 1), 'a second run claimed the same reminder');
    }

    public function test_a_reminder_is_held_back_when_the_invoice_was_paid_or_the_customer_opted_out(): void
    {
        $company = $this->companyWithReminders();
        $paid = $this->invoice($company, 'INV-JUST-PAID', 5);
        $optedOut = $this->invoice($company, 'INV-OPTED-OUT', 5, ['customer_email' => 'Stop@Example.test']);
        $fine = $this->invoice($company, 'INV-FINE', 5);

        // Paid in the moment between the list being read and the email going out.
        $this->pdo->prepare("UPDATE portal_invoices SET status = 'paid', balance_due = 0 WHERE id = ?")->execute([$paid]);
        $this->pdo->prepare("INSERT INTO email_suppressions (email, context) VALUES ('stop@example.test', 'portal')")->execute();

        $this->assertSame('paid', portal_reminder_halt_reason($this->pdo, $paid)[0]);
        $this->assertSame('suppressed', portal_reminder_halt_reason($this->pdo, $optedOut)[0]);
        $this->assertNull(portal_reminder_halt_reason($this->pdo, $fine)[0]);
    }

    private function sentReminder(int $invoiceRowId, int $companyId, int $stage, string $sentAt): void
    {
        $this->pdo->prepare(
            "INSERT INTO portal_invoice_reminders (portal_invoice_id, company_id, stage, status, sent_at)
             VALUES (?, ?, ?, 'sent', ?)"
        )->execute([$invoiceRowId, $companyId, $stage, date('Y-m-d H:i:s', strtotime($sentAt))]);
    }
}
