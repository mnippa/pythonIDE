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

// Clean up old projects
echo "=== Cleaning up old projects ===\n";
$conn->query("DELETE FROM project_files WHERE project_id IN (SELECT id FROM projects WHERE user_id = $userId AND name LIKE 'Task 17%')");
$conn->query("DELETE FROM projects WHERE user_id = $userId AND name LIKE 'Task 17%'");
echo "✓ Old projects deleted\n\n";

// Get Task 170 real code from DB
echo "=== Reading Task 170 from database ===\n";
$stmt = $conn->prepare('SELECT solution_code FROM tasks WHERE id = 170');
$stmt->execute();
$task170Result = $stmt->get_result();
$task170 = $task170Result->fetch_assoc();
$task170Code = $task170['solution_code'];
echo "✓ Task 170 code retrieved (" . strlen($task170Code) . " chars)\n\n";

// Read task files from filesystem
echo "=== Reading Task 170 files from filesystem ===\n";
$task170Dir = __DIR__ . '/../storage/tasks/folders/task_170';

if (!is_dir($task170Dir)) {
    die("Task 170 directory not found at $task170Dir\n");
}

$indexHtml = file_get_contents($task170Dir . '/index.html');
$styleCss = file_get_contents($task170Dir . '/style.css');
$ideguiPy = file_get_contents($task170Dir . '/idegui.py');

echo "✓ index.html (" . strlen($indexHtml) . " chars)\n";
echo "✓ style.css (" . strlen($styleCss) . " chars)\n";
echo "✓ idegui.py (" . strlen($ideguiPy) . " chars)\n\n";

// Create Project 18: Task 170
echo "=== Creating Project 18: Task 170 ===\n";
$projectName = 'Task 170 - Taschenrechner mit Event-Funktionen';
$projectDesc = 'Taschenrechner mit Button-Triggern';
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
    'init.py' => $task170Code
];

foreach ($files as $fileName => $content) {
    $escapedContent = $conn->real_escape_string($content);
    $sql = "INSERT INTO project_files (project_id, folder_id, name, content, created_at) VALUES ($projectId, NULL, '$fileName', '$escapedContent', NOW())";
    
    if ($conn->query($sql)) {
        echo "✓ $fileName\n";
    } else {
        echo "✗ Failed to insert $fileName: " . $conn->error . "\n";
    }
}

echo "\n=== DONE ===\n";
echo "Project 18 created successfully with real Task 170 code and files!\n";
