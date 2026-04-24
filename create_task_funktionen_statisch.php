<?php
/**
 * Task erstellen: Zwei Funktionen (finde_maximum, finde_durchschnitt)
 * mit statischer Funktionsprüfung (feste 3er-Listen)
 */

require_once __DIR__ . '/config/database.php';
$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', 'start123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$output = [];

try {
    // ========== CODE TEMPLATE (für den Schüler) ==========
    $code_template = <<<'PYTHON'
def finde_maximum(zahlen):
    # TODO: Gib das Maximum der Liste zurück
    pass


def finde_durchschnitt(zahlen):
    # TODO: Gib den Durchschnitt der Liste zurück
    pass
PYTHON;

    // ========== SOLUTION CODE (Musterlösung) ==========
    $solution_code = <<<'PYTHON'
def finde_maximum(zahlen):
    return max(zahlen)


def finde_durchschnitt(zahlen):
    return sum(zahlen) / len(zahlen)
PYTHON;

    // ========== TEST CASES (statische Funktionsprüfung) ==========
    $test_cases = json_encode([
        ['type' => 'function', 'function_name' => 'finde_maximum', 'args' => [[3, 7, 5]], 'expected' => 7],
        ['type' => 'function', 'function_name' => 'finde_maximum', 'args' => [[10, 2, 8]], 'expected' => 10],
        ['type' => 'function', 'function_name' => 'finde_durchschnitt', 'args' => [[3, 6, 9]], 'expected' => 6.0],
        ['type' => 'function', 'function_name' => 'finde_durchschnitt', 'args' => [[2, 4, 8]], 'expected' => 4.666666666666667]
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
        ':assignment_id' => 29,
        ':title' => 'Maximum und Durchschnitt (statische Tests)',
        ':description' => 'Schreibe zwei Funktionen: finde_maximum(zahlen) und finde_durchschnitt(zahlen). Die Prüfung erfolgt mit festen Listen aus jeweils 3 Elementen.',
        ':max_attempts' => 3,
        ':iterations_count' => 1,
        ':show_solution' => 1,
        ':show_solution_code' => 1,
        ':problem_type' => 'code_completion',
        ':code_template' => $code_template,
        ':solution_code' => $solution_code,
        ':randomizer_code' => '',
        ':test_cases' => $test_cases,
        ':task_type' => 'code',
        ':task_text' => 'Maximum und Durchschnitt mit festen Testwerten',
        ':task_difficulty' => 'medium',
        ':folderstructure' => 0,
        ':allowDownload' => 0,
        ':allow_code_ui_web_edit' => 1
    ]);

    if ($result) {
        $task_id = $pdo->lastInsertId();
        $output[] = 'OK Task erfolgreich erstellt';
        $output[] = 'Task ID: ' . $task_id;
        $output[] = 'Titel: Maximum und Durchschnitt (statische Tests)';
        $output[] = 'Funktionen: finde_maximum(), finde_durchschnitt()';
        $output[] = 'Argumente: feste Listen mit 3 Elementen';
        $output[] = 'Test-Mode: function (statisch)';
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

$html = '<html><head><meta charset="utf-8"><title>Task erstellen</title><style>body{font-family:monospace;padding:20px;} .ok{color:green;} .err{color:red;}</style></head><body><pre>';
foreach ($output as $line) {
    $class = stripos($line, 'FEHLER') !== false ? 'err' : 'ok';
    $html .= '<div class="' . $class . '">' . htmlspecialchars($line) . '</div>';
}
$html .= '</pre></body></html>';

file_put_contents(__DIR__ . '/tmp_create_task_funktionen_statisch.html', $html);
?>
