<?php
// like-post.php - สำหรับกดไลค์ใน index.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

require 'db.php';

$post_id = $_POST['post_id'] ?? 0;
$user_id = $_SESSION['user_id'];

if (!$post_id || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    // ตรวจสอบตาราง likes หรือ post_likes
    $tableName = null;
    $check = $pdo->query("SHOW TABLES LIKE 'post_likes'")->fetch();
    if ($check) {
        $tableName = 'post_likes';
    } else {
        $check = $pdo->query("SHOW TABLES LIKE 'likes'")->fetch();
        if ($check) {
            $tableName = 'likes';
        }
    }
    
    if (!$tableName) {
        echo json_encode(['success' => false, 'message' => 'ระบบไลค์ไม่พร้อมใช้งาน']);
        exit;
    }
    
    // ตรวจสอบว่ามี like อยู่แล้วหรือไม่
    $stmt = $pdo->prepare("SELECT id FROM $tableName WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // ลบ like
        $stmt = $pdo->prepare("DELETE FROM $tableName WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user_id]);
        $liked = false;
    } else {
        // เพิ่ม like
        $stmt = $pdo->prepare("INSERT INTO $tableName (post_id, user_id) VALUES (?, ?)");
        $stmt->execute([$post_id, $user_id]);
        $liked = true;
    }
    
    // นับ likes ใหม่
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $tableName WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $likes_count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true, 
        'liked' => $liked, 
        'likes_count' => $likes_count
    ]);
    
} catch (PDOException $e) {
    error_log("Like error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
}
?>