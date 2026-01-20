<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_admin();

// Summary cards
$totalRanges = (int) $pdo->query('SELECT COUNT(*) FROM ranges')->fetchColumn();
$activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalRevenue = (float) $pdo->query('SELECT COALESCE(SUM(cost), 0) FROM sms_logs')->fetchColumn();
$monthlySmsStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(sms_count), 0) FROM sms_logs WHERE created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'
);
$monthlySmsStmt->execute();
$monthlySms = (int) $monthlySmsStmt->fetchColumn();
$pendingPayouts = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount), 0) FROM payouts WHERE status = 'pending'"
)->fetchColumn();

// Chart data: last 7 days SMS volume
$dailyStmt = $pdo->query(
    "SELECT DATE(created_at) AS d, SUM(sms_count) AS total
     FROM sms_logs
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at)
     ORDER BY d ASC"
);
$dailyData = $dailyStmt->fetchAll();

$labels = [];
$values = [];
foreach ($dailyData as $row) {
    $labels[] = $row['d'];
    $values[] = (int) $row['total'];
}

// Revenue by country
$countryStmt = $pdo->query(
    'SELECT country, SUM(cost) AS revenue FROM sms_logs GROUP BY country ORDER BY revenue DESC'
);
$countryData = $countryStmt->fetchAll();

// Top ranges by SMS
$topRangesStmt = $pdo->query(
    'SELECT r.range_name, SUM(s.sms_count) AS sms_total
     FROM sms_logs s
     JOIN ranges r ON r.id = s.range_id
     GROUP BY s.range_id
     ORDER BY sms_total DESC
     LIMIT 5'
);
$topRanges = $topRangesStmt->fetchAll();

$settings = get_settings();
$brandName = $settings['brand_name'] ?? 'IPRN SMS Panel';

render_header($brandName . ' - Admin Dashboard', true, '', $settings['meta_description'] ?? null);
?>
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Total Ranges</div>
                <div class="h4 mb-0" id="totalRanges"><?php echo $totalRanges; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Active Users</div>
                <div class="h4 mb-0" id="activeUsers"><?php echo $activeUsers; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Revenue (USD)</div>
                <div class="h4 mb-0" id="totalRevenue">$<?php echo number_format($totalRevenue, 2); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Monthly SMS</div>
                <div class="h4 mb-0" id="monthlySms"><?php echo number_format($monthlySms); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">Daily SMS Volume (Last 7 Days)</div>
            <div class="card-body">
                <canvas id="dailySmsChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">Revenue by Country</div>
            <div class="card-body">
                <canvas id="revenueByCountryChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header">Top Performing Ranges</div>
            <div class="card-body">
                <canvas id="topRangesChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header">Pending Payouts</div>
            <div class="card-body">
                <div class="h5 mb-2">৳<?php echo number_format($pendingPayouts, 2); ?></div>
                <p class="small text-muted mb-0">
                    Pending payouts accumulate automatically based on user balances and minimum payout rules.
                    Use the Billing section to approve and mark payouts as paid.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    window.dailySmsData = {
        labels: <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>,
        values: <?php echo json_encode($values, JSON_UNESCAPED_UNICODE); ?>
    };
    window.revenueByCountryData = <?php echo json_encode($countryData, JSON_UNESCAPED_UNICODE); ?>;
    window.topRangesData = <?php echo json_encode($topRanges, JSON_UNESCAPED_UNICODE); ?>;
    window.autoStatsEndpoint = 'api_stats.php';
</script>
<?php
render_footer();