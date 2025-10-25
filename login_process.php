<?php
session_start();

// ตั้งค่า session cookie parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// เริ่มเซสชัน
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/php_errors.log');

// สร้าง CSRF token ถ้ายังไม่มี
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "⛔ CSRF token ไม่ถูกต้อง";
        header("Location: login.php");
        exit;
    }

    // รีเซ็ต CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // เชื่อมต่อฐานข้อมูล
    $conn = new mysqli('localhost', 'app_user', 'secure_password', 'user_db');
    if ($conn->connect_error) {
        $_SESSION['error'] = "❌ การเชื่อมต่อฐานข้อมูลล้มเหลว";
        error_log('Database connection failed: ' . $conn->connect_error);
        header("Location: login.php");
        exit;
    }

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "❌ กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
        header("Location: login.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT id, password, role, display_name, email, phone, avatar, name_color FROM users WHERE username = ?");
    if (!$stmt) {
        $_SESSION['error'] = "❌ การเตรียมคำสั่ง SQL ล้มเหลว";
        error_log('Query preparation failed: ' . $conn->error);
        header("Location: login.php");
        exit;
    }
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        $_SESSION['error'] = "❌ การรันคำสั่ง SQL ล้มเหลว";
        error_log('Query execution failed: ' . $stmt->error);
        header("Location: login.php");
        exit;
    }
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hashedPassword, $role, $displayName, $email, $phone, $avatar, $nameColor);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            session_regenerate_id(true); // ป้องกัน session fixation
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $id; // ใช้ user_id เพื่อความสอดคล้อง
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['display_name'] = $displayName;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            $_SESSION['avatar'] = $avatar ?? 'korox.webp';
            $_SESSION['name_color'] = $nameColor ?? 'text-white';
            $_SESSION['last_activity'] = time();

            // Remember me
            if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
                setcookie('rememberme', session_id(), time() + (30 * 24 * 60 * 60), "/", "", true, true);
            } else {
                setcookie('rememberme', '', time() - 3600, "/", "", true, true);
            }

            // Logging login
            $log = date('Y-m-d H:i:s') . " | Login by ID $id | IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
            file_put_contents('logs/login_log.txt', $log, FILE_APPEND);

            if ($role === 'admin') {
                header("Location: dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $_SESSION['error'] = "❌ รหัสผ่านไม่ถูกต้อง";
            header("Location: login.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "❌ ไม่พบผู้ใช้";
        header("Location: login.php");
        exit;
    }

    $stmt->close();
    $conn->close();
} else {
    $_SESSION['error'] = "⛔ กรุณาใช้ฟอร์มเพื่อล็อกอิน";
    header("Location: login.php");
    exit;
}
?>