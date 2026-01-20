<?php
// CRON: Aggregate basic statistics (optional).
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$totalSms = (int) $pdo->query('SELECT COALESCE(SUM(sms_count), 0) FROM sms_logs')->fetchColumn();
$totalRevenue = (float) $pdo->query('SELECT COALESCE(SUM(cost), 0) FROM sms_logs')->fetchColumn();

echo 'Stats updated - Total SMS: ' . $totalSms . ' | Total Revenue: ' . number_format($totalRevenue, 2) . PHP_EOL;