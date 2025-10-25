<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    if ($code === $_SESSION['verification_code']) {
        $conn = new mysqli('localhost', 'root', '', 'user_db');
        if ($conn->connect_error) {
            die('Database connection failed: ' . $conn->connect_error);
        }
        $userId = $_SESSION['id'];
        $new_password = $_SESSION['pending_password'];
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $userId);
        if ($stmt->execute()) {
            $success = 'เปลี่ยนรหัสผ่านสำเร็จ';
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_email']);
            unset($_SESSION['pending_password']);
        } else {
            $error = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
        }
        $stmt->close();
        $conn->close();
    } else {
        $error = 'รหัสยืนยันไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-white min-h-screen p-6">
    <div class="max-w-3xl mx-auto bg-zinc-900 p-6 rounded-lg">
        <h2 class="text-2xl font-bold mb-6">ยืนยันอีเมล</h2>
        <p class="mb-4">กรุณากรอกรหัสยืนยันที่ส่งไปยังอีเมลของคุณ</p>
        <?php if ($error): ?>
            <p class="text-red-500 mb-4"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="text-green-500 mb-4"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <form action="verify_email.php" method="POST">
            <div class="mb-4">
                <label for="code" class="block text-sm font-medium text-gray-300">รหัสยืนยัน</label>
                <input type="text" id="code" name="code" required class="mt-1 block w-full p-2 rounded bg-zinc-800 border border-gray-600 focus:border-blue-500 focus:outline-none">
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">ยืนยัน</button>
        </form>
        <a href="profile.php" class="mt-4 block text-blue-400">กลับไปโปรไฟล์</a>
    </div>
</body>
</html>