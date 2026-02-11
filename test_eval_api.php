<?php
session_start();
$_SESSION['user_id'] = 6; // admin user
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/config/database.php';

echo "=== Testing Evaluation Overview API ===\n";
$assignment_id = 7; // Replace with your assignment ID

$_GET['assignment_id'] = $assignment_id;
$_SERVER['REQUEST_METHOD'] = 'GET';

// Simulate the API call
ob_start();
include __DIR__ . '/api/admin/evaluation/overview.php';
$output = ob_get_clean();

echo "API Output:\n";
var_dump(json_decode($output, true));

echo "\n\n=== Testing User Detail API ===\n";
$_GET['assignment_id'] = $assignment_id;
$_GET['user_id'] = 6;

ob_start();
include __DIR__ . '/api/admin/evaluation/user-detail.php';
$output2 = ob_get_clean();

echo "API Output:\n";
var_dump(json_decode($output2, true));
?>
