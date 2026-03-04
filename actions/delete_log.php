<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id']) && isset($_SESSION['user_id'])) {
    $id = $_POST['id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Delete only if the record belongs to the authenticated user
        $stmt = $conn->prepare("DELETE FROM entries WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $id, ':user_id' => $user_id]);
        
        // Return fresh logs to update the UI without refreshing
        require 'fetch_logs.php';
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized request.']);
}
?>