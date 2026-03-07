<?php
require_once 'c:/xampp/htdocs/pythonIDE/config/database.php';

$conn = getDbConnection();

$projectIds = [10, 11]; // Blackjack, Kniffel

foreach ($projectIds as $projectId) {
    $stmt = $conn->prepare('SELECT name FROM projects WHERE id = ?');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    
    echo "\n=== Project $projectId: " . ($project['name'] ?? 'Unknown') . " ===\n";
    
    $stmt = $conn->prepare('SELECT name FROM project_files WHERE project_id = ? ORDER BY name');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "  No files found\n";
    } else {
        while ($row = $result->fetch_assoc()) {
            echo "  - " . $row['name'] . "\n";
        }
    }
}

$conn->close();
