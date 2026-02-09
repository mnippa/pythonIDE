<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "=== Alle Task Test Cases ===\n\n";

$result = $conn->query("
    SELECT id, title, test_cases, validation_mode, max_attempts
    FROM tasks 
    WHERE test_cases IS NOT NULL
    ORDER BY id
");

while ($row = $result->fetch_assoc()) {
    echo "Task {$row['id']}: {$row['title']}\n";
    echo "Validation Mode: {$row['validation_mode']}\n";
    echo "Max Attempts: {$row['max_attempts']}\n";
    
    $testCases = json_decode($row['test_cases'], true);
    if ($testCases && is_array($testCases)) {
        echo "Test Cases (" . count($testCases) . "):\n";
        foreach ($testCases as $idx => $tc) {
            $input = isset($tc['input']) ? $tc['input'] : 'N/A';
            $expected = isset($tc['expected']) ? $tc['expected'] : 'N/A';
            echo "  Test " . ($idx + 1) . ":\n";
            echo "    Input: " . (empty($input) ? '(empty)' : $input) . "\n";
            echo "    Expected: '" . $expected . "'\n";
        }
        
        // Check for duplicate/conflicting tests
        if (count($testCases) > 1) {
            $expectedValues = array_map(function($tc) {
                return isset($tc['expected']) ? $tc['expected'] : '';
            }, $testCases);
            
            if (count(array_unique($expectedValues)) < count($expectedValues)) {
                echo "  ⚠️  WARNING: Duplicate expected values!\n";
            } else {
                echo "  ⚠️  NOTE: Different expected values - can't pass all tests with single output!\n";
            }
        }
    } else {
        echo "No valid test cases\n";
    }
    echo "\n" . str_repeat('-', 80) . "\n\n";
}

$conn->close();
