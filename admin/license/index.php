<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_sender.php';
require_once __DIR__ . '/../../license_functions.php';
require_once __DIR__ . '/../../config/pricing.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf_token() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        return false;
    }
    return true;
}

// Set page variables for the header
$page_title = "Subscription Administration";
$page_description = "Manage Premium subscriptions and free subscription keys";

function get_premium_subscriptions($search_filter = '')
{
    global $pdo;
    $subscriptions = [];

    try {
        $query = "
            SELECT s.*, u.username,
                (
                    SELECT p.amount
                    FROM premium_subscription_payments p
                    WHERE p.subscription_id = s.subscription_id
                      AND p.status = 'completed'
                      AND p.amount > 0
                    ORDER BY p.created_at DESC
                    LIMIT 1
                ) AS last_paid_amount,
                (
                    SELECT COALESCE(SUM(p.amount), 0)
                    FROM premium_subscription_payments p
                    WHERE p.subscription_id = s.subscription_id
                      AND p.status = 'completed'
                      AND p.payment_type != 'credit'
                ) AS total_spent
            FROM premium_subscriptions s
            LEFT JOIN community_users u ON s.user_id = u.id
            WHERE s.payment_method != 'free_key'
              AND s.environment = ?
        ";
        $params = [current_environment()];

        if (!empty($search_filter)) {
            $query .= " AND (s.email LIKE ? OR s.subscription_id LIKE ? OR u.username LIKE ?)";
            $search_param = '%' . $search_filter . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }

        $query .= " ORDER BY s.created_at DESC LIMIT 100";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching Premium subscriptions: " . $e->getMessage());
    }

    return $subscriptions;
}

// Function to get Premium subscription keys (free/promo)
function get_premium_subscription_keys()
{
    global $pdo;
    $keys = [];

    try {
        // Only genuine free and promo keys: a paid subscription also creates a row here so the app can redeem it, and those are excluded.
        $stmt = $pdo->query("
            SELECT k.*,
                   s.end_date AS subscription_end_date,
                   s.status   AS subscription_status
            FROM premium_subscription_keys k
            LEFT JOIN premium_subscriptions s ON s.subscription_id = k.subscription_id
            WHERE (s.subscription_id IS NULL OR s.payment_method = 'free_key')
              AND k.batch_label IS NULL
            ORDER BY k.created_at DESC
        ");
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching Premium subscription keys: " . $e->getMessage());
    }

    return $keys;
}

/**
 * Work out the display status of a free/promo subscription key.
 *
 * A key has no shelf life of its own: duration_months is the length of the
 * subscription minted at redemption, so an unredeemed key never expires no
 * matter how old it is. Once redeemed, the term runs out and the key should
 * stop reading as a live "Redeemed" key.
 *
 * Prefer the linked subscription's end_date/status. If the subscription row is
 * missing (deleted, or never created), fall back to redeemed_at + duration,
 * which is the same anchor _recreate_subscription_for_key() uses in
 * license_functions.php, so both agree on whether access has run out.
 *
 * @param array $key Row from get_premium_subscription_keys()
 * @return string 'available' | 'redeemed' | 'expired'
 */
function get_subscription_key_status(array $key)
{
    if (empty($key['redeemed_at'])) {
        return 'available';
    }

    // Permanent keys (duration 0) never expire.
    if ((int)$key['duration_months'] === 0) {
        return 'redeemed';
    }

    if (!empty($key['subscription_status']) && $key['subscription_status'] === 'expired') {
        return 'expired';
    }

    if (!empty($key['subscription_end_date'])) {
        return strtotime($key['subscription_end_date']) <= time() ? 'expired' : 'redeemed';
    }

    // No subscription row to read: derive the term from the redemption date.
    $end = strtotime($key['redeemed_at'] . ' +' . (int)$key['duration_months'] . ' months');
    return $end !== false && $end <= time() ? 'expired' : 'redeemed';
}

function generate_premium_subscription_key($email = null, $duration_months = 1, $notes = '')
{
    global $pdo;

    // Reuse the shared license key generator with 'premium' type prefix
    $key = generate_license_key('premium');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO premium_subscription_keys (subscription_key, email, duration_months, notes)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$key, $email ?: null, $duration_months, $notes ?: null]);
        return $key;
    } catch (PDOException $e) {
        error_log("Error generating Premium subscription key: " . $e->getMessage());
        return false;
    }
}

/**
 * One row per reseller batch, with the numbers that decide whether a deal worked.
 *
 * Redemption rate says whether the codes are reaching buyers at all. Capture rate says whether
 * the deal produced contactable customers, which is the entire reason the email prompt exists:
 * a reseller hands over codes and keeps the buyer details, so without capture a batch converts
 * revenue into people we can never reach again.
 *
 * @return array Batch summaries, newest first
 */
function get_reseller_batches()
{
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT k.batch_label,
                   MIN(k.created_at) AS created_at,
                   COUNT(*) AS total_keys,
                   SUM(CASE WHEN k.redeemed_at IS NOT NULL THEN 1 ELSE 0 END) AS redeemed,
                   SUM(CASE WHEN k.customer_email IS NOT NULL THEN 1 ELSE 0 END) AS captured,
                   SUM(CASE WHEN k.customer_email_verified_at IS NOT NULL THEN 1 ELSE 0 END) AS verified
            FROM premium_subscription_keys k
            WHERE k.batch_label IS NOT NULL
            GROUP BY k.batch_label
            ORDER BY MIN(k.created_at) DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching reseller batches: " . $e->getMessage());
        return [];
    }
}

/**
 * Every key in one batch. Used by the key list and by the CSV handed to the reseller.
 *
 * @param string $label Batch label
 * @return array Key rows, oldest first so a downloaded CSV keeps a stable order
 */
function get_reseller_batch_keys($label)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT k.*,
                   s.end_date AS subscription_end_date,
                   s.status   AS subscription_status
            FROM premium_subscription_keys k
            LEFT JOIN premium_subscriptions s ON s.subscription_id = k.subscription_id
            WHERE k.batch_label = ?
            ORDER BY k.id ASC
        ");
        $stmt->execute([$label]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching batch keys: " . $e->getMessage());
        return [];
    }
}

/**
 * Mints a batch of unrestricted keys to hand to a reseller.
 *
 * No email is set on these, deliberately. The email column restricts who may redeem, and a
 * reseller sells to people whose addresses we do not know yet. That is exactly what makes the
 * capture prompt fire in the app at redemption.
 *
 * Inserted in one transaction so a failure part way through cannot leave half a batch that has
 * already been counted and quoted to the reseller.
 *
 * @param string $label Batch label
 * @param int $count How many keys
 * @param int $duration_months 0 for a lifetime licence
 * @return int Number of keys created
 */
function generate_reseller_batch($label, $count, $duration_months)
{
    global $pdo;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO premium_subscription_keys
                (subscription_key, email, duration_months, notes, batch_label)
            VALUES (?, NULL, ?, NULL, ?)
        ");

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([generate_license_key('premium'), $duration_months, $label]);
            $created++;
        }

        $pdo->commit();
        return $created;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error generating reseller batch: " . $e->getMessage());
        return 0;
    }
}

// Get chart data - Premium subscriptions by date
function get_subscription_chart_data()
{
    global $pdo;
    $data = [];

    try {
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date,
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
            FROM premium_subscriptions
            WHERE payment_method != 'free_key'
              AND environment = ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([current_environment()]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching subscription chart data: " . $e->getMessage());
    }

    return $data;
}

// Get usage data for a list of license keys
function get_license_usage($license_keys)
{
    global $pdo;
    $usage = [];
    if (empty($license_keys)) return $usage;

    $usage_month = date('Y-m-01');
    $placeholders = implode(',', array_fill(0, count($license_keys), '?'));
    $tail = [$usage_month, current_environment()];

    try {
        $stmt = $pdo->prepare("SELECT license_key, scan_count FROM receipt_scan_usage WHERE license_key IN ($placeholders) AND usage_month = ? AND environment = ?");
        $stmt->execute(array_merge($license_keys, $tail));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $usage[$row['license_key']]['receipt_scans'] = (int)$row['scan_count'];
        }

        $stmt = $pdo->prepare("SELECT license_key, scan_count FROM ai_import_usage WHERE license_key IN ($placeholders) AND usage_month = ? AND environment = ?");
        $stmt->execute(array_merge($license_keys, $tail));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($usage[$row['license_key']])) $usage[$row['license_key']] = ['receipt_scans' => 0];
            $usage[$row['license_key']]['ai_imports'] = (int)$row['scan_count'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching license usage: " . $e->getMessage());
    }

    return $usage;
}

// Handle usage reset via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_usage'])) {
    header('Content-Type: application/json');

    if (!verify_csrf_token()) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }

    $license_keys = $_POST['license_keys'] ?? [];
    if (!is_array($license_keys)) $license_keys = [$license_keys];
    $license_keys = array_filter(array_map('trim', $license_keys));

    if (empty($license_keys)) {
        echo json_encode(['success' => false, 'error' => 'No license keys provided']);
        exit;
    }

    $usage_month = date('Y-m-01');
    $placeholders = implode(',', array_fill(0, count($license_keys), '?'));
    $params = array_merge($license_keys, [$usage_month, current_environment()]);

    try {
        $stmt = $pdo->prepare("UPDATE receipt_scan_usage SET scan_count = 0 WHERE license_key IN ($placeholders) AND usage_month = ? AND environment = ?");
        $stmt->execute($params);

        $stmt = $pdo->prepare("UPDATE ai_import_usage SET scan_count = 0 WHERE license_key IN ($placeholders) AND usage_month = ? AND environment = ?");
        $stmt->execute($params);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Usage reset error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

// Handle form submissions
$generated_sub_key = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $_SESSION['message'] = "Invalid request. Please try again.";
        $_SESSION['message_type'] = 'error';
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['generate_batch'])) {
        $label = trim($_POST['batch_label'] ?? '');
        $count = intval($_POST['batch_count'] ?? 0);
        $duration = intval($_POST['batch_duration'] ?? 0);

        if ($label === '' || mb_strlen($label) > 60) {
            $_SESSION['message'] = "Enter a batch label of 60 characters or fewer.";
            $_SESSION['message_type'] = 'error';
            header('Location: index.php#reseller-batches');
            exit;
        }

        // Capped at 2000. A typo in this box mints keys that cannot be un-minted without
        // deleting rows, and a reseller deal of more than 2000 is worth running twice.
        if ($count < 1 || $count > 2000) {
            $_SESSION['message'] = "Enter a key count between 1 and 2000.";
            $_SESSION['message_type'] = 'error';
            header('Location: index.php#reseller-batches');
            exit;
        }

        if ($duration < 0) $duration = 0;
        if ($duration > 24 && $duration !== 0) $duration = 24;

        $created = generate_reseller_batch($label, $count, $duration);

        $_SESSION['message'] = $created > 0
            ? "Generated $created key(s) for \"$label\". Download the CSV to send to the reseller."
            : "Could not generate the batch. Nothing was created.";
        $_SESSION['message_type'] = $created > 0 ? 'success' : 'error';

        header('Location: index.php#reseller-batches');
        exit;
    }

    if (isset($_POST['download_batch'])) {
        $label = trim($_POST['batch_label'] ?? '');
        $keys = $label === '' ? [] : get_reseller_batch_keys($label);

        if (empty($keys)) {
            $_SESSION['message'] = "That batch has no keys to download.";
            $_SESSION['message_type'] = 'error';
            header('Location: index.php#reseller-batches');
            exit;
        }

        // Sent before any page output, so the CSV is the whole response.
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $label) . '-keys.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['license_key', 'status', 'redeemed_at', 'customer_email', 'email_verified_at']);
        foreach ($keys as $k) {
            fputcsv($out, [
                $k['subscription_key'],
                get_subscription_key_status($k),
                $k['redeemed_at'] ?? '',
                $k['customer_email'] ?? '',
                $k['customer_email_verified_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    if (isset($_POST['generate_sub_key'])) {
        $email = !empty($_POST['sub_email']) ? filter_var($_POST['sub_email'], FILTER_VALIDATE_EMAIL) : null;
        $duration = intval($_POST['duration_months'] ?? 1);
        $notes = trim($_POST['notes'] ?? '');

        // Require a valid email address
        if (!$email) {
            $_SESSION['message'] = "Please enter a valid email address.";
            $_SESSION['message_type'] = 'error';
            header('Location: index.php#free-sub-keys');
            exit;
        }

        // Allow 0 for permanent, otherwise 1-24 months
        if ($duration < 0) $duration = 1;
        if ($duration > 24 && $duration !== 0) $duration = 24;

        $generated_sub_key = generate_premium_subscription_key($email, $duration, $notes);

        if ($generated_sub_key) {
            $_SESSION['generated_sub_key'] = $generated_sub_key;
            $_SESSION['sub_key_duration'] = $duration;
            $_SESSION['sub_key_email'] = $email;

            // Send email notification
            $email_sent = send_free_subscription_key_email($email, $generated_sub_key, $duration, $notes);
            $_SESSION['message'] = $email_sent
                ? "Free subscription key generated and email sent to $email!"
                : "Free subscription key generated, but failed to send email to $email.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Failed to generate subscription key.";
            $_SESSION['message_type'] = 'error';
        }
        header('Location: index.php#free-sub-keys');
        exit;
    } elseif (isset($_POST['bulk_sub_key_action'])) {
        // Handle bulk actions for subscription keys
        $action = $_POST['bulk_sub_key_action'];
        $key_ids = $_POST['selected_sub_keys'] ?? [];

        if (!empty($key_ids)) {
            $count = count($key_ids);
            $placeholders = implode(',', array_fill(0, $count, '?'));

            if ($action === 'delete') {
                try {
                    $stmt = $pdo->prepare("DELETE FROM premium_subscription_keys WHERE id IN ($placeholders)");
                    $stmt->execute($key_ids);
                    $deleted = $stmt->rowCount();
                    if ($deleted > 0) {
                        $_SESSION['message'] = "$deleted subscription key(s) deleted successfully.";
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = "No keys deleted.";
                        $_SESSION['message_type'] = 'error';
                    }
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error deleting keys.";
                    $_SESSION['message_type'] = 'error';
                }
            } elseif ($action === 'resend_email') {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM premium_subscription_keys WHERE id IN ($placeholders)");
                    $stmt->execute($key_ids);
                    $keys_to_resend = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $sent = 0;
                    $skipped = 0;
                    foreach ($keys_to_resend as $k) {
                        if (empty($k['email'])) {
                            $skipped++;
                            continue;
                        }
                        if (send_free_subscription_key_email($k['email'], $k['subscription_key'], $k['duration_months'], $k['notes'] ?? '')) {
                            $sent++;
                        }
                    }

                    $msg = "$sent email(s) resent successfully.";
                    if ($skipped > 0) {
                        $msg .= " $skipped key(s) skipped (no email address).";
                    }
                    $_SESSION['message'] = $msg;
                    $_SESSION['message_type'] = $sent > 0 ? 'success' : 'error';
                } catch (PDOException $e) {
                    error_log("Error resending license emails: " . $e->getMessage());
                    $_SESSION['message'] = "Error resending emails.";
                    $_SESSION['message_type'] = 'error';
                }
            }

            header('Location: index.php#free-sub-keys');
            exit;
        }
    } elseif (isset($_POST['bulk_subscription_action'])) {
        // Handle bulk actions for Premium subscriptions (Give Credit)
        $action = $_POST['bulk_subscription_action'];
        $subscription_ids = $_POST['selected_subscriptions'] ?? [];
        $credit_amount = floatval($_POST['credit_amount'] ?? 0);
        $credit_note = trim($_POST['credit_note'] ?? '');

        if (!empty($subscription_ids) && $action === 'give_credit' && $credit_amount > 0) {
            $count = count($subscription_ids);
            $placeholders = implode(',', array_fill(0, $count, '?'));
            $success_count = 0;
            $email_success = 0;

            try {
                // Filtered by environment so a stale form submit cannot change the other environment's subscriptions.
                $stmt = $pdo->prepare("
                    UPDATE premium_subscriptions
                    SET credit_balance = credit_balance + ?
                    WHERE id IN ($placeholders)
                      AND environment = ?
                ");
                $params = array_merge([$credit_amount], $subscription_ids, [current_environment()]);
                $stmt->execute($params);
                $success_count = $stmt->rowCount();

                // Log the credit addition for debugging
                error_log("Bulk credit: Added \$$credit_amount to " . count($subscription_ids) . " subscriptions. IDs: " . implode(',', $subscription_ids) . ". Rows affected: $success_count");

                // Send emails to affected users and verify credit was saved.
                // Env filter prevents a stale form submit from touching subs from a different environment.
                $stmt = $pdo->prepare("
                    SELECT id, email, subscription_id, credit_balance
                    FROM premium_subscriptions
                    WHERE id IN ($placeholders)
                      AND environment = ?
                ");
                $stmt->execute(array_merge($subscription_ids, [current_environment()]));
                $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Log the actual credit balances after update
                foreach ($subscriptions as $s) {
                    error_log("Bulk credit verify: Subscription {$s['subscription_id']} now has credit_balance = {$s['credit_balance']}");
                }

                foreach ($subscriptions as $sub) {
                    if (send_free_credit_email($sub['email'], $credit_amount, $credit_note, $sub['subscription_id'])) {
                        $email_success++;
                    }
                }

                $_SESSION['message'] = "\$$credit_amount credit added to $success_count subscription(s). $email_success email(s) sent.";
                $_SESSION['message_type'] = 'success';
            } catch (PDOException $e) {
                error_log("Error adding bulk credit: " . $e->getMessage());
                $_SESSION['message'] = "Error adding credit to subscriptions.";
                $_SESSION['message_type'] = 'error';
            }

            header('Location: index.php#premium-subscriptions');
            exit;
        } elseif (!empty($subscription_ids) && $action === 'resend_email') {
            $count = count($subscription_ids);
            $placeholders = implode(',', array_fill(0, $count, '?'));

            try {
                $stmt = $pdo->prepare("
                    SELECT email, subscription_id, billing_cycle, end_date
                    FROM premium_subscriptions
                    WHERE id IN ($placeholders)
                      AND environment = ?
                ");
                $stmt->execute(array_merge($subscription_ids, [current_environment()]));
                $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $sent = 0;
                foreach ($subscriptions as $sub) {
                    if (resend_subscription_id_email($sub['email'], $sub['subscription_id'], $sub['billing_cycle'], $sub['end_date'])) {
                        $sent++;
                    }
                }

                $_SESSION['message'] = "License key email resent to $sent of $count recipient(s).";
                $_SESSION['message_type'] = $sent > 0 ? 'success' : 'error';
            } catch (PDOException $e) {
                error_log("Error resending subscription emails: " . $e->getMessage());
                $_SESSION['message'] = "Error resending emails.";
                $_SESSION['message_type'] = 'error';
            }

            header('Location: index.php#premium-subscriptions');
            exit;
        } else {
            $_SESSION['message'] = "Please select subscriptions and enter a valid credit amount.";
            $_SESSION['message_type'] = 'error';
            header('Location: index.php#premium-subscriptions');
            exit;
        }
    }
}

// Check for session messages
if (isset($_SESSION['generated_sub_key'])) {
    $generated_sub_key = $_SESSION['generated_sub_key'];
    unset($_SESSION['generated_sub_key']);
}
$sub_key_duration = $_SESSION['sub_key_duration'] ?? 0;
unset($_SESSION['sub_key_duration']);
$sub_key_email = $_SESSION['sub_key_email'] ?? '';
unset($_SESSION['sub_key_email']);

// Get filter parameters
// Get data
$premium_subscriptions = get_premium_subscriptions();
$premium_subscription_keys = get_premium_subscription_keys();
$subscription_chart_data = get_subscription_chart_data();

// Reseller batches, plus the keys of whichever batch is open.
$reseller_batches = get_reseller_batches();

$batch_totals = ['keys' => 0, 'redeemed' => 0, 'captured' => 0, 'verified' => 0];
foreach ($reseller_batches as $b) {
    $batch_totals['keys']     += (int) $b['total_keys'];
    $batch_totals['redeemed'] += (int) $b['redeemed'];
    $batch_totals['captured'] += (int) $b['captured'];
    $batch_totals['verified'] += (int) $b['verified'];
}

// Get usage data and limits
$pricing_config = get_pricing_config();
$receipt_limit = $pricing_config['receipt_scan_monthly_limit'];
$ai_import_limit = $pricing_config['ai_import_monthly_limit'];

$all_sub_ids = array_column($premium_subscriptions, 'subscription_id');
$all_key_ids = array_column($premium_subscription_keys, 'subscription_key');
$usage_data = get_license_usage(array_merge($all_sub_ids, $all_key_ids));

$active_ai_subs = 0;
foreach ($premium_subscriptions as $sub) {
    if ($sub['status'] === 'active') $active_ai_subs++;
}

// Buckets for the stat cards. Expired keys are redeemed keys whose term has run out, so they're counted separately rather than inflating "Redeemed": total = redeemed + expired + available.
$unredeemed_keys = 0;
$redeemed_keys = 0;
$expired_keys = 0;
foreach ($premium_subscription_keys as $key) {
    switch (get_subscription_key_status($key)) {
        case 'available':
            $unredeemed_keys++;
            break;
        case 'expired':
            $expired_keys++;
            break;
        default:
            $redeemed_keys++;
    }
}

// Flash messages
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="../search.css">
<link rel="stylesheet" href="../../resources/styles/checkbox.css">

<style>
.btn-copy {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: 500;
    color: var(--black);
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    cursor: pointer;
    vertical-align: middle;
    transition: all 0.15s ease;
    min-width: 60px;
    text-align: center;
}
.btn-copy:hover {
    background: #e5e7eb;
    color: var(--black);
}
.btn-copy.copied {
    background: #d1fae5;
    color: #065f46;
    border-color: #6ee7b7;
}
[data-theme="dark"] .btn-copy {
    background: #1e293b;
    color: var(--white);
    border-color: #334155;
}
[data-theme="dark"] .btn-copy:hover {
    background: #334155;
    color: var(--white);
}
[data-theme="dark"] .btn-copy.copied {
    background: #064e3b;
    color: #6ee7b7;
    border-color: #065f46;
}
/* Payment provider label. Light theme reads fine as plain brand-colored text;
   in dark theme the dark brand colors (esp. PayPal navy #003087) disappear, so
   sit them on a white chip for contrast. */
.provider-chip {
    font-weight: 600;
}
[data-theme="dark"] .provider-chip {
    display: inline-block;
    background: #ffffff;
    padding: 1px 8px;
    border-radius: 4px;
}
</style>

<script>
    const subscriptionChartData = <?php echo json_encode($subscription_chart_data); ?>;
</script>
<script src="main.js"></script>

<div class="container">
    <!-- Section Tabs -->
    <div class="section-tabs">
        <button class="section-tab active" data-tab="premium-subscriptions">Premium Subscriptions</button>
        <button class="section-tab" data-tab="free-sub-keys">Free Premium Subscription Keys</button>
        <button class="section-tab" data-tab="reseller-batches">Reseller Batches</button>
    </div>

    <?php if (!empty($message)): ?>
        <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Premium Subscriptions Tab -->
    <div id="premium-subscriptions" class="tab-content active">
        <div class="stats-grid">
            <div class="stat-card active">
                <h3>Active Subscriptions</h3>
                <div class="value"><?php echo $active_ai_subs; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Subscriptions</h3>
                <div class="value"><?php echo count($premium_subscriptions); ?></div>
            </div>
        </div>

        <!-- Subscription Chart -->
        <div class="chart-container">
            <h2>Premium Subscriptions Over Time</h2>
            <canvas id="subscriptionsChart"></canvas>
        </div>

        <div class="table-container">
            <h2>Premium Subscriptions</h2>

            <div style="margin-bottom: 12px;">
                <input type="text" id="subscriptionSearch" placeholder="Search by email, ID, or username..." oninput="filterTable(this, 'subscription-table-body')" style="max-width: 350px;">
            </div>

            <!-- Bulk Actions for Premium Subscriptions -->
            <div class="bulk-actions-container" id="subscription-bulk-actions">
                <div class="selection-info">
                    <span id="subscription-selected-count">0</span> selected
                </div>
                <div class="bulk-buttons">
                    <button type="button" class="btn btn-bulk btn-premium" id="open-credit-modal" disabled>
                        Give Credit
                    </button>
                    <button type="button" class="btn btn-bulk btn-blue" id="subscription-bulk-resend" disabled>
                        Resend Email
                    </button>
                    <button type="button" class="btn btn-bulk btn-reset-usage" id="subscription-bulk-reset-usage" disabled>
                        Reset Usage
                    </button>
                </div>
            </div>

            <?php if (empty($premium_subscriptions)): ?>
                <p>No Premium subscriptions found.</p>
            <?php else: ?>
                <form id="subscription-bulk-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="bulk_subscription_action" id="bulk_subscription_action_input" value="give_credit">
                    <input type="hidden" name="credit_amount" id="credit_amount_input">
                    <input type="hidden" name="credit_note" id="credit_note_input">
                    <div class="table-responsive">
                        <table data-paginate="25">
                            <thead>
                                <tr>
                                    <th class="checkbox-column">
                                        <div class="checkbox">
                                            <input type="checkbox" id="subscription-select-all">
                                            <label for="subscription-select-all"></label>
                                        </div>
                                    </th>
                                    <th>License Key</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Plan</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Receipt Scans</th>
                                    <th>AI Imports</th>
                                    <th>Total Spent</th>
                                    <th>Credit</th>
                                </tr>
                            </thead>
                            <tbody id="subscription-table-body">
                                <?php foreach ($premium_subscriptions as $sub): ?>
                                    <tr>
                                        <td class="checkbox-column">
                                            <div class="checkbox">
                                                <input type="checkbox" name="selected_subscriptions[]" value="<?php echo $sub['id']; ?>" class="subscription-checkbox" id="sub-<?php echo $sub['id']; ?>" data-license-key="<?php echo htmlspecialchars($sub['subscription_id']); ?>">
                                                <label for="sub-<?php echo $sub['id']; ?>"></label>
                                            </div>
                                        </td>
                                        <td class="key-field"><?php echo htmlspecialchars($sub['subscription_id']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['username'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                        <?php
                                            // Shows the latest completed payment, since s.amount can differ from it.
                                            $billedAmount = $sub['last_paid_amount'] ?? $sub['amount'];
                                        ?>
                                        <td><?php echo ucfirst($sub['billing_cycle']); ?> - $<?php echo number_format((float) $billedAmount, 2); ?></td>
                                        <td>
                                            <?php
                                            $providerNames = ['paypal' => 'PayPal', 'stripe' => 'Stripe', 'square' => 'Square'];
                                            $providerColors = ['paypal' => '#003087', 'stripe' => '#635bff', 'square' => '#006aff'];
                                            $method = strtolower($sub['payment_method'] ?? '');
                                            $providerName = $providerNames[$method] ?? ucfirst($sub['payment_method'] ?? 'N/A');
                                            $providerColor = $providerColors[$method] ?? '#6b7280';
                                            ?>
                                            <span class="provider-chip" style="color: <?php echo $providerColor; ?>;"><?php echo $providerName; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $sub['status']; ?>">
                                                <?php echo ucfirst($sub['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($sub['start_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($sub['end_date'])); ?></td>
                                        <?php
                                            $sub_key = $sub['subscription_id'];
                                            $scans = $usage_data[$sub_key]['receipt_scans'] ?? 0;
                                            $imports = $usage_data[$sub_key]['ai_imports'] ?? 0;
                                        ?>
                                        <td><span class="usage-count <?php echo $scans >= $receipt_limit ? 'usage-maxed' : ''; ?>"><?php echo $scans; ?> / <?php echo $receipt_limit; ?></span></td>
                                        <td><span class="usage-count <?php echo $imports >= $ai_import_limit ? 'usage-maxed' : ''; ?>"><?php echo $imports; ?> / <?php echo $ai_import_limit; ?></span></td>
                                        <td>$<?php echo number_format((float) ($sub['total_spent'] ?? 0), 2); ?></td>
                                        <td>$<?php echo number_format($sub['credit_balance'] ?? 0, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Credit Modal -->
    <div id="credit-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Give Free Credit</h3>
                <button type="button" class="modal-close" id="close-credit-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Add free credit to <strong><span id="modal-selected-count">0</span></strong> selected subscription(s).</p>
                <div class="form-group">
                    <label for="modal-credit-amount">Credit Amount ($)</label>
                    <input type="number" id="modal-credit-amount" min="0.01" step="0.01" placeholder="e.g., 5.00" required>
                </div>
                <div class="form-group">
                    <label for="modal-credit-note">Note (optional)</label>
                    <textarea id="modal-credit-note" rows="3" placeholder="e.g., Thank you for being a loyal customer!"></textarea>
                </div>
                <p class="modal-note">An email will be sent to each user notifying them of the free credit.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-credit-modal">Cancel</button>
                <button type="button" class="btn btn-premium" id="confirm-give-credit">Give Credit</button>
            </div>
        </div>
    </div>

    <!-- Free Subscription Keys Tab -->
    <div id="free-sub-keys" class="tab-content">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Generated</h3>
                <div class="value"><?php echo count($premium_subscription_keys); ?></div>
            </div>
            <div class="stat-card pending">
                <h3>Redeemed Keys</h3>
                <div class="value"><?php echo $redeemed_keys; ?></div>
            </div>
            <div class="stat-card free">
                <h3>Available Keys</h3>
                <div class="value"><?php echo $unredeemed_keys; ?></div>
            </div>
            <div class="stat-card">
                <h3>Expired Keys</h3>
                <div class="value"><?php echo $expired_keys; ?></div>
            </div>
        </div>

        <div class="table-container" style="padding: 20px 20px 30px 20px;">
            <h2>Generate Free Subscription Key</h2>
            <?php if ($generated_sub_key): ?>
                <div class="key-display">
                    <strong>Key Generated:</strong><br>
                    <code><?php echo htmlspecialchars($generated_sub_key); ?></code><br>
                    <small>Duration: <?php echo $sub_key_duration == 0 ? 'Permanent' : $sub_key_duration . ' month(s)'; ?> | For: <?php echo htmlspecialchars($sub_key_email); ?></small>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="generate-form-grid">
                    <div class="form-group">
                        <label for="sub_email">Recipient Email</label>
                        <input type="email" id="sub_email" name="sub_email" placeholder="customer@example.com" required>
                    </div>
                    <div class="form-group">
                        <div class="label-with-tip">
                            <label for="duration_months">Duration</label>
                            <span class="info-tip" tabindex="0" role="button" aria-label="What does Duration mean?" aria-describedby="duration-tip">
                                <span class="info-tip-icon" aria-hidden="true">i</span>
                                <span class="info-tip-tooltip" id="duration-tip" role="tooltip">The countdown starts when the recipient redeems the key, not when you generate it. A key that is never redeemed never expires.</span>
                            </span>
                        </div>
                        <select id="duration_months" name="duration_months">
                            <option value="1">1 month</option>
                            <option value="3">3 months</option>
                            <option value="6">6 months</option>
                            <option value="12">12 months</option>
                            <option value="0">Permanent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes (optional)</label>
                        <input type="text" id="notes" name="notes" placeholder="e.g., Giveaway winner">
                         <p style="position: absolute; margin: 5px 0 20px; color: var(--admin-text);">This will also appear in the recipient's email.</p>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="generate_sub_key" class="btn btn-premium" style="margin-top: 24px;">Generate Key</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-container">
            <h2>Free Subscription Keys</h2>

            <div style="margin-bottom: 12px;">
                <input type="text" id="freeKeysSearch" placeholder="Search by key, email, or notes..." oninput="filterTable(this, 'free-keys-table-body')" style="max-width: 350px;">
            </div>

            <!-- Bulk Actions for Subscription Keys -->
            <div class="bulk-actions-container" id="sub-key-bulk-actions">
                <div class="selection-info">
                    <span id="sub-key-selected-count">0</span> selected
                </div>
                <div class="bulk-buttons">
                    <button type="button" class="btn btn-bulk btn-premium" id="sub-key-bulk-resend" disabled>Resend Email</button>
                    <button type="button" class="btn btn-bulk btn-reset-usage" id="sub-key-bulk-reset-usage" disabled>Reset Usage</button>
                    <button type="button" class="btn btn-bulk btn-delete" id="sub-key-bulk-delete" data-action="delete" disabled>Delete Selected</button>
                </div>
            </div>

            <?php if (empty($premium_subscription_keys)): ?>
                <p>No free subscription keys generated yet.</p>
            <?php else: ?>
                <form id="sub-key-bulk-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="bulk_sub_key_action" id="bulk_sub_key_action_input">
                    <div class="table-responsive">
                        <table data-paginate="25">
                            <thead>
                                <tr>
                                    <th class="checkbox-column">
                                        <div class="checkbox">
                                            <input type="checkbox" id="sub-key-select-all">
                                            <label for="sub-key-select-all"></label>
                                        </div>
                                    </th>
                                    <th>Key</th>
                                    <th>Duration</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Receipt Scans</th>
                                    <th>AI Imports</th>
                                    <th>Created</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="free-keys-table-body">
                                <?php foreach ($premium_subscription_keys as $key): ?>
                                    <?php $key_status = get_subscription_key_status($key); ?>
                                    <tr class="<?php echo $key['redeemed_at'] ? 'redeemed-row' : ''; ?>">
                                        <td class="checkbox-column">
                                            <div class="checkbox">
                                                <input type="checkbox" name="selected_sub_keys[]" value="<?php echo $key['id']; ?>" class="sub-key-checkbox" id="sub-key-<?php echo $key['id']; ?>" data-license-key="<?php echo htmlspecialchars($key['subscription_key']); ?>">
                                                <label for="sub-key-<?php echo $key['id']; ?>"></label>
                                            </div>
                                        </td>
                                        <td class="key-field">
                                            <?php echo htmlspecialchars($key['subscription_key']); ?>
                                            <button type="button" class="btn-copy" onclick="copyToClipboard('<?php echo htmlspecialchars($key['subscription_key'], ENT_QUOTES); ?>', this)" title="Copy key">Copy</button>
                                        </td>
                                        <td class="col-nowrap"><?php echo $key['duration_months'] == 0 ? '<span style="color:#2563eb;font-weight:500;">Permanent</span>' : $key['duration_months'] . ' month' . ($key['duration_months'] > 1 ? 's' : ''); ?></td>
                                        <td><?php echo $key['email'] ? htmlspecialchars($key['email']) : '<span style="color:var(--admin-text);">Any user</span>'; ?></td>
                                        <td>
                                            <?php if ($key_status === 'expired'): ?>
                                                <span class="badge badge-expired" title="Redeemed <?php echo date('M j, Y', strtotime($key['redeemed_at'])); ?><?php echo !empty($key['subscription_end_date']) ? ' &middot; ended ' . date('M j, Y', strtotime($key['subscription_end_date'])) : ''; ?>">Expired</span>
                                            <?php elseif ($key_status === 'redeemed'): ?>
                                                <span class="badge badge-redeemed">Redeemed</span>
                                            <?php else: ?>
                                                <span class="badge badge-free">Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php
                                            $fk = $key['subscription_key'];
                                            $fk_scans = $usage_data[$fk]['receipt_scans'] ?? 0;
                                            $fk_imports = $usage_data[$fk]['ai_imports'] ?? 0;
                                            $fk_redeemed = !empty($key['redeemed_at']);
                                        ?>
                                        <td>
                                            <?php if ($fk_redeemed): ?>
                                                <span class="usage-count <?php echo $fk_scans >= $receipt_limit ? 'usage-maxed' : ''; ?>"><?php echo $fk_scans; ?> / <?php echo $receipt_limit; ?></span>
                                            <?php else: ?>
                                                <span class="usage-na">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fk_redeemed): ?>
                                                <span class="usage-count <?php echo $fk_imports >= $ai_import_limit ? 'usage-maxed' : ''; ?>"><?php echo $fk_imports; ?> / <?php echo $ai_import_limit; ?></span>
                                            <?php else: ?>
                                                <span class="usage-na">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($key['created_at'])); ?></td>
                                        <td><?php echo $key['notes'] ? htmlspecialchars($key['notes']) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reseller Batches Tab -->
    <div id="reseller-batches" class="tab-content">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Batch Keys</h3>
                <div class="value"><?php echo $batch_totals['keys']; ?></div>
            </div>
            <div class="stat-card pending">
                <h3>Redeemed</h3>
                <div class="value"><?php echo $batch_totals['redeemed']; ?></div>
            </div>
            <div class="stat-card free">
                <h3>Emails Captured</h3>
                <div class="value"><?php echo $batch_totals['captured']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Emails Verified</h3>
                <div class="value"><?php echo $batch_totals['verified']; ?></div>
            </div>
        </div>

        <div class="table-container" style="padding: 20px 20px 30px 20px;">
            <h2>Generate Batch</h2>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 16px;">
                Keys for a reseller to hand out. No email is attached to them, so the app asks each
                buyer for one when they redeem. Download the CSV and send that to the reseller.
            </p>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="generate-form-grid">
                    <div class="form-group">
                        <label for="batch_label">Batch label</label>
                        <input type="text" id="batch_label" name="batch_label" maxlength="60"
                               placeholder="StackSocial Aug 2026" required>
                    </div>
                    <div class="form-group">
                        <label for="batch_count">How many keys</label>
                        <input type="number" id="batch_count" name="batch_count" min="1" max="2000"
                               value="100" required>
                    </div>
                    <div class="form-group">
                        <label for="batch_duration">Duration</label>
                        <select id="batch_duration" name="batch_duration">
                            <option value="0" selected>Lifetime</option>
                            <option value="12">12 months</option>
                            <option value="24">24 months</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="generate_batch" class="btn btn-premium" style="margin-top: 24px;">Generate Batch</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-container">
            <h2>Batches</h2>
            <?php if (empty($reseller_batches)): ?>
                <p style="padding: 20px; color: var(--text-secondary);">No reseller batches yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Created</th>
                            <th>Keys</th>
                            <th>Redeemed</th>
                            <th>Emails captured</th>
                            <th>Verified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reseller_batches as $b): ?>
                            <?php
                            $total = (int) $b['total_keys'];
                            $redeemed = (int) $b['redeemed'];
                            $captured = (int) $b['captured'];
                            $verified = (int) $b['verified'];
                            // Capture is measured against REDEEMED, not against the whole batch.
                            $redeemPct = $total > 0 ? round($redeemed / $total * 100) : 0;
                            $capturePct = $redeemed > 0 ? round($captured / $redeemed * 100) : 0;
                            $verifyPct = $captured > 0 ? round($verified / $captured * 100) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($b['batch_label']); ?></strong></td>
                                <td><?php echo date('M j, Y', strtotime($b['created_at'])); ?></td>
                                <td><?php echo $total; ?></td>
                                <td><?php echo $redeemed; ?> <span style="color: var(--text-secondary);">(<?php echo $redeemPct; ?>%)</span></td>
                                <td><?php echo $captured; ?> <span style="color: var(--text-secondary);">(<?php echo $capturePct; ?>% of redeemed)</span></td>
                                <td><?php echo $verified; ?> <span style="color: var(--text-secondary);">(<?php echo $verifyPct; ?>% of captured)</span></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="batch_label" value="<?php echo htmlspecialchars($b['batch_label']); ?>">
                                        <button type="submit" name="download_batch" class="btn btn-secondary">Download CSV</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(function() {
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.textContent = 'Copy';
            btn.classList.remove('copied');
        }, 1500);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Tab switching is handled centrally by admin/section-tabs.js

    // Subscription Key Bulk Selection
    const subKeySelectAll = document.getElementById('sub-key-select-all');
    const subKeyCheckboxes = document.querySelectorAll('.sub-key-checkbox');
    const subKeySelectedCount = document.getElementById('sub-key-selected-count');
    const subKeyBulkDelete = document.getElementById('sub-key-bulk-delete');
    const subKeyBulkResend = document.getElementById('sub-key-bulk-resend');
    const subKeyBulkResetUsage = document.getElementById('sub-key-bulk-reset-usage');
    const subKeyBulkForm = document.getElementById('sub-key-bulk-form');
    const subKeyBulkActionInput = document.getElementById('bulk_sub_key_action_input');

    function updateSubKeySelection() {
        const checked = document.querySelectorAll('.sub-key-checkbox:checked');
        const count = checked.length;
        subKeySelectedCount.textContent = count;
        subKeyBulkDelete.disabled = count === 0;
        if (subKeyBulkResend) subKeyBulkResend.disabled = count === 0;
        if (subKeyBulkResetUsage) subKeyBulkResetUsage.disabled = count === 0;
    }

    if (subKeySelectAll) {
        subKeySelectAll.addEventListener('change', function() {
            subKeyCheckboxes.forEach(cb => cb.checked = this.checked);
            updateSubKeySelection();
        });
    }

    subKeyCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateSubKeySelection();
            // Update select all checkbox state
            if (subKeySelectAll) {
                const allChecked = document.querySelectorAll('.sub-key-checkbox:checked').length === subKeyCheckboxes.length;
                subKeySelectAll.checked = allChecked && subKeyCheckboxes.length > 0;
            }
        });
    });

    if (subKeyBulkDelete) {
        subKeyBulkDelete.addEventListener('click', function() {
            const checked = document.querySelectorAll('.sub-key-checkbox:checked');
            if (checked.length === 0) return;

            if (confirm(`Are you sure you want to delete ${checked.length} subscription key(s)?`)) {
                subKeyBulkActionInput.value = 'delete';
                subKeyBulkForm.submit();
            }
        });
    }

    if (subKeyBulkResend) {
        subKeyBulkResend.addEventListener('click', function() {
            const checked = document.querySelectorAll('.sub-key-checkbox:checked');
            if (checked.length === 0) return;

            if (confirm(`Resend license key email to ${checked.length} recipient(s)?`)) {
                subKeyBulkActionInput.value = 'resend_email';
                subKeyBulkForm.submit();
            }
        });
    }

    // Premium Subscription Bulk Selection
    const subscriptionSelectAll = document.getElementById('subscription-select-all');
    const subscriptionCheckboxes = document.querySelectorAll('.subscription-checkbox');
    const subscriptionSelectedCount = document.getElementById('subscription-selected-count');
    const openCreditModalBtn = document.getElementById('open-credit-modal');
    const subscriptionBulkResend = document.getElementById('subscription-bulk-resend');
    const subscriptionBulkResetUsage = document.getElementById('subscription-bulk-reset-usage');
    const subscriptionBulkForm = document.getElementById('subscription-bulk-form');
    const bulkSubscriptionActionInput = document.getElementById('bulk_subscription_action_input');
    const creditAmountInput = document.getElementById('credit_amount_input');
    const creditNoteInput = document.getElementById('credit_note_input');

    // Modal elements
    const creditModal = document.getElementById('credit-modal');
    const closeCreditModalBtn = document.getElementById('close-credit-modal');
    const cancelCreditModalBtn = document.getElementById('cancel-credit-modal');
    const confirmGiveCreditBtn = document.getElementById('confirm-give-credit');
    const modalSelectedCount = document.getElementById('modal-selected-count');
    const modalCreditAmount = document.getElementById('modal-credit-amount');
    const modalCreditNote = document.getElementById('modal-credit-note');

    function updateSubscriptionSelection() {
        const checked = document.querySelectorAll('.subscription-checkbox:checked');
        const count = checked.length;
        subscriptionSelectedCount.textContent = count;
        if (openCreditModalBtn) {
            openCreditModalBtn.disabled = count === 0;
        }
        if (subscriptionBulkResend) {
            subscriptionBulkResend.disabled = count === 0;
        }
        if (subscriptionBulkResetUsage) {
            subscriptionBulkResetUsage.disabled = count === 0;
        }
    }

    if (subscriptionSelectAll) {
        subscriptionSelectAll.addEventListener('change', function() {
            subscriptionCheckboxes.forEach(cb => cb.checked = this.checked);
            updateSubscriptionSelection();
        });
    }

    subscriptionCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateSubscriptionSelection();
            // Update select all checkbox state
            if (subscriptionSelectAll) {
                const checkedCount = document.querySelectorAll('.subscription-checkbox:checked').length;
                const allChecked = checkedCount === subscriptionCheckboxes.length;
                subscriptionSelectAll.checked = allChecked && subscriptionCheckboxes.length > 0;
                subscriptionSelectAll.indeterminate = checkedCount > 0 && !allChecked;
            }
        });
    });

    // Modal functionality
    function openModal() {
        const count = document.querySelectorAll('.subscription-checkbox:checked').length;
        if (count === 0) return;
        modalSelectedCount.textContent = count;
        modalCreditAmount.value = '';
        modalCreditNote.value = '';
        creditModal.style.display = 'flex';
        modalCreditAmount.focus();
    }

    function closeModal() {
        creditModal.style.display = 'none';
    }

    if (openCreditModalBtn) {
        openCreditModalBtn.addEventListener('click', openModal);
    }

    if (subscriptionBulkResend) {
        subscriptionBulkResend.addEventListener('click', function() {
            const checked = document.querySelectorAll('.subscription-checkbox:checked');
            if (checked.length === 0) return;

            if (confirm(`Resend license key email to ${checked.length} recipient(s)?`)) {
                bulkSubscriptionActionInput.value = 'resend_email';
                creditAmountInput.value = '0';
                subscriptionBulkForm.submit();
            }
        });
    }

    if (closeCreditModalBtn) {
        closeCreditModalBtn.addEventListener('click', closeModal);
    }

    if (cancelCreditModalBtn) {
        cancelCreditModalBtn.addEventListener('click', closeModal);
    }

    // Close modal on overlay click (only if mousedown also started on backdrop)
    let modalMouseDownTarget = null;
    if (creditModal) {
        creditModal.addEventListener('mousedown', function(e) {
            modalMouseDownTarget = e.target;
        });
        creditModal.addEventListener('click', function(e) {
            if (e.target === creditModal && modalMouseDownTarget === creditModal) {
                closeModal();
            }
        });
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && creditModal && creditModal.style.display === 'flex') {
            closeModal();
        }
    });

    if (confirmGiveCreditBtn) {
        confirmGiveCreditBtn.addEventListener('click', function() {
            const amount = parseFloat(modalCreditAmount.value);
            const note = modalCreditNote.value.trim();
            const checkedCount = document.querySelectorAll('.subscription-checkbox:checked').length;

            if (isNaN(amount) || amount <= 0) {
                alert('Please enter a valid credit amount greater than 0.');
                modalCreditAmount.focus();
                return;
            }

            if (confirm(`Are you sure you want to give $${amount.toFixed(2)} credit to ${checkedCount} subscription(s)?`)) {
                bulkSubscriptionActionInput.value = 'give_credit';
                creditAmountInput.value = amount;
                creditNoteInput.value = note;
                subscriptionBulkForm.submit();
            }
        });
    }

    // Allow Enter key in credit amount field to submit
    if (modalCreditAmount) {
        modalCreditAmount.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmGiveCreditBtn.click();
            }
        });
    }

    // Reset Usage helper
    function resetUsage(checkboxSelector) {
        const checked = document.querySelectorAll(checkboxSelector + ':checked');
        if (checked.length === 0) return;

        if (!confirm(`Reset usage for ${checked.length} selected license(s)? This resets the current month's receipt scan and AI import counts to 0.`)) return;

        const formData = new FormData();
        formData.append('reset_usage', '1');
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        checked.forEach(cb => formData.append('license_keys[]', cb.dataset.licenseKey));

        fetch('index.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(() => alert('Network error resetting usage.'));
    }

    if (subscriptionBulkResetUsage) {
        subscriptionBulkResetUsage.addEventListener('click', () => resetUsage('.subscription-checkbox'));
    }

    if (subKeyBulkResetUsage) {
        subKeyBulkResetUsage.addEventListener('click', () => resetUsage('.sub-key-checkbox'));
    }
});

function filterTable(input, tbodyId) {
    const filter = input.value.toLowerCase();
    const tbody = document.getElementById(tbodyId);
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        row.classList.remove('pg-hidden');
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
    // Reset pagination after filtering
    const table = tbody.closest('table');
    if (table && table._paginator) table._paginator.reset();
}
</script>
