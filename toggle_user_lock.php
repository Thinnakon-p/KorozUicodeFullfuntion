<?php
session_start();
require 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid CSRF token']);
    exit;
}

$user_id = $_POST['user_id'] ?? 0;
$stmt = $pdo->prepare("UPDATE users SET is_locked = NOT is_locked WHERE id = ?");
$success = $stmt->execute([$user_id]);

header('Content-Type: application/json');
echo json_encode(['success' => $success, 'message' => $success ? 'User lock status toggled' : 'Failed to toggle user lock']);
?>