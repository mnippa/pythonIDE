<?php
require_once __DIR__ . '/../config/database.php';

$db = getDbConnection();
$ids = [90, 91, 92, 93];
$in = implode(',', $ids);

$sql = "SELECT id, title, task_type, question_text, code_template, solution_code, correct_answer, test_cases, validation_mode FROM tasks WHERE id IN ($in) ORDER BY id";
$res = $db->query($sql);

if (!$res) {
    echo "Query failed: " . $db->error . "\n";
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    echo "\nTask " . $row['id'] . " (" . $row['task_type'] . ") - " . $row['title'] . "\n";
    $qt = $row['question_text'];
    $ct = $row['code_template'];
    $sc = $row['solution_code'];
    $ca = $row['correct_answer'];
    $tc = $row['test_cases'];
    echo "  question_text: " . ($qt === null || $qt === '' ? 'EMPTY' : substr($qt, 0, 200) . (strlen($qt) > 200 ? '...' : '')) . "\n";
    echo "  code_template: " . ($ct === null || $ct === '' ? 'EMPTY' : substr($ct, 0, 200) . (strlen($ct) > 200 ? '...' : '')) . "\n";
    echo "  solution_code: " . ($sc === null || $sc === '' ? 'EMPTY' : substr($sc, 0, 200) . (strlen($sc) > 200 ? '...' : '')) . "\n";
    echo "  correct_answer: " . ($ca === null || $ca === '' ? 'EMPTY' : substr($ca, 0, 200) . (strlen($ca) > 200 ? '...' : '')) . "\n";
    if ($tc === null || $tc === '') {
        echo "  test_cases: EMPTY\n";
    } else {
        $short = substr($tc, 0, 200);
        echo "  test_cases: " . $short . (strlen($tc) > 200 ? '...' : '') . "\n";
    }
    $vm = $row['validation_mode'];
    echo "  validation_mode: " . ($vm === null ? 'NULL' : $vm) . "\n";
}

$db->close();
