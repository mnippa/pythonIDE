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

$out = [];

$task402 = q($conn, 'SELECT id, assignment_id, title, task_type, folderstructure, LEFT(code_template, 160) AS tpl_head FROM tasks WHERE id = 402');
$out['task_402'] = $task402;

$assignmentId = isset($task402[0]['assignment_id']) ? (int)$task402[0]['assignment_id'] : 0;
$out['assignment_id'] = $assignmentId;

if ($assignmentId > 0) {
    $tasks = q($conn, 'SELECT id, title, task_type, folderstructure FROM tasks WHERE assignment_id = ? ORDER BY id', [$assignmentId], 'i');
    $out['tasks_in_assignment'] = $tasks;

    $expTasks = [];
    foreach ($tasks as $t) {
        $title = mb_strtolower((string)($t['title'] ?? ''), 'UTF-8');
        if (mb_strpos($title, 'exp', 0, 'UTF-8') !== false) {
            $expTasks[] = $t;
        }
    }
    $out['exp_tasks'] = $expTasks;
}

$targetTaskIds = [402];
if (!empty($out['exp_tasks'])) {
    foreach ($out['exp_tasks'] as $t) {
        $targetTaskIds[] = (int)$t['id'];
    }
}
$targetTaskIds = array_values(array_unique(array_filter($targetTaskIds)));
$out['target_task_ids'] = $targetTaskIds;

if ($targetTaskIds) {
    $in = implode(',', array_map('intval', $targetTaskIds));

    $out['user_tasks_summary'] = q(
        $conn,
        "SELECT task_id, COUNT(*) AS cnt,\n                SUM(CASE WHEN current_code IS NULL OR current_code = '' THEN 1 ELSE 0 END) AS empty_code_cnt,\n                SUM(CASE WHEN current_code LIKE '%<html%' OR current_code LIKE '%<!DOCTYPE html%' THEN 1 ELSE 0 END) AS html_in_current_code_cnt,\n                SUM(CASE WHEN current_code LIKE '%def %' OR current_code LIKE '%import %' OR current_code LIKE '%print(%' THEN 1 ELSE 0 END) AS py_like_in_current_code_cnt\n         FROM user_tasks\n         WHERE task_id IN ($in)\n         GROUP BY task_id\n         ORDER BY task_id"
    );

    $out['user_task_files_summary'] = q(
        $conn,
        "SELECT task_id, file_path, COUNT(*) AS cnt,\n                SUM(CASE WHEN content IS NULL OR content = '' THEN 1 ELSE 0 END) AS empty_cnt,\n                SUM(CASE WHEN content LIKE '%import %' OR content LIKE '%def %' OR content LIKE '%print(%' THEN 1 ELSE 0 END) AS py_like_cnt,\n                SUM(CASE WHEN content LIKE '%<html%' OR content LIKE '%<!DOCTYPE html%' THEN 1 ELSE 0 END) AS html_like_cnt\n         FROM user_task_files\n         WHERE task_id IN ($in)\n         GROUP BY task_id, file_path\n         ORDER BY task_id, file_path"
    );

    $out['suspicious_index_py'] = q(
        $conn,
        "SELECT utf.user_id, utf.task_id, utf.file_path, LEFT(utf.content, 180) AS head\n         FROM user_task_files utf\n         WHERE utf.task_id IN ($in)\n           AND utf.file_path IN ('index.html', 'style.css')\n           AND (utf.content LIKE '%import %' OR utf.content LIKE '%def %' OR utf.content LIKE '%print(%')\n         ORDER BY utf.task_id, utf.user_id\n         LIMIT 80"
    );

    $out['suspicious_current_code_html'] = q(
        $conn,
        "SELECT ut.user_id, ut.task_id, LEFT(ut.current_code, 180) AS head\n         FROM user_tasks ut\n         WHERE ut.task_id IN ($in)\n           AND (ut.current_code LIKE '%<html%' OR ut.current_code LIKE '%<!DOCTYPE html%')\n         ORDER BY ut.task_id, ut.user_id\n         LIMIT 80"
    );

    $out['ua_presence'] = q(
        $conn,
        "SELECT ut.task_id, COUNT(*) AS rows_cnt,\n                SUM(CASE WHEN ua.id IS NULL THEN 1 ELSE 0 END) AS missing_user_assignment_cnt\n         FROM user_tasks ut\n         LEFT JOIN tasks t ON t.id = ut.task_id\n         LEFT JOIN user_assignments ua ON ua.user_id = ut.user_id AND ua.assignment_id = t.assignment_id\n         WHERE ut.task_id IN ($in)\n         GROUP BY ut.task_id\n         ORDER BY ut.task_id"
    );
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
