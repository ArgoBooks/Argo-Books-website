<?php

// Load environment variables
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/env_helper.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Create PDO connection for files that need it
try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("PDO connection error: " . $e->getMessage());
    $pdo = null;
}

/**
 * Returns the current runtime environment ('production' or 'sandbox') derived
 * from APP_ENV in .env. Used to tag rows on insert (premium_subscriptions,
 * premium_subscription_payments) and to filter admin reads so dev test data
 * doesn't pollute prod stats. Both environments share the same database.
 * Anything other than 'production' is normalized to 'sandbox' to match the
 * ENUM('production','sandbox') columns.
 */
function current_environment(): string
{
    return ($_ENV['APP_ENV'] ?? '') === 'production' ? 'production' : 'sandbox';
}

// site_url() lives in env_helper.php (already required at the top of this file). Use it for sitemap loc URLs, canonical hrefs, Open Graph og:url, and JSON-LD @id values that need an absolute URL.

/**
 * Encrypt a string using AES-256-GCM.
 * Requires PORTAL_ENCRYPTION_KEY environment variable (64-char hex = 256 bits).
 */
function portal_encrypt(string $plaintext): string
{
    $raw = trim($_ENV['PORTAL_ENCRYPTION_KEY'] ?? '');
    $key = @hex2bin($raw);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('PORTAL_ENCRYPTION_KEY must be a 64-character hex string (256 bits).');
    }
    $iv = random_bytes(12); // 96-bit IV for GCM
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed.');
    }
    // Format: base64(iv + tag + ciphertext)
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt a string encrypted with portal_encrypt().
 */
function portal_decrypt(string $encoded): string
{
    $raw = trim($_ENV['PORTAL_ENCRYPTION_KEY'] ?? '');
    $key = @hex2bin($raw);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('PORTAL_ENCRYPTION_KEY must be a 64-character hex string (256 bits).');
    }
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 28) { // 12 IV + 16 tag minimum
        throw new RuntimeException('Invalid encrypted data.');
    }
    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $ciphertext = substr($data, 28);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plaintext === false) {
        throw new RuntimeException('Decryption failed.');
    }
    return $plaintext;
}
