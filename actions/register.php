<?php
require_once '../config/db.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
        $stmt->execute([':name' => $name, ':email' => $email, ':password' => $password]);
        echo json_encode(['success' => true, 'message' => 'Account created. Please login.']);
    } catch (PDOException $e) {
        $msg = strpos($e->getMessage(), 'Duplicate entry') !== false ? 'Email already exists.' : $e->getMessage();
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}
?>