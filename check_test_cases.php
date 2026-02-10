<?php
/**
 * Check test cases for lowercase true/false
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

// Get all test cases
$result = $db->query("
    SELECT tc.id, tc.assignment_id, a.title, tc.description, tc.test_input, tc.expected_output, tc.test_type
    FROM test_cases tc
    JOIN assignments a ON tc.assignment_id = a.id
    ORDER BY tc.assignment_id, tc.id
");

echo "=== TESTFÄLLE MIT MÖGLICHERWEISE FALSCHEN BOOLEAN-WERTEN ===\n\n";

$found = false;
while ($row = $result->fetch_assoc()) {
    $input = $row['test_input'];
    $output = $row['expected_output'];
    
    // Check for lowercase true/false in JSON or expected output
    if (stripos($input, '"true"') !== false || 
        stripos($input, '"false"') !== false ||
        stripos($input, 'true') !== false || 
        stripos($input, 'false') !== false ||
        stripos($output, 'true') !== false || 
        stripos($output, 'false') !== false) {
        
        $found = true;
        echo "Assignment: {$row['title']}\n";
        echo "Test ID: {$row['id']} | Type: {$row['test_type']}\n";
        echo "Description: {$row['description']}\n";
        echo "Input: {$input}\n";
        echo "Expected: {$output}\n";
        echo str_repeat("-", 80) . "\n\n";
    }
}

if (!$found) {
    echo "Keine Probleme gefunden.\n";
}
