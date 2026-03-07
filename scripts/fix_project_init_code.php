<?php
// Direct database connection
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

// Get the REAL Task 170 solution code from DB
$stmt = $conn->prepare('SELECT solution_code FROM tasks WHERE id = 170');
$stmt->execute();
$task170Result = $stmt->get_result();
if ($task170Result->num_rows === 0) {
    die("Task 170 not found!\n");
}
$task170 = $task170Result->fetch_assoc();
$task170Code = $task170['solution_code'];

echo "=== Updating Project 18 with REAL Task 170 Code ===\n";
echo "Code length: " . strlen($task170Code) . " chars\n";
echo "First 300 chars:\n";
echo substr($task170Code, 0, 300) . "\n\n";

// Delete old init.py from project 18
$conn->query("DELETE FROM project_files WHERE project_id = 18 AND name = 'init.py'");

// Insert the REAL code as init.py - use direct query with escaping
$escapedCode = $conn->real_escape_string($task170Code);
$sql = "INSERT INTO project_files (project_id, folder_id, name, content, created_at) VALUES (18, NULL, 'init.py', '$escapedCode', NOW())";

if ($conn->query($sql)) {
    echo "✓ Project 18 init.py updated with REAL Task 170 code\n";
} else {
    echo "✗ Failed to update Project 18: " . $conn->error . "\n";
}

echo "\nDONE\n";
