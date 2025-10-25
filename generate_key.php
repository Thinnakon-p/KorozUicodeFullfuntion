<?php
// ตั้งค่า session cookie parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true, // ใช้ false ถ้าไม่ใช่ HTTPS (เช่น ท้องถิ่น)
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

// ตรวจสอบการล็อกอินและสิทธิ์แอดมิน
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_access']) || $_SESSION['admin_access'] !== true) {
    http_response_code(403);
    echo "<h1>403 - ไม่มีสิทธิ์เข้าถึง</h1>";
    echo "<p>คุณต้องเป็นแอดมินที่ได้รับอนุญาต</p>";
    exit;
}

// Include file การเชื่อมต่อฐานข้อมูล
require 'db.php';

// ตรวจสอบว่ามีการร้องขอสร้างคีย์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_key'])) {
    try {
        $key_length = 32;
        $new_key = bin2hex(random_bytes($key_length)); // สร้างคีย์แบบสุ่ม
        $user_id = $_SESSION['user_id']; // ผูกกับผู้ใช้ที่ล็อกอิน

        // เข้ารหัสคีย์ด้วย password_hash
        $hashed_key = password_hash($new_key, PASSWORD_DEFAULT);

        // บันทึกคีย์ลงฐานข้อมูล
        $stmt = $pdo->prepare("INSERT INTO admin_keys (key_value, user_id) VALUES (?, ?)");
        $stmt->execute([$hashed_key, $user_id]);

        // แสดงคีย์ที่สร้าง (สำหรับแอดมิน)
        echo "<p>คีย์ใหม่ที่สร้าง: <strong>$new_key</strong></p>";
        echo "<p>กรุณาจดบันทึกคีย์นี้ไว้ เนื่องจากจะแสดงเพียงครั้งเดียว!</p>";
    } catch (PDOException $e) {
        error_log("Error generating key: " . $e->getMessage());
        echo "<p>เกิดข้อผิดพลาดในการสร้างคีย์ กรุณาลองใหม่</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Generate Admin Key</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #121212;
            color: #f0f0f0;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #1f2937;
            border-radius: 8px;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <nav class="fixed w-full z-30 top-0 left-0 bg-gray-800">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <a class="text-2xl font-bold" href="dashboard.php">Dashboard</a>
            <a class="text-white" href="logout.php">Logout</a>
        </div>
    </nav>
    <main class="flex-grow pt-24 max-w-7xl mx-auto px-6">
        <div class="container">
            <h2 class="text-2xl font-bold mb-6">สร้างคีย์สำหรับแอดมิน</h2>
            <p>สร้างคีย์ใหม่สำหรับการเข้าถึงแดชบอร์ดแอดมิน</p>
            <form method="POST" class="mt-4 space-y-4">
                <button type="submit" name="generate_key" class="bg-green-600 text-white p-2 rounded w-full">สร้างคีย์ใหม่</button>
            </form>
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_key'])): ?>
                <div class="mt-4 p-4 bg-gray-700 rounded">
                    <p>คีย์นี้ถูกบันทึกในระบบแล้ว กรุณาแจ้งให้แอดมินที่ต้องการใช้</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>