<?php
// CRON: Create payout records for users who reached minimum payout threshold.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$settings = get_settings();
$minPayout = (float) $settings['min_payout'];

$stmt = $pdo->prepare(
    'SELECT * FROM users 
     WHERE balance >= ? 
       AND id NOT IN (
         SELECT user_id FROM payouts WHERE status IN ("pending","approved")
       )'
);
$stmt->execute([$minPayout]);
$eligibleUsers = $stmt->fetchAll();

if (!$eligibleUsers) {
    echo 'No eligible users for payouts.' . PHP_EOL;
    return;
}

$insert = $pdo->prepare(
    'INSERT INTO payouts (user_id, amount, status, method, details) 
     VALUES (?, ?, "pending", ?, ?)'
);

foreach ($eligibleUsers as $user) {
    $method = 'manual';
    $details = 'Auto-generated payout request via CRON.';
    $insert->execute([$user['id'], $user['balance'], $method, $details]);
    echo 'Created payout for user ID ' . $user['id'] . ' amount ' . $user['balance'] . PHP_EOL;
}