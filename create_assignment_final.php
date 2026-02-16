<?php
/**
 * Create new Assignment: "Funktionen und Schleifen: Binär"
 * with 2 tasks for each of 6 task types
 */

require_once 'config/database.php';

$conn = getDbConnection();

// Create assignment
$assignmentTitle = 'Funktionen und Schleifen: Binär';
$assignmentDesc = 'Kombinieren Sie Funktionen und Schleifen zur Verarbeitung von Binärzahlen. ' .
                  'Verschiedene Aufgabentypen: Single/Multiple Choice, Free Text, Code, Code Reading.';
$createdBy = 1;

$stmt = $conn->prepare('INSERT INTO assignments (title, description, created_by) VALUES (?, ?, ?)');
$stmt->bind_param('ssi', $assignmentTitle, $assignmentDesc, $createdBy);
if (!$stmt->execute()) {
    die("Error creating assignment: " . $stmt->error . "\n");
}
$assignmentId = $conn->insert_id;
$stmt->close();

echo "====================================================================\n";
echo "✓ Created Assignment #$assignmentId: $assignmentTitle\n";
echo "====================================================================\n\n";

// Define tasks with all info
$tasks = [
    // ===== SINGLE_CHOICE (2) =====
    [
        'task_type' => 'single_choice',
        'title' => 'Single Choice 1: Binärkonvertierung',
        'description' => 'Aufgabentyp: Single Choice - Einmalauswahl',
        'question_text' => 'Was ist der Dezimalwert von Binär "1101"?',
        'position' => 1,
        'code_template' => 'binary = "1101"\n# 1×8 + 1×4 + 0×2 + 1×1 = ?',
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
        'title' => 'Single Choice 2: bin() Funktion',
        'description' => 'Aufgabentyp: Single Choice - Ausgabe vorhersagen',
        'question_text' => 'Welche Ausgabe erzeugt: for i in range(2, 4): print(bin(i))',
        'position' => 2,
        'code_template' => 'for i in range(2, 4):\n    print(bin(i))',
        'solution_code' => 'for i in range(2, 4):\n    print(bin(i))',
        'correct_answer' => '0b10\\n0b11',
        'options' => [
            ['text' => '0b10\\n0b11', 'is_correct' => 1],
            ['text' => '0b1\\n0b10\\n0b11', 'is_correct' => 0],
            ['text' => '2\\n3', 'is_correct' => 0],
            ['text' => '10\\n11', 'is_correct' => 0],
        ],
    ],
    
    // ===== MULTIPLE_CHOICE (2) =====
    [
        'task_type' => 'multiple_choice',
        'title' => 'Multiple Choice 1: Binärzahlen verstehen',
        'description' => 'Aufgabentyp: Multiple Choice - Mehrfachauswahl',
        'question_text' => 'Welche Aussagen über Binärzahlen sind WAHR?',
        'position' => 3,
        'code_template' => 'bin(5)  # Beispiel',
        'solution_code' => 'bin(5)  # returns "0b101"',
        'correct_answer' => '1,3',
        'options' => [
            ['text' => 'Binär 101 = Dezimal 5', 'is_correct' => 1],
            ['text' => 'bin() gibt Integers zurück', 'is_correct' => 0],
            ['text' => 'bin() gibt Strings mit "0b" Präfix zurück', 'is_correct' => 1],
            ['text' => 'Binärzahlen nur für spezielle Zwecke', 'is_correct' => 0],
        ],
    ],
    [
        'task_type' => 'multiple_choice',
        'title' => 'Multiple Choice 2: int() mit Basis 2',
        'description' => 'Aufgabentyp: Multiple Choice - Konvertierung',
        'question_text' => 'Welche Aussagen zu int(x, 2) sind korrekt?',
        'position' => 4,
        'code_template' => 'int("1010", 2)',
        'solution_code' => 'int("1010", 2)  # returns 10',
        'correct_answer' => '1,2',
        'options' => [
            ['text' => 'int("1010", 2) konvertiert Binärstring zu Dezimal', 'is_correct' => 1],
            ['text' => 'Das Ergebnis von int("1010", 2) ist 10', 'is_correct' => 1],
            ['text' => 'int(1010, 2) und int("1010", 2) sind gleich', 'is_correct' => 0],
            ['text' => 'Basis-Parameter können nur 2 oder 10 sein', 'is_correct' => 0],
        ],
    ],
    
    // ===== FREE_TEXT (2) =====
    [
        'task_type' => 'free_text',
        'title' => 'Free Text 1: bin() erklären',
        'description' => 'Aufgabentyp: Freier Text - Erklärung',
        'question_text' => 'Erkläre in 1-2 Sätzen: Was macht bin() und was gibt sie zurück?',
        'position' => 5,
        'code_template' => 'result = bin(7)\nprint(result)',
        'solution_code' => '# bin() konvertiert zu Binär und gibt einen String mit "0b" Präfix zurück\nresult = bin(7)  # "0b111"',
        'correct_answer' => '',
    ],
    [
        'task_type' => 'free_text',
        'title' => 'Free Text 2: int() mit Basis erklären',
        'description' => 'Aufgabentyp: Freier Text - Prozess beschreiben',
        'question_text' => 'Erkläre, wie man einem Binärstring in Dezimal umwandelt (mit int()). Beispiel:',
        'position' => 6,
        'code_template' => '# Konvertiere "1101" zu Dezimal\nbinary_str = "1101"\nresult = ?',
        'solution_code' => 'binary_str = "1101"\nresult = int(binary_str, 2)  # 13',
        'correct_answer' => '',
    ],
    
    // ===== CODE (2) =====
    [
        'task_type' => 'code',
        'title' => 'Code 1: Dezimal zu Binär ohne bin()',
        'description' => 'Aufgabentyp: Code - Funktionsimplementierung',
        'question_text' => 'Schreibe eine Funktion decimal_to_binary(num), die ohne bin() arbeitet.',
        'position' => 7,
        'code_template' => 'def decimal_to_binary(num):\n    # TODO\n    pass\n\nresult = decimal_to_binary(5)\nprint(f"5 in Binär: {result}")',
        'solution_code' => 'def decimal_to_binary(num):\n    if num == 0:\n        return "0"\n    binary = ""\n    while num > 0:\n        binary = str(num % 2) + binary\n        num //= 2\n    return binary\n\nresult = decimal_to_binary(5)\nprint(f"5 in Binär: {result}")',
        'correct_answer' => 'decimal_to_binary',
    ],
    [
        'task_type' => 'code',
        'title' => 'Code 2: Binär zu Dezimal mit Schleife',
        'description' => 'Aufgabentyp: Code - Umkehrfunktion',
        'question_text' => 'Schreibe binary_to_decimal(binary_str), die einen Binärstring konvertiert.',
        'position' => 8,
        'code_template' => 'def binary_to_decimal(binary_str):\n    # TODO\n    pass\n\nresult = binary_to_decimal("101")\nprint(f"101 in Dezimal: {result}")',
        'solution_code' => 'def binary_to_decimal(binary_str):\n    decimal = 0\n    for i, bit in enumerate(reversed(binary_str)):\n        if bit == "1":\n            decimal += 2 ** i\n    return decimal\n\nresult = binary_to_decimal("101")\nprint(f"101 in Dezimal: {result}")',
        'correct_answer' => 'binary_to_decimal',
    ],
    
    // ===== CODE_READING (2) =====
    [
        'task_type' => 'code_reading',
        'title' => 'Code Reading 1: Schleife analysieren',
        'description' => 'Aufgabentyp: Code Reading - Output vorhersagen',
        'question_text' => 'Was gibt dieser Code aus?\nfor i in range(1, 3):\n    print(bin(i))',
        'position' => 9,
        'code_template' => 'for i in range(1, 3):\n    print(bin(i))',
        'solution_code' => 'for i in range(1, 3):\n    print(bin(i))\n# Output:\n# 0b1\n# 0b10',
        'correct_answer' => 'result',
    ],
    [
        'task_type' => 'code_reading',
        'title' => 'Code Reading 2: Konvertierungslogik',
        'description' => 'Aufgabentyp: Code Reading - Wert herausfinden',
        'question_text' => 'Was ist der Wert von `result` nach diesem Code?\nfor bit in "1010":\n    print(bit)',
        'position' => 10,
        'code_template' => 'for bit in "1010":\n    print(bit)',
        'solution_code' => 'for bit in "1010":\n    print(bit)\n# Output:\n# 1\n# 0\n# 1\n# 0',
        'correct_answer' => 'result',
    ],
];

// Insert tasks
$createdCount = 0;
$failedCount = 0;

foreach ($tasks as $task) {
    $stmt = $conn->prepare(
        'INSERT INTO tasks (
            assignment_id, title, description, position, task_type,
            question_text, code_template, solution_code, correct_answer,
            show_solution
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    
    if (!$stmt) {
        echo "✗ Prepare error: " . $conn->error . "\n";
        $failedCount++;
        continue;
    }
    
    $showSolution = 1;
    
    $stmt->bind_param(
        'issississi',
        $assignmentId,
        $task['title'],
        $task['description'],
        $task['position'],
        $task['task_type'],
        $task['question_text'],
        $task['code_template'],
        $task['solution_code'],
        $task['correct_answer'],
        $showSolution
    );
    
    if (!$stmt->execute()) {
        echo "✗ Task {$task['position']}: {$task['title']} - {$stmt->error}\n";
        $failedCount++;
        $stmt->close();
        continue;
    }
    
    $taskId = $conn->insert_id;
    $stmt->close();
    
    // Insert options for choice tasks
    if (in_array($task['task_type'], ['single_choice', 'multiple_choice']) && !empty($task['options'])) {
        foreach ($task['options'] as $optIdx => $option) {
            $optStmt = $conn->prepare(
                'INSERT INTO task_options (task_id, option_text, is_correct, order_num) VALUES (?, ?, ?, ?)'
            );
            
            if (!$optStmt) {
                echo "  ✗ Option error\n";
                continue;
            }
            
            $orderNum = $optIdx + 1;
            $isCorrect = $option['is_correct'] ? 1 : 0;
            
            $optStmt->bind_param('isii', $taskId, $option['text'], $isCorrect, $orderNum);
            $optStmt->execute();
            $optStmt->close();
        }
        echo "✓ Task {$task['position']}: {$task['title']} (ID: $taskId, {$task['task_type']}, " . count($task['options']) . " Options)\n";
    } else {
        echo "✓ Task {$task['position']}: {$task['title']} (ID: $taskId, {$task['task_type']})\n";
    }
    
    $createdCount++;
}

echo "\n====================================================================\n";
echo "✓ ASSIGNMENT CREATION COMPLETE\n";
echo "====================================================================\n";
echo "Assignment ID: $assignmentId\n";
echo "Title: $assignmentTitle\n";
echo "Tasks Created: $createdCount /" . count($tasks) . "\n";
if ($failedCount > 0) {
    echo "Failed: $failedCount\n";
}
echo "====================================================================\n";

$conn->close();
?>
