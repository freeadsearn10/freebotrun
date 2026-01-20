<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$settings = get_settings();

$totalRanges = (int) $pdo->query('SELECT COUNT(*) FROM ranges')->fetchColumn();
$totalNumbers = (int) $pdo->query('SELECT COUNT(*) FROM numbers')->fetchColumn();
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalSms = (int) $pdo->query('SELECT COALESCE(SUM(sms_count), 0) FROM sms_logs')->fetchColumn();
$totalRevenue = (float) $pdo->query('SELECT COALESCE(SUM(cost), 0) FROM sms_logs')->fetchColumn();

render_header('IPRN SMS Panel - Home');
?>
<section class="py-5">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="display-5 fw-bold mb-3">IPRN SMS Panel v6.0</h1>
            <p class="lead">Carrier-grade International Premium Rate Number (IPRN) SMS routing panel for shared hosting. Upload TXT lists, define ranges and rates, and manage payouts — all from a responsive Bootstrap 5 interface.</p>
            <ul class="list-unstyled mb-4">
                <li>✔ TXT upload (numbers only)</li>
                <li>✔ Range-based rating and payout percentages</li>
                <li>✔ Static user dashboard with live stats</li>
                <li>✔ Admin mobile/desktop control panel</li>
                <li>✔ Ready for cPanel + CRON jobs</li>
            </ul>
            <a href="register.php" class="btn btn-primary btn-lg me-2">Get Started</a>
            <a href="login.php" class="btn btn-outline-light btn-lg">Admin Login</a>
        </div>
        <div class="col-md-6 mt-4 mt-md-0">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    Live Platform Stats
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h4><?php echo $totalRanges; ?></h4>
                            <div class="text-muted">Ranges</div>
                        </div>
                        <div class="col-6 mb-3">
                            <h4><?php echo number_format($totalNumbers); ?></h4>
                            <div class="text-muted">Numbers Loaded</div>
                        </div>
                        <div class="col-6">
                            <h4><?php echo number_format($totalSms); ?></h4>
                            <div class="text-muted">Total SMS</div>
                        </div>
                        <div class="col-6">
                            <h4>$<?php echo number_format($totalRevenue, 2); ?></h4>
                            <div class="text-muted">Total Revenue</div>
                        </div>
                    </div>
                    <hr>
                    <p class="mb-0 small text-muted">
                        Minimum payout: ৳<?php echo number_format((float) $settings['min_payout'], 2); ?> ·
                        Signup: <?php echo $settings['signup_enabled'] ? 'OPEN' : 'CLOSED'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-4">
    <h2 class="h4 mb-3">Features</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">TXT Number Upload</h5>
                    <p class="card-text">Upload simple <code>.txt</code> files with one number per line. The panel validates that each line contains numbers only and automatically builds your stock.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Range &amp; Rate Control</h5>
                    <p class="card-text">Attach uploaded numbers to named ranges (e.g. <strong>Afghanistan RTX 761</strong>) with custom per-SMS rates and payout percentages.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Payout Management</h5>
                    <p class="card-text">Configure minimum payout thresholds, track pending payouts, and manage manual approvals from a secure admin billing panel.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-4">
    <h2 class="h4 mb-3">Compliance</h2>
    <p class="small text-muted">
        This demo panel is designed for environments regulated by authorities such as BTRC, TCPA, and GDPR. You are responsible for configuring your own routing, SMPP/HTTP gateways, and ensuring that all traffic
        complies with your local regulations, opt-in requirements, and data protection laws.
    </p>
</section>

<section class="py-4">
    <h2 class="h4 mb-3">API Integration</h2>
    <p class="small text-muted">
        The panel is built to sit in front of your own SMS delivery infrastructure. You can attach HTTP or SMPP gateways to your ranges and use the balance and statistics as a control layer for resellers.
    </p>
</section>
<?php
render_footer();