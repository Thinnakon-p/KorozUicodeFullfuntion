<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}
$conn = new mysqli('localhost', 'root', '', 'user_db');
if ($conn->connect_error) {
    http_response_code(500);
    die('Database connection failed: ' . $conn->connect_error);
}
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    if ($new_password !== $confirm_password) {
        $error = 'รหัสผ่านใหม่ไม่ตรงกัน';
    } elseif (strlen($new_password) < 8) {
        $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
    } else {
        $userId = $_SESSION['id'];
        $stmt = $conn->prepare("SELECT password, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if (password_verify($old_password, $user['password'])) {
            // Placeholder for email verification step
            // TODO: Implement email verification (e.g., send a verification code to $user['email'])
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed, $userId);
            if ($update->execute()) {
                $success = 'เปลี่ยนรหัสผ่านสำเร็จ';
            } else {
                $error = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
            }
            $update->close();
        } else {
            $error = 'รหัสผ่านเก่าไม่ถูกต้อง';
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-white min-h-screen p-6">
    <div class="max-w-3xl mx-auto bg-zinc-900 p-6 rounded-lg">
        <h2 class="text-2xl font-bold mb-6">เปลี่ยนรหัสผ่าน</h2>
        <?php if ($error): ?>
            <p class="text-red-500 mb-4"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="text-green-500 mb-4"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <form action="change_password.php" method="POST">
            <div class="mb-4">
                <label for="old_password" class="block text-sm font-medium text-gray-300">รหัสผ่านเก่า</label>
                <input type="password" id="old_password" name="old_password" required class="mt-1 block w-full p-2 rounded bg-zinc-800 border border-gray-600 focus:border-blue-500 focus:outline-none">
            </div>
            <div class="mb-4">
                <label for="new_password" class="block text-sm font-medium text-gray-300">รหัสผ่านใหม่</label>
                <input type="password" id="new_password" name="new_password" required class="mt-1 block w-full p-2 rounded bg-zinc-800 border border-gray-600 focus:border-blue-500 focus:outline-none">
            </div>
            <div class="mb-4">
                <label for="confirm_password" class="block text-sm font-medium text-gray-300">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" id="confirm_password" name="confirm_password" required class="mt-1 block w-full p-2 rounded bg-zinc-800 border border-gray-600 focus:border-blue-500 focus:outline-none">
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">บันทึก</button>
        </form>
        <a href="profile.php" class="mt-4 block text-blue-400">กลับไปโปรไฟล์</a>
    </div>
</body>
</html>