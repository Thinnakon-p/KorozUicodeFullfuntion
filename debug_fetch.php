<?php
// debug_fetch.php - ทดสอบ JSON output
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("ต้องล็อกอิน");
}

require 'fetch_posts.php'; // เรียก fetch_posts.php ที่สร้างแล้ว
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>DEBUG - Fetch API</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-6">
    <h1 class="text-3xl font-bold text-red-500 mb-6">🔍 DEBUG - Fetch API Output</h1>
    
    <div id="result" class="bg-gray-800 p-4 rounded"></div>
    
    <script>
    fetch('fetch_posts.php')
    .then(res => res.json())
    .then(posts => {
        const result = document.getElementById('result');
        if (posts.length === 0) {
            result.innerHTML = '<p class="text-yellow-500">ไม่มีโพสต์</p>';
            return;
        }
        
        let html = `<h3 class="font-bold mb-3">📋 พบ ${posts.length} โพสต์</h3>`;
        posts.forEach((post, i) => {
            html += `
            <div class="bg-gray-700 p-3 rounded mb-2">
                <h4 class="font-semibold">#${i+1} ${post.title}</h4>
                <p><strong>Avatar:</strong> ${post.poster_avatar}</p>
                <p><strong>Image:</strong> <code>${post.post_image}</code></p>
                <img src="${post.post_image}" class="w-20 h-20 object-cover rounded mt-1" 
                     onerror="this.style.border='2px solid red'">
            </div>
            `;
        });
        result.innerHTML = html;
    })
    .catch(err => {
        document.getElementById('result').innerHTML = `<p class="text-red-500">Error: ${err}</p>`;
    });
    </script>
</body>
</html>