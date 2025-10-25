<?php
// fix_paths.php - แก้ path รูปอัตโนมัติ
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    exit;
}

require 'db.php';

$fixed = 0;
$failed = 0;

try {
    $stmt = $pdo->query("SELECT id, image FROM posts WHERE image IS NOT NULL AND image != ''");
    $posts = $stmt->fetchAll();
    
    foreach ($posts as $post) {
        $dbPath = $post['image'];
        $possiblePaths = [
            $dbPath,
            "uploads/{$dbPath}",
            "posts/{$dbPath}",
            "images/{$dbPath}"
        ];
        
        $foundPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $foundPath = $path;
                break;
            }
        }
        
        if ($foundPath && $foundPath !== $dbPath) {
            $stmt_update = $pdo->prepare("UPDATE posts SET image = ? WHERE id = ?");
            if ($stmt_update->execute([$foundPath, $post['id']])) {
                $fixed++;
            } else {
                $failed++;
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['fixed' => $fixed, 'failed' => $failed]);
    
} catch (Exception $e) {
    echo json_encode(['fixed' => 0, 'failed' => 'Error: ' . $e->getMessage()]);
}
?>