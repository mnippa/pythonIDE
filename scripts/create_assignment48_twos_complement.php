<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('BETA_LIVE_ALLOW_WRITE', true);
require_once __DIR__ . '/../config/database.beta_live.local.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = getBetaLiveDbConnection();
$assignmentId = 48;
$title = 'Binärrechnen: Zweierkomplement mit 8 Bit';
$description = 'Bilde das Zweierkomplement des gegebenen 8-Bit-Musters. Die Binärzahl ist gerade und ungleich null. Gib das Ergebnis mit genau 8 Bit ein, einschließlich führender Nullen und ohne 0b-Präfix oder Leerzeichen. In jeder der 3 Iterationen wird eine neue Zufallszahl erzeugt. Insgesamt stehen 2 Fehlversuche für alle 3 Iterationen zusammen zur Verfügung; richtige Antworten verbrauchen keinen Versuch.';
$taskText = 'Bilde zu binaerzahl das Zweierkomplement im 8-Bit-Zahlenraum. Gib das Ergebnis mit genau 8 Bit an.';
$hint1 = 'Schritt 1: Invertiere alle 8 Bits. Ersetze jede 0 durch eine 1 und jede 1 durch eine 0.';
$hint2 = 'Schritt 2: Addiere zur invertierten Bitfolge 1. Beachte die Überträge und behalte genau 8 Bit.';
$randomizerCode = <<<'PYTHON'
import random

binaerzahl = format(2 * random.randint(1, 127), "08b")
PYTHON;
$solutionCode = <<<'PYTHON'
zahl = int({binaerzahl}, 2)
invertiert = zahl ^ 255
result = format((invertiert + 1) & 255, "08b")
PYTHON;
$overrides = json_encode([
    [
        'inputs' => ['binaerzahl' => '<random>'],
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

    $stmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ?');
    $stmt->bind_param('is', $assignmentId, $title);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing) {
        $taskId = (int)$existing['id'];
        $action = 'EXISTS';
    } else {
        $stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_position FROM tasks WHERE assignment_id = ?');
        $stmt->bind_param('i', $assignmentId);
        $stmt->execute();
        $position = (int)$stmt->get_result()->fetch_assoc()['next_position'];
        $stmt = $conn->prepare(
            "INSERT INTO tasks (
                assignment_id, position, title, description, task_text, task_type,
                task_difficulty, randomizer_code, solution_code, code_template,
                correct_answer, variable_overrides, hint1, hint2,
                iterations_count, max_attempts, show_solution, show_solution_code,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'code_random_complex', 'medium', ?, ?, ?,
                'result', ?, ?, ?, 3, 2, 1, 0, NOW(), NOW())"
        );
        $stmt->bind_param(
            'iisssssssss',
            $assignmentId, $position, $title, $description, $taskText,
            $randomizerCode, $solutionCode, $solutionCode, $overrides, $hint1, $hint2
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
        max_attempts, hint1, hint2, show_solution_code FROM tasks WHERE id = ?'
);
$stmt->bind_param('i', $taskId);
$stmt->execute();
echo $action . PHP_EOL;
echo json_encode($stmt->get_result()->fetch_assoc(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
