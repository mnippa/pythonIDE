<?php
/**
 * Create/refresh assignment:
 * DataCamp Intro to Python - Kapitel 1-3 (Code-Fokus)
 *
 * - 15 Aufgaben analog zu den Practice-Chapters
 * - Mehr Codeaufgaben statt nur MC/Lueckentext
 * - Bestehendes Assignment mit gleichem Titel wird aktualisiert (Tasks neu gesetzt)
 */

require_once __DIR__ . '/../config/database.php';

function getAdminUserId(mysqli $conn): int {
    $res = $conn->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return 1;
}

function getOrCreateAssignment(mysqli $conn, string $title, string $description): int {
    $sel = $conn->prepare('SELECT id FROM assignments WHERE title = ? LIMIT 1');
    if (!$sel) {
        throw new Exception('Prepare failed (assignment select): ' . $conn->error);
    }
    $sel->bind_param('s', $title);
    $sel->execute();
    $existing = $sel->get_result()->fetch_assoc();
    $sel->close();

    if ($existing) {
        $id = (int)$existing['id'];
        $upd = $conn->prepare('UPDATE assignments SET description = ?, difficulty = ?, is_active = 1, updated_at = NOW() WHERE id = ?');
        if (!$upd) {
            throw new Exception('Prepare failed (assignment update): ' . $conn->error);
        }
        $difficulty = 'beginner';
        $upd->bind_param('ssi', $description, $difficulty, $id);
        if (!$upd->execute()) {
            throw new Exception('Execute failed (assignment update): ' . $upd->error);
        }
        $upd->close();
        return $id;
    }

    $adminId = getAdminUserId($conn);
    $difficulty = 'beginner';
    $isActive = 1;
    $ins = $conn->prepare('INSERT INTO assignments (title, description, created_by, is_active, difficulty, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    if (!$ins) {
        throw new Exception('Prepare failed (assignment insert): ' . $conn->error);
    }
    $ins->bind_param('ssiis', $title, $description, $adminId, $isActive, $difficulty);
    if (!$ins->execute()) {
        throw new Exception('Execute failed (assignment insert): ' . $ins->error);
    }
    $id = (int)$conn->insert_id;
    $ins->close();

    return $id;
}

function clearAssignmentTasks(mysqli $conn, int $assignmentId): void {
    $ids = [];
    $res = $conn->query('SELECT id FROM tasks WHERE assignment_id = ' . (int)$assignmentId);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
    }

    if (!empty($ids)) {
        $idList = implode(',', $ids);
        if (!$conn->query('DELETE FROM task_options WHERE task_id IN (' . $idList . ')')) {
            throw new Exception('Delete task_options failed: ' . $conn->error);
        }
    }

    if (!$conn->query('DELETE FROM tasks WHERE assignment_id = ' . (int)$assignmentId)) {
        throw new Exception('Delete tasks failed: ' . $conn->error);
    }
}

function insertTask(mysqli $conn, array $task): int {
    $sql = 'INSERT INTO tasks (
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
        hint1,
        hint2,
        hint3,
        stoff,
        expected_output,
        test_cases,
        solution_code,
        max_attempts,
        iterations_count,
        show_solution,
        show_solution_code,
        min_keywords_required,
        manual_review_required,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed (insert task): ' . $conn->error);
    }

    $assignmentId = (string)$task['assignment_id'];
    $title = (string)$task['title'];
    $description = (string)($task['description'] ?? '');
    $taskText = (string)($task['task_text'] ?? '');
    $position = (string)$task['position'];
    $taskType = (string)$task['task_type'];
    $taskDifficulty = (string)($task['task_difficulty'] ?? 'medium');
    $questionText = (string)($task['question_text'] ?? '');
    $imageUrl = (string)($task['image_url'] ?? '');
    $correctAnswer = (string)($task['correct_answer'] ?? '');
    $variableOverrides = (string)($task['variable_overrides'] ?? 'null');
    $problemType = (string)($task['problem_type'] ?? 'code_completion');
    $folderstructure = (string)($task['folderstructure'] ?? 0);
    $allowDownload = (string)($task['allowDownload'] ?? 0);
    $allowCodeUiWebEdit = (string)($task['allow_code_ui_web_edit'] ?? 0);
    $codeTemplate = (string)($task['code_template'] ?? '');
    $randomizerCode = (string)($task['randomizer_code'] ?? '');
    $hint1 = (string)($task['hint1'] ?? '');
    $hint2 = (string)($task['hint2'] ?? '');
    $hint3 = (string)($task['hint3'] ?? '');
    $stoff = (string)($task['stoff'] ?? '');
    $expectedOutput = (string)($task['expected_output'] ?? '');
    $testCases = (string)($task['test_cases'] ?? 'null');
    $solutionCode = (string)($task['solution_code'] ?? '');
    $maxAttempts = (string)($task['max_attempts'] ?? 5);
    $iterationsCount = (string)($task['iterations_count'] ?? 1);
    $showSolution = (string)($task['show_solution'] ?? 1);
    $showSolutionCode = (string)($task['show_solution_code'] ?? 1);
    $minKeywordsRequired = (string)($task['min_keywords_required'] ?? 0);
    $manualReviewRequired = (string)($task['manual_review_required'] ?? 0);

    $types = str_repeat('s', 30);
    $stmt->bind_param(
        $types,
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
        $hint1,
        $hint2,
        $hint3,
        $stoff,
        $expectedOutput,
        $testCases,
        $solutionCode,
        $maxAttempts,
        $iterationsCount,
        $showSolution,
        $showSolutionCode,
        $minKeywordsRequired,
        $manualReviewRequired
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed (insert task): ' . $stmt->error . ' | Task: ' . $title);
    }

    $taskId = (int)$conn->insert_id;
    $stmt->close();
    return $taskId;
}

function insertOptions(mysqli $conn, int $taskId, array $options): void {
    $stmt = $conn->prepare('INSERT INTO task_options (task_id, option_text, image_url, is_correct, order_num) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('Prepare failed (insert options): ' . $conn->error);
    }

    $order = 1;
    foreach ($options as $opt) {
        $text = (string)$opt['text'];
        $image = '';
        $isCorrect = !empty($opt['is_correct']) ? 1 : 0;
        $stmt->bind_param('issii', $taskId, $text, $image, $isCorrect, $order);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed (insert option): ' . $stmt->error . ' | Task ID: ' . $taskId);
        }
        $order++;
    }

    $stmt->close();
}

try {
    $conn = getDbConnection();

    $assignmentTitle = 'DataCamp Intro Python Kapitel 1-3 (Code-Fokus)';
    $assignmentDescription = 'Lokales Assignment basierend auf DataCamp Introduction to Python, Practice Chapter 1-3. Schwerpunkt auf eigenstaendigem Coden mit automatischer Pruefung (variable/function/output/code_check), inkl. Loesungen.';

    $assignmentId = getOrCreateAssignment($conn, $assignmentTitle, $assignmentDescription);
    clearAssignmentTasks($conn, $assignmentId);

    $tasks = [];
    $p = 1;

    // Kapitel 1: Python Basics
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Typ von 5.0 bestimmen',
        'task_text' => 'Bestimme den Typ von 5.0 und speichere den Typnamen in typname.',
        'description' => 'Nutze type() und den Typnamen als String, z. B. ueber __name__.',
        'stoff' => 'Datentypen in Python: float, int, str.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'zahl = 5.0\n# Speichere den Typnamen als String in typname\ntypname = ""\nprint(typname)',
        'solution_code' => 'zahl = 5.0\ntypname = type(zahl).__name__\nprint(typname)',
        'test_cases' => json_encode([
            ['type' => 'output', 'expected' => ['float']],
            ['type' => 'code_check', 'keywords' => ['type\s*\(', '__name__'], 'operator' => 'AND', 'feedback' => 'Nutze type(...).__name__ fuer den Typnamen als String.']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1,
        'hint1' => 'type(5.0) liefert den Typ.',
        'hint2' => 'Mit __name__ bekommst du den Typnamen als Text.',
        'hint3' => 'typname = type(zahl).__name__'
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Variablen mit = erstellen',
        'task_text' => 'Lege die Variablen alter = 21 und pi = 3.14 an und gib beide Werte in einer Zeile aus.',
        'description' => 'Nutze den Zuweisungsoperator =, erstelle beide Variablen und print(alter, pi).',
        'stoff' => 'Variablenzuweisung und Ausgabe mit print().',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => '# Lege beide Variablen an\n\n# Gib beide Werte in einer Zeile aus\nprint(alter, pi)',
        'solution_code' => 'alter = 21\npi = 3.14\nprint(alter, pi)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['alter' => 21, 'pi' => 3.14]],
            ['type' => 'output', 'expected' => ['21 3.14', '21 3.1400000000000001']],
            ['type' => 'code_check', 'keywords' => ['alter\s*=\s*21', 'pi\s*=\s*3\.14'], 'operator' => 'AND', 'feedback' => 'Erstelle beide Variablen mit =.']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'String-Typ pruefen',
        'task_text' => 'Setze p auf "7.2" und speichere den Typnamen in typname.',
        'description' => 'Die Zahl steht als Text in Anfuehrungszeichen. Pruefe den Datentyp korrekt.',
        'stoff' => 'Unterschied zwischen String und Zahl.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'p = "7.2"\ntypname = ""\nprint(typname)',
        'solution_code' => 'p = "7.2"\ntypname = type(p).__name__\nprint(typname)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['typname' => 'str']],
            ['type' => 'output', 'expected' => ['str']],
            ['type' => 'code_check', 'keywords' => ['type\s*\('], 'operator' => 'AND', 'feedback' => 'Nutze type().']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Potenzoperator anwenden',
        'task_text' => 'Berechne 6 hoch 4 und speichere das Ergebnis in erg.',
        'description' => 'Nutze den Potenzoperator ** fuer die Berechnung.',
        'stoff' => 'Arithmetische Operatoren, Potenzen.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'x = 6\ny = 4\nerg = 0\nprint(erg)',
        'solution_code' => 'x = 6\ny = 4\nerg = x ** y\nprint(erg)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['erg' => 1296]],
            ['type' => 'output', 'expected' => ['1296']],
            ['type' => 'code_check', 'keywords' => ['\*\*'], 'operator' => 'AND', 'feedback' => 'Verwende den Potenzoperator **.']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Division berechnen',
        'task_text' => 'Berechne 3/8 und speichere das Ergebnis in result.',
        'description' => 'Nutze den Divisionsoperator /.',
        'stoff' => 'Division in Python liefert bei / einen float.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'result = 0\nprint(result)',
        'solution_code' => 'result = 3 / 8\nprint(result)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['result' => 0.375]],
            ['type' => 'output', 'expected' => ['0.375']],
            ['type' => 'code_check', 'keywords' => ['/'], 'operator' => 'AND', 'feedback' => 'Nutze die Division /.']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    // Kapitel 2: Python Lists
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Liste mit p und 3 bauen',
        'task_text' => 'Setze p = 0 und baue die Liste werte mit [p, 3].',
        'description' => 'Erstelle die Liste explizit und speichere sie in werte.',
        'stoff' => 'Listenliteral und Variablen in Listen.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'p = 0\nwerte = []\nprint(werte)',
        'solution_code' => 'p = 0\nwerte = [p, 3]\nprint(werte)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['werte' => [0, 3]]],
            ['type' => 'output', 'expected' => ['[0, 3]']]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Indexwerte addieren',
        'task_text' => 'Berechne aus x die Summe x[5] + x[2] und speichere sie in result.',
        'description' => 'Nutze Indexzugriff mit eckigen Klammern.',
        'stoff' => 'Indexzugriff auf Listen.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'x = [6, 5, 8, 2, 4, 0]\nresult = 0\nprint(result)',
        'solution_code' => 'x = [6, 5, 8, 2, 4, 0]\nresult = x[5] + x[2]\nprint(result)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['result' => 8]],
            ['type' => 'output', 'expected' => ['8']],
            ['type' => 'code_check', 'keywords' => ['x\[5\]', 'x\[2\]'], 'operator' => 'AND', 'feedback' => 'Greife auf beide geforderten Indizes zu.']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Index von a finden',
        'task_text' => 'Finde den Index von "a" in x und speichere ihn in idx.',
        'description' => 'Nutze die Listenmethode index().',
        'stoff' => 'Listenmethoden, speziell list.index().',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'x = ["d", "a", "t", "a"]\nidx = -1\nprint(idx)',
        'solution_code' => 'x = ["d", "a", "t", "a"]\nidx = x.index("a")\nprint(idx)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['idx' => 1]],
            ['type' => 'output', 'expected' => ['1']],
            ['type' => 'code_check', 'keywords' => ['\.index\s*\('], 'operator' => 'AND', 'feedback' => 'Nutze index() statt manuell zu zaehlen.']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Listen verketten',
        'task_text' => 'Erzeuge aus x die neue Liste y = x + [True, False].',
        'description' => 'Nutze Listenaddition, um Elemente anzuhaengen.',
        'stoff' => 'Listenverkettung mit +.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'x = [1, -1]\ny = []\nprint(y)',
        'solution_code' => 'x = [1, -1]\ny = x + [True, False]\nprint(y)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['y' => [1, -1, true, false]]],
            ['type' => 'output', 'expected' => ['[1, -1, True, False]']],
            ['type' => 'code_check', 'keywords' => ['\+\s*\[\s*True\s*,\s*False\s*\]'], 'operator' => 'AND', 'feedback' => 'Nutze x + [True, False].']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Slicing mit [1:3]',
        'task_text' => 'Speichere den Slice x[1:3] in teil.',
        'description' => 'Der rechte Index ist exklusiv.',
        'stoff' => 'List Slicing: Start inklusive, Ende exklusiv.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'x = [10, 12, 3, 7, 8]\nteil = []\nprint(teil)',
        'solution_code' => 'x = [10, 12, 3, 7, 8]\nteil = x[1:3]\nprint(teil)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['teil' => [12, 3]]],
            ['type' => 'output', 'expected' => ['[12, 3]']],
            ['type' => 'code_check', 'keywords' => ['\[\s*1\s*:\s*3\s*\]'], 'operator' => 'AND', 'feedback' => 'Nutze den geforderten Slice [1:3].']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    // Kapitel 3: Functions and Packages
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Begriff pip',
        'task_text' => 'Waehle die korrekte Aussage zu pip.',
        'description' => 'Concept-Check: pip ist das Paketverwaltungssystem fuer Python.',
        'stoff' => 'Pakete installieren und verwalten mit pip.',
        'position' => $p++,
        'task_type' => 'single_choice',
        'problem_type' => 'multiple_choice',
        'question_text' => 'Was ist pip?',
        'max_attempts' => 4,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 0,
        'options' => [
            ['text' => 'Ein Python-Paket fuer Data Science.', 'is_correct' => 0],
            ['text' => 'Eine Python-Distribution fuer Data Science.', 'is_correct' => 0],
            ['text' => 'Ein Paketverwaltungssystem fuer Python.', 'is_correct' => 1]
        ]
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'index("Feb") auf Monatsliste',
        'task_text' => 'Bestimme die Position von "Feb" in x und speichere sie in idx.',
        'description' => 'Nutze die Methode index auf der Monatsliste.',
        'stoff' => 'Listenmethode index mit Stringwerten.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'x = ["Apr", "May", "Mar", "Jan", "Feb"]\nidx = -1\nprint(idx)',
        'solution_code' => 'x = ["Apr", "May", "Mar", "Jan", "Feb"]\nidx = x.index("Feb")\nprint(idx)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['idx' => 4]],
            ['type' => 'output', 'expected' => ['4']],
            ['type' => 'code_check', 'keywords' => ['\.index\s*\('], 'operator' => 'AND', 'feedback' => 'Nutze index().']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Import-Stil beurteilen',
        'task_text' => 'Waehle die korrekte Aussage zu Import-Stilen.',
        'description' => 'Concept-Check wie im Practice-Chapter: General imports sind gegenueber selektiven imports zu bevorzugen.',
        'stoff' => 'Importstile in Python: general import vs selective import.',
        'position' => $p++,
        'task_type' => 'single_choice',
        'problem_type' => 'multiple_choice',
        'question_text' => 'Welche Aussage ist korrekt?',
        'max_attempts' => 4,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 0,
        'options' => [
            ['text' => 'Beide Importstile sind immer gleichwertig bevorzugt.', 'is_correct' => 0],
            ['text' => 'General imports sind gegenueber selective imports bevorzugt.', 'is_correct' => 1],
            ['text' => 'Selective imports sind gegenueber general imports bevorzugt.', 'is_correct' => 0]
        ]
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'capitalize auf String anwenden',
        'task_text' => 'Verbinde die Strings zu x und speichere x.capitalize() in titel.',
        'description' => 'x soll den Wert "python exercise" enthalten, titel den kapitalisierten Text.',
        'stoff' => 'String-Methoden, insbesondere capitalize().',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'x = "python" + " exercise"\ntitel = ""\nprint(titel)',
        'solution_code' => 'x = "python" + " exercise"\ntitel = x.capitalize()\nprint(titel)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['titel' => 'Python exercise']],
            ['type' => 'output', 'expected' => ['Python exercise']],
            ['type' => 'code_check', 'keywords' => ['\.capitalize\s*\('], 'operator' => 'AND', 'feedback' => 'Nutze capitalize().']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Minimum einer Liste finden',
        'task_text' => 'Speichere das Minimum von q in min_wert.',
        'description' => 'Nutze die eingebaute Funktion min() fuer die gegebene Liste q.',
        'stoff' => 'Built-in Funktionen, min() und max().',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'q = [48, 34, 6, 30, 13, 33]\nmin_wert = 0\nprint(min_wert)',
        'solution_code' => 'q = [48, 34, 6, 30, 13, 33]\nmin_wert = min(q)\nprint(min_wert)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['min_wert' => 6]],
            ['type' => 'output', 'expected' => ['6']],
            ['type' => 'code_check', 'keywords' => ['min\s*\('], 'operator' => 'AND', 'feedback' => 'Nutze min().']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 6,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $createdTaskIds = [];
    foreach ($tasks as $task) {
        $taskId = insertTask($conn, $task);
        $createdTaskIds[] = $taskId;

        if (!empty($task['options']) && is_array($task['options'])) {
            insertOptions($conn, $taskId, $task['options']);
        }
    }

    echo "Assignment aktualisiert: #{$assignmentId} | {$assignmentTitle}\n";
    echo "Neu angelegte Tasks: " . count($createdTaskIds) . "\n";
    echo "Task IDs: " . implode(', ', $createdTaskIds) . "\n";
    echo "OK\n";

    $conn->close();
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
