<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();
$assignmentId = 29;
$title = 'Einfache Rekursion: Summe bis n';

$stmt = $conn->prepare('SELECT id, position FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
$stmt->bind_param('is', $assignmentId, $title);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $taskId = (int)$existing['id'];
    $position = (int)$existing['position'];
} else {
    $stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM tasks WHERE assignment_id = ?');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $position = (int)$result['next_pos'];
    $stmt->close();
}

$description = '<div class="task-details"><p>Schreibe eine wirklich einfache rekursive Funktion, die die Summe aller Zahlen von <code>1</code> bis <code>n</code> berechnet.</p><p>Verwende eine Abbruchbedingung und einen rekursiven Aufruf. Fakultät und Multiplikation sind hier bewusst nicht gefragt.</p></div>';
$taskType = 'code_random_complex';
$taskText = 'Berechne rekursiv die Summe von 1 bis n.';
$questionText = 'Schreibe eine rekursive Loesung fuer die gegebene Zahl n.';
$codeTemplate = <<<'PYTHON'
def einfach_rekursion(n):
    if n == 0:
        return 0
    return n + einfach_rekursion(n - 1)

result = einfach_rekursion(values["n"])
PYTHON;
$solutionCode = <<<'PYTHON'
def einfach_rekursion(n):
    if n == 0:
        return 0
    return n + einfach_rekursion(n - 1)

result = einfach_rekursion(values["n"])
PYTHON;
$correctAnswer = 'result';
$variableOverrides = json_encode([
    [
        'inputs' => ['n' => '<random>'],
        'expected' => ['variable' => 'result']
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$randomizerCode = <<<'PYTHON'
import random

n = random.randint(4, 10)
values = {
    'n': n
}
PYTHON;
$maxAttempts = 10;
$iterationsCount = 5;
$showSolution = 1;
$showSolutionCode = 1;

if ($existing) {
    $stmt = $conn->prepare('UPDATE tasks SET position = ?, description = ?, task_type = ?, task_text = ?, question_text = ?, code_template = ?, solution_code = ?, correct_answer = ?, variable_overrides = ?, randomizer_code = ?, max_attempts = ?, iterations_count = ?, show_solution = ?, show_solution_code = ? WHERE id = ?');
    $stmt->bind_param(
        'isssssssssiiiii',
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
        $maxAttempts,
        $iterationsCount,
        $showSolution,
        $showSolutionCode,
        $taskId
    );
} else {
    $stmt = $conn->prepare('INSERT INTO tasks (assignment_id, title, description, position, task_type, task_text, question_text, code_template, solution_code, correct_answer, variable_overrides, randomizer_code, max_attempts, iterations_count, show_solution, show_solution_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param(
        'ississssssssiiii',
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
        $maxAttempts,
        $iterationsCount,
        $showSolution,
        $showSolutionCode
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
