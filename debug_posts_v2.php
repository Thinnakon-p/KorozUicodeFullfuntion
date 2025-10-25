<?php
// debug_posts_v2.php - VER 2.0
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) die("ต้องล็อกอิน");
require 'db.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>DEBUG V2 - รูปโพสต์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body {background:#1a1a1a;color:white;font-family:'Kanit',sans-serif;}</style>
</head>
<body class="p-6">
    <h1 class="text-3xl font-bold text-red-500 mb-6">🔍 DEBUG V2 - รูปโพสต์</h1>
    
    <?php
    $stmt = $pdo->query("SELECT id, title, image FROM posts ORDER BY id");
    $posts = $stmt->fetchAll();
    
    echo "<div class='grid md:grid-cols-2 lg:grid-cols-3 gap-4'>";
    if (empty($posts)) {
        echo "<div class='text-center py-8 text-yellow-500'>ไม่มีโพสต์</div>";
    } else {
        foreach ($posts as $post) {
            $dbPath = $post['image'];
            $cleanPath = str_replace('uploads/', '', $dbPath);
            $finalPath = file_exists($cleanPath) ? $cleanPath : (file_exists($dbPath) ? $dbPath : 'assets/default-post-image.png');
            
            echo "<div class='bg-gray-800 p-4 rounded'>";
            echo "<h3 class='font-bold mb-2'>ID: {$post['id']} - {$post['title']}</h3>";
            echo "<p><strong>DB:</strong> <code class='text-blue-400'>{$dbPath}</code></p>";
            echo "<p><strong>Final:</strong> <code class='text-green-400'>{$finalPath}</code></p>";
            echo "<img src='{$finalPath}' class='w-full h-32 object-cover rounded mt-2' 
                     onerror='this.src=\"assets/default-post-image.png\";this.style.border=\"2px solid red\"'>";
            echo "</div>";
        }
    }
    echo "</div>";
    
    // สรุป
    $working = 0;
    foreach ($posts as $p) {
        $clean = str_replace('uploads/', '', $p['image']);
        if (file_exists($clean) || file_exists($p['image'])) $working++;
    }
    $successRate = count($posts) ? round(($working/count($posts))*100) : 0;
    
    echo "<div class='mt-8 p-4 bg-green-900 rounded-lg'>";
    echo "<h2 class='text-xl font-bold mb-2'>📊 สรุป:</h2>";
    echo "<p>โพสต์ทั้งหมด: <strong>" . count($posts) . "</strong></p>";
    echo "<p>รูปแสดงได้: <strong>{$working} ({$successRate}%)</strong></p>";
    echo "<p class='text-lg mt-2'>🎉 <strong>READY!</strong> คลิกปุ่มด้านล่าง</p>";
    echo "</div>";
    ?>

    <div class="mt-6 p-4 bg-blue-900 rounded-lg">
        <button onclick="fixAll()" class="bg-green-600 px-6 py-3 rounded text-white text-lg">🚀 แก้ไขทั้งหมด + รีโหลด Dashboard</button>
    </div>

    <script>
    function fixAll() {
        if (!confirm('แก้ไข path ทั้งหมด + สร้าง default images?')) return;
        
        fetch('fix_all_paths.php')
        .then(res => res.json())
        .then(data => {
            alert(`✅ เสร็จ!\n${data.message}\n\n🔄 กำลังรีโหลด Dashboard...`);
            window.location.href = 'dashboard.php';
        })
        .catch(err => alert('❌ Error: ' + err));
    }
    </script>
</body>
</html>