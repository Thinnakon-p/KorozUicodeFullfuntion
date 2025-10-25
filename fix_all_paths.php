<?php
// fix_all_paths.php - แก้ DB + สร้าง default images
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403); exit;
}

require 'db.php';

$fixed = 0;
$created = 0;
$failed = 0;

// 1. สร้างโฟลเดอร์
$folders = ['uploads', 'posts', 'images', 'assets'];
foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }
}

// 2. สร้าง default images
$defaultImage = 'assets/default-post-image.png';
if (!file_exists($defaultImage)) {
    // สร้างภาพสีเทา 400x300
    $img = imagecreatetruecolor(400, 300);
    $gray = imagecolorallocate($img, 128, 128, 128);
    imagefill($img, 0, 0, $gray);
    imagepng($img, $defaultImage);
    imagedestroy($img);
    $created++;
}

try {
    // 3. แก้ path ใน DB
    $stmt = $pdo->query("SELECT id, image FROM posts WHERE image IS NOT NULL AND image != ''");
    $posts = $stmt->fetchAll();
    
    foreach ($posts as $post) {
        $dbPath = $post['image'];
        $cleanPath = str_replace('uploads/', '', $dbPath);
        
        // ถ้าไฟล์หาย → ใช้ default
        if (!file_exists($cleanPath) && !file_exists($dbPath)) {
            $pdo->prepare("UPDATE posts SET image = ? WHERE id = ?")->execute([$defaultImage, $post['id']]);
            $fixed++;
        } else {
            // ถ้าพบไฟล์ → อัพเดท path ให้ถูก
            $correctPath = file_exists($cleanPath) ? $cleanPath : $dbPath;
            if ($correctPath != $dbPath) {
                $pdo->prepare("UPDATE posts SET image = ? WHERE id = ?")->execute([$correctPath, $post['id']]);
                $fixed++;
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'fixed' => $fixed, 
        'created' => $created, 
        'failed' => $failed,
        'total_posts' => count($posts),
        'message' => "แก้ไข {$fixed} รูป + สร้าง {$created} default images"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>