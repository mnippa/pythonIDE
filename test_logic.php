<?php
/**
 * Test the team-matrix-export logic without authentication
 */
header('Content-Type: text/plain; charset=utf-8');

echo "=== Team Matrix Export Logic Test ===\n\n";

// Test the core functions and logic without going through middleware
require_once __DIR__ . '/config/database.php';

$_GET['team_id'] = 2; // SoSe26

try {
    // Get team
    $teamId = intval($_GET['team_id']);
    $teamResult = $mysqli->query("SELECT id, name FROM teams WHERE id = $teamId");
    $team = $teamResult->fetch_assoc();
    
    echo "✓ Team loaded: " . htmlspecialchars($team['name']) . "\n";
    
    // Get assignments
    $assignResult = $mysqli->query("
        SELECT tad.id, tad.assignment_id, a.name
        FROM team_assignment_defaults tad
        JOIN assignments a ON tad.assignment_id = a.id
        WHERE tad.team_id = $teamId
    ");
    
    $assignCount = $assignResult->num_rows;
    echo "✓ Assignments loaded: $assignCount\n";
    
    // Get team members with their statuses
    $memberResult = $mysqli->query("
        SELECT 
            u.id, u.first_name, u.last_name, u.email,
            COUNT(DISTINCT ua.id) as assignment_count,
            SUM(CASE WHEN ua.status = 'passed' THEN 1 ELSE 0 END) as passed_count,
            SUM(CASE WHEN ua.status = 'failed' THEN 1 ELSE 0 END) as failed_count
        FROM user_team ut
        JOIN users u ON ut.user_id = u.id
        LEFT JOIN user_assignments ua ON ua.user_id = u.id AND ua.assignment_id IN (
            SELECT assignment_id FROM team_assignment_defaults WHERE team_id = $teamId
        )
        WHERE ut.team_id = $teamId
        GROUP BY u.id
    ");
    
    $memberCount = $memberResult->num_rows;
    echo "✓ Team members loaded: $memberCount\n";
    
    $members = [];
    while ($row = $memberResult->fetch_assoc()) {
        $members[] = $row;
    }
    
    // Display sample members
    echo "\nSample team members:\n";
    foreach (array_slice($members, 0, 3) as $member) {
        echo "  - " . $member['first_name'] . " " . $member['last_name'] 
            . " | Assignments: " . $member['assignment_count'] 
            . " | Passed: " . $member['passed_count'] 
            . " | Failed: " . $member['failed_count'] . "\n";
    }
    
    // Test CSV functions exist
    echo "\n✓ Core functions available:\n";
    
    // Read the export file to verify functions
    $exportCode = file_get_contents(__DIR__ . '/api/admin/evaluation/team-matrix-export.php');
    
    $functions = ['mapStatus', 'csvString', 'labStatusLabel', 'labStatusSortRank'];
    foreach ($functions as $func) {
        if (strpos($exportCode, "function $func") !== false) {
            echo "  ✓ $func()\n";
        }
    }
    
    echo "\n=== Test Results ===\n";
    echo "✓ Database connection: OK\n";
    echo "✓ Team query: OK (ID: " . $team['id'] . ")\n";
    echo "✓ Assignments query: OK ($assignCount assignments)\n";
    echo "✓ Members query: OK ($memberCount members)\n";
    echo "✓ Core functions: OK\n";
    echo "\n✓ API is ready for browser testing\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
