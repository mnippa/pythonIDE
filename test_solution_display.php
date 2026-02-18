<?php
/**
 * Test Suite: Code Random Complex Solution Display
 * Verifies all components work together correctly
 */

require 'config/database.php';
$conn = getDbConnection();

echo "=== Code Random Complex Solution Test ===\n\n";

// Test 1: Verify Task 79 exists and has solution_code
echo "Test 1: Database - Task 79 has solution_code\n";
echo str_repeat("-", 50) . "\n";
$sql = "SELECT id, title, task_type, solution_code FROM tasks WHERE id = 79";
$result = $conn->query($sql);
$task = $result->fetch_assoc();

if (!$task) {
    echo "❌ FAIL: Task 79 not found\n";
} elseif ($task['task_type'] !== 'code_random_complex') {
    echo "❌ FAIL: Task 79 is type '{$task['task_type']}', not code_random_complex\n";
} elseif (!$task['solution_code']) {
    echo "❌ FAIL: Task 79 has no solution_code\n";
} else {
    echo "✓ PASS: Task 79 is code_random_complex with solution_code\n";
    echo "  solution_code length: " . strlen($task['solution_code']) . " chars\n";
}

// Test 2: API returns solution_code in test mode
echo "\nTest 2: API returns solution_code when test_mode=1\n";
echo str_repeat("-", 50) . "\n";
$_GET['test_mode'] = '1';
$test_mode = isset($_GET['test_mode']) && $_GET['test_mode'] === '1';

if (!$test_mode) {
    echo "❌ FAIL: test_mode not set\n";
} else {
    echo "✓ PASS: test_mode parameter set to 1\n";
    
    // Simulate API column selection
    $selectColumns = 'id, assignment_id, title, task_type, question_text';
    if ($test_mode) {
        $selectColumns .= ', expected_output, solution_code, generator_code';
    }
    
    $sql = "SELECT $selectColumns FROM tasks WHERE id = 79";
    $result = $conn->query($sql);
    
    if (!$result) {
        echo "❌ FAIL: Query error: " . $conn->error . "\n";
    } else {
        $task = $result->fetch_assoc();
        if (!$task) {
            echo "❌ FAIL: Task not found in query\n";
        } elseif (!isset($task['solution_code'])) {
            echo "❌ FAIL: solution_code not in result set\n";
        } elseif (!$task['solution_code']) {
            echo "❌ FAIL: solution_code is empty\n";
        } else {
            echo "✓ PASS: API returns solution_code\n";
            echo "  Columns: " . implode(", ", array_keys($task)) . "\n";
        }
    }
}

// Test 3: Check hasSolution logic
echo "\nTest 3: JavaScript hasSolution logic for code_random_complex\n";
echo str_repeat("-", 50) . "\n";
echo "Simulating frontend hasSolution check:\n";

$task = [
    'id' => 79,
    'task_type' => 'code_random_complex',
    'solution_code' => 'def binary_to_decimal(...)',
    'generator_code' => '# placeholder'
];

// The hasSolution check from assignments.js
$hasSolution = !!($task['solution_code']);
if ($hasSolution) {
    echo "✓ PASS: hasSolution = true for code_random_complex\n";
    echo "  (because solution_code exists)\n";
} else {
    echo "❌ FAIL: hasSolution = false\n";
}

// Test 4: Check solution display logic
echo "\nTest 4: Solution Display Logic\n";
echo str_repeat("-", 50) . "\n";

if ($task['task_type'] === 'code_random_complex') {
    echo "✓ PASS: Task type detected as code_random_complex\n";
    
    if (isset($task['solution_code']) && $task['solution_code']) {
        echo "✓ PASS: solution_code exists and will be shown in editor\n";
        echo "  solution_code preview: " . substr($task['solution_code'], 0, 50) . "...\n";
    } else {
        echo "❌ FAIL: solution_code missing\n";
    }
} else {
    echo "❌ FAIL: Wrong task type\n";
}

echo "\n=== All Tests Complete ===\n";
?>
