<?php
/**
 * Check Quadrat tasks in detail
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

echo "=== ALLE QUADRAT-TASKS ===\n\n";

$result = $db->query("
    SELECT t.id, t.assignment_id, t.position, t.title, t.test_cases, t.validation_mode, a.title as assignment_title
    FROM tasks t
    JOIN assignments a ON t.assignment_id = a.id
    WHERE t.title LIKE '%Quadrat%'
    ORDER BY t.assignment_id, t.position
");

while ($row = $result->fetch_assoc()) {
    echo str_repeat("=", 80) . "\n";
    echo "Task ID: {$row['id']}\n";
    echo "Assignment: {$row['assignment_title']}\n";
    echo "Position: {$row['position']}\n";
    echo "Title: {$row['title']}\n";
    echo "Validation Mode: {$row['validation_mode']}\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $testCases = json_decode($row['test_cases'], true);
    
    if ($testCases) {
        echo "Test Cases:\n";
        foreach ($testCases as $idx => $test) {
            echo "\n--- Test #{$idx} ---\n";
            echo json_encode($test, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            
            // Check expected value type
            if (isset($test['expected'])) {
                $expected = $test['expected'];
                $type = gettype($expected);
                echo "\nExpected Analysis:\n";
                echo "  Type: {$type}\n";
                echo "  Value: " . var_export($expected, true) . "\n";
                echo "  JSON: " . json_encode($expected) . "\n";
                
                if (is_int($expected)) {
                    echo "  → Integer (OK for numeric comparison)\n";
                } elseif (is_string($expected)) {
                    echo "  → String (may need exact match)\n";
                    if (is_numeric($expected)) {
                        echo "  → But it's numeric string: '{$expected}'\n";
                    }
                }
            }
        }
    }
    
    echo "\n\n";
}
