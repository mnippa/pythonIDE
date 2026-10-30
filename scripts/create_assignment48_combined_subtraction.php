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
$title = 'Binärrechnen: kombinierte Subtraktion zweier Dezimalzahlen';
$description = 'Gegeben sind zwei positive Dezimalzahlen im 8-Bit-Zahlenraum. Die erste Zahl ist größer als die zweite. Wandle beide Zahlen in Binärzahlen um und subtrahiere die zweite von der ersten mithilfe des Zweierkomplements. Gib genau fünf Angaben in dieser Reihenfolge ein, jeweils durch einen Strichpunkt getrennt: Zahl 1 binär; Zahl 2 binär; Zweierkomplement von Zahl 2; Ergebnis binär; Ergebnis dezimal. Die ersten vier Angaben müssen jeweils genau 8 Bit einschließlich führender Nullen enthalten. Leerzeichen sind erlaubt, auch innerhalb der Bitfolgen. Verwende kein 0b-Präfix. Ein Durchlauf mit insgesamt 5 Fehlversuchen.';
$taskText = 'Berechne zahl1 − zahl2 schriftlich im Binärsystem. Antwortformat: Zahl 1 binär;Zahl 2 binär;Zweierkomplement von Zahl 2;Ergebnis binär;Ergebnis dezimal.';
$hint1 = 'Wandle beide Dezimalzahlen in 8-Bit-Binärzahlen um. Ergänze bei Bedarf links Nullen.';
$hint2 = 'Invertiere die 8 Bits der zweiten Zahl und addiere 1. Addiere dieses Zweierkomplement zur ersten Binärzahl und verwirf den Übertrag über die achte Stelle hinaus.';
$hint3 = 'Wandle das binäre Subtraktionsergebnis wieder in eine Dezimalzahl um. Prüfe, ob es mit zahl1 − zahl2 übereinstimmt.';
$randomizerCode = <<<'PYTHON'
import random

zahl1 = random.randint(2, 255)
zahl2 = random.randint(1, zahl1 - 1)
PYTHON;
$solutionCode = <<<'PYTHON'
erste = {zahl1}
zweite = {zahl2}
komplement = ((zweite ^ 255) + 1) & 255
ergebnis = (erste + komplement) & 255
result = ";".join([
    format(erste, "08b"),
    format(zweite, "08b"),
    format(komplement, "08b"),
    format(ergebnis, "08b"),
    str(ergebnis)
])
PYTHON;
$overrides = json_encode([[
    'answer_format' => 'binary_subtraction_steps_8',
    'inputs' => ['zahl1' => '<random>', 'zahl2' => '<random>'],
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
                correct_answer, variable_overrides, hint1, hint2, hint3,
                iterations_count, max_attempts, show_solution, show_solution_code,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'code_random_complex', 'hard', ?, ?, ?,
                'result', ?, ?, ?, ?, 1, 5, 1, 0, NOW(), NOW())"
        );
        $stmt->bind_param(
            'iissssssssss',
            $assignmentId, $position, $title, $description, $taskText,
            $randomizerCode, $solutionCode, $solutionCode, $overrides, $hint1, $hint2, $hint3
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
