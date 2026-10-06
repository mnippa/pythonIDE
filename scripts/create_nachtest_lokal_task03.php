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

    $title = 'Funktion geschwindkeit: Strecke durch Zeit';
    $taskText = 'Schreibe die Funktion geschwindkeit(strecke, zeit). Gib die Durchschnittsgeschwindigkeit als Rueckgabewert zurueck.';
    $questionText = 'Nutze die Formel strecke / zeit (zeit in Sekunden) und gib den Wert mit return zurueck.';
    $taskType = 'code';
    $problemType = 'code_completion';
    $maxAttempts = 12;
    $showSolution = 1;
    $showSolutionCode = 1;
    $manualReviewRequired = 0;
    $stoff = '<p>Eine Funktion kapselt Berechnungen mit Parametern. Die durchschnittliche Geschwindigkeit ergibt sich aus <code>strecke / zeit</code>. Der berechnete Wert wird ueber <code>return</code> zurueckgegeben.</p>';
    $hint1 = 'Der Funktionsname soll genau geschwindkeit sein.';
    $hint2 = 'Parameter: strecke und zeit.';
    $hint3 = 'Rueckgabewert: strecke / zeit.';

    $codeTemplate = <<<'PY'
#INIT START
strecke = 120.0
zeit = 10.0
#INIT END

# Schreibe selbst die Funktionsdeklaration:
# def geschwindkeit(strecke, zeit):
#     ...

# Optionaler Schnelltest mit den INIT-Werten:
# ergebnis = geschwindkeit(strecke, zeit)
# print(ergebnis)
PY;

    $solutionCode = <<<'PY'
#INIT START
strecke = 120.0
zeit = 10.0
#INIT END

def geschwindkeit(strecke, zeit):
    return strecke / zeit
PY;

    $randomizerCode = <<<'PY'
import random

values = {
    'strecke': round(random.uniform(0.1, 10000.0), 3),
    'zeit': round(random.uniform(0.1, 3600.0), 3)
}
PY;

    $testCases = json_encode([
        'mode' => 'function',
        'tests' => 12,
        'tolerance' => 0.0001,
        'function' => [
            'name' => 'geschwindkeit',
            'params' => ['strecke', 'zeit'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $description = ''
        . '<p>Implementiere eine eigene Funktion zur Geschwindigkeitsberechnung.</p>'
        . '<ul>'
        . '<li>Funktionsname: <code>geschwindkeit</code></li>'
        . '<li>Parameter: <code>strecke</code>, <code>zeit</code></li>'
        . '<li>Rueckgabe: <code>strecke / zeit</code></li>'
        . '<li>Die Variablen <code>strecke</code> und <code>zeit</code> sind im <code>#INIT</code>-Block vorgegeben.</li>'
        . '</ul>'
        . '<div class="test-requirements-section"><h3>Test-Anforderungen</h3>'
        . '<table class="test-requirements-table">'
        . '<thead><tr><th>Aspekt</th><th>Details</th></tr></thead>'
        . '<tbody>'
        . '<tr><td>Funktionsname</td><td>geschwindkeit</td></tr>'
        . '<tr><td>Parameter</td><td>2 (strecke, zeit)</td></tr>'
        . '<tr><td>Checking</td><td>Intelligent Function: Rueckgabewert mit 12 Zufallstests</td></tr>'
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
            'sssssisssssssssiiii',
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