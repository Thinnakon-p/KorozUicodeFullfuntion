<?php
require 'db.php';
header('Content-Type: application/json');

$post_id = (int)($_GET['post_id'] ?? 0);
if (!$post_id) {
    echo json_encode(['likes' => [], 'stats' => ['total' => 0, 'today' => 0, 'top_user_count' => 0, 'avg_per_day' => 0]]);
    exit;
}

try {
    // Get Likes
    $stmt = $pdo->prepare("
        SELECT l.id, l.created_at, u.id as user_id, u.username, u.display_name, 
               u.name_color, u.avatar as user_avatar, u.frame
        FROM likes l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.post_id = ? 
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$post_id]);
    $likes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats
    $stats = [
        'total' => count($likes),
        'today' => 0,
        'top_user_count' => 0,
        'avg_per_day' => 0
    ];
    
    if ($stats['total'] > 0) {
        // Today likes
        $today = date('Y-m-d');
        $stmt_today = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ? AND DATE(created_at) = ?");
        $stmt_today->execute([$post_id, $today]);
        $stats['today'] = $stmt_today->fetchColumn();
        
        // Top user
        $stmt_top = $pdo->prepare("
            SELECT u.username, COUNT(l.id) as count 
            FROM likes l JOIN users u ON l.user_id = u.id 
            WHERE l.post_id = ? 
            GROUP BY u.id ORDER BY count DESC LIMIT 1
        ");
        $stmt_top->execute([$post_id]);
        $top = $stmt_top->fetch();
        $stats['top_user_count'] = $top ? $top['count'] : 0;
        
        // Avg per day
        $stmt_dates = $pdo->prepare("SELECT COUNT(DISTINCT DATE(created_at)) as days FROM likes WHERE post_id = ?");
        $stmt_dates->execute([$post_id]);
        $days = $stmt_dates->fetchColumn();
        $stats['avg_per_day'] = $days > 0 ? round($stats['total'] / $days, 1) : 0;
    }
    
    echo json_encode(['likes' => $likes, 'stats' => $stats]);
    
} catch (PDOException $e) {
    echo json_encode(['likes' => [], 'stats' => ['total' => 0, 'today' => 0, 'top_user_count' => 0, 'avg_per_day' => 0]]);
}
?>