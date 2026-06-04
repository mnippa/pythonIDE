<?php

define('BETA_LIVE_ALLOW_WRITE', true);
require_once __DIR__ . '/config/database.beta_live.local.php';

$conn = getBetaLiveDbConnection();

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function ensureTaskTypeEnumContains(mysqli $conn, string $value): void {
    $res = $conn->query("SHOW COLUMNS FROM tasks LIKE 'task_type'");
    if (!$res instanceof mysqli_result || $res->num_rows === 0) {
        throw new RuntimeException('tasks.task_type column not found');
    }
    $row = $res->fetch_assoc();
    $type = (string)($row['Type'] ?? '');
    if (stripos($type, "'{$value}'") !== false) {
        return;
    }

    $values = [];
    if (preg_match_all("/'([^']+)'/", $type, $m)) {
        $values = $m[1];
    }
    if (!in_array($value, $values, true)) {
        $values[] = $value;
    }
    $quoted = array_map(static fn($v) => "'" . $conn->real_escape_string($v) . "'", $values);
    $enum = 'enum(' . implode(',', $quoted) . ')';

    $sql = "ALTER TABLE tasks MODIFY task_type {$enum} NOT NULL DEFAULT 'code'";
    runBetaLiveQuery($sql, [], '', true);
}

ensureTaskTypeEnumContains($conn, 'file_submission');

if (!hasColumn($conn, 'tasks', 'file_submission_allowed_types')) {
    runBetaLiveQuery("ALTER TABLE tasks ADD COLUMN file_submission_allowed_types VARCHAR(255) NULL AFTER randomizer_code", [], '', true);
}
if (!hasColumn($conn, 'tasks', 'file_submission_max_size_bytes')) {
    runBetaLiveQuery("ALTER TABLE tasks ADD COLUMN file_submission_max_size_bytes INT NULL AFTER file_submission_allowed_types", [], '', true);
}

$assignmentId = 21;
$title = 'ZZ Dateiabgabe Testtask (Prod)';

$dupStmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? ORDER BY id DESC LIMIT 1');
$dupStmt->bind_param('is', $assignmentId, $title);
$dupStmt->execute();
$dupRes = $dupStmt->get_result();
if ($dupRes instanceof mysqli_result && ($dup = $dupRes->fetch_assoc())) {
    echo 'EXISTS task_id=' . (int)$dup['id'] . PHP_EOL;
    exit(0);
}

$posRes = runBetaLiveQuery('SELECT COALESCE(MAX(position), 0) AS max_pos FROM tasks WHERE assignment_id = ?', [$assignmentId], 'i', true);
$maxPos = 0;
if ($posRes instanceof mysqli_result) {
    $row = $posRes->fetch_assoc();
    $maxPos = (int)($row['max_pos'] ?? 0);
}
$position = $maxPos + 1;

$insertSql = "INSERT INTO tasks (
    assignment_id, title, description, task_text, position, task_type, task_difficulty,
    folderstructure, allowDownload, allow_code_ui_web_edit,
    file_submission_allowed_types, file_submission_max_size_bytes,
    max_attempts, show_solution, show_solution_code, manual_review_required,
    created_at, updated_at
) VALUES (
    ?, ?, ?, ?, ?, 'file_submission', 'medium',
    0, 0, 0,
    ?, ?,
    1, 0, 0, 1,
    NOW(), NOW()
)";

$desc = 'Temporärer Testtask für Dateiabgabe auf Prod.';
$text = 'Bitte lade eine ZIP-Datei hoch und gib die Aufgabe ab.';
$types = 'zip,png';
$maxSize = 1048576;

$stmt = $conn->prepare($insertSql);
if (!$stmt) {
    throw new RuntimeException('Prepare failed: ' . $conn->error);
}
$stmt->bind_param('isssisi', $assignmentId, $title, $desc, $text, $position, $types, $maxSize);
if (!$stmt->execute()) {
    throw new RuntimeException('Insert failed: ' . $stmt->error);
}

$newId = (int)$conn->insert_id;
echo 'CREATED task_id=' . $newId . ' assignment_id=' . $assignmentId . ' position=' . $position . PHP_EOL;
