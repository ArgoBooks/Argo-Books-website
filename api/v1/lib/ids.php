<?php
declare(strict_types=1);

/**
 * Public object ids.
 *
 * Format is <prefix>_<24 hex chars>, e.g. cus_9f21c0b47ae35d18f2c4a7bb. Opaque
 * on purpose: nothing about the account, the row number, or the creation time
 * is recoverable from one, so ids are safe to put in logs and support tickets.
 *
 * These are API ids and they are NOT the ids the desktop assigns when it
 * imports an object. The desktop's id comes back separately in `local_ref`.
 */

function api_generate_id(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

/**
 * True when $id looks like an id of the given prefix. Used to reject a
 * cross-resource id (passing a cus_ where a sup_ belongs) with a clear message
 * rather than a confusing "no such object".
 */
function api_id_has_prefix(string $id, string $prefix): bool
{
    return (bool) preg_match('/^' . preg_quote($prefix, '/') . '_[0-9a-f]{24}$/', $id);
}

/**
 * A merchant-issued secret key. `ab_` marks it recognisably as an Argo Books
 * credential so GitHub secret scanning and our own log redaction can spot one
 * that has leaked. There is no live/test split: the prefix is for detection,
 * not for isolation.
 */
function api_generate_secret_key(): string
{
    return 'ab_' . bin2hex(random_bytes(24));
}

/**
 * The displayable fragment of a secret, stored alongside the hash so Settings
 * and the admin page can identify a key without holding the secret itself.
 */
function api_key_hint(string $secret): string
{
    return substr($secret, 0, 7) . '...' . substr($secret, -4);
}
