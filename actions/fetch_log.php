<?php
// Note: This file can be called directly or included by other actions
if (basename($_SERVER['PHP_SELF']) == 'fetch_logs.php') {
    require_once '../config/db.php';
    require_once '../includes/functions.php';
    header('Content-Type: application/json');
}

try {
    $stmt = $conn->query("SELECT * FROM entries ORDER BY log_date DESC, created_at DESC");
    $logs = formatLogs($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $grand_total = array_sum(array_column($logs, 'total_hours'));

    echo json_encode([
        'success' => true,
        'message' => 'Logs retrieved successfully.',
        'logs' => $logs,
        'grand_total' => $grand_total
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>