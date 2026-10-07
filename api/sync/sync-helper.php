<?php
/**
 * Shared helpers for the mobile sync endpoints (api/sync/*).
 *
 * Auth model:
 *  - Desktop ("owner") authenticates with its existing license key (premium) or
 *    device id (free), via portal-helper's authenticate_license_request /
 *    authenticate_device_request. resolve_owner_identity() returns the owner hash.
 *  - Phone authenticates with a long-lived device token (X-Sync-Device-Token),
 *    issued at pairing, via authenticate_sync_device().
 *
 * The AES key and all plaintext live only on the paired devices; this server
 * only moves opaque ciphertext.
 */

require_once __DIR__ . '/../portal/portal-helper.php';

/**
 * Resolve the desktop "owner" identity hash from the request, or null.
 * Premium: sha256 license-key hash. Free: sha256 device-id hash.
 */
function resolve_owner_identity(): ?string
{
    $deviceHash = authenticate_device_request();
    $license = authenticate_license_request();
    if ($license) {
        if ($deviceHash) {
            sync_adopt_device_identity($deviceHash, $license['license_key_hash']);
        }
        return $license['license_key_hash'];
    }
    return $deviceHash ?: null;
}

/**
 * Carry a desktop's paired phones over when it starts sending a license key.
 *
 * A free desktop is known by its device id and a Premium one by its license key,
 * so entering a key changed who the desktop was. Its phones stayed under the old
 * identity, sending receipts to a queue the desktop does not read, and
 * the desktop listed no phones at all. The desktop sends both headers, so the
 * rows filed under its device id are moved to its license here.
 *
 * The snapshot is dropped, not moved. The desktop uploads a new one on every
 * sync, and moving it could collide with one already stored under the license.
 */
function sync_adopt_device_identity(string $deviceHash, string $licenseHash): void
{
    global $pdo;

    if ($deviceHash === $licenseHash) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT DISTINCT company_uid FROM mobile_sync_devices WHERE owner_identity_hash = ?');
        $stmt->execute([$deviceHash]);
        $companies = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$companies) {
            return;
        }

        // The phones go last. They are what the lookup above finds, so a run cut
        // short part way is picked up again by the next request.
        foreach ($companies as $companyUid) {
            $pdo->prepare('UPDATE mobile_sync_queue SET owner_identity_hash = ? WHERE company_uid = ? AND owner_identity_hash = ?')
                ->execute([$licenseHash, $companyUid, $deviceHash]);
            $pdo->prepare('DELETE FROM mobile_sync_snapshots WHERE company_uid = ? AND owner_identity_hash = ?')
                ->execute([$companyUid, $deviceHash]);
        }
        $pdo->prepare('UPDATE mobile_sync_devices SET owner_identity_hash = ? WHERE owner_identity_hash = ?')
            ->execute([$licenseHash, $deviceHash]);
    } catch (PDOException $e) {
        error_log('sync: could not move paired phones to the license identity: ' . $e->getMessage());
    }
}

/**
 * Authenticate a phone by its X-Sync-Device-Token header.
 * Returns ['device_id','company_uid','owner_identity_hash'] and bumps last_seen_at, or null.
 */
function authenticate_sync_device(): ?array
{
    global $pdo;
    $token = $_SERVER['HTTP_X_SYNC_DEVICE_TOKEN'] ?? '';
    if ($token === '') {
        return null;
    }
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT id, company_uid, owner_identity_hash FROM mobile_sync_devices WHERE device_token_hash = ? LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $pdo->prepare('UPDATE mobile_sync_devices SET last_seen_at = NOW() WHERE id = ?')->execute([$row['id']]);
    return [
        'device_id' => (int) $row['id'],
        'company_uid' => $row['company_uid'],
        'owner_identity_hash' => $row['owner_identity_hash'],
    ];
}

/**
 * Generate an 8-char short code from an alphabet that excludes ambiguous
 * characters (no 0 1 I L O U), for the phone to type in manually.
 */
function generate_pairing_short_code(): string
{
    $alphabet = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
    $max = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

/**
 * Create a single-use, 10-minute pairing token for (owner, company). Opportunistically
 * GCs expired tokens. Also mints a unique short code for the manual-entry pairing flow.
 * Returns ['token' => bin2hex(random_bytes(16)), 'short_code' => string].
 */
function create_pairing_token(string $ownerHash, string $companyUid, string $companyLabel): array
{
    global $pdo;
    $pdo->prepare('DELETE FROM mobile_sync_pairings WHERE expires_at < NOW()')->execute();

    $token = bin2hex(random_bytes(16));
    $insert = $pdo->prepare(
        "INSERT INTO mobile_sync_pairings
            (pairing_token, short_code, owner_identity_hash, company_uid, company_label, status, expires_at)
         VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
    );

    $attempts = 0;
    while (true) {
        $code = generate_pairing_short_code();
        try {
            $insert->execute([$token, $code, $ownerHash, $companyUid, $companyLabel]);
            break;
        } catch (\PDOException $e) {
            $attempts++;
            // MySQL duplicate-entry error code is 23000; retry with a fresh code
            // a few times in case of a short_code collision, then give up.
            if ($e->getCode() !== '23000' || $attempts >= 5) {
                throw $e;
            }
        }
    }

    return ['token' => $token, 'short_code' => $code];
}

/**
 * Validate + consume (single use) a pairing token. Returns the binding or null.
 *
 * Mutually exclusive with the short-code claim path: the DELETE's
 * `status = 'pending'` guard means a pairing already claimed via
 * claim_pairing_code() (status='claimed') cannot be redeemed here, and
 * rowCount() === 1 is the single source of truth for "this call won the
 * redeem," mirroring claim_pairing_code()'s atomic UPDATE guard.
 */
function consume_pairing_token(string $token): ?array
{
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT owner_identity_hash, company_uid, company_label
         FROM mobile_sync_pairings WHERE pairing_token = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $del = $pdo->prepare(
        "DELETE FROM mobile_sync_pairings WHERE pairing_token = ? AND status = 'pending' AND expires_at > NOW()"
    );
    $del->execute([$token]);
    if ($del->rowCount() !== 1) {
        return null;
    }
    return $row;
}

/**
 * Normalize a phone-typed short code: uppercase, then strip anything outside
 * the pairing alphabet (23456789ABCDEFGHJKMNPQRSTVWXYZ) so stray dashes,
 * spaces, or a mistaken lowercase entry still match.
 */
function normalize_pairing_short_code(string $code): string
{
    return preg_replace('/[^23456789ABCDEFGHJKMNPQRSTVWXYZ]/', '', strtoupper($code)) ?? '';
}

/**
 * Claim a pending pairing by its manually-typed short code: the phone uploads
 * its public key and picks up a device token.
 *
 * Atomic against double-claims and TOCTOU races: the UPDATE's
 * `status = 'pending' AND expires_at > NOW()` guard only ever matches one
 * concurrent caller, so rowCount() === 1 is the single source of truth for
 * "this call won the claim."
 *
 * Returns ['company_uid', 'company_label', 'device_token'] on success, or
 * null if the code doesn't resolve to a pending, unexpired row. Unknown,
 * expired, and already-claimed codes all collapse to the same null so
 * callers can surface one generic error without leaking which codes exist.
 */
function claim_pairing_code(string $rawCode, string $phonePublicKey, string $deviceLabel): ?array
{
    global $pdo;
    $code = normalize_pairing_short_code($rawCode);
    if ($code === '') {
        return null;
    }

    $update = $pdo->prepare(
        "UPDATE mobile_sync_pairings
         SET status = 'claimed', phone_public_key = ?, device_label = ?, claimed_at = NOW()
         WHERE short_code = ? AND status = 'pending' AND expires_at > NOW()"
    );
    $update->execute([$phonePublicKey, $deviceLabel, $code]);
    if ($update->rowCount() !== 1) {
        return null;
    }

    $select = $pdo->prepare(
        'SELECT owner_identity_hash, company_uid, company_label FROM mobile_sync_pairings WHERE short_code = ? LIMIT 1'
    );
    $select->execute([$code]);
    $row = $select->fetch();
    if (!$row) {
        // Shouldn't happen: the UPDATE above just matched this exact row.
        return null;
    }

    $deviceToken = bin2hex(random_bytes(32));
    $deviceTokenHash = hash('sha256', $deviceToken);

    // The device row and the pairing's token hash have to land together, or a throw strands a claimed row that is unusable and unclaimable.
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->prepare(
            'INSERT INTO mobile_sync_devices (device_token_hash, owner_identity_hash, company_uid, device_label, last_seen_at)
             VALUES (?, ?, ?, ?, NOW())'
        )->execute([$deviceTokenHash, $row['owner_identity_hash'], $row['company_uid'], $deviceLabel]);

        $pdo->prepare('UPDATE mobile_sync_pairings SET device_token_hash = ? WHERE short_code = ?')
            ->execute([$deviceTokenHash, $code]);

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (\Throwable $e) {
        if ($ownTransaction) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'company_uid' => $row['company_uid'],
        'company_label' => $row['company_label'],
        'device_token' => $deviceToken,
    ];
}

/**
 * Look up a pairing's status for the desktop that created it (owner-scoped:
 * matches pairing_token AND owner_identity_hash, so one owner can never poll
 * another owner's session). Returns null if no such pairing exists for this
 * owner; a pairing that exists but belongs to someone else collapses to the
 * same null, so callers surface one generic not-found.
 *
 * Always returns ['status' => ...]. Only adds 'phone_public_key' and
 * 'device_label' once status has moved past 'pending' (i.e. 'claimed' or
 * 'delivered'), so a still-pending session never exposes a null/empty key.
 */
function get_pairing_status(string $pairingToken, string $ownerHash): ?array
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT status, phone_public_key, device_label
         FROM mobile_sync_pairings WHERE pairing_token = ? AND owner_identity_hash = ? LIMIT 1'
    );
    $stmt->execute([$pairingToken, $ownerHash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $result = ['status' => $row['status']];
    if ($row['status'] !== 'pending') {
        $result['phone_public_key'] = $row['phone_public_key'];
        $result['device_label'] = $row['device_label'];
    }
    return $result;
}

/**
 * Desktop delivers the RSA-encrypted sync key to a pairing it created, once
 * the phone has claimed it. Owner-scoped and status-gated in a single atomic
 * UPDATE: matches pairing_token AND owner_identity_hash AND status = 'claimed'
 * AND expires_at > NOW(), so rowCount() === 1 is the only signal callers need.
 * A pairing that's still pending, already delivered, expired, or belongs to a
 * different owner all collapse to the same false, so callers surface one
 * generic error without leaking which case applied.
 */
function deliver_pairing_key(string $pairingToken, string $ownerHash, string $encryptedSyncKey): bool
{
    global $pdo;
    $update = $pdo->prepare(
        "UPDATE mobile_sync_pairings
         SET encrypted_sync_key = ?, status = 'delivered'
         WHERE pairing_token = ? AND owner_identity_hash = ? AND status = 'claimed' AND expires_at > NOW()"
    );
    $update->execute([$encryptedSyncKey, $pairingToken, $ownerHash]);
    return $update->rowCount() === 1;
}

/**
 * Phone polls for the delivered sync key using the device token it received at claim time.
 * Looked up by device_token_hash on the pairing row itself (not mobile_sync_devices), since
 * the pairing, not the device, carries the ciphertext.
 *
 * Returns null if the token doesn't resolve to any unexpired pairing (caller surfaces one
 * generic not-found, whether the token is unknown, expired, or already consumed). Returns
 * ['pending' => true] while the desktop hasn't delivered the key yet. Once delivered,
 * deletes the pairing row with a `status = 'delivered'` guard and returns
 * ['encrypted_sync_key' => ...] only when that guarded delete actually removed the row, so
 * two concurrent polls can't both consume it; the mobile_sync_devices row is untouched and
 * keeps working for snapshot/queue calls.
 */
function fetch_and_consume_pairing_key(string $deviceToken): ?array
{
    global $pdo;
    if ($deviceToken === '') {
        return null;
    }
    $tokenHash = hash('sha256', $deviceToken);
    $stmt = $pdo->prepare(
        'SELECT id, status, encrypted_sync_key FROM mobile_sync_pairings
         WHERE device_token_hash = ? AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ($row['status'] !== 'delivered') {
        return ['pending' => true];
    }

    // Guarded delete: only the caller whose DELETE actually removes the row
    // gets to return the ciphertext, so two concurrent polls can't both consume it.
    $delete = $pdo->prepare("DELETE FROM mobile_sync_pairings WHERE id = ? AND status = 'delivered'");
    $delete->execute([$row['id']]);
    if ($delete->rowCount() !== 1) {
        return null;
    }
    return ['encrypted_sync_key' => $row['encrypted_sync_key']];
}
