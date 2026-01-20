<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

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

render_header('Login - IPRN SMS Panel');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header">Secure Login</div>
            <div class="card-body">
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
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
        <p class="mt-3 text-center small">
            Don't have an account? <a href="register.php">Register</a>
        </p>
    </div>
</div>
<?php
render_footer();