<?php
require __DIR__ . '/../config/database.php';
$conn = getDbConnection();

$ids = [75, 76, 77, 79, 80, 81];
$in = implode(',', $ids);
$sql = "SELECT id, title, code_template, solution_code FROM tasks WHERE id IN ($in) ORDER BY id";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    echo "ID {$row['id']}: {$row['title']}\n";
    $codeTemplate = str_replace("\n", "\\n", $row['code_template'] ?? '');
    $solutionCode = str_replace("\n", "\\n", $row['solution_code'] ?? '');
    echo "code_template: {$codeTemplate}\n";
    echo "solution_code: {$solutionCode}\n\n";
}

$conn->close();
