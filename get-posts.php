<?php
header('Content-Type: application/json');
require 'db.php';

try {
    // Query ดึงโพสต์พร้อมข้อมูลผู้ใช้และสถานะ likes
    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            COALESCE(u.display_name, u.username) AS poster_name, 
            u.name_color AS poster_color, 
            u.avatar AS poster_avatar,
            (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes_count,
            (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) AS user_liked
        FROM posts p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.is_locked = 0 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'] ?? 0]); // ใช้ user_id จาก session ถ้ามี
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ตรวจสอบและปรับปรุง path ของรูปภาพ
    foreach ($posts as &$post) {
        // ตรวจสอบและกำหนด path สำหรับ post image
        if ($post['image'] && file_exists('uploads/posts/' . $post['image'])) {
            $post['image'] = 'uploads/posts/' . $post['image'];
        } else {
            $post['image'] = null; // หรือใช้ 'assets/placeholder.jpg' ถ้ามี
        }
        // ตรวจสอบและกำหนด path สำหรับ poster avatar
        if ($post['poster_avatar'] && file_exists('uploads/avatars/' . $post['poster_avatar'])) {
            $post['poster_avatar'] = 'uploads/avatars/' . $post['poster_avatar'];
        } else {
            $post['poster_avatar'] = 'assets/korox.webp'; // Fallback image
        }
    }
    unset($post); // ป้องกัน reference leak

    echo json_encode($posts);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงโพสต์: ' . $e->getMessage()]);
}
?>