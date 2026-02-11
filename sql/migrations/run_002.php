<?php
/**
 * Execute Migration 002: Update Teams
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    
    echo "Starting migration 002: Update Teams...\n";
    
    // Update existing teams
    echo "Updating team names...\n";
    $conn->query("UPDATE teams SET name = 'WiSe 25/26', description = 'Wintersemester 2025/2026' WHERE id = 1");
    $conn->query("UPDATE teams SET name = 'SoSe 26', description = 'Sommersemester 2026' WHERE id = 2");
    $conn->query("UPDATE teams SET name = 'SoSe 27', description = 'Sommersemester 2027' WHERE id = 3");
    
    // Add SoSe 28
    echo "Adding SoSe 28...\n";
    $conn->query("INSERT INTO teams (name, description, is_active) VALUES ('SoSe 28', 'Sommersemester 2028', 1) ON DUPLICATE KEY UPDATE name=name");
    
    // Assign all existing users to WiSe 25/26
    echo "Assigning existing users to WiSe 25/26...\n";
    $result = $conn->query("UPDATE users SET team_id = 1 WHERE team_id IS NULL");
    echo "Assigned {$result->affected_rows} users\n";
    
    echo "\n✅ Migration 002 completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
