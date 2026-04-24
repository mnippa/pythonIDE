<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();
$id = 306;
$stmt = $conn->prepare('SELECT id, title, task_type, code_template, solution_code, randomizer_code, test_cases FROM tasks WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    file_put_contents(__DIR__ . '/tmp_read_task_306.json', json_encode(['error' => 'not found'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    exit;
}
file_put_contents(__DIR__ . '/tmp_read_task_306.json', json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
