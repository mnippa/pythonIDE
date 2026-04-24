<?php
// Direct database insert for Task 4 using PDO
$output = [];

try {
    // PDO connection
    $pdo = new PDO(
        'mysql:host=localhost;dbname=pythonide',
        'root',
        'start123',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $output[] = "✓ Database connected";
    
    // Task 4 data
    $code_template = '#INIT START
import random
range_i = random.randint(1, 5)
range_j = random.randint(1, 5)
range_k = random.randint(1, 5)
#INIT END

counter = 0
for i in range(range_i):
    for j in range(range_j):
        for k in range(range_k):
            # Schreibe hier den Code um den Counter zu inkrementieren
            pass
';
    
    $solution_code = '#INIT START
import random
range_i = random.randint(1, 5)
range_j = random.randint(1, 5)
range_k = random.randint(1, 5)
#INIT END

counter = 0
for i in range(range_i):
    for j in range(range_j):
        for k in range(range_k):
            counter += 1
            counter += 1
            counter += 1
';
    
    $randomizer_code = 'import random
range_i = random.randint(1, 5)
range_j = random.randint(1, 5)
range_k = random.randint(1, 5)
values = {
    "range_i": range_i,
    "range_j": range_j,
    "range_k": range_k
}';
    
    $test_cases = '{"mode": "intelligent", "tests": [{"inputs": ["range_i", "range_j", "range_k"], "outputs": ["counter"]}]}';
    
    $sql = "INSERT INTO tasks (
        assignment_id, title, description, max_attempts, iterations_count, show_solution, show_solution_code, min_keywords_required, problem_type, code_template, hint1, hint2, hint3, stoff, expected_output, test_cases, solution_code, task_type, task_text, question_text, image_url, correct_answer, variable_overrides, randomizer_code, folderstructure, allowDownload, allow_code_ui_web_edit, task_difficulty
    ) VALUES (
        :assignment_id, :title, :description, :max_attempts, :iterations_count, :show_solution, :show_solution_code, :min_keywords_required, :problem_type, :code_template, :hint1, :hint2, :hint3, :stoff, :expected_output, :test_cases, :solution_code, :task_type, :task_text, :question_text, :image_url, :correct_answer, :variable_overrides, :randomizer_code, :folderstructure, :allowDownload, :allow_code_ui_web_edit, :task_difficulty
    )";
    
    $stmt = $pdo->prepare($sql);
    
    $result = $stmt->execute([
        ':assignment_id' => 29,
        ':title' => 'Verschachtelte Schleife mit Zähler',
        ':description' => 'Schreibe ein Programm mit drei verschachtelten Schleifen (i, j, k). Die Ranges für die drei Schleifen werden zufällig generiert (jeweils 1-5). In der innersten Schleife soll ein Counter 3 Mal inkrementiert werden pro Iteration.',
        ':max_attempts' => 1,
        ':iterations_count' => 3,
        ':show_solution' => 1,
        ':show_solution_code' => 0,
        ':min_keywords_required' => null,
        ':problem_type' => 'code_completion',
        ':code_template' => $code_template,
        ':hint1' => null,
        ':hint2' => null,
        ':hint3' => null,
        ':stoff' => null,
        ':expected_output' => '',
        ':test_cases' => $test_cases,
        ':solution_code' => $solution_code,
        ':task_type' => 'code_random_complex',
        ':task_text' => 'Verschachtelte Schleife mit Zähler',
        ':question_text' => 'Schreibe ein Programm mit drei verschachtelten Schleifen (i, j, k). Die Ranges für die drei Schleifen werden zufällig generiert (jeweils 1-5). In der innersten Schleife soll ein Counter 3 Mal inkrementiert werden pro Iteration.',
        ':image_url' => null,
        ':correct_answer' => null,
        ':variable_overrides' => null,
        ':randomizer_code' => $randomizer_code,
        ':folderstructure' => 0,
        ':allowDownload' => 0,
        ':allow_code_ui_web_edit' => 1,
        ':task_difficulty' => 'medium'
    ]);
    
    if ($result) {
        $task_id = $pdo->lastInsertId();
        $output[] = "✓ Task 4 erfolgreich erstellt!";
        $output[] = "Task ID: " . $task_id;
        $output[] = "Test URL: http://localhost/pythonIDE/public/editor_assignment_test.php?assignment_id=29&task_id=" . $task_id;
    } else {
        $output[] = "✗ Insert fehlgeschlagen";
    }
    
} catch (PDOException $e) {
    $output[] = "✗ Datenbankfehler: " . $e->getMessage();
} catch (Exception $e) {
    $output[] = "✗ Fehler: " . $e->getMessage();
}

// Generate HTML output
$html = '<html><head><meta charset="utf-8"><title>Task 4 Insert Result</title><style>pre { font-family: monospace; white-space: pre-wrap; word-wrap: break-word; }</style></head><body><pre>';
$html .= htmlspecialchars(implode("\n", $output));
$html .= '</pre></body></html>';

// Write to file
file_put_contents('tmp_task_4_result.html', $html);
echo $html;
?>
