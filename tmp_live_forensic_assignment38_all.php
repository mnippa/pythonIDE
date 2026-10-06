<?php
require_once __DIR__ . '/config/database.beta_live.local.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = getBetaLiveDbConnection();
$conn->set_charset('utf8mb4');

function q(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    if (!$params) {
        $res = $conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

$assignmentId = 38;
$out = [];

$tasks = q($conn, 'SELECT id, title, task_type, folderstructure FROM tasks WHERE assignment_id = ? ORDER BY id', [$assignmentId], 'i');
$out['tasks'] = $tasks;

$folderTaskIds = [];
$codeUiTaskIds = [];
foreach ($tasks as $t) {
    if ((int)$t['folderstructure'] === 1) {
        $folderTaskIds[] = (int)$t['id'];
    }
    if (($t['task_type'] ?? '') === 'code_ui') {
        $codeUiTaskIds[] = (int)$t['id'];
    }
}
$out['folder_task_ids'] = $folderTaskIds;
$out['code_ui_task_ids'] = $codeUiTaskIds;

if ($folderTaskIds) {
    $in = implode(',', array_map('intval', $folderTaskIds));

    $out['ut_counts'] = q($conn,
        "SELECT task_id, COUNT(*) cnt,\n                SUM(CASE WHEN current_code IS NULL OR current_code='' THEN 1 ELSE 0 END) empty_code,\n                SUM(CASE WHEN current_code LIKE '%<html%' OR current_code LIKE '%<!DOCTYPE html%' THEN 1 ELSE 0 END) html_in_current_code,\n                SUM(CASE WHEN current_code LIKE '%import %' OR current_code LIKE '%def %' OR current_code LIKE '%print(%' THEN 1 ELSE 0 END) py_like_in_current_code\n         FROM user_tasks\n         WHERE task_id IN ($in)\n         GROUP BY task_id\n         ORDER BY task_id"
    );

    $out['utf_counts'] = q($conn,
        "SELECT task_id, file_path, COUNT(*) cnt,\n                SUM(CASE WHEN content IS NULL OR content='' THEN 1 ELSE 0 END) empty_content,\n                SUM(CASE WHEN content LIKE '%<html%' OR content LIKE '%<!DOCTYPE html%' THEN 1 ELSE 0 END) html_like,\n                SUM(CASE WHEN content LIKE '%import %' OR content LIKE '%def %' OR content LIKE '%print(%' THEN 1 ELSE 0 END) py_like\n         FROM user_task_files\n         WHERE task_id IN ($in)\n         GROUP BY task_id, file_path\n         ORDER BY task_id, file_path"
    );

    $out['index_or_css_with_python'] = q($conn,
        "SELECT user_id, task_id, file_path, LEFT(content, 200) head\n         FROM user_task_files\n         WHERE task_id IN ($in)\n           AND file_path IN ('index.html','style.css')\n           AND (content LIKE '%import %' OR content LIKE '%def %' OR content LIKE '%print(%')\n         ORDER BY task_id, user_id\n         LIMIT 200"
    );

    $out['current_code_with_html'] = q($conn,
        "SELECT user_id, task_id, LEFT(current_code, 200) head\n         FROM user_tasks\n         WHERE task_id IN ($in)\n           AND (current_code LIKE '%<html%' OR current_code LIKE '%<!DOCTYPE html%')\n         ORDER BY task_id, user_id\n         LIMIT 200"
    );
}

if ($codeUiTaskIds) {
    $inUi = implode(',', array_map('intval', $codeUiTaskIds));
    $out['code_ui_missing_index_override_ratio'] = q($conn,
        "SELECT t.id task_id, t.title,\n                COUNT(DISTINCT ut.user_id) users_with_user_tasks,\n                COUNT(DISTINCT CASE WHEN utf.file_path='index.html' THEN utf.user_id END) users_with_index_override\n         FROM tasks t\n         LEFT JOIN user_tasks ut ON ut.task_id=t.id\n         LEFT JOIN user_task_files utf ON utf.task_id=t.id AND utf.user_id=ut.user_id\n         WHERE t.id IN ($inUi)\n         GROUP BY t.id, t.title\n         ORDER BY t.id"
    );
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
