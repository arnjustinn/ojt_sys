<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password required.']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords mismatch.']);
        exit;
    }

    try {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->execute([':password' => $hashed, ':id' => $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Security updated.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database failure.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
}
?>