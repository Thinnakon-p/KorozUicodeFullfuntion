<?php
require 'db.php';
echo "<h2>DEBUG POSTS</h2>";
$stmt = $pdo->query("SELECT id, title, image FROM posts LIMIT 5");
while ($row = $stmt->fetch()) {
    echo "<div style='border:1px solid #ccc; margin:5px; padding:10px;'>";
    echo "<strong>ID:</strong> {$row['id']}<br>";
    echo "<strong>Title:</strong> {$row['title']}<br>";
    echo "<strong>Image Path:</strong> {$row['image']}<br>";
    echo "<strong>File Exists?</strong> ";
    $exists = file_exists($row['image']) ? "✅ YES" : "❌ NO";
    echo $exists;
    
    // ลอง path อื่น
    $paths = ["uploads/{$row['image']}", "posts/{$row['image']}"];
    foreach ($paths as $p) {
        if (file_exists($p)) echo "<br><strong>{$p}</strong> → ✅ FOUND!";
    }
    echo "</div>";
}
?>