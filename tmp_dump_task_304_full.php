<?php
require __DIR__ . '/config/database.php';
$conn = getDbConnection();
$stmt = $conn->prepare('SELECT id, title, code_template, solution_code, randomizer_code, test_cases FROM tasks WHERE id = 304');
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    echo "TASK_NOT_FOUND\n";
    exit(1);
}
echo "TITLE=" . $row['title'] . "\n";
echo "CODE_TEMPLATE_START\n" . $row['code_template'] . "\nCODE_TEMPLATE_END\n";
echo "SOLUTION_CODE_START\n" . $row['solution_code'] . "\nSOLUTION_CODE_END\n";
echo "RANDOMIZER_START\n" . $row['randomizer_code'] . "\nRANDOMIZER_END\n";
echo "TEST_CASES=" . $row['test_cases'] . "\n";
