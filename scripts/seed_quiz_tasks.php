<?php
/**
 * Seed quiz tasks (SC/MC/FreeText/CodeReading) into existing assignments.
 * Usage: php scripts/seed_quiz_tasks.php
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$assignmentIds = [3, 4, 6, 7];

$tasksByAssignment = [
    3 => [
        [
            'task_type' => 'single_choice',
            'title' => 'Schleifen - Ausgabe',
            'description' => 'Single-Choice zur for-Schleife.',
            'question_text' => 'Was gibt der folgende Code aus?\nfor i in range(3):\n    print(i)',
            'options' => [
                ['text' => '0 1 2 (jeweils in neuer Zeile)', 'is_correct' => true],
                ['text' => '1 2 3', 'is_correct' => false],
                ['text' => '0 1 2 3', 'is_correct' => false],
                ['text' => '3 2 1 0', 'is_correct' => false]
            ]
        ],
        [
            'task_type' => 'multiple_choice',
            'title' => 'Bedingungen - Wahr/Falsch',
            'description' => 'Multiple-Choice zu if/else.',
            'question_text' => 'Welche Aussagen sind korrekt?',
            'options' => [
                ['text' => 'if x > 5 wird nur ausgefuehrt, wenn x groesser als 5 ist', 'is_correct' => true],
                ['text' => 'else wird nur ausgefuehrt, wenn die if-Bedingung true ist', 'is_correct' => false],
                ['text' => 'elif kann mehrfach verwendet werden', 'is_correct' => true],
                ['text' => 'if braucht immer ein else', 'is_correct' => false]
            ]
        ],
        [
            'task_type' => 'free_text',
            'title' => 'Funktionen - Beschreibung',
            'description' => 'Freitext zu Funktionen.',
            'question_text' => 'Was macht eine Funktion in Python und warum ist sie nuetzlich?',
            'correct_answer' => 'wiederverwendbar,parameter,rueckgabewert,struktur'
        ],
        [
            'task_type' => 'code_reading',
            'title' => 'Listen - Code Lesen',
            'description' => 'Berechne den Endwert der Variable result.',
            'code_template' => "nums = [x, y, x + y]\nresult = sum(nums)\nprint(result)",
            'correct_answer' => 'result',
            'variable_overrides' => ['x' => [1, 2, 3], 'y' => [4, 5, 6]]
        ]
    ],
    4 => [
        [
            'task_type' => 'single_choice',
            'title' => 'Bedingungen - Ausgabe',
            'description' => 'Single-Choice zu if/else.',
            'question_text' => 'Was gibt der Code aus, wenn x = 2 ist?\nx = 2\nif x > 3:\n    print("A")\nelse:\n    print("B")',
            'options' => [
                ['text' => 'A', 'is_correct' => false],
                ['text' => 'B', 'is_correct' => true],
                ['text' => 'A B', 'is_correct' => false],
                ['text' => 'Keine Ausgabe', 'is_correct' => false]
            ]
        ],
        [
            'task_type' => 'multiple_choice',
            'title' => 'Listen - Grundbegriffe',
            'description' => 'Multiple-Choice zu Listen.',
            'question_text' => 'Welche Aussagen sind korrekt?',
            'options' => [
                ['text' => 'listen.append(x) fuegt ein Element am Ende hinzu', 'is_correct' => true],
                ['text' => 'len(listen) gibt die Anzahl der Elemente zurueck', 'is_correct' => true],
                ['text' => 'listen[0] gibt das letzte Element zurueck', 'is_correct' => false],
                ['text' => 'listen.remove(x) loescht das Element an Index x', 'is_correct' => false]
            ]
        ],
        [
            'task_type' => 'free_text',
            'title' => 'Schleifen - Erklaerung',
            'description' => 'Freitext zu Schleifen.',
            'question_text' => 'Erklaere den Unterschied zwischen for- und while-Schleife.',
            'correct_answer' => 'for,while,bedingungen,zaehler,iterator'
        ],
        [
            'task_type' => 'code_reading',
            'title' => 'Funktionen - Code Lesen',
            'description' => 'Berechne den Endwert der Variable out.',
            'code_template' => "def add(a, b):\n    return a + b\n\nout = add(x, y) * 2\nprint(out)",
            'correct_answer' => 'out',
            'variable_overrides' => ['x' => [2, 3, 4], 'y' => [1, 5, 7]]
        ]
    ],
    6 => [
        [
            'task_type' => 'single_choice',
            'title' => 'Funktionen - Rueckgabe',
            'description' => 'Single-Choice zu Funktionen.',
            'question_text' => 'Was gibt die Funktion zurueck?\n\ndef f(x):\n    return x * 2\n\nprint(f(3))',
            'options' => [
                ['text' => '6', 'is_correct' => true],
                ['text' => '3', 'is_correct' => false],
                ['text' => 'x * 2', 'is_correct' => false],
                ['text' => 'Keine Ausgabe', 'is_correct' => false]
            ]
        ],
        [
            'task_type' => 'multiple_choice',
            'title' => 'Listen - Methoden',
            'description' => 'Multiple-Choice zu Listenmethoden.',
            'question_text' => 'Welche Aussagen sind korrekt?',
            'options' => [
                ['text' => 'list.pop() entfernt das letzte Element', 'is_correct' => true],
                ['text' => 'list.sort() sortiert die Liste in-place', 'is_correct' => true],
                ['text' => 'list.clear() gibt eine neue Liste zurueck', 'is_correct' => false],
                ['text' => 'list.extend(x) fuegt x als einzelnes Element hinzu', 'is_correct' => false]
            ]
        ],
        [
            'task_type' => 'free_text',
            'title' => 'Bedingungen - Nutzen',
            'description' => 'Freitext zu Bedingungen.',
            'question_text' => 'Warum sind Bedingungen in Programmen wichtig? Nenne mindestens zwei Gruende.',
            'correct_answer' => 'entscheidung,verzweigung,logik,steuerung'
        ],
        [
            'task_type' => 'code_reading',
            'title' => 'Schleifen - Code Lesen',
            'description' => 'Berechne den Endwert der Variable total.',
            'code_template' => "total = 0\nfor i in range(x):\n    total += i\nprint(total)",
            'correct_answer' => 'total',
            'variable_overrides' => ['x' => [3, 4, 5]]
        ]
    ],
    7 => [
        [
            'task_type' => 'single_choice',
            'title' => 'Listen - Zugriff',
            'description' => 'Single-Choice zu Listenindex.',
            'question_text' => 'Was ist die Ausgabe?\nnums = [5, 8, 2]\nprint(nums[1])',
            'options' => [
                ['text' => '5', 'is_correct' => false],
                ['text' => '8', 'is_correct' => true],
                ['text' => '2', 'is_correct' => false],
                ['text' => '1', 'is_correct' => false]
            ]
        ],
        [
            'task_type' => 'multiple_choice',
            'title' => 'Schleifen - Eigenschaften',
            'description' => 'Multiple-Choice zu Schleifen.',
            'question_text' => 'Welche Aussagen sind korrekt?',
            'options' => [
                ['text' => 'range(5) liefert 0 bis 4', 'is_correct' => true],
                ['text' => 'while-Schleifen brauchen immer einen Zaehler', 'is_correct' => false],
                ['text' => 'break beendet die Schleife sofort', 'is_correct' => true],
                ['text' => 'continue beendet das Programm', 'is_correct' => false]
            ]
        ],
        [
            'task_type' => 'free_text',
            'title' => 'Funktionen - Parameter',
            'description' => 'Freitext zu Parametern.',
            'question_text' => 'Was sind Parameter in einer Funktion und wofuer nutzt man sie?',
            'correct_answer' => 'parameter,argumente,werte,uebergabe,flexibel'
        ],
        [
            'task_type' => 'code_reading',
            'title' => 'Bedingungen - Code Lesen',
            'description' => 'Berechne den Endwert der Variable x.',
            'code_template' => "x = a\nif x < b:\n    x = x + b\nelse:\n    x = x - b\nprint(x)",
            'correct_answer' => 'x',
            'variable_overrides' => ['a' => [1, 4, 7], 'b' => [3, 5, 8]]
        ]
    ]
];

function getMaxPosition($conn, $assignmentId) {
    $stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) AS max_pos FROM tasks WHERE assignment_id = ?');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)$row['max_pos'];
}

$conn->begin_transaction();

try {
    foreach ($assignmentIds as $assignmentId) {
        $maxPos = getMaxPosition($conn, $assignmentId);
        $position = $maxPos + 1;

        foreach ($tasksByAssignment[$assignmentId] as $taskData) {
            $taskType = $taskData['task_type'];

            // Map to legacy problem_type
            $problemType = 'code_completion';
            if ($taskType === 'single_choice' || $taskType === 'multiple_choice') {
                $problemType = 'multiple_choice';
            } elseif ($taskType === 'free_text') {
                $problemType = 'essay';
            }

            $stmt = $conn->prepare(
                'INSERT INTO tasks (assignment_id, title, description, position, problem_type, task_type, question_text, image_url, correct_answer, variable_overrides, code_template) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $questionText = $taskData['question_text'] ?? null;
            $imageUrl = $taskData['image_url'] ?? null;
            $correctAnswer = $taskData['correct_answer'] ?? null;
            $variableOverrides = isset($taskData['variable_overrides']) ? json_encode($taskData['variable_overrides']) : null;
            $codeTemplate = $taskData['code_template'] ?? null;

            $stmt->bind_param(
                'ississsssss',
                $assignmentId,
                $taskData['title'],
                $taskData['description'],
                $position,
                $problemType,
                $taskType,
                $questionText,
                $imageUrl,
                $correctAnswer,
                $variableOverrides,
                $codeTemplate
            );

            $stmt->execute();
            $taskId = $conn->insert_id;

            // Insert options for SC/MC
            if ($taskType === 'single_choice' || $taskType === 'multiple_choice') {
                $options = $taskData['options'] ?? [];
                $order = 1;

                $optStmt = $conn->prepare(
                    'INSERT INTO task_options (task_id, option_text, image_url, is_correct, order_num) VALUES (?, ?, ?, ?, ?)'
                );

                foreach ($options as $opt) {
                    $optText = $opt['text'];
                    $optImage = $opt['image_url'] ?? null;
                    $isCorrect = !empty($opt['is_correct']) ? 1 : 0;
                    $optStmt->bind_param('issii', $taskId, $optText, $optImage, $isCorrect, $order);
                    $optStmt->execute();
                    $order++;
                }
            }

            $position++;
        }
    }

    $conn->commit();
    echo "Seeded quiz tasks successfully.\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
