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

    $title = 'Zahlen von 100 bis 0 in 2er-Schritten';
    $taskText = 'Gib alle Zahlen von 100 bis 0 in 2er-Schritten untereinander aus.';
    $questionText = 'Nutze eine Schleife und gib 100, 98, 96 usw. jeweils in einer neuen Zeile aus.';
    $taskType = 'code';
    $problemType = 'code_completion';
    $maxAttempts = 12;
    $showSolution = 1;
    $showSolutionCode = 1;
    $manualReviewRequired = 0;
    $stoff = '<p>Schleifen wiederholen Anweisungen automatisch. Fuer feste Zahlenfolgen eignet sich besonders eine <code>for</code>-Schleife mit passender Schrittweite. Alternativ kann auch eine <code>while</code>-Schleife verwendet werden.</p>';
    $hint1 = 'Starte bei 100 und verringere den Wert jeweils um 2.';
    $hint2 = 'Jede Zahl soll mit <code>print()</code> in einer eigenen Zeile erscheinen.';
    $hint3 = 'Verwende eine Schleife, also <code>for</code> oder <code>while</code>.';

    $codeTemplate = <<<'PY'
# Gib alle Zahlen von 100 bis 0 in 2er-Schritten aus.
# Jede Zahl soll in einer eigenen Zeile stehen.

# TODO: Verwende eine Schleife.
PY;

    $solutionCode = <<<'PY'
for zahl in range(100, -1, -2):
    print(zahl)
PY;

    $expectedLines = [];
    for ($value = 100; $value >= 0; $value -= 2) {
        $expectedLines[] = (string)$value;
    }
    $expectedOutput = implode("\n", $expectedLines);

    $testCases = json_encode([
        [
            'type' => 'output',
            'input' => '',
            'expected' => [$expectedOutput],
            'expected_type' => 'text',
            'validation_mode' => 'strict',
            'case_sensitive' => true,
        ],
        [
            'type' => 'code_check',
            'keywords' => ['for', 'while'],
            'operator' => 'OR',
            'feedback' => 'Verwende eine for- oder while-Schleife.',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $description = ''
        . '<p>Gib die Zahlenfolge 100, 98, 96, ... bis 0 korrekt untereinander aus.</p>'
        . '<ul>'
        . '<li>Jede Zahl muss in einer eigenen Zeile stehen.</li>'
        . '<li>Verwende dafuer eine Schleife.</li>'
        . '</ul>'
        . '<div class="test-requirements-section"><h3>Test-Anforderungen</h3>'
        . '<table class="test-requirements-table">'
        . '<thead><tr><th>Aspekt</th><th>Details</th></tr></thead>'
        . '<tbody>'
        . '<tr><td>OUTPUT</td><td>Exakte Zahlenfolge von 100 bis 0 in 2er-Schritten, jeweils neue Zeile</td></tr>'
        . '<tr><td>Keyword-Pruefung</td><td>aktiv</td></tr>'
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
            'sssssissssssssiiii',
            $taskText,
            $questionText,
            $description,
            $taskType,
            $problemType,
            $position,
            $codeTemplate,
            $solutionCode,
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
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )');
        if (!$insertStmt) {
            throw new RuntimeException('Prepare failed (task insert): ' . $db->error);
        }
        $insertStmt->bind_param(
            'issssisssssssssiiii',
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