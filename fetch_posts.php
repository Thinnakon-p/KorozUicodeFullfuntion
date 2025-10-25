<?php
// fetch_posts.php - สำหรับ index.php (ไม่ต้อง admin_liked)
session_start();
header('Content-Type: application/json');

require 'db.php';

$userId = $_SESSION['user_id'] ?? null;

try {
    // ตรวจสอบตาราง
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
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   u.display_name as poster_name, 
                   u.username,
                   u.avatar as poster_avatar, 
                   u.name_color as poster_color,
                   p.created_at,  // ✅ เพิ่มบรรทัดนี้
                   0 as likes_count,
                   0 as user_liked
            FROM posts p 
            LEFT JOIN users u ON p.user_id = u.id 
            ORDER BY p.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   u.display_name as poster_name, 
                   u.username,
                   u.avatar as poster_avatar, 
                   u.name_color as poster_color,
                   (SELECT COUNT(*) FROM $tableName l WHERE l.post_id = p.id) as likes_count,
                   EXISTS(SELECT 1 FROM $tableName l WHERE l.post_id = p.id AND l.user_id = ?) as user_liked
            FROM posts p 
            LEFT JOIN users u ON p.user_id = u.id 
            ORDER BY p.created_at DESC
        ");
    }
    
    $stmt->execute([$userId]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($posts as &$post) {
        $post['poster_avatar'] = 'uploads/' . ($post['poster_avatar'] ?? 'default.png');
        
        if ($post['image']) {
            $post['post_image'] = 'uploads/' . $post['image'];
        } elseif ($post['file_path']) {
            $post['post_image'] = $post['file_path'];
        } else {
            $post['post_image'] = 'assets/default-post-image.png';
        }
        
        $post['poster_name'] = $post['poster_name'] ?? $post['username'] ?? 'Unknown';
    }

    echo json_encode($posts);
    
} catch (PDOException $e) {
    error_log("Fetch posts ERROR: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>