<?php
/**
 * Task erstellen: Zwei Funktionen (finde_maximum, finde_durchschnitt)
 * mit Randomizer (Liste 30 Zahlen) und Funktionsprüfung
 */

require_once __DIR__ . '/config/database.php';
$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', 'start123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$output = [];

try {
    // ========== CODE TEMPLATE (für den Schüler) ==========
    $code_template = <<<'PYTHON'
#INIT START
import random
zahlen = [random.randint(1, 100) for _ in range(30)]
#INIT END

def finde_maximum(zahlen):
    # TODO: Schreibe eine Funktion, die das Maximum findet
    pass

def finde_durchschnitt(zahlen):
    # TODO: Schreibe eine Funktion, die den Durchschnitt findet
    pass

# Speichere die Ergebnisse
ergebnis_max = finde_maximum(zahlen[:])
ergebnis_avg = finde_durchschnitt(zahlen[:])
PYTHON;

    // ========== SOLUTION CODE (Musterlösung) ==========
    $solution_code = <<<'PYTHON'
#INIT START
import random
zahlen = [random.randint(1, 100) for _ in range(30)]
#INIT END

def finde_maximum(zahlen):
    """Findet das Maximum in einer Liste"""
    if not zahlen:
        return None
    return max(zahlen)

def finde_durchschnitt(zahlen):
    """Findet den Durchschnitt einer Liste"""
    if not zahlen:
        return None
    return sum(zahlen) / len(zahlen)

# Speichere die Ergebnisse
ergebnis_max = finde_maximum(zahlen[:])
ergebnis_avg = finde_durchschnitt(zahlen[:])
PYTHON;

    // ========== RANDOMIZER CODE (generiert zufällige Liste) ==========
    $randomizer_code = <<<'PYTHON'
import random

# Generiere Liste mit 30 zufälligen Zahlen (1-100)
values = {
    "zahlen": [random.randint(1, 100) for _ in range(30)]
}
PYTHON;

    // ========== TEST CASES (intelligent vars-mode) ==========
    $test_cases = json_encode([
        'type' => 'intelligent',
        'mode' => 'vars',
        'tests' => 8,
        'inputs' => ['zahlen'],
        'outputs' => ['ergebnis_max', 'ergebnis_avg'],
        'solution_code' => null,  // wird von task.solution_code verwendet
        'randomizer_code' => null  // wird von task.randomizer_code verwendet
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
        ':title' => 'Maximum und Durchschnitt finden',
        ':description' => 'Schreibe zwei Funktionen: finde_maximum() und finde_durchschnitt(). Beide erhalten eine Liste mit 30 Zahlen und geben den entsprechenden Wert zurück.',
        ':max_attempts' => 3,
        ':iterations_count' => 8,
        ':show_solution' => 1,
        ':show_solution_code' => 1,
        ':problem_type' => 'code_completion',
        ':code_template' => $code_template,
        ':solution_code' => $solution_code,
        ':randomizer_code' => $randomizer_code,
        ':test_cases' => $test_cases,
        ':task_type' => 'code_random_complex',
        ':task_text' => 'Maximum und Durchschnitt finden',
        ':task_difficulty' => 'medium',
        ':folderstructure' => 0,
        ':allowDownload' => 0,
        ':allow_code_ui_web_edit' => 1
    ]);

    if ($result) {
        $task_id = $pdo->lastInsertId();
        $output[] = "✅ Task erfolgreich erstellt!";
        $output[] = "Task ID: " . $task_id;
        $output[] = "Titel: Maximum und Durchschnitt finden";
        $output[] = "Funktionen: finde_maximum(), finde_durchschnitt()";
        $output[] = "Argumente: Liste mit 30 zufälligen Zahlen (1-100)";
        $output[] = "Test-Mode: intelligent vars-mode mit 8 Iterationen";
        $output[] = "Test URL: http://localhost/pythonIDE/public/editor_assignment_test.php?assignment_id=29&task_id=" . $task_id;
    } else {
        $output[] = "❌ Fehler beim Einfügen";
    }

} catch (Exception $e) {
    $output[] = "❌ Fehler: " . $e->getMessage();
}

// Ausgabe
foreach ($output as $line) {
    echo $line . "\n";
}

// Als HTML speichern
$html = '<html><head><meta charset="utf-8"><title>Task erstellen</title><style>body{font-family:monospace;padding:20px;} .ok{color:green;} .err{color:red;}</style></head><body><pre>';
foreach ($output as $line) {
    $class = strpos($line, '❌') !== false ? 'err' : 'ok';
    $html .= '<div class="' . $class . '">' . htmlspecialchars($line) . '</div>';
}
$html .= '</pre></body></html>';

file_put_contents(__DIR__ . '/tmp_create_task_funktionen.html', $html);
?>
