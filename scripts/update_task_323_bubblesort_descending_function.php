<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getPdoConnection();

$title = 'Bubblesort absteigend (Funktion gegeben)';
$taskText = 'Die Funktion bubblesort(zahlen) ist bereits vollstaendig implementiert und sortiert aktuell aufsteigend. Programmiere sie so um, dass sie die Liste absteigend sortiert. Verwende den Tausch weiter ueber die Hilfsvariable hilfe.';

$stoff = <<<'HTML'
<h4>Bubblesort</h4>
<p>
  Bubblesort ist ein einfaches Sortierverfahren. Dabei werden immer zwei
  benachbarte Elemente verglichen und bei Bedarf vertauscht.
</p>
<p>
  Nach jedem Durchlauf steht ein weiteres Element an seiner richtigen Position.
  Fuer eine <strong>absteigende</strong> Sortierung muessen groessere Werte nach
  vorne wandern.
</p>
<p>
  Das Verfahren eignet sich gut, um Vergleiche, Schleifen und Tauschoperationen
  zu verstehen.
</p>
HTML;

$description = '<div class="test-requirements-section"><h3>Test-Anforderungen</h3>'
    . '<table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody>'
    . '<tr><td>Funktionsname</td><td>bubblesort</td></tr>'
    . '<tr><td>Parameter</td><td>1</td></tr>'
    . '<tr><td>Keyword-Pruefung</td><td>aktiv</td></tr>'
    . '</tbody></table></div>';

$codeTemplate = <<<'PYTHON'
#INIT START
zahlen = [42, 7, 19, 88, 3, 55, 71, 12, 64, 28, 90, 1, 37, 46, 82, 15, 60, 24, 99, 33]
#INIT END

def bubblesort(zahlen):
    for i in range(len(zahlen) - 1):
        for j in range(len(zahlen) - 1 - i):
            if zahlen[j] > zahlen[j + 1]:
                hilfe = zahlen[j]
                zahlen[j] = zahlen[j + 1]
                zahlen[j + 1] = hilfe
    return zahlen

print(bubblesort(zahlen))
PYTHON;

$solutionCode = <<<'PYTHON'
#INIT START
zahlen = [42, 7, 19, 88, 3, 55, 71, 12, 64, 28, 90, 1, 37, 46, 82, 15, 60, 24, 99, 33]
#INIT END

def bubblesort(zahlen):
    for i in range(len(zahlen) - 1):
        for j in range(len(zahlen) - 1 - i):
            if zahlen[j] < zahlen[j + 1]:
                hilfe = zahlen[j]
                zahlen[j] = zahlen[j + 1]
                zahlen[j + 1] = hilfe
    return zahlen

print(bubblesort(zahlen))
PYTHON;

$hint1 = <<<'TEXT'
Die Funktion ist schon fertig. Fuer absteigende Sortierung musst du nur die Vergleichsrichtung anpassen, damit groessere Werte nach vorne wandern.
TEXT;

$hint2 = <<<'TEXT'
Aktuell wird getauscht, wenn der linke Wert groesser ist als der rechte. Fuer absteigende Sortierung muss genau der umgekehrte Fall zum Tausch fuehren.
TEXT;

$hint3 = <<<'TEXT'
Aendere nur die if-Bedingung im inneren Schleifendurchlauf:

if zahlen[j] < zahlen[j + 1]:
    ...

Der Tausch mit der Hilfsvariable hilfe bleibt gleich.
TEXT;

$expectedOutput = '[99, 90, 88, 82, 71, 64, 60, 55, 46, 42, 37, 33, 28, 24, 19, 15, 12, 7, 3, 1]';

$testCases = json_encode([
    [
        'type' => 'intelligent',
        'mode' => 'function',
        'tests' => 8,
        'function' => [
            'name' => 'bubblesort',
            'params' => ['zahlen']
        ]
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

$stmt = $pdo->prepare('UPDATE tasks SET
    title = ?,
    task_text = ?,
    stoff = ?,
    description = ?,
    code_template = ?,
    solution_code = ?,
    hint1 = ?,
    hint2 = ?,
    hint3 = ?,
    expected_output = ?,
    test_cases = ?,
    randomizer_code = ?,
    problem_type = ?,
    task_type = ?,
    max_attempts = ?,
    show_solution = ?,
    show_solution_code = ?,
    variable_overrides = ?
WHERE id = 323');

$stmt->execute([
    $title,
    $taskText,
    $stoff,
    $description,
    $codeTemplate,
    $solutionCode,
    $hint1,
    $hint2,
    $hint3,
    $expectedOutput,
    $testCases,
    $randomizerCode,
    'code_completion',
    'code',
    10,
    0,
    0,
    null
]);

$check = $pdo->query('SELECT id, title, LEFT(task_text, 180) AS task_text, LEFT(description, 220) AS description FROM tasks WHERE id = 323')->fetch(PDO::FETCH_ASSOC);
echo 'Updated task 323' . PHP_EOL;
print_r($check);
