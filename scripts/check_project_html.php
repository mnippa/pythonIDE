<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

// Get the HTML from project 22
$stmt = $conn->prepare('SELECT content FROM project_files WHERE project_id = 22 AND name = ?');
$name = 'index.html';
$stmt->bind_param('s', $name);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo "=== Current index.html in Project 22 ===\n\n";
echo $result['content'];
echo "\n\n=== Looking for data-element attributes ===\n";

if (preg_match_all('/data-element="([^"]*)"/', $result['content'], $matches)) {
    echo "Found data-element attributes:\n";
    foreach ($matches[1] as $attr) {
        echo "  - $attr\n";
    }
} else {
    echo "NO data-element attributes found!\n";
}
