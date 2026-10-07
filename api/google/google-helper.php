<?php
/**
 * Google OAuth Helper Functions
 *
 * Shared authentication and token management for Google API endpoints.
 * Google Sheets is a free feature: authentication uses device ID.
 */

require_once __DIR__ . '/../portal/portal-helper.php';

/**
 * Authenticate a Google API request using device ID.
 * Google Sheets is a free feature, so only a device identifier is required.
 *
 * @return array|null Auth context with device_id_hash, or null if missing
 */
function authenticate_google_request(): ?array
{
    $deviceIdHash = authenticate_device_request();
    if ($deviceIdHash) {
        return [
            'type' => 'device',
            'device_id_hash' => $deviceIdHash,
        ];
    }

    return null;
}

/**
 * Get Google OAuth tokens for the given auth context.
 * Falls back to portal_companies table for users who connected via the old portal flow.
 *
 * @param array $authContext Auth context from authenticate_google_request()
 * @return array|null Token row with google_refresh_token, google_access_token, google_token_expires
 */
function get_google_tokens(array $authContext): ?array
{
    global $pdo;

    // Query device-based tokens
    $stmt = $pdo->prepare(
        'SELECT google_access_token, google_refresh_token, google_token_expires
         FROM google_oauth_tokens WHERE device_id_hash = ? LIMIT 1'
    );
    $stmt->execute([$authContext['device_id_hash']]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Update just the access token for the given auth context (after refresh).
 */
function update_google_access_token(array $authContext, string $encryptedAccessToken, string $expiresAt): void
{
    global $pdo;

    $stmt = $pdo->prepare(
        'UPDATE google_oauth_tokens SET google_access_token = ?, google_token_expires = ? WHERE device_id_hash = ?'
    );

    $stmt->execute([$encryptedAccessToken, $expiresAt, $authContext['device_id_hash']]);
}

/**
 * Clear Google OAuth tokens for the given auth context.
 */
function clear_google_tokens(array $authContext): void
{
    global $pdo;

    $stmt = $pdo->prepare(
        'DELETE FROM google_oauth_tokens WHERE device_id_hash = ?'
    );

    $stmt->execute([$authContext['device_id_hash']]);
}

/**
 * Store an OAuth state token for Google auth flow.
 * Uses a dedicated table to avoid FK constraints on portal_oauth_states.
 */
function store_google_oauth_state(array $authContext, string $state): void
{
    global $pdo;

    // Clean up expired states for this device
    $stmt = $pdo->prepare('DELETE FROM google_oauth_states WHERE device_id_hash = ? OR expires_at < NOW()');
    $stmt->execute([$authContext['device_id_hash']]);

    $stmt = $pdo->prepare(
        'INSERT INTO google_oauth_states (state_token, device_id_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))'
    );

    $stmt->execute([$state, $authContext['device_id_hash']]);
}

/**
 * Resolve the AES key for Google OAuth token encryption.
 *
 * Prefers the dedicated GOOGLE_ENCRYPTION_KEY (64-char hex, like
 * PORTAL_ENCRYPTION_KEY). Falls back to deriving from GOOGLE_CLIENT_SECRET
 * for backwards compatibility with tokens encrypted before this env var
 * existed. Returns [primaryKey, fallbackKey|null]: encrypt with primary,
 * decrypt by trying primary first then fallback.
 */
function _google_encryption_keys(): array
{
    $dedicatedHex = trim($_ENV['GOOGLE_ENCRYPTION_KEY'] ?? '');
    $dedicatedKey = null;
    if ($dedicatedHex !== '') {
        $dedicatedKey = @hex2bin($dedicatedHex);
        if ($dedicatedKey === false || strlen($dedicatedKey) !== 32) {
            throw new RuntimeException('GOOGLE_ENCRYPTION_KEY must be a 64-character hex string (256 bits).');
        }
    }

    $secret = trim($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
    $derivedKey = $secret !== '' ? hash('sha256', $secret, true) : null;

    if ($dedicatedKey === null && $derivedKey === null) {
        throw new RuntimeException('Neither GOOGLE_ENCRYPTION_KEY nor GOOGLE_CLIENT_SECRET is configured.');
    }

    // Use the dedicated key when available, falling back to the derived one. Both are returned for decryption so old ciphertext keeps working after GOOGLE_ENCRYPTION_KEY is introduced.
    return [
        'primary' => $dedicatedKey ?? $derivedKey,
        'fallback' => $dedicatedKey !== null ? $derivedKey : null,
    ];
}

/**
 * Encrypt a string using AES-256-GCM. Uses GOOGLE_ENCRYPTION_KEY if set
 * (dedicated, rotation-safe), otherwise falls back to a key derived from
 * GOOGLE_CLIENT_SECRET (backwards-compatible default).
 */
function google_encrypt(string $plaintext): string
{
    $keys = _google_encryption_keys();
    $iv = random_bytes(12);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $keys['primary'], OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed.');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt a string encrypted with google_encrypt(). Tries the primary key
 * first, then the fallback (so tokens encrypted under the old derived key
 * keep working after GOOGLE_ENCRYPTION_KEY is introduced).
 */
function google_decrypt(string $encoded): string
{
    $keys = _google_encryption_keys();
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 28) {
        throw new RuntimeException('Invalid encrypted data.');
    }
    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $ciphertext = substr($data, 28);

    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $keys['primary'], OPENSSL_RAW_DATA, $iv, $tag);
    if ($plaintext === false && $keys['fallback'] !== null) {
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $keys['fallback'], OPENSSL_RAW_DATA, $iv, $tag);
    }
    if ($plaintext === false) {
        throw new RuntimeException('Decryption failed.');
    }
    return $plaintext;
}

/**
 * Refresh an expired Google access token.
 *
 * @param string $refreshToken Decrypted refresh token
 * @param array $authContext Auth context
 * @return string|null New access token or null on failure
 */
function refresh_google_token(string $refreshToken, array $authContext): ?string
{
    $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
    $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';

    $payload = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 200) {
        error_log('Google token refresh failed: ' . ($response ?: 'curl error'));
        return null;
    }

    $tokenData = json_decode($response, true);
    $newAccessToken = $tokenData['access_token'] ?? '';
    $expiresIn = $tokenData['expires_in'] ?? 3600;

    if (empty($newAccessToken)) {
        return null;
    }

    // Update stored token
    $encrypted = google_encrypt($newAccessToken);
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
    update_google_access_token($authContext, $encrypted, $expiresAt);

    return $newAccessToken;
}
