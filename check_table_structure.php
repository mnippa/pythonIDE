<?php
/**
 * Check test_cases table structure
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

// Check table structure
$result = $db->query("DESCRIBE test_cases");

echo "=== TEST_CASES TABLE STRUCTURE ===\n\n";
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} | {$row['Type']} | Null: {$row['Null']} | Default: {$row['Default']}\n";
}

// Check a sample test case
echo "\n=== SAMPLE TEST CASES ===\n\n";
$sample = $db->query("SELECT * FROM test_cases LIMIT 5");
while ($row = $sample->fetch_assoc()) {
    print_r($row);
    echo "\n";
}
