<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.beta_live.local.php';
require_once __DIR__ . '/../config/database.php';

$conn = getBetaLiveDbConnection();
$local = getDbConnection();

$assignmentId = 38;
$sql = "SELECT id, assignment_id, position, title, task_type, folderstructure, manual_review_required, created_at
        FROM tasks
        WHERE assignment_id = ?
        ORDER BY id DESC
        LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

$stmtLocal = $local->prepare($sql);
$stmtLocal->bind_param('i', $assignmentId);
$stmtLocal->execute();
$resLocal = $stmtLocal->get_result();
$localRows = [];
while ($row = $resLocal->fetch_assoc()) {
    $localRows[] = $row;
}

$betaRoot = 'C:/xampp/htdocs/pythonIDEBeta/storage/tasks/folders';
$liveRoot = 'C:/xampp/htdocs/pythonIDE/storage/tasks/folders';

echo "Assignment #{$assignmentId} latest tasks (DB via live credentials):\n";
echo str_repeat('-', 90) . "\n";

foreach ($rows as $row) {
    $taskId = (int)$row['id'];
    $betaFolder = $betaRoot . '/task_' . $taskId;
    $liveFolder = $liveRoot . '/task_' . $taskId;

    $betaExists = is_dir($betaFolder) ? 'yes' : 'no';
    $liveExists = is_dir($liveFolder) ? 'yes' : 'no';

    $betaFiles = [];
    if (is_dir($betaFolder)) {
        $items = array_values(array_filter(scandir($betaFolder) ?: [], static function (string $name): bool {
            return $name !== '.' && $name !== '..';
        }));
        $betaFiles = $items;
    }

    echo sprintf(
        "id=%d | pos=%s | folder=%s | manual=%s | title=%s\n",
        $taskId,
        (string)$row['position'],
        (string)$row['folderstructure'],
        (string)$row['manual_review_required'],
        (string)$row['title']
    );
    echo "  beta_folder_exists={$betaExists}; live_folder_exists={$liveExists}\n";
    if (!empty($betaFiles)) {
        echo "  beta_folder_files=" . implode(', ', $betaFiles) . "\n";
    }
}

echo "\nAssignment #{$assignmentId} latest tasks (LOCAL DB):\n";
echo str_repeat('-', 90) . "\n";
foreach ($localRows as $row) {
    $taskId = (int)$row['id'];
    $betaFolder = $betaRoot . '/task_' . $taskId;
    $liveFolder = $liveRoot . '/task_' . $taskId;

    $betaExists = is_dir($betaFolder) ? 'yes' : 'no';
    $liveExists = is_dir($liveFolder) ? 'yes' : 'no';

    echo sprintf(
        "id=%d | pos=%s | folder=%s | manual=%s | title=%s\n",
        $taskId,
        (string)$row['position'],
        (string)$row['folderstructure'],
        (string)$row['manual_review_required'],
        (string)$row['title']
    );
    echo "  beta_folder_exists={$betaExists}; live_folder_exists={$liveExists}\n";
}
