<?php
/**
 * Telemetry timestamps carry milliseconds, which matters for ordering: several events can
 * share a second, and two that do are shown in the order they were recorded, which a
 * newest-first list then renders backwards. Closing the app writes the open page's view
 * and then the session end in the same instant, and at whole-second precision the page
 * appeared to come after the session it belonged to.
 *
 * strtotime() returns whole seconds and cannot be used for this.
 */

/** Seconds since the epoch including milliseconds, or false when the value is unusable. */
function telemetry_ts_seconds($value)
{
    if (!is_string($value) || $value === '') {
        return false;
    }
    try {
        return (float) (new DateTimeImmutable($value))->format('U.u');
    } catch (Exception $e) {
        return false;
    }
}

/** A telemetry timestamp rendered for reading, milliseconds included. */
function telemetry_ts_text(float $ts): string
{
    $dt = DateTimeImmutable::createFromFormat('U.u', number_format($ts, 6, '.', ''), new DateTimeZone('UTC'));
    return $dt === false ? '' : $dt->format('Y-m-d H:i:s.v');
}
