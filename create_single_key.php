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

// ตรวจสอบการล็อกอินและสิทธิ์แอดมิน (สมมติว่าใช้บัญชีแอดมินที่มีอยู่)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_access']) || $_SESSION['admin_access'] !== true) {
    http_response_code(403);
    echo "<h1>403 - ไม่มีสิทธิ์เข้าถึง</h1>";
    echo "<p>คุณต้องเป็นแอดมินที่ได้รับอนุญาต</p>";
    exit;
}

// Include file การเชื่อมต่อฐานข้อมูล
require 'db.php';

try {
    // สร้างคีย์แบบสุ่ม
    $key_length = 32;
    $new_key = bin2hex(random_bytes($key_length)); // คีย์แบบสุ่ม เช่น a1b2c3d4e5f6...

    // เข้ารหัสคีย์
    $hashed_key = password_hash($new_key, PASSWORD_DEFAULT);

    // บันทึกคีย์ลงฐานข้อมูล (สมมติใช้ user_id จากเซสชัน)
    $user_id = $_SESSION['user_id']; // ต้องแน่ใจว่า user_id ถูกตั้งค่าในเซสชัน
    $stmt = $pdo->prepare("INSERT INTO admin_keys (key_value, user_id) VALUES (?, ?)");
    $stmt->execute([$hashed_key, $user_id]);

    // แสดงคีย์ที่สร้าง (สำหรับแอดมิน)
    echo "<!DOCTYPE html>";
    echo "<html lang='th'>";
    echo "<head><meta charset='UTF-8'><title>สร้างคีย์สำเร็จ</title><script src='https://cdn.tailwindcss.com'></script></head>";
    echo "<body class='min-h-screen flex flex-col bg-gray-900 text-white'>";
    echo "<main class='flex-grow pt-24 max-w-7xl mx-auto px-6'>";
    echo "<div class='container max-w-md mx-auto p-6 bg-gray-800 rounded-lg shadow-lg'>";
    echo "<h2 class='text-2xl font-bold mb-4'>สร้างคีย์สำเร็จ</h2>";
    echo "<p>คีย์ใหม่ที่สร้าง: <strong>$new_key</strong></p>";
    echo "<p>กรุณาจดบันทึกคีย์นี้ไว้ เนื่องจากจะแสดงเพียงครั้งเดียว!</p>";
    echo "<p>คีย์นี้ถูกผูกกับ User ID: $user_id</p>";
    echo "<a href='dashboard.php' class='mt-4 inline-block bg-blue-600 text-white p-2 rounded'>กลับไปยัง Dashboard</a>";
    echo "</div></main></body></html>";
} catch (PDOException $e) {
    error_log("Error creating key: " . $e->getMessage());
    echo "<p>เกิดข้อผิดพลาดในการสร้างคีย์: " . $e->getMessage() . "</p>";
}
?>
