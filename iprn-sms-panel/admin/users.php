<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $numbersLimit = (int) ($_POST['numbers_limit'] ?? 10);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email address is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!in_array($role, ['user', 'admin'], true)) {
        $error = 'Invalid role.';
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
            $stmt->execute([$email, $hash, $role, $numbersLimit]);
            flash('success', 'User created successfully.');
        }
    }

    if ($error) {
        flash('error', $error);
    }

    redirect('users.php');
}

$stmtUsers = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
$users = $stmtUsers->fetchAll();

render_header('Users - Admin - IPRN SMS Panel', true);
?>
<div class="row mb-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header">Create User</div>
            <div class="card-body">
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <div class="form-text">Minimum 6 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role">Role</label>
                        <select id="role" name="role" class="form-select">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="numbers_limit">Numbers Limit</label>
                        <input type="number" id="numbers_limit" name="numbers_limit" class="form-control"
                               value="10" min="1">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Create User</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8 mt-3 mt-lg-0">
        <div class="card shadow-sm">
            <div class="card-header">All Users</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Balance</th>
                            <th>Numbers Limit</th>
                            <th>Created</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($users): ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo (int) $u['id']; ?></td>
                                    <td><?php echo e($u['email']); ?></td>
                                    <td><span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : 'secondary'; ?>">
                                            <?php echo e($u['role']); ?>
                                        </span></td>
                                    <td>৳<?php echo number_format((float) $u['balance'], 2); ?></td>
                                    <td><?php echo (int) $u['numbers_limit']; ?></td>
                                    <td><?php echo e($u['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">No users found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted p-3 mb-0">
                    Users can log in to a static dashboard to see their balances, SMS statistics, and available ranges.
                </p>
            </div>
        </div>
    </div>
</div>
<?php
render_footer();