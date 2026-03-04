<?php
// Note: This file can be called directly or included by other actions
if (basename($_SERVER['PHP_SELF']) == 'fetch_log.php') {
    require_once '../config/db.php';
    require_once '../includes/functions.php';
    session_start();
    header('Content-Type: application/json');
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User session expired.']);
    exit;
}

try {
    // Filter by user_id to ensure the UI updates with the correct private data
    $stmt = $conn->prepare("SELECT * FROM entries WHERE user_id = :user_id ORDER BY log_date DESC, created_at DESC");
    $stmt->execute([':user_id' => $user_id]);
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