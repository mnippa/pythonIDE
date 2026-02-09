<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

echo "Lösche alte user_tasks Tabelle...\n";
$conn->query("DROP TABLE IF EXISTS user_tasks");

echo "Erstelle neue user_tasks Tabelle...\n";
$sql = file_get_contents(__DIR__ . '/sql/migration_add_user_tasks.sql');

if (mysqli_multi_query($conn, $sql)) {
    echo "✓ Migration erfolgreich!\n";
    echo "✓ Neue Tabelle 'user_tasks' wurde erstellt.\n\n";
    
    // Clear all result sets
    while (mysqli_more_results($conn)) {
        mysqli_next_result($conn);
    }
    
    // Show new structure
    $result = $conn->query("DESCRIBE user_tasks");
    echo "Neue Struktur:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  ✓ {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "✗ Fehler: " . mysqli_error($conn) . "\n";
}

$conn->close();
