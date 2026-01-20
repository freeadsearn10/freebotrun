<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

render_header('Pricing - IPRN SMS Panel');
?>
<h1 class="h4 mb-3">Pricing Overview</h1>
<p class="small text-muted">
    Pricing is fully controlled by the ranges you configure in the admin panel. Below is a quick view of current
    demo ranges and their per-SMS rates.
</p>
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Range</th>
                    <th>Country</th>
                    <th>Rate (USD)</th>
                    <th>Payout %</th>
                    <th>Total Stock</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $pdo->query('SELECT * FROM ranges ORDER BY created_at DESC');
                $ranges = $stmt->fetchAll();
                if ($ranges):
                    foreach ($ranges as $r):
                        ?>
                        <tr>
                            <td><?php echo e($r['range_name']); ?></td>
                            <td><?php echo e($r['country']); ?></td>
                            <td>$<?php echo number_format((float) $r['rate'], 4); ?></td>
                            <td><?php echo number_format((float) $r['payout_percent'], 0); ?>%</td>
                            <td><?php echo number_format((int) $r['total_stock']); ?></td>
                        </tr>
                    <?php
                    endforeach;
                else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">No ranges configured yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
render_footer();