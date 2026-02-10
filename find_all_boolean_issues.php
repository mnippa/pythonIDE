<?php
/**
 * Find all tasks and check for boolean issues in detail
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

echo "=== ALLE TASKS MIT TESTFÄLLEN ===\n\n";

$result = $db->query("
    SELECT t.id, t.assignment_id, t.position, t.title, t.test_cases, a.title as assignment_title
    FROM tasks t
    JOIN assignments a ON t.assignment_id = a.id
    WHERE t.test_cases IS NOT NULL AND t.test_cases != '[]'
    ORDER BY t.assignment_id, t.position
");

$problematicTasks = [];

while ($row = $result->fetch_assoc()) {
    $testCasesRaw = $row['test_cases'];
    $testCases = json_decode($testCasesRaw, true);
    
    if (!$testCases || !is_array($testCases)) {
        continue;
    }
    
    $hasIssue = false;
    $issues = [];
    
    foreach ($testCases as $idx => $test) {
        // Check expected_output
        if (isset($test['expected_output'])) {
            $expected = $test['expected_output'];
            
            // Check if it's a string containing lowercase boolean
            if (is_string($expected)) {
                if ($expected === 'true' || $expected === 'false') {
                    $hasIssue = true;
                    $issues[] = "Test #{$idx}: expected_output = '{$expected}' (lowercase boolean!)";
                } elseif (preg_match('/\btrue\b|\bfalse\b/', $expected) && 
                         !preg_match('/\bTrue\b|\bFalse\b/', $expected)) {
                    $hasIssue = true;
                    $issues[] = "Test #{$idx}: expected_output contains lowercase 'true' or 'false': {$expected}";
                }
            }
        }
        
        // Check test_input for lowercase booleans
        if (isset($test['test_input']) && is_array($test['test_input'])) {
            foreach ($test['test_input'] as $key => $value) {
                if (is_string($value) && ($value === 'true' || $value === 'false')) {
                    $hasIssue = true;
                    $issues[] = "Test #{$idx}: test_input['{$key}'] = '{$value}' (lowercase boolean!)";
                }
            }
        }
    }
    
    if ($hasIssue) {
        $problematicTasks[] = [
            'id' => $row['id'],
            'assignment' => $row['assignment_title'],
            'title' => $row['title'],
            'position' => $row['position'],
            'issues' => $issues,
            'test_cases' => $testCasesRaw
        ];
    }
}

if (empty($problematicTasks)) {
    echo "✓ Keine Boolean-Probleme gefunden!\n";
} else {
    echo "⚠️ " . count($problematicTasks) . " Tasks mit Boolean-Problemen gefunden:\n\n";
    
    foreach ($problematicTasks as $task) {
        echo "Task ID: {$task['id']} | Assignment: {$task['assignment']} | Pos: {$task['position']}\n";
        echo "Title: {$task['title']}\n";
        echo "Issues:\n";
        foreach ($task['issues'] as $issue) {
            echo "  - {$issue}\n";
        }
        echo "\nTest Cases JSON (first 300 chars):\n";
        echo substr($task['test_cases'], 0, 300) . "...\n";
        echo str_repeat("=", 80) . "\n\n";
    }
}
