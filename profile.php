<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db.php';

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    session_destroy();
    header('Location: login.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("DB error in profile: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

// ✅ LIKE HISTORY - ใช้โครงสร้างเดียวกับ fetch_posts.php
try {
    // ตรวจสอบตาราง (copy จาก fetch_posts.php)
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
    
    if ($tableName) {
        // ✅ Query แบบเดียวกับ fetch_posts.php
        $stmt = $pdo->prepare("
            SELECT pl.*, 
                   p.content, 
                   p.created_at as post_date,
                   u.display_name as author_name,
                   u.username as author_username
            FROM $tableName pl 
            JOIN posts p ON pl.post_id = p.id 
            LEFT JOIN users u ON p.user_id = u.id
            WHERE pl.user_id = ? 
            ORDER BY pl.created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        $likeHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $likeHistory = [];
    }
    
} catch (PDOException $e) {
    error_log("Like history error: " . $e->getMessage());
    $likeHistory = [];
}

$displayName = $user['display_name'] ?? $user['username'] ?? 'Unknown';
$username = $user['username'] ?? 'Unknown';
$nameColor = $user['name_color'] ?? 'text-white';
$avatar = $user['avatar'] ?? 'default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<style>
    .animated-button {
        position: relative; display: inline-block; padding: 12px 24px; border: none;
        font-size: 16px; background-color: inherit; border-radius: 100px; font-weight: 600;
        color: #ffffff40; box-shadow: 0 0 0 2px #ffffff20; cursor: pointer; overflow: hidden;
        transition: all 0.6s cubic-bezier(0.23, 1, 0.320, 1);
    }
    .animated-button span:last-child {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 20px; height: 20px; background-color: #2196F3; border-radius: 50%;
        opacity: 0; transition: all 0.8s cubic-bezier(0.23, 1, 0.320, 1);
    }
    .animated-button span:first-child { position: relative; z-index: 1; }
    .animated-button:hover { box-shadow: 0 0 0 5px #2195f360; color: #ffffff; }
    .animated-button:active { scale: 0.95; }
    .animated-button:hover span:last-child { width: 150px; height: 150px; opacity: 1; }
    
    .like-item {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        border-left: 4px solid #3b82f6; transition: all 0.3s ease;
    }
    .like-item:hover { transform: translateX(4px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
</style>
<body class="bg-black text-white min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <!-- HEADER -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-4">
                <?php
                $avatarPath = !empty($avatar) && file_exists("uploads/{$avatar}") ? "uploads/{$avatar}" : "uploads/default.png";
                if (!file_exists($avatarPath)) $avatarPath = "assets/korox.webp";
                ?>
                <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-2 border-white" onerror="this.src='assets/korox.webp';">
                <div>
                    <h2 class="text-xl font-bold <?= htmlspecialchars($nameColor) ?>"><?= htmlspecialchars($displayName) ?></h2>
                    <p class="text-gray-400">@<?= htmlspecialchars($username) ?></p>
                </div>
            </div>
            <a href="edit_profile.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                <i class="ph ph-pencil mr-1"></i>แก้ไขโปรไฟล์
            </a>
        </div>

        <!-- PROFILE INFO -->
        <div class="grid md:grid-cols-2 gap-4 mb-8">
            <div class="bg-zinc-800 p-4 rounded">
                <p class="text-sm text-gray-400">ชื่อที่แสดง</p>
                <p class="text-lg font-medium <?= htmlspecialchars($nameColor) ?>"><?= htmlspecialchars($displayName) ?></p>
            </div>
            <div class="bg-zinc-800 p-4 rounded">
                <p class="text-sm text-gray-400">ชื่อผู้ใช้</p>
                <p class="text-lg font-medium">@<?= htmlspecialchars($username) ?></p>
            </div>
        </div>

        <!-- LIKE HISTORY -->
        <div class="bg-zinc-900 p-6 rounded-lg">
            <h3 class="text-xl font-bold mb-4 flex items-center">
                <i class="ph ph-thumbs-up mr-2 text-blue-400"></i>
                ประวัติการกดไลค์ (<?= count($likeHistory) ?> รายการ)
            </h3>
            
            <?php if (empty($likeHistory)): ?>
                <div class="text-center py-8 text-gray-400">
                    <i class="ph ph-thumbs-up text-4xl mb-2 block"></i>
                    <p>ยังไม่เคยกดไลค์โพสต์ใด ๆ</p>
                    <p class="text-xs mt-2">User ID: <?= $userId ?></p>
                    <p class="text-xs">Table: <?= $tableName ?? 'ไม่พบ' ?></p>
                </div>
            <?php else: ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($likeHistory as $like): ?>
                        <div class="like-item p-4 rounded-lg">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <i class="ph ph-thumbs-up-fill text-blue-400 text-xl mt-1"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-200">
                                        <?= htmlspecialchars($like['content'] ?? 'โพสต์นี้') ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <time><?= date('d/m/Y H:i', strtotime($like['post_date'])) ?></time>
                                        <?php if (isset($like['author_name'])): ?>
                                            <span class="ml-2">โดย <?= htmlspecialchars($like['author_name']) ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="text-xs bg-blue-500/20 text-blue-400 px-2 py-1 rounded-full">ไลค์</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- BACK BUTTON -->
        <div class="mt-8 text-center">
            <a href="index.php" class="animated-button">
                <span><i class="ph ph-arrow-left mr-2"></i>กลับหน้าหลัก</span>
                <span></span>
            </a>
        </div>
    </div>
</body>
</html>