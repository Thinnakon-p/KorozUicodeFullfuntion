<?php
session_start();
require 'db.php';

header('Content-Type: application/json');
if (isset($_SESSION['admin_access']) && $_SESSION['admin_access']) {
    $role_id = $_POST['role_id'];
    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->execute([$role_id]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
exit;
?>