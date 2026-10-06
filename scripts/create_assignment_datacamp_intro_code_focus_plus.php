<?php
/**
 * Create/refresh assignment:
 * DataCamp Intro Python Kapitel 1-3 (Code-Fokus Plus)
 *
 * Harder variant with more open coding and intelligent checks.
 */

require_once __DIR__ . '/../config/database.php';

function getAdminUserIdPlus(mysqli $conn): int {
    $res = $conn->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return 1;
}

function getOrCreateAssignmentPlus(mysqli $conn, string $title, string $description): int {
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
        $difficulty = 'intermediate';
        $upd->bind_param('ssi', $description, $difficulty, $id);
        if (!$upd->execute()) {
            throw new Exception('Execute failed (assignment update): ' . $upd->error);
        }
        $upd->close();
        return $id;
    }

    $adminId = getAdminUserIdPlus($conn);
    $difficulty = 'intermediate';
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

function clearAssignmentTasksPlus(mysqli $conn, int $assignmentId): void {
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

function insertTaskPlus(mysqli $conn, array $task): int {
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
    $taskDifficulty = (string)($task['task_difficulty'] ?? 'hard');
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
    $maxAttempts = (string)($task['max_attempts'] ?? 8);
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

function insertOptionsPlus(mysqli $conn, int $taskId, array $options): void {
    $stmt = $conn->prepare('INSERT INTO task_options (task_id, option_text, image_url, is_correct, order_num) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('Prepare failed (insert options): ' . $conn->error);
    }

    $order = 1;
    foreach ($options as $opt) {
        $text = (string)$opt['text'];
        $img = '';
        $isCorrect = !empty($opt['is_correct']) ? 1 : 0;
        $stmt->bind_param('issii', $taskId, $text, $img, $isCorrect, $order);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed (insert option): ' . $stmt->error);
        }
        $order++;
    }

    $stmt->close();
}

try {
    $conn = getDbConnection();

    $title = 'DataCamp Intro Python Kapitel 1-3 (Code-Fokus Plus)';
    $description = 'Schwerere Plus-Variante: offene Code-Templates, mehr Eigenleistung, intelligente Variationen je Kapitel. Keine Framework-Aenderung.';

    $assignmentId = getOrCreateAssignmentPlus($conn, $title, $description);
    clearAssignmentTasksPlus($conn, $assignmentId);

    $tasks = [];
    $p = 1;

    // Chapter 1 - Python Basics (2 intelligent)
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Intelligent Vars: Grundrechenarten',
        'task_text' => 'Berechne aus a und b die Variablen summe, differenz, produkt und quotient.',
        'description' => 'Die Eingaben werden pro Testlauf randomisiert. Lege die vier Ergebnisvariablen korrekt an.',
        'stoff' => 'Arithmetische Operatoren und Variablenzuweisung.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => '#INIT START\na = 1\nb = 1\n#INIT END\n\nsumme = 0\ndifferenz = 0\nprodukt = 0\nquotient = 0.0\nprint(summe, differenz, produkt, quotient)',
        'solution_code' => '#INIT START\na = 1\nb = 1\n#INIT END\n\nsumme = a + b\ndifferenz = a - b\nprodukt = a * b\nquotient = a / b\nprint(summe, differenz, produkt, quotient)',
        'randomizer_code' => 'import random\nvalues = {"a": random.randint(10, 90), "b": random.randint(2, 15)}',
        'test_cases' => json_encode([
            [
                'type' => 'intelligent',
                'mode' => 'vars',
                'tests' => 5,
                'inputs' => ['a', 'b'],
                'outputs' => ['summe', 'differenz', 'produkt', 'quotient']
            ],
            [
                'type' => 'code_check',
                'keywords' => ['summe', 'differenz', 'produkt', 'quotient'],
                'operator' => 'AND',
                'feedback' => 'Alle vier Zielvariablen muessen gesetzt werden.'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 5,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Intelligent Function: describe_number',
        'task_text' => 'Schreibe describe_number(x), die "float", "int" oder "other" zurueckgibt.',
        'description' => 'Die Funktion wird mehrfach mit unterschiedlichen Werten geprueft.',
        'stoff' => 'Typpruefung mit isinstance und if/elif/else.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'def describe_number(x):\n    # return "float", "int" oder "other"\n    pass\n\nprobe = describe_number(5)\nprint(probe)',
        'solution_code' => 'def describe_number(x):\n    if isinstance(x, float):\n        return "float"\n    if isinstance(x, int) and not isinstance(x, bool):\n        return "int"\n    return "other"\n\nprobe = describe_number(5)\nprint(probe)',
        'test_cases' => json_encode([
            [
                'type' => 'intelligent',
                'mode' => 'function',
                'tests' => 6,
                'function' => [
                    'name' => 'describe_number',
                    'params' => ['x']
                ]
            ],
            [
                'type' => 'function',
                'function_name' => 'describe_number',
                'test_cases' => [
                    ['args' => [5], 'expected' => 'int'],
                    ['args' => [5.5], 'expected' => 'float'],
                    ['args' => ['abc'], 'expected' => 'other']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 1,
        'show_solution' => 1,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Ausdruck mit Potenz und Division',
        'task_text' => 'Berechne result = (a ** b) / c.',
        'description' => 'Verwende die vorhandenen Variablen ohne Hardcoding.',
        'stoff' => 'Operatorrangfolge und Potenzoperator.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'a = 6\nb = 4\nc = 3\nresult = 0\nprint(result)',
        'solution_code' => 'a = 6\nb = 4\nc = 3\nresult = (a ** b) / c\nprint(result)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['result' => 432.0]],
            ['type' => 'code_check', 'keywords' => ['\*\*', '/'], 'operator' => 'AND']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'String zu Float und Ruecktyp',
        'task_text' => 'Wandle raw in eine Zahl um und speichere den Typnamen in typname.',
        'description' => 'Setze zahl = float(raw) und pruefe dann type(zahl).__name__.',
        'stoff' => 'Typkonvertierung mit float().',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'raw = "7.25"\nzahl = 0.0\ntypname = ""\nprint(zahl, typname)',
        'solution_code' => 'raw = "7.25"\nzahl = float(raw)\ntypname = type(zahl).__name__\nprint(zahl, typname)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['zahl' => 7.25, 'typname' => 'float']],
            ['type' => 'code_check', 'keywords' => ['float\s*\('], 'operator' => 'AND']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Concept Check: variable assignment',
        'task_text' => 'Waehle die korrekte Aussage zu Variablen in Python.',
        'description' => 'Kleine Theoriefrage als Auflockerung.',
        'stoff' => 'Variablen zeigen auf Werte und werden mit = zugewiesen.',
        'position' => $p++,
        'task_type' => 'single_choice',
        'problem_type' => 'multiple_choice',
        'question_text' => 'Welche Aussage ist richtig?',
        'options' => [
            ['text' => 'Variablen koennen in Python nicht fuer Berechnungen genutzt werden.', 'is_correct' => 0],
            ['text' => 'Variablen werden in Python typischerweise mit = erstellt.', 'is_correct' => 1],
            ['text' => 'Variablen duerfen nur Ganzzahlen speichern.', 'is_correct' => 0]
        ],
        'max_attempts' => 4,
        'iterations_count' => 1,
        'show_solution_code' => 0
    ];

    // Chapter 2 - Python Lists (2 intelligent)
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Intelligent Vars: Listenkennzahlen',
        'task_text' => 'Berechne fuer werte die Variablen anzahl, erstes, letztes und summe.',
        'description' => 'werte wird in jedem Testlauf variiert.',
        'stoff' => 'len, Indexzugriff und Summenbildung auf Listen.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => '#INIT START\nwerte = [1, 2, 3]\n#INIT END\n\nanzahl = 0\nerstes = 0\nletztes = 0\nsumme = 0\nprint(anzahl, erstes, letztes, summe)',
        'solution_code' => '#INIT START\nwerte = [1, 2, 3]\n#INIT END\n\nanzahl = len(werte)\nerstes = werte[0]\nletztes = werte[-1]\nsumme = sum(werte)\nprint(anzahl, erstes, letztes, summe)',
        'randomizer_code' => 'import random\nlaenge = random.randint(4, 8)\nwerte = [random.randint(-9, 20) for _ in range(laenge)]\nvalues = {"werte": werte}',
        'test_cases' => json_encode([
            [
                'type' => 'intelligent',
                'mode' => 'vars',
                'tests' => 5,
                'inputs' => ['werte'],
                'outputs' => ['anzahl', 'erstes', 'letztes', 'summe']
            ],
            [
                'type' => 'code_check',
                'keywords' => ['len\s*\(', 'sum\s*\('],
                'operator' => 'AND',
                'feedback' => 'Nutze len() und sum() fuer die Listenanalyse.'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 5
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Intelligent Random: Slicing mit Schrittweite',
        'task_text' => 'Speichere in teil den Slice werte[start:ende:step].',
        'description' => 'start, ende und step sind pro Lauf unterschiedlich.',
        'stoff' => 'Fortgeschrittenes Slicing mit Schrittweite.',
        'position' => $p++,
        'task_type' => 'code_random_complex',
        'problem_type' => 'code_completion',
        'task_difficulty' => 'hard',
        'code_template' => 'werte = values["werte"]\nstart = values["start"]\nende = values["ende"]\nstep = values["step"]\nteil = []\nprint(teil)',
        'solution_code' => 'werte = values["werte"]\nstart = values["start"]\nende = values["ende"]\nstep = values["step"]\nteil = werte[start:ende:step]\nprint(teil)',
        'correct_answer' => 'teil',
        'variable_overrides' => json_encode([
            [
                'inputs' => [
                    'werte' => '<random>',
                    'start' => '<random>',
                    'ende' => '<random>',
                    'step' => '<random>'
                ],
                'expected' => ['variable' => 'teil']
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'randomizer_code' => 'import random\nlaenge = random.randint(8, 12)\nwerte = [random.randint(0, 40) for _ in range(laenge)]\nstart = random.randint(0, 2)\nende = random.randint(laenge - 4, laenge)\nstep = random.choice([1, 2])\nvalues = {"werte": werte, "start": start, "ende": ende, "step": step}',
        'test_cases' => 'null',
        'max_attempts' => 8,
        'iterations_count' => 5,
        'show_solution_code' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Indexsuche mit Schleife',
        'task_text' => 'Finde den ersten Index von target in daten ohne .index().',
        'description' => 'Setze idx auf -1, falls target nicht vorhanden ist.',
        'stoff' => 'for-Schleife und bedingter Abbruch.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'daten = [5, 9, 4, 9, 8]\ntarget = 9\nidx = -1\nprint(idx)',
        'solution_code' => 'daten = [5, 9, 4, 9, 8]\ntarget = 9\nidx = -1\nfor i, v in enumerate(daten):\n    if v == target:\n        idx = i\n        break\nprint(idx)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['idx' => 1]],
            ['type' => 'code_check', 'keywords' => ['for', 'if', 'break'], 'operator' => 'AND'],
            ['type' => 'code_check', 'keywords' => ['\.index\s*\('], 'operator' => 'NOT', 'feedback' => 'Nutze keine .index()-Methode.']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'List concat und bool filter',
        'task_text' => 'Erstelle z aus x + y und speichere in hat_true, ob True enthalten ist.',
        'description' => 'Bearbeite beide Teilaufgaben im selben Programm.',
        'stoff' => 'Listenverkettung und Mitgliedstest mit in.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'x = [1, -1]\ny = [True, False]\nz = []\nhat_true = False\nprint(z, hat_true)',
        'solution_code' => 'x = [1, -1]\ny = [True, False]\nz = x + y\nhat_true = True in z\nprint(z, hat_true)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['z' => [1, -1, true, false], 'hat_true' => true]],
            ['type' => 'code_check', 'keywords' => ['\+', 'in'], 'operator' => 'AND']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Slice reverse Teilbereich',
        'task_text' => 'Speichere in teil den umgekehrten Ausschnitt x[1:5].',
        'description' => 'Erzeuge erst den Slice und drehe ihn dann um.',
        'stoff' => 'Slicing und Reverse mit [::-1].',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'x = [3, 8, 1, 9, 2, 7]\nteil = []\nprint(teil)',
        'solution_code' => 'x = [3, 8, 1, 9, 2, 7]\nteil = x[1:5][::-1]\nprint(teil)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['teil' => [2, 9, 1, 8]]],
            ['type' => 'code_check', 'keywords' => ['\[\s*1\s*:\s*5\s*\]', '\[\s*::\s*-1\s*\]'], 'operator' => 'AND']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 1
    ];

    // Chapter 3 - Functions and Packages (2 intelligent)
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Intelligent Function: normalize_title',
        'task_text' => 'Schreibe normalize_title(text), die den Text getrimmt und mit Anfangsbuchstaben gross zurueckgibt.',
        'description' => 'Fuehre strip() und capitalize() aus, in dieser Reihenfolge.',
        'stoff' => 'String-Methoden strip und capitalize.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'def normalize_title(text):\n    pass\n\nprint(normalize_title("  hello world  "))',
        'solution_code' => 'def normalize_title(text):\n    return text.strip().capitalize()\n\nprint(normalize_title("  hello world  "))',
        'test_cases' => json_encode([
            [
                'type' => 'intelligent',
                'mode' => 'function',
                'tests' => 6,
                'function' => [
                    'name' => 'normalize_title',
                    'params' => ['text']
                ]
            ],
            [
                'type' => 'function',
                'function_name' => 'normalize_title',
                'test_cases' => [
                    ['args' => ['  hello world  '], 'expected' => 'Hello world'],
                    ['args' => ['python'], 'expected' => 'Python']
                ]
            ],
            [
                'type' => 'code_check',
                'keywords' => ['strip\s*\(', 'capitalize\s*\('],
                'operator' => 'AND'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Intelligent Vars: min max span',
        'task_text' => 'Berechne min_wert, max_wert und span aus der Liste q.',
        'description' => 'span = max_wert - min_wert.',
        'stoff' => 'min, max und arithmetische Differenz.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => '#INIT START\nq = [5, 1, 9]\n#INIT END\n\nmin_wert = 0\nmax_wert = 0\nspan = 0\nprint(min_wert, max_wert, span)',
        'solution_code' => '#INIT START\nq = [5, 1, 9]\n#INIT END\n\nmin_wert = min(q)\nmax_wert = max(q)\nspan = max_wert - min_wert\nprint(min_wert, max_wert, span)',
        'randomizer_code' => 'import random\nq = [random.randint(-20, 40) for _ in range(random.randint(5, 9))]\nvalues = {"q": q}',
        'test_cases' => json_encode([
            [
                'type' => 'intelligent',
                'mode' => 'vars',
                'tests' => 5,
                'inputs' => ['q'],
                'outputs' => ['min_wert', 'max_wert', 'span']
            ],
            [
                'type' => 'code_check',
                'keywords' => ['min\s*\(', 'max\s*\('],
                'operator' => 'AND'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 5
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Import und sqrt anwenden',
        'task_text' => 'Importiere math und berechne in wurzel die Quadratwurzel von n.',
        'description' => 'Nutze math.sqrt und speichere das Ergebnis in wurzel.',
        'stoff' => 'Import von Standardbibliotheken und Funktionsaufrufe.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'n = 81\nwurzel = 0\nprint(wurzel)',
        'solution_code' => 'import math\nn = 81\nwurzel = math.sqrt(n)\nprint(wurzel)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['wurzel' => 9.0]],
            ['type' => 'code_check', 'keywords' => ['import\s+math', 'math\.sqrt\s*\('], 'operator' => 'AND']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Monatposition mit index()',
        'task_text' => 'Finde den Index von "Feb" in monate und speichere ihn in idx.',
        'description' => 'Nutze die Listenmethode index() fuer die Positionsbestimmung.',
        'stoff' => 'Listenmethoden, index auf Stringlisten.',
        'position' => $p++,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => 'monate = ["Apr", "May", "Mar", "Jan", "Feb"]\nidx = -1\nprint(idx)',
        'solution_code' => 'monate = ["Apr", "May", "Mar", "Jan", "Feb"]\nidx = monate.index("Feb")\nprint(idx)',
        'test_cases' => json_encode([
            ['type' => 'variable', 'expected_vars' => ['idx' => 4]],
            ['type' => 'code_check', 'keywords' => ['\\.index\\s*\\('], 'operator' => 'AND']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'max_attempts' => 8,
        'iterations_count' => 1
    ];

    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title' => 'Concept Check: pip role',
        'task_text' => 'Waehle die korrekte Aussage zu pip.',
        'description' => 'Kurzer Theoriecheck zu Packages.',
        'stoff' => 'pip als Paketverwaltungssystem.',
        'position' => $p++,
        'task_type' => 'single_choice',
        'problem_type' => 'multiple_choice',
        'question_text' => 'Was beschreibt pip korrekt?',
        'options' => [
            ['text' => 'pip ist ein Plotting-Framework.', 'is_correct' => 0],
            ['text' => 'pip ist das Paketverwaltungssystem fuer Python.', 'is_correct' => 1],
            ['text' => 'pip ersetzt den Python-Interpreter.', 'is_correct' => 0]
        ],
        'max_attempts' => 4,
        'iterations_count' => 1,
        'show_solution_code' => 0
    ];

    $taskIds = [];
    foreach ($tasks as $task) {
        $taskId = insertTaskPlus($conn, $task);
        $taskIds[] = $taskId;

        if (!empty($task['options']) && is_array($task['options'])) {
            insertOptionsPlus($conn, $taskId, $task['options']);
        }
    }

    echo "Assignment aktualisiert: #{$assignmentId} | {$title}\n";
    echo "Neu angelegte Tasks: " . count($taskIds) . "\n";
    echo "Task IDs: " . implode(', ', $taskIds) . "\n";
    echo "OK\n";

    $conn->close();
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
