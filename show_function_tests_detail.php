<?php
/**
 * Show all test cases in detail, especially function tests
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

echo "=== ALLE TASKS MIT FUNCTION TESTS (detailliert) ===\n\n";

$result = $db->query("
    SELECT t.id, t.assignment_id, t.position, t.title, t.test_cases, a.title as assignment_title
    FROM tasks t
    JOIN assignments a ON t.assignment_id = a.id
    WHERE t.test_cases LIKE '%\"type\":%\"function\"%'
       OR t.test_cases LIKE '%function_name%'
    ORDER BY t.assignment_id, t.position
");

while ($row = $result->fetch_assoc()) {
    echo "========================================\n";
    echo "Task ID: {$row['id']}\n";
    echo "Assignment: {$row['assignment_title']}\n";
    echo "Position: {$row['position']}\n";
    echo "Title: {$row['title']}\n";
    echo "========================================\n\n";
    
    $testCases = json_decode($row['test_cases'], true);
    
    if ($testCases) {
        foreach ($testCases as $idx => $test) {
            echo "--- Test #{$idx} ---\n";
            echo "Raw JSON:\n";
            echo json_encode($test, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            
            // Check args for boolean issues
            if (isset($test['args']) && is_array($test['args'])) {
                echo "Args analysis:\n";
                foreach ($test['args'] as $argIdx => $arg) {
                    $type = gettype($arg);
                    $value = json_encode($arg);
                    echo "  Arg {$argIdx}: type={$type}, value={$value}";
                    
                    if (is_bool($arg)) {
                        echo " (BOOLEAN - OK!)";
                    } elseif ($arg === 'true' || $arg === 'false') {
                        echo " ⚠️ STRING BOOLEAN DETECTED!";
                    }
                    echo "\n";
                }
            }
            
            // Check expected
            if (isset($test['expected'])) {
                $expected = $test['expected'];
                $type = gettype($expected);
                echo "\nExpected: type={$type}, value=" . json_encode($expected);
                
                if ($expected === 'true' || $expected === 'false') {
                    echo " ⚠️ STRING BOOLEAN DETECTED!";
                } elseif (is_bool($expected)) {
                    echo " (BOOLEAN - OK!)";
                }
                echo "\n";
            }
            
            echo "\n";
        }
    }
    
    echo "\n\n";
}
