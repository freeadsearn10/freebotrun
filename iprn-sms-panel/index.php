<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$settings = get_settings();
$brandName = $settings['brand_name'] ?? 'IPRN SMS Panel';

$totalRanges = (int) $pdo->query('SELECT COUNT(*) FROM ranges')->fetchColumn();
$totalNumbers = (int) $pdo->query('SELECT COUNT(*) FROM numbers')->fetchColumn();
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalSms = (int) $pdo->query('SELECT COALESCE(SUM(sms_count), 0) FROM sms_logs')->fetchColumn();
$totalRevenue = (float) $pdo->query('SELECT COALESCE(SUM(cost), 0) FROM sms_logs')->fetchColumn();

render_header($brandName . ' - Home', false, 'landing-page', $settings['meta_description'] ?? null);
?>
<section class="hero-section py-5">
    <div class="row align-items-center">
        <div class="col-lg-6 mb-4 mb-lg-0 animate-fade-up">
            <span class="badge bg-gradient-primary-soft text-uppercase mb-3 small fw-semibold">
                IPRN SMS PLATFORM
            </span>
            <h1 class="display-5 fw-bold mb-3 text-white">
                Turn OTP &amp; Traffic SMS
                <span class="text-gradient-primary">Into Revenue</span>
            </h1>
            <p class="lead text-light-50 mb-4">
                Connect premium rate SMS numbers, monitor live volumes and control payouts from one secure panel –
                built for serious IPRN operators and resellers.
            </p>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="register.php" class="btn btn-primary btn-lg px-4">Create Account</a>
                <a href="login.php" class="btn btn-outline-light btn-lg px-4">Admin Login</a>
            </div>
            <div class="d-flex flex-wrap gap-3 hero-meta">
                <div>
                    <div class="h5 mb-0 text-white"><?php echo number_format($totalSms); ?></div>
                    <div class="small text-light-50">Total SMS Tracked</div>
                </div>
                <div>
                    <div class="h5 mb-0 text-white"><?php echo $totalRanges; ?></div>
                    <div class="small text-light-50">Active Ranges</div>
                </div>
                <div>
                    <div class="h5 mb-0 text-white">$<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="small text-light-50">Platform Revenue</div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 animate-fade-up">
            <div class="card glass-card shadow-lg mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Live Routing Snapshot</span>
                    <span class="badge bg-success-subtle text-success">Operational</span>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-6 mb-3">
                            <div class="small text-muted">Ranges</div>
                            <div class="h4 mb-0"><?php echo $totalRanges; ?></div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="small text-muted">Numbers Loaded</div>
                            <div class="h4 mb-0"><?php echo number_format($totalNumbers); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Total SMS</div>
                            <div class="h4 mb-0"><?php echo number_format($totalSms); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Revenue (USD)</div>
                            <div class="h4 mb-0">$<?php echo number_format($totalRevenue, 2); ?></div>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Minimum payout</span>
                        <span>৳<?php echo number_format((float) $settings['min_payout'], 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span>Public signup</span>
                        <span class="badge bg-<?php echo $settings['signup_enabled'] ? 'success' : 'secondary'; ?>">
                            <?php echo $settings['signup_enabled'] ? 'OPEN' : 'CLOSED'; ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="row g-2 text-center small text-light-50">
                <div class="col-4">
                    <span class="d-block fw-semibold text-white">Premium Routes</span>
                    High value IPRN SMS
                </div>
                <div class="col-4">
                    <span class="d-block fw-semibold text-white">Live Analytics</span>
                    Volume &amp; revenue
                </div>
                <div class="col-4">
                    <span class="d-block fw-semibold text-white">Fast Payouts</span>
                    bKash, Nagad, bank
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-dark-900">
    <h2 class="h4 mb-3 text-white text-center animate-fade-up">Why operators choose <?php echo e($brandName); ?></h2>
    <p class="text-center text-light-50 mb-4 animate-fade-up">
        Simple web panel for premium rate SMS – connect ranges, track performance and manage payouts in minutes.
    </p>
    <div class="row g-3">
        <div class="col-md-4 animate-fade-up">
            <div class="card feature-card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Easy number loading</h5>
                    <p class="card-text">
                        Upload your active numbers and keep everything organised by country and operator.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Range &amp; rate control</h5>
                    <p class="card-text">
                        Set custom rates and payout percentages per range to match your commercial deals.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Payout workflow</h5>
                    <p class="card-text">
                        Track user balances, create payout requests and mark them as pending, approved or paid.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-dark-950">
    <div class="row g-4 align-items-center">
        <div class="col-lg-6">
            <h2 class="h4 text-white mb-3">Realtime Analytics &amp; Country Mix</h2>
            <p class="text-light-50 mb-3">
                Track daily SMS volume, revenue distribution by country and top-performing ranges with responsive
                charts optimised for mobile admin.
            </p>
            <ul class="text-light-50 small mb-0">
                <li>Line chart: daily SMS volume (last 7 days)</li>
                <li>Pie chart: revenue split by country</li>
                <li>Bar chart: top ranges by SMS</li>
                <li>Auto-refresh admin KPIs every 30 seconds</li>
            </ul>
        </div>
        <div class="col-lg-6">
            <div class="card glass-card shadow-lg">
                <div class="card-header">Example User Snapshot</div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Balance</span>
                        <strong>৳18,450</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Today</span>
                        <span>3,245 SMS · $245.60</span>
                    </div>
                    <hr>
                    <div class="mb-2 fw-semibold">Recent Ranges</div>
                    <ul class="list-unstyled mb-0 small">
                        <li>Afghanistan RTX 761 – $0.12 · 2,450 numbers</li>
                        <li>Bangladesh GP 1645 – $0.08 · 5,000 numbers</li>
                        <li>USA AT&amp;T 555 – $0.15 · 150 numbers</li>
                    </ul>
                    <div class="mt-3 small text-success">
                        Minimum ৳5,000 | Balance ৳18,450 – ELIGIBLE for payout
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-dark-900">
    <div class="row g-4">
        <div class="col-md-4">
            <h3 class="h5 text-white mb-3">Compliance Ready</h3>
            <p class="text-light-50 small mb-3">
                Built with BTRC, TCPA and GDPR guidelines in mind – you control routing, consent and retention policies.
            </p>
            <a href="compliance.php" class="link-light small text-decoration-none">
                View compliance notes →
            </a>
        </div>
        <div class="col-md-4">
            <h3 class="h5 text-white mb-3">API &amp; Integration</h3>
            <p class="text-light-50 small mb-3">
                Use your existing SMPP or HTTP gateways. The panel focuses on rating, balances and reporting – not raw
                SMS delivery.
            </p>
            <a href="api-docs.php" class="link-light small text-decoration-none">
                View API examples →
            </a>
        </div>
        <div class="col-md-4">
            <h3 class="h5 text-white mb-3">Pricing &amp; Live Stats</h3>
            <p class="text-light-50 small mb-3">
                Expose public pricing cards and live counters directly from real database values to build trust.
            </p>
            <div class="d-flex flex-column gap-1 small">
                <a href="pricing.php" class="link-light text-decoration-none">View pricing ranges →</a>
                <a href="live-stats.php" class="link-light text-decoration-none">View live platform stats →</a>
            </div>
        </div>
    </div>
</section>

<footer class="py-4 bg-dark-975 border-top border-dark">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-light-50">
        <div>© <?php echo date('Y'); ?> IPRN SMS Panel. All rights reserved.</div>
        <div class="mt-2 mt-md-0">
            <a href="contact.php" class="link-light text-decoration-none me-3">Contact</a>
            <a href="compliance.php" class="link-light text-decoration-none me-3">Compliance</a>
            <a href="api-docs.php" class="link-light text-decoration-none">API</a>
        </div>
    </div>
</footer>
<?php
render_footer();