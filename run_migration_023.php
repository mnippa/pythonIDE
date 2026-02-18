<?php
/**
 * Run migration 023
 */
require 'config/database.php';

$conn = getDbConnection();
$sql = file_get_contents('sql/migrations/023_add_generator_code_column.sql');

$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($s) { return !empty($s) && strpos($s, '--') !== 0; }
);

foreach ($statements as $stmt) {
    echo "Executing: " . substr($stmt, 0, 60) . "...\n";
    if ($conn->query($stmt)) {
        echo "✓ OK\n";
    } else {
        die("❌ Error: " . $conn->error . "\n");
    }
}

echo "\n✅ Migration completed!\n";
?>
