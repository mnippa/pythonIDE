<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$assignmentId = 29;
$title = 'Temperaturdaten aus Datei auswerten';

function generateTemperatureSeries(int $count): array
{
    mt_srand(290426);

    $values = [];
    for ($index = 0; $index < $count; $index++) {
        $season = sin(($index % 365) / 365 * 2 * M_PI);
        $trend = sin(($index % 30) / 30 * 2 * M_PI) * 1.2;
        $noise = mt_rand(-18, 18) / 10;
        $temp = 14.5 + ($season * 10.5) + $trend + $noise;
        $temp = max(-9.5, min(36.8, $temp));
        $values[] = round($temp, 1);
    }

    return $values;
}

function computeStats(array $temperatures): array
{
    $sum = 0.0;
    $minimum = $temperatures[0];
    $maximum = $temperatures[0];
    $countInRange = 0;

    foreach ($temperatures as $value) {
        $sum += $value;
        if ($value < $minimum) {
            $minimum = $value;
        }
        if ($value > $maximum) {
            $maximum = $value;
        }
        if ($value >= 15.0 && $value <= 20.0) {
            $countInRange++;
        }
    }

    return [
        'durchschnitt' => round($sum / count($temperatures), 1),
        'minimum' => $minimum,
        'maximum' => $maximum,
        'anzahl_15_bis_20' => $countInRange,
    ];
}

function renderTemperatureModule(array $temperatures): string
{
    $lines = [
        '# Automatisch generierte Temperaturdaten (ca. 1500 Werte)',
        '# Jeder Wert ist in Grad Celsius mit einer Nachkommastelle gespeichert.',
        'temperaturen = [',
    ];

    $chunks = array_chunk($temperatures, 12);
    foreach ($chunks as $chunk) {
        $formatted = array_map(static fn(float $value): string => number_format($value, 1, '.', ''), $chunk);
        $lines[] = '    ' . implode(', ', $formatted) . ',';
    }

    $lines[] = ']';
    $lines[] = '';

    return implode(PHP_EOL, $lines);
}

$temperatureSeries = generateTemperatureSeries(1500);

$checkLists = [
    [12.0, 15.0, 18.5, 20.0, 21.0],
    [14.5, 15.0, 15.5, 19.5, 20.0, 22.0],
    [9.0, 10.0, 14.9, 20.0, 20.1],
    [15.0, 16.0, 17.0, 18.0, 19.0, 20.0],
];

$variableTests = [];
foreach ($checkLists as $list) {
    $variableTests[] = [
        'type' => 'variable',
        'init_vars' => ['temperaturen' => $list],
        'expected_vars' => computeStats($list),
    ];
}

$testCases = $variableTests;
$testCases[] = [
    'type' => 'code_check',
    'keywords' => ['import', 'for', 'if'],
    'operator' => 'AND',
    'feedback' => 'Verwende einen Import sowie mindestens eine for-Schleife und eine if-Abfrage.'
];

$description = <<<'TEXT'
Importiere die Liste `temperaturen` aus der Datei `temperaturen.py` und werte die Messreihe mit einer Schleife aus.

Berechne die folgenden Variablen exakt mit diesen Namen:
- `durchschnitt` (auf 1 Nachkommastelle gerundet)
- `minimum`
- `maximum`
- `anzahl_15_bis_20`

`anzahl_15_bis_20` soll zählen, wie viele Werte zwischen 15.0 und 20.0 Grad liegen, inklusive beider Grenzen.
TEXT;

$taskText = <<<'TEXT'
Importiere die Liste `temperaturen` aus `temperaturen.py`.
Berechne anschließend mit einer Schleife den Durchschnitt, das Minimum, das Maximum und die Anzahl der Werte zwischen 15.0 und 20.0 Grad (inklusive).
TEXT;

$questionText = <<<'TEXT'
Nutze `from temperaturen import temperaturen` und ermittle die Variablen `durchschnitt`, `minimum`, `maximum` und `anzahl_15_bis_20`.
TEXT;

$codeTemplate = <<<'PY'
#INIT Start#
from temperaturen import temperaturen
#INIT End#

summe = 0.0
minimum = temperaturen[0]
maximum = temperaturen[0]
anzahl_15_bis_20 = 0

for wert in temperaturen:
    # TODO: Summe aktualisieren
    # TODO: Minimum und Maximum mit if pruefen
    # TODO: Werte im Bereich 15.0 bis 20.0 zaehlen
    pass

durchschnitt = 0.0
# TODO: Durchschnitt auf 1 Nachkommastelle runden
PY;

$solutionCode = <<<'PY'
from temperaturen import temperaturen

summe = 0.0
minimum = temperaturen[0]
maximum = temperaturen[0]
anzahl_15_bis_20 = 0

for wert in temperaturen:
    summe += wert
    if wert < minimum:
        minimum = wert
    if wert > maximum:
        maximum = wert
    if 15.0 <= wert <= 20.0:
        anzahl_15_bis_20 += 1

durchschnitt = round(summe / len(temperaturen), 1)
PY;

$hint1 = 'Importiere zuerst mit `from temperaturen import temperaturen` die Liste aus der Zusatzdatei.';
$hint2 = 'Initialisiere `minimum` und `maximum` mit dem ersten Listenwert, bevor die Schleife startet.';
$hint3 = 'Zaehle den Bereich mit `if 15.0 <= wert <= 20.0:` und runde den Durchschnitt mit `round(..., 1)`.';

$pdo = getPdoConnection();

$selectStmt = $pdo->prepare('SELECT id, position FROM tasks WHERE assignment_id = :assignment_id AND title = :title LIMIT 1');
$selectStmt->execute([
    ':assignment_id' => $assignmentId,
    ':title' => $title,
]);
$existingTask = $selectStmt->fetch();

if ($existingTask) {
    $taskId = (int)$existingTask['id'];
    $position = (int)$existingTask['position'];

    $updateSql = <<<'SQL'
UPDATE tasks
SET description = :description,
    position = :position,
    max_attempts = :max_attempts,
    iterations_count = :iterations_count,
    show_solution = :show_solution,
    show_solution_code = :show_solution_code,
    min_keywords_required = :min_keywords_required,
    problem_type = :problem_type,
    code_template = :code_template,
    hint1 = :hint1,
    hint2 = :hint2,
    hint3 = :hint3,
    stoff = :stoff,
    expected_output = :expected_output,
    test_cases = :test_cases,
    solution_code = :solution_code,
    task_type = :task_type,
    task_text = :task_text,
    question_text = :question_text,
    image_url = :image_url,
    correct_answer = :correct_answer,
    variable_overrides = :variable_overrides,
    randomizer_code = :randomizer_code,
    folderstructure = :folderstructure,
    allowDownload = :allow_download,
    allow_code_ui_web_edit = :allow_code_ui_web_edit,
    task_difficulty = :task_difficulty
WHERE id = :id
SQL;

    $stmt = $pdo->prepare($updateSql);
} else {
    $positionStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_position FROM tasks WHERE assignment_id = :assignment_id');
    $positionStmt->execute([':assignment_id' => $assignmentId]);
    $position = (int)$positionStmt->fetchColumn();

    $insertSql = <<<'SQL'
INSERT INTO tasks (
    assignment_id, title, description, position, max_attempts, iterations_count,
    show_solution, show_solution_code, min_keywords_required, problem_type,
    code_template, hint1, hint2, hint3, stoff, expected_output, test_cases,
    solution_code, task_type, task_text, question_text, image_url,
    correct_answer, variable_overrides, randomizer_code, folderstructure,
    allowDownload, allow_code_ui_web_edit, task_difficulty
) VALUES (
    :assignment_id, :title, :description, :position, :max_attempts, :iterations_count,
    :show_solution, :show_solution_code, :min_keywords_required, :problem_type,
    :code_template, :hint1, :hint2, :hint3, :stoff, :expected_output, :test_cases,
    :solution_code, :task_type, :task_text, :question_text, :image_url,
    :correct_answer, :variable_overrides, :randomizer_code, :folderstructure,
    :allow_download, :allow_code_ui_web_edit, :task_difficulty
)
SQL;

    $stmt = $pdo->prepare($insertSql);
}

$params = [
    ':assignment_id' => $assignmentId,
    ':title' => $title,
    ':description' => $description,
    ':position' => $position,
    ':max_attempts' => 8,
    ':iterations_count' => 1,
    ':show_solution' => 1,
    ':show_solution_code' => 0,
    ':min_keywords_required' => null,
    ':problem_type' => 'code_completion',
    ':code_template' => $codeTemplate,
    ':hint1' => $hint1,
    ':hint2' => $hint2,
    ':hint3' => $hint3,
    ':stoff' => 'Import von Python-Modulen, Schleifen, if-Abfragen, Durchschnitt, Minimum, Maximum, Zaehlen von Werten in Bereichen',
    ':expected_output' => '',
    ':test_cases' => json_encode($testCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ':solution_code' => $solutionCode,
    ':task_type' => 'code',
    ':task_text' => $taskText,
    ':question_text' => $questionText,
    ':image_url' => null,
    ':correct_answer' => null,
    ':variable_overrides' => null,
    ':randomizer_code' => null,
    ':folderstructure' => 1,
    ':allow_download' => 0,
    ':allow_code_ui_web_edit' => 1,
    ':task_difficulty' => 'medium',
];

if ($existingTask) {
    $params[':id'] = $taskId;
}

$stmt->execute($params);

if (!$existingTask) {
    $taskId = (int)$pdo->lastInsertId();
}

$taskFolder = __DIR__ . '/../storage/tasks/folders/task_' . $taskId;
if (!is_dir($taskFolder) && !mkdir($taskFolder, 0755, true) && !is_dir($taskFolder)) {
    throw new RuntimeException('Task folder could not be created: ' . $taskFolder);
}

$temperatureModulePath = $taskFolder . '/temperaturen.py';
$policyPath = $taskFolder . '/.file-policies.json';

file_put_contents($temperatureModulePath, renderTemperatureModule($temperatureSeries));
file_put_contents(
    $policyPath,
    json_encode([
        'files' => [
            'temperaturen.py' => ['read_only' => true],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'Task ID: ' . $taskId . PHP_EOL;
echo 'Assignment ID: ' . $assignmentId . PHP_EOL;
echo 'Position: ' . $position . PHP_EOL;
echo 'Folder: ' . $taskFolder . PHP_EOL;
echo 'Data file: ' . $temperatureModulePath . PHP_EOL;
echo 'Values generated: ' . count($temperatureSeries) . PHP_EOL;
echo 'Mode: ' . ($existingTask ? 'updated' : 'created') . PHP_EOL;