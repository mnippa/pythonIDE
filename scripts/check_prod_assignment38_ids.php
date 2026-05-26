<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.beta_live.local.php';

$conn = getBetaLiveDbConnection();
$assignmentId = 38;

$stmt = $conn->prepare(
    'SELECT id, assignment_id, position, title, folderstructure, manual_review_required, created_at
     FROM tasks
     WHERE assignment_id = ?
     ORDER BY id DESC
     LIMIT 20'
);
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

echo "PROD DB tasks for assignment {$assignmentId}\n";
echo str_repeat('-', 90) . "\n";

foreach ($rows as $row) {
    $id = (int)$row['id'];
    $expectedFolder = 'task_' . $id;
    echo sprintf(
        "ID=%d | Pos=%d | folder=%d | manual=%d | title=%s | expected_folder=%s\n",
        $id,
        (int)$row['position'],
        (int)$row['folderstructure'],
        (int)$row['manual_review_required'],
        (string)$row['title'],
        $expectedFolder
    );
}
