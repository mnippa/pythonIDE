<?php
/**
 * Script to assign all assignments to all users
 * Fixed version with proper TRUNCATE
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = getDbConnection();
    
    echo "=== SCHRITT 1: Alle alten Zuweisungen löschen ===\n";
    
    // First, delete all existing assignments
    $db->query("TRUNCATE TABLE user_assignments");
    echo "✓ Alle alten Zuweisungen gelöscht\n\n";
    
    echo "=== SCHRITT 2: Neue Zuweisungen erstellen ===\n";
    
    // Now insert all assignments for all users
    $db->query("
        INSERT INTO user_assignments (user_id, assignment_id, status, assigned_at)
        SELECT u.id, a.id, 'assigned', NOW()
        FROM users u
        CROSS JOIN assignments a
        WHERE u.role = 'user'
          AND a.is_active = TRUE
    ");
    
    echo "✓ Neue Zuweisungen erstellt\n\n";
    
    // Get results
    $stmt = $db->query("
        SELECT COUNT(DISTINCT user_id) as user_count, 
               COUNT(DISTINCT assignment_id) as assignment_count,
               COUNT(*) as total_assignments
        FROM user_assignments
    ");
    $result = $stmt->fetch_assoc();
    
    echo "=== ERGEBNIS ===\n";
    echo "Anzahl Nutzer: " . $result['user_count'] . "\n";
    echo "Anzahl Assignments: " . $result['assignment_count'] . "\n";
    echo "Gesamt Zuweisungen: " . $result['total_assignments'] . "\n";
    echo "\n✓ Alle Assignments wurden erfolgreich allen Nutzern zugewiesen!\n";
    
} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
    exit(1);
}
