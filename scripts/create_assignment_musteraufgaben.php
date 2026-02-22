<?php
/**
 * Create Assignment: Musteraufgaben
 * Covers all task types with multiple examples.
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Find an admin user
$adminId = 1;
$adminRes = $conn->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1");
if ($adminRes && $adminRes->num_rows > 0) {
    $adminId = (int)$adminRes->fetch_assoc()['id'];
}

$title = 'Aufgabenmuster';
$description = 'Musteraufgaben zur Konfiguration aller Tasktypen und Validierungen.';
$difficulty = 'intermediate';
$isActive = 1;

$stmt = $conn->prepare("INSERT INTO assignments (title, description, difficulty, is_active, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
$stmt->bind_param('sssii', $title, $description, $difficulty, $isActive, $adminId);
$stmt->execute();
$assignmentId = $stmt->insert_id;
$stmt->close();

echo "Created assignment '{$title}' (ID: {$assignmentId})\n\n";

$tasks = [];
$pos = 1;

// -------------------- CODE TASKS --------------------
$tasks[] = [
    'title' => 'Ausgabe pruefen (OUTPUT)',
    'description' => 'Gib eine Begruessung fuer Ada aus.',
    'position' => $pos++,
    'task_type' => 'code',
    'problem_type' => 'code_completion',
    'code_template' => 'name = "Ada"\nprint("Hallo " + name)',
    'solution_code' => 'name = "Ada"\nprint("Hallo " + name + "!")',
    'test_cases' => json_encode([
        ['type' => 'output', 'input' => '', 'expected' => ['Hallo Ada', 'Hallo Ada!']]
    ]),
    'validation_mode' => 'loose',
    'max_attempts' => 8
];

$tasks[] = [
    'title' => 'Funktion schreiben (FUNCTION)',
    'description' => 'Implementiere summe(a, b), die die Summe zweier Zahlen liefert.',
    'position' => $pos++,
    'task_type' => 'code',
    'problem_type' => 'code_completion',
    'code_template' => 'def summe(a, b):\n    return a ___ b',
    'solution_code' => 'def summe(a, b):\n    return a + b',
    'test_cases' => json_encode([
        ['type' => 'function', 'function_name' => 'summe', 'args' => [2, 3], 'expected' => 5],
        ['type' => 'function', 'function_name' => 'summe', 'args' => [-1, 5], 'expected' => 4]
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 8
];

$tasks[] = [
    'title' => 'Variable pruefen (VARIABLE)',
    'description' => 'Berechne das Quadrat von x und speichere es in result.',
    'position' => $pos++,
    'task_type' => 'code',
    'problem_type' => 'code_completion',
    'code_template' => '#INIT Start#\nx = 4\n#INIT End#\n\nresult = x * ___',
    'solution_code' => 'result = x * x',
    'test_cases' => json_encode([
        ['type' => 'variable', 'init_vars' => ['x' => 3], 'expected_vars' => ['result' => 9]],
        ['type' => 'variable', 'init_vars' => ['x' => -2], 'expected_vars' => ['result' => 4]]
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 8
];

$tasks[] = [
    'title' => 'Code Check (CODE_CHECK)',
    'description' => 'Summiere Zahlen von 1 bis n mit einer for-Schleife. while ist nicht erlaubt.',
    'position' => $pos++,
    'task_type' => 'code',
    'problem_type' => 'code_completion',
    'code_template' => 'n = 10\nresult = 0\n# TODO: for-Schleife verwenden\n',
    'solution_code' => 'n = 10\nresult = 0\nfor i in range(1, n + 1):\n    result += i',
    'test_cases' => json_encode([
        ['type' => 'code_check', 'keywords' => ['for'], 'operator' => 'AND', 'feedback' => 'for-Schleife wird verlangt'],
        ['type' => 'code_check', 'keywords' => ['while'], 'operator' => 'NOT', 'feedback' => 'while ist nicht erlaubt']
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 8
];

$tasks[] = [
    'title' => 'Intelligent Test (INTELLIGENT)',
    'description' => 'Schreibe eine Funktion doppeln(lst), die alle Zahlen in der Liste verdoppelt.',
    'position' => $pos++,
    'task_type' => 'code',
    'problem_type' => 'code_completion',
    'code_template' => 'def doppeln(lst):\n    return [x * ___ for x in lst]',
    'solution_code' => 'def doppeln(lst):\n    return [x * 2 for x in lst]',
    'test_cases' => json_encode([
        [
            'type' => 'intelligent',
            'mode' => 'function',
            'tests' => 5,
            'function' => [
                'name' => 'doppeln',
                'inputs' => [
                    ['name' => 'lst', 'type' => 'list', 'element' => ['type' => 'int']]
                ],
                'output' => ['type' => 'list', 'element' => ['type' => 'int']]
            ]
        ]
    ]),
    'validation_mode' => 'intelligent',
    'max_attempts' => 8
];

$tasks[] = [
    'title' => 'Kombinierte Tests (OUTPUT + FUNCTION + VARIABLE)',
    'description' => 'Implementiere verdoppeln(x) und gib das Ergebnis als Text aus.',
    'position' => $pos++,
    'task_type' => 'code',
    'problem_type' => 'code_completion',
    'code_template' => '#INIT Start#\nx = 5\n#INIT End#\n\n
def verdoppeln(x):\n    return x * ___\n\nresult = verdoppeln(x)\nprint(f"Ergebnis: {___}")',
    'solution_code' => '#INIT Start#\nx = 5\n#INIT End#\n\n
def verdoppeln(x):\n    return x * 2\n\nresult = verdoppeln(x)\nprint(f"Ergebnis: {result}")',
    'test_cases' => json_encode([
        ['type' => 'function', 'function_name' => 'verdoppeln', 'args' => [7], 'expected' => 14],
        ['type' => 'variable', 'init_vars' => ['x' => 10], 'expected_vars' => ['result' => 20]],
        ['type' => 'output', 'input' => '', 'expected' => ['Ergebnis: 10', 'Ergebnis: 14', 'Ergebnis: 20']]
    ]),
    'validation_mode' => 'strict',
    'max_attempts' => 8
];

// -------------------- SINGLE CHOICE --------------------
$tasks[] = [
    'title' => 'Single Choice: Wahr/Falsch',
    'description' => 'Waehle die richtige Aussage.',
    'position' => $pos++,
    'task_type' => 'single_choice',
    'problem_type' => 'multiple_choice',
    'question_text' => 'Welche Aussage zu Python ist richtig?',
    'options' => [
        ['text' => 'Python ist eine kompilierte Sprache', 'is_correct' => false],
        ['text' => 'Python verwendet Einrueckungen fuer Bloecke', 'is_correct' => true],
        ['text' => 'Python hat keine Listen', 'is_correct' => false]
    ],
    'max_attempts' => 3
];

$tasks[] = [
    'title' => 'Single Choice: Operator',
    'description' => 'Waehle den richtigen Operator.',
    'position' => $pos++,
    'task_type' => 'single_choice',
    'problem_type' => 'multiple_choice',
    'question_text' => 'Welcher Operator prueft Gleichheit?',
    'options' => [
        ['text' => '=', 'is_correct' => false],
        ['text' => '==', 'is_correct' => true],
        ['text' => '!=', 'is_correct' => false]
    ],
    'max_attempts' => 3
];

// -------------------- MULTIPLE CHOICE --------------------
$tasks[] = [
    'title' => 'Multiple Choice: Primzahlen',
    'description' => 'Mehrere Antworten moeglich.',
    'position' => $pos++,
    'task_type' => 'multiple_choice',
    'problem_type' => 'multiple_choice',
    'question_text' => 'Welche Zahlen sind Primzahlen?',
    'options' => [
        ['text' => '2', 'is_correct' => true],
        ['text' => '3', 'is_correct' => true],
        ['text' => '4', 'is_correct' => false],
        ['text' => '5', 'is_correct' => true]
    ],
    'max_attempts' => 3
];

$tasks[] = [
    'title' => 'Multiple Choice: Datentypen',
    'description' => 'Mehrere Antworten moeglich.',
    'position' => $pos++,
    'task_type' => 'multiple_choice',
    'problem_type' => 'multiple_choice',
    'question_text' => 'Welche sind eingebaute Python-Datentypen?',
    'options' => [
        ['text' => 'list', 'is_correct' => true],
        ['text' => 'dict', 'is_correct' => true],
        ['text' => 'vector', 'is_correct' => false],
        ['text' => 'tuple', 'is_correct' => true]
    ],
    'max_attempts' => 3
];

// -------------------- FREE TEXT --------------------
$tasks[] = [
    'title' => 'Freitext: Datentypen',
    'description' => 'Nenne mindestens zwei Python-Datentypen.',
    'position' => $pos++,
    'task_type' => 'free_text',
    'problem_type' => 'essay',
    'question_text' => 'Welche Python-Datentypen kennst du?',
    'correct_answer' => 'int, str, list, dict',
    'min_keywords_required' => 2,
    'validation_mode' => 'loose',
    'max_attempts' => 3
];

$tasks[] = [
    'title' => 'Freitext: Schleifen',
    'description' => 'Erklaere, was eine Schleife ist.',
    'position' => $pos++,
    'task_type' => 'free_text',
    'problem_type' => 'essay',
    'question_text' => 'Was ist eine Schleife?',
    'correct_answer' => 'wiederholung, iteration, for, while',
    'min_keywords_required' => 3,
    'validation_mode' => 'loose',
    'max_attempts' => 3
];

// -------------------- CODE READING --------------------
$tasks[] = [
    'title' => 'Code Reading: Summe einer Range (Iteration)',
    'description' => 'Lies den Code und bestimme den Wert von result.',
    'position' => $pos++,
    'task_type' => 'code_reading',
    'problem_type' => 'code_completion',
    'question_text' => 'Was ist der Wert von result nach der Schleife?',
    'code_template' => 'result = 0\nfor i in range({start}, {end} + 1):\n    result += i',
    'correct_answer' => 'result',
    'variable_overrides' => json_encode([
        ['start' => 1, 'end' => 3],
        ['start' => 2, 'end' => 5],
        ['start' => 4, 'end' => 4]
    ]),
    'validation_mode' => 'loose',
    'max_attempts' => 3,
    'max_iterations' => 3,
    'show_solution_code' => 1
];

$tasks[] = [
    'title' => 'Code Reading: Dezimal zu Binaer (ohne Code)',
    'description' => 'Algorithmus bekannt: Umwandlung in 8-bit Binaer.',
    'position' => $pos++,
    'task_type' => 'code_reading',
    'problem_type' => 'code_completion',
    'question_text' => 'Wandle die Dezimalzahl in eine 8-bit Binaerzahl um.',
    'code_template' => 'decimal = {decimal}\nresult = format(decimal, "08b")',
    'correct_answer' => 'result',
    'variable_overrides' => json_encode([
        ['decimal' => 13]
    ]),
    'validation_mode' => 'loose',
    'max_attempts' => 3,
    'max_iterations' => 1,
    'show_solution_code' => 0
];

// -------------------- CODE RANDOM COMPLEX --------------------
$tasks[] = [
    'title' => 'Code Random: Gerade Zahl? (ohne Iteration)',
    'description' => 'Pruefe, ob n gerade ist.',
    'position' => $pos++,
    'task_type' => 'code_random_complex',
    'problem_type' => 'code_completion',
    'question_text' => 'Ist die Zufallszahl n gerade?',
    'code_template' => 'import random\nvalues = {"n": random.randint(1, 20)}',
    'solution_code' => 'result = (values["n"] % 2 == 0)',
    'correct_answer' => 'result',
    'validation_mode' => 'loose',
    'max_attempts' => 3,
    'max_iterations' => 1,
    'show_solution_code' => 0
];

$tasks[] = [
    'title' => 'Code Random: Binaer zu Dezimal (Iteration, Code anzeigen)',
    'description' => 'Wandle eine zufaellige 8-bit Binaerzahl in Dezimal um.',
    'position' => $pos++,
    'task_type' => 'code_random_complex',
    'problem_type' => 'code_completion',
    'question_text' => 'Wandle die Binaerzahl in Dezimal um.',
    'code_template' => 'import random\nvalues = {"binary": format(random.randint(0, 255), "08b")}',
    'solution_code' => 'result = int(values["binary"], 2)',
    'correct_answer' => 'result',
    'validation_mode' => 'loose',
    'max_attempts' => 3,
    'max_iterations' => 3,
    'show_solution_code' => 1
];

$tasks[] = [
    'title' => 'Code Random: Celsius zu Fahrenheit (Iteration, Code versteckt)',
    'description' => 'Wandle Celsius in Fahrenheit um.',
    'position' => $pos++,
    'task_type' => 'code_random_complex',
    'problem_type' => 'code_completion',
    'question_text' => 'Wandle die Temperatur von Celsius in Fahrenheit um.',
    'code_template' => 'import random\nvalues = {"celsius": random.randint(-10, 30)}',
    'solution_code' => 'result = values["celsius"] * 9 / 5 + 32',
    'correct_answer' => 'result',
    'validation_mode' => 'loose',
    'max_attempts' => 3,
    'max_iterations' => 3,
    'show_solution_code' => 0
];

// -------------------- INSERT TASKS --------------------
$taskStmt = $conn->prepare(
    'INSERT INTO tasks (
        assignment_id, title, description, position, max_attempts, iterations_count,
        show_solution, show_solution_code, min_keywords_required, problem_type,
        code_template, hint1, hint2, hint3, stoff, expected_output, validation_mode,
        test_cases, solution_code, task_type, question_text, image_url, correct_answer,
        variable_overrides
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$optionStmt = $conn->prepare(
    'INSERT INTO task_options (task_id, option_text, image_url, is_correct, order_num) VALUES (?, ?, ?, ?, ?)'
);

foreach ($tasks as $task) {
    $assignmentIdLocal = $assignmentId;
    $title = $task['title'];
    $desc = $task['description'] ?? '';
    $position = $task['position'];
    $maxAttempts = $task['max_attempts'] ?? 3;
    $iterations = $task['max_iterations'] ?? 1;
    $showSolution = $task['show_solution'] ?? 1;
    $showSolutionCode = $task['show_solution_code'] ?? 0;
    $minKeywords = array_key_exists('min_keywords_required', $task) ? $task['min_keywords_required'] : null;
    $problemType = $task['problem_type'] ?? 'code_completion';
    $codeTemplate = $task['code_template'] ?? '';
    $hint1 = $task['hint1'] ?? '';
    $hint2 = $task['hint2'] ?? '';
    $hint3 = $task['hint3'] ?? '';
    $stoff = $task['stoff'] ?? '';
    $expectedOutput = $task['expected_output'] ?? '';
    $validationMode = $task['validation_mode'] ?? '';
    $testCases = $task['test_cases'] ?? null;
    $solutionCode = $task['solution_code'] ?? '';
    $taskType = $task['task_type'] ?? 'code';
    $questionText = $task['question_text'] ?? '';
    $imageUrl = $task['image_url'] ?? '';
    $correctAnswer = $task['correct_answer'] ?? '';
    $variableOverrides = $task['variable_overrides'] ?? null;

    $taskStmt->bind_param(
        'issiiiiii' . str_repeat('s', 15),
        $assignmentIdLocal,
        $title,
        $desc,
        $position,
        $maxAttempts,
        $iterations,
        $showSolution,
        $showSolutionCode,
        $minKeywords,
        $problemType,
        $codeTemplate,
        $hint1,
        $hint2,
        $hint3,
        $stoff,
        $expectedOutput,
        $validationMode,
        $testCases,
        $solutionCode,
        $taskType,
        $questionText,
        $imageUrl,
        $correctAnswer,
        $variableOverrides
    );

    if ($taskStmt->execute()) {
        $taskId = $conn->insert_id;
        echo "✓ Task {$position}: {$title} (ID: {$taskId})\n";

        if (($taskType === 'single_choice' || $taskType === 'multiple_choice') && !empty($task['options'])) {
            $order = 1;
            foreach ($task['options'] as $opt) {
                $optText = $opt['text'] ?? '';
                $optImage = $opt['image_url'] ?? null;
                $isCorrect = !empty($opt['is_correct']) ? 1 : 0;
                $optionStmt->bind_param('issii', $taskId, $optText, $optImage, $isCorrect, $order);
                $optionStmt->execute();
                $order++;
            }
        }
    } else {
        echo "✗ Task {$position}: {$title} - {$taskStmt->error}\n";
    }
}

$taskStmt->close();
$optionStmt->close();
$conn->close();

echo "\nDone. Assignment ID: {$assignmentId}\n";
