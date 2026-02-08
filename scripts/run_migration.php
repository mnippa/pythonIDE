<?php
/**
 * Run migration for project visibility
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$sql = file_get_contents(__DIR__ . '/../sql/migration_visibility.sql');

// Remove comments and USE statement
$lines = explode("\n", $sql);
$filteredLines = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (empty($trimmed) || $trimmed[0] === '-' || stripos($trimmed, 'USE ') === 0) {
        continue;
    }
    $filteredLines[] = $line;
}
$sql = implode("\n", $filteredLines);

// Split and execute
$statements = explode(';', $sql);
$success = 0;
$errors = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement) || preg_match('/^\s*--/', $statement)) {
        continue;
    }
    
    if ($conn->query($statement)) {
        $success++;
        echo "✓ " . substr(str_replace("\n", " ", $statement), 0, 70) . "...\n";
    } else {
        $errors++;
        echo "✗ Error: " . $conn->error . "\n";
    }
}

echo "\n========================================\n";
echo "Migration completed!\n";
echo "Success: $success statements\n";
echo "Errors: $errors statements\n";
echo "========================================\n";
