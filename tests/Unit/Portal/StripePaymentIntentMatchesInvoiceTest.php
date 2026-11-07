<?php
declare(strict_types=1);

namespace Tests\Unit\Portal;

use PHPUnit\Framework\TestCase;

/** stripe_payment_intent_matches_invoice(), which stops a payment made for one invoice being posted against another. */
final class StripePaymentIntentMatchesInvoiceTest extends TestCase
{
    private const INVOICE = ['invoice_id' => 'INV-002', 'company_id' => 12, 'currency' => 'CAD'];

    public function test_the_payment_checkout_made_for_this_invoice_matches(): void
    {
        // Stripe hands metadata back as strings and the currency in lower case.
        $this->assertTrue(stripe_payment_intent_matches_invoice(['portal_invoice_id' => 'INV-002', 'portal_company_id' => '12'], 'cad', self::INVOICE));
    }

    public function test_a_payment_for_another_invoice_does_not_match(): void
    {
        $this->assertFalse(stripe_payment_intent_matches_invoice(['portal_invoice_id' => 'INV-001', 'portal_company_id' => '12'], 'cad', self::INVOICE));
    }

    public function test_a_payment_for_another_company_does_not_match(): void
    {
        $this->assertFalse(stripe_payment_intent_matches_invoice(['portal_invoice_id' => 'INV-002', 'portal_company_id' => '13'], 'cad', self::INVOICE));
    }

    public function test_a_payment_in_another_currency_does_not_match(): void
    {
        $this->assertFalse(stripe_payment_intent_matches_invoice(['portal_invoice_id' => 'INV-002', 'portal_company_id' => '12'], 'usd', self::INVOICE));
    }

    public function test_a_payment_without_portal_metadata_does_not_match(): void
    {
        $this->assertFalse(stripe_payment_intent_matches_invoice([], 'cad', self::INVOICE));
    }

    public function test_an_invoice_with_no_currency_is_treated_as_usd_like_checkout(): void
    {
        $invoice = ['invoice_id' => 'INV-003', 'company_id' => 12, 'currency' => ''];
        $this->assertTrue(stripe_payment_intent_matches_invoice(['portal_invoice_id' => 'INV-003', 'portal_company_id' => '12'], 'usd', $invoice));
    }
}
