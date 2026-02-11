<?php
/**
 * Run migration to create assignment_tasks table
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getPdoConnection();
    
    // Read migration file
    $sql = file_get_contents(__DIR__ . '/../sql/restore_old_structure.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo json_encode([
        'ok' => true,
        'message' => 'Migration executed successfully',
        'table' => 'assignment_tasks created'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Migration failed: ' . $e->getMessage()
    ]);
}
