<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', 'start123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$out = [];

try {
    $codeTemplate = <<<'PYTHON'
ri = {ri}
rj = {rj}
rk = {rk}

counter = 0

for i in range(ri):
    # TODO: counter hier erhöhen
    for j in range(rj):
        # TODO: counter hier erhöhen
        for k in range(rk):
            # TODO: counter hier erhöhen
            pass
PYTHON;

    $solutionCode = <<<'PYTHON'
ri = {ri}
rj = {rj}
rk = {rk}

counter = 0

for i in range(ri):
    counter += 1
    for j in range(rj):
        counter += 1
        for k in range(rk):
            counter += 1
PYTHON;

    $randomizerCode = <<<'PYTHON'
import random
ri = random.randint(1, 5)
rj = random.randint(1, 5)
rk = random.randint(1, 5)
PYTHON;

    $variableOverrides = json_encode([
        [
            'inputs' => [
                'ri' => '<random>',
                'rj' => '<random>',
                'rk' => '<random>'
            ],
            'expected' => [
                'variable' => 'counter'
            ]
        ]
    ]);

    $stmt = $pdo->prepare("UPDATE tasks SET
        task_type = :task_type,
        problem_type = :problem_type,
        title = :title,
        description = :description,
        task_text = :task_text,
        code_template = :code_template,
        solution_code = :solution_code,
        randomizer_code = :randomizer_code,
        correct_answer = :correct_answer,
        variable_overrides = :variable_overrides,
        test_cases = :test_cases,
        iterations_count = :iterations_count,
        task_difficulty = :task_difficulty,
        updated_at = NOW()
    WHERE id = :id");

    $ok = $stmt->execute([
        ':task_type' => 'code_random_complex',
        ':problem_type' => 'code_completion',
        ':title' => 'Verschachtelte Schleifen mit Counter',
        ':description' => 'Drei verschachtelte for-Schleifen mit range(). ri, rj, rk sind Zufallswerte zwischen 1 und 5. Erhoehe counter auf jeder Schleifenebene jeweils um 1.',
        ':task_text' => 'Nutze range mit i, j, k und zaehle eine counter-Variable in jeder Ebene hoch.',
        ':code_template' => $codeTemplate,
        ':solution_code' => $solutionCode,
        ':randomizer_code' => $randomizerCode,
        ':correct_answer' => 'counter',
        ':variable_overrides' => $variableOverrides,
        ':test_cases' => null,
        ':iterations_count' => 8,
        ':task_difficulty' => 'medium',
        ':id' => 310
    ]);

    if (!$ok) {
        throw new Exception('Update fehlgeschlagen');
    }

    $verify = $pdo->prepare('SELECT id, assignment_id, task_type, correct_answer, code_template, solution_code, randomizer_code, variable_overrides, test_cases FROM tasks WHERE id = :id LIMIT 1');
    $verify->execute([':id' => 310]);
    $row = $verify->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Task 310 nicht gefunden nach Update');
    }

    $out[] = 'OK Task 310 auf Recap-A-Standard aktualisiert';
    $out[] = 'ID=' . $row['id'] . ' | assignment_id=' . $row['assignment_id'] . ' | task_type=' . $row['task_type'] . ' | correct_answer=' . $row['correct_answer'];
    $out[] = 'CODE_TEMPLATE=' . str_replace(["\r", "\n"], ['\\r', '\\n'], (string)$row['code_template']);
    $out[] = 'SOLUTION_CODE=' . str_replace(["\r", "\n"], ['\\r', '\\n'], (string)$row['solution_code']);
    $out[] = 'RANDOMIZER_CODE=' . str_replace(["\r", "\n"], ['\\r', '\\n'], (string)$row['randomizer_code']);
    $out[] = 'VARIABLE_OVERRIDES=' . (string)$row['variable_overrides'];
    $out[] = 'TEST_CASES=' . (string)$row['test_cases'];

} catch (Exception $e) {
    $out[] = 'FEHLER: ' . $e->getMessage();
}

file_put_contents(__DIR__ . '/tmp_fix_task_310_recap_style.txt', implode("\n", $out) . "\n");

echo implode("\n", $out) . "\n";
?>