<?php
// Direct database connection
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

// Get Task 170 solution code
$stmt = $conn->prepare('SELECT id, title, solution_code FROM tasks WHERE id = 170');
$stmt->execute();
$task170 = $stmt->get_result()->fetch_assoc();

echo "=== Task 170 ===\n";
echo "Title: " . $task170['title'] . "\n";
echo "Solution Code (first 200 chars):\n";
echo substr($task170['solution_code'], 0, 200) . "...\n\n";

// Get Task 172 solution code  
$stmt = $conn->prepare('SELECT id, title, solution_code FROM tasks WHERE id = 172');
$stmt->execute();
$task172 = $stmt->get_result()->fetch_assoc();

echo "=== Task 172 ===\n";
echo "Title: " . $task172['title'] . "\n";
echo "Solution Code (first 200 chars):\n";
echo substr($task172['solution_code'], 0, 200) . "...\n\n";

// Now update projects with the correct files
// First, delete old files from project 18
$projectId = 18;
$conn->query("DELETE FROM project_files WHERE project_id = $projectId");

// Copy Task 170 files to project 18
$files = [
    'index.html' => file_get_contents(__DIR__ . '/../storage/tasks/folders/task_170/index.html'),
    'style.css' => file_get_contents(__DIR__ . '/../storage/tasks/folders/task_170/style.css'),
    'idegui.py' => file_get_contents(__DIR__ . '/../storage/tasks/folders/task_170/idegui.py'),
    'init.py' => $task170['solution_code']
];

$insertStmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, created_at) VALUES (?, ?, ?, ?, NOW())');

foreach ($files as $fileName => $content) {
    $insertStmt->bind_param('iiss', $projectId, $folderIdNull, $fileName, $content);
    $folderIdNull = null;
    $insertStmt->execute();
    echo "✓ Project 18: Created $fileName\n";
}

// Now do the same for project 19 with Task 172
$projectId = 19;
$conn->query("DELETE FROM project_files WHERE project_id = $projectId");

// We need to find Task 172 files directory
$task172Dir = __DIR__ . '/../storage/tasks/folders/task_172';
if (!is_dir($task172Dir)) {
    echo "⚠ Task 172 directory not found at $task172Dir\n";
    echo "Creating with generic Task 170 files instead...\n";
    // Use Task 170 files but with Task 172 code
    $files = [
        'index.html' => file_get_contents(__DIR__ . '/../storage/tasks/folders/task_170/index.html'),
        'style.css' => file_get_contents(__DIR__ . '/../storage/tasks/folders/task_170/style.css'),
        'idegui.py' => file_get_contents(__DIR__ . '/../storage/tasks/folders/task_170/idegui.py'),
        'init.py' => $task172['solution_code']
    ];
} else {
    $files = [
        'index.html' => file_get_contents($task172Dir . '/index.html'),
        'style.css' => file_get_contents($task172Dir . '/style.css'),
        'idegui.py' => file_get_contents($task172Dir . '/idegui.py'),
        'init.py' => $task172['solution_code']
    ];
}

foreach ($files as $fileName => $content) {
    $insertStmt->bind_param('iiss', $projectId, $folderIdNull, $fileName, $content);
    $folderIdNull = null;
    $insertStmt->execute();
    echo "✓ Project 19: Created $fileName\n";
}

echo "\n=== DONE ===\n";
