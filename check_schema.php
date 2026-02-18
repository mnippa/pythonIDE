<?php
require 'config/database.php';
$conn = getDbConnection();

// Check which code-related columns exist
$result = $conn->query('DESCRIBE tasks');
echo "Columns containing 'code':\n";
while($row = $result->fetch_assoc()) {
    if(strpos($row['Field'], 'code') !== false) {
        echo "- " . $row['Field'] . "\n";
    }
}

// Check if generator_code exists
$result2 = $conn->query("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tasks' AND COLUMN_NAME = 'generator_code'");
$exists = $result2->fetch_assoc()['cnt'];
echo "\ngenerator_code column exists: " . ($exists ? "YES" : "NO") . "\n";
?>
