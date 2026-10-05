<?php
declare(strict_types=1);

/**
 * Deciding which overdue invoices get a reminder, and making sure no customer is sent the
 * same one twice. Used by cron/portal_invoice_reminders.php, and kept apart from it so the
 * tests can check these rules without running the cron or sending anything.
 */

// stage => days past due. Fixed, deliberately not user-configurable.
const PORTAL_REMINDER_STAGES = [1 => 3, 2 => 7, 3 => 14];

// Never chase an invoice more than this many days past due. Bounds the scan,
// and stops a long outage or a late re-enable from firing a "final reminder"
// at an invoice from months ago.
const PORTAL_REMINDER_MAX_AGE_DAYS = 45;

// Balances below this are rounding noise (typically a processing-fee residue
// on a partial payment) and are not worth an email.
const PORTAL_REMINDER_MIN_BALANCE = 1.00;

// The natural cadence gaps are 4 and 7 days, so this only ever catches
// day-boundary and DST edge cases.
const PORTAL_REMINDER_MIN_GAP_SECONDS = 172800;

/**
 * The invoices a run may send a reminder for, oldest due date first. Several clauses here are
 * load-bearing:
 *
 *  - pi.due_date > DATE(pc.reminders_enabled_at) is the entire "enabling
 *    never releases a backlog" guarantee. Kept in SQL so there is exactly
 *    one place to get it wrong. reminders_enabled_at IS NOT NULL is a
 *    fail-closed guard for rows where the flag was set by hand.
 *  - pi.environment = ? is mandatory. Sandbox and production share this
 *    database, so without it a sandbox test invoice emails a real customer.
 *  - pc.locked = 0 keeps a company under fraud review from emailing anyone.
 *
 * @return list<array<string, mixed>>
 */
function portal_reminder_candidates(PDO $pdo, string $environment): array
{
    $stmt = $pdo->prepare(
        'SELECT pi.id, pi.company_id, pi.invoice_id, pi.invoice_token,
                pi.customer_name, pi.customer_email, pi.status,
                pi.total_amount, pi.balance_due, pi.currency, pi.due_date,
                pi.pass_processing_fee,
                pc.company_name, pc.owner_email, pc.email_verified_at,
                DATEDIFF(CURDATE(), pi.due_date) AS days_overdue
         FROM portal_invoices pi
         INNER JOIN portal_companies pc ON pc.id = pi.company_id
         WHERE pc.reminders_enabled = 1
           AND pc.is_active = 1
           AND pc.locked = 0
           AND pc.reminders_enabled_at IS NOT NULL
           AND pi.due_date IS NOT NULL
           AND pi.due_date > DATE(pc.reminders_enabled_at)
           AND pi.environment = ?
           AND pi.balance_due >= ?
           AND pi.status NOT IN ("paid", "cancelled", "draft")
           AND pi.customer_email IS NOT NULL AND pi.customer_email <> ""
           AND pi.due_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY)
           AND pi.due_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         ORDER BY pi.due_date ASC
         LIMIT 1000'
    );
    $stmt->execute([
        $environment,
        PORTAL_REMINDER_MIN_BALANCE,
        PORTAL_REMINDER_STAGES[1],
        PORTAL_REMINDER_MAX_AGE_DAYS,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * The stage an invoice this many days overdue has reached, or null before the first one.
 * The highest eligible stage wins rather than catching up, so a cron that missed a week
 * sends one reminder, not three in a row.
 */
function portal_reminder_stage_for(int $daysOverdue): ?int
{
    foreach ([3, 2, 1] as $stage) {
        if ($daysOverdue >= PORTAL_REMINDER_STAGES[$stage]) {
            return $stage;
        }
    }
    return null;
}

/**
 * The stage to send now for this invoice, or null when there is nothing to send.
 *
 * Stages only ever move forward: an invoice whose due date was edited after a reminder went
 * out can look un-sent by days overdue alone. And two reminders are never closer together
 * than PORTAL_REMINDER_MIN_GAP_SECONDS.
 */
function portal_reminder_stage_due(PDO $pdo, int $invoiceRowId, int $daysOverdue, ?int $now = null): ?int
{
    $stage = portal_reminder_stage_for($daysOverdue);
    if ($stage === null) {
        return null;
    }

    $prev = $pdo->prepare(
        'SELECT MAX(stage) AS max_stage, MAX(sent_at) AS last_sent
         FROM portal_invoice_reminders WHERE portal_invoice_id = ?'
    );
    $prev->execute([$invoiceRowId]);
    $prevRow = $prev->fetch(PDO::FETCH_ASSOC);

    if ($prevRow && $prevRow['max_stage'] !== null && (int)$prevRow['max_stage'] >= $stage) {
        return null;
    }
    if ($prevRow && !empty($prevRow['last_sent'])
        && strtotime((string)$prevRow['last_sent']) > ($now ?? time()) - PORTAL_REMINDER_MIN_GAP_SECONDS) {
        return null;
    }

    return $stage;
}

/**
 * Claims a stage for an invoice BEFORE anything is sent, and returns the new reminder row's
 * id. UNIQUE (portal_invoice_id, stage) means a null here is another run, or an earlier
 * day, already owning this touch, so this is the whole duplicate-send defence.
 *
 * @param array<string, mixed> $invoice A row from portal_reminder_candidates().
 */
function portal_reminder_claim(PDO $pdo, array $invoice, int $stage): ?int
{
    $claim = $pdo->prepare(
        'INSERT IGNORE INTO portal_invoice_reminders
            (portal_invoice_id, company_id, stage, status,
             due_date_at_send, balance_at_send, recipient_email, created_at)
         VALUES (?, ?, ?, "sending", ?, ?, ?, NOW())'
    );
    $claim->execute([
        (int)$invoice['id'], (int)$invoice['company_id'], $stage,
        $invoice['due_date'], $invoice['balance_due'], $invoice['customer_email'],
    ]);

    return $claim->rowCount() === 0 ? null : (int)$pdo->lastInsertId();
}

/**
 * Re-reads an invoice immediately before sending. The candidate list can be tens of seconds
 * stale on a full run, which is plenty of time for the customer to have paid online or the
 * merchant to have cancelled.
 *
 * @return array{0: ?string, 1: ?array<string, mixed>} Why not to send (null to go ahead),
 *         and the invoice as it stands now.
 */
function portal_reminder_halt_reason(PDO $pdo, int $invoiceRowId): array
{
    $fresh = $pdo->prepare(
        'SELECT status, balance_due, customer_email FROM portal_invoices WHERE id = ? LIMIT 1'
    );
    $fresh->execute([$invoiceRowId]);
    $now = $fresh->fetch(PDO::FETCH_ASSOC);

    if (!$now) {
        return ['not_found', null];
    }
    if (in_array($now['status'], ['paid', 'cancelled'], true)) {
        return [$now['status'], $now];
    }
    if ((float)$now['balance_due'] < PORTAL_REMINDER_MIN_BALANCE) {
        return ['zero_balance', $now];
    }
    if (empty($now['customer_email'])) {
        return ['no_email', $now];
    }

    $sup = $pdo->prepare(
        'SELECT 1 FROM email_suppressions
         WHERE LOWER(email) = LOWER(?) AND context IN ("portal", "all_marketing") LIMIT 1'
    );
    $sup->execute([$now['customer_email']]);

    return [$sup->fetch() ? 'suppressed' : null, $now];
}
