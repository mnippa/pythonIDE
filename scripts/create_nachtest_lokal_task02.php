<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDbConnection();

    $assignmentTitle = 'Nachtest lokal';
    $assignmentDescription = 'Einfache Python-Codeaufgaben fuer den Nachtest.';
    $createdBy = 1;

    $assignmentStmt = $db->prepare('SELECT id FROM assignments WHERE title = ? ORDER BY id DESC LIMIT 1');
    if (!$assignmentStmt) {
        throw new RuntimeException('Prepare failed (assignment select): ' . $db->error);
    }
    $assignmentStmt->bind_param('s', $assignmentTitle);
    $assignmentStmt->execute();
    $assignment = $assignmentStmt->get_result()->fetch_assoc();
    $assignmentStmt->close();

    if ($assignment) {
        $assignmentId = (int)$assignment['id'];
        $assignmentMode = 'reused';
    } else {
        $insertAssignmentStmt = $db->prepare('INSERT INTO assignments (title, description, created_by) VALUES (?, ?, ?)');
        if (!$insertAssignmentStmt) {
            throw new RuntimeException('Prepare failed (assignment insert): ' . $db->error);
        }
        $insertAssignmentStmt->bind_param('ssi', $assignmentTitle, $assignmentDescription, $createdBy);
        if (!$insertAssignmentStmt->execute()) {
            throw new RuntimeException('Assignment insert failed: ' . $insertAssignmentStmt->error);
        }
        $assignmentId = (int)$db->insert_id;
        $insertAssignmentStmt->close();
        $assignmentMode = 'created';
    }

    $title = 'EVA: Kubikzahl berechnen';
    $taskText = 'Nutze das EVA-Prinzip: Lies eine Zahl mit input() ein, berechne die Kubikzahl und gib das Ergebnis aus.';
    $questionText = 'Die Zahl soll per input() eingelesen werden. Speichere die Kubikzahl in ergebnis und gib ergebnis aus.';
    $taskType = 'code';
    $problemType = 'code_completion';
    $maxAttempts = 12;
    $showSolution = 1;
    $showSolutionCode = 1;
    $manualReviewRequired = 0;
    $stoff = '<p>EVA steht fuer Eingabe, Verarbeitung, Ausgabe. In dieser Aufgabe wird die Eingabe mit <code>input()</code> eingelesen, danach die Kubikzahl berechnet und das Ergebnis ausgegeben. Fuer die automatisierte Pruefung werden INIT-/Randomizer-Werte verwendet.</p>';
    $hint1 = 'Lies die Zahl mit input() ein und wandle sie in int um.';
    $hint2 = 'Kubikzahl bedeutet: zahl * zahl * zahl oder zahl ** 3.';
    $hint3 = 'Speichere das Resultat in der Variable ergebnis und gib es aus.';

    $codeTemplate = <<<'PY'
# EVA:
# E = Zahl per input() einlesen
# V = Kubikzahl berechnen
# A = Ergebnis ausgeben

#INIT START
zahl = 0
#INIT END

ergebnis = 0

# TODO: Lies die Zahl mit input() ein
# TODO: Berechne die Kubikzahl in ergebnis
# TODO: Gib ergebnis aus
PY;

    $solutionCode = <<<'PY'
#INIT START
zahl = 0
#INIT END

# Im Browser nutzt der User input(); bei automatischen Checks ohne stdin
# bleibt der von INIT/Randomizer gesetzte Wert erhalten.
try:
    zahl = int(input())
except Exception:
    pass

ergebnis = zahl ** 3
print(ergebnis)
PY;

    $randomizerCode = <<<'PY'
import random

values = {
    'zahl': random.randint(-9, 9)
}
PY;

    $testCases = json_encode([
        [
            'type' => 'intelligent',
            'mode' => 'vars',
            'tests' => 12,
            'inputs' => ['zahl'],
            'outputs' => ['ergebnis'],
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $description = ''
        . '<p>Setze das EVA-Prinzip um:</p>'
        . '<ul>'
        . '<li><strong>Eingabe:</strong> Lies die Zahl mit <code>input()</code> ein.</li>'
        . '<li><strong>Verarbeitung:</strong> Berechne die Kubikzahl von <code>zahl</code>.</li>'
        . '<li><strong>Ausgabe:</strong> Gib das berechnete Ergebnis aus.</li>'
        . '</ul>'
        . '<p>Der <code>#INIT</code>-Block dient der automatischen Pruefung und wird dort mit Zufallswerten befuellt.</p>'
        . '<p>Verwende am Ende die Variable <code>ergebnis</code>.</p>'
        . '<div class="test-requirements-section"><h3>Test-Anforderungen</h3>'
        . '<table class="test-requirements-table">'
        . '<thead><tr><th>Aspekt</th><th>Details</th></tr></thead>'
        . '<tbody>'
        . '<tr><td>INTELLIGENT</td><td>12 Zufallswerte fuer zahl, pruefe ergebnis</td></tr>'
        . '</tbody>'
        . '</table></div>';

    $positionStmt = $db->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_position FROM tasks WHERE assignment_id = ?');
    if (!$positionStmt) {
        throw new RuntimeException('Prepare failed (position select): ' . $db->error);
    }
    $positionStmt->bind_param('i', $assignmentId);
    $positionStmt->execute();
    $position = (int)$positionStmt->get_result()->fetch_assoc()['next_position'];
    $positionStmt->close();

    $taskStmt = $db->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    if (!$taskStmt) {
        throw new RuntimeException('Prepare failed (task select): ' . $db->error);
    }
    $taskStmt->bind_param('is', $assignmentId, $title);
    $taskStmt->execute();
    $existingTask = $taskStmt->get_result()->fetch_assoc();
    $taskStmt->close();

    if ($existingTask) {
        $taskId = (int)$existingTask['id'];
        $updateStmt = $db->prepare('UPDATE tasks SET
            task_text = ?,
            question_text = ?,
            description = ?,
            task_type = ?,
            problem_type = ?,
            position = ?,
            code_template = ?,
            solution_code = ?,
            randomizer_code = ?,
            test_cases = ?,
            hint1 = ?,
            hint2 = ?,
            hint3 = ?,
            stoff = ?,
            max_attempts = ?,
            show_solution = ?,
            show_solution_code = ?,
            manual_review_required = ?,
            updated_at = NOW()
            WHERE id = ?');
        if (!$updateStmt) {
            throw new RuntimeException('Prepare failed (task update): ' . $db->error);
        }
        $updateStmt->bind_param(
            'sssssissssssssiiiii',
            $taskText,
            $questionText,
            $description,
            $taskType,
            $problemType,
            $position,
            $codeTemplate,
            $solutionCode,
            $randomizerCode,
            $testCases,
            $hint1,
            $hint2,
            $hint3,
            $stoff,
            $maxAttempts,
            $showSolution,
            $showSolutionCode,
            $manualReviewRequired,
            $taskId
        );
        if (!$updateStmt->execute()) {
            throw new RuntimeException('Task update failed: ' . $updateStmt->error);
        }
        $updateStmt->close();
        $taskMode = 'updated';
    } else {
        $insertStmt = $db->prepare('INSERT INTO tasks (
            assignment_id,
            title,
            task_text,
            question_text,
            description,
            position,
            task_type,
            problem_type,
            code_template,
            solution_code,
            randomizer_code,
            test_cases,
            hint1,
            hint2,
            hint3,
            stoff,
            max_attempts,
            show_solution,
            show_solution_code,
            manual_review_required,
            created_at,
            updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )');
        if (!$insertStmt) {
            throw new RuntimeException('Prepare failed (task insert): ' . $db->error);
        }
        $insertStmt->bind_param(
            'issssissssssssssiiii',
            $assignmentId,
            $title,
            $taskText,
            $questionText,
            $description,
            $position,
            $taskType,
            $problemType,
            $codeTemplate,
            $solutionCode,
            $randomizerCode,
            $testCases,
            $hint1,
            $hint2,
            $hint3,
            $stoff,
            $maxAttempts,
            $showSolution,
            $showSolutionCode,
            $manualReviewRequired
        );
        if (!$insertStmt->execute()) {
            throw new RuntimeException('Task insert failed: ' . $insertStmt->error);
        }
        $taskId = (int)$db->insert_id;
        $insertStmt->close();
        $taskMode = 'created';
    }

    echo 'assignment_' . $assignmentMode . '=' . $assignmentId . PHP_EOL;
    echo 'task_' . $taskMode . '=' . $taskId . PHP_EOL;
    echo 'assignment_title=' . $assignmentTitle . PHP_EOL;
    echo 'task_title=' . $title . PHP_EOL;
    echo 'position=' . $position . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}