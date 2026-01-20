<?php
// Simple web installer for IPRN SMS Panel.

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = trim($_POST['db_pass'] ?? '');
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    if ($dbName === '' || $dbUser === '' || $baseUrl === '' || $adminEmail === '' || $adminPassword === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Admin email must be a valid email address.';
    } elseif (strlen($adminPassword) < 6) {
        $error = 'Admin password must be at least 6 characters.';
    } else {
        try {
            $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Load SQL schema
            $sqlFile = __DIR__ . '/../database.sql';
            if (!file_exists($sqlFile)) {
                throw new RuntimeException('database.sql file is missing.');
            }
            $sql = file_get_contents($sqlFile);
            $statements = array_filter(array_map('trim', preg_split('/;[\r\n]+/', $sql)));
            foreach ($statements as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            // Insert default settings
            $pdo->exec(
                "INSERT INTO settings (id, min_payout, signup_enabled, default_rate, default_payout)
                 VALUES (1, 5000, 1, 0.08, 70)"
            );

            // Create admin user
            $adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password, role, numbers_limit) VALUES (?, ?, "admin", 100)'
            );
            $stmt->execute([$adminEmail, $adminHash]);

            // Create demo user
            $demoHash = password_hash('user123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password, role, numbers_limit, balance) VALUES (?, ?, "user", 50, ?)'
            );
            $stmt->execute(['user@demo.com', $demoHash, 18450]);

            // Sample ranges
            $ranges = [
                ['Afghanistan RTX 761', 'Afghanistan', 0.12, 80],
                ['Bangladesh GP 1645', 'Bangladesh', 0.08, 70],
                ['USA AT&amp;T 555', 'USA', 0.15, 60],
                ['UK Vodafone 0906', 'United Kingdom', 0.50, 65],
            ];

            $rangeIds = [];
            $stmtRange = $pdo->prepare(
                'INSERT INTO ranges (range_name, country, rate, payout_percent, total_stock, available_stock, status)
                 VALUES (?, ?, ?, ?, ?, ?, "active")'
            );
            foreach ($ranges as $r) {
                // For demo purposes, create 10 numbers per range
                $stock = 10;
                $stmtRange->execute([$r[0], $r[1], $r[2], $r[3], $stock, $stock]);
                $rangeIds[] = (int) $pdo->lastInsertId();
            }

            $stmtNumber = $pdo->prepare('INSERT INTO numbers (range_id, number, status) VALUES (?, ?, "available")');

            // Generate a few sample numbers
            foreach ($rangeIds as $index => $rid) {
                for ($i = 0; $i < 10; $i++) {
                    switch ($index) {
                        case 0:
                            $number = '93761' . str_pad((string) $i, 5, '0', STR_PAD_LEFT);
                            break;
                        case 1:
                            $number = '88016' . str_pad((string) $i, 5, '0', STR_PAD_LEFT);
                            break;
                        case 2:
                            $number = '555' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
                            break;
                        default:
                            $number = '0906' . str_pad((string) $i, 6, '0', STR_PAD_LEFT);
                            break;
                    }
                    $stmtNumber->execute([$rid, $number]);
                }
            }

            // Sample SMS logs for stats
            $stmtSms = $pdo->prepare(
                'INSERT INTO sms_logs (user_id, range_id, sms_count, cost, country, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $userIdStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $userIdStmt->execute(['user@demo.com']);
            $demoUser = $userIdStmt->fetch();
            $demoUserId = $demoUser ? (int) $demoUser['id'] : 2;

            $now = new DateTimeImmutable();
            for ($d = 0; $d < 7; $d++) {
                $date = $now->sub(new DateInterval('P' . $d . 'D'))->format('Y-m-d 12:00:00');
                foreach ($rangeIds as $idx => $rid) {
                    $smsCount = rand(100, 1000);
                    $rate = $ranges[$idx][2];
                    $cost = $smsCount * $rate;
                    $country = $ranges[$idx][1];
                    $stmtSms->execute([$demoUserId, $rid, $smsCount, $cost, $country, $date]);
                }
            }

            // Write config.php
            $configPath = __DIR__ . '/../includes/config.php';
            $configContent = "<?php\n"
                . "// Auto-generated by installer\n"
                . "define('DB_HOST', '" . addslashes($dbHost) . "');\n"
                . "define('DB_NAME', '" . addslashes($dbName) . "');\n"
                . "define('DB_USER', '" . addslashes($dbUser) . "');\n"
                . "define('DB_PASS', '" . addslashes($dbPass) . "');\n"
                . "define('BASE_URL', '" . addslashes($baseUrl) . "');\n"
                . "define('SESSION_TIMEOUT', 1800);\n";

            if (file_put_contents($configPath, $configContent) === false) {
                throw new RuntimeException('Failed to write config.php. Please check file permissions.');
            }

            $success = 'Installation completed. You can now delete the install/ folder and log in.';
        } catch (Throwable $e) {
            $error = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IPRN SMS Panel Installer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">IPRN SMS Panel Installer</div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?><br>
                            Default demo credentials:<br>
                            <strong>Admin:</strong> <?php echo htmlspecialchars($adminEmail ?: 'admin@demo.com', ENT_QUOTES, 'UTF-8'); ?>
                            / your chosen password<br>
                            <strong>User:</strong> user@demo.com / user123
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <h5 class="mb-3">Database</h5>
                        <div class="mb-3">
                            <label class="form-label" for="db_host">Host</label>
                            <input type="text" class="form-control" id="db_host" name="db_host"
                                   value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="db_name">Database Name</label>
                            <input type="text" class="form-control" id="db_name" name="db_name"
                                   value="<?php echo htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="db_user">Username</label>
                            <input type="text" class="form-control" id="db_user" name="db_user"
                                   value="<?php echo htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="db_pass">Password</label>
                            <input type="password" class="form-control" id="db_pass" name="db_pass">
                        </div>

                        <h5 class="mb-3">Application</h5>
                        <div class="mb-3">
                            <label class="form-label" for="base_url">Base URL</label>
                            <input type="text" class="form-control" id="base_url" name="base_url"
                                   placeholder="https://yourdomain.com/iprn-sms-panel"
                                   value="<?php echo htmlspecialchars($_POST['base_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <h5 class="mb-3">Admin Account</h5>
                        <div class="mb-3">
                            <label class="form-label" for="admin_email">Admin Email</label>
                            <input type="email" class="form-control" id="admin_email" name="admin_email"
                                   value="<?php echo htmlspecialchars($_POST['admin_email'] ?? 'admin@demo.com', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="admin_password">Admin Password</label>
                            <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Install</button>
                    </form>
                </div>
            </div>
            <p class="mt-3 small text-muted text-center">
                After a successful installation, delete the <code>install/</code> folder for security.
            </p>
        </div>
    </div>
</div>
</body>
</html>