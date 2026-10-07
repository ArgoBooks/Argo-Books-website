<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Generate CSRF token if not present (used by the lock/unlock/revert forms in
// the Companies tab and by the existing Failed & Refunds admin actions).
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set page variables for the header
$page_title = "Payment Portal";
$page_description = "Monitor portal payments, companies, invoices, and payment analytics";

global $pdo;

// Determine view environment (production vs sandbox)
$view_env = $_GET['view_env'] ?? 'production';
if ($view_env !== 'sandbox') {
    $view_env = 'production';
}

// Only constant strings are assigned here and $view_env is validated above, so interpolating these fragments into SQL carries no user input.
if ($view_env === 'sandbox') {
    $env_sql = "(payment_environment IS NULL OR payment_environment != 'production')";
    $env_sql_p = "(p.payment_environment IS NULL OR p.payment_environment != 'production')";
    $env_sql_c = "(c.environment IS NULL OR c.environment != 'production')";
    $env_sql_i = "(i.environment IS NULL OR i.environment != 'production')";
} else {
    $env_sql = "(payment_environment = 'production')";
    $env_sql_p = "(p.payment_environment = 'production')";
    $env_sql_c = "(c.environment = 'production')";
    $env_sql_i = "(i.environment = 'production')";
}

// --- DATA QUERIES --------------------------------------------------------------------------------

// --- Overview Stats ---
$total_revenue = 0;
$monthly_revenue = 0;
$total_fees = 0;
$active_companies = 0;
$total_invoices = 0;
$overdue_invoices = 0;

try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM portal_payments WHERE status = 'completed' AND $env_sql");
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM portal_payments WHERE status = 'completed' AND $env_sql AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $monthly_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COALESCE(SUM(processing_fee), 0) as total FROM portal_payments WHERE status = 'completed' AND $env_sql");
    $total_fees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM portal_companies c WHERE $env_sql_c");
    $active_companies = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM portal_invoices i WHERE $env_sql_i");
    $total_invoices = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM portal_invoices i WHERE i.status = 'overdue' AND $env_sql_i");
    $overdue_invoices = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    error_log("Payment stats error: " . $e->getMessage());
}

// --- Daily Revenue (last 30 days) ---
$daily_revenue = [];
try {
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, SUM(amount) as revenue
        FROM portal_payments
        WHERE status = 'completed' AND $env_sql AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $daily_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Daily revenue error: " . $e->getMessage());
}

// --- Payment Method Distribution ---
$method_distribution = [];
try {
    $stmt = $pdo->query("
        SELECT payment_method, COUNT(*) as count, SUM(amount) as total
        FROM portal_payments
        WHERE status = 'completed' AND $env_sql
        GROUP BY payment_method
    ");
    $method_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Method distribution error: " . $e->getMessage());
}

// --- Invoice Status Distribution ---
$invoice_statuses = [];
try {
    $stmt = $pdo->query("
        SELECT i.status, COUNT(*) as count
        FROM portal_invoices i
        WHERE $env_sql_i
        GROUP BY i.status
    ");
    $invoice_statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Invoice status error: " . $e->getMessage());
}

// --- Recent Payments (last 10) ---
$recent_payments = [];
try {
    $stmt = $pdo->query("
        SELECT p.created_at, c.company_name, p.customer_name, p.amount,
               p.currency, p.payment_method, p.status, p.payment_environment
        FROM portal_payments p
        LEFT JOIN portal_companies c ON p.company_id = c.id
        WHERE $env_sql_p
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent payments error: " . $e->getMessage());
}

// --- All Transactions (for Transactions tab) ---
$transactions = [];
$tx_search = $_GET['tx_search'] ?? '';
$tx_method = $_GET['tx_method'] ?? '';
$tx_status = $_GET['tx_status'] ?? '';
$tx_company = $_GET['tx_company'] ?? '';
try {
    $query = "
        SELECT p.*, c.company_name
        FROM portal_payments p
        LEFT JOIN portal_companies c ON p.company_id = c.id
        WHERE $env_sql_p
    ";
    $params = [];

    if (!empty($tx_search)) {
        $query .= " AND (p.customer_name LIKE ? OR p.reference_number LIKE ? OR c.company_name LIKE ?)";
        $search_param = '%' . $tx_search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    if (!empty($tx_method)) {
        $query .= " AND p.payment_method = ?";
        $params[] = $tx_method;
    }
    if (!empty($tx_status)) {
        $query .= " AND p.status = ?";
        $params[] = $tx_status;
    }
    if (!empty($tx_company)) {
        $query .= " AND c.id = ?";
        $params[] = $tx_company;
    }
    $query .= " ORDER BY p.created_at DESC LIMIT 200";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Transactions error: " . $e->getMessage());
}

// --- Companies ---
$companies = [];
try {
    $stmt = $pdo->query("
        SELECT c.*,
               COUNT(CASE WHEN $env_sql_p THEN p.id ELSE NULL END) as total_payments,
               COALESCE(SUM(CASE WHEN p.status = 'completed' AND $env_sql_p THEN p.amount ELSE 0 END), 0) as total_revenue,
               MAX(CASE WHEN $env_sql_p THEN p.created_at ELSE NULL END) as last_payment_date
        FROM portal_companies c
        LEFT JOIN portal_payments p ON p.company_id = c.id
        WHERE $env_sql_c
        GROUP BY c.id
        ORDER BY total_revenue DESC
    ");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Companies error: " . $e->getMessage());
}

// --- Per-company email change history (last 10 per company) ---
$email_changes_by_company = [];
try {
    $stmt = $pdo->query("
        SELECT id, company_id, old_email, new_email, state,
               created_at, completed_at, reverted_at, revert_until,
               cancel_token IS NOT NULL AS has_revert_token
        FROM email_change_requests
        ORDER BY created_at DESC
    ");
    foreach ($stmt as $row) {
        $cid = (int)$row['company_id'];
        if (!isset($email_changes_by_company[$cid])) $email_changes_by_company[$cid] = [];
        if (count($email_changes_by_company[$cid]) >= 10) continue;
        $email_changes_by_company[$cid][] = $row;
    }
} catch (PDOException $e) {
    error_log("Email changes error: " . $e->getMessage());
}

// --- Invoices (for Invoices tab) ---
$invoices = [];
$inv_search = $_GET['inv_search'] ?? '';
$inv_status = $_GET['inv_status'] ?? '';
$inv_company = $_GET['inv_company'] ?? '';

try {
    $query = "
        SELECT i.*, c.company_name,
               COALESCE(SUM(CASE WHEN p.status = 'completed' AND $env_sql_p THEN p.amount ELSE 0 END), 0) as paid_amount
        FROM portal_invoices i
        LEFT JOIN portal_companies c ON i.company_id = c.id
        LEFT JOIN portal_payments p ON p.invoice_id = i.id
        WHERE $env_sql_i
    ";
    $params = [];

    if (!empty($inv_search)) {
        $query .= " AND (i.customer_name LIKE ? OR i.invoice_id LIKE ? OR c.company_name LIKE ?)";
        $search_param = '%' . $inv_search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    if (!empty($inv_status)) {
        $query .= " AND i.status = ?";
        $params[] = $inv_status;
    }
    if (!empty($inv_company)) {
        $query .= " AND c.id = ?";
        $params[] = $inv_company;
    }

    $query .= " GROUP BY i.id ORDER BY i.created_at DESC LIMIT 200";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Invoices error: " . $e->getMessage());
}

// --- Revenue Analytics ---
$revenue_by_company = [];
$revenue_by_method_monthly = [];
$avg_transaction_trend = [];
$fees_trend = [];

try {
    // Top 10 companies by revenue
    $stmt = $pdo->query("
        SELECT c.company_name, SUM(p.amount) as revenue
        FROM portal_payments p
        JOIN portal_companies c ON p.company_id = c.id
        WHERE p.status = 'completed' AND $env_sql_p
        GROUP BY c.id
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $revenue_by_company = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue by method per month (last 12 months)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, payment_method, SUM(amount) as revenue
        FROM portal_payments
        WHERE status = 'completed' AND $env_sql AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month, payment_method
        ORDER BY month ASC
    ");
    $revenue_by_method_monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Average transaction value per month
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, AVG(amount) as avg_amount, COUNT(*) as count
        FROM portal_payments
        WHERE status = 'completed' AND $env_sql AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $avg_transaction_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Processing fees per month
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(processing_fee) as fees, SUM(amount) as revenue
        FROM portal_payments
        WHERE status = 'completed' AND $env_sql AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $fees_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Revenue analytics error: " . $e->getMessage());
}

// Revenue summary metrics
$total_tx_count = 0;
$avg_payment_size = 0;
$prev_month_revenue = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count, AVG(amount) as avg_amount FROM portal_payments WHERE status = 'completed' AND $env_sql");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_tx_count = $row['count'];
    $avg_payment_size = $row['avg_amount'] ?? 0;

    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM portal_payments WHERE status = 'completed' AND $env_sql AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $prev_month_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    error_log("Revenue summary error: " . $e->getMessage());
}

$mom_growth = ($prev_month_revenue > 0) ? (($monthly_revenue - $prev_month_revenue) / $prev_month_revenue * 100) : 0;
$fee_percentage = ($total_revenue > 0) ? ($total_fees / $total_revenue * 100) : 0;

// --- Failed Payments & Refunds ---
$failed_payments = [];
$refunded_payments = [];
$total_failed = 0;
$total_refunded = 0;
$total_refund_amount = 0;
$failure_rate = 0;

try {
    $stmt = $pdo->query("
        SELECT p.created_at, c.company_name, p.customer_name, p.amount,
               p.currency, p.payment_method, p.status,
               p.provider_payment_id, p.payment_environment
        FROM portal_payments p
        LEFT JOIN portal_companies c ON p.company_id = c.id
        WHERE p.status = 'failed' AND $env_sql_p
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $failed_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT p.created_at, c.company_name, p.customer_name, p.amount,
               p.currency, p.payment_method, p.provider_payment_id,
               p.payment_environment
        FROM portal_payments p
        LEFT JOIN portal_companies c ON p.company_id = c.id
        WHERE p.status = 'refunded' AND $env_sql_p
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $refunded_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM portal_payments WHERE status = 'failed' AND $env_sql");
    $total_failed = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM portal_payments WHERE status = 'refunded' AND $env_sql");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_refunded = $row['count'];
    $total_refund_amount = $row['total'];

    // --- In-flight refund requests (state machine view) ---
    $inflight_refunds = [];
    $inflight_count = 0;
    $held_for_review_count = 0;
    try {
        // INNER JOIN + env filter so the sandbox/production toggle scopes refund_requests the same way it scopes payments/companies above.
        $stmt = $pdo->query("
            SELECT r.id, r.invoice_number, r.customer_name, r.amount_cents, r.currency,
                   r.provider, r.state, r.velocity_tier, r.state_reason,
                   r.created_at, r.cooling_off_until,
                   c.company_name, c.environment
            FROM refund_requests r
            INNER JOIN portal_companies c ON c.id = r.company_id
            WHERE r.state IN ('pending_code','code_verified','cooling_off','processing','cancelled','failed')
              AND $env_sql_c
            ORDER BY r.created_at DESC
            LIMIT 100
        ");
        $inflight_refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $inflight_count = (int)$pdo->query("
            SELECT COUNT(*) FROM refund_requests r
            INNER JOIN portal_companies c ON c.id = r.company_id
            WHERE r.state IN ('pending_code','code_verified','cooling_off','processing')
              AND $env_sql_c
        ")->fetchColumn();
        $held_for_review_count = (int)$pdo->query("
            SELECT COUNT(*) FROM refund_requests r
            INNER JOIN portal_companies c ON c.id = r.company_id
            WHERE r.state IN ('cooling_off','failed')
              AND r.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
              AND $env_sql_c
        ")->fetchColumn();
    } catch (PDOException $e) {
        error_log('Refund requests admin query: ' . $e->getMessage());
    }

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM portal_payments WHERE $env_sql");
    $all_payments_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $failure_rate = ($all_payments_count > 0) ? ($total_failed / $all_payments_count * 100) : 0;
} catch (PDOException $e) {
    error_log("Failed payments error: " . $e->getMessage());
}

// --- Company list for filter dropdowns ---
$company_options = [];
try {
    $stmt = $pdo->query("SELECT id, company_name FROM portal_companies c WHERE $env_sql_c ORDER BY company_name ASC");
    $company_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Company options error: " . $e->getMessage());
}

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="style.css">

<div class="container payments-page">

    <!-- Environment Toggle -->
    <?php $current_tab = $_GET['tab'] ?? ''; $tab_param = !empty($current_tab) ? '&tab=' . urlencode($current_tab) : ''; ?>
    <div class="control-bar">
        <div class="control-group">
            <span class="control-label">Environment:</span>
            <div class="control-pills">
                <a href="?view_env=production<?php echo $tab_param; ?>" class="control-pill env-toggle-production <?php echo $view_env === 'production' ? 'active' : ''; ?>">Production</a>
                <a href="?view_env=sandbox<?php echo $tab_param; ?>" class="control-pill env-toggle-sandbox <?php echo $view_env === 'sandbox' ? 'active' : ''; ?>">Sandbox</a>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="section-tabs">
        <button class="section-tab active" data-tab="overview">Overview</button>
        <button class="section-tab" data-tab="transactions">Transactions</button>
        <button class="section-tab" data-tab="companies">Companies</button>
        <button class="section-tab" data-tab="invoices">Invoices</button>
        <button class="section-tab" data-tab="revenue">Payment Analytics</button>
        <button class="section-tab" data-tab="failures">Failed & Refunds</button>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 1: OVERVIEW -->
    <!-- ============================================================ -->
    <div id="overview" class="tab-content active">
        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Payments</h3>
                <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="subtext">All time (CAD)</div>
            </div>
            <div class="stat-card">
                <h3>Payments This Month</h3>
                <div class="stat-value">$<?php echo number_format($monthly_revenue, 2); ?></div>
                <div class="subtext">Last 30 days</div>
            </div>
            <div class="stat-card">
                <h3>Processing Fees</h3>
                <div class="stat-value">$<?php echo number_format($total_fees, 2); ?></div>
                <div class="subtext">Total collected</div>
            </div>
            <div class="stat-card">
                <h3>Active Companies</h3>
                <div class="stat-value"><?php echo number_format($active_companies); ?></div>
                <div class="subtext">Portal businesses</div>
            </div>
            <div class="stat-card">
                <h3>Total Invoices</h3>
                <div class="stat-value"><?php echo number_format($total_invoices); ?></div>
                <div class="subtext">All statuses</div>
            </div>
            <div class="stat-card">
                <h3>Overdue Invoices</h3>
                <div class="stat-value <?php echo $overdue_invoices > 0 ? 'text-red' : ''; ?>"><?php echo number_format($overdue_invoices); ?></div>
                <div class="subtext">Require attention</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="chart-row">
            <div class="chart-container">
                <h3>Daily Payments (Last 30 Days)</h3>
                <?php if (empty($daily_revenue)): ?>
                    <div class="chart-no-data"><p>No payment data yet</p></div>
                <?php else: ?>
                    <canvas id="dailyRevenueChart"></canvas>
                <?php endif; ?>
            </div>
            <div class="chart-container">
                <h3>Payment Method Distribution</h3>
                <?php if (empty($method_distribution)): ?>
                    <div class="chart-no-data"><p>No payment data yet</p></div>
                <?php else: ?>
                    <canvas id="methodChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <div class="chart-row">
            <div class="chart-container">
                <h3>Invoice Status Breakdown</h3>
                <?php if (empty($invoice_statuses)): ?>
                    <div class="chart-no-data"><p>No invoice data yet</p></div>
                <?php else: ?>
                    <canvas id="invoiceStatusChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="table-container">
            <div class="table-header">
                <h2>Recent Payments</h2>
            </div>
            <?php if (empty($recent_payments)): ?>
                <p style="text-align: center; color: var(--admin-text); padding: 2rem;">No payments recorded yet</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Company</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['company_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                    <td>$<?php echo number_format($payment['amount'], 2); ?> <?php echo htmlspecialchars($payment['currency'] ?? 'CAD'); ?></td>
                                    <td><span class="badge badge-method"><?php echo htmlspecialchars(ucfirst($payment['payment_method'])); ?></span></td>
                                    <td><span class="badge badge-<?php echo $payment['status']; ?>"><?php echo ucfirst($payment['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 2: TRANSACTIONS -->
    <!-- ============================================================ -->
    <div id="transactions" class="tab-content">
        <!-- Filters -->
        <div class="control-bar">
            <form method="GET" class="filters-form" id="tx-filters-form">
                <input type="hidden" name="tab" value="transactions">
                <div class="control-group">
                    <input type="text" class="control-input" name="tx_search" placeholder="Search customer, reference, company..." value="<?php echo htmlspecialchars($tx_search); ?>">
                </div>
                <div class="control-group">
                    <select class="control-select" name="tx_method" onchange="document.getElementById('tx-filters-form').submit()">
                        <option value="">All Methods</option>
                        <option value="stripe" <?php echo $tx_method === 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                        <option value="paypal" <?php echo $tx_method === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                        <option value="square" <?php echo $tx_method === 'square' ? 'selected' : ''; ?>>Square</option>
                    </select>
                </div>
                <div class="control-group">
                    <select class="control-select" name="tx_status" onchange="document.getElementById('tx-filters-form').submit()">
                        <option value="">All Statuses</option>
                        <option value="completed" <?php echo $tx_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending" <?php echo $tx_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo $tx_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?php echo $tx_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                <input type="hidden" name="view_env" value="<?php echo htmlspecialchars($view_env); ?>">
                <div class="control-group">
                    <select class="control-select" name="tx_company" onchange="document.getElementById('tx-filters-form').submit()">
                        <option value="">All Companies</option>
                        <?php foreach ($company_options as $co): ?>
                            <option value="<?php echo $co['id']; ?>" <?php echo $tx_company == $co['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($co['company_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-small btn-primary-blue">Search</button>
                <?php if (!empty($tx_search) || !empty($tx_method) || !empty($tx_status) || !empty($tx_company)): ?>
                    <a href="?tab=transactions&view_env=<?php echo htmlspecialchars($view_env); ?>" class="btn btn-small btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2>Portal Transactions</h2>
                <span class="result-count"><?php echo count($transactions); ?> results</span>
            </div>
            <?php if (empty($transactions)): ?>
                <p style="text-align: center; color: var(--admin-text); padding: 2rem;">No transactions found</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table data-paginate="25">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Company</th>
                                <th>Customer</th>
                                <th>Invoice #</th>
                                <th>Amount</th>
                                <th>Fee</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Synced</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($tx['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($tx['company_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($tx['customer_name']); ?></td>
                                    <td class="key-field"><?php echo htmlspecialchars($tx['invoice_id'] ?? 'N/A'); ?></td>
                                    <td>$<?php echo number_format($tx['amount'], 2); ?></td>
                                    <td>$<?php echo number_format($tx['processing_fee'] ?? 0, 2); ?></td>
                                    <td><span class="badge badge-method"><?php echo htmlspecialchars(ucfirst($tx['payment_method'])); ?></span></td>
                                    <td><span class="badge badge-<?php echo $tx['status']; ?>"><?php echo ucfirst($tx['status']); ?></span></td>
                                    <td>
                                        <?php if ($tx['synced_to_argo']): ?>
                                            <span class="badge badge-success">Synced</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="key-field"><?php echo htmlspecialchars($tx['reference_number'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 3: COMPANIES -->
    <!-- ============================================================ -->
    <div id="companies" class="tab-content">
        <div class="table-container">
            <div class="table-header">
                <h2>Portal Companies</h2>
                <span class="result-count"><?php echo count($companies); ?> companies</span>
            </div>
            <?php if (empty($companies)): ?>
                <p style="text-align: center; color: var(--admin-text); padding: 2rem;">No companies registered yet</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Connected Providers</th>
                                <th>Total Payments</th>
                                <th>Total Payments</th>
                                <th>Last Payment</th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($companies as $company): ?>
                                <tr class="expandable-row" onclick="toggleCompanyDetail(<?php echo $company['id']; ?>)">
                                    <td><strong><?php echo htmlspecialchars($company['company_name']); ?></strong></td>
                                    <td>
                                        <div class="provider-badges">
                                            <span class="provider-badge <?php echo !empty($company['stripe_account_id']) ? 'connected' : 'disconnected'; ?>" title="Stripe">Stripe</span>
                                            <span class="provider-badge <?php echo !empty($company['paypal_merchant_id']) ? 'connected' : 'disconnected'; ?>" title="PayPal">PayPal</span>
                                            <span class="provider-badge <?php echo !empty($company['square_merchant_id']) ? 'connected' : 'disconnected'; ?>" title="Square">Square</span>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($company['total_payments']); ?></td>
                                    <td>$<?php echo number_format($company['total_revenue'], 2); ?></td>
                                    <td><?php echo $company['last_payment_date'] ? date('M j, Y', strtotime($company['last_payment_date'])) : 'Never'; ?></td>
                                    <td class="expand-arrow-cell"><span class="expand-arrow">&#9662;</span></td>
                                </tr>
                                <tr class="detail-row" id="company-detail-<?php echo $company['id']; ?>" style="display: none;">
                                    <td colspan="6">
                                        <div class="company-detail">
                                            <!-- General Info -->
                                            <div class="detail-section">
                                                <h4 class="detail-section-title">General</h4>
                                                <div class="detail-section-grid">
                                                    <div class="detail-item">
                                                        <span class="detail-label">Owner Email</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($company['owner_email'] ?? 'N/A'); ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span class="detail-label">API Key</span>
                                                        <span class="detail-value key-field"><?php
                                                            $key = $company['api_key'] ?? '';
                                                            echo $key ? htmlspecialchars(substr($key, 0, 8) . '...' . substr($key, -4)) : 'N/A';
                                                        ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Created</span>
                                                        <span class="detail-value"><?php echo $company['created_at'] ? date('M j, Y', strtotime($company['created_at'])) : 'N/A'; ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Provider Connections -->
                                            <div class="detail-section">
                                                <h4 class="detail-section-title">Payment Providers</h4>
                                                <div class="provider-cards">
                                                    <div class="provider-card <?php echo !empty($company['stripe_account_id']) ? 'provider-connected' : 'provider-disconnected'; ?>">
                                                        <div class="provider-card-header">
                                                            <span class="provider-card-name">Stripe</span>
                                                            <span class="provider-card-status"><?php echo !empty($company['stripe_account_id']) ? 'Connected' : 'Not connected'; ?></span>
                                                        </div>
                                                        <?php if (!empty($company['stripe_account_id'])): ?>
                                                        <div class="provider-card-details">
                                                            <div class="detail-item">
                                                                <span class="detail-label">Account ID</span>
                                                                <span class="detail-value key-field"><?php echo htmlspecialchars($company['stripe_account_id']); ?></span>
                                                            </div>
                                                            <div class="detail-item">
                                                                <span class="detail-label">Email</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($company['stripe_email'] ?? 'N/A'); ?></span>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="provider-card <?php echo !empty($company['paypal_merchant_id']) ? 'provider-connected' : 'provider-disconnected'; ?>">
                                                        <div class="provider-card-header">
                                                            <span class="provider-card-name">PayPal</span>
                                                            <span class="provider-card-status"><?php echo !empty($company['paypal_merchant_id']) ? 'Connected' : 'Not connected'; ?></span>
                                                        </div>
                                                        <?php if (!empty($company['paypal_merchant_id'])): ?>
                                                        <div class="provider-card-details">
                                                            <div class="detail-item">
                                                                <span class="detail-label">Merchant ID</span>
                                                                <span class="detail-value key-field"><?php echo htmlspecialchars($company['paypal_merchant_id']); ?></span>
                                                            </div>
                                                            <div class="detail-item">
                                                                <span class="detail-label">Email</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($company['paypal_email'] ?? 'N/A'); ?></span>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="provider-card <?php echo !empty($company['square_merchant_id']) ? 'provider-connected' : 'provider-disconnected'; ?>">
                                                        <div class="provider-card-header">
                                                            <span class="provider-card-name">Square</span>
                                                            <span class="provider-card-status"><?php echo !empty($company['square_merchant_id']) ? 'Connected' : 'Not connected'; ?></span>
                                                        </div>
                                                        <?php if (!empty($company['square_merchant_id'])): ?>
                                                        <div class="provider-card-details">
                                                            <div class="detail-item">
                                                                <span class="detail-label">Merchant ID</span>
                                                                <span class="detail-value key-field"><?php echo htmlspecialchars($company['square_merchant_id']); ?></span>
                                                            </div>
                                                            <div class="detail-item">
                                                                <span class="detail-label">Location ID</span>
                                                                <span class="detail-value key-field"><?php echo htmlspecialchars($company['square_location_id'] ?? 'N/A'); ?></span>
                                                            </div>
                                                            <div class="detail-item">
                                                                <span class="detail-label">Email</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($company['square_email'] ?? 'N/A'); ?></span>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Account Status (lock + verification) -->
                                            <div class="detail-section">
                                                <h4 class="detail-section-title">Account Status</h4>
                                                <div class="detail-section-grid">
                                                    <div class="detail-item">
                                                        <span class="detail-label">Email Verified</span>
                                                        <span class="detail-value"><?php echo !empty($company['email_verified_at']) ? '✓ ' . date('M j, Y', strtotime($company['email_verified_at'])) : '✗ Not verified'; ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Account Locked</span>
                                                        <span class="detail-value"><?php
                                                            if (!empty($company['locked'])) {
                                                                echo '<strong style="color:#dc2626;">Locked</strong>';
                                                                if (!empty($company['locked_at'])) echo ' on ' . date('M j', strtotime($company['locked_at']));
                                                                if (!empty($company['lock_reason'])) echo '<br><small style="color:var(--admin-text);">' . htmlspecialchars($company['lock_reason']) . '</small>';
                                                            } else {
                                                                echo 'Active';
                                                            }
                                                        ?></span>
                                                    </div>
                                                </div>
                                                <div style="margin-top:0.6rem;">
                                                    <?php if (!empty($company['locked'])): ?>
                                                        <form method="post" action="../_actions/portal_company_action.php" style="display:inline;" onclick="event.stopPropagation();">
                                                            <input type="hidden" name="action" value="unlock">
                                                            <input type="hidden" name="company_id" value="<?php echo (int)$company['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                            <input type="text" name="reason" placeholder="Unlock reason (required)" required style="padding:4px 8px;width:240px;margin-right:6px;">
                                                            <button type="submit" onclick="event.stopPropagation();return confirm('Unlock this account? Refunds will be re-enabled.');" style="background:#059669;color:#fff;padding:6px 14px;border:none;border-radius:4px;cursor:pointer;">Unlock</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" action="../_actions/portal_company_action.php" style="display:inline;" onclick="event.stopPropagation();">
                                                            <input type="hidden" name="action" value="lock">
                                                            <input type="hidden" name="company_id" value="<?php echo (int)$company['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                            <input type="text" name="reason" placeholder="Lock reason (required)" required style="padding:4px 8px;width:240px;margin-right:6px;">
                                                            <button type="submit" onclick="event.stopPropagation();return confirm('Lock this account? Refunds will be disabled until unlocked.');" style="background:#dc2626;color:#fff;padding:6px 14px;border:none;border-radius:4px;cursor:pointer;">Lock</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Email Change History -->
                                            <?php $ec_changes = $email_changes_by_company[$company['id']] ?? []; ?>
                                            <?php if (!empty($ec_changes)): ?>
                                            <div class="detail-section">
                                                <h4 class="detail-section-title">Email Change History (last 10)</h4>
                                                <table style="width:100%;font-size:.9em;">
                                                    <thead>
                                                        <tr>
                                                            <th style="text-align:left;">When</th>
                                                            <th style="text-align:left;">From → To</th>
                                                            <th style="text-align:left;">State</th>
                                                            <th style="text-align:left;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($ec_changes as $ec): ?>
                                                        <tr>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($ec['created_at'])); ?></td>
                                                            <td>
                                                                <?php echo htmlspecialchars($ec['old_email']); ?>
                                                                <span style="color:var(--admin-text);"> → </span>
                                                                <?php echo htmlspecialchars($ec['new_email']); ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-state-<?php echo htmlspecialchars($ec['state']); ?>">
                                                                    <?php echo htmlspecialchars($ec['state']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($ec['state'] === 'completed' && !empty($ec['has_revert_token']) && (!$ec['revert_until'] || strtotime($ec['revert_until']) > time())): ?>
                                                                    <form method="post" action="../_actions/portal_company_action.php" style="display:inline;" onclick="event.stopPropagation();">
                                                                        <input type="hidden" name="action" value="revert_email">
                                                                        <input type="hidden" name="email_change_id" value="<?php echo (int)$ec['id']; ?>">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                                        <input type="text" name="reason" placeholder="Revert reason" required style="padding:3px 6px;width:160px;margin-right:4px;">
                                                                        <button type="submit" onclick="event.stopPropagation();return confirm('Admin-revert this email change? Both addresses will be notified.');" style="background:#fff;border:1px solid #d1d5db;padding:4px 10px;border-radius:4px;cursor:pointer;">Revert</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 4: INVOICES -->
    <!-- ============================================================ -->
    <div id="invoices" class="tab-content">
        <!-- Filters -->
        <div class="control-bar">
            <form method="GET" class="filters-form" id="inv-filters-form">
                <input type="hidden" name="tab" value="invoices">
                <input type="hidden" name="view_env" value="<?php echo htmlspecialchars($view_env); ?>">
                <div class="control-group">
                    <input type="text" class="control-input" name="inv_search" placeholder="Search customer, invoice ID, company..." value="<?php echo htmlspecialchars($inv_search); ?>">
                </div>
                <div class="control-group">
                    <select class="control-select" name="inv_status" onchange="document.getElementById('inv-filters-form').submit()">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $inv_status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="sent" <?php echo $inv_status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="viewed" <?php echo $inv_status === 'viewed' ? 'selected' : ''; ?>>Viewed</option>
                        <option value="pending" <?php echo $inv_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="partial" <?php echo $inv_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="paid" <?php echo $inv_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="overdue" <?php echo $inv_status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <option value="cancelled" <?php echo $inv_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="control-group">
                    <select class="control-select" name="inv_company" onchange="document.getElementById('inv-filters-form').submit()">
                        <option value="">All Companies</option>
                        <?php foreach ($company_options as $co): ?>
                            <option value="<?php echo $co['id']; ?>" <?php echo $inv_company == $co['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($co['company_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-small btn-primary-blue">Search</button>
                <?php if (!empty($inv_search) || !empty($inv_status) || !empty($inv_company)): ?>
                    <a href="?tab=invoices&view_env=<?php echo htmlspecialchars($view_env); ?>" class="btn btn-small btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2>Portal Invoices</h2>
                <span class="result-count"><?php echo count($invoices); ?> results</span>
            </div>
            <?php if (empty($invoices)): ?>
                <p style="text-align: center; color: var(--admin-text); padding: 2rem;">No invoices found</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table data-paginate="25">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Company</th>
                                <th>Customer</th>
                                <th>Total Amount</th>
                                <th>Balance Due</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                                <tr class="<?php echo $inv['status'] === 'overdue' ? 'row-overdue' : ''; ?>">
                                    <td class="key-field"><?php echo htmlspecialchars($inv['invoice_id']); ?></td>
                                    <td><?php echo htmlspecialchars($inv['company_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($inv['customer_name']); ?></td>
                                    <td>$<?php echo number_format($inv['total_amount'], 2); ?></td>
                                    <td>$<?php echo number_format($inv['balance_due'], 2); ?></td>
                                    <td><span class="badge badge-invoice-<?php echo $inv['status']; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                                    <td><?php echo $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : 'N/A'; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($inv['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 5: REVENUE ANALYTICS -->
    <!-- ============================================================ -->
    <div id="revenue" class="tab-content">
        <!-- Summary Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Payments</h3>
                <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="subtext">All time</div>
            </div>
            <div class="stat-card">
                <h3>Avg Payment Size</h3>
                <div class="stat-value">$<?php echo number_format($avg_payment_size, 2); ?></div>
                <div class="subtext"><?php echo number_format($total_tx_count); ?> transactions</div>
            </div>
            <div class="stat-card">
                <h3>MoM Growth</h3>
                <div class="stat-value <?php echo $mom_growth >= 0 ? 'text-green' : 'text-red'; ?>">
                    <?php echo ($mom_growth >= 0 ? '+' : '') . number_format($mom_growth, 1); ?>%
                </div>
                <div class="subtext">vs previous 30 days</div>
            </div>
            <div class="stat-card">
                <h3>Fee Rate</h3>
                <div class="stat-value"><?php echo number_format($fee_percentage, 2); ?>%</div>
                <div class="subtext">$<?php echo number_format($total_fees, 2); ?> total fees</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="chart-row">
            <div class="chart-container">
                <h3>Top 10 Companies by Payments</h3>
                <?php if (empty($revenue_by_company)): ?>
                    <div class="chart-no-data"><p>No payment data yet</p></div>
                <?php else: ?>
                    <canvas id="companyRevenueChart"></canvas>
                <?php endif; ?>
            </div>
            <div class="chart-container">
                <h3>Payments by Method (Monthly)</h3>
                <?php if (empty($revenue_by_method_monthly)): ?>
                    <div class="chart-no-data"><p>No data yet</p></div>
                <?php else: ?>
                    <canvas id="methodMonthlyChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <div class="chart-row">
            <div class="chart-container">
                <h3>Average Transaction Value</h3>
                <?php if (empty($avg_transaction_trend)): ?>
                    <div class="chart-no-data"><p>No data yet</p></div>
                <?php else: ?>
                    <canvas id="avgTransactionChart"></canvas>
                <?php endif; ?>
            </div>
            <div class="chart-container">
                <h3>Processing Fees Over Time</h3>
                <?php if (empty($fees_trend)): ?>
                    <div class="chart-no-data"><p>No data yet</p></div>
                <?php else: ?>
                    <canvas id="feesTrendChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 6: FAILED PAYMENTS & REFUNDS -->
    <!-- ============================================================ -->
    <div id="failures" class="tab-content">
        <!-- Summary Stats -->
        <div class="stats-grid stats-grid-3">
            <div class="stat-card">
                <h3>Failed Payments</h3>
                <div class="stat-value text-red"><?php echo number_format($total_failed); ?></div>
                <div class="subtext">All time</div>
            </div>
            <div class="stat-card">
                <h3>Failure Rate</h3>
                <div class="stat-value"><?php echo number_format($failure_rate, 1); ?>%</div>
                <div class="subtext">Of all payment attempts</div>
            </div>
            <div class="stat-card">
                <h3>Total Refunded</h3>
                <div class="stat-value"><?php echo number_format($total_refunded); ?></div>
                <div class="subtext">$<?php echo number_format($total_refund_amount, 2); ?> CAD</div>
            </div>
            <div class="stat-card">
                <h3>Refunds in Flight</h3>
                <div class="stat-value"><?php echo number_format($inflight_count); ?></div>
                <div class="subtext">Not yet completed</div>
            </div>
            <div class="stat-card">
                <h3>Held for Review</h3>
                <div class="stat-value"><?php echo number_format($held_for_review_count); ?></div>
                <div class="subtext">Last 7d (cooling-off + failed)</div>
            </div>
        </div>

        <!-- In-flight refund requests (state machine) -->
        <div class="table-container">
            <div class="table-header">
                <h2>Refund Requests</h2>
                <span class="subtext" style="color:var(--admin-text);font-size:.85rem;">In-flight orchestration. Completed refunds appear in "Refunded Payments" below.</span>
            </div>
            <?php if (empty($inflight_refunds)): ?>
                <p style="text-align: center; color: var(--admin-text); padding: 1.5rem;">No in-flight refund requests.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table data-paginate="25">
                        <thead>
                            <tr>
                                <th>Created</th>
                                <th>Company</th>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Provider</th>
                                <th>State</th>
                                <th>Tier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inflight_refunds as $r): ?>
                                <tr>
                                    <td><?php echo date('M j g:i A', strtotime($r['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($r['company_name'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($r['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($r['customer_name'] ?? '—'); ?></td>
                                    <td>$<?php echo number_format($r['amount_cents']/100, 2); ?> <?php echo htmlspecialchars($r['currency']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($r['provider'])); ?></td>
                                    <td><span class="badge badge-state-<?php echo htmlspecialchars($r['state']); ?>"><?php echo htmlspecialchars($r['state']); ?></span></td>
                                    <td><?php echo htmlspecialchars($r['velocity_tier'] ?? '—'); ?></td>
                                    <td>
                                        <?php if (in_array($r['state'], ['pending_code','code_verified','cooling_off'], true)): ?>
                                            <form method="post" action="../_actions/refund_admin_action.php" style="display:inline;">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                <button type="submit" onclick="return confirm('Cancel this refund?')" style="background:#fff;border:1px solid #d1d5db;padding:4px 10px;border-radius:4px;cursor:pointer;">Cancel</button>
                                            </form>
                                        <?php elseif ($r['state'] === 'processing'): ?>
                                            <form method="post" action="../_actions/refund_admin_action.php" style="display:inline;">
                                                <input type="hidden" name="action" value="force_fail">
                                                <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                <button type="submit" onclick="return confirm('Force-fail this refund?')" style="background:#fff;border:1px solid #d1d5db;padding:4px 10px;border-radius:4px;cursor:pointer;">Force fail</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($r['state_reason']): ?>
                                            <small style="color:var(--admin-text);display:block;margin-top:2px;"><?php echo htmlspecialchars(substr($r['state_reason'], 0, 80)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Failed Payments Table -->
        <div class="table-container">
            <div class="table-header">
                <h2>Failed Payments</h2>
            </div>
            <?php if (empty($failed_payments)): ?>
                <p style="text-align: center; color: var(--admin-text); padding: 2rem;">No failed payments</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table data-paginate="25">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Company</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Provider ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failed_payments as $fp): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($fp['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($fp['company_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($fp['customer_name']); ?></td>
                                    <td>$<?php echo number_format($fp['amount'], 2); ?> <?php echo htmlspecialchars($fp['currency'] ?? 'CAD'); ?></td>
                                    <td><span class="badge badge-method"><?php echo htmlspecialchars(ucfirst($fp['payment_method'])); ?></span></td>
                                    <td class="key-field"><?php echo htmlspecialchars($fp['provider_payment_id'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Refunded Payments Table -->
        <div class="table-container">
            <div class="table-header">
                <h2>Refunded Payments</h2>
            </div>
            <?php if (empty($refunded_payments)): ?>
                <p style="text-align: center; color: var(--admin-text); padding: 2rem;">No refunded payments</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table data-paginate="25">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Company</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Provider ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($refunded_payments as $rp): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($rp['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($rp['company_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($rp['customer_name']); ?></td>
                                    <td>$<?php echo number_format($rp['amount'], 2); ?> <?php echo htmlspecialchars($rp['currency'] ?? 'CAD'); ?></td>
                                    <td><span class="badge badge-method"><?php echo htmlspecialchars(ucfirst($rp['payment_method'])); ?></span></td>
                                    <td class="key-field"><?php echo htmlspecialchars($rp['provider_payment_id'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
// Tab switching is handled centrally by admin/section-tabs.js

// ============================================================
// Company Detail Toggle
// ============================================================
function toggleCompanyDetail(companyId) {
    const detailRow = document.getElementById('company-detail-' + companyId);
    if (detailRow) {
        const isHidden = detailRow.style.display === 'none';
        detailRow.style.display = isHidden ? 'table-row' : 'none';
        const expandableRow = detailRow.previousElementSibling;
        if (expandableRow) expandableRow.classList.toggle('expanded', isHidden);
    }
}

// ============================================================
// Charts
// ============================================================
const chartColors = {
    blue: 'rgba(59, 130, 246, 0.8)',
    navy: 'rgba(30, 64, 175, 0.8)',
    green: 'rgba(16, 185, 129, 0.8)',
    red: 'rgba(239, 68, 68, 0.8)',
    amber: 'rgba(245, 158, 11, 0.8)',
    sky: 'rgba(14, 165, 233, 0.8)',
    pink: 'rgba(236, 72, 153, 0.8)',
    lightBlue: 'rgba(96, 165, 250, 0.8)',
    emerald: 'rgba(52, 211, 153, 0.8)',
    cyan: 'rgba(6, 182, 212, 0.8)'
};

const methodColors = {
    stripe: chartColors.navy,
    paypal: chartColors.blue,
    square: chartColors.green
};

// --- Daily Revenue Chart ---
<?php if (!empty($daily_revenue)): ?>
new Chart(document.getElementById('dailyRevenueChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($daily_revenue, 'date')); ?>,
        datasets: [{
            label: 'Payments ($)',
            data: <?php echo json_encode(array_map('floatval', array_column($daily_revenue, 'revenue'))); ?>,
            borderColor: chartColors.blue,
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString() } },
            x: { ticks: { maxTicksLimit: 10 } }
        }
    }
});
<?php endif; ?>

// --- Payment Method Distribution Chart ---
<?php if (!empty($method_distribution)): ?>
new Chart(document.getElementById('methodChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($m) { return ucfirst($m['payment_method']); }, $method_distribution)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map('floatval', array_column($method_distribution, 'total'))); ?>,
            backgroundColor: <?php echo json_encode(array_map(function($m) {
                $colors = ['stripe' => 'rgba(30, 64, 175, 0.8)', 'paypal' => 'rgba(59, 130, 246, 0.8)', 'square' => 'rgba(16, 185, 129, 0.8)'];
                return $colors[$m['payment_method']] ?? 'rgba(107, 114, 128, 0.8)';
            }, $method_distribution)); ?>
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: ctx => ctx.label + ': $' + ctx.parsed.toLocaleString(undefined, {minimumFractionDigits: 2}) } }
        }
    }
});
<?php endif; ?>

// --- Invoice Status Chart ---
<?php if (!empty($invoice_statuses)): ?>
new Chart(document.getElementById('invoiceStatusChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($s) { return ucfirst($s['status']); }, $invoice_statuses)); ?>,
        datasets: [{
            label: 'Invoices',
            data: <?php echo json_encode(array_map('intval', array_column($invoice_statuses, 'count'))); ?>,
            backgroundColor: <?php echo json_encode(array_map(function($s) {
                $colors = [
                    'draft' => 'rgba(107, 114, 128, 0.7)',
                    'sent' => 'rgba(59, 130, 246, 0.7)',
                    'viewed' => 'rgba(14, 165, 233, 0.7)',
                    'pending' => 'rgba(245, 158, 11, 0.7)',
                    'partial' => 'rgba(30, 64, 175, 0.7)',
                    'paid' => 'rgba(16, 185, 129, 0.7)',
                    'overdue' => 'rgba(239, 68, 68, 0.7)',
                    'cancelled' => 'rgba(107, 114, 128, 0.4)'
                ];
                return $colors[$s['status']] ?? 'rgba(107, 114, 128, 0.5)';
            }, $invoice_statuses)); ?>,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
<?php endif; ?>

// --- Company Revenue Chart ---
<?php if (!empty($revenue_by_company)): ?>
new Chart(document.getElementById('companyRevenueChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($revenue_by_company, 'company_name')); ?>,
        datasets: [{
            label: 'Payments ($)',
            data: <?php echo json_encode(array_map('floatval', array_column($revenue_by_company, 'revenue'))); ?>,
            backgroundColor: Object.values(chartColors).slice(0, <?php echo count($revenue_by_company); ?>),
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString() } } }
    }
});
<?php endif; ?>

// --- Revenue by Method Monthly Chart ---
<?php if (!empty($revenue_by_method_monthly)): ?>
(function() {
    const rawData = <?php echo json_encode($revenue_by_method_monthly); ?>;
    const months = [...new Set(rawData.map(r => r.month))];
    const methods = [...new Set(rawData.map(r => r.payment_method))];

    const datasets = methods.map(method => {
        const data = months.map(month => {
            const entry = rawData.find(r => r.month === month && r.payment_method === method);
            return entry ? parseFloat(entry.revenue) : 0;
        });
        return {
            label: method.charAt(0).toUpperCase() + method.slice(1),
            data: data,
            backgroundColor: methodColors[method] || chartColors.lightBlue,
            borderColor: methodColors[method] || chartColors.lightBlue,
            fill: true,
            tension: 0.3
        };
    });

    new Chart(document.getElementById('methodMonthlyChart'), {
        type: 'line',
        data: { labels: months, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, stacked: true, ticks: { callback: v => '$' + v.toLocaleString() } },
                x: { ticks: { maxTicksLimit: 12 } }
            }
        }
    });
})();
<?php endif; ?>

// --- Avg Transaction Value Chart ---
<?php if (!empty($avg_transaction_trend)): ?>
new Chart(document.getElementById('avgTransactionChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($avg_transaction_trend, 'month')); ?>,
        datasets: [{
            label: 'Avg Transaction ($)',
            data: <?php echo json_encode(array_map('floatval', array_column($avg_transaction_trend, 'avg_amount'))); ?>,
            borderColor: chartColors.emerald,
            backgroundColor: 'rgba(52, 211, 153, 0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '$' + v.toFixed(2) } },
            x: { ticks: { maxTicksLimit: 12 } }
        }
    }
});
<?php endif; ?>

// --- Fees Trend Chart ---
<?php if (!empty($fees_trend)): ?>
new Chart(document.getElementById('feesTrendChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($fees_trend, 'month')); ?>,
        datasets: [{
            label: 'Fees ($)',
            data: <?php echo json_encode(array_map('floatval', array_column($fees_trend, 'fees'))); ?>,
            borderColor: chartColors.amber,
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '$' + v.toFixed(2) } },
            x: { ticks: { maxTicksLimit: 12 } }
        }
    }
});
<?php endif; ?>
</script>
