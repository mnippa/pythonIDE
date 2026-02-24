<?php
/**
 * Fix Task 144 - Code Reading Task
 */

require_once __DIR__ . '/config/database.php';

try {
    $conn = getPdoConnection();
    
    // Corrected code template with proper Python syntax
    $codeTemplate = "summe = 1\n\nfor n in range({a}, {b}):\n    summe = summe + n * summe";
    
    // For code_reading, solution_code should be NULL (not used)
    $solutionCode = null;
    
    // correct_answer should be NULL for code_reading (use per-iteration expected)
    $correctAnswer = null;
    
    $stmt = $conn->prepare("
        UPDATE tasks 
        SET 
            code_template = :code_template,
            solution_code = :solution_code,
            correct_answer = :correct_answer
        WHERE id = 144
    ");
    
    $stmt->execute([
        ':code_template' => $codeTemplate,
        ':solution_code' => $solutionCode,
        ':correct_answer' => $correctAnswer
    ]);
    
    echo "✓ Task 144 updated successfully!\n\n";
    echo "Changes:\n";
    echo "- Fixed Python syntax: range[...] → range(...)\n";
    echo "- Set solution_code to NULL (not needed for code_reading)\n";
    echo "- Set correct_answer to NULL (using per-iteration _expected)\n";
    echo "\nCode Template:\n";
    echo $codeTemplate . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
