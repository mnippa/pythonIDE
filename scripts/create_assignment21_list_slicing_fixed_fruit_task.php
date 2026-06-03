<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$assignmentId = 21;
$position = 31;
$title = 'List Slicing: Vier feste Varianten';
$description = '<p>Code-Random-Complex-Aufgabe wie das bewaehrte Slicing-Muster, aber mit fester 7er-Fruchtliste in allen Iterationen.</p>';
$taskType = 'code_random_complex';
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

$variableOverrides = json_encode([
    [
        'inputs' => [
            'woerter' => '<random>',
            'slice_expr' => '<random>',
            'modus_name' => '<random>'
        ],
        'expected' => [
            'variable' => 'result'
        ]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$randomizerCode = <<<'PYTHON'
import random

woerter = ["Apfel", "Birne", "Banane", "Orange", "Zitrone", "Melone", "Ananas"]
n = len(woerter)
modus = random.randint(1, 4)

if modus == 1:
    start = random.randint(0, n - 2)
    stop = random.randint(start + 1, n)
    slice_expr = f"woerter[{start}:{stop}]"
    modus_name = "Klare Grenzen"
elif modus == 2:
    stop = random.randint(1, n - 1)
    slice_expr = f"woerter[:{stop}]"
    modus_name = "Linke Wildcard"
elif modus == 3:
    step = random.choice([2, 3])
    while True:
        start = random.randint(0, n - 2)
        stop = random.randint(start + 2, n)
        if len(woerter[start:stop:step]) >= 2:
            break
    slice_expr = f"woerter[{start}:{stop}:{step}]"
    modus_name = "Schrittweite"
else:
    while True:
        start_neg = random.randint(2, n - 1)
        stop_neg = random.randint(1, start_neg - 1)
        teil = woerter[-start_neg:-stop_neg]
        if len(teil) >= 1:
            break
    slice_expr = f"woerter[-{start_neg}:-{stop_neg}]"
    modus_name = "Negative Indizes"
PYTHON;

$stmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
$stmt->bind_param('is', $assignmentId, $title);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $taskId = (int)$existing['id'];
    $stmt = $conn->prepare('UPDATE tasks SET position = ?, description = ?, task_type = ?, task_text = ?, question_text = ?, code_template = ?, solution_code = ?, correct_answer = ?, variable_overrides = ?, randomizer_code = ?, hint1 = ?, hint2 = ?, hint3 = ?, stoff = ?, max_attempts = ?, iterations_count = ?, show_solution = ?, show_solution_code = ?, manual_review_required = ?, problem_type = ?, task_difficulty = ?, updated_at = NOW() WHERE id = ?');
    $taskDifficulty = 'medium';
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
    $taskDifficulty = 'medium';
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