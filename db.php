<?php
// db.php - ปรับลำดับ: เชื่อมต่อ → สร้างตาราง → update schema ก่อนใช้งาน
$host = 'localhost';
$db = 'user_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // สร้างตาราง roles
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE
    )");
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO roles (name) VALUES ('admin'), ('user')");
    }

    // สร้างตาราง users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'user',
        display_name VARCHAR(100),
        name_color VARCHAR(50) DEFAULT 'text-white',
        frame VARCHAR(100),
        avatar VARCHAR(255) DEFAULT 'default.png',
        is_locked TINYINT DEFAULT 0,
        locked_functions VARCHAR(255) DEFAULT '',
        locked_colors VARCHAR(255) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ดึง columns ปัจจุบัน
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    // เพิ่ม locked_functions และ locked_colors ถ้ายังไม่มี
    $required = ['locked_functions' => 'VARCHAR(255) DEFAULT ""', 'locked_colors' => 'VARCHAR(255) DEFAULT ""'];
    foreach ($required as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE users ADD $col $def");
            error_log("Added $col column to users");
        }
    }

    // Seed admin ถ้ายังไม่มี
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, role, display_name, is_locked, locked_functions, locked_colors, name_color, frame, avatar) 
                VALUES (?, ?, ?, 'admin', 'Admin', 0, '', '', 'text-white', '', 'default.png')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['admin', 'admin@example.com', $hashed_password]);
        error_log("Successfully seeded admin user");
    }

    // ✅ สร้างตาราง posts - รองรับ รูปภาพ + MCPack 100%
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        upload_type ENUM('image', 'pack') DEFAULT 'image',
        file_path VARCHAR(500),
        file_name VARCHAR(255),
        file_size BIGINT DEFAULT 0,
        file_type VARCHAR(20),
        link VARCHAR(500),
        image VARCHAR(255),
        user_id INT DEFAULT NULL,
        likes_count INT DEFAULT 0,
        is_locked TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    // ✅ อัปเดต Schema posts - เพิ่มคอลัมน์ใหม่ถ้ายังไม่มี
    $post_columns = $pdo->query("SHOW COLUMNS FROM posts")->fetchAll(PDO::FETCH_COLUMN);
    $new_columns = [
        'upload_type' => "ENUM('image', 'pack') DEFAULT 'image'",
        'file_path' => 'VARCHAR(500)',
        'file_name' => 'VARCHAR(255)',
        'file_size' => 'BIGINT DEFAULT 0',
        'file_type' => 'VARCHAR(20)'
    ];

    foreach ($new_columns as $col => $def) {
        if (!in_array($col, $post_columns)) {
            $pdo->exec("ALTER TABLE posts ADD $col $def");
            error_log("Added $col column to posts");
        }
    }

    // Seed posts ทดสอบ
    $stmt = $pdo->query("SELECT COUNT(*) FROM posts");
    if ($stmt->fetchColumn() == 0) {
        $admin_id = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
        $pdo->prepare("INSERT INTO posts (title, description, upload_type, file_name, user_id) VALUES (?, ?, 'image', ?, ?)")->execute([
            'โพสต์ทดสอบ 1', 'นี่คือโพสต์ทดสอบรูปภาพ', 'test_image.jpg', $admin_id
        ]);
        $pdo->prepare("INSERT INTO posts (title, description, upload_type, file_name, user_id) VALUES (?, ?, 'pack', ?, ?)")->execute([
            'Resource Pack ทดสอบ', 'นี่คือ Resource Pack ทดสอบ', 'test_pack.mcpack', $admin_id
        ]);
        error_log("Successfully seeded test posts");
    }

    // สร้างตาราง likes
    $pdo->exec("CREATE TABLE IF NOT EXISTS likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (post_id, user_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // สร้างตาราง admin_keys
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        key_hash VARCHAR(255) NOT NULL,
        user_id INT DEFAULT NULL,
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // สร้างตาราง colors
    $pdo->exec("CREATE TABLE IF NOT EXISTS colors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        color_name VARCHAR(50) UNIQUE NOT NULL
    )");
    $stmt = $pdo->query("SELECT COUNT(*) FROM colors");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO colors (color_name) VALUES ('text-white'), ('text-red-500'), ('text-blue-500'), ('text-green-500')");
    }

} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>