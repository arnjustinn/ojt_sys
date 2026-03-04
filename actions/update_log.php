<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    $user_id = $_SESSION['user_id'];
    $log_date = $_POST['log_date'];
    $status = $_POST['status'] ?? 'Present';
    $tasks = $_POST['tasks'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    $start_time = ($status === 'Absent') ? '00:00:00' : ($_POST['start_time'] ?? '00:00:00');
    $end_time = ($status === 'Absent') ? '00:00:00' : ($_POST['end_time'] ?? '00:00:00');
    $total_hours = ($status === 'Absent') ? 0 : calculateTotalHours($start_time, $end_time);

    try {
        $sql = "UPDATE entries SET 
                log_date = :log_date, 
                start_time = :start_time, 
                end_time = :end_time, 
                tasks = :tasks, 
                status = :status, 
                remarks = :remarks, 
                total_hours = :total_hours 
                WHERE id = :id AND user_id = :user_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $user_id,
            ':log_date' => $log_date,
            ':start_time' => $start_time,
            ':end_time' => $end_time,
            ':tasks' => $tasks,
            ':status' => $status,
            ':remarks' => $remarks,
            ':total_hours' => $total_hours
        ]);
        
        $stmt = $conn->prepare("SELECT * FROM entries WHERE user_id = :user_id ORDER BY log_date DESC, created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        $logs = formatLogs($stmt->fetchAll(PDO::FETCH_ASSOC));
        $grand_total = array_sum(array_column($logs, 'total_hours'));

        echo json_encode(['success' => true, 'message' => 'Sequence updated.', 'logs' => $logs, 'grand_total' => $grand_total]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>