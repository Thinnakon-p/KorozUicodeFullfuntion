<?php
session_start();
require 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ไม่ได้รับอนุญาต']);
    exit;
}

header('Content-Type: application/json');

$postId = $_POST['post_id'] ?? 0;
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$link = $_POST['link'] ?? '';

if (empty($postId) || empty($title) || empty($description) || empty($link)) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบ']);
    exit;
}

// ดึงโพสต์เดิม
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post || $post['user_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์แก้ไข']);
    exit;
}

// จัดการไฟล์ใหม่
$newImage = $post['image'];
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    // ลบไฟล์เก่า
    if ($post['image'] && file_exists('uploads/' . $post['image'])) {
        unlink('uploads/' . $post['image']);
    }
    
    $fileExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $fileName = 'post_' . time() . '.' . $fileExt;
    $filePath = 'uploads/' . $fileName;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
        $newImage = $fileName;
    }
}

// อัปเดตโพสต์
$stmt = $pdo->prepare("
    UPDATE posts SET title = ?, description = ?, link = ?, image = ? WHERE id = ?
");
$success = $stmt->execute([$title, $description, $link, $newImage, $postId]);

echo json_encode([
    'success' => $success, 
    'message' => $success ? 'แก้ไขสำเร็จ' : 'แก้ไขล้มเหลว'
]);
?>