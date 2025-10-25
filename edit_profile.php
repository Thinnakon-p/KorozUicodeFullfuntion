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

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ดึงข้อมูล Session User
$_SESSION['display_name'] = $user['display_name'] ?? $user['username'];
$_SESSION['name_color'] = $user['name_color'] ?? '#ffffff';
$_SESSION['avatar'] = $user['avatar'] ?? 'default.png';

$isAdmin = isset($_SESSION['admin_access']) && $_SESSION['admin_access'] === true;

// ดึงข้อมูลผู้ใช้ที่กำลังแก้ไข
$editUserId = $_GET['user_id'] ?? $userId;
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$editUserId]);
$editUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$editUser) {
    $_SESSION['error'] = "ไม่พบผู้ใช้ที่ระบุ";
    header('Location: dashboard.php');
    exit;
}

$editUserName = $editUser['display_name'] ?? $editUser['username'];
$frame = $editUser['frame'] ?? '';
$isLocked = $editUser['is_locked'] ?? 0;

$stmt_roles = $pdo->query("SELECT * FROM roles");
$roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

$lockedColors = explode(',', $editUser['locked_colors'] ?? '');
$lockedFunctions = explode(',', $editUser['locked_functions'] ?? '');

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "CSRF token ไม่ถูกต้อง";
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบถ้วน";
            } elseif ($new_password !== $confirm_password) {
                $_SESSION['error'] = "รหัสผ่านใหม่ไม่ตรงกัน";
            } elseif (!password_verify($current_password, $editUser['password'])) {
                $_SESSION['error'] = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
            } elseif (strlen($new_password) < 6) {
                $_SESSION['error'] = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $editUserId]);
                $_SESSION['success'] = "เปลี่ยนรหัสผ่านสำเร็จ";
            }
        }
    }
    header('Location: edit_profile.php?user_id=' . $editUserId);
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ | Koroz City</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Kanit', sans-serif; 
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a2e 50%, #16213e 100%);
        }
        .glass { 
            background: rgba(255, 255, 255, 0.05); 
            backdrop-filter: blur(10px); 
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* ✅ CUSTOM COLORS - สร้างจากตาราง colors ใน DB */
        <?php 
        $stmt_colors_css = $pdo->query("SELECT color_name FROM colors");
        while ($color = $stmt_colors_css->fetchColumn()): 
            $color_clean = str_replace('#', '', $color);
        ?>
            .color-<?= $color_clean ?> { 
                color: <?= $color ?> !important; 
            }
        <?php endwhile; ?>
        
        /* Session User Color */
        .session-name-color { 
            color: <?= $_SESSION['name_color'] ?> !important; 
        }
        
        /* Edit User Color */
        .edit-user-color { 
            color: <?= $editUser['name_color'] ?> !important; 
        }
        
        .avatar-frame { 
            position: relative; 
            display: inline-block; 
        }
        .avatar-frame img { 
            border-radius: 50%; 
            transition: transform 0.3s ease; 
        }
        .avatar-frame:hover img { 
            transform: scale(1.05); 
        }
        .avatar-frame::before {
            content: ''; 
            position: absolute;
            top: -8px; left: -8px; right: -8px; bottom: -8px;
            background: url('frames/<?= htmlspecialchars($frame) ?>') no-repeat center;
            background-size: 120%; 
            z-index: -1; 
            opacity: 0.8;
        }
        input:focus, select:focus { 
            outline: none; 
            ring: 2px solid #b91c1c; 
        }
    </style>
</head>
<body class="min-h-screen text-white">
    <!-- HEADER -->
    <header class="bg-black/80 backdrop-blur-md fixed w-full z-50 border-b border-red-500/20">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <a href="<?= $isAdmin ? 'dashboard.php' : 'index.php' ?>" class="text-2xl font-bold text-red-500">
                <i class="fas fa-home mr-2"></i>Koroz City
            </a>
            <div class="flex items-center space-x-4">
                <!-- ✅ สีชื่อ Session User -->
                <span class="session-name-color font-semibold text-lg">
                    <?= htmlspecialchars($_SESSION['display_name']) ?>
                </span>
                <?php if ($isAdmin): ?>
                    <a href="dashboard.php" class="bg-red-600 px-4 py-2 rounded-lg hover:bg-red-700 transition">
                        <i class="fas fa-tachometer-alt mr-1"></i>แดชบอร์ด
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="text-red-400 hover:text-red-300">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="pt-24 pb-12">
        <div class="max-w-4xl mx-auto px-4">
            <!-- ALERTS -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-500/20 border border-red-500/50 p-4 rounded-lg mb-6 glass">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-500/20 border border-green-500/50 p-4 rounded-lg mb-6 glass">
                    <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- PROFILE HEADER -->
            <div class="text-center mb-8 glass p-6 rounded-xl">
                <div class="avatar-frame inline-block mb-4">
                    <img src="uploads/<?= htmlspecialchars($editUser['avatar'] ?? 'default.png') ?>" 
                         alt="Avatar" class="w-32 h-32 border-4 border-red-500/50" 
                         onerror="this.src='uploads/default.png'">
                </div>
                
                <!-- ✅ สีชื่อผู้ใช้ที่แก้ไข -->
                <h1 class="edit-user-color text-4xl font-bold mb-2">
                    <?= htmlspecialchars($editUserName) ?>
                </h1>
                <p class="text-gray-400 mb-2">ID: <?= $editUserId ?></p>
                
                <!-- ✅ Preview สีปัจจุบัน -->
                <div class="mb-4">
                    <span class="text-gray-400">สีปัจจุบัน: </span>
                    <span class="edit-user-color color-preview px-2 py-1 rounded bg-black/20">
                        <?= htmlspecialchars($editUser['name_color']) ?>
                    </span>
                </div>
                
                <?php if ($isAdmin && $editUserId != $userId): ?>
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold mt-2 
                        <?= $isLocked ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400' ?>">
                        <?= $isLocked ? '🔒 ล็อกผู้ใช้' : '✅ ใช้งานได้' ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- EDIT FORM -->
                <div class="glass p-6 rounded-xl">
                    <h2 class="text-2xl font-bold mb-6 flex items-center">
                        <i class="fas fa-edit mr-2 text-red-500"></i>แก้ไขโปรไฟล์
                    </h2>
                    <form action="update_profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="user_id" value="<?= $editUserId ?>">

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">ชื่อที่แสดง</label>
                            <input type="text" name="display_name" value="<?= htmlspecialchars($editUserName) ?>" 
                                   class="w-full p-3 rounded-lg bg-zinc-800 border border-gray-600 focus:ring-2 focus:ring-red-500"
                                   required <?= in_array('display_name', $lockedFunctions) ? 'disabled' : '' ?>>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">สีชื่อ</label>
                            <select name="name_color" id="nameColorSelect" onchange="previewColor()" 
                                    class="w-full p-3 rounded-lg bg-zinc-800 border border-gray-600 focus:ring-2 focus:ring-red-500"
                                    <?= in_array('name_color', $lockedFunctions) ? 'disabled' : '' ?>>
                                <?php 
                                $stmt_colors_select = $pdo->query("SELECT color_name FROM colors");
                                while ($color = $stmt_colors_select->fetchColumn()): 
                                    $color_clean = str_replace('#', '', $color);
                                ?>
                                    <?php if (!in_array($color, $lockedColors)): ?>
                                        <option value="<?= $color ?>" <?= $color === $editUser['name_color'] ? 'selected' : '' ?> 
                                                style="color: <?= $color ?> !important;">
                                            <span class="color-<?= $color_clean ?> mr-2">⬤</span>
                                            <?= $color ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            </select>
                            
                            <!-- ✅ Preview สีเรียลไทม์ -->
                            <div id="colorPreview" class="mt-2 p-2 bg-zinc-800 rounded flex items-center">
                                <span class="font-bold px-2 py-1 rounded mr-2" id="previewName" style="color: <?= $editUser['name_color'] ?>">
                                    <?= htmlspecialchars($editUserName) ?>
                                </span>
                                <span class="text-gray-400 text-sm" id="previewColorName"><?= $editUser['name_color'] ?></span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">กรอบอวตาร</label>
                            <select name="frame" class="w-full p-3 rounded-lg bg-zinc-800 border border-gray-600 focus:ring-2 focus:ring-red-500"
                                    <?= in_array('frame', $lockedFunctions) ? 'disabled' : '' ?>>
                                <option value="">ไม่มีกรอบ</option>
                                <?php 
                                $freeFrames = ['frame1.png', 'frame2.png', 'frame3.png', 'frame4.png', 'frame5.png', 
                                               'frame6.png', 'frame7.png', 'frame8.png', 'frame9.png', 'frame10.png'];
                                foreach ($freeFrames as $f): ?>
                                    <option value="<?= $f ?>" <?= $f === $frame ? 'selected' : '' ?>><?= $f ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">รูปโปรไฟล์</label>
                            <input type="file" name="avatar" accept="image/*" 
                                   class="w-full p-3 rounded-lg bg-zinc-800 border border-gray-600 focus:ring-2 focus:ring-red-500"
                                   <?= in_array('avatar', $lockedFunctions) ? 'disabled' : '' ?>>
                            <p class="text-sm text-gray-400 mt-1">ปัจจุบัน: <?= htmlspecialchars($editUser['avatar'] ?? 'default.png') ?></p>
                        </div>

                        <?php if ($isAdmin && $editUserId != $userId): ?>
                            <div class="border-t pt-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_locked" value="1" <?= $isLocked ? 'checked' : '' ?> 
                                           class="rounded border-gray-600 bg-zinc-800 text-red-500 focus:ring-red-500 mr-2">
                                    <span class="text-red-400">ล็อกผู้ใช้</span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 p-3 rounded-lg font-bold transition">
                            <i class="fas fa-save mr-2"></i>บันทึก
                        </button>
                    </form>
                </div>

                <!-- CHANGE PASSWORD -->
                <div class="glass p-6 rounded-xl">
                    <h2 class="text-2xl font-bold mb-6 flex items-center">
                        <i class="fas fa-key mr-2 text-red-500"></i>เปลี่ยนรหัสผ่าน
                    </h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">รหัสผ่านปัจจุบัน</label>
                            <input type="password" name="current_password" required
                                   class="w-full p-3 rounded-lg bg-zinc-800 border border-gray-600 focus:ring-2 focus:ring-red-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">รหัสผ่านใหม่</label>
                            <input type="password" name="new_password" required
                                   class="w-full p-3 rounded-lg bg-zinc-800 border border-gray-600 focus:ring-2 focus:ring-red-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">ยืนยันรหัสผ่าน</label>
                            <input type="password" name="confirm_password" required
                                   class="w-full p-3 rounded-lg bg-zinc-800 border border-gray-600 focus:ring-2 focus:ring-red-500">
                        </div>

                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 p-3 rounded-lg font-bold transition">
                            <i class="fas fa-lock mr-2"></i>เปลี่ยนรหัสผ่าน
                        </button>
                    </form>
                </div>
            </div>

            <!-- ADMIN CONTROLS -->
            <?php if ($isAdmin && $editUserId != $userId): ?>
                <div class="mt-8 glass p-6 rounded-xl">
                    <h2 class="text-2xl font-bold mb-6 flex items-center">
                        <i class="fas fa-cog mr-2 text-red-500"></i>การจัดการผู้ใช้
                    </h2>
                    <div class="grid md:grid-cols-3 gap-4">
                        <a href="dashboard.php" class="bg-blue-600 hover:bg-blue-700 p-3 rounded-lg text-center">
                            <i class="fas fa-users block mb-1"></i>ดูผู้ใช้ทั้งหมด
                        </a>
                        <button onclick="lockUser(<?= $editUserId ?>)" 
                                class="bg-red-600 hover:bg-red-700 p-3 rounded-lg text-center <?= $isLocked ? 'hidden' : '' ?>">
                            <i class="fas fa-lock block mb-1"></i>ล็อกผู้ใช้
                        </button>
                        <button onclick="unlockUser(<?= $editUserId ?>)" 
                                class="bg-green-600 hover:bg-green-700 p-3 rounded-lg text-center <?= !$isLocked ? 'hidden' : '' ?>">
                            <i class="fas fa-unlock block mb-1"></i>ปลดล็อก
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- BACK BUTTONS -->
            <div class="mt-8 flex space-x-4">
                <a href="<?= $isAdmin ? 'dashboard.php' : 'index.php' ?>" 
                   class="flex-1 bg-gray-600 hover:bg-gray-700 p-3 rounded-lg text-center transition">
                    <i class="fas fa-arrow-left mr-2"></i>กลับ<?= $isAdmin ? 'แดชบอร์ด' : 'หน้าหลัก' ?>
                </a>
                <?php if ($editUserId != $userId): ?>
                    <a href="profile.php" class="flex-1 bg-gray-600 hover:bg-gray-700 p-3 rounded-lg text-center transition">
                        <i class="fas fa-user mr-2"></i>โปรไฟล์ตัวเอง
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // ✅ Preview สีเรียลไทม์
        function previewColor() {
            const select = document.getElementById('nameColorSelect');
            const previewName = document.getElementById('previewName');
            const previewColorName = document.getElementById('previewColorName');
            const selectedColor = select.value;
            
            // ใช้ style inline สำหรับ HEX จริง
            previewName.style.color = selectedColor;
            previewName.textContent = '<?= htmlspecialchars($editUserName) ?>';
            previewColorName.textContent = selectedColor;
        }

        // รัน preview ครั้งแรก
        previewColor();

        // Admin Functions
        async function lockUser(userId) {
            if (!confirm('ล็อกผู้ใช้นี้?')) return;
            try {
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=lock_user&user_id=${userId}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
                });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                }
            } catch (error) {
                alert('เกิดข้อผิดพลาด');
            }
        }

        async function unlockUser(userId) {
            if (!confirm('ปลดล็อกผู้ใช้?')) return;
            try {
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=unlock_user&user_id=${userId}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
                });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                }
            } catch (error) {
                alert('เกิดข้อผิดพลาด');
            }
        }
    </script>
</body>
</html>