<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$totalRanges = (int) $pdo->query('SELECT COUNT(*) FROM ranges')->fetchColumn();
$activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalRevenue = (float) $pdo->query('SELECT COALESCE(SUM(cost), 0) FROM sms_logs')->fetchColumn();
$monthlySmsStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(sms_count), 0) FROM sms_logs WHERE created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'
);
$monthlySmsStmt->execute();
$monthlySms = (int) $monthlySmsStmt->fetchColumn();
$pendingPayouts = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount), 0) FROM payouts WHERE status = 'pending'"
)->fetchColumn();

echo json_encode([
    'total_ranges' => $totalRanges,
    'active_users' => $activeUsers,
    'total_revenue' => $totalRevenue,
    'monthly_sms' => $monthlySms,
    'pending_payouts' => $pendingPayouts,
]);