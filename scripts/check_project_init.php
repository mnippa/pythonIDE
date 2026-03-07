<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

// Get the init.py from project 22
$stmt = $conn->prepare('SELECT content FROM project_files WHERE project_id = 22 AND name = ?');
$name = 'init.py';
$stmt->bind_param('s', $name);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo "=== Current init.py in Project 22 ===\n\n";
echo $result['content'];
