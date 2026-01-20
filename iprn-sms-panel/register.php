<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$settings = get_settings();
$brandName = $settings['brand_name'] ?? 'IPRN SMS Panel';

if (!$settings['signup_enabled']) {
    flash('error', 'Public signup is currently disabled by the administrator.');
    redirect('login.php');
}

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email address is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'Email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password, role, numbers_limit) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$email, $hash, 'user', 10]);

            flash('success', 'Account created successfully. You can now log in.');
            redirect('login.php');
        }
    }
}

if ($error) {
    flash('error', $error);
}

render_header('Register - ' . $brandName, false, 'auth-page', $settings['meta_description'] ?? null);
?>
<div class="auth-wrapper">
    <div class="auth-card shadow-sm">
        <div class="auth-card-header">
            <h1 class="mb-1"><?php echo e($brandName); ?></h1>
            <div class="small fw-semibold mb-3">Monetise your OTP and traffic SMS globally</div>
            <p class="mb-2">
                Already working with IPRN SMS?
                <a href="login.php" class="fw-semibold">Sign in to your account</a>
            </p>
            <p class="mb-0 small">
                Read our <a href="compliance.php">terms</a> and <a href="compliance.php">conditions</a>.
            </p>
        </div>
        <div class="auth-card-body">
            <h2 class="auth-form-title">Create Free Account</h2>
            <p class="auth-muted mb-3">Register to access premium ranges, live statistics and payout reports.</p>
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           required value="<?php echo e($_POST['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">Minimum 6 characters.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password"
                           name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Create account</button>
            </form>
            <p class="mt-3 text-center small">
                Already have an account? <a href="login.php">Login</a>
            </p>
        </div>
    </div>
</div>
<?php
render_footer();