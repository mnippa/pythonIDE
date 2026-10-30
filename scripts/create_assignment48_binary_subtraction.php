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
$title = 'Binärrechnen: schriftliche Subtraktion mit 8 Bit';
$description = 'Subtrahiere die zweite Binärzahl von der ersten mithilfe des Zweierkomplements. Beide Zahlen werden mit 8 Bit dargestellt; die erste ist größer als die zweite. Antworte in dieser Reihenfolge: Zweierkomplement der zweiten Zahl;Ergebnis der Subtraktion. Beide Angaben müssen genau 8 Bit einschließlich führender Nullen enthalten. Leerzeichen sind erlaubt, auch innerhalb der Bitfolgen. Verwende einen Strichpunkt als Trennzeichen und kein 0b-Präfix. Insgesamt stehen 4 Fehlversuche für alle 3 Iterationen zusammen zur Verfügung; richtige Antworten verbrauchen keinen Versuch.';
$taskText = 'Berechne schriftlich binaerzahl1 − binaerzahl2 im 8-Bit-Zahlenraum. Gib das Zweierkomplement von binaerzahl2 und die Differenz mit einem Strichpunkt getrennt an.';
$hint1 = 'Bilde das Zweierkomplement der zweiten Zahl: Invertiere alle 8 Bits und addiere 1.';
$hint2 = 'Addiere das Zweierkomplement zur ersten Zahl. Verwirf den Übertrag über die achte Stelle hinaus und gib das Ergebnis mit genau 8 Bit an.';
$randomizerCode = <<<'PYTHON'
import random

_erste = random.randint(2, 255)
_zweite = random.randint(1, _erste - 1)
binaerzahl1 = format(_erste, "08b")
binaerzahl2 = format(_zweite, "08b")
PYTHON;
$solutionCode = <<<'PYTHON'
erste = int({binaerzahl1}, 2)
zweite = int({binaerzahl2}, 2)
komplement = ((zweite ^ 255) + 1) & 255
ergebnis = (erste + komplement) & 255
result = format(komplement, "08b") + ";" + format(ergebnis, "08b")
PYTHON;
$overrides = json_encode([[
    'answer_format' => 'binary_pair_8',
    'inputs' => ['binaerzahl1' => '<random>', 'binaerzahl2' => '<random>'],
    'expected' => ['variable' => 'result'],
]], JSON_THROW_ON_ERROR);

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
                'result', ?, ?, ?, 3, 4, 1, 0, NOW(), NOW())"
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

echo $action . ' task_id=' . $taskId . PHP_EOL;
