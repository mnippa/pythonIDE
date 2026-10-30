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
$title = 'Binärrechnen: zwei 7-stellige Binärzahlen schriftlich addieren';
$description = 'Addiere die beiden positiven Binärzahlen schriftlich mit Überträgen. Beide Summanden haben genau 7 Stellen und beginnen mit 1. Gib das vollständige Ergebnis als Binärzahl ohne 0b-Präfix, Leerzeichen oder führende Nullen ein. Ein Übertrag in die achte Stelle darf nicht abgeschnitten werden. In jeder der 3 Iterationen werden beide Zahlen zufällig neu erzeugt. Insgesamt stehen 2 Fehlversuche für alle 3 Iterationen zusammen zur Verfügung; richtige Antworten verbrauchen keinen Versuch.';
$taskText = 'Addiere binaerzahl1 und binaerzahl2 schriftlich im Binärsystem. Notiere dabei die Überträge und gib hier die vollständige Summe als Binärzahl ein.';
$randomizerCode = <<<'PYTHON'
import random

binaerzahl1 = format(random.randint(64, 127), "07b")
binaerzahl2 = format(random.randint(64, 127), "07b")
PYTHON;
$solutionCode = <<<'PYTHON'
result = format(int({binaerzahl1}, 2) + int({binaerzahl2}, 2), "b")
PYTHON;
$overrides = json_encode([
    [
        'inputs' => ['binaerzahl1' => '<random>', 'binaerzahl2' => '<random>'],
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
                correct_answer, variable_overrides, iterations_count, max_attempts,
                show_solution, show_solution_code, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'code_random_complex', 'medium', ?, ?, ?,
                'result', ?, 3, 2, 1, 0, NOW(), NOW())"
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
        max_attempts, show_solution_code FROM tasks WHERE id = ?'
);
$stmt->bind_param('i', $taskId);
$stmt->execute();
echo $action . PHP_EOL;
echo json_encode($stmt->get_result()->fetch_assoc(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
