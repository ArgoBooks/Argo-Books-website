<?php

/**
 * Links a fresh install to the browser session that downloaded it.
 *
 * The desktop app opens /welcome/?m=<machine_uuid> in the default browser on its
 * first run. That browser still carries the argo_visitor_id cookie from the visit
 * that produced the download, so this request is the one moment where the machine
 * and the visitor are both identifiable. Matching them here is exact: no guessing
 * from IP addresses or timing.
 *
 * This exists because macOS gives no other route. Windows recovers the token from
 * the installer filename and Linux from the AppImage filename, but a Mac download
 * is a .zip the browser auto-expands, and the extracted "Argo Books.app" carries
 * neither the archive's name nor any record of where it came from: recent macOS
 * stopped storing the source URL in the quarantine event, so there is nothing on
 * disk left to read.
 *
 * Only rows that are still unattributed are touched, so a token match always wins
 * and a second visit cannot overwrite or reassign an existing attribution.
 */

/** Machine ids are GUIDs written by FirstRunReporter.GetOrCreateMachineUuid(). */
function welcome_valid_uuid(?string $value): bool
{
    return is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1;
}

/**
 * Attaches $visitor_id (and whatever source first brought them in) to this
 * machine's unattributed app_first_run row.
 *
 * Returns 'linked', 'already', 'no_install' or 'error'. Nothing is shown to the
 * visitor either way: the page is worth reading on its own, and a page that
 * announces it just tracked you is a page nobody wants opened on their behalf.
 */
function welcome_link_install(PDO $pdo, string $machine_uuid, string $visitor_id): string
{
    try {
        $env = current_environment();

        $find = $pdo->prepare(
            "SELECT id FROM referral_events
              WHERE event_type = 'app_first_run'
                AND environment = ?
                AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.machine_uuid')) = ?
              ORDER BY created_at DESC
              LIMIT 1"
        );
        $find->execute([$env, $machine_uuid]);
        $row = $find->fetch();

        // The app posts app_first_run before opening the browser, so a missing row means the post failed or the machine id is not ours.
        if ($row === false) {
            return 'no_install';
        }

        // Same rule the token path uses: first-touch source for this visitor.
        $src = $pdo->prepare(
            "SELECT source_code FROM referral_events
              WHERE visitor_id = ?
                AND event_type = 'landing'
                AND source_code IS NOT NULL
              ORDER BY created_at DESC
              LIMIT 1"
        );
        $src->execute([$visitor_id]);
        $src_row = $src->fetch();
        $source_code = $src_row === false ? null : $src_row['source_code'];

        // attribution_method records that this came from the welcome page rather than a verified token, so the two are never confused when reading the funnel back.
        $upd = $pdo->prepare(
            "UPDATE referral_events
                SET visitor_id = ?,
                    source_code = COALESCE(?, source_code),
                    event_data = JSON_SET(COALESCE(event_data, '{}'), '$.attribution_method', 'welcome_page')
              WHERE id = ? AND visitor_id IS NULL"
        );
        $upd->execute([$visitor_id, $source_code, (int)$row['id']]);

        return $upd->rowCount() > 0 ? 'linked' : 'already';
    } catch (PDOException $e) {
        error_log('welcome_link_install failed: ' . $e->getMessage());
        return 'error';
    }
}
