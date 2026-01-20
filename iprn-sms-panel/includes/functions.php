<?php

function init_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }

    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['initiated'] = true;
    }

    $_SESSION['last_activity'] = time();
}

function redirect(string $path): void
{
    if (strpos($path, 'http') !== 0) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        $path = rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function check_csrf_token(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';

        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            exit('Invalid CSRF token');
        }
    }
}

function flash(string $key, ?string $message = null)
{
    if ($message === null) {
        if (!empty($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }

        return null;
    }

    $_SESSION['flash'][$key] = $message;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    static $user = null;

    if ($user !== null) {
        return $user;
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    global $pdo;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        return null;
    }

    return $user;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    $user = current_user();

    if (!$user || $user['role'] !== 'admin') {
        flash('error', 'You do not have access to that page.');
        redirect('dashboard.php');
    }
}

function too_many_login_attempts(): bool
{
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $last = $_SESSION['last_login_attempt'] ?? 0;

    return $attempts >= 5 && (time() - $last) < 900;
}

function register_login_attempt(bool $success): void
{
    if ($success) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_login_attempt'] = time();
        return;
    }

    $attempts = $_SESSION['login_attempts'] ?? 0;
    $_SESSION['login_attempts'] = $attempts + 1;
    $_SESSION['last_login_attempt'] = time();
}

function get_settings(): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    global $pdo;

    $stmt = $pdo->query('SELECT * FROM settings WHERE id = 1');
    $settings = $stmt->fetch() ?: [
        'min_payout' => 5000,
        'signup_enabled' => 1,
        'default_rate' => 0.08,
        'default_payout' => 70,
    ];

    return $settings;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_flash_messages(): void
{
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $type => $message) {
            $class = $type === 'error' ? 'danger' : 'success';

            echo '<div class="alert alert-' . $class . ' alert-dismissible fade show" role="alert">'
                . e($message)
                . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                . '</div>';
        }

        $_SESSION['flash'] = [];
    }
}

function render_header(string $title = 'IPRN SMS Panel', bool $is_admin = false): void
{
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?php echo e($title); ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
              integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/admin.css">
    </head>
    <body class="<?php echo $is_admin ? 'admin-body' : 'public-body'; ?>">
    <?php if ($is_admin): ?>
        <nav class="navbar navbar-dark bg-dark navbar-expand-lg fixed-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?php echo $base; ?>/admin/index.php">IPRN SMS Admin</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false"
                        aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="adminNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/admin/index.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/admin/users.php">Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/admin/numbers.php">Numbers</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/admin/settings.php">Settings</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/admin/billing.php">Billing</a></li>
                    </ul>
                    <span class="navbar-text me-3">
                        <?php $user = current_user(); ?>
                        <?php if ($user): ?>
                            <?php echo e($user['email']); ?>
                        <?php endif; ?>
                    </span>
                    <a href="<?php echo $base; ?>/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
                </div>
            </div>
        </nav>
        <div class="container-fluid admin-container">
            <div class="row">
                <div class="col-md-2 col-lg-2 d-none d-md-block bg-light sidebar">
                    <div class="list-group list-group-flush pt-3">
                        <a href="<?php echo $base; ?>/admin/index.php" class="list-group-item list-group-item-action">Dashboard</a>
                        <a href="<?php echo $base; ?>/admin/users.php" class="list-group-item list-group-item-action">Users</a>
                        <a href="<?php echo $base; ?>/admin/numbers.php" class="list-group-item list-group-item-action">Numbers</a>
                        <a href="<?php echo $base; ?>/admin/settings.php" class="list-group-item list-group-item-action">Settings</a>
                        <a href="<?php echo $base; ?>/admin/billing.php" class="list-group-item list-group-item-action">Billing</a>
                    </div>
                </div>
                <main class="col-12 col-md-10 ms-sm-auto px-3 pt-4">
                    <?php render_flash_messages(); ?>
    <?php else: ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="<?php echo $base; ?>/index.php">IPRN SMS Panel</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#publicNavbar" aria-controls="publicNavbar"
                        aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="publicNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/pricing.php">Pricing</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/live-stats.php">Live Stats</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/api-docs.php">API</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/compliance.php">Compliance</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/contact.php">Contact</a></li>
                    </ul>
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <?php if (!is_logged_in()): ?>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/login.php">Login</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/register.php">Register</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/dashboard.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/payouts.php">Payouts</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>/logout.php">Logout</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
        <main class="container py-4">
            <?php render_flash_messages(); ?>
    <?php endif;
}

function render_footer(): void
{
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    ?>
        </main>
        <?php if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false): ?>
            </div><!-- row -->
        </div><!-- container-fluid -->
        <?php endif; ?>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"
                integrity="sha256-3gJwYp4Z5qX6Ch12eDqO/flMHDL/95B2S/bR5CV4wZc="
                crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
                integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe"
                crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script src="<?php echo $base; ?>/assets/js/admin.js"></script>
    </body>
    </html>
    <?php
}

init_session();