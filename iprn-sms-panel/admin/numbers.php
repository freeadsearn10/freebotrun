<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_admin();

$uploadDir = __DIR__ . '/../upload';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $rangeName = trim($_POST['range_name'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $rate = (float) ($_POST['rate'] ?? 0);
    $payoutPercent = (float) ($_POST['payout_percent'] ?? 0);

    if ($rangeName === '') {
        $error = 'Range name is required.';
    } elseif ($rate <= 0) {
        $error = 'Rate must be greater than zero.';
    } elseif ($payoutPercent <= 0 || $payoutPercent > 100) {
        $error = 'Payout percent must be between 1 and 100.';
    } elseif (!isset($_FILES['numbers_file']) || $_FILES['numbers_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'A valid TXT file is required.';
    } else {
        $file = $_FILES['numbers_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'txt') {
            $error = 'Only .txt files are allowed.';
        } else {
            $target = $uploadDir . '/' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                $error = 'Failed to move uploaded file.';
            } else {
                $lines = file($target, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $numbers = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if (!ctype_digit($line)) {
                        continue;
                    }
                    $numbers[] = $line;
                }

                $total = count($numbers);

                if ($total === 0) {
                    $error = 'No valid numeric lines found in file.';
                } else {
                    try {
                        $pdo->beginTransaction();

                        $stmtRange = $pdo->prepare(
                            'INSERT INTO ranges (range_name, country, rate, payout_percent, total_stock, available_stock, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)'
                        );
                        $stmtRange->execute([$rangeName, $country, $rate, $payoutPercent, $total, $total, 'active']);
                        $rangeId = (int) $pdo->lastInsertId();

                        $stmtNumber = $pdo->prepare(
                            'INSERT INTO numbers (range_id, number, status) VALUES (?, ?, ?)'
                        );
                        foreach ($numbers as $number) {
                            $stmtNumber->execute([$rangeId, $number, 'available']);
                        }

                        $pdo->commit();
                        flash('success', 'Range "' . $rangeName . '" created with ' . number_format($total) . ' numbers.');
                        redirect('numbers.php');
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $error = 'Failed to save numbers: ' . $e->getMessage();
                    }
                }
            }
        }
    }

    if ($error) {
        flash('error', $error);
        redirect('numbers.php');
    }
}

$stmtRanges = $pdo->query(
    'SELECT r.*, 
            (SELECT COUNT(*) FROM numbers n WHERE n.range_id = r.id) AS numbers_count
     FROM ranges r
     ORDER BY r.created_at DESC'
);
$ranges = $stmtRanges->fetchAll();

render_header('Numbers &amp; Ranges - Admin - IPRN SMS Panel', true);
?>
<div class="row mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header">Upload Numbers (TXT Only)</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="numbers_file">TXT File</label>
                        <input type="file" class="form-control" id="numbers_file" name="numbers_file" accept=".txt" required>
                        <div class="form-text">One number per line. Digits only (e.g. 8801612345678, 55555).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="range_name">Range Name</label>
                        <input type="text" class="form-control" id="range_name" name="range_name"
                               placeholder="Afghanistan RTX 761" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="country">Country</label>
                        <input type="text" class="form-control" id="country" name="country" placeholder="Afghanistan">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label" for="rate">Rate (USD)</label>
                            <input type="number" step="0.0001" min="0" class="form-control" id="rate" name="rate"
                                   placeholder="0.12" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label" for="payout_percent">Payout %</label>
                            <input type="number" step="1" min="1" max="100" class="form-control"
                                   id="payout_percent" name="payout_percent" placeholder="80" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Upload All</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mt-3 mt-lg-0">
        <div class="card shadow-sm">
            <div class="card-header">Ranges &amp; Stocks</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Range Name</th>
                            <th>Country</th>
                            <th>Rate</th>
                            <th>Payout</th>
                            <th>Stock</th>
                            <th>Numbers</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($ranges): ?>
                            <?php foreach ($ranges as $range): ?>
                                <tr>
                                    <td><?php echo e($range['range_name']); ?></td>
                                    <td><?php echo e($range['country']); ?></td>
                                    <td>$<?php echo number_format((float) $range['rate'], 4); ?></td>
                                    <td><?php echo number_format((float) $range['payout_percent'], 0); ?>%</td>
                                    <td><?php echo number_format((int) $range['total_stock']); ?></td>
                                    <td><?php echo number_format((int) $range['numbers_count']); ?></td>
                                    <td>
                                        <?php if ($range['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">No ranges created yet.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted p-3 mb-0">
                    TXT files are stored under <code>/upload</code>. Each line is validated as digits-only before being
                    attached to the selected range.
                </p>
            </div>
        </div>
    </div>
</div>
<?php
render_footer();