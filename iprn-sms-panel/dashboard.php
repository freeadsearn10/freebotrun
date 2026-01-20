<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = current_user();
$settings = get_settings();
$brandName = $settings['brand_name'] ?? 'IPRN SMS Panel';

$minPayout = (float) $settings['min_payout'];
$balance = (float) $user['balance'];
$eligible = $balance >= $minPayout;

$today = date('Y-m-d');

$stmtToday = $pdo->prepare(
    'SELECT COALESCE(SUM(sms_count), 0) AS sms_total, COALESCE(SUM(cost), 0) AS revenue 
     FROM sms_logs WHERE user_id = ? AND DATE(created_at) = ?'
);
$stmtToday->execute([$user['id'], $today]);
$todayStats = $stmtToday->fetch() ?: ['sms_total' => 0, 'revenue' => 0];

$stmtRanges = $pdo->query(
    'SELECT r.*, 
            (SELECT COUNT(*) FROM numbers n WHERE n.range_id = r.id) AS numbers_count
     FROM ranges r
     ORDER BY r.created_at DESC
     LIMIT 10'
);
$ranges = $stmtRanges->fetchAll();

render_header('Dashboard - ' . $brandName, false, '', $settings['meta_description'] ?? null);
?>
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Balance</div>
                    <div class="h4 mb-0">৳<?php echo number_format($balance, 2); ?></div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Today SMS</div>
                    <div class="h5 mb-0"><?php echo number_format((int) $todayStats['sms_total']); ?></div>
                    <div class="small text-muted">Revenue: $<?php echo number_format((float) $todayStats['revenue'], 2); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Payout Status</div>
                    <div class="fw-bold">
                        Minimum ৳<?php echo number_format($minPayout, 2); ?>
                        | Balance ৳<?php echo number_format($balance, 2); ?>
                    </div>
                </div>
                <div class="text-end">
                    <?php if ($eligible): ?>
                        <span class="badge bg-success">ELIGIBLE</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Not yet eligible</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="h5 mb-3">Your Ranges (Recent First)</h2>
<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Range Name</th>
                    <th>Rate (USD)</th>
                    <th>Payout %</th>
                    <th>Numbers</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($ranges): ?>
                    <?php foreach ($ranges as $range): ?>
                        <tr>
                            <td><?php echo e($range['range_name']); ?></td>
                            <td>$<?php echo number_format((float) $range['rate'], 4); ?></td>
                            <td><?php echo number_format((float) $range['payout_percent'], 0); ?>%</td>
                            <td><?php echo number_format((int) $range['numbers_count']); ?></td>
                            <td>
                                <?php if ($range['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">No ranges available yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="small text-muted">
    This is a read-only user dashboard. Range assignment, routing and SMS delivery are managed by the administrator
    through external SMPP/HTTP integrations.
</p>
<?php
render_footer();