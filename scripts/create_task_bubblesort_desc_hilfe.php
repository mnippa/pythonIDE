<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getPdoConnection();
$nextPosition = (int)$pdo->query('SELECT COALESCE(MAX(position), 0) + 1 FROM tasks WHERE assignment_id = 29')->fetchColumn();

$title = 'Bubblesort absteigend (F: mit Hilfsvariable)';
$taskText = 'Sortiere die Liste zahlen mit dem Bubblesort-Algorithmus absteigend. Verwende fuer jeden Tausch die Hilfsvariable hilfe.';
$stoff = <<<'HTML'
<h4>Bubblesort</h4>
<p>
  Bubblesort ist ein einfaches Sortierverfahren. Dabei werden immer zwei
  benachbarte Elemente verglichen und bei Bedarf vertauscht.
</p>
<p>
  Nach jedem Durchlauf ist ein weiteres Element an seiner richtigen Position.
  Fuer eine <strong>absteigende</strong> Sortierung muessen groessere Werte nach
  vorne wandern.
</p>
<p>
  Das Verfahren eignet sich vor allem zum Verstehen von Vergleichen,
  Schleifen und Tauschoperationen, ist aber fuer grosse Datenmengen nicht sehr effizient.
</p>
HTML;

$description = '<div class="test-requirements-section"><h3>Test-Anforderungen</h3>'
    . '<table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody>'
    . '<tr><td>INPUTS erwartet</td><td>1</td></tr>'
    . '<tr><td>Input-Variablen</td><td>zahlen</td></tr>'
    . '<tr><td>Checking</td><td>zahlen</td></tr>'
    . '<tr><td>Erforderliche Keywords</td><td>hilfe, for, if</td></tr>'
    . '<tr><td>Verbotene Keywords</td><td>sort(, sorted(</td></tr>'
    . '</tbody></table></div>';

$codeTemplate = <<<'PYTHON'
#INIT START
zahlen = [42, 7, 19, 88, 3, 55, 71, 12, 64, 28, 90, 1, 37, 46, 82, 15, 60, 24, 99, 33]
#INIT END

for i in range(len(zahlen) - 1):
    for j in range(len(zahlen) - 1 - i):
        # Tausche mit der Hilfsvariable hilfe, damit am Ende absteigend sortiert ist
        pass

print(zahlen)
PYTHON;

$solutionCode = <<<'PYTHON'
#INIT START
zahlen = [42, 7, 19, 88, 3, 55, 71, 12, 64, 28, 90, 1, 37, 46, 82, 15, 60, 24, 99, 33]
#INIT END

for i in range(len(zahlen) - 1):
    for j in range(len(zahlen) - 1 - i):
        if zahlen[j] < zahlen[j + 1]:
            hilfe = zahlen[j]
            zahlen[j] = zahlen[j + 1]
            zahlen[j + 1] = hilfe

print(zahlen)
PYTHON;

$hint1 = <<<'TEXT'
Vergleiche immer zwei benachbarte Werte. Fuer absteigende Sortierung musst du tauschen, wenn der linke Wert kleiner ist als der rechte.
TEXT;

$hint2 = <<<'TEXT'
Die Bedingung im inneren Schleifendurchlauf lautet:

if zahlen[j] < zahlen[j + 1]:
    ...

Dann musst du die beiden Elemente mit der Hilfsvariable hilfe vertauschen.
TEXT;

$hint3 = <<<'TEXT'
Der Tausch mit Hilfsvariable sieht so aus:

hilfe = zahlen[j]
zahlen[j] = zahlen[j + 1]
zahlen[j + 1] = hilfe

Kombiniere das mit der if-Bedingung im inneren Schleifendurchlauf.
TEXT;

$expectedOutput = '[99, 90, 88, 82, 71, 64, 60, 55, 46, 42, 37, 33, 28, 24, 19, 15, 12, 7, 3, 1]';

$testCases = json_encode([
    [
        'type' => 'intelligent',
        'mode' => 'vars',
        'tests' => 8,
        'inputs' => ['zahlen'],
        'outputs' => ['zahlen']
    ],
    [
        'type' => 'code_check',
        'keywords' => ['hilfe', 'for', 'if'],
        'forbidden' => ['sort(', 'sorted('],
        'operator' => 'AND',
        'feedback' => ''
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$randomizerCode = <<<'PYTHON'
import random

zahlen = random.sample(range(-50, 151), 20)

values = {
    'zahlen': zahlen
}
PYTHON;

$stmt = $pdo->prepare('INSERT INTO tasks (
    assignment_id,
    title,
    description,
    position,
    max_attempts,
    iterations_count,
    show_solution,
    show_solution_code,
    min_keywords_required,
    problem_type,
    code_template,
    hint1,
    hint2,
    hint3,
    stoff,
    expected_output,
    test_cases,
    solution_code,
    task_type,
    task_text,
    question_text,
    image_url,
    correct_answer,
    variable_overrides,
    randomizer_code
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

$stmt->execute([
    29,
    $title,
    $description,
    $nextPosition,
    10,
    1,
    0,
    0,
    0,
    'code_completion',
    $codeTemplate,
    $hint1,
    $hint2,
    $hint3,
    $stoff,
    $expectedOutput,
    $testCases,
    $solutionCode,
    'code',
    $taskText,
    '',
    '',
    '',
    null,
    $randomizerCode
]);

$newId = (int)$pdo->lastInsertId();
$check = $pdo->query('SELECT id, title, position FROM tasks WHERE id = ' . $newId)->fetch(PDO::FETCH_ASSOC);

echo 'Created task: ' . $check['id'] . ' | P' . $check['position'] . ' | ' . $check['title'] . PHP_EOL;
