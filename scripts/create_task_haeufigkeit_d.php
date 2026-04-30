<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getPdoConnection();

// Check assignment 29 tasks
$rows = $pdo->query('SELECT id, title, position FROM tasks WHERE assignment_id=29 ORDER BY position')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo $row['id'] . ' pos=' . $row['position'] . ' ' . $row['title'] . PHP_EOL;
}
$nextPos = $rows[count($rows)-1]['position'] + 1;
echo 'Next pos: ' . $nextPos . PHP_EOL;

// Get task 319 as base
$base = $pdo->query('SELECT * FROM tasks WHERE id=319')->fetch(PDO::FETCH_ASSOC);

// New template: print loop without tuple unpacking
$code_template = <<<'PYTHON'
#INIT START
werte = [3, 7, 0, 9, 5, 2, 8, 1, 6, 4,
         12, 17, 10, 19, 15, 11, 18, 13, 16, 14,
         23, 28, 20, 29, 25, 22, 27, 24, 26, 21,
         33, 38, 30, 39, 35, 32, 37, 34, 36, 31,
         -3, -1, 45, 50, -5, 42, 9, 19, 29, 39]
#INIT END

haeufigkeit_klassen = {
    '0-9': 0,
    '10-19': 0,
    '20-29': 0,
    '30-39': 0,
    'ausserhalb': 0
}

# Ergaenze hier die Auswertung
for wert in werte:
    pass  # TODO: Klasse bestimmen und Zaehler erhoehen

print("Haeufigkeiten:")
for klasse in haeufigkeit_klassen:
    print(f"  {klasse}: {haeufigkeit_klassen[klasse]}")
PYTHON;

$hint1 = <<<'TEXT'
Gehe jeden Wert aus der Liste durch. Prüfe mit if/elif, in welche Klasse er fällt, und erhöhe den passenden Zähler:

for wert in werte:
    if 0 <= wert <= 9:
        haeufigkeit_klassen['0-9'] += 1
    elif ...

Denke daran: Werte, die in keine Klasse passen, zählen zu 'ausserhalb'.
TEXT;

$hint2 = <<<'TEXT'
Die ersten zwei Klassen sehen so aus:

for wert in werte:
    if 0 <= wert <= 9:
        haeufigkeit_klassen['0-9'] += 1
    elif 10 <= wert <= 19:
        haeufigkeit_klassen['10-19'] += 1
    elif ...  # weiter so für 20-29 und 30-39

Ergänze die fehlenden elif-Zweige und den else-Zweig für 'ausserhalb'.
TEXT;

$hint3 = <<<'TEXT'
Alle vier Klassen sind bereits gefüllt – nur 'ausserhalb' fehlt noch:

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
        ...  # Hier den Zähler für 'ausserhalb' erhöhen
TEXT;

$solution_code = <<<'PYTHON'
#INIT START
werte = [3, 7, 0, 9, 5, 2, 8, 1, 6, 4,
         12, 17, 10, 19, 15, 11, 18, 13, 16, 14,
         23, 28, 20, 29, 25, 22, 27, 24, 26, 21,
         33, 38, 30, 39, 35, 32, 37, 34, 36, 31,
         -3, -1, 45, 50, -5, 42, 9, 19, 29, 39]
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

print("Haeufigkeiten:")
for klasse in haeufigkeit_klassen:
    print(f"  {klasse}: {haeufigkeit_klassen[klasse]}")
PYTHON;

$expected_output = "Haeufigkeiten:\n  0-9: 12\n  10-19: 10\n  20-29: 10\n  30-39: 10\n  ausserhalb: 8";

$title      = 'Haeufigkeit mit Klassen (D: mit Daten, Ausgabe ohne Tupel)';
$task_text  = $base['task_text'];
$description = $base['description'];
$assignmentId = 29;
$position   = $nextPos;
$taskType   = $base['task_type'] ?? 'code';
$problemType = $base['problem_type'] ?? 'output';
$stoff      = $base['stoff'] ?? '';
$maxAttempts = $base['max_attempts'] ?? 0;
$iterCount  = $base['iterations_count'] ?? 1;
$showSolution = $base['show_solution'] ?? 1;
$showSolutionCode = $base['show_solution_code'] ?? 1;
$minKw      = $base['min_keywords_required'] ?? 0;
$testCases  = $base['test_cases'] ?? '';
$variableOverrides = null;
$randomizerCode = $base['randomizer_code'] ?? '';
$questionText = $base['question_text'] ?? '';
$imageUrl   = $base['image_url'] ?? '';
$correctAnswer = $base['correct_answer'] ?? '';

$stmt = $pdo->prepare("INSERT INTO tasks
    (assignment_id, title, description, position, max_attempts, iterations_count,
     show_solution, show_solution_code, min_keywords_required, problem_type,
     code_template, hint1, hint2, hint3, stoff, expected_output, test_cases,
     solution_code, task_type, task_text, question_text, image_url, correct_answer,
     variable_overrides, randomizer_code)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$stmt->execute([
    $assignmentId, $title, $description, $position, $maxAttempts, $iterCount,
    $showSolution, $showSolutionCode, $minKw, $problemType,
    $code_template, $hint1, $hint2, $hint3, $stoff, $expected_output, $testCases,
    $solution_code, $taskType, $task_text, $questionText, $imageUrl, $correctAnswer,
    $variableOverrides, $randomizerCode
]);

$newId = $pdo->lastInsertId();
echo "Created task D: id=$newId title=$title\n";
