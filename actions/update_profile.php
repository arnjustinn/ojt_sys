<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];

    try {
        $stmt = $conn->prepare("UPDATE users SET name = :name, email = :email WHERE id = :id");
        $stmt->execute([':name' => $name, ':email' => $email, ':id' => $user_id]);
        
        $_SESSION['user_name'] = $name;
        
        echo json_encode(['success' => true, 'message' => 'Profile updated.']);
    } catch (PDOException $e) {
        $error = strpos($e->getMessage(), 'Duplicate entry') !== false ? 'Email exists.' : 'Database failure.';
        echo json_encode(['success' => false, 'message' => $error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
}
?>