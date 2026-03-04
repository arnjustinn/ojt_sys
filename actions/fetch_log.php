<?php
/**
 * Modified fetch_log.php to include full completion metrics.
 * This allows background refreshes to update all dashboard stats.
 */
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
    // Fetch logs filtered by authenticated user
    $stmt = $conn->prepare("SELECT * FROM entries WHERE user_id = :user_id ORDER BY log_date DESC, created_at DESC");
    $stmt->execute([':user_id' => $user_id]);
    $logs = formatLogs($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Fetch dynamic completion metrics using shared utility
    $metrics = getCompletionMetrics($conn, $user_id);

    echo json_encode([
        'success' => true,
        'message' => 'Logs retrieved successfully.',
        'logs' => $logs,
        'grand_total' => $metrics['rendered_hours'],
        'estimated_date' => $metrics['estimated_date'],
        'remaining_days' => $metrics['remaining_days']
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>