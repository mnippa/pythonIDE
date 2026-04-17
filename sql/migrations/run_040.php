<?php
/**
 * Migration 040: Create local recap assignments from workshop assignment.
 *
 * Source:
 *   assignment #24 (Workshop Kontrollstrukturen und erste Programme)
 *
 * Targets:
 *   A) Recap Theorie
 *   B) Recap Python
 */

require_once __DIR__ . '/../../config/database.php';

function getAdminCreatorId040(mysqli $conn): int {
    $res = $conn->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return 1;
}

function getOrCreateAssignment040(mysqli $conn, string $title, string $description): int {
    $sel = $conn->prepare('SELECT id FROM assignments WHERE title = ? LIMIT 1');
    if (!$sel) {
        throw new Exception('Prepare failed (assignment select): ' . $conn->error);
    }
    $sel->bind_param('s', $title);
    $sel->execute();
    $existing = $sel->get_result()->fetch_assoc();
    $sel->close();

    if ($existing) {
        return (int)$existing['id'];
    }

    $creatorId = getAdminCreatorId040($conn);
    $difficulty = 'beginner';
    $isActive = 1;
    $codeTemplate = null;

    $ins = $conn->prepare('INSERT INTO assignments (title, description, code_template, created_by, is_active, difficulty, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
    if (!$ins) {
        throw new Exception('Prepare failed (assignment insert): ' . $conn->error);
    }
    $ins->bind_param('sssiss', $title, $description, $codeTemplate, $creatorId, $isActive, $difficulty);

    if (!$ins->execute()) {
        throw new Exception('Execute failed (assignment insert): ' . $ins->error);
    }

    $id = (int)$conn->insert_id;
    $ins->close();

    return $id;
}

function taskExistsInAssignment040(mysqli $conn, int $assignmentId, string $title): bool {
    $stmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Prepare failed (task exists): ' . $conn->error);
    }

    $stmt->bind_param('is', $assignmentId, $title);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

function cloneTasksByPositionRange040(
    mysqli $conn,
    int $sourceAssignmentId,
    int $targetAssignmentId,
    int $minPos,
    int $maxPos,
    string $label
): int {
    $srcStmt = $conn->prepare('SELECT * FROM tasks WHERE assignment_id = ? AND position BETWEEN ? AND ? ORDER BY position ASC');
    if (!$srcStmt) {
        throw new Exception('Prepare failed (source tasks): ' . $conn->error);
    }

    $srcStmt->bind_param('iii', $sourceAssignmentId, $minPos, $maxPos);
    $srcStmt->execute();
    $result = $srcStmt->get_result();

    $insertSql = 'INSERT INTO tasks (
        assignment_id,
        title,
        description,
        task_text,
        position,
        task_type,
        task_difficulty,
        question_text,
        image_url,
        correct_answer,
        variable_overrides,
        problem_type,
        folderstructure,
        allowDownload,
        allow_code_ui_web_edit,
        code_template,
        randomizer_code,
        hint,
        hint1,
        hint2,
        hint3,
        stoff,
        expected_output,
        test_cases,
        solution_code,
        generator_code,
        max_attempts,
        iterations_count,
        show_solution,
        show_solution_code,
        min_keywords_required,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

    $insStmt = $conn->prepare($insertSql);
    if (!$insStmt) {
        throw new Exception('Prepare failed (insert cloned task): ' . $conn->error);
    }

    $copied = 0;
    $newPos = 1;

    while ($row = $result->fetch_assoc()) {
        $title = (string)$row['title'];
        if (taskExistsInAssignment040($conn, $targetAssignmentId, $title)) {
            echo "⚠ Skipped ({$label}, exists): {$title}\n";
            continue;
        }

        $assignmentId = $targetAssignmentId;
        $description = $row['description'];
        $taskText = $row['task_text'];
        $position = $newPos++;
        $taskType = $row['task_type'];
        $taskDifficulty = $row['task_difficulty'];
        $questionText = $row['question_text'];
        $imageUrl = $row['image_url'];
        $correctAnswer = $row['correct_answer'];
        $variableOverrides = $row['variable_overrides'];
        $problemType = $row['problem_type'];
        $folderstructure = isset($row['folderstructure']) ? (int)$row['folderstructure'] : 0;
        $allowDownload = isset($row['allowDownload']) ? (int)$row['allowDownload'] : 0;
        $allowCodeUiWebEdit = isset($row['allow_code_ui_web_edit']) ? (int)$row['allow_code_ui_web_edit'] : 0;
        $codeTemplate = $row['code_template'];
        $randomizerCode = $row['randomizer_code'];
        $hint = $row['hint'];
        $hint1 = $row['hint1'];
        $hint2 = $row['hint2'];
        $hint3 = $row['hint3'];
        $stoff = $row['stoff'];
        $expectedOutput = $row['expected_output'];
        $testCases = $row['test_cases'];
        $solutionCode = $row['solution_code'];
        $generatorCode = $row['generator_code'];
        $maxAttempts = isset($row['max_attempts']) ? (int)$row['max_attempts'] : 10;
        $iterationsCount = isset($row['iterations_count']) ? (int)$row['iterations_count'] : null;
        $showSolution = isset($row['show_solution']) ? (int)$row['show_solution'] : 0;
        $showSolutionCode = isset($row['show_solution_code']) ? (int)$row['show_solution_code'] : 0;
        $minKeywordsRequired = isset($row['min_keywords_required']) ? (int)$row['min_keywords_required'] : null;

        $paramTypes = str_repeat('s', 31);
        $insStmt->bind_param(
            $paramTypes,
            $assignmentId,
            $title,
            $description,
            $taskText,
            $position,
            $taskType,
            $taskDifficulty,
            $questionText,
            $imageUrl,
            $correctAnswer,
            $variableOverrides,
            $problemType,
            $folderstructure,
            $allowDownload,
            $allowCodeUiWebEdit,
            $codeTemplate,
            $randomizerCode,
            $hint,
            $hint1,
            $hint2,
            $hint3,
            $stoff,
            $expectedOutput,
            $testCases,
            $solutionCode,
            $generatorCode,
            $maxAttempts,
            $iterationsCount,
            $showSolution,
            $showSolutionCode,
            $minKeywordsRequired
        );

        if (!$insStmt->execute()) {
            throw new Exception('Execute failed (insert cloned task): ' . $insStmt->error);
        }

        $copied++;
        echo "✓ Cloned ({$label}) task #{$row['id']} -> #" . (int)$conn->insert_id . ": {$title}\n";
    }

    $insStmt->close();
    $srcStmt->close();

    return $copied;
}

function getTaskCountForAssignment040(mysqli $conn, int $assignmentId): int {
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM tasks WHERE assignment_id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed (task count): ' . $conn->error);
    }
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0);
}

try {
    $conn = getDbConnection();
    echo "Running Migration 040: create recap assignments...\n";

    $sourceAssignmentId = 24;

    $srcCheck = $conn->prepare('SELECT id, title FROM assignments WHERE id = ? LIMIT 1');
    if (!$srcCheck) {
        throw new Exception('Prepare failed (source check): ' . $conn->error);
    }
    $srcCheck->bind_param('i', $sourceAssignmentId);
    $srcCheck->execute();
    $srcRow = $srcCheck->get_result()->fetch_assoc();
    $srcCheck->close();

    if (!$srcRow) {
        throw new Exception('Source assignment #24 not found. Run migration 039 first.');
    }

    $recapTheorieId = getOrCreateAssignment040(
        $conn,
        'A) Recap Theorie',
        'Recap mit Fokus auf Codeverstaendnis und Kontrollstrukturen (code_random_complex).'
    );

    $recapPythonId = getOrCreateAssignment040(
        $conn,
        'B) Recap Python',
        'Recap mit Fokus auf Programmieraufgaben (Bedingungen, Schleifen, Listen, Funktionen).'
    );

    echo "Target assignment A) Recap Theorie: #{$recapTheorieId}\n";
    echo "Target assignment B) Recap Python: #{$recapPythonId}\n";

    $copiedPython = 0;
    $copiedTheorie = 0;

    $pythonCount = getTaskCountForAssignment040($conn, $recapPythonId);
    if ($pythonCount > 0) {
        echo "⚠ Skip cloning into B) Recap Python (#{$recapPythonId}) because it already has {$pythonCount} tasks.\n";
    } else {
        $copiedPython = cloneTasksByPositionRange040($conn, $sourceAssignmentId, $recapPythonId, 1, 16, 'Recap Python');
    }

    $theorieCount = getTaskCountForAssignment040($conn, $recapTheorieId);
    if ($theorieCount > 0) {
        echo "⚠ Skip cloning into A) Recap Theorie (#{$recapTheorieId}) because it already has {$theorieCount} tasks.\n";
    } else {
        $copiedTheorie = cloneTasksByPositionRange040($conn, $sourceAssignmentId, $recapTheorieId, 17, 23, 'Recap Theorie');
    }

    echo "\nSummary:\n";
    echo "- Copied to B) Recap Python: {$copiedPython} tasks\n";
    echo "- Copied to A) Recap Theorie: {$copiedTheorie} tasks\n";

    echo "\n✅ Migration 040: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 040 failed: " . $e->getMessage() . "\n";
    exit(1);
}
