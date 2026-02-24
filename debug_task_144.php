<?php
/**
 * Debug Task 144 - Check structure for code_reading
 */

require_once __DIR__ . '/config/database.php';

try {
    $conn = getPdoConnection();
    
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = 144");
    $stmt->execute();
    $task = $stmt->fetch();
    
    if (!$task) {
        echo "Task 144 not found!\n";
        exit(1);
    }
    
    echo "=== TASK 144 DATA ===\n\n";
    echo "ID: {$task['id']}\n";
    echo "Title: {$task['title']}\n";
    echo "Task Type: {$task['task_type']}\n";
    echo "Assignment ID: {$task['assignment_id']}\n";
    echo "\n--- CODE TEMPLATE ---\n";
    echo $task['code_template'] . "\n";
    
    echo "\n--- SOLUTION CODE ---\n";
    echo ($task['solution_code'] ?: '(NULL)') . "\n";
    
    echo "\n--- VARIABLE OVERRIDES ---\n";
    echo $task['variable_overrides'] . "\n";
    
    echo "\n--- CORRECT ANSWER ---\n";
    echo ($task['correct_answer'] ?: '(NULL)') . "\n";
    
    echo "\n--- ITERATIONS COUNT ---\n";
    echo ($task['iterations_count'] ?: '(NULL)') . "\n";
    
    echo "\n--- MAX ITERATIONS ---\n";
    echo (isset($task['max_iterations']) ? $task['max_iterations'] : '(NULL)') . "\n";
    
    echo "\n--- MAX ATTEMPTS ---\n";
    echo ($task['max_attempts'] ?: '(NULL)') . "\n";
    
    echo "\n--- SHOW SOLUTION ---\n";
    echo ($task['show_solution'] ?: '(NULL)') . "\n";
    
    echo "\n--- SHOW SOLUTION CODE ---\n";
    echo ($task['show_solution_code'] ?: '(NULL)') . "\n";
    
    // Parse and validate variable_overrides
    echo "\n=== VALIDATION ===\n";
    $varOverrides = json_decode($task['variable_overrides'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ ERROR: variable_overrides is not valid JSON: " . json_last_error_msg() . "\n";
    } else {
        echo "✓ variable_overrides is valid JSON\n";
        
        if (is_array($varOverrides)) {
            echo "✓ variable_overrides is an array\n";
            echo "  Array has " . count($varOverrides) . " iteration(s)\n";
            
            foreach ($varOverrides as $idx => $iteration) {
                echo "\n  Iteration $idx:\n";
                if (is_array($iteration)) {
                    foreach ($iteration as $key => $value) {
                        echo "    - $key = " . json_encode($value) . "\n";
                    }
                } else {
                    echo "    ❌ ERROR: Not an object/array\n";
                }
            }
        } else {
            echo "❌ ERROR: variable_overrides is not an array (might be old format)\n";
            echo "  Content: " . json_encode($varOverrides) . "\n";
        }
    }
    
    // Check expected variable name
    echo "\n--- EXPECTED VARIABLE/VALUE ---\n";
    if (!empty($task['correct_answer'])) {
        echo "correct_answer field: {$task['correct_answer']}\n";
        echo "⚠️  WARNING: correct_answer should be NULL for code_reading (use per-iteration expected)\n";
    }
    
    // Check if iterations match
    if (!empty($task['max_iterations'])) {
        $expectedIterations = (int)$task['max_iterations'];
        $actualIterations = is_array($varOverrides) ? count($varOverrides) : 0;
        
        if ($expectedIterations !== $actualIterations) {
            echo "❌ ERROR: max_iterations ($expectedIterations) does not match variable_overrides array length ($actualIterations)\n";
        } else {
            echo "✓ max_iterations matches array length\n";
        }
    }
    
    echo "\n=== EXPECTED STRUCTURE (NEW SCHEMA) ===\n";
    echo "variable_overrides should be:\n";
    echo "[\n";
    echo "  {\n";
    echo "    \"varName1\": value1,\n";
    echo "    \"varName2\": value2,\n";
    echo "    \"_expected\": \"expectedVarName\" OR \"_expectedValue\": directValue\n";
    echo "  },\n";
    echo "  { ... iteration 2 ... }\n";
    echo "]\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
