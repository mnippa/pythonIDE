<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$assignmentId = 21;
$position = 32;
$title = 'List Slicing: Vier feste Beispiele (Copy)';
$description = '<p>Vier feste Iterationen mit klaren Slicing-Faellen: feste Grenzen, Joker, dritter Parameter, negativer Startindex.</p>';
$taskType = 'code_reading';
$problemType = 'code_completion';
$taskText = 'Bestimme den Inhalt des Slice-Ausdrucks. Antworte nur mit den Werten der Strings, kommasepariert und ohne Klammern, zum Beispiel: Apfel, Birne, Kiwi';
$questionText = 'Welchen Inhalt hat der gegebene Slice-Ausdruck?';
$codeTemplate = <<<'PYTHON'
# Typ: {modus_name}
# Gegebene Liste: {woerter}
# Gegebener Slice: {slice_expr}
# Antworte kommasepariert.
result = ""
PYTHON;
$solutionCode = <<<'PYTHON'
woerter = {woerter}
ausdruck = {slice_expr}

teil = eval(ausdruck)
result = ", ".join(item.strip() for item in teil).strip()
PYTHON;
$correctAnswer = 'result';
$hint1 = 'Lies genau, welche Teile im Slice vorkommen: Start, Stop und eventuell Schrittweite.';
$hint2 = 'Der Stop-Index gehoert nie mehr zum Ergebnis.';
$hint3 = 'Antworte nur mit den String-Werten, getrennt durch Komma und Leerzeichen.';
$stoff = '<p>Python-Listen, Slicing, Wildcards, Schrittweite und negative Indizes mit einer festen Liste.</p>';
$maxAttempts = 3;
$iterationsCount = 4;
$showSolution = 1;
$showSolutionCode = 1;
$manualReviewRequired = 0;
$taskDifficulty = 'medium';
$randomizerCode = null;

$fruitListLiteral = "['Apfel', 'Birne', 'Banane', 'Orange', 'Zitrone', 'Melone', 'Ananas']";

$variableOverrides = json_encode([
    [
        'inputs' => [
            'woerter' => $fruitListLiteral,
            'slice_expr' => '"woerter[1:5]"',
            'modus_name' => '"Feste Grenzen"'
        ],
        'expected' => [
            'variable' => 'result'
        ]
    ],
    [
        'inputs' => [
            'woerter' => $fruitListLiteral,
            'slice_expr' => '"woerter[:2]"',
            'modus_name' => '"Joker"'
        ],
        'expected' => [
            'variable' => 'result'
        ]
    ],
    [
        'inputs' => [
            'woerter' => $fruitListLiteral,
            'slice_expr' => '"woerter[::2]"',
            'modus_name' => '"Dritter Parameter"'
        ],
        'expected' => [
            'variable' => 'result'
        ]
    ],
    [
        'inputs' => [
            'woerter' => $fruitListLiteral,
            'slice_expr' => '"woerter[-5:-1]"',
            'modus_name' => '"Negativer Startindex"'
        ],
        'expected' => [
            'variable' => 'result'
        ]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$stmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
$stmt->bind_param('is', $assignmentId, $title);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $taskId = (int)$existing['id'];
    $stmt = $conn->prepare('UPDATE tasks SET position = ?, description = ?, task_type = ?, task_text = ?, question_text = ?, code_template = ?, solution_code = ?, correct_answer = ?, variable_overrides = ?, randomizer_code = ?, hint1 = ?, hint2 = ?, hint3 = ?, stoff = ?, max_attempts = ?, iterations_count = ?, show_solution = ?, show_solution_code = ?, manual_review_required = ?, problem_type = ?, task_difficulty = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param(
        'isssssssssssssiiiiissi',
        $position,
        $description,
        $taskType,
        $taskText,
        $questionText,
        $codeTemplate,
        $solutionCode,
        $correctAnswer,
        $variableOverrides,
        $randomizerCode,
        $hint1,
        $hint2,
        $hint3,
        $stoff,
        $maxAttempts,
        $iterationsCount,
        $showSolution,
        $showSolutionCode,
        $manualReviewRequired,
        $problemType,
        $taskDifficulty,
        $taskId
    );
} else {
    $stmt = $conn->prepare('INSERT INTO tasks (assignment_id, title, description, position, task_type, task_text, question_text, code_template, solution_code, correct_answer, variable_overrides, randomizer_code, hint1, hint2, hint3, stoff, max_attempts, iterations_count, show_solution, show_solution_code, manual_review_required, problem_type, task_difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param(
        'ississssssssssssiiiiiss',
        $assignmentId,
        $title,
        $description,
        $position,
        $taskType,
        $taskText,
        $questionText,
        $codeTemplate,
        $solutionCode,
        $correctAnswer,
        $variableOverrides,
        $randomizerCode,
        $hint1,
        $hint2,
        $hint3,
        $stoff,
        $maxAttempts,
        $iterationsCount,
        $showSolution,
        $showSolutionCode,
        $manualReviewRequired,
        $problemType,
        $taskDifficulty
    );
}

if (!$stmt->execute()) {
    echo 'DB error: ' . $stmt->error . PHP_EOL;
    exit(1);
}

if (!$existing) {
    $taskId = (int)$conn->insert_id;
}

$stmt->close();

echo ($existing ? 'Updated' : 'Created') . ' task: ' . $taskId . ' | P' . $position . ' | ' . $title . PHP_EOL;
