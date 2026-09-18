<?php
declare(strict_types=1);

/**
 * Public token-authenticated landing page for the "revert" link in the
 * email-change-completed notification sent to the OLD address.
 *
 * Bound to: GET/POST /api/portal/revert-email.php?token=...
 */

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../rate_limit_helper.php';
require_once __DIR__ . '/_audit.php';
require_once __DIR__ . '/../../email_sender.php';

global $pdo;
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');

// Only failed lookups are counted, as on the other public token pages: a real owner
// following their own link once never comes near it.
$clientIp = get_client_ip();
if (rate_limit_exceeded('portal_revert_email', $clientIp)) {
    http_response_code(429);
    header('Retry-After: ' . rate_limit_window('portal_revert_email'));
    echo revert_layout('Too many attempts', '<p>Please try again in '
        . rate_limit_wait_phrase('portal_revert_email') . '.</p>');
    exit;
}

if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    rate_limit_record('portal_revert_email', $clientIp);
    http_response_code(400);
    echo revert_layout('Invalid link', '<p>This revert link is malformed or expired.</p>');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM email_change_requests WHERE cancel_token = ? AND state = 'completed'");
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    rate_limit_record('portal_revert_email', $clientIp);
    http_response_code(404);
    echo revert_layout('Not found', '<p>This revert link is no longer valid (it may have been used or the 30-day window passed).</p>');
    exit;
}

if ($row['revert_until'] && strtotime($row['revert_until']) < time()) {
    http_response_code(410);
    echo revert_layout('Window expired', '<p>The 30-day revert window has expired.</p>');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    // Stale-token guard: only revert if the company is currently on the
    // new_email from THIS change request. Without this predicate, a 30-day-old
    // revert link can silently undo a later legitimate change
    // (A->B then B->C; the old A->B link stomps owner back to A).
    //
    // email_verified_at is restored to whatever the old address had, not left on the
    // stamp the new address earned. old_email_verified_at is NULL when the old address
    // never answered a code, and reverting to it has to put the company back to
    // unverified rather than let it inherit a mark it never earned.
    $upd = $pdo->prepare(
        "UPDATE portal_companies SET owner_email = ?, email_verified_at = ?
         WHERE id = ? AND owner_email = ?"
    );
    $upd->execute([$row['old_email'], $row['old_email_verified_at'], $row['company_id'], $row['new_email']]);
    if ($upd->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(409);
        echo revert_layout(
            'Cannot revert',
            '<p>This email change has been superseded by a newer one. The portal owner email is no longer ' . htmlspecialchars($row['new_email']) . ', so this revert link can no longer be used.</p>'
        );
        exit;
    }
    $pdo->prepare("UPDATE email_change_requests SET state='reverted', reverted_at = NOW(), cancel_token = NULL WHERE id = ?")
        ->execute([$row['id']]);
    audit_log($pdo, (int)$row['company_id'], 'email_reverted', 'owner', null, null, (int)$row['id'], [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $pdo->commit();

    // Notify both addresses
    $old_safe = htmlspecialchars($row['old_email']);
    $new_safe = htmlspecialchars($row['new_email']);
    send_styled_email($row['old_email'], 'Email change reverted',
        "<p>Your Argo Books portal email has been restored to <strong>$old_safe</strong>.</p>", 'blue');
    send_styled_email($row['new_email'], 'Email change reverted',
        "<p>The change to <strong>$new_safe</strong> was reverted by the original owner. The portal owner email is now back to the previous value.</p>", 'purple');

    echo revert_layout('Email reverted',
        "<p>Your portal owner email has been restored to <strong>$old_safe</strong>.</p>"
        . "<p>Both addresses have been notified.</p>");
    exit;
}

// GET: show confirmation
$old_safe = htmlspecialchars($row['old_email']);
$new_safe = htmlspecialchars($row['new_email']);
echo revert_layout('Revert email change?',
    "<p>Your portal owner email was changed to <strong>$new_safe</strong>.</p>"
    . "<p>Click below to revert it back to <strong>$old_safe</strong>.</p>"
    . "<form method='post'>"
    . "<input type='hidden' name='token' value='" . htmlspecialchars($token) . "'>"
    . "<button type='submit' class='btn-danger'>Revert email</button>"
    . "</form>");

function revert_layout(string $title, string $body): string {
    $titleSafe = htmlspecialchars($title);
    return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>$titleSafe - Argo Books</title>
            <link rel="shortcut icon" type="image/x-icon" href="/resources/images/argo-logo/argo-icon.ico">
            <link rel="stylesheet" href="/resources/styles/custom-colors.css">
            <link rel="stylesheet" href="/portal/style.css">
            <style>
                .callback-result { text-align: center; padding: 60px 20px; max-width: 480px; margin: 0 auto; }
                .callback-title { font-size: 22px; font-weight: 600; color: var(--gray-900, #111); margin: 0 0 12px; }
                .callback-result p { color: var(--gray-900, #111); font-size: 15px; line-height: 1.5; margin: 0 0 12px; }
                .callback-result form { margin-top: 28px; }
                .btn-danger { display: inline-block; padding: 14px 28px; background: var(--red-600); color: var(--white); border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
                .btn-danger:hover { background: var(--red-700); transform: translateY(-1px); box-shadow: 0 4px 12px var(--red-alpha-30); }
                .callback-result code { background: var(--gray-bg-light); padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class="portal-page">
                <header class="portal-header">
                    <div class="portal-header-inner">
                        <div class="company-info">
                            <h1 class="company-name">Payment Portal</h1>
                            <span class="portal-subtitle">Powered by Argo Books</span>
                        </div>
                    </div>
                </header>
                <main class="portal-main">
                    <div class="callback-result">
                        <h2 class="callback-title">$titleSafe</h2>
                        $body
                    </div>
                </main>
                <footer class="portal-footer">
                    <p>Secure payments powered by <a href="https://argorobots.com" target="_blank" rel="noopener">Argo Books</a></p>
                </footer>
            </div>
        </body>
        </html>
HTML;
}
