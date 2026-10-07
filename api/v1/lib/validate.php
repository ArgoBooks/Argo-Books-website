<?php
declare(strict_types=1);

/**
 * Input validation, driven by the field specs in resource.php.
 *
 * Every rejection names the offending parameter in the error's `param`, because
 * "invalid request" without a field name is the single most common way an API
 * wastes a developer's afternoon.
 */

/**
 * Validate and coerce a request body against a resource's field specs.
 *
 * On create, every required field must be present. On update, absent fields are
 * left alone, and only what was supplied is validated, so a partial update never
 * has to resend the whole object.
 *
 * Returns a column => value map ready for SQL.
 */
function api_validate_input(array $spec, array $input, bool $isCreate, int $accountId): array
{
    $out = [];

    foreach ($input as $name => $_) {
        if ($name === 'expand') {
            continue;
        }
        if (!isset($spec['fields'][$name])) {
            api_error(
                400,
                'invalid_request_error',
                'unknown_parameter',
                "Received unknown parameter '$name'.",
                (string) $name
            );
        }
    }

    foreach ($spec['fields'] as $name => $field) {
        $present = array_key_exists($name, $input);

        if (!$present) {
            if ($isCreate && !empty($field['required'])) {
                api_error(
                    400,
                    'invalid_request_error',
                    'parameter_missing',
                    "Missing required parameter '$name'.",
                    $name
                );
            }
            if ($isCreate && array_key_exists('default', $field)) {
                $out[$name] = $field['default'];
            }
            continue;
        }

        $value = $input[$name];

        // An explicit null clears an optional field. Required fields cannot be
        // cleared, since that would leave the object invalid.
        if ($value === null || $value === '') {
            if (!empty($field['required'])) {
                api_error(
                    400,
                    'invalid_request_error',
                    'parameter_invalid_empty',
                    "Parameter '$name' cannot be empty.",
                    $name
                );
            }
            $out[$name] = null;
            continue;
        }

        $out[$name] = api_coerce_field($name, $field, $value, $accountId);
    }

    return $out;
}

/** Coerce one value to its column representation, or error out. */
function api_coerce_field(string $name, array $field, $value, int $accountId)
{
    switch ($field['type']) {
        case 'string':
        case 'text':
            if (!is_string($value) && !is_numeric($value)) {
                api_error(400, 'invalid_request_error', 'parameter_invalid_type', "Parameter '$name' must be a string.", $name);
            }
            $value = trim((string) $value);
            $max = $field['max'] ?? 65535;
            if (mb_strlen($value) > $max) {
                api_error(400, 'invalid_request_error', 'parameter_too_long', "Parameter '$name' must be $max characters or fewer.", $name);
            }
            return $value;

        case 'email':
            $value = trim((string) $value);
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                api_error(400, 'invalid_request_error', 'parameter_invalid_email', "Parameter '$name' must be a valid email address.", $name);
            }
            return $value;

        case 'amount':
            // Minor units, as Stripe uses. A decimal is rejected rather than rounded, because guessing at money is how books go wrong.
            if (is_int($value)) {
                return $value;
            }
            if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
                return (int) trim($value);
            }
            api_error(
                400,
                'invalid_request_error',
                'parameter_invalid_amount',
                "Parameter '$name' must be an integer in the currency's smallest unit (1999 means 19.99 USD).",
                $name
            );
            return 0;

        case 'currency':
            $value = strtoupper(trim((string) $value));
            if (!preg_match('/^[A-Z]{3}$/', $value)) {
                api_error(400, 'invalid_request_error', 'parameter_invalid_currency', "Parameter '$name' must be a three-letter ISO 4217 code.", $name);
            }
            return $value;

        case 'country':
            $value = strtoupper(trim((string) $value));
            if (!preg_match('/^[A-Z]{2}$/', $value)) {
                api_error(400, 'invalid_request_error', 'parameter_invalid_country', "Parameter '$name' must be a two-letter ISO 3166-1 code.", $name);
            }
            return $value;

        case 'date':
            $value = trim((string) $value);
            $parsed = DateTime::createFromFormat('!Y-m-d', $value);
            if (!$parsed || $parsed->format('Y-m-d') !== $value) {
                api_error(400, 'invalid_request_error', 'parameter_invalid_date', "Parameter '$name' must be a date in YYYY-MM-DD form.", $name);
            }
            return $value;

        case 'decimal':
            if (!is_numeric($value)) {
                api_error(400, 'invalid_request_error', 'parameter_invalid_type', "Parameter '$name' must be a number.", $name);
            }
            return number_format((float) $value, 4, '.', '');

        case 'enum':
            $value = trim((string) $value);
            if (!in_array($value, $field['values'], true)) {
                api_error(
                    400,
                    'invalid_request_error',
                    'parameter_invalid_value',
                    "Parameter '$name' must be one of: " . implode(', ', $field['values']) . '.',
                    $name
                );
            }
            return $value;

        case 'ref':
            return api_validate_ref($name, $field, (string) $value, $accountId);

        case 'metadata':
            return api_encode_metadata($name, $value);
    }

    api_error(500, 'api_error', 'field_spec_error', "No validator for field type '{$field['type']}'.");
    return null;
}

/**
 * Check that a referenced object exists, belongs to this account, and is of the
 * expected type. Catching a dangling reference here rather than at import time
 * means the developer sees the mistake on the request that caused it.
 */
function api_validate_ref(string $name, array $field, string $value, int $accountId): string
{
    global $pdo;

    $value = trim($value);
    if (!api_id_has_prefix($value, $field['prefix'])) {
        api_error(
            400,
            'invalid_request_error',
            'parameter_invalid_reference',
            "Parameter '$name' must be the id of a {$field['object']} (it starts with {$field['prefix']}_).",
            $name
        );
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM ' . $field['table'] . '
          WHERE public_id = ? AND account_id = ? AND deleted_at IS NULL
          LIMIT 1'
    );
    $stmt->execute([$value, $accountId]);
    if ($stmt->fetch() === false) {
        api_error(
            400,
            'invalid_request_error',
            'reference_not_found',
            "No such {$field['object']}: '$value'.",
            $name
        );
    }

    return $value;
}

/**
 * Metadata is a flat string map, as on Stripe. Nesting is refused rather than
 * flattened, so what comes back out is exactly what went in.
 */
function api_encode_metadata(string $name, $value): ?string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            api_error(400, 'invalid_request_error', 'parameter_invalid_metadata', "Parameter '$name' must be a JSON object.", $name);
        }
        $value = $decoded;
    }
    if (!is_array($value)) {
        api_error(400, 'invalid_request_error', 'parameter_invalid_metadata', "Parameter '$name' must be an object.", $name);
    }
    if (count($value) > 50) {
        api_error(400, 'invalid_request_error', 'metadata_too_large', "Parameter '$name' accepts at most 50 keys.", $name);
    }

    $clean = [];
    foreach ($value as $k => $v) {
        if (is_array($v) || is_object($v)) {
            api_error(400, 'invalid_request_error', 'parameter_invalid_metadata', "Metadata value for '$k' must be a string. Nested objects are not stored.", $name);
        }
        if (mb_strlen((string) $k) > 40) {
            api_error(400, 'invalid_request_error', 'metadata_key_too_long', "Metadata key '$k' must be 40 characters or fewer.", $name);
        }
        $stringValue = $v === null ? '' : (string) $v;
        if (mb_strlen($stringValue) > 500) {
            api_error(400, 'invalid_request_error', 'metadata_value_too_long', "Metadata value for '$k' must be 500 characters or fewer.", $name);
        }
        $clean[(string) $k] = $stringValue;
    }

    return $clean === [] ? null : json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
