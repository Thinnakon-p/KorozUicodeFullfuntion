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

// ✅ ตรวจสอบทั้ง role และ admin_access
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$isAdmin = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['admin_access']) && $_SESSION['admin_access'] === true) {
    $isAdmin = true;
}

if (!$isAdmin) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
        exit;
    }
    http_response_code(403);
    echo "<h1>403 - ไม่มีสิทธิ์</h1><p>กรุณากรอกคีย์แอดมินที่หน้าแรก</p>";
    exit;
}



require 'db.php';

// Schema check
try {
    // Users table
    $col_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_locked'")->fetch();
    if (!$col_check) {
        $pdo->exec("ALTER TABLE users ADD is_locked TINYINT DEFAULT 0");
        $pdo->exec("UPDATE users SET is_locked = 0 WHERE is_locked IS NULL");
    }
    $col_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'locked_functions'")->fetch();
    if (!$col_check) $pdo->exec("ALTER TABLE users ADD locked_functions VARCHAR(255) DEFAULT ''");
    $col_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'locked_colors'")->fetch();
    if (!$col_check) $pdo->exec("ALTER TABLE users ADD locked_colors VARCHAR(255) DEFAULT ''");
    
    // Permissions table
    $table_check = $pdo->query("SHOW TABLES LIKE 'permissions'")->fetch();
    if (!$table_check) {
        $pdo->exec("CREATE TABLE permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_name VARCHAR(50) NOT NULL,
            can_post TINYINT DEFAULT 1,
            can_like TINYINT DEFAULT 1,
            can_comment TINYINT DEFAULT 1,
            can_upload_files TINYINT DEFAULT 1,
            max_file_size INT DEFAULT 10,
            allowed_file_types TEXT DEFAULT 'jpg,jpeg,png,gif,zip,pdf,mp4,mp3',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_role (role_name)
        )");
        
        // Default permissions
        $default_perms = [
            ['admin', 1,1,1,1,50,'jpg,jpeg,png,gif,zip,pdf,mp4,mp3,doc,docx'],
            ['user', 1,1,1,1,10,'jpg,jpeg,png,gif,zip,pdf'],
            ['guest', 0,1,0,0,5,'jpg,jpeg,png']
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (role_name, can_post, can_like, can_comment, can_upload_files, max_file_size, allowed_file_types) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($default_perms as $perm) {
            $stmt->execute($perm);
        }
    }
    
    // Likes table
    $table_check = $pdo->query("SHOW TABLES LIKE 'likes'")->fetch();
    if (!$table_check) {
        $pdo->exec("CREATE TABLE likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_like (post_id, user_id)
        )");
    }
    
    // Posts table
    $col_check = $pdo->query("SHOW COLUMNS FROM posts LIKE 'file_path'")->fetch();
    if (!$col_check) {
        $pdo->exec("ALTER TABLE posts ADD file_path VARCHAR(255) DEFAULT ''");
    }
    $col_check = $pdo->query("SHOW COLUMNS FROM posts LIKE 'file_name'")->fetch();
    if (!$col_check) {
        $pdo->exec("ALTER TABLE posts ADD file_name VARCHAR(255) DEFAULT ''");
    }
    $col_check = $pdo->query("SHOW COLUMNS FROM posts LIKE 'is_live'")->fetch();
    if (!$col_check) {
        $pdo->exec("ALTER TABLE posts ADD is_live TINYINT DEFAULT 0");
    }
    
    // Colors table
    $table_check = $pdo->query("SHOW TABLES LIKE 'colors'")->fetch();
    if (!$table_check) {
        $pdo->exec("CREATE TABLE colors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            color_name VARCHAR(50) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $default_colors = [
            '#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF',
            'rgb(255,0,0)', 'rgb(0,255,0)', 'rgb(0,0,255)', 'rgb(255,255,0)',
            'text-red-500', 'text-green-500', 'text-blue-500', 'text-yellow-500'
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO colors (color_name) VALUES (?)");
        foreach ($default_colors as $color) {
            $stmt->execute([$color]);
        }
    }
    
    // Admin keys table
    $table_check = $pdo->query("SHOW TABLES LIKE 'admin_keys'")->fetch();
    if (!$table_check) {
        $pdo->exec("CREATE TABLE admin_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            key_hash VARCHAR(255) NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT DEFAULT 1
        )");
    }
} catch (PDOException $e) {
    error_log("Schema error: " . $e->getMessage());
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Dynamic data
$stmt_colors = $pdo->query("SELECT color_name FROM colors ORDER BY id");
$colors = $stmt_colors->fetchAll(PDO::FETCH_COLUMN);

$stmt_roles = $pdo->query("SELECT name FROM roles");
$roles_list = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
$roles = array_flip($roles_list);

$freeFrames = array_map('basename', glob('frames/*.png'));

$stmt_perms = $pdo->query("SELECT * FROM permissions");
$permissions = $stmt_perms->fetchAll(PDO::FETCH_ASSOC);

$displayName = $user['display_name'] ?? $user['username'];
$nameColor = $user['name_color'] ?? '#FFFFFF';
$avatar = $user['avatar'] ?? 'default.png';
$frame = $user['frame'] ?? '';

$search = $_GET['search'] ?? '';
$select_columns = "id, username, email, phone, is_locked, role, display_name, name_color, frame, locked_functions, locked_colors";
try {
    $stmt_users = $pdo->prepare("SELECT $select_columns FROM users WHERE username LIKE ? OR email LIKE ?");
    $stmt_users->execute(["%$search%", "%$search%"]);
    $users = $stmt_users->fetchAll();
} catch (PDOException $e) {
    $stmt_users = $pdo->prepare("SELECT id, username, email, phone, role, display_name, name_color, frame FROM users WHERE username LIKE ? OR email LIKE ?");
    $stmt_users->execute(["%$search%", "%$search%"]);
    $users = $stmt_users->fetchAll();
    foreach ($users as &$u) {
        $u['is_locked'] = 0;
        $u['locked_functions'] = '';
        $u['locked_colors'] = '';
    }
}

$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_posts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();

$lockableFunctions = ['display_name', 'name_color', 'frame', 'avatar', 'password'];

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ✅ DELETE POST - ใหม่
    if ($action === 'delete_post' && isset($_POST['post_id'])) {
        $post_id = (int)$_POST['post_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT title, post_image, file_path FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$post) {
                echo json_encode(['success' => false, 'message' => 'ไม่พบโพสต์']);
                exit;
            }
            
            // Delete files
            if (!empty($post['post_image']) && file_exists('uploads/' . $post['post_image'])) {
                unlink('uploads/' . $post['post_image']);
            }
            if (!empty($post['file_path']) && file_exists($post['file_path'])) {
                unlink($post['file_path']);
            }
            
            // Delete post
            $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
            $deleted = $stmt->execute([$post_id]);
            
            echo json_encode([
                'success' => $deleted, 
                'message' => 'ลบโพสต์เรียบร้อย',
                'title' => $post['title']
            ]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        }
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Update User
    if ($action === 'update_user') {
        $user_id = $_POST['user_id'];
        $display_name = $_POST['display_name'];
        $name_color = $_POST['name_color'];
        $frame = $_POST['frame'];
        $is_locked = isset($_POST['is_locked']) ? 1 : 0;
        $role = $_POST['role'];
        $locked_functions = isset($_POST['locked_functions']) ? implode(',', (array)$_POST['locked_functions']) : '';
        $locked_colors = isset($_POST['locked_colors']) ? implode(',', (array)$_POST['locked_colors']) : '';

        $stmt_update = $pdo->prepare("UPDATE users SET display_name = ?, name_color = ?, frame = ?, is_locked = ?, role = ?, locked_functions = ?, locked_colors = ? WHERE id = ?");
        $updated = $stmt_update->execute([$display_name, $name_color, $frame, $is_locked, $role, $locked_functions, $locked_colors, $user_id]);
        echo json_encode(['success' => $updated]);
        exit;
    }
    
    // Add Color
    elseif ($action === 'add_color') {
        $color_name = trim($_POST['color_name']);
        if (empty($color_name)) {
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อสี']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO colors (color_name) VALUES (?)");
            $stmt->execute([$color_name]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'สีซ้ำแล้ว']);
        }
        exit;
    }
    
    // Delete Color
    elseif ($action === 'delete_color') {
        $color_name = $_POST['color_name'];
        $stmt = $pdo->prepare("DELETE FROM colors WHERE color_name = ?");
        $stmt->execute([$color_name]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Create Key
    elseif ($action === 'create_key') {
        $key_length = 32;
        $new_key = bin2hex(random_bytes($key_length));
        $hashed_key = password_hash($new_key, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO admin_keys (key_hash, user_id, created_at, is_active) VALUES (?, ?, NOW(), 1)");
        $stmt->execute([$hashed_key, $userId]);
        $key_id = $pdo->lastInsertId();
        
        echo json_encode(['success' => true, 'key' => $new_key, 'key_id' => $key_id]);
        exit;
    }
    
    // Delete Key
    elseif ($action === 'delete_key' && isset($_POST['key_id'])) {
        $key_id = $_POST['key_id'];
        $stmt = $pdo->prepare("DELETE FROM admin_keys WHERE id = ? AND user_id = ?");
        $deleted = $stmt->execute([$key_id, $userId]);
        echo json_encode(['success' => $deleted > 0]);
        exit;
    }
    
    // Update Permissions
    elseif ($action === 'update_permissions') {
        $role_name = $_POST['role_name'];
        $can_post = isset($_POST['can_post']) ? 1 : 0;
        $can_like = isset($_POST['can_like']) ? 1 : 0;
        $can_comment = isset($_POST['can_comment']) ? 1 : 0;
        $can_upload_files = isset($_POST['can_upload_files']) ? 1 : 0;
        $max_file_size = (int)$_POST['max_file_size'];
        $allowed_file_types = implode(',', (array)$_POST['allowed_file_types']);
        
        $stmt = $pdo->prepare("INSERT INTO permissions (role_name, can_post, can_like, can_comment, can_upload_files, max_file_size, allowed_file_types) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE can_post=VALUES(can_post), can_like=VALUES(can_like), can_comment=VALUES(can_comment), can_upload_files=VALUES(can_upload_files), max_file_size=VALUES(max_file_size), allowed_file_types=VALUES(allowed_file_types)");
        $success = $stmt->execute([$role_name, $can_post, $can_like, $can_comment, $can_upload_files, $max_file_size, $allowed_file_types]);
        echo json_encode(['success' => $success]);
        exit;
    }
    
    // Delete Like
    elseif ($action === 'delete_like' && isset($_POST['like_id'])) {
        $like_id = $_POST['like_id'];
        $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
        $deleted = $stmt->execute([$like_id]);
        echo json_encode(['success' => $deleted > 0]);
        exit;
    }
    
    // Change Password
    elseif ($action === 'change_password') {
        $user_id = $_POST['user_id'];
        $new_password = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        if ($new_password !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านไม่ตรงกัน']);
            exit;
        }
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updated = $stmt->execute([$hashed, $user_id]);
        echo json_encode(['success' => $updated]);
        exit;
    }
    
    // Lock/Unlock All
    elseif ($action === 'lock_all' || $action === 'unlock_all') {
        $lock = $action === 'lock_all';
        $functions = $lockableFunctions;
        $locked_functions = $lock ? implode(',', $functions) : '';
        $locked_colors = $lock ? implode(',', $colors) : '';
        $is_locked = $lock ? 1 : 0;
        $default_values = ['name_color' => '#FFFFFF', 'frame' => '', 'avatar' => 'default.png'];

        $stmt_update = $pdo->prepare("UPDATE users SET name_color = ?, frame = ?, display_name = username, avatar = ?, locked_functions = ?, locked_colors = ?, is_locked = ? WHERE id != ?");
        $stmt_update->execute([
            $default_values['name_color'], $default_values['frame'], $default_values['avatar'],
            $locked_functions, $locked_colors, $is_locked, $userId
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap");
        body { font-family: 'Kanit', sans-serif; background: linear-gradient(135deg, #1a1a1a, #2d2d2d); color: #f0f0f0; }
        .navbar { background: rgba(0,0,0,0.95); backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .card { background: rgba(30,30,30,0.9); border-radius: 15px; transition: all 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(255,69,0,0.3); }
        .frame { position: relative; display: inline-block; width: 48px; height: 48px; }
        .frame img.avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; transition: transform 0.5s ease; border: 3px solid #fff; }
        .frame:hover img.avatar { transform: scale(1.1); }
        .frame::before { content: ''; position: absolute; top: -8px; left: -8px; right: -8px; bottom: -8px; background: var(--frame-url) no-repeat center; background-size: 100% 100%; z-index: -1; opacity: 0.9; transition: opacity 0.3s ease; border-radius: 50%; }
        .frame:hover::before { opacity: 1; }
        .color-preview { width: 20px; height: 20px; border-radius: 4px; display: inline-block; margin-right: 8px; border: 1px solid #444; }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.05);} }
        .fade-in { animation: fadeIn 1s ease-in-out; }
        @keyframes fadeIn { 0%{opacity:0;transform:translateY(20px);} 100%{opacity:1;transform:translateY(0);} }
        .slide-up { animation: slideUp 0.8s ease-out; }
        @keyframes slideUp { 0%{opacity:0;transform:translateY(50px);} 100%{opacity:1;transform:translateY(0);} }
        .glow { animation: glow 2s ease-in-out infinite alternate; }
        @keyframes glow { 0%{box-shadow:0 0 5px #ff4500;} 100%{box-shadow:0 0 20px #ff4500,0 0 30px #ff4500;} }
        .btn { transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255,69,0,0.4); }
        .btn:active { transform: scale(0.95); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #444; }
        th { background: #222; }
        tr:hover { background: #333; }
        #postsList, #keysList, #colorsList, #permissionsList { max-height: 400px; overflow-y: auto; }
        #postsList::-webkit-scrollbar, #keysList::-webkit-scrollbar, #colorsList::-webkit-scrollbar, #permissionsList::-webkit-scrollbar { width: 6px; }
        #postsList::-webkit-scrollbar-thumb, #keysList::-webkit-scrollbar-thumb, #colorsList::-webkit-scrollbar-thumb, #permissionsList::-webkit-scrollbar-thumb { background-color: #ef4444; border-radius: 3px; }
        .live-badge { background: #ef4444; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
        .link-badge { background: #3b82f6; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
        .file-icon { font-size: 16px; margin-right: 4px; }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <nav class="navbar p-4 fixed w-full z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center text-white">
            <i class="fas fa-crown text-2xl mr-2 text-red-500"></i>
            <a href="#" class="text-2xl font-bold text-red-500 pulse">Admin Dashboard</a>
            <div class="space-x-6">
                <a href="index.php" class="hover:text-red-500 transition fade-in">View Posts</a>
                <a href="profile.php" class="hover:text-red-500 transition fade-in">Profile</a>
                <a href="logout.php" class="hover:text-red-500 transition fade-in">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto pt-24 pb-10 px-6">
        <h1 class="text-4xl font-extrabold text-center mb-8 text-orange-500 glow slide-up">ยินดีต้อนรับสู่แดชบอร์ดแอดมิน</h1>
        <h2 class="text-xl font-bold mb-4">
            Welcome, <span style="color: <?= htmlspecialchars($nameColor) ?>"><?= htmlspecialchars($displayName) ?> (Admin)</span>
        </h2>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-6 rounded-lg text-center card">
                <i class="fas fa-users text-3xl mb-2"></i>
                <h3 class="text-2xl font-bold">ผู้ใช้ทั้งหมด</h3>
                <p class="text-4xl font-extrabold"><?= $total_users ?></p>
            </div>
            <div class="bg-gradient-to-r from-green-600 to-green-800 p-6 rounded-lg text-center card">
                <i class="fas fa-file-alt text-3xl mb-2"></i>
                <h3 class="text-2xl font-bold">โพสต์ทั้งหมด</h3>
                <p class="text-4xl font-extrabold"><?= $total_posts ?></p>
            </div>
            <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-6 rounded-lg text-center card">
                <i class="fas fa-thumbs-up text-3xl mb-2"></i>
                <h3 class="text-2xl font-bold">ไลค์ทั้งหมด</h3>
                <p class="text-4xl font-extrabold" id="totalLikes">-</p>
            </div>
        </div>

        <!-- Permissions Management -->
        <section class="mb-12 fade-in">
            <h2 class="text-3xl font-bold mb-6 text-red-500 flex items-center">
                <i class="fas fa-shield-alt mr-2"></i>จัดการสิทธิ์ (Permissions)
            </h2>
            <div id="permissionsList" class="space-y-4">
                <?php foreach ($permissions as $perm): ?>
                <div class="bg-gray-800 p-6 rounded-lg">
                    <h3 class="text-xl font-bold mb-4"><?= htmlspecialchars($perm['role_name']) ?></h3>
                    <form id="permForm_<?= $perm['role_name'] ?>" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update_permissions">
                        <input type="hidden" name="role_name" value="<?= $perm['role_name'] ?>">
                        
                        <div>
                            <label class="flex items-center mb-2"><input type="checkbox" name="can_post" <?= $perm['can_post'] ? 'checked' : '' ?> class="mr-2"> สร้างโพสต์</label>
                            <label class="flex items-center mb-2"><input type="checkbox" name="can_like" <?= $perm['can_like'] ? 'checked' : '' ?> class="mr-2"> กดไลค์</label>
                            <label class="flex items-center"><input type="checkbox" name="can_comment" <?= $perm['can_comment'] ? 'checked' : '' ?> class="mr-2"> คอมเมนต์</label>
                        </div>
                        
                        <div>
                            <label class="flex items-center mb-2"><input type="checkbox" name="can_upload_files" <?= $perm['can_upload_files'] ? 'checked' : '' ?> class="mr-2"> อัปโหลดไฟล์</label>
                            <label class="mb-2">ขนาดไฟล์สูงสุด: <input type="number" name="max_file_size" value="<?= $perm['max_file_size'] ?>" class="w-16 p-1 bg-gray-700 rounded ml-2"> MB</label>
                        </div>
                        
                        <div>
                            <label>ประเภทไฟล์:</label>
                            <div class="flex flex-wrap gap-1 mt-2">
                                <?php 
                                $allowed_types = explode(',', $perm['allowed_file_types']);
                                $all_types = ['jpg','jpeg','png','gif','zip','pdf','mp4','mp3','doc','docx'];
                                foreach ($all_types as $type): 
                                ?>
                                <label class="flex items-center"><input type="checkbox" name="allowed_file_types[]" value="<?= $type ?>" <?= in_array($type, $allowed_types) ? 'checked' : '' ?> class="mr-1"><?= $type ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="md:col-span-3 btn bg-green-600 text-white p-2 rounded">บันทึกสิทธิ์</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- User Management -->
        <section class="mb-12 fade-in">
            <h2 class="text-3xl font-bold mb-6 text-red-500 flex items-center">
                <i class="fas fa-user-shield mr-2"></i>จัดการผู้ใช้
            </h2>
            <div class="mb-4 flex flex-col sm:flex-row gap-4">
                <button onclick="lockAll()" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded flex items-center">
                    <i class="fas fa-lock mr-2"></i>ล็อกทั้งหมด
                </button>
                <button onclick="unlockAll()" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded flex items-center">
                    <i class="fas fa-unlock mr-2"></i>ปลดล็อกทั้งหมด
                </button>
                <input type="text" id="search" placeholder="ค้นหา username หรือ email..." class="w-full p-2 bg-gray-700 rounded" value="<?= htmlspecialchars($search) ?>">
                <button onclick="searchUsers()" class="bg-blue-600 hover:bg-blue-700 p-2 rounded">ค้นหา</button>
            </div>
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th>Avatar</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $userNameColor = $u['name_color'] ?? '#FFFFFF';
                            $isHexOrRgb = strpos($userNameColor, '#') === 0 || strpos($userNameColor, 'rgb') === 0;
                            $nameStyleOrClass = $isHexOrRgb ? 'style="color: ' . htmlspecialchars($userNameColor) . '"' : 'class="' . htmlspecialchars($userNameColor) . '"';
                            $frameUrl = !empty($u['frame']) ? 'url(\'frames/' . htmlspecialchars($u['frame']) . '\')' : 'none';
                        ?>
                        <tr>
                            <td><div class="frame" style="--frame-url: <?= $frameUrl ?>;"><img src="uploads/<?= htmlspecialchars($u['avatar'] ?? 'default.png') ?>" class="avatar"></div></td>
                            <td><h3 <?= $nameStyleOrClass ?> class="text-lg font-semibold"><?= htmlspecialchars($u['display_name'] ?? $u['username']) ?></h3></td>
                            <td><?= htmlspecialchars($u['email'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($u['phone'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($u['role'] ?? 'user') ?></td>
                            <td><span class="<?= $u['is_locked'] ? 'text-red-500' : 'text-green-500' ?>"><?= $u['is_locked'] ? 'Locked' : 'Active' ?></span></td>
                            <td>
                                <button onclick="openChangePasswordModal(<?= $u['id'] ?>)" class="btn bg-yellow-600 text-white px-3 py-1 rounded text-sm mr-2">รหัส</button>
                                <button onclick="editUser(<?= $u['id'] ?>)" class="btn bg-blue-600 text-white px-3 py-1 rounded text-sm">แก้ไข</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Color Management -->
        <section class="mb-12 fade-in">
            <h2 class="text-3xl font-bold mb-6 text-red-500 flex items-center">
                <i class="fas fa-palette mr-2"></i>จัดการสีชื่อ
            </h2>
            <div class="bg-gray-800 p-6 rounded-lg mb-4">
                <form id="addColorForm" class="flex flex-col sm:flex-row gap-4 mb-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add_color">
                    <input type="text" name="color_name" id="colorInput" placeholder="ตัวอย่าง: #FF0000, rgb(255,0,0), text-red-500" class="flex-1 p-2 bg-gray-700 rounded" required>
                    <button type="submit" class="btn bg-green-600 text-white p-2 rounded">เพิ่มสี</button>
                </form>
            </div>
            <div id="colorsList" class="space-y-2 max-h-64 overflow-y-auto">
                <?php foreach ($colors as $color): ?>
                    <?php 
                    $colorDisplay = (strpos($color, '#') === 0 || strpos($color, 'rgb') === 0) 
                        ? 'style="color: ' . htmlspecialchars($color) . '"' 
                        : 'class="' . htmlspecialchars($color) . '"';
                    ?>
                    <div class="flex justify-between items-center bg-gray-700 p-3 rounded">
                        <div class="flex items-center">
                            <div class="color-preview" style="background-color: <?= htmlspecialchars($color) ?>;"></div>
                            <span <?= $colorDisplay ?> class="font-mono"><?= htmlspecialchars($color) ?></span>
                        </div>
                        <button onclick="deleteColor('<?= htmlspecialchars($color) ?>')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">ลบ</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Add Post -->
        <section class="mb-12 fade-in">
            <h2 class="text-3xl font-bold mb-6 text-red-500 flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>เพิ่มโพสต์ใหม่
            </h2>
            <form id="postForm" class="space-y-4 bg-gray-800 p-6 rounded-lg" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="text" name="title" placeholder="ชื่อโพสต์" class="w-full p-2 rounded bg-gray-700 text-white" required>
                <textarea name="description" placeholder="รายละเอียด" class="w-full p-2 rounded bg-gray-700 text-white" required></textarea>
                
                <!-- ลิงก์หรือไลฟ์ -->
                <div class="flex space-x-2">
                    <input type="url" name="link" placeholder="ลิงก์[](https://...)" class="flex-1 p-2 rounded bg-gray-700 text-white">
                    <select name="is_live" class="p-2 rounded bg-gray-700 text-white">
                        <option value="0">ลิงก์</option>
                        <option value="1">ไลฟ์</option>
                    </select>
                </div>
                
                <!-- รูปภาพ + ไฟล์ -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="file" name="image" accept="image/*" class="p-2 rounded bg-gray-700 text-white">
                    <input type="file" name="file" accept=".zip,.pdf,.mp4,.mp3,.doc,.docx" class="p-2 rounded bg-gray-700 text-white">
                </div>
                
                <button type="submit" class="btn w-full bg-red-600 text-white p-3 rounded font-bold">เพิ่มโพสต์</button>
            </form>
        </section>

        <!-- Posts List -->
        <section class="mb-12 fade-in">
            <h2 class="text-3xl font-bold mb-6 text-red-500 flex items-center">
                <i class="fas fa-list mr-2"></i>โพสต์ทั้งหมด
            </h2>
            <div id="postsList"></div>
        </section>

        <!-- Key Management -->
        <section class="py-10 fade-in">
            <h2 class="text-3xl font-bold mb-6 text-red-500 flex items-center">
                <i class="fas fa-key mr-2"></i>จัดการคีย์แอดมิน
            </h2>
            <button onclick="createKey()" class="bg-green-600 hover:bg-green-700 text-white p-3 rounded font-bold mb-4">
                <i class="fas fa-plus mr-2"></i>สร้างคีย์ใหม่
            </button>
            <div id="keysList" class="space-y-4"></div>
        </section>

        <!-- Likes Modal -->
        <div id="likesModal" class="fixed inset-0 hidden flex items-center justify-center z-50">
            <div class="absolute inset-0 bg-black bg-opacity-75" onclick="closeLikesModal()"></div>
            <div class="relative bg-gray-800 rounded-lg max-w-4xl w-full mx-4 p-6 max-h-screen overflow-y-auto">
                <button onclick="closeLikesModal()" class="absolute top-3 right-3 text-red-600 text-2xl">&times;</button>
                <h3 class="text-2xl font-bold mb-6 text-red-600 flex items-center">
                    <i class="fas fa-thumbs-up mr-2"></i>ผู้ใช้ที่กดไลค์
                </h3>
                
                <!-- Post Info -->
                <div id="postInfo" class="bg-gray-700 p-4 rounded mb-6 hidden">
                    <h4 id="postTitle" class="text-xl font-bold mb-2"></h4>
                    <p id="postLikesCount" class="text-blue-400"></p>
                </div>
                
                <!-- Likes Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-600">
                                <th class="p-3">Avatar</th>
                                <th class="p-3">ชื่อผู้ใช้</th>
                                <th class="p-3">โพสต์ที่ไลค์</th>
                                <th class="p-3">วันที่กดไลค์</th>
                                <th class="p-3">Action</th>
                            </tr>
                        </thead>
                        <tbody id="likesTableBody">
                            <tr><td colspan="5" class="p-4 text-center text-gray-400">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-4 border-t border-gray-600">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-blue-400" id="totalLikesInModal">-</p>
                        <p class="text-sm text-gray-400">ทั้งหมด</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-400" id="todayLikes">-</p>
                        <p class="text-sm text-gray-400">วันนี้</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-purple-400" id="topUser">-</p>
                        <p class="text-sm text-gray-400">ไลค์มากสุด</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-yellow-400" id="avgLikes">-</p>
                        <p class="text-sm text-gray-400">เฉลี่ย/วัน</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password Modal -->
        <div id="changePasswordModal" class="fixed inset-0 hidden flex items-center justify-center z-50">
            <div class="absolute inset-0 bg-black bg-opacity-75" onclick="closeChangePasswordModal()"></div>
            <div class="relative bg-gray-800 rounded-lg max-w-md w-full mx-4 p-6">
                <button onclick="closeChangePasswordModal()" class="absolute top-3 right-3 text-red-600 text-2xl">&times;</button>
                <h3 class="text-2xl font-bold mb-6 text-red-600">เปลี่ยนรหัสผ่าน</h3>
                <form id="changePasswordForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" id="changePasswordUserId" name="user_id">
                    <input type="password" name="new_password" placeholder="รหัสผ่านใหม่" class="w-full p-2 rounded bg-gray-700 text-white" required>
                    <input type="password" name="confirm_password" placeholder="ยืนยันรหัสผ่าน" class="w-full p-2 rounded bg-gray-700 text-white" required>
                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 bg-red-600 text-white p-2 rounded">บันทึก</button>
                        <button type="button" onclick="closeChangePasswordModal()" class="flex-1 bg-gray-600 text-white p-2 rounded">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        let totalLikes = 0;

        // Toast
        function showToast(title, message, type = 'info') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500', warning: 'bg-yellow-500' };
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white ${colors[type]} transform translate-x-full transition-transform duration-300`;
            toast.innerHTML = `<div class="flex items-center"><i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i><div><strong>${title}</strong><br><span class="text-sm">${message}</span></div></div>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.remove('translate-x-full'), 100);
            setTimeout(() => { toast.classList.add('translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
        }

        // Permissions Forms
        document.querySelectorAll('[id^="permForm_"]').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                try {
                    const r = await fetch("dashboard.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, body: formData });
                    const data = await r.json();
                    if (data.success) showToast('สำเร็จ', 'อัปเดตสิทธิ์เรียบร้อย', 'success');
                    else showToast('ข้อผิดพลาด', data.message, 'error');
                } catch (e) { showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาด', 'error'); }
            });
        });

        // Key Management
        function showKeyModal(key, keyId) {
            const modal = document.createElement('div');
            modal.id = 'keyModal';
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75';
            modal.innerHTML = `
                <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
                    <h3 class="text-2xl font-bold mb-4 text-green-500 flex items-center"><i class="fas fa-key mr-2"></i>คีย์ใหม่!</h3>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">คีย์ของคุณ:</label>
                        <div class="flex">
                            <input id="keyInput" type="text" value="${key}" readonly class="flex-1 p-3 bg-gray-700 rounded-l border border-gray-600 text-sm font-mono">
                            <button onclick="copyKey()" class="bg-blue-600 hover:bg-blue-700 px-4 py-3 rounded-r text-white"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <button onclick="closeKeyModal()" class="w-full bg-gray-600 hover:bg-gray-700 p-2 rounded text-white">ปิด</button>
                </div>`;
            document.body.appendChild(modal);
            document.getElementById('keyInput').select();
        }

        function copyKey() {
            const input = document.getElementById('keyInput');
            input.select();
            document.execCommand('copy');
            showToast('คัดลอกแล้ว!', 'กด Ctrl+V เพื่อวาง', 'success');
        }

        function closeKeyModal() { document.getElementById('keyModal').remove(); }

        async function deleteKey(keyId) {
            if (!confirm('ลบคีย์นี้?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_key');
            formData.append('key_id', keyId);
            formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
            try {
                const r = await fetch("dashboard.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, body: formData });
                const data = await r.json();
                if (data.success) { showToast('สำเร็จ', 'ลบคีย์เรียบร้อย', 'success'); loadKeys(); }
            } catch (e) { showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาด', 'error'); }
        }

        function createKey() {
            const formData = new FormData();
            formData.append('action', 'create_key');
            formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
            fetch("dashboard.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, body: formData })
                .then(r => r.json()).then(data => {
                    if (data.success) { showKeyModal(data.key, data.key_id); loadKeys(); }
                }).catch(() => showToast("ข้อผิดพลาด", "เกิดข้อผิดพลาด", "error"));
        }

        // Load Total Likes
        async function loadTotalLikes() {
            try {
                const r = await fetch("fetch_total_likes.php");
                const data = await r.json();
                totalLikes = data.total;
                document.getElementById("totalLikes").textContent = totalLikes;
            } catch (e) { document.getElementById("totalLikes").textContent = "0"; }
        }

        // Load Posts
        async function loadPosts() {
            try {
                const r = await fetch("fetch_posts.php");
                const posts = await r.json();
                document.getElementById("postsList").innerHTML = posts.map(p => `
                    <div class="bg-gray-800 p-4 rounded mb-4">
                        <h4 class="text-xl font-bold mb-2">${p.title}</h4>
                        <p class="mb-2">${p.description}</p>
                        <div class="flex items-center mb-3">
                            <span class="mr-2">${p.is_live ? '<i class="fas fa-video text-red-500"></i>' : '<i class="fas fa-link text-blue-500"></i>'}</span>
                            <a href="${p.link}" target="_blank" class="text-blue-400 underline">${p.link}</a>
                            <span class="ml-2 ${p.is_live ? 'live-badge' : 'link-badge'}">${p.is_live ? 'ไลฟ์' : 'ลิงก์'}</span>
                        </div>
                        ${p.post_image ? `<img src="${p.post_image}" class="w-full h-48 object-cover rounded mb-3">` : ''}
                        ${p.file_path ? `
                            <div class="flex items-center p-2 bg-gray-700 rounded mb-3">
                                <i class="fas ${p.file_path.includes('.zip') ? 'fa-file-archive' : p.file_path.includes('.pdf') ? 'fa-file-pdf' : p.file_path.includes('.mp4') ? 'fa-file-video' : 'fa-file'} file-icon text-blue-400"></i>
                                <a href="${p.file_path}" target="_blank" class="text-blue-400 underline">${p.file_name}</a>
                            </div>
                        ` : ''}
                        <div class="flex justify-between items-center">
                            <button onclick="showLikes(${p.id}, '${p.title.replace(/'/g, "\\'")}')" class="like-btn flex items-center text-blue-400 hover:text-blue-300 cursor-pointer">
                                <i class="fas fa-thumbs-up mr-1"></i> ${p.likes_count} ไลค์
                            </button>
                            <button onclick="deletePost(${p.id})" class="bg-red-600 text-white px-3 py-1 rounded">ลบ</button>
                        </div>
                    </div>
                `).join('') || '<p class="text-gray-400">ไม่มีโพสต์</p>';
            } catch (e) { 
                document.getElementById("postsList").innerHTML = '<p class="text-red-500">โหลดไม่สำเร็จ</p>'; 
            }
        }

        // Show Likes Modal
        async function showLikes(postId, postTitle) {
            document.getElementById('likesModal').classList.remove('hidden');
            
            // Show Post Info
            document.getElementById('postTitle').textContent = postTitle;
            document.getElementById('postLikesCount').textContent = `ทั้งหมด ${postId} ไลค์`;
            document.getElementById('postInfo').classList.remove('hidden');
            
            try {
                const r = await fetch(`fetch_likes.php?post_id=${postId}`);
                const data = await r.json();
                
                if (data.likes.length === 0) {
                    document.getElementById('likesTableBody').innerHTML = '<tr><td colspan="5" class="p-4 text-center text-gray-400">ยังไม่มีคนกดไลค์</td></tr>';
                    updateLikesStats(data.stats);
                    return;
                }
                
                // Build Table
                document.getElementById('likesTableBody').innerHTML = data.likes.map(like => `
                    <tr class="hover:bg-gray-700">
                        <td class="p-3">
                            <div class="frame" style="--frame-url: ${like.frame ? `url('frames/${like.frame}')` : 'none'};">
                                <img src="uploads/${like.user_avatar}" class="avatar" alt="${like.username}">
                            </div>
                        </td>
                        <td class="p-3">
                            <h4 class="${like.name_color}" style="color: ${like.name_color.startsWith('#') || like.name_color.startsWith('rgb') ? like.name_color : ''};">
                                ${like.display_name || like.username}
                            </h4>
                            <p class="text-sm text-gray-400">@${like.username}</p>
                        </td>
                        <td class="p-3">
                            <div class="text-sm">
                                <strong>${postTitle}</strong><br>
                                <span class="text-gray-400">Post ID: ${postId}</span>
                            </div>
                        </td>
                        <td class="p-3">
                            <span class="text-sm">${new Date(like.created_at).toLocaleString('th-TH')}</span>
                        </td>
                        <td class="p-3">
                            <button onclick="unlikeUser(${like.id}, ${postId})" class="bg-red-600 text-white px-2 py-1 rounded text-xs">
                                <i class="fas fa-trash mr-1"></i>ลบ
                            </button>
                        </td>
                    </tr>
                `).join('');
                
                updateLikesStats(data.stats);
                
            } catch (e) {
                document.getElementById('likesTableBody').innerHTML = '<tr><td colspan="5" class="p-4 text-center text-red-500">โหลดไม่สำเร็จ</td></tr>';
            }
        }

        // Update Stats
        function updateLikesStats(stats) {
            document.getElementById('totalLikesInModal').textContent = stats.total;
            document.getElementById('todayLikes').textContent = stats.today;
            document.getElementById('topUser').textContent = stats.top_user_count;
            document.getElementById('avgLikes').textContent = stats.avg_per_day;
        }

        // Delete Like
        async function unlikeUser(likeId, postId) {
            if (!confirm('ลบไลค์ของผู้ใช้นี้?')) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_like');
                formData.append('like_id', likeId);
                formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
                
                const r = await fetch("dashboard.php", { 
                    method: "POST", 
                    headers: { "X-Requested-With": "XMLHttpRequest" }, 
                    body: formData 
                });
                const data = await r.json();
                
                if (data.success) {
                    showToast('สำเร็จ', 'ลบไลค์เรียบร้อย', 'success');
                    showLikes(postId, document.getElementById('postTitle').textContent);
                    loadPosts();
                } else {
                    showToast('ข้อผิดพลาด', data.message, 'error');
                }
            } catch (e) {
                showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาด', 'error');
            }
        }

        function closeLikesModal() { 
            document.getElementById('likesModal').classList.add('hidden'); 
        }

        // Load Keys
        async function loadKeys() {
            try {
                const r = await fetch("fetch_keys.php");
                const keys = await r.json();
                document.getElementById("keysList").innerHTML = keys.map(k => `
                    <div class="bg-gray-700 p-4 rounded flex justify-between items-center">
                        <div>
                            <p class="font-bold">Key: ${k.key_hash.substring(0,8)}...</p>
                            <p class="text-sm text-gray-400">${new Date(k.created_at).toLocaleString('th-TH')}</p>
                        </div>
                        <button onclick="deleteKey(${k.id})" class="bg-red-600 px-3 py-1 rounded text-white">ลบ</button>
                    </div>
                `).join('') || '<p class="text-gray-400">ไม่มีคีย์</p>';
            } catch (e) { console.error(e); }
        }

        // Post Form
        document.getElementById('postForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const r = await fetch('add_post.php', { method: 'POST', body: formData });
                const data = await r.json();
                if (data.success) { 
                    showToast('สำเร็จ', 'เพิ่มโพสต์เรียบร้อย', 'success'); 
                    e.target.reset(); 
                    loadPosts(); 
                }
                else showToast('ข้อผิดพลาด', data.message, 'error');
            } catch (e) { showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาด', 'error'); }
        });

        // Color Management
        document.getElementById("addColorForm").addEventListener("submit", async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const r = await fetch("dashboard.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, body: formData });
                const data = await r.json();
                if (data.success) {
                    showToast('สำเร็จ', 'เพิ่มสีเรียบร้อย', 'success');
                    e.target.reset();
                    location.reload();
                } else {
                    showToast('ข้อผิดพลาด', data.message, 'error');
                }
            } catch (e) { showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาด', 'error'); }
        });

        async function deleteColor(colorName) {
            if (!confirm(`ลบสี "${colorName}"?`)) return;
            const formData = new FormData();
            formData.append('action', 'delete_color');
            formData.append('color_name', colorName);
            formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
            try {
                const r = await fetch("dashboard.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, body: formData });
                const data = await r.json();
                if (data.success) {
                    showToast('สำเร็จ', 'ลบสีเรียบร้อย', 'success');
                    location.reload();
                }
            } catch (e) { showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาด', 'error'); }
        }

        // Lock/Unlock
        async function lockAll() {
            if (!confirm('ล็อกทุกฟีเจอร์?')) return;
            const formData = new FormData();
            formData.append('action', 'lock_all');
            formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
            try {
                const r = await fetch("dashboard.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, body: formData });
                const data = await r.json();
                if (data.success) { showToast('สำเร็จ', 'ล็อกเรียบร้อย', 'success'); location.reload(); }
            } catch (e) { showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาด', 'error'); }
        }

        async function unlockAll() {
            if (!confirm('ปลดล็อกทุกฟีเจอร์?')) return;
            const formData = new FormData();
            formData.append('action', 'unlock_all');
            formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
            try {
                const r = await fetch("dashboard.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, body: formData });
                const data = await r.json();
                if (data.success) { showToast('สำเร็จ', 'ปลดล็อกเรียบร้อย', 'success'); location.reload(); }
            } catch (e) { showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาด', 'error'); }
        }

        // Other functions
        function closeChangePasswordModal() { document.getElementById('changePasswordModal').classList.add('hidden'); }
        function openChangePasswordModal(userId) { 
            document.getElementById('changePasswordUserId').value = userId; 
            document.getElementById('changePasswordModal').classList.remove('hidden'); 
        }
        function searchUsers() { window.location.href = `?search=${encodeURIComponent(document.getElementById('search').value)}`; }// ✅ Delete Post - ลบจริง + ลบไฟล์ + Toast
async function deletePost(id) {
    if (!confirm('ลบโพสต์นี้? (รวมรูปภาพ/ไฟล์/ไลค์ทั้งหมด)')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_post');
        formData.append('post_id', id);
        formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
        
        // Show loading
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = 'กำลังลบ...';
        btn.disabled = true;
        
        const r = await fetch("dashboard.php", { 
            method: "POST", 
            headers: { "X-Requested-With": "XMLHttpRequest" }, 
            body: formData 
        });
        const data = await r.json();
        
        btn.textContent = originalText;
        btn.disabled = false;
        
        if (data.success) {
            showToast('สำเร็จ', `ลบโพสต์ "${data.title}" เรียบร้อย`, 'success');
            loadPosts(); // Reload list
            loadTotalLikes(); // Update stats
        } else {
            showToast('ข้อผิดพลาด', data.message, 'error');
        }
    } catch (e) {
        showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการลบ', 'error');
    }
}async function deletePost(id) { if(confirm('ลบโพสต์?')) { loadPosts(); } }
        function editUser(userId) { alert('Edit user coming soon!'); }

        document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
            try {
                const r = await fetch("dashboard.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, body: formData });
                const data = await r.json();
                if (data.success) { showToast('สำเร็จ', 'เปลี่ยนรหัสผ่านเรียบร้อย', 'success'); closeChangePasswordModal(); }
                else showToast('ข้อผิดพลาด', data.message, 'error');
            } catch (e) { showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาด', 'error'); }
        });

        // Init
        document.addEventListener('DOMContentLoaded', () => {
            loadTotalLikes();
            loadPosts();
            loadKeys();
            document.getElementById('search').addEventListener('keypress', e => { if(e.key === 'Enter') searchUsers(); });
        });
    </script>
</body>
</html>