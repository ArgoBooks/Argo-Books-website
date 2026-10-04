<?php
declare(strict_types=1);

namespace Tests\Integration\PublicApi;

/**
 * Authentication, scopes, and the boundary between accounts.
 *
 * An account is one Argo Books company, and it is the only isolation the API
 * has: a query that forgets its account filter shows one company's objects to
 * another.
 */
final class AuthAndScopingTest extends ApiIntegrationTestCase
{
    public function testValidKeyResolvesToItsAccount(): void
    {
        $auth = $this->authenticateAs($this->writeKey);

        $this->assertSame($this->accountId, $auth['account_id']);
        $this->assertSame($this->accountPublicId, $auth['account_public_id']);
        $this->assertSame(['read', 'write'], $auth['key_scopes']);
    }

    public function testXApiKeyHeaderWorksToo(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = $this->writeKey;

        $this->assertSame($this->accountId, api_authenticate()['account_id']);
    }

    public function testMissingKeyIs401(): void
    {
        [$status, $payload] = $this->capture(fn () => api_authenticate());

        $this->assertSame(401, $status);
        $this->assertSame('missing_api_key', $payload['error']['code']);
    }

    public function testUnknownButWellFormedKeyIs401(): void
    {
        [$status, $payload] = $this->capture(fn () => $this->authenticateAs(api_generate_secret_key()));

        $this->assertSame(401, $status);
        $this->assertSame('invalid_api_key', $payload['error']['code']);
    }

    public function testRevokedKeyIs401(): void
    {
        $this->pdo->prepare('UPDATE api_keys SET revoked_at = NOW() WHERE account_id = ?')
            ->execute([$this->accountId]);

        [$status, $payload] = $this->capture(fn () => $this->authenticateAs($this->writeKey));

        $this->assertSame(401, $status);
        $this->assertSame('api_key_revoked', $payload['error']['code']);
    }

    public function testInactiveAccountIs403(): void
    {
        $this->pdo->prepare('UPDATE api_accounts SET is_active = 0 WHERE id = ?')->execute([$this->accountId]);

        [$status, $payload] = $this->capture(fn () => $this->authenticateAs($this->writeKey));

        $this->assertSame(403, $status);
        $this->assertSame('account_inactive', $payload['error']['code']);
    }

    /**
     * The whole point of storing only a hash: the row cannot be turned back
     * into a working credential.
     */
    public function testOnlyTheHashIsStored(): void
    {
        $stmt = $this->pdo->prepare('SELECT key_hash, key_hint FROM api_keys WHERE account_id = ? LIMIT 1');
        $stmt->execute([$this->accountId]);
        $row = $stmt->fetch();

        $this->assertNotSame($this->writeKey, $row['key_hash']);
        $this->assertStringNotContainsString(substr($this->writeKey, 3, 20), (string) $row['key_hint']);
    }

    // -- scopes --------------------------------------------------------------

    public function testWriteScopeIsRefusedToAReadOnlyKey(): void
    {
        $auth = $this->authenticateAs($this->readKey);

        [$status, $payload] = $this->capture(fn () => api_require_scope($auth, 'write'));

        $this->assertSame(403, $status);
        $this->assertSame('insufficient_scope', $payload['error']['code']);
    }

    public function testReadScopeIsAllowedToAReadOnlyKey(): void
    {
        $auth = $this->authenticateAs($this->readKey);
        api_require_scope($auth, 'read');

        $this->assertTrue(true); // no exception is the assertion
    }

    // -- accounts ------------------------------------------------------------

    public function testListsDoNotLeakAcrossAccounts(): void
    {
        [$otherAccountId] = $this->makeAccount();
        try {
            $mine = $this->seedObject('api_customers', $this->accountId, [
                'public_id' => api_generate_id('cus'), 'name' => 'Mine',
            ]);
            $this->seedObject('api_customers', $otherAccountId, [
                'public_id' => api_generate_id('cus'), 'name' => 'Someone Else',
            ]);

            $auth = $this->authenticateAs($this->writeKey);
            $spec = api_resource_definitions()['customers'];

            [$status, $payload] = $this->capture(fn () => api_handle_list($spec, $auth, 'customers'));

            $this->assertSame(200, $status);
            $this->assertSame([$mine], array_column($payload['data'], 'id'));
        } finally {
            $this->pdo->prepare('DELETE FROM api_accounts WHERE id = ?')->execute([$otherAccountId]);
        }
    }

    public function testRetrieveDoesNotReachAcrossAccounts(): void
    {
        [$otherAccountId] = $this->makeAccount();
        try {
            $theirs = $this->seedObject('api_customers', $otherAccountId, [
                'public_id' => api_generate_id('cus'), 'name' => 'Someone Else',
            ]);

            $auth = $this->authenticateAs($this->writeKey);
            $spec = api_resource_definitions()['customers'];

            [$status, $payload] = $this->capture(fn () => api_handle_retrieve($spec, $auth, $theirs));
            $this->assertSame(404, $status);
            $this->assertSame('resource_missing', $payload['error']['code']);
        } finally {
            $this->pdo->prepare('DELETE FROM api_accounts WHERE id = ?')->execute([$otherAccountId]);
        }
    }
}
