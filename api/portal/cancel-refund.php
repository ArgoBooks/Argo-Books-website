<?php
declare(strict_types=1);

/**
 * Public token-authenticated landing page for the "Cancel this refund" link
 * in the cooling-off email. The token is single-use and only valid while the
 * refund is still in pending_code / code_verified / cooling_off.
 *
 * Bound to: GET/POST /api/portal/cancel-refund.php?token=...
 */

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/_audit.php';

global $pdo;
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');

if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    echo cancel_refund_layout('Invalid link', '<p>This cancellation link is malformed or expired.</p>');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM refund_requests WHERE cancel_token = ?");
$stmt->execute([$token]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    http_response_code(404);
    echo cancel_refund_layout('Not found', '<p>This refund request was not found. The link may have already been used or expired.</p>');
    exit;
}

if (!in_array($req['state'], ['pending_code','code_verified','cooling_off'], true)) {
    echo cancel_refund_layout('Already finalized',
        '<p>This refund can no longer be cancelled. Current state: <code>' . htmlspecialchars($req['state']) . '</code></p>');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // State-guarded, because the cooling-off cron can move this row on between the SELECT above and this UPDATE.
    $upd = $pdo->prepare("
        UPDATE refund_requests
        SET state='cancelled', state_reason='cancelled_by_email_link', cancel_token = NULL, updated_at = NOW()
        WHERE id = ? AND state IN ('pending_code','code_verified','cooling_off')
    ");
    $upd->execute([$req['id']]);
    if ($upd->rowCount() === 0) {
        http_response_code(409);
        echo cancel_refund_layout('Already finalized',
            '<p>This refund was finalized before the cancellation could complete. The page may have been left open while the cooling-off window expired.</p>');
        exit;
    }
    audit_log(
        $pdo, (int)$req['company_id'], 'cancelled_by_email_link', 'owner', null,
        (int)$req['id'], null,
        ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]
    );

    $amount = number_format($req['amount_cents'] / 100, 2) . ' ' . htmlspecialchars($req['currency']);
    $invoice = htmlspecialchars($req['invoice_number']);
    echo cancel_refund_layout('Refund cancelled',
        "<p>The refund of <strong>$amount</strong> on invoice <strong>$invoice</strong> has been cancelled.</p>"
        . "<p>No money has moved.</p>");
    exit;
}

// GET: show confirmation
$amount = number_format($req['amount_cents'] / 100, 2) . ' ' . htmlspecialchars($req['currency']);
$invoice = htmlspecialchars($req['invoice_number']);
echo cancel_refund_layout('Cancel this refund?',
    "<p>You're about to cancel a refund of <strong>$amount</strong> on invoice <strong>$invoice</strong>.</p>"
    . "<form method='post'>"
    . "<input type='hidden' name='token' value='" . htmlspecialchars($token) . "'>"
    . "<button type='submit' class='btn-danger'>Cancel this refund</button>"
    . "</form>");

function cancel_refund_layout(string $title, string $body): string {
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
