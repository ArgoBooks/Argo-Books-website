<?php
declare(strict_types=1);

namespace Tests\Integration\Sync;

use Tests\Helpers\DatabaseTestCase;

require_once __DIR__ . '/../../../api/sync/sync-helper.php';

final class SyncHelperTest extends DatabaseTestCase
{
    public function test_create_and_consume_pairing_token_round_trip(): void
    {
        $pairing = create_pairing_token('owner-hash-1', 'company-uid-1', 'Acme Co');
        $token = $pairing['token'];
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTVWXYZ]{8}$/', $pairing['short_code']);

        $consumed = consume_pairing_token($token);
        $this->assertSame('owner-hash-1', $consumed['owner_identity_hash']);
        $this->assertSame('company-uid-1', $consumed['company_uid']);
        $this->assertSame('Acme Co', $consumed['company_label']);
    }

    /**
     * A free desktop that enters a license key keeps its phone and the receipts
     * the phone has already sent, and nobody else's rows are touched.
     */
    public function test_entering_a_license_keeps_the_paired_phone_and_its_queue(): void
    {
        $add = function (string $owner, string $company): void {
            $this->pdo->prepare(
                'INSERT INTO mobile_sync_devices (device_token_hash, owner_identity_hash, company_uid, device_label) VALUES (?,?,?,?)'
            )->execute([hash('sha256', bin2hex(random_bytes(8))), $owner, $company, 'Phone']);
            $this->pdo->prepare(
                'INSERT INTO mobile_sync_queue (company_uid, owner_identity_hash, from_device_id, ciphertext) VALUES (?,?,?,?)'
            )->execute([$company, $owner, (int) $this->pdo->lastInsertId(), 'ITEM']);
        };
        $add('device-hash', 'company-1');
        $add('someone-else', 'company-2');

        sync_adopt_device_identity('device-hash', 'license-hash');

        $owners = fn (string $table) => $this->pdo
            ->query("SELECT owner_identity_hash FROM $table ORDER BY company_uid")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['license-hash', 'someone-else'], $owners('mobile_sync_devices'));
        $this->assertSame(['license-hash', 'someone-else'], $owners('mobile_sync_queue'));
    }

    public function test_pairing_token_is_single_use(): void
    {
        $pairing = create_pairing_token('owner-hash-1', 'company-uid-1', 'Acme Co');
        $token = $pairing['token'];
        consume_pairing_token($token);
        $this->assertNull(consume_pairing_token($token));
    }

    public function test_expired_pairing_token_is_rejected(): void
    {
        $token = bin2hex(random_bytes(16));
        $this->pdo->prepare(
            "INSERT INTO mobile_sync_pairings (pairing_token, owner_identity_hash, company_uid, company_label, expires_at)
             VALUES (?, 'o', 'c', 'L', DATE_SUB(NOW(), INTERVAL 1 MINUTE))"
        )->execute([$token]);
        $this->assertNull(consume_pairing_token($token));
    }

    public function test_authenticate_sync_device_returns_binding_and_updates_last_seen(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->pdo->prepare(
            "INSERT INTO mobile_sync_devices (device_token_hash, owner_identity_hash, company_uid, device_label)
             VALUES (?, 'owner-hash-1', 'company-uid-1', 'Pixel 8')"
        )->execute([hash('sha256', $token)]);

        $_SERVER['HTTP_X_SYNC_DEVICE_TOKEN'] = $token;
        $result = authenticate_sync_device();
        unset($_SERVER['HTTP_X_SYNC_DEVICE_TOKEN']);

        $this->assertSame('company-uid-1', $result['company_uid']);
        $this->assertSame('owner-hash-1', $result['owner_identity_hash']);
        $this->assertIsInt($result['device_id']);
    }

    public function test_authenticate_sync_device_returns_null_without_token(): void
    {
        unset($_SERVER['HTTP_X_SYNC_DEVICE_TOKEN']);
        $this->assertNull(authenticate_sync_device());
    }
}
