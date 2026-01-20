<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_admin();

$settings = get_settings();
$brandName = $settings['brand_name'] ?? 'IPRN SMS Panel';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $payoutId = (int) ($_POST['payout_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';

        if (!in_array($status, ['pending', 'approved', 'paid', 'rejected'], true)) {
            flash('error', 'Invalid payout status.');
        } elseif ($payoutId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE payouts 
                 SET status = ?, processed_at = CASE WHEN ? IN ("paid","rejected","approved") THEN NOW() ELSE processed_at END
                 WHERE id = ?'
            );
            $stmt->execute([$status, $status, $payoutId]);
            flash('success', 'Payout updated.');
        }

        redirect('billing.php');
    }
}

$stmtPayouts = $pdo->query(
    'SELECT p.*, u.email 
     FROM payouts p 
     JOIN users u ON u.id = p.user_id 
     ORDER BY p.created_at DESC'
);
$payouts = $stmtPayouts->fetchAll();

render_header('Billing &amp; Payouts - Admin - ' . $brandName, true, '', $settings['meta_description'] ?? null);
?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm mb-3">
            <div class="card-header">Payout Management</div>
            <div class="card-body">
                <p class="small text-muted mb-0">
                    Payouts are generated when users reach the configured minimum balance. You can approve or mark
                    payouts as paid after processing them via bKash, Nagad, PayPal, Stripe or bank transfer.
                </p>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">Payouts</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Amount (BDT)</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Processed</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($payouts): ?>
                            <?php foreach ($payouts as $p): ?>
                                <tr>
                                    <td><?php echo (int) $p['id']; ?></td>
                                    <td><?php echo e($p['email']); ?></td>
                                    <td>৳<?php echo number_format((float) $p['amount'], 2); ?></td>
                                    <td><?php echo e($p['method']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $p['status'] === 'paid' ? 'success' :
                                                ($p['status'] === 'pending' ? 'warning' :
                                                    ($p['status'] === 'approved' ? 'primary' : 'secondary'));
                                        ?>">
                                            <?php echo e(ucfirst($p['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo e($p['created_at']); ?></td>
                                    <td><?php echo e($p['processed_at']); ?></td>
                                    <td>
                                        <form method="post" class="d-flex gap-1">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="payout_id" value="<?php echo (int) $p['id']; ?>">
                                            <select name="status" class="form-select form-select-sm">
                                                <option value="pending" <?php echo $p['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="approved" <?php echo $p['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="paid" <?php echo $p['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                <option value="rejected" <?php echo $p['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">No payouts yet.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
render_footer();