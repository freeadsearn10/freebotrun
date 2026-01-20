<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$totalRanges = (int) $pdo->query('SELECT COUNT(*) FROM ranges')->fetchColumn();
$totalNumbers = (int) $pdo->query('SELECT COUNT(*) FROM numbers')->fetchColumn();
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalSms = (int) $pdo->query('SELECT COALESCE(SUM(sms_count), 0) FROM sms_logs')->fetchColumn();
$totalRevenue = (float) $pdo->query('SELECT COALESCE(SUM(cost), 0) FROM sms_logs')->fetchColumn();

render_header('Live Stats - IPRN SMS Panel');
?>
<h1 class="h4 mb-3">Live Platform Statistics</h1>
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Ranges</div>
                <div class="h4 mb-0"><?php echo $totalRanges; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Numbers</div>
                <div class="h4 mb-0"><?php echo number_format($totalNumbers); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Users</div>
                <div class="h4 mb-0"><?php echo $totalUsers; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Total SMS</div>
                <div class="h4 mb-0"><?php echo number_format($totalSms); ?></div>
            </div>
        </div>
    </div>
</div>
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="text-muted small">Total Revenue (USD)</div>
        <div class="h4 mb-0">$<?php echo number_format($totalRevenue, 2); ?></div>
    </div>
</div>
<?php
render_footer();