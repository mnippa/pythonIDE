<?php
/**
 * Task erstellen: Verschachtelte Schleifen i,j,k mit counter
 * code_random_complex, intelligent vars-mode
 */

$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', 'start123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$output = [];

try {
    // ========== CODE TEMPLATE (für den Schüler) ==========
    $code_template = <<<'PYTHON'
#INIT START
import random
ri = random.randint(1, 5)
rj = random.randint(1, 5)
rk = random.randint(1, 5)
#INIT END

counter = 0

for i in range(ri):
    # TODO: counter hier erhöhen
    for j in range(rj):
        # TODO: counter hier erhöhen
        for k in range(rk):
            # TODO: counter hier erhöhen
            pass
PYTHON;

    // ========== SOLUTION CODE (Musterlösung) ==========
    $solution_code = <<<'PYTHON'
#INIT START
import random
ri = random.randint(1, 5)
rj = random.randint(1, 5)
rk = random.randint(1, 5)
#INIT END

counter = 0

for i in range(ri):
    counter += 1
    for j in range(rj):
        counter += 1
        for k in range(rk):
            counter += 1
PYTHON;

    // ========== RANDOMIZER CODE ==========
    $randomizer_code = <<<'PYTHON'
import random

values = {
    "ri": random.randint(1, 5),
    "rj": random.randint(1, 5),
    "rk": random.randint(1, 5)
}
PYTHON;

    // ========== TEST CASES (intelligent vars-mode) ==========
    $test_cases = json_encode([
        'type' => 'intelligent',
        'mode' => 'vars',
        'tests' => 8,
        'inputs' => ['ri', 'rj', 'rk'],
        'outputs' => ['counter']
    ]);

    // ========== INSERT IN DATENBANK ==========
    $sql = "INSERT INTO tasks (
        assignment_id, title, description, max_attempts, iterations_count,
        show_solution, show_solution_code, problem_type, code_template,
        solution_code, randomizer_code, test_cases, task_type, task_text,
        task_difficulty, folderstructure, allowDownload, allow_code_ui_web_edit
    ) VALUES (
        :assignment_id, :title, :description, :max_attempts, :iterations_count,
        :show_solution, :show_solution_code, :problem_type, :code_template,
        :solution_code, :randomizer_code, :test_cases, :task_type, :task_text,
        :task_difficulty, :folderstructure, :allowDownload, :allow_code_ui_web_edit
    )";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':assignment_id'          => 29,
        ':title'                  => 'Verschachtelte Schleifen mit Counter',
        ':description'            => 'Schreibe drei verschachtelte for-Schleifen (i, j, k) mit range(). Eine counter-Variable wird auf jeder Schleifenebene um 1 erhöht.',
        ':max_attempts'           => 3,
        ':iterations_count'       => 8,
        ':show_solution'          => 1,
        ':show_solution_code'     => 1,
        ':problem_type'           => 'code_completion',
        ':code_template'          => $code_template,
        ':solution_code'          => $solution_code,
        ':randomizer_code'        => $randomizer_code,
        ':test_cases'             => $test_cases,
        ':task_type'              => 'code_random_complex',
        ':task_text'              => 'Verschachtelte Schleifen mit Counter',
        ':task_difficulty'        => 'medium',
        ':folderstructure'        => 0,
        ':allowDownload'          => 0,
        ':allow_code_ui_web_edit' => 1
    ]);

    if ($result) {
        $task_id = $pdo->lastInsertId();
        $output[] = 'OK Task erfolgreich erstellt';
        $output[] = 'Task ID: ' . $task_id;
        $output[] = 'Titel: Verschachtelte Schleifen mit Counter';
        $output[] = 'Loops: for i in range(ri) / for j in range(rj) / for k in range(rk)';
        $output[] = 'Randomizer: ri, rj, rk je random 1-5';
        $output[] = 'Counter: +1 auf jeder Schleifen-Ebene (i, j, k)';
        $output[] = 'Test-Mode: intelligent vars-mode, 8 Iterationen';
        $output[] = 'Test URL: http://localhost/pythonIDE/public/editor_assignment_test.php?assignment_id=29&task_id=' . $task_id;
    } else {
        $output[] = 'FEHLER beim Einfuegen';
    }

} catch (Exception $e) {
    $output[] = 'FEHLER: ' . $e->getMessage();
}

foreach ($output as $line) {
    echo $line . "\n";
}

$html = '<html><head><meta charset="utf-8"><title>Task erstellen</title>'
    . '<style>body{font-family:monospace;padding:20px;}.ok{color:green;}.err{color:red;}</style>'
    . '</head><body><pre>';
foreach ($output as $line) {
    $class = stripos($line, 'FEHLER') !== false ? 'err' : 'ok';
    $html .= '<div class="' . $class . '">' . htmlspecialchars($line) . '</div>';
}
$html .= '</pre></body></html>';

file_put_contents(__DIR__ . '/tmp_create_task_nested_loops.html', $html);
?>
