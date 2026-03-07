<?php
// Direct database connection
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

// Get user Markus2
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$email = 'markus2@example.com';
$stmt->bind_param('s', $email);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows === 0) {
    die("User Markus2 not found!\n");
}

$user = $userResult->fetch_assoc();
$userId = $user['id'];

echo "Using User ID: $userId\n\n";

// Get Task 172 real code from DB
echo "=== Reading Task 172 from database ===\n";
$stmt = $conn->prepare('SELECT id, title, solution_code FROM tasks WHERE id = 172');
$stmt->execute();
$task172Result = $stmt->get_result();

if ($task172Result->num_rows === 0) {
    die("Task 172 not found in database!\n");
}

$task172 = $task172Result->fetch_assoc();
$task172Code = $task172['solution_code'];
echo "✓ Task 172 title: " . $task172['title'] . "\n";
echo "✓ Task 172 code retrieved (" . strlen($task172Code) . " chars)\n\n";

// Read task files from filesystem
echo "=== Reading Task 172 files from filesystem ===\n";
$task172Dir = __DIR__ . '/../storage/tasks/folders/task_172';

if (!is_dir($task172Dir)) {
    echo "⚠ Task 172 directory not found at $task172Dir\n";
    echo "Using Task 170 files as template...\n";
    $task172Dir = __DIR__ . '/../storage/tasks/folders/task_170';
}

if (!is_dir($task172Dir)) {
    die("No task directory found!\n");
}

$indexHtml = file_get_contents($task172Dir . '/index.html');
$styleCss = file_get_contents($task172Dir . '/style.css');
$ideguiPy = file_get_contents($task172Dir . '/idegui.py');

echo "✓ index.html (" . strlen($indexHtml) . " chars)\n";
echo "✓ style.css (" . strlen($styleCss) . " chars)\n";
echo "✓ idegui.py (" . strlen($ideguiPy) . " chars)\n\n";

// Delete old project if exists
echo "=== Cleaning up old Task 172 projects ===\n";
$conn->query("DELETE FROM project_files WHERE project_id IN (SELECT id FROM projects WHERE user_id = $userId AND name LIKE 'Task 172%')");
$conn->query("DELETE FROM projects WHERE user_id = $userId AND name LIKE 'Task 172%'");
echo "✓ Old projects deleted\n\n";

// Create Project: Task 172
echo "=== Creating Project: Task 172 ===\n";
$projectName = 'Task 172 - Taschenrechner (Run-Logik mit Trigger-Dispatch)';
$projectDesc = 'Taschenrechner mit Trigger-Dispatch und Run-Logik';
$projectType = 'html';

$insertProjectStmt = $conn->prepare('INSERT INTO projects (user_id, name, description, project_type, created_at) VALUES (?, ?, ?, ?, NOW())');
$insertProjectStmt->bind_param('isss', $userId, $projectName, $projectDesc, $projectType);

if ($insertProjectStmt->execute()) {
    $projectId = $conn->insert_id;
    echo "✓ Project created (ID: $projectId)\n";
} else {
    die("Failed to create project: " . $conn->error . "\n");
}

// Insert files into project
echo "\nInserting files into project $projectId...\n";

$files = [
    'index.html' => $indexHtml,
    'style.css' => $styleCss,
    'idegui.py' => $ideguiPy,
    'init.py' => $task172Code
];

foreach ($files as $fileName => $content) {
    $escapedContent = $conn->real_escape_string($content);
    $sql = "INSERT INTO project_files (project_id, folder_id, name, content, created_at) VALUES ($projectId, NULL, '$fileName', '$escapedContent', NOW())";
    
    if ($conn->query($sql)) {
        echo "✓ $fileName (" . strlen($content) . " chars)\n";
    } else {
        echo "✗ Failed to insert $fileName: " . $conn->error . "\n";
    }
}

echo "\n=== DONE ===\n";
echo "Project created successfully with Task 172 code and files!\n";
echo "Project ID: $projectId\n";
echo "User: Markus2 (ID: $userId)\n";
