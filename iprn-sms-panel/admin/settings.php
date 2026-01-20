<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_admin();

$settings = get_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $minPayout = (float) ($_POST['min_payout'] ?? 0);
    $signupEnabled = isset($_POST['signup_enabled']) ? 1 : 0;
    $defaultRate = (float) ($_POST['default_rate'] ?? 0);
    $defaultPayout = (int) ($_POST['default_payout'] ?? 0);

    if ($minPayout <= 0) {
        flash('error', 'Minimum payout must be greater than zero.');
    } elseif ($defaultRate <= 0) {
        flash('error', 'Default rate must be greater than zero.');
    } elseif ($defaultPayout < 1 || $defaultPayout > 100) {
        flash('error', 'Default payout must be between 1 and 100.');
    } else {
        $stmt = $pdo->prepare(
            'UPDATE settings 
             SET min_payout = ?, signup_enabled = ?, default_rate = ?, default_payout = ?
             WHERE id = 1'
        );
        $stmt->execute([$minPayout, $signupEnabled, $defaultRate, $defaultPayout]);
        flash('success', 'Settings updated successfully.');
    }

    redirect('settings.php');
}

render_header('Settings - Admin - IPRN SMS Panel', true);
?>
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">General Settings</div>
            <div class="card-body">
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="min_payout">Minimum Payout (BDT ৳)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="min_payout"
                               name="min_payout"
                               value="<?php echo e($settings['min_payout']); ?>" required>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" id="signup_enabled"
                               name="signup_enabled" <?php echo $settings['signup_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="signup_enabled">Public Signup Enabled</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="default_rate">Default Rate (USD)</label>
                        <input type="number" step="0.0001" min="0" class="form-control" id="default_rate"
                               name="default_rate"
                               value="<?php echo e($settings['default_rate']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="default_payout">Default Payout %</label>
                        <input type="number" step="1" min="1" max="100" class="form-control"
                               id="default_payout" name="default_payout"
                               value="<?php echo e($settings['default_payout']); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mt-3 mt-lg-0">
        <div class="card shadow-sm">
            <div class="card-header">Notes</div>
            <div class="card-body">
                <ul class="small text-muted mb-0">
                    <li>Minimum payout controls when users become eligible for payout requests.</li>
                    <li>Public signup toggle enables or disables the registration form for new users.</li>
                    <li>Default rate and payout percentages are used as initial values when creating new ranges.</li>
                    <li>You can adjust per-range values from the Numbers section.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php
render_footer();