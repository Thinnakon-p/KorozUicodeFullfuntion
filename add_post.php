<?php
session_start();
require 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ไม่ได้รับอนุญาต']);
    exit;
}

header('Content-Type: application/json');

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$link = $_POST['link'] ?? '';
$userId = $_SESSION['user_id'];

if (empty($title) || empty($description) || empty($link)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
    exit;
}

// สร้างโฟลเดอร์ uploads ถ้ายังไม่มี
if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
}
if (!is_dir('packs')) {
    mkdir('packs', 0755, true);
}

$uploadSuccess = false;
$uploadType = 'image';
$filePath = '';
$fileName = '';

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedImageExt = ['jpg', 'jpeg', 'png', 'gif'];
    $allowedPackExt = ['mcpack', 'zip'];
    
    $fileExt = strtolower($fileExt);
    
    if (in_array($fileExt, $allowedImageExt)) {
        // Upload รูปภาพ
        $uploadType = 'image';
        $fileName = 'post_' . time() . '.' . $fileExt;
        $filePath = 'uploads/' . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $uploadSuccess = true;
        }
        
    } elseif (in_array($fileExt, $allowedPackExt)) {
        // Upload MCPack/ZIP
        $uploadType = 'pack';
        $fileName = $file['name']; // เก็บชื่อเดิม
        $filePath = 'packs/' . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $uploadSuccess = true;
        }
    }
}

// บันทึกโพสต์
if ($uploadSuccess) {
    $stmt = $pdo->prepare("
        INSERT INTO posts (title, description, upload_type, file_path, file_name, link, image, user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $success = $stmt->execute([
        $title, $description, $uploadType, $filePath, $fileName, $link,
        $uploadType === 'image' ? $fileName : null, $userId
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'เพิ่มโพสต์สำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'บันทึกฐานข้อมูลล้มเหลว']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'อัปโหลดไฟล์ล้มเหลว']);
}
?>