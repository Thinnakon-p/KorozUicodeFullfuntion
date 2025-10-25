<?php
session_start();
require 'db.php';

if (isset($_SESSION['admin_access']) && $_SESSION['admin_access']) {
    $key_length = 32;
    $new_key = bin2hex(random_bytes($key_length));
    $hashed_key = password_hash($new_key, PASSWORD_DEFAULT);
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("INSERT INTO admin_keys (key_value, user_id, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$hashed_key, $user_id]);
    echo "<script>alert('คีย์ใหม่: $new_key กรุณาจดบันทึก'); window.location='dashboard.php';</script>";
    exit;
}
header('Location: dashboard.php');
exit;
?>