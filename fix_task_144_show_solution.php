<?php
/**
 * Fix Task 144 - Set show_solution
 */

require_once __DIR__ . '/config/database.php';

try {
    $conn = getPdoConnection();
    
    $stmt = $conn->prepare("
        UPDATE tasks 
        SET show_solution = 1
        WHERE id = 144
    ");
    
    $stmt->execute();
    
    echo "✓ Task 144 updated: show_solution set to 1\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
