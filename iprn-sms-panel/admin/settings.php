<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_admin();

$settings = get_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $brandName = trim($_POST['brand_name'] ?? '');
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $minPayout = (float) ($_POST['min_payout'] ?? 0);
    $signupEnabled = isset($_POST['signup_enabled']) ? 1 : 0;
    $defaultRate = (float) ($_POST['default_rate'] ?? 0);
    $defaultPayout = (int) ($_POST['default_payout'] ?? 0);

    if ($brandName === '') {
        flash('error', 'Brand name is required.');
    } elseif ($minPayout <= 0) {
        flash('error', 'Minimum payout must be greater than zero.');
    } elseif ($defaultRate <= 0) {
        flash('error', 'Default rate must be greater than zero.');
    } elseif ($defaultPayout < 1 || $defaultPayout > 100) {
        flash('error', 'Default payout must be between 1 and 100.');
    } else {
        if ($metaTitle === '') {
            $metaTitle = $brandName;
        }

        $stmt = $pdo->prepare(
            'UPDATE settings 
             SET brand_name = ?, meta_title = ?, meta_description = ?, min_payout = ?, signup_enabled = ?, default_rate = ?, default_payout = ?
             WHERE id = 1'
        );
        $stmt->execute([$brandName, $metaTitle, $metaDescription, $minPayout, $signupEnabled, $defaultRate, $defaultPayout]);
        flash('success', 'Settings updated successfully.');
    }

    redirect('settings.php');
}

$settings = get_settings();
$brandName = $settings['brand_name'] ?? 'IPRN SMS Panel';

render_header('Settings - Admin - ' . $brandName, true, '', $settings['meta_description'] ?? null);
?>
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Brand &amp; SEO</div>
            <div class="card-body">
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="brand_name">Brand Name</label>
                        <input type="text" class="form-control" id="brand_name" name="brand_name"
                               value="<?php echo e($settings['brand_name'] ?? 'IPRN SMS Panel'); ?>" required>
                        <div class="form-text">Shown in the navbar, admin header and page titles.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="meta_title">Default Meta Title</label>
                        <input type="text" class="form-control" id="meta_title" name="meta_title"
                               value="<?php echo e($settings['meta_title'] ?? ''); ?>">
                        <div class="form-text">Used for SEO and social sharing when a page does not override the title.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="meta_description">Default Meta Description</label>
                        <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?php
                            echo e($settings['meta_description'] ?? '');
                        ?></textarea>
                        <div class="form-text">Short description for search engines and social media previews.</div>
                    </div>
                    <hr>
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
                    <li><strong>Brand name</strong> controls the public and admin navbars and is used in titles.</li>
                    <li><strong>Meta title &amp; description</strong> are used for SEO and social previews if a page does not override them.</li>
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