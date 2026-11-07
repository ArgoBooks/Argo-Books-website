<?php
declare(strict_types=1);

namespace Tests\Integration\Portal;

use Tests\Helpers\DatabaseTestCase;

/** stripe_payment_belongs_to_portal_invoice(), which stops one connected Stripe account crediting another company's invoice through payment metadata. */
final class StripePaymentAccountTest extends DatabaseTestCase
{
    private function companyOnStripe(string $account): int
    {
        $companyId = $this->seedPortalCompany();
        $this->pdo->prepare('UPDATE portal_companies SET stripe_account_id = ? WHERE id = ?')->execute([$account, $companyId]);
        return $companyId;
    }

    public function test_a_payment_from_the_companys_own_account_is_accepted(): void
    {
        $companyId = $this->companyOnStripe('acct_owner');
        $this->seedPortalInvoice($companyId, 'INV-ACCT-1', 50.00);

        $this->assertTrue(stripe_payment_belongs_to_portal_invoice($this->pdo, $companyId, 'INV-ACCT-1', 'acct_owner', 'sandbox'));
    }

    public function test_a_payment_from_another_connected_account_is_refused(): void
    {
        $victim = $this->companyOnStripe('acct_victim');
        $this->seedPortalInvoice($victim, 'INV-001', 50.00);
        $this->companyOnStripe('acct_attacker');

        $this->assertFalse(stripe_payment_belongs_to_portal_invoice($this->pdo, $victim, 'INV-001', 'acct_attacker', 'sandbox'));
    }

    public function test_a_payment_with_no_connected_account_is_refused(): void
    {
        $companyId = $this->companyOnStripe('acct_owner2');
        $this->seedPortalInvoice($companyId, 'INV-ACCT-2', 50.00);

        $this->assertFalse(stripe_payment_belongs_to_portal_invoice($this->pdo, $companyId, 'INV-ACCT-2', null, 'sandbox'));
        $this->assertFalse(stripe_payment_belongs_to_portal_invoice($this->pdo, $companyId, 'INV-ACCT-2', '', 'sandbox'));
    }

    public function test_an_invoice_the_company_does_not_have_is_refused(): void
    {
        $companyId = $this->companyOnStripe('acct_owner3');

        $this->assertFalse(stripe_payment_belongs_to_portal_invoice($this->pdo, $companyId, 'INV-MISSING', 'acct_owner3', 'sandbox'));
    }

    public function test_a_payment_in_the_other_environment_is_refused(): void
    {
        // Production and sandbox share one database, and the seeded rows are sandbox.
        $companyId = $this->companyOnStripe('acct_owner4');
        $this->seedPortalInvoice($companyId, 'INV-ACCT-4', 50.00);

        $this->assertFalse(stripe_payment_belongs_to_portal_invoice($this->pdo, $companyId, 'INV-ACCT-4', 'acct_owner4', 'production'));
    }
}
