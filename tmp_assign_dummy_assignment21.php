<?php

define('BETA_LIVE_ALLOW_WRITE', true);
require __DIR__ . '/config/database.beta_live.local.php';

$userId = 118;
$assignmentId = 21;

$res = runBetaLiveQuery('SELECT id FROM user_assignments WHERE user_id=? AND assignment_id=? LIMIT 1', [$userId,$assignmentId], 'ii', true);
if ($res instanceof mysqli_result && $res->fetch_assoc()) {
    echo "EXISTS\n";
    exit(0);
}

runBetaLiveQuery(
    "INSERT INTO user_assignments (user_id, assignment_id, status, attempts, assigned_at, assigned_by)
     VALUES (?, ?, 'assigned', 0, NOW(), 1)",
    [$userId, $assignmentId],
    'ii',
    true
);

echo "ASSIGNED\n";
