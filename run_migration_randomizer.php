<?php
require_once __DIR__ . '/config/database.php';

echo "Starting migration: Add randomizer_code column\n";
echo "==========================================\n\n";

$conn = getDbConnection();

// Read migration file
$migrationFile = __DIR__ . '/sql/migration_add_randomizer_code.sql';
$sql = file_get_contents($migrationFile);

// Parse statements
$lines = explode("\n", $sql);
$cleanedLines = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    // Keep non-empty lines that aren't pure comments
    if (!empty($trimmed) && !preg_match('/^--/', $trimmed)) {
        $cleanedLines[] = $line;
    }
}
$cleanedSql = implode("\n", $cleanedLines);

// Split into individual statements by semicolon
$statements = array_filter(
    array_map('trim', explode(';', $cleanedSql)),
    function($stmt) {
        return !empty($stmt);
    }
);

$success = true;
$executedCount = 0;

foreach ($statements as $statement) {
    $cleanStatement = preg_replace('/--.*$/m', '', $statement);
    $cleanStatement = trim($cleanStatement);
    
    if (empty($cleanStatement)) {
        continue;
    }
    
    echo "Executing: " . substr($cleanStatement, 0, 60) . "...\n";
    
    if ($conn->query($statement)) {
        echo "✓ Success\n";
        $executedCount++;
    } else {
        echo "✗ Error: " . $conn->error . "\n";
        $success = false;
        break;
    }
    echo "\n";
}

echo "==========================================\n";
if ($success) {
    echo "✓ Migration completed successfully!\n";
    echo "  Executed $executedCount statements\n\n";
    
    // Verify the column was added
    $result = $conn->query("SHOW COLUMNS FROM tasks LIKE 'randomizer_code'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Column 'randomizer_code' verified in database\n";
    }
} else {
    echo "✗ Migration failed!\n";
    echo "  Please check the error messages above\n";
}

$conn->close();
