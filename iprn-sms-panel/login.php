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

    $securityAnswer = trim($_POST['security_answer'] ?? '');
    if ($securityAnswer !== '19') {
        $error = 'Security question answer is incorrect.';
    } elseif (too_many_login_attempts()) {
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
            <div class="small fw-semibold mb-3">Account Login</div>
        </div>
        <div class="auth-card-body">
            <p class="auth-muted mb-3">Sign in to access your routes, test numbers and SMS reports.</p>
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <div class="auth-input-wrapper">
                        <span class="auth-input-icon">&#9993;</span>
                        <input type="email" class="form-control auth-input" id="email" name="email"
                               placeholder="you@example.com"
                               required value="<?php echo e($_POST['email'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="auth-input-wrapper">
                        <span class="auth-input-icon">&#128274;</span>
                        <input type="password" class="form-control auth-input" id="password" name="password"
                               placeholder="Password" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="security_answer">What is 9 + 10 ?</label>
                    <input type="text" class="form-control" id="security_answer" name="security_answer"
                           placeholder="Answer" required>
                </div>
                <?php if (too_many_login_attempts()): ?>
                    <div class="alert alert-warning small">
                        Login attempts temporarily blocked. Please wait a few minutes before trying again.
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn auth-btn-primary w-100">LOGIN</button>
            </form>
            <p class="mt-3 text-center small">
                Don't have an account? <a href="register.php">Create one</a>
            </p>
        </div>
    </div>
</div>
<?php
render_footer();