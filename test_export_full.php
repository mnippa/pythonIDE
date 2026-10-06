<?php
// Full API test with database
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Full API Functional Test ===\n\n";

// Set up minimal environment
define('PROD_MODE', false);
define('BASE_DIR', __DIR__);

// Simulate request
$_GET['team_id'] = 2; // SoSe26

// Include config and establish DB connection
try {
    require_once(__DIR__ . '/includes/config.php');
    
    echo "1. Database connection: ";
    if (isset($mysqli) && $mysqli->connect_error === null) {
        echo "OK\n";
    } else {
        echo "FAILED\n";
        exit(1);
    }
    
    // Test the export endpoint logic
    echo "2. Testing team_id=2 export logic:\n";
    
    // Query team
    $teamId = intval($_GET['team_id']);
    $result = $mysqli->query("SELECT id, name FROM teams WHERE id = $teamId");
    
    if ($result && $row = $result->fetch_assoc()) {
        echo "   - Team found: " . htmlspecialchars($row['name']) . " (ID: " . $row['id'] . ")\n";
    } else {
        echo "   - Team NOT found\n";
        exit(1);
    }
    
    // Query assignments for the team
    $assignResult = $mysqli->query("
        SELECT tad.id, tad.assignment_id, a.name 
        FROM team_assignment_defaults tad
        JOIN assignments a ON tad.assignment_id = a.id
        WHERE tad.team_id = $teamId
        LIMIT 5
    ");
    
    echo "   - Assignments count: " . $assignResult->num_rows . "\n";
    while ($aRow = $assignResult->fetch_assoc()) {
        echo "     • Assignment " . $aRow['assignment_id'] . ": " . htmlspecialchars($aRow['name']) . "\n";
    }
    
    // Query team members and their evaluations
    $memberResult = $mysqli->query("
        SELECT u.id, u.first_name, u.last_name, u.email, COUNT(e.id) as eval_count
        FROM user_team ut
        JOIN users u ON ut.user_id = u.id
        LEFT JOIN team_member_lab_evaluations e ON e.user_id = u.id AND e.team_id = ut.team_id
        WHERE ut.team_id = $teamId
        GROUP BY u.id
        LIMIT 3
    ");
    
    echo "   - Team members sample:\n";
    while ($mRow = $memberResult->fetch_assoc()) {
        echo "     • " . htmlspecialchars($mRow['first_name'] . ' ' . $mRow['last_name']) . " (" . $mRow['email'] . ") - evals: " . $mRow['eval_count'] . "\n";
    }
    
    echo "\n3. API endpoint would generate:\n";
    echo "   - 01_uebersicht.csv (overview with status counts)\n";
    echo "   - 02_uebersicht_assignment_details.csv (detailed assignments)\n";
    echo "   - 03_uebersicht_sortiert_status_name.csv (sorted by status, then name)\n";
    echo "   - emails_bestanden.txt (passed students)\n";
    echo "   - emails_nicht_bestanden.txt (failed students)\n";
    echo "   - emails_nachtest.txt (retest students)\n";
    
    echo "\n=== API Ready for Live Testing ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
