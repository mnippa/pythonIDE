<?php
declare(strict_types=1);

/**
 * Append the mixed binary arithmetic task to live assignment 48.
 * Run with PHP CLI; repeated execution leaves an existing task unchanged.
 * Use --update-existing to repair the task before students have started it.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('BETA_LIVE_ALLOW_WRITE', true);
require_once __DIR__ . '/../config/database.beta_live.local.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = getBetaLiveDbConnection();
$assignmentId = 48;
$title = 'Binärrechnen: addieren, multiplizieren und dividieren';
$previousTitle = 'Binaerrechnen: +1, multiplizieren und dividieren';
$description = '8 Iterationen in zufälliger Reihenfolge: 2 Multiplikationen, 3 Additionen (zweimal +1, einmal +2) und 3 Divisionen. Bei Multiplikation und Division ist der Wert 2, 4, 8, 16 oder 32. Divisionen gehen ohne Rest auf. Antworte als Binärzahl ohne 0b-Präfix, Leerzeichen oder führende Nullen; die Bitbreite ist nicht begrenzt. Insgesamt stehen 3 Fehlversuche für alle 8 Iterationen zusammen zur Verfügung.';
$taskText = <<<'TEXT'
Rechnen im Binärsystem
Wende die unten geforderte Operation an.
+ Addiere den Wert zur Binärzahl hinzu.
* Multipliziere die Binärzahl mit dem Wert.
/ Teile die Binärzahl durch den Wert.
TEXT;
$randomizerCode = <<<'PYTHON'
import random

_aufgaben = [("+", 1), ("+", 1), ("+", 2),
             ("*", None), ("*", None),
             ("/", None), ("/", None), ("/", None)]
for _eingabe in __previous_inputs:
    _aufgaben.remove((_eingabe["rechenart"],
                      _eingabe["wert"] if _eingabe["rechenart"] == "+" else None))
rechenart, _inkrement = random.choice(_aufgaben)
wert = _inkrement if rechenart == "+" else random.choice([2, 4, 8, 16, 32])
if rechenart == "+":
    _uebertrag = random.randint(2, 5)
    _zahl = (random.randint(1, 7) << _uebertrag) + (1 << _uebertrag) - wert
elif rechenart == "/":
    _zahl = random.randint(8, 63) * wert
else:
    _zahl = random.randint(16, 127)
binaerzahl = format(_zahl, "b")
PYTHON;
$solutionCode = <<<'PYTHON'
zahl = int({binaerzahl}, 2)
if {rechenart} == "+":
    ergebnis = zahl + {wert}
elif {rechenart} == "*":
    ergebnis = zahl * {wert}
elif {rechenart} == "/":
    ergebnis = zahl // {wert}
else:
    raise ValueError("Unbekannte Rechenart")
result = format(ergebnis, "b")
PYTHON;
$overrides = json_encode([
    [
        'iteration_context' => true,
        'inputs' => [
            'binaerzahl' => '<random>',
            'rechenart' => '<random>',
            'wert' => '<random>',
        ],
        'expected' => ['variable' => 'result'],
    ],
], JSON_THROW_ON_ERROR);

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT id FROM assignments WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new RuntimeException('Live assignment 48 does not exist');
    }

    $stmt = $conn->prepare('SELECT id, task_text FROM tasks WHERE assignment_id = ? AND title IN (?, ?) FOR UPDATE');
    $stmt->bind_param('iss', $assignmentId, $title, $previousTitle);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $taskId = (int)$existing['id'];
        $action = 'EXISTS';
        if (in_array('--update-existing', $argv, true)) {
            $stmt = $conn->prepare('SELECT id FROM user_tasks WHERE task_id = ? LIMIT 1 FOR UPDATE');
            $stmt->bind_param('i', $taskId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('Task already has student progress; migrate saved inputs before renaming variables');
            }
            $taskText = str_replace(
                '+ Addiere zur Zahl 1 hinzu.',
                '+ Addiere den Wert zur Binärzahl hinzu.',
                $existing['task_text']
            );
            $stmt = $conn->prepare(
                'UPDATE tasks SET title = ?, description = ?, task_text = ?, randomizer_code = ?, solution_code = ?,
                    code_template = ?, variable_overrides = ?, updated_at = NOW()
                 WHERE id = ? AND assignment_id = ?'
            );
            $stmt->bind_param(
                'sssssssii',
                $title, $description, $taskText, $randomizerCode, $solutionCode, $solutionCode, $overrides,
                $taskId, $assignmentId
            );
            $stmt->execute();
            $action = 'UPDATED';
        }
    } else {
        $stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_position FROM tasks WHERE assignment_id = ?');
        $stmt->bind_param('i', $assignmentId);
        $stmt->execute();
        $position = (int)$stmt->get_result()->fetch_assoc()['next_position'];

        $stmt = $conn->prepare(
            "INSERT INTO tasks (
                assignment_id, position, title, description, task_text, task_type,
                task_difficulty, randomizer_code, solution_code, code_template, correct_answer,
                variable_overrides, iterations_count, max_attempts,
                show_solution, show_solution_code, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'code_random_complex',
                'medium', ?, ?, ?, 'result', ?, 8, 3, 1, 0, NOW(), NOW())"
        );
        $stmt->bind_param(
            'iisssssss',
            $assignmentId, $position, $title, $description, $taskText,
            $randomizerCode, $solutionCode, $solutionCode, $overrides
        );
        $stmt->execute();
        $taskId = (int)$conn->insert_id;
        $action = 'CREATED';
    }
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}

$stmt = $conn->prepare(
    'SELECT id, assignment_id, position, title, task_type, iterations_count,
        max_attempts, show_solution, show_solution_code
     FROM tasks WHERE id = ?'
);
$stmt->bind_param('i', $taskId);
$stmt->execute();
echo $action . PHP_EOL;
echo json_encode($stmt->get_result()->fetch_assoc(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
