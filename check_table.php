<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

// Check if user_tasks table exists
$result = $conn->query("SHOW TABLES LIKE 'user_tasks'");
if ($result->num_rows > 0) {
    echo "✓ Tabelle 'user_tasks' existiert\n\n";
    
    // Show table structure
    $result = $conn->query("DESCRIBE user_tasks");
    echo "Struktur:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Key']}\n";
    }
    
    // Count entries
    $result = $conn->query("SELECT COUNT(*) as count FROM user_tasks");
    $count = $result->fetch_assoc()['count'];
    echo "\nAnzahl Einträge: $count\n";
} else {
    echo "✗ Tabelle 'user_tasks' existiert NICHT!\n";
}

$conn->close();
