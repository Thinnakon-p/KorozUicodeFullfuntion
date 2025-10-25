<?php
require 'db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM likes");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['total' => $result['total']]);
} catch (PDOException $e) {
    echo json_encode(['total' => 0]);
}
?>