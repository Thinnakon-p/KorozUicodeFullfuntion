<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_access']) || $_SESSION['admin_access'] !== true) {
    echo json_encode([]);
    exit;
}

require 'db.php';

$stmt = $pdo->prepare("SELECT id, key_hash, created_at FROM admin_keys WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($keys);
?>