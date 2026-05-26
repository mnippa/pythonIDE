<?php
require_once __DIR__ . '/../config/database.php';

$taskId = 345;
$taskFolder = __DIR__ . '/../storage/tasks/folders/task_' . $taskId;
$mainPath = $taskFolder . '/main.py';
$initPath = $taskFolder . '/init.py';

if (!file_exists($mainPath)) {
    echo 'ERROR: main.py not found: ' . $mainPath . PHP_EOL;
    exit(1);
}

$codeTemplate = file_get_contents($mainPath);
if ($codeTemplate === false) {
    echo 'ERROR: could not read main.py' . PHP_EOL;
    exit(1);
}

$solutionCode = str_replace("'vX': 1,", "'vX': 2,", $codeTemplate, $count);
if ($count < 1) {
    echo 'WARNING: no vX replacement found, using template as solution' . PHP_EOL;
    $solutionCode = $codeTemplate;
}

$conn = getDbConnection();
$stmt = $conn->prepare('UPDATE tasks SET code_template = ?, solution_code = ?, updated_at = NOW() WHERE id = ?');
$stmt->bind_param('ssi', $codeTemplate, $solutionCode, $taskId);

if (!$stmt->execute()) {
    echo 'ERROR updating task: ' . $stmt->error . PHP_EOL;
    exit(1);
}
$stmt->close();

if (file_exists($initPath)) {
    unlink($initPath);
}

echo 'Updated task ' . $taskId . PHP_EOL;
echo 'code_template_len=' . strlen($codeTemplate) . PHP_EOL;
echo 'solution_code_len=' . strlen($solutionCode) . PHP_EOL;
echo 'init_exists=' . (file_exists($initPath) ? '1' : '0') . PHP_EOL;
