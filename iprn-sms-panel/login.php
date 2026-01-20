<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$settings = get_settings();
$brandName = $settings['brand_name'] ?? 'IPRN SMS Panel';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    if (too_many_login_attempts()) {
        $error = 'Too many failed attempts. Please try again later.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Email and password are required.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                register_login_attempt(true);
                $_SESSION['user_id'] = $user['id'];
                flash('success', 'Welcome back, ' . $user['email'] . '.');

                if ($user['role'] === 'admin') {
                    redirect('admin/index.php');
                } else {
                    redirect('dashboard.php');
                }
            } else {
                register_login_attempt(false);
                $error = 'Invalid email or password.';
            }
        }
    }
}

if ($error) {
    flash('error', $error);
}

render_header('Login - ' . $brandName, false, 'auth-page', $settings['meta_description'] ?? null);
?>
<div class="auth-wrapper">
    <div class="auth-card shadow-sm">
        <div class="auth-card-header">
            <h1 class="mb-1"><?php echo e($brandName); ?></h1>
            <div class="small fw-semibold mb-3">Your trusted premium SMS numbers partner</div>
            <p class="mb-2">
                Don't have an account?
                <a href="contact.php" class="fw-semibold">Contact us</a>
            </p>
            <p class="mb-0 small">
                Read our <a href="compliance.php">terms</a> and <a href="compliance.php">conditions</a>.
            </p>
        </div>
        <div class="auth-card-body">
            <h2 class="auth-form-title">Account Login</h2>
            <p class="auth-muted mb-3">Sign in to access your ranges, balances and live SMS statistics.</p>
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
                </div>
                <?php if (too_many_login_attempts()): ?>
                    <div class="alert alert-warning small">
                        Login attempts temporarily blocked. Please wait a few minutes before trying again.
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check small">
                        <input class="form-check-input" type="checkbox" value="1" id="remember_me" disabled>
                        <label class="form-check-label" for="remember_me">
                            Remember me
                        </label>
                    </div>
                    <a href="contact.php" class="small text-decoration-none">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-primary w-100">Log in</button>
            </form>
            <p class="mt-3 text-center small">
                Don't have an account? <a href="register.php">Create one</a>
            </p>
        </div>
    </div>
</div>
<?php
render_footer();