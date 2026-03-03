<?php
require 'config/database.php';
$pdo = getPdoConnection();

echo "Task #21 Details:\n";
echo "================\n\n";

$stmt = $pdo->prepare('SELECT id, title, folderstructure FROM tasks WHERE id = 21');
$stmt->execute();
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if ($task) {
    echo "Task ID: " . $task['id'] . "\n";
    echo "Title: " . $task['title'] . "\n";
    echo "folderstructure: " . ($task['folderstructure'] ? "✅ AKTIV (1)" : "❌ INAKTIV (0)") . "\n\n";
    
    if (!$task['folderstructure']) {
        echo "🔧 Aktiviere folderstructure...\n";
        $updateStmt = $pdo->prepare('UPDATE tasks SET folderstructure = 1 WHERE id = 21');
        if ($updateStmt->execute()) {
            echo "✅ folderstructure aktiviert!\n";
        }
    }
} else {
    echo "❌ Task nicht gefunden\n";
}

// Check if scaffold files exist
echo "\n📁 Scaffold-Dateien:\n";
$folderPath = __DIR__ . '/storage/tasks/folders/task_21';
$files = ['index.html', 'style.css', 'idegui.py', 'code_ui.template.json'];
foreach ($files as $file) {
    $filePath = $folderPath . '/' . $file;
    if (file_exists($filePath)) {
        $size = filesize($filePath);
        echo "   ✅ " . $file . " (" . $size . " bytes)\n";
    } else {
        echo "   ❌ " . $file . " FEHLT\n";
    }
}
?>


