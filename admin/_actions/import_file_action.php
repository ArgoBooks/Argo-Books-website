<?php
declare(strict_types=1);

/**
 * Admin actions for import_diagnostic_files: download, delete, and delete all.
 *
 * Bound to: POST /admin/_actions/import_file_action.php
 * Session-authenticated (admin_logged_in, which is already behind TOTP) + CSRF-protected.
 *
 * A download decrypts the file into the response and removes the server's copy in
 * the same request, which is the whole point of the page: the file exists here for
 * as long as it takes someone to look at it, and no longer. The row survives the
 * delete as an audit trail, marked with who took it and when.
 */

require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
if (empty($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
    http_response_code(403);
    exit('Bad CSRF token');
}

$storageDir = __DIR__ . '/../../storage/import-diagnostics';
$action = (string) ($_POST['action'] ?? '');
$admin = (string) ($_SESSION['admin_username'] ?? 'unknown');

/** Removes the file from disk and marks the row as having no file left. */
function import_file_shred(PDO $pdo, string $storageDir, array $row, string $admin, bool $downloaded): void
{
    $path = $storageDir . '/' . $row['storage_name'] . '.enc';
    if (is_file($path)) {
        @unlink($path);
    }
    // The row stays. It is the only record that a file ever existed and who took it,
    // and it is what the purge counts.
    $stmt = $pdo->prepare(
        'UPDATE import_diagnostic_files
            SET state = ?, downloaded_at = ?, downloaded_by = ?
          WHERE id = ?'
    );
    $stmt->execute([
        'downloaded',
        $downloaded ? date('Y-m-d H:i:s') : null,
        $downloaded ? $admin : null,
        $row['id'],
    ]);
}

/** The one row this action is about, in this environment, or null. */
function import_file_row(PDO $pdo, $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM import_diagnostic_files WHERE id = ? AND environment = ?'
    );
    $stmt->execute([(int) $id, current_environment()]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

$back = '../import-files/';

if ($action === 'download') {
    $row = import_file_row($pdo, $_POST['id'] ?? 0);
    if ($row === null || $row['state'] !== 'held') {
        header('Location: ' . $back . '?msg=gone');
        exit;
    }

    $path = $storageDir . '/' . $row['storage_name'] . '.enc';
    $sealed = is_file($path) ? file_get_contents($path) : false;
    if ($sealed === false) {
        error_log('import-files: file missing on disk for row ' . $row['id']);
        import_file_shred($pdo, $storageDir, $row, $admin, false);
        header('Location: ' . $back . '?msg=gone');
        exit;
    }

    try {
        $plain = portal_decrypt($sealed);
    } catch (RuntimeException $e) {
        error_log('import-files: decrypt failed for row ' . $row['id'] . ': ' . $e->getMessage());
        header('Location: ' . $back . '?msg=error');
        exit;
    }

    // Named after the row, never after anything the customer typed.
    $ext = strtolower((string) $row['file_kind']);
    $name = 'import-' . $row['id'] . '-' . preg_replace('/[^a-z0-9]+/', '-', (string) $row['failure_reason']) . '.' . $ext;

    // Marked taken and removed from disk BEFORE the body is sent, so a connection
    // that drops mid-transfer still leaves nothing behind.
    import_file_shred($pdo, $storageDir, $row, $admin, true);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($plain));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    echo $plain;
    exit;
}

if ($action === 'delete') {
    $row = import_file_row($pdo, $_POST['id'] ?? 0);
    if ($row !== null) {
        import_file_shred($pdo, $storageDir, $row, $admin, false);
    }
    header('Location: ' . $back . '?msg=deleted');
    exit;
}

if ($action === 'delete_all') {
    $stmt = $pdo->prepare(
        "SELECT * FROM import_diagnostic_files WHERE state = 'held' AND environment = ?"
    );
    $stmt->execute([current_environment()]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        import_file_shred($pdo, $storageDir, $row, $admin, false);
    }
    header('Location: ' . $back . '?msg=deleted_all&n=' . count($rows));
    exit;
}

http_response_code(400);
exit('Unknown action');
