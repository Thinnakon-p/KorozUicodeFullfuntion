<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_access']) || $_SESSION['admin_access'] !== true) {
    echo json_encode(['success' => false, 'message' => "ไม่มีสิทธิ์"]);
    exit;
}

try {
    // ตัวอย่าง: อัปเดตสถานะโพสต์ทั้งหมดเป็น locked (เพิ่มคอลัมน์ is_locked ในตาราง posts ถ้ายังไม่มี)
    $stmt = $pdo->prepare("UPDATE posts SET is_locked = 1");
    $stmt->execute();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
}
?>