<?php
/**
 * Check tasks table for boolean issues
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

echo "=== TASKS TABLE STRUCTURE ===\n\n";
$result = $db->query("DESCRIBE tasks");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} | {$row['Type']}\n";
}

echo "\n=== SAMPLE TASKS WITH TEST_CASES ===\n\n";
$result = $db->query("
    SELECT t.id, t.assignment_id, t.position, t.title, t.test_cases, a.title as assignment_title
    FROM tasks t
    JOIN assignments a ON t.assignment_id = a.id
    WHERE t.test_cases IS NOT NULL
    ORDER BY t.assignment_id, t.position
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    echo "Task ID: {$row['id']} | Assignment: {$row['assignment_title']} | Pos: {$row['position']}\n";
    echo "Title: {$row['title']}\n";
    
    // Parse and check test cases
    $testCases = json_decode($row['test_cases'], true);
    if ($testCases && is_array($testCases)) {
        foreach ($testCases as $idx => $test) {
            echo "  Test #{$idx}:\n";
            echo "    Type: " . ($test['type'] ?? 'N/A') . "\n";
            
            if (isset($test['expected_output'])) {
                $expected = $test['expected_output'];
                echo "    Expected: {$expected}\n";
                
                // Check for lowercase boolean
                if ($expected === 'true' || $expected === 'false') {
                    echo "    ⚠️ PROBLEM: lowercase boolean detected!\n";
                }
            }
            
            if (isset($test['test_input'])) {
                $input = json_encode($test['test_input']);
                echo "    Input: {$input}\n";
                
                // Check input for lowercase booleans
                foreach ($test['test_input'] as $key => $value) {
                    if ($value === 'true' || $value === 'false') {
                        echo "    ⚠️ PROBLEM: lowercase boolean in input[{$key}]: {$value}\n";
                    }
                }
            }
        }
    }
    echo "\n" . str_repeat("-", 80) . "\n\n";
}
