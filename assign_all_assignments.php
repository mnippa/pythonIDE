<?php
/**
 * Script to assign all assignments to all users
 * Executes: sql/assign_all_to_all.sql
 */

require_once __DIR__ . '/config/database.php';

try {
    // Read the SQL file
    $sqlFile = __DIR__ . '/sql/assign_all_to_all.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Get database connection
    $db = getDbConnection();
    
    // Split SQL file into individual statements (separated by semicolons)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Filter out empty statements and comments
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   !preg_match('/^USE/', $stmt);
        }
    );
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            if ($db->query($statement)) {
                echo "✓ Statement executed successfully\n";
            } else {
                echo "✗ Error executing statement: " . $db->error . "\n";
                echo "   Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    // Get results
    $stmt = $db->query("
        SELECT COUNT(DISTINCT user_id) as user_count, 
               COUNT(DISTINCT assignment_id) as assignment_count,
               COUNT(*) as total_assignments
        FROM user_assignments
    ");
    $result = $stmt->fetch_assoc();
    
    echo "\n=== ERGEBNIS ===\n";
    echo "Anzahl Nutzer: " . $result['user_count'] . "\n";
    echo "Anzahl Assignments: " . $result['assignment_count'] . "\n";
    echo "Gesamt Zuweisungen: " . $result['total_assignments'] . "\n";
    echo "\n✓ Alle Assignments wurden erfolgreich allen Nutzern zugewiesen!\n";
    
} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
    exit(1);
}
