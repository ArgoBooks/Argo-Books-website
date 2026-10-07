<?php
require_once __DIR__ . '/admin_session.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../resources/icons.php';
require_once __DIR__ . '/../community/affiliate/affiliate_functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

try {
    global $pdo;

    // Every query is filtered to the current environment because both share one database, and interpolating is safe since current_environment() returns one of two constants.
    $env = current_environment();

    // --- MRR for the last 12 months ---
    // The run-rate live at each month end, which is not cash collected in the month.
    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(months.month_date, '%Y-%m') as month,
            COALESCE(SUM(CASE
                WHEN s.created_at <= LAST_DAY(months.month_date)
                     AND (s.cancelled_at IS NULL OR s.cancelled_at > LAST_DAY(months.month_date))
                     AND (s.status = 'active' OR s.end_date > LAST_DAY(months.month_date))
                THEN (CASE WHEN s.billing_cycle = 'yearly' THEN s.amount / 12 ELSE s.amount END)
                ELSE 0 END), 0) as mrr
        FROM (
            SELECT DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL n MONTH) as month_date
            FROM (
                SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
                UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7
                UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11
            ) nums
        ) months
        LEFT JOIN premium_subscriptions s ON s.payment_method != 'free_key' AND s.environment = '$env'
        GROUP BY months.month_date
        ORDER BY months.month_date ASC
    ");
    $mrr_by_month = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mrr_by_month[$row['month']] = round((float)$row['mrr'], 2);
    }

    // --- Actual cash revenue per month (for the cumulative-revenue chart) ---
    // Real completed payments booked in each calendar month (not normalized).
    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COALESCE(SUM(amount), 0) as revenue
        FROM premium_subscription_payments
        WHERE status = 'completed'
        AND environment = '$env'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $revenue_by_month = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $revenue_by_month[$row['month']] = round((float)$row['revenue'], 2);
    }

    // Revenue booked before the 12-month window, so the cumulative line starts
    // from the true all-time total rather than resetting to $0 a year ago.
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total FROM premium_subscription_payments
        WHERE status = 'completed'
        AND environment = '$env'
        AND created_at < DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH), '%Y-%m-01')
    ");
    $revenue_before_window = round((float)$stmt->fetch(PDO::FETCH_ASSOC)['total'], 2);

    // --- Active vs inactive licences per month ---
    // A subscription counts when it existed by month end and was active or paid past it.
    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(months.month_date, '%Y-%m') as month,
            COALESCE(SUM(CASE
                WHEN s.created_at <= LAST_DAY(months.month_date)
                     AND (s.cancelled_at IS NULL OR s.cancelled_at > LAST_DAY(months.month_date))
                     AND (s.status = 'active' OR s.end_date > LAST_DAY(months.month_date))
                     AND s.payment_method != 'free_key'
                THEN 1 ELSE 0 END), 0) as active_count,
            COALESCE(SUM(CASE
                WHEN s.created_at <= LAST_DAY(months.month_date)
                     AND s.payment_method != 'free_key'
                     AND (
                         (s.cancelled_at IS NOT NULL AND s.cancelled_at <= LAST_DAY(months.month_date))
                         OR (s.status <> 'active' AND (s.end_date IS NULL OR s.end_date <= LAST_DAY(months.month_date)))
                     )
                THEN 1 ELSE 0 END), 0) as inactive_count
        FROM (
            SELECT DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL n MONTH) as month_date
            FROM (
                SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
                UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7
                UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11
            ) nums
        ) months
        LEFT JOIN premium_subscriptions s ON s.payment_method != 'free_key' AND s.environment = '$env'
        GROUP BY months.month_date
        ORDER BY months.month_date ASC
    ");
    $license_by_month = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $license_by_month[$row['month']] = [
            'active' => (int)$row['active_count'],
            'inactive' => (int)$row['inactive_count']
        ];
    }

    // --- Churn rate per month (last 12 months) ---
    // cancelled_in_month / active_at_start_of_month * 100
    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(cancelled_at, '%Y-%m') as month,
            COUNT(*) as cancelled_count
        FROM premium_subscriptions
        WHERE cancelled_at IS NOT NULL
        AND payment_method != 'free_key'
        AND environment = '$env'
        AND cancelled_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(cancelled_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $cancelled_by_month = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cancelled_by_month[$row['month']] = (int)$row['cancelled_count'];
    }

    // Active count at start of each month (created before month and not yet cancelled/expired)
    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(months.month_date, '%Y-%m') as month,
            COUNT(s.subscription_id) as active_start
        FROM (
            SELECT DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL n MONTH) as month_date
            FROM (
                SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
                UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7
                UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11
            ) nums
        ) months
        LEFT JOIN premium_subscriptions s
            ON s.created_at < months.month_date
            AND s.payment_method != 'free_key'
            AND s.environment = '$env'
            AND (s.cancelled_at IS NULL OR s.cancelled_at >= months.month_date)
            AND (s.end_date >= months.month_date OR s.end_date IS NULL)
        GROUP BY months.month_date
        ORDER BY months.month_date ASC
    ");
    $active_start_by_month = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $active_start_by_month[$row['month']] = (int)$row['active_start'];
    }

    // --- Summary stat cards ---
    // Total paid licenses (active, not free)
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM premium_subscriptions
        WHERE payment_method != 'free_key'
        AND environment = '$env'
        AND (cancelled_at IS NULL)
        AND (end_date IS NULL OR end_date > NOW())
    ");
    $total_paid_licenses = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Revenue last 30 days
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total FROM premium_subscription_payments
        WHERE status = 'completed'
        AND environment = '$env'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $revenue_30d = round((float)$stmt->fetch(PDO::FETCH_ASSOC)['total'], 2);

    // Revenue last 365 days
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total FROM premium_subscription_payments
        WHERE status = 'completed'
        AND environment = '$env'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
    ");
    $revenue_365d = round((float)$stmt->fetch(PDO::FETCH_ASSOC)['total'], 2);

    // Revenue all time
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total FROM premium_subscription_payments
        WHERE status = 'completed'
        AND environment = '$env'
    ");
    $revenue_all = round((float)$stmt->fetch(PDO::FETCH_ASSOC)['total'], 2);

} catch (Exception $e) {
    error_log("Error fetching subscription stats: " . $e->getMessage());
    $mrr_by_month = [];
    $revenue_by_month = [];
    $revenue_before_window = 0;
    $license_by_month = [];
    $cancelled_by_month = [];
    $active_start_by_month = [];
    $total_paid_licenses = 0;
    $revenue_30d = 0;
    $revenue_365d = 0;
    $revenue_all = 0;
}

// Affiliate commission currently owed across the whole program. Revenue figures above are gross (before this), so this surfaces the liability separately.
$affiliate_owed = affiliate_program_totals($env)['owed'];

// Build arrays for last 12 months
$chart_labels = [];
$mrr_data = [];
$cumulative_data = [];
$active_data = [];
$inactive_data = [];
$churn_data = [];

// Seed the cumulative line with revenue booked before the 12-month window.
$running_total = $revenue_before_window;

for ($i = 11; $i >= 0; $i--) {
    $month_key = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $chart_labels[] = $month_label;

    // MRR run-rate (flat while subscribed; moves only on signup/cancel)
    $mrr = $mrr_by_month[$month_key] ?? 0;
    $mrr_data[] = $mrr;

    // Cumulative actual cash revenue (running sum of real completed payments)
    $running_total += $revenue_by_month[$month_key] ?? 0;
    $cumulative_data[] = round($running_total, 2);

    // Active vs Inactive
    $active_data[] = $license_by_month[$month_key]['active'] ?? 0;
    $inactive_data[] = $license_by_month[$month_key]['inactive'] ?? 0;

    // Churn rate
    $cancelled = $cancelled_by_month[$month_key] ?? 0;
    $active_start = $active_start_by_month[$month_key] ?? 0;
    $churn_data[] = $active_start > 0 ? round($cancelled / $active_start * 100, 1) : 0;
}

include __DIR__ . '/admin_header.php';
?>

<link rel="stylesheet" href="style.css">

<div class="dashboard-home">
    <!-- Hero Section -->
    <div class="hero-section">
        <h1>Admin Dashboard</h1>
    </div>

    <!-- Summary Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Active Licenses</h3>
            <div class="value"><?php echo $total_paid_licenses; ?></div>
        </div>
        <div class="stat-card">
            <h3>Revenue (30 days)</h3>
            <div class="value">$<?php echo number_format($revenue_30d, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Revenue (1 year)</h3>
            <div class="value">$<?php echo number_format($revenue_365d, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Revenue (All Time)</h3>
            <div class="value">$<?php echo number_format($revenue_all, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Affiliate Commission Owed</h3>
            <div class="value">$<?php echo number_format($affiliate_owed, 2); ?></div>
            <div class="subtext">not deducted from revenue</div>
        </div>
    </div>

    <!-- Charts Grid (replaces stat cards) -->
    <div class="charts-grid">
        <!-- MRR Chart -->
        <div class="chart-card">
            <h2 class="chart-card-title">Monthly Recurring Revenue</h2>
            <div class="chart-card-container">
                <canvas id="mrrChart"></canvas>
            </div>
        </div>

        <!-- Revenue (All Time) Chart -->
        <div class="chart-card">
            <h2 class="chart-card-title">Revenue (All Time)</h2>
            <div class="chart-card-container">
                <canvas id="cumulativeChart"></canvas>
            </div>
        </div>

        <!-- Active vs Inactive Chart -->
        <div class="chart-card">
            <h2 class="chart-card-title">Active vs Inactive Licenses</h2>
            <div class="chart-card-container">
                <canvas id="licensesChart"></canvas>
            </div>
        </div>

        <!-- Churn Rate Chart -->
        <div class="chart-card">
            <h2 class="chart-card-title">Monthly Churn Rate</h2>
            <div class="chart-card-container">
                <canvas id="churnChart"></canvas>
            </div>
        </div>
    </div>

</div>

<script>
const chartLabels = <?php echo json_encode($chart_labels); ?>;

const sharedTooltip = {
    backgroundColor: '#1e293b',
    titleColor: '#f8fafc',
    bodyColor: '#cbd5e1',
    padding: 12,
    cornerRadius: 8,
    titleFont: { size: 13, weight: '600' },
    bodyFont: { size: 12 }
};

const sharedScaleX = {
    grid: { display: false },
    ticks: { font: { size: 10 }, maxRotation: 45 }
};

// 1. MRR Line Chart
new Chart(document.getElementById('mrrChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'MRR',
            data: <?php echo json_encode($mrr_data); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#3b82f6',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                ...sharedTooltip,
                callbacks: {
                    label: ctx => '$' + ctx.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})
                }
            }
        },
        scales: {
            x: sharedScaleX,
            y: {
                grid: { color: 'rgba(148, 163, 184, 0.1)' },
                ticks: { font: { size: 11 }, callback: v => '$' + v.toLocaleString() }
            }
        }
    }
});

// 2. Cumulative Revenue Area Chart
new Chart(document.getElementById('cumulativeChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Revenue (All Time)',
            data: <?php echo json_encode($cumulative_data); ?>,
            borderColor: '#0ea5e9',
            backgroundColor: 'rgba(14, 165, 233, 0.15)',
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#0ea5e9',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                ...sharedTooltip,
                callbacks: {
                    label: ctx => '$' + ctx.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})
                }
            }
        },
        scales: {
            x: sharedScaleX,
            y: {
                grid: { color: 'rgba(148, 163, 184, 0.1)' },
                ticks: { font: { size: 11 }, callback: v => '$' + v.toLocaleString() }
            }
        }
    }
});

// 3. Active vs Inactive Stacked Bar Chart
new Chart(document.getElementById('licensesChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [
            {
                label: 'Active',
                data: <?php echo json_encode($active_data); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderRadius: 4,
                borderSkipped: false
            },
            {
                label: 'Inactive',
                data: <?php echo json_encode($inactive_data); ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.6)',
                borderRadius: 4,
                borderSkipped: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: { usePointStyle: true, pointStyle: 'circle', padding: 15, font: { size: 11 } }
            },
            tooltip: sharedTooltip
        },
        scales: {
            x: { ...sharedScaleX, stacked: true },
            y: {
                stacked: true,
                grid: { color: 'rgba(148, 163, 184, 0.1)' },
                ticks: { font: { size: 11 }, stepSize: 1 }
            }
        }
    }
});

// 4. Churn Rate Line Chart
new Chart(document.getElementById('churnChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Churn Rate',
            data: <?php echo json_encode($churn_data); ?>,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#ef4444',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                ...sharedTooltip,
                callbacks: {
                    label: ctx => ctx.parsed.y + '% churn'
                }
            }
        },
        scales: {
            x: sharedScaleX,
            y: {
                grid: { color: 'rgba(148, 163, 184, 0.1)' },
                ticks: { font: { size: 11 }, callback: v => v + '%' },
                min: 0
            }
        }
    }
});
</script>
