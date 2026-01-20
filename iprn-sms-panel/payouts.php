<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = current_user();
$settings = get_settings();

$minPayout = (float) $settings['min_payout'];
$balance = (float) $user['balance'];

// Sum of pending/approved payouts to avoid double requests
$stmtPending = $pdo->prepare(
    'SELECT COALESCE(SUM(amount), 0) AS total_pending 
     FROM payouts 
     WHERE user_id = ? AND status IN ("pending","approved")'
);
$stmtPending->execute([$user['id']]);
$pendingRow = $stmtPending->fetch() ?: ['total_pending' => 0];
$pendingAmount = (float) $pendingRow['total_pending'];

$hasOpenPayout = $pendingAmount > 0;
$eligible = $balance >= $minPayout && !$hasOpenPayout;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $method = trim($_POST['method'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $details = trim($_POST['details'] ?? '');

    if (!$eligible) {
        $error = 'You are not eligible to request a payout at this time.';
    } elseif ($amount < $minPayout) {
        $error = 'Requested amount must be at least the minimum payout.';
    } elseif ($amount > $balance) {
        $error = 'Requested amount cannot be greater than your current balance.';
    } elseif ($method === '') {
        $error = 'Please select a payout method.';
    } elseif ($details === '') {
        $error = 'Please provide payout details (e.g. bKash/Nagad number or PayPal email).';
    }

    if ($error === '') {
        $stmtInsert = $pdo->prepare(
            'INSERT INTO payouts (user_id, amount, status, method, details) 
             VALUES (?, ?, "pending", ?, ?)'
        );
        $stmtInsert->execute([$user['id'], $amount, $method, $details]);

        flash('success', 'Payout request submitted and is now pending review.');
        header('Location: payouts.php');
        exit;
    } else {
        flash('error', $error);
        header('Location: payouts.php');
        exit;
    }
}

// Load user payouts
$stmtPayouts = $pdo->prepare(
    'SELECT * FROM payouts WHERE user_id = ? ORDER BY created_at DESC'
);
$stmtPayouts->execute([$user['id']]);
$payouts = $stmtPayouts->fetchAll();

render_header('Payouts - IPRN SMS Panel');
?>
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header">Payout Overview</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Current Balance</span>
                    <span class="fw-semibold">৳<?php echo number_format($balance, 2); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Minimum Payout</span>
                    <span class="fw-semibold">৳<?php echo number_format($minPayout, 2); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Pending / Approved Payouts</span>
                    <span class="fw-semibold">৳<?php echo number_format($pendingAmount, 2); ?></span>
                </div>
                <hr>
                <div>
                    <span class="text-muted small">Status:</span>
                    <?php if ($eligible): ?>
                        <span class="badge bg-success ms-1">Eligible for payout</span>
                    <?php elseif ($hasOpenPayout): ?>
                        <span class="badge bg-warning text-dark ms-1">Pending payout in progress</span>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-1">Not eligible yet</span>
                    <?php endif; ?>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    Payouts are processed manually by the administrator via bKash, Nagad, PayPal, Stripe or bank transfer.
                    Once processed, the status will change to <strong>Paid</strong>.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header">Request Payout</div>
            <div class="card-body">
                <?php if ($eligible): ?>
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="amount">Amount (৳)</label>
                            <input type="number"
                                   class="form-control"
                                   id="amount"
                                   name="amount"
                                   min="<?php echo e($minPayout); ?>"
                                   max="<?php echo e($balance); ?>"
                                   step="0.01"
                                   value="<?php echo e($balance); ?>"
                                   required>
                            <div class="form-text small">
                                Minimum ৳<?php echo number_format($minPayout, 2); ?>, up to your current balance.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="method">Payout Method</label>
                            <select class="form-select" id="method" name="method" required>
                                <option value="">Select method</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Stripe">Stripe</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Other">Other / Manual</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="details">Payout Details</label>
                            <textarea class="form-control" id="details" name="details" rows="3" required
                                      placeholder="e.g. bKash: 01XXXXXXXXX, PayPal: you@example.com"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Payout Request</button>
                    </form>
                <?php else: ?>
                    <p class="small text-muted mb-0">
                        You do not currently meet the minimum payout requirement or you already have a payout in progress.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">Your Payouts</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Amount (BDT)</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Processed</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($payouts): ?>
                    <?php foreach ($payouts as $p): ?>
                        <tr>
                            <td><?php echo (int) $p['id']; ?></td>
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
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">No payout history yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
render_footer();