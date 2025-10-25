<?php
// delete_post.php - รองรับ Form + AJAX + CSRF + likes/post_likes
session_start();
header('Content-Type: application/json');

require 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    http_response_code(403);
    exit;
}

// ตรวจสอบ CSRF Token
$csrfToken = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!empty($csrfToken) && !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'CSRF Token ไม่ถูกต้อง']);
    http_response_code(403);
    exit;
}

$postId = $_POST['post_id'] ?? $_GET['post_id'] ?? 0;

if (empty($postId)) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID']);
    http_response_code(400);
    exit;
}

// ✅ ตรวจสอบตาราง likes/post_likes (เดียวกับ like-post.php)
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

// ตรวจสอบสิทธิ์ + ดึงข้อมูลไฟล์
$stmt = $pdo->prepare("SELECT user_id, image, file_path FROM posts WHERE id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบโพสต์']);
    http_response_code(404);
    exit;
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
if ($post['user_id'] != $_SESSION['user_id'] && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ลบ']);
    http_response_code(403);
    exit;
}

// ✅ ลบไฟล์แนบ
if ($post['image'] && file_exists('uploads/' . $post['image'])) {
    unlink('uploads/' . $post['image']);
}
if ($post['file_path'] && file_exists($post['file_path'])) {
    unlink($post['file_path']);
}

// ✅ ลบ likes ที่เกี่ยวข้อง (รองรับ likes + post_likes)
if ($tableName) {
    $stmt = $pdo->prepare("DELETE FROM $tableName WHERE post_id = ?");
    $stmt->execute([$postId]);
} else {
    error_log("Delete post $postId: No likes table found");
}

// ลบโพสต์
$stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
$success = $stmt->execute([$postId]);

if ($success) {
    echo json_encode([
        'success' => true, 
        'message' => 'ลบโพสต์สำเร็จ',
        'table_used' => $tableName ?? 'none'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'ลบไม่สำเร็จ']);
    http_response_code(500);
}
?>