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

// ✅ อย่าใช้ session_regenerate_id(true) ที่นี่ → ล้าง session!
// session_regenerate_id(true); ← ลบบรรทัดนี้ออก!

error_log('Session data in index.php: ' . print_r($_SESSION, true));

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db.php';

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, display_name, name_color, avatar, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user) {
    $_SESSION['display_name'] = $user['display_name'] ?? $user['username'];
    $_SESSION['name_color'] = $user['name_color'] ?? 'text-white';
    $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
    $_SESSION['role'] = $user['role'];
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || (isset($_SESSION['admin_access']) && $_SESSION['admin_access'] === true);

$error = '';

// ✅ ตรวจสอบคีย์แอดมิน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_key']) && !$isAdmin) {
    $input_key = trim($_POST['admin_key']);
    try {
        $stmt = $pdo->prepare("SELECT key_hash, user_id FROM admin_keys WHERE is_active = 1");
        $stmt->execute();
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $valid = false;
        foreach ($keys as $key_record) {
            if (password_verify($input_key, $key_record['key_hash'])) {
                // อนุญาตให้ใช้ได้ทุก user ที่มีคีย์ถูกต้อง
                $_SESSION['admin_access'] = true;
                $valid = true;
                break;
            }
        }

        if ($valid) {
            // ✅ บังคับบันทึก session ก่อน redirect
            session_write_close();
            header('Location: dashboard.php');
            exit;
        } else {
            $error = "คีย์ไม่ถูกต้องหรือหมดอายุ กรุณาตรวจสอบอีกครั้ง";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการตรวจสอบคีย์";
        error_log("Key check failed: " . $e->getMessage());
    }
}

// Timeout
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Koroz City - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;700&display=swap" rel="stylesheet"/>
    <style>
        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: #121212; 
            color: #f0f0f0; 
        }

        /* *** 4 CARDS PER ROW GRID *** */
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            padding: 20px 0;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* *** CARD ANIMATION *** */
        .post {
            background-color: #1e1e1e;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            transition: all 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
            opacity: 0;
            transform: translateY(30px);
            cursor: pointer;
            position: relative;
        }

        /* *** STAGGER ANIMATION *** */
        .post:nth-child(1) { animation: slideInUp 0.6s 0.1s ease forwards; }
        .post:nth-child(2) { animation: slideInUp 0.6s 0.2s ease forwards; }
        .post:nth-child(3) { animation: slideInUp 0.6s 0.3s ease forwards; }
        .post:nth-child(4) { animation: slideInUp 0.6s 0.4s ease forwards; }
        .post:nth-child(5) { animation: slideInUp 0.6s 0.5s ease forwards; }
        .post:nth-child(6) { animation: slideInUp 0.6s 0.6s ease forwards; }

        @keyframes slideInUp {
            0% { opacity: 0; transform: translateY(30px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .post:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(185, 28, 28, 0.3);
        }

        /* *** POST CONTENT *** */
        .post-header { 
            display: flex; align-items: center; 
            padding: 16px; 
            border-bottom: 1px solid #333; 
        }
        .post-avatar { 
            width: 48px; height: 48px; 
            border-radius: 50%; 
            margin-right: 12px; 
            border: 2px solid #b91c1c; 
        }
        .post-description { 
            padding: 16px; 
            color: #f0f0f0; 
            line-height: 1.6; 
            min-height: 80px;
        }
        .post-image { 
            width: 100%; 
            height: 200px; 
            object-fit: cover; 
        }
        .post-footer { 
            padding: 12px 16px; 
            font-size: 0.9em; 
            color: #aaa; 
        }
        .post-actions { 
            display: flex; 
            justify-content: center; 
            padding: 12px 16px; 
            background-color: #1a1a1a; 
        }
        .action-btn { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            color: #b0b3b8; 
            cursor: pointer; 
            transition: all 0.3s; 
            font-weight: 600; 
            padding: 8px 16px;
            border-radius: 8px;
            width: 100%;
            justify-content: center;
        }
        .action-btn:hover:not(.disabled) { 
            color: #b91c1c; 
            background: rgba(185, 28, 28, 0.1);
        }
        .action-btn.liked { color: #1877f2; }
        .action-btn.disabled { color: #666; cursor: not-allowed; }
        
        .download-container { 
            padding: 16px; 
            text-align: center; 
        }
        .download-btn { 
            width: 100%;
            padding: 12px; 
            background: linear-gradient(45deg, #ff5f6d, #ffc371); 
            border: none; 
            border-radius: 12px; 
            color: #fff; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.3s; 
        }
        .download-btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(255, 95, 109, 0.4); 
        }

        /* *** PERFECT MODAL *** */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; left: 0; 
            width: 100vw; 
            height: 100vh; 
            background: rgba(0, 0, 0, 0.9); 
            z-index: 10000; 
            justify-content: center; 
            align-items: center; 
            padding: 20px;
            backdrop-filter: blur(8px);
        }

        .modal-content { 
            background: #1e1e1e; 
            border-radius: 12px; 
            width: 90vw; 
            max-width: 800px; 
            height: 85vh; 
            max-height: 85vh;
            overflow-y: auto; 
            position: relative; 
            box-shadow: 0 0 50px rgba(185, 28, 28, 0.5);
        }

        .close-btn { 
            position: absolute; 
            top: 15px; 
            right: 15px; 
            background: #b91c1c; 
            border: none; 
            width: 40px; 
            height: 40px;
            border-radius: 50%; 
            color: #fff; 
            font-size: 20px; 
            cursor: pointer; 
            z-index: 10;
            transition: all 0.3s;
        }
        .close-btn:hover { 
            background: #ef4444; 
            transform: scale(1.1); 
        }

        .modal-post .post-image { height: 300px; }
        .modal-post .post-description { min-height: 100px; font-size: 1.1em; padding: 20px; }
        .modal-post .download-btn { width: 250px; margin: 0 auto; }

        /* *** ADMIN FORM *** */
        .admin-form {
            background: #1e1e1e;
            border-radius: 16px;
            padding: 32px;
            max-width: 400px;
            margin: 0 auto 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        .admin-input {
            width: 100%;
            padding: 16px;
            background: #2a2a2a;
            border: 2px solid #333;
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            margin-bottom: 16px;
        }
        .admin-input:focus {
            outline: none;
            border-color: #b91c1c;
            box-shadow: 0 0 10px rgba(185, 28, 28, 0.3);
        }

        /* *** SEARCH BAR *** */
        .search-container {
            position: relative;
            max-width: 400px;
            margin: 0 auto 20px;
        }
        .search-input {
            width: 100%;
            padding: 12px 40px 12px 16px;
            background-color: #1e1e1e;
            border: 1px solid #444;
            border-radius: 50px;
            color: #fff;
            font-size: 1em;
        }
        .search-input:focus {
            outline: none;
            border-color: #b91c1c;
            box-shadow: 0 0 10px rgba(185, 28, 28, 0.3);
        }
        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
            font-size: 1.2em;
        }
        .search-btn:hover { color: #b91c1c; }

        /* *** RESPONSIVE *** */
        @media (max-width: 768px) {
            .posts-grid { grid-template-columns: repeat(2, 1fr); }
            .modal-content { width: 95vw; height: 90vh; }
        }
        @media (max-width: 480px) {
            .posts-grid { grid-template-columns: 1fr; }
            .modal-content { width: 98vw; height: 95vh; }
        }

        /* *** LOADING *** */
        .honeycomb { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 4px; 
            width: 60px; 
            margin: 40px auto; 
        }
        .honeycomb div { 
            width: 12px; height: 12px; 
            background: #b91c1c; 
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%); 
            animation: honeycomb 1s infinite alternate; 
        }
        @keyframes honeycomb { 
            0% { transform: scale(0.8); opacity: 0.5; } 
            100% { transform: scale(1.2); opacity: 1; } 
        }

        /* *** NAV BUTTON *** */
        .Btn {
            width: 140px; height: 40px; 
            border: none; border-radius: 10px; 
            background: linear-gradient(to right,#000000,#777572,#9c9c9c,#ffffff,#c0bfbe,#727272);
            background-size: 250%; background-position: left; 
            color: #ffd277; cursor: pointer; 
            transition-duration: 1s; overflow: hidden;
        }
        .Btn::before {
            content: "กรอกคีย์"; color: #fffffe; 
            display: flex; align-items: center; justify-content: center; 
            width: 97%; height: 90%; border-radius: 8px; 
            background-color: rgba(0, 0, 0, 0.842);
        }
        .Btn:hover { background-position: right; }
    </style>
</head>
<body>
    <!-- NAV -->
    <nav class="fixed w-full z-30 top-0 bg-black bg-opacity-80 p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a class="text-2xl font-bold text-red-700" href="#">Koroz</a>
            <ul class="flex space-x-6">
                <li><a class="hover:text-red-500" href="#about">เกี่ยวกับ</a></li>
                <li><a class="hover:text-red-500" href="#contact">ติดต่อ</a></li>
                <li><span class="<?= $_SESSION['name_color'] ?> font-bold"><?= $_SESSION['display_name'] ?></span></li>
                <?php if ($isAdmin): ?>
                    <li><a href="dashboard.php" class="text-red-500">แดชบอร์ด</a></li>
                <?php else: ?>
                    <li><button class="Btn" onclick="document.querySelector('.admin-form').scrollIntoView()">กรอกคีย์</button></li>
                <?php endif; ?>
                <li><a href="profile.php">โปรไฟล์</a></li>
                <li><a href="logout.php">ออกจากระบบ</a></li>
            </ul>
        </div>
    </nav>
    
    <!-- MAIN -->
    <main class="pt-24 max-w-7xl mx-auto px-6">
        <!-- WELCOME -->
        <section class="py-16 text-center" id="about">
            <h1 class="text-4xl font-bold mb-8">
                ยินดีต้อนรับสู่ Koroz City 
                <span class="text-red-500"><?= $_SESSION['display_name'] ?></span>
            </h1>
            <p class="text-lg mb-8">วันที่: <?= date('d M Y, H:i') ?></p>
            
            <?php if (!$isAdmin): ?>
                <div class="admin-form">
                    <h2 class="text-2xl font-bold mb-6">🔑 กรอกคีย์แอดมิน</h2>
                    <?php if ($error): ?>
                        <div style="background:#b91c1c;color:#fff;padding:12px;border-radius:8px;margin-bottom:16px;"><?= $error ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="password" name="admin_key" placeholder="กรอกคีย์แอดมิน..." 
                               class="admin-input" required autocomplete="off">
                        <button type="submit" class="download-btn w-full">✓ ยืนยันคีย์</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>

        <!-- POSTS -->
        <section class="py-16">
            <h2 class="text-3xl font-bold mb-8 text-center">📝 โพสต์ทั้งหมด</h2>
            <div class="search-container">
                <input type="text" id="searchPosts" class="search-input" placeholder="ค้นหาโพสต์...">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
            <div id="loading" class="honeycomb">
                <div></div><div></div><div></div><div></div><div></div><div></div><div></div>
            </div>
            <div id="postsContainer" class="posts-grid"></div>
        </section>

        <!-- CONTACT -->
        <section class="py-16 text-center" id="contact">
            <h2 class="text-3xl font-bold mb-8">ติดต่อฉัน</h2>
            <div class="flex justify-center space-x-12 text-4xl">
                <a href="https://www.instagram.com/" target="_blank"><i class="fab fa-instagram text-pink-500"></i></a>
                <a href="https://www.facebook.com/" target="_blank"><i class="fab fa-facebook text-blue-500"></i></a>
                <a href="https://discord.com/" target="_blank"><i class="fab fa-discord text-indigo-500"></i></a>
            </div>
        </section>
    </main>

    <!-- MODAL -->
    <div id="modal" class="modal" onclick="closeModal(event)">
        <button class="close-btn" onclick="closeModal()">&times;</button>
        <div class="modal-content" onclick="event.stopPropagation()">
            <div id="modalPostContent"></div>
        </div>
    </div>

<script>
const isLoggedIn = <?= json_encode($_SESSION['loggedin'] ?? false) ?>;
let allPosts = [];

function renderPost(post, isModal = false) {
    const postElem = document.createElement('div');
    postElem.className = `post ${isModal ? 'modal-post' : ''}`;
    
    const postTitle = post.title || 'ไม่มีชื่อ';
    const postDesc = post.description || 'ไม่มีคำอธิบาย';
    const isLiked = post.user_liked == 1;
    const likesCount = post.likes_count || 0;
    
    postElem.innerHTML = `
        <div class="post-header">
            <img src="${post.poster_avatar || 'assets/korox.webp'}" class="post-avatar" alt="Avatar" onerror="this.src='assets/korox.webp'">
            <div>
                <div class="${post.poster_color}" style="font-weight: bold;">${post.poster_name || 'Unknown'}</div>
                <div class="text-sm text-gray-400">${new Date(post.created_at).toLocaleString('th-TH')}</div>
            </div>
        </div>
        
        <div class="px-4 pt-2 pb-1">
            <h3 class="font-bold text-lg text-white border-b border-red-500 pb-1">${postTitle}</h3>
        </div>
        
        <div class="post-description">
            <p>${postDesc}</p>
        </div>
        
        ${post.post_image ? `<img src="${post.post_image}" class="post-image" alt="Post image" onerror="this.style.display='none'">` : ''}
        
        <div class="post-footer">
            <span class="text-red-500 font-semibold">
                <i class="fas fa-heart ${isLiked ? 'text-red-500' : 'text-gray-400'}"></i> ${likesCount}
            </span>
        </div>
        
        <div class="post-actions">
            <div class="action-btn ${!isLoggedIn ? 'disabled' : ''} ${isLiked ? 'liked' : ''}" 
                 data-post-id="${post.id}" 
                 onclick="${!isLoggedIn ? 'window.location.href=\'login.php\'' : 'handleLike(event)'}">
                <i class="fas fa-heart ${isLiked ? 'text-red-500' : 'text-gray-400'}"></i> 
                <span>${likesCount} ไลค์</span>
            </div>
        </div>
        
        <div class="download-container">
            <button class="download-btn" onclick="window.open('${post.link}', '_blank'); event.stopPropagation();">
                <i class="fas fa-download mr-2"></i> Download
            </button>
        </div>
    `;

    if (!isModal) {
        postElem.addEventListener('click', (e) => {
            if (!e.target.closest('.action-btn') && !e.target.closest('.download-btn')) {
                openModal(post);
            }
        });
    }

    return postElem;
}
async function handleLike(event) {
    const btn = event.currentTarget;
    const postId = btn.dataset.postId;
    
    try {
        const response = await fetch('like-post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `post_id=${postId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            // อัปเดตปุ่ม
            btn.classList.toggle('liked', data.liked);
            btn.innerHTML = `
                <i class="fas fa-heart ${data.liked ? 'text-red-500' : 'text-gray-400'}"></i> 
                <span>${data.likes_count} ไลค์</span>
            `;
            
            // อัปเดตจำนวน likes ใน post-footer
            const footer = btn.closest('.post').querySelector('.post-footer span');
            footer.innerHTML = `
                <i class="fas fa-heart ${data.liked ? 'text-red-500' : 'text-gray-400'}"></i> 
                ${data.likes_count}
            `;
        }
    } catch (err) {
        console.error('Like error:', err);
    }
}

function openModal(post) {
    document.getElementById('modalPostContent').innerHTML = '';
    document.getElementById('modalPostContent').appendChild(renderPost(post, true));
    document.getElementById('modal').style.display = 'flex';
}

function closeModal(event) {
    if (event && event.target === event.currentTarget) {
        document.getElementById('modal').style.display = 'none';
    } else {
        document.getElementById('modal').style.display = 'none';
    }
}

function filterPosts() {
    const query = document.getElementById('searchPosts').value.toLowerCase();
    const container = document.getElementById('postsContainer');
    container.innerHTML = '';
    
    const filtered = allPosts.filter(post => 
        post.title.toLowerCase().includes(query) || 
        post.description.toLowerCase().includes(query)
    );
    
    if (filtered.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-16">ไม่พบโพสต์ที่ตรงกัน</p>';
    } else {
        filtered.forEach(post => container.appendChild(renderPost(post)));
    }
}

// LOAD POSTS
document.getElementById('loading').style.display = 'flex';
fetch("fetch_posts.php")
    .then(response => response.json())
    .then(data => {
        document.getElementById('loading').style.display = 'none';
        allPosts = data;
        const container = document.getElementById('postsContainer');
        container.innerHTML = '';
        
        data.forEach(post => {
            container.appendChild(renderPost(post));
        });
    })
    .catch(error => {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('postsContainer').innerHTML = 
            '<p class="text-red-500 text-center py-16 text-xl">โหลดโพสต์ไม่สำเร็จ</p>';
        console.error('Fetch posts error:', error);
    });

// EVENT LISTENERS
document.getElementById('searchPosts').addEventListener('input', filterPosts);
document.querySelector('.search-btn').addEventListener('click', filterPosts);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
});
</script>
</body>
</html>