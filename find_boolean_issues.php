<?php
/**
 * Find test cases with lowercase booleans
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

echo "=== SUCHE NACH TESTFÄLLEN MIT LOWERCASE BOOLEANS ===\n\n";

// Check user_tasks table for test_cases with lowercase true/false
$result = $db->query("
    SELECT id, assignment_id, position, title, test_cases 
    FROM user_tasks 
    WHERE test_cases LIKE '%true%' 
       OR test_cases LIKE '%false%'
    ORDER BY assignment_id, position
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $testCases = $row['test_cases'];
        
        // Check if it contains lowercase true/false (not True/False)
        if (preg_match('/[^A-Z](true|false)[^a-z]/', $testCases)) {
            echo "Task ID: {$row['id']}\n";
            echo "Assignment ID: {$row['assignment_id']}\n";
            echo "Position: {$row['position']}\n";
            echo "Title: {$row['title']}\n";
            echo "Test Cases (first 500 chars):\n";
            echo substr($testCases, 0, 500) . "\n";
            echo str_repeat("=", 80) . "\n\n";
        }
    }
} else {
    echo "Keine Testfälle mit lowercase booleans gefunden.\n";
}

// Also check for the specific assignment mentioned
echo "\n=== ALLE TESTFÄLLE MIT FUNCTIONEN (TYPE: function) ===\n\n";
$result = $db->query("
    SELECT id, assignment_id, position, title, test_cases 
    FROM user_tasks 
    WHERE test_cases LIKE '%\"type\": \"function\"%'
    ORDER BY assignment_id, position
");

if ($result && $result->num_rows > 0) {
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $count++;
        echo "Task ID: {$row['id']} | Assignment: {$row['assignment_id']} | Pos: {$row['position']}\n";
        echo "Title: {$row['title']}\n";
        
        // Parse JSON and check for lowercase booleans
        $testCases = json_decode($row['test_cases'], true);
        if ($testCases) {
            foreach ($testCases as $idx => $test) {
                if (isset($test['expected_output'])) {
                    $expected = $test['expected_output'];
                    if ($expected === 'true' || $expected === 'false') {
                        echo "  ⚠️ Test #{$idx}: expected_output = '{$expected}' (SOLLTE '{$expected}' sein mit Großbuchstaben!)\n";
                    }
                }
            }
        }
        echo "\n";
    }
    echo "Total: {$count} tasks mit function tests\n";
}
