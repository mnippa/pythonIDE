<?php
/**
 * Migration: Move validation_mode from tasks table into test_cases JSON
 * 
 * This script:
 * 1. Reads all tasks with test_cases
 * 2. For each test case of type output/function/variable/intelligent, adds validation_mode field
 * 3. Updates the test_cases JSON in the database
 * 4. Drops the validation_mode column from tasks table
 */

require_once __DIR__ . '/../config/database.php';

$mysqli = getDbConnection();
$mysqli->set_charset('utf8mb4');

echo "=== Validation Mode Migration ===\n\n";

// Step 1: Load all tasks with test_cases
$result = $mysqli->query("
    SELECT id, title, task_type, test_cases, validation_mode 
    FROM tasks 
    WHERE test_cases IS NOT NULL 
    AND test_cases != ''
    AND test_cases != '[]'
    AND task_type = 'code'
    ORDER BY id
");

if (!$result) {
    die("Error loading tasks: " . $mysqli->error . "\n");
}

$tasksToUpdate = [];
$taskCount = 0;
$testCaseCount = 0;

while ($row = $result->fetch_assoc()) {
    $taskId = $row['id'];
    $title = $row['title'];
    $taskValidationMode = $row['validation_mode'] ?: 'loose'; // Default to loose
    $testCasesJson = $row['test_cases'];
    
    // Parse test_cases
    $testCases = json_decode($testCasesJson, true);
    
    if (!is_array($testCases) || empty($testCases)) {
        continue;
    }
    
    $modified = false;
    
    // Add validation_mode to each test case if not already present
    foreach ($testCases as $idx => &$testCase) {
        if (!is_array($testCase)) continue;
        
        $type = $testCase['type'] ?? 'output';
        
        // Only add validation_mode for types that use it
        // CODE_CHECK doesn't need validation_mode
        if ($type === 'code_check') {
            continue;
        }
        
        // Check if validation_mode is already set
        if (!isset($testCase['validation_mode'])) {
            $testCase['validation_mode'] = $taskValidationMode;
            $modified = true;
            $testCaseCount++;
        }
    }
    unset($testCase); // Break reference
    
    if ($modified) {
        $tasksToUpdate[] = [
            'id' => $taskId,
            'title' => $title,
            'test_cases' => json_encode($testCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];
        $taskCount++;
    }
}

echo "Found $taskCount tasks with $testCaseCount test cases to migrate\n\n";

// Step 2: Update tasks with new test_cases structure
if (!empty($tasksToUpdate)) {
    $stmt = $mysqli->prepare("UPDATE tasks SET test_cases = ? WHERE id = ?");
    
    foreach ($tasksToUpdate as $task) {
        $stmt->bind_param('si', $task['test_cases'], $task['id']);
        
        if ($stmt->execute()) {
            echo "✓ Task {$task['id']}: {$task['title']}\n";
        } else {
            echo "✗ Task {$task['id']}: {$task['title']} - Error: {$stmt->error}\n";
        }
    }
    
    $stmt->close();
    echo "\n";
}

// Step 3: Verify migration
echo "=== Verification ===\n";
$result = $mysqli->query("
    SELECT id, title, test_cases 
    FROM tasks 
    WHERE test_cases IS NOT NULL 
    AND test_cases != ''
    AND test_cases != '[]'
    AND task_type = 'code'
    ORDER BY id 
    LIMIT 3
");

while ($row = $result->fetch_assoc()) {
    $testCases = json_decode($row['test_cases'], true);
    echo "Task {$row['id']}: {$row['title']}\n";
    
    if (is_array($testCases)) {
        foreach ($testCases as $idx => $tc) {
            $type = $tc['type'] ?? 'output';
            $mode = $tc['validation_mode'] ?? 'NOT SET';
            echo "  - Test Case " . ($idx + 1) . " ({$type}): validation_mode = {$mode}\n";
        }
    }
    echo "\n";
}

// Step 4: Drop validation_mode column
echo "=== Dropping validation_mode column from tasks ===\n";

$dropResult = $mysqli->query("ALTER TABLE tasks DROP COLUMN validation_mode");

if ($dropResult) {
    echo "✓ Column validation_mode dropped successfully\n";
} else {
    echo "✗ Error dropping column: " . $mysqli->error . "\n";
    echo "  (This is OK if column was already dropped)\n";
}

echo "\n=== Migration Complete ===\n";
echo "Summary:\n";
echo "- Tasks updated: $taskCount\n";
echo "- Test cases migrated: $testCaseCount\n";
echo "- validation_mode column removed from tasks table\n";

$mysqli->close();
?>
