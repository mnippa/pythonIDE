<?php
/**
 * Create new Assignment 13: "Funktionen und Schleifen: Binär"
 * with 2 tasks for each task type + task options for single/multiple choice
 */

require_once 'config/database.php';

$conn = getDbConnection();

// Create assignment
$assignmentTitle = 'Funktionen und Schleifen: Binär';
$assignmentDesc = 'Kombinieren Sie Funktionen und Schleifen zur Verarbeitung von Binärzahlen. ' .
                  'Verschiedene Aufgabentypen: Single/Multiple Choice, Free Text, Code, Code Reading.';
$createdBy = 1; // Admin user

$stmt = $conn->prepare(
    'INSERT INTO assignments (title, description, created_by) VALUES (?, ?, ?)'
);
$stmt->bind_param('ssi', $assignmentTitle, $assignmentDesc, $createdBy);
if (!$stmt->execute()) {
    die("Error creating assignment: " . $stmt->error . "\n");
}
$assignmentId = $conn->insert_id;
$stmt->close();

echo "✓ Created Assignment $assignmentId: $assignmentTitle\n\n";

// Define tasks
$tasks = [
    // ===== SINGLE_CHOICE (2 questions) =====
    [
        'task_type' => 'single_choice',
        'title' => 'Single Choice 1: Binärkonvertierung verstehen',
        'description' => 'Aufgabentyp: Single Choice - Einmalauswahl',
        'question_text' => 'Was ist der Dezimalwert von Binär "1101"?',
        'position' => 1,
        'code_template' => 'binary = "1101"\n# Umwandlung: 1×8 + 1×4 + 0×2 + 1×1 = ?',
        'solution_code' => 'decimal = 13',
        'correct_answer' => '13',
        'options' => [
            ['text' => '8', 'is_correct' => 0],
            ['text' => '12', 'is_correct' => 0],
            ['text' => '13', 'is_correct' => 1],
            ['text' => '15', 'is_correct' => 0],
        ],
    ],
    [
        'task_type' => 'single_choice',
        'title' => 'Single Choice 2: Schleife mit Binärzahlen',
        'description' => 'Aufgabentyp: Single Choice - Code-Ausgabe',
        'question_text' => 'Welche Ausgabe erzeugt dieser Code?\nfor i in range(2, 4):\n    print(bin(i))',
        'position' => 2,
        'code_template' => 'for i in range(2, 4):\n    print(bin(i))',
        'solution_code' => 'for i in range(2, 4):\n    print(bin(i))',
        'correct_answer' => '0b10 0b11',
        'options' => [
            ['text' => '0b10 0b11', 'is_correct' => 1],
            ['text' => '0b1 0b10 0b11', 'is_correct' => 0],
            ['text' => '10 11', 'is_correct' => 0],
            ['text' => '2 3', 'is_correct' => 0],
        ],
    ],
    
    // ===== MULTIPLE_CHOICE (2 questions) =====
    [
        'task_type' => 'multiple_choice',
        'title' => 'Multiple Choice 1: Binäre Funktionen',
        'description' => 'Aufgabentyp: Multiple Choice - Mehrfachauswahl',
        'question_text' => 'Welche Aussagen über Binärzahlen sind WAHR? (Mehrfachauswahl)',
        'position' => 3,
        'code_template' => 'bin(5)  # returns "0b101"',
        'solution_code' => 'bin(5)  # 5 = 0b101',
        'correct_answer' => 'option1,option3',
        'options' => [
            ['text' => 'Binär 101 = Dezimal 5', 'is_correct' => 1],
            ['text' => 'bin() gibt immer ganzzahligen Wert zurück', 'is_correct' => 0],
            ['text' => 'Python bin() gibt Strings mit "0b" Präfix zurück', 'is_correct' => 1],
            ['text' => 'Binärzahlen sind nur für spezielle Anwendungen nützlich', 'is_correct' => 0],
        ],
    ],
    [
        'task_type' => 'multiple_choice',
        'title' => 'Multiple Choice 2: int() mit Basis',
        'description' => 'Aufgabentyp: Multiple Choice - Konvertierung',
        'question_text' => 'Welche Aussagen zu int(x, 2) sind korrekt?',
        'position' => 4,
        'code_template' => 'int("1010", 2)  # Konvertiert Binärstring zu Dezimal',
        'solution_code' => 'int("1010", 2)  # returns 10',
        'correct_answer' => 'option1,option2',
        'options' => [
            ['text' => 'int("1010", 2) konvertiert Binärstring zu Dezimal', 'is_correct' => 1],
            ['text' => 'Das Ergebnis von int("1010", 2) ist 10', 'is_correct' => 1],
            ['text' => 'int(1010, 2) und int("1010", 2) geben das gleiche Ergebnis', 'is_correct' => 0],
            ['text' => 'Die Basis-Parameter können nur 2 oder 10 sein', 'is_correct' => 0],
        ],
    ],
    
    // ===== FREE_TEXT (2 questions) =====
    [
        'task_type' => 'free_text',
        'title' => 'Free Text 1: bin() Funktion erklären',
        'description' => 'Aufgabentyp: Freier Text - Erklärung erforderlich',
        'question_text' => 'Erkläre in 1-2 Sätzen, wie die Funktion bin() funktioniert und was sie zurückgibt.',
        'position' => 5,
        'code_template' => 'result = bin(7)\nprint(result)  # ?',
        'solution_code' => '# bin() konvertiert eine Dezimalzahl zu Binär\n# Rückgabe: String mit "0b" Präfix\nresult = bin(7)  # "0b111"',
        'correct_answer' => '',
    ],
    [
        'task_type' => 'free_text',
        'title' => 'Free Text 2: int() mit Basis 2',
        'description' => 'Aufgabentyp: Freier Text - Beschreibung',
        'question_text' => 'Beschreibe, wie man einen Binärstring mit int() und der Basis 2 in eine Dezimalzahl umwandelt. Gib ein Beispiel.',
        'position' => 6,
        'code_template' => '# Konvertiere Binärstring zu Dezimal\nbinary_str = "1101"\n# Wie konvertierst du das?',
        'solution_code' => 'binary_str = "1101"\ndecimal = int(binary_str, 2)  # Ergebnis: 13',
        'correct_answer' => '',
    ],
    
    // ===== CODE (2 questions) =====
    [
        'task_type' => 'code',
        'title' => 'Code 1: decimal_to_binary ohne bin()',
        'description' => 'Aufgabentyp: Code - Schreibe eine Funktion',
        'question_text' => 'Schreibe eine Funktion decimal_to_binary(num), die eine Dezimalzahl in Binär konvertiert (ohne bin() zu benutzen).',
        'position' => 7,
        'code_template' => 'def decimal_to_binary(num):\n    """Konvertiere Dezimal zu Binär ohne bin()"""\n    # TODO: Implementiere hier\n    pass\n\n# Test\nresult = decimal_to_binary(13)\nprint(f"13 in Binary: {result}")',
        'solution_code' => 'def decimal_to_binary(num):\n    """Konvertiere Dezimal zu Binär ohne bin()"""\n    if num == 0:\n        return "0"\n    binary = ""\n    while num > 0:\n        binary = str(num % 2) + binary\n        num //= 2\n    return binary\n\nresult = decimal_to_binary(13)\nprint(f"13 in Binary: {result}")',
        'correct_answer' => 'def decimal_to_binary',
        'test_cases' => json_encode([
            ['input' => '', 'expected_output' => '13 in Binary: 1101'],
        ]),
    ],
    [
        'task_type' => 'code',
        'title' => 'Code 2: Binär zu Dezimal mit Schleife',
        'description' => 'Aufgabentyp: Code - Implementiere die Umkehrfunktion',
        'question_text' => 'Schreibe eine Funktion binary_to_decimal(binary_str), die einen Binärstring in Dezimal konvertiert.',
        'position' => 8,
        'code_template' => 'def binary_to_decimal(binary_str):\n    """Konvertiere Binärstring zu Dezimal"""\n    # TODO: Implementiere hier\n    pass\n\n# Test\nresult = binary_to_decimal("1101")\nprint(f"Binary 1101: {result}")',
        'solution_code' => 'def binary_to_decimal(binary_str):\n    """Konvertiere Binärstring zu Dezimal"""\n    decimal = 0\n    for i, bit in enumerate(reversed(binary_str)):\n        if bit == "1":\n            decimal += 2 ** i\n    return decimal\n\nresult = binary_to_decimal("1101")\nprint(f"Binary 1101: {result}")',
        'correct_answer' => 'def binary_to_decimal',
    ],
    
    // ===== CODE_READING (2 questions) =====
    [
        'task_type' => 'code_reading',
        'title' => 'Code Reading 1: Binäre Schleife analysieren',
        'description' => 'Aufgabentyp: Code Reading - Analysiere den Output',
        'question_text' => 'Was gibt dieser Code aus?\nfor i in range(1, 4):\n    print(bin(i), "=", i)',
        'position' => 9,
        'code_template' => 'for i in range(1, 4):\n    print(bin(i), "=", i)',
        'solution_code' => 'for i in range(1, 4):\n    print(bin(i), "=", i)\n# Output:\n# 0b1 = 1\n# 0b10 = 2\n# 0b11 = 3',
        'correct_answer' => 'result',
    ],
    [
        'task_type' => 'code_reading',
        'title' => 'Code Reading 2: Konvertierungslogik verstehen',
        'description' => 'Aufgabentyp: Code Reading - Variablenwert herausfinden',
        'question_text' => 'Welchen Wert hat die Variable "result" nach diesem Code?\ndecimal = 0\nfor i, bit in enumerate(reversed("1010")):\n    if bit == "1":\n        decimal += 2 ** i\nresult = decimal',
        'position' => 10,
        'code_template' => 'decimal = 0\nfor i, bit in enumerate(reversed("1010")):\n    if bit == "1":\n        decimal += 2 ** i\nresult = decimal\n# result = ?',
        'solution_code' => 'decimal = 0\nfor i, bit in enumerate(reversed("1010")):\n    if bit == "1":\n        decimal += 2 ** i\nresult = decimal  # = 10',
        'correct_answer' => 'result',
    ],
];

// Insert all tasks
$createdCount = 0;
foreach ($tasks as $index => $task) {
    $stmt = $conn->prepare(
        'INSERT INTO tasks (
            assignment_id, title, description, position, task_type,
            question_text, code_template, solution_code, correct_answer,
            test_cases, show_solution
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    
    if (!$stmt) {
        echo "✗ PrepareStmt Error: " . $conn->error . "\n";
        continue;
    }
    
    $tests = $task['test_cases'] ?? null;
    $showSolution = 1;
    
    $stmt->bind_param(
        'issiisssss',
        $assignmentId,
        $task['title'],
        $task['description'],
        $task['position'],
        $task['task_type'],
        $task['question_text'],
        $task['code_template'],
        $task['solution_code'],
        $task['correct_answer'],
        $tests,
        $showSolution
    );
    
    if (!$stmt->execute()) {
        echo "✗ Task {$task['position']}: {$task['title']} - {$stmt->error}\n";
        $stmt->close();
        continue;
    }
    
    $taskId = $conn->insert_id;
    $stmt->close();
    
    // Insert options for single/multiple choice
    if (in_array($task['task_type'], ['single_choice', 'multiple_choice']) && !empty($task['options'])) {
        foreach ($task['options'] as $optIndex => $option) {
            $optStmt = $conn->prepare(
                'INSERT INTO task_options (task_id, option_text, is_correct, order_num) VALUES (?, ?, ?, ?)'
            );
            
            if (!$optStmt) {
                echo "  ✗ Option " . ($optIndex + 1) . " prepare error: " . $conn->error . "\n";
                continue;
            }
            
            $orderNum = $optIndex + 1;
            $isCorrect = $option['is_correct'] ? 1 : 0;
            
            $optStmt->bind_param('isii', $taskId, $option['text'], $isCorrect, $orderNum);
            
            if(!$optStmt->execute()) {
                echo "  ✗ Option " . ($optIndex + 1) . " execute error: " . $optStmt->error . "\n";
            }
            
            $optStmt->close();
        }
        echo "✓ Task {$task['position']}: {$task['title']} (ID: $taskId) mit " . count($task['options']) . " Optionen\n";
    } else {
        echo "✓ Task {$task['position']}: {$task['title']} (ID: $taskId)\n";
    }
    
    $createdCount++;
}

$separator = str_repeat("=", 60);
echo "\n" . $separator . "\n";
echo "✓ Assignment created successfully!\n";
echo "Assignment ID: $assignmentId\n";
echo "Assignment Title: $assignmentTitle\n";
echo "Total Tasks Created: $createdCount/" . count($tasks) . "\n";
echo $separator . "\n";

$conn->close();
?>
