<?php
/**
 * Run migration: Add user_tasks table
 */

require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

$sql = file_get_contents(__DIR__ . '/sql/migration_add_user_tasks.sql');

if (mysqli_multi_query($conn, $sql)) {
    echo "✓ Migration erfolgreich ausgeführt!\n";
    echo "✓ Tabelle 'user_tasks' wurde erstellt.\n";
    
    // Clear all result sets
    while (mysqli_more_results($conn)) {
        mysqli_next_result($conn);
    }
} else {
    echo "✗ Fehler bei Migration: " . mysqli_error($conn) . "\n";
}

$conn->close();
