<?php
/**
 * Assign 3 of 5 assignments to all existing non-admin users.
 * If fewer than 3 assignments exist, assign all.
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Fetch up to 5 active assignments
$assignments = [];
$stmt = $conn->prepare('SELECT id, title FROM assignments WHERE is_active = 1 ORDER BY id ASC LIMIT 5');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}

if (count($assignments) === 0) {
    echo "No assignments found. Nothing to assign.\n";
    exit;
}

$assignCount = min(3, count($assignments));

// Fetch all non-admin users
$users = [];
$stmt = $conn->prepare("SELECT id, email FROM users WHERE role = 'user' ORDER BY id ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

if (count($users) === 0) {
    echo "No non-admin users found. Nothing to assign.\n";
    exit;
}

echo "Assigning {$assignCount} assignments to " . count($users) . " users...\n\n";

$insertStmt = $conn->prepare(
    'INSERT IGNORE INTO user_assignments (user_id, assignment_id, status) VALUES (?, ?, ?)'
);

$status = 'assigned';
$assignedTotal = 0;

foreach ($users as $user) {
    $assignedForUser = 0;
    for ($i = 0; $i < $assignCount; $i++) {
        $assignmentId = (int)$assignments[$i]['id'];
        $insertStmt->bind_param('iis', $user['id'], $assignmentId, $status);
        $insertStmt->execute();
        if ($insertStmt->affected_rows > 0) {
            $assignedTotal++;
            $assignedForUser++;
        }
    }
    echo "User {$user['email']} -> {$assignedForUser} assigned\n";
}

echo "\nDone. Total new assignments: {$assignedTotal}\n";
?>
