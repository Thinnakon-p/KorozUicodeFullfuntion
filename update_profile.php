<?php
session_start();
require 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("CSRF token ไม่ถูกต้อง");
}

$userId = $_POST['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "ไม่พบผู้ใช้";
    header('Location: profile.php');
    exit;
}

$displayName = $_POST['display_name'] ?? $user['username'];
$nameColor = $_POST['name_color'] ?? $user['name_color'];
$frame = $_POST['frame'] ?? $user['frame'];
$isLocked = isset($_POST['is_locked']) ? 1 : $user['is_locked'];
$roleId = $_POST['role_id'] ?? $user['role_id'];
$lockedColors = implode(',', $_POST['locked_colors'] ?? []);
$lockedFunctions = implode(',', array_intersect(['display_name', 'name_color', 'frame', 'avatar', 'password'], $_POST['locked_functions'] ?? []));

// จัดการอัปโหลด avatar
$avatar = $user['avatar'];
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['avatar'];
    $fileName = uniqid() . '_' . basename($file['name']);
    $targetDir = "uploads/";
    $targetFile = $targetDir . $fileName;

    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($imageFileType, $allowedTypes)) {
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            $avatar = $fileName;
            // ลบไฟล์เก่า (ถ้าไม่ใช่ default.png)
            if ($user['avatar'] && $user['avatar'] !== 'default.png') {
                unlink($targetDir . $user['avatar']);
            }
        }
    }
}

$stmt = $pdo->prepare("UPDATE users SET display_name = ?, name_color = ?, frame = ?, is_locked = ?, role_id = ?, locked_colors = ?, locked_functions = ?, avatar = ? WHERE id = ?");
$stmt->execute([$displayName, $nameColor, $frame, $isLocked, $roleId, $lockedColors, $lockedFunctions, $avatar, $userId]);

$_SESSION['success'] = "อัปเดตโปรไฟล์สำเร็จ";
header('Location: profile.php?user_id=' . $userId);
exit;
?>