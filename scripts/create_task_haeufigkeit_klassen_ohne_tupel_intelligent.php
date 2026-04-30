<?php
require __DIR__ . '/../config/database.php';

$db = getDbConnection();

// Create a new intelligent-vars variant of task 313 (without tuple usage).
$assignmentId = 29;
$position = 999;
$title = 'Haeufigkeitstabelle mit Klassen (Intelligent, ohne Tupel)';
$taskType = 'code';
$problemType = 'code_completion';
$taskText = 'Zaehle Werte in die Klassen 0-9, 10-19, 20-29, 30-39 und speichere das Ergebnis in haeufigkeit_klassen.';
$description = '<p>Erstelle die Klassenhaeufigkeiten fuer die Liste <code>werte</code>.</p><ul><li>Nutze <strong>keine Tupel</strong> und keine Klassenliste wie <code>[(0,9), ...]</code>.</li><li>Ordne jeden Wert ueber if/elif direkt den vier Klassen zu.</li><li>Werte ausserhalb der Klassen werden in <code>ausserhalb</code> gezaehlt.</li><li>Inklusive Grenzen verwenden (z. B. 0 bis 9 inklusive).</li></ul>';
$stoff = '<h4>Klassieren ohne Tupel</h4><ul><li>Pruefe jeden Wert mit einer if/elif-Kette.</li><li>Erhoehe pro Treffer den passenden Dictionary-Zaehler.</li><li>Nutze einen else-Zweig fuer <code>ausserhalb</code>.</li></ul>';

$codeTemplate = <<<'PY'
#INIT START
werte = []
#INIT END

haeufigkeit_klassen = {
    '0-9': 0,
    '10-19': 0,
    '20-29': 0,
    '30-39': 0,
    'ausserhalb': 0
}

# TODO: Werte ohne Tupel in die Klassen einordnen
# Hinweis: mit if/elif und inklusiven Grenzen arbeiten
PY;

$solutionCode = <<<'PY'
#INIT START
werte = []
#INIT END

haeufigkeit_klassen = {
    '0-9': 0,
    '10-19': 0,
    '20-29': 0,
    '30-39': 0,
    'ausserhalb': 0
}

for wert in werte:
    if 0 <= wert <= 9:
        haeufigkeit_klassen['0-9'] += 1
    elif 10 <= wert <= 19:
        haeufigkeit_klassen['10-19'] += 1
    elif 20 <= wert <= 29:
        haeufigkeit_klassen['20-29'] += 1
    elif 30 <= wert <= 39:
        haeufigkeit_klassen['30-39'] += 1
    else:
        haeufigkeit_klassen['ausserhalb'] += 1
PY;

$randomizerCode = <<<'PY'
import random

laenge = random.randint(12, 26)
werte = [random.randint(-8, 48) for _ in range(laenge)]

values = {
    'werte': werte
}
PY;

$testCases = json_encode([
    'mode' => 'vars',
    'tests' => 6,
    'inputs' => ['werte'],
    'outputs' => ['haeufigkeit_klassen']
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$maxAttempts = 10;
$showSolution = 0;
$showSolutionCode = 0;

$sql = 'INSERT INTO tasks (
    assignment_id,
    title,
    description,
    position,
    task_type,
    problem_type,
    task_text,
    code_template,
    solution_code,
    randomizer_code,
    test_cases,
    max_attempts,
    show_solution,
    show_solution_code,
    stoff
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$stmt = $db->prepare($sql);
if (!$stmt) {
    echo 'Prepare failed: ' . $db->error . PHP_EOL;
    exit(1);
}

$stmt->bind_param(
    'ississsssssiiis',
    $assignmentId,
    $title,
    $description,
    $position,
    $taskType,
    $problemType,
    $taskText,
    $codeTemplate,
    $solutionCode,
    $randomizerCode,
    $testCases,
    $maxAttempts,
    $showSolution,
    $showSolutionCode,
    $stoff
);

if (!$stmt->execute()) {
    echo 'Insert failed: ' . $stmt->error . PHP_EOL;
    exit(1);
}

$newId = (int)$db->insert_id;
echo 'OK: new task id=' . $newId . PHP_EOL;

echo 'assignment_id=' . $assignmentId . PHP_EOL;
echo 'title=' . $title . PHP_EOL;
