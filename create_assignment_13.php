<?php
/**
 * Create new Assignment 13: "Funktionen und Schleifen: Binär"
 * with 2 tasks for each task type
 */

require_once 'config/database.php';

$conn = getDbConnection();

// Create assignment
$assignmentTitle = 'Funktionen und Schleifen: Binär';
$assignmentDesc = 'Kombinieren Sie Funktionen und Schleifen zur Verarbeitung von Binärzahlen. ' .
                  'Verschiedene Aufgabentypen: Single/Multiple Choice, Free Text, Code, Code Reading, Code Random Complex.';

$stmt = $conn->prepare(
    'INSERT INTO assignments (title, description) VALUES (?, ?)'
);
$stmt->bind_param('ss', $assignmentTitle, $assignmentDesc);
$stmt->execute();
$assignmentId = $conn->insert_id;
$stmt->close();

echo "✓ Created Assignment $assignmentId: $assignmentTitle\n\n";

// Define tasks for all 6 task types (2 per type)
$tasks = [
    // ===== SINGLE_CHOICE (2 questions) =====
    [
        'task_type' => 'single_choice',
        'title' => 'Single Choice 1: Binärkonvertierung verstehen',
        'description' => 'Aufgabentyp: Single Choice - Wähle die richtige Antwort',
        'question_text' => 'Was ist der Dezimalwert von Binär "1101"?',
        'position' => 1,
        'code_template' => 'binary = "1101"\n# Umwandlung: 1×8 + 1×4 + 0×2 + 1×1 = ?',
        'solution_code' => 'decimal = 13',
        'correct_answer' => '13',
        'options' => json_encode(['8', '12', '13', '15']), // correct: '13'
    ],
    [
        'task_type' => 'single_choice',
        'title' => 'Single Choice 2: Schleife mit Binärzahlen',
        'description' => 'Aufgabentyp: Single Choice - Analysiere die Schleife',
        'question_text' => 'Welche Ausgabe erzeugt dieser Code?\nfor i in range(2, 5):\n    print(bin(i))',
        'position' => 2,
        'code_template' => 'for i in range(2, 5):\n    print(bin(i))',
        'solution_code' => 'for i in range(2, 5):\n    print(bin(i))',
        'correct_answer' => '0b10\n0b11\n0b100',
        'options' => json_encode(['0b10 0b11 0b100', '0b10\n0b11\n0b100', '0b1\n0b10\n0b11', '10\n11\n100']),
    ],
    
    // ===== MULTIPLE_CHOICE (2 questions) =====
    [
        'task_type' => 'multiple_choice',
        'title' => 'Multiple Choice 1: Binäre Funktionen',
        'description' => 'Aufgabentyp: Multiple Choice - Wähle alle korrekten Antworten',
        'question_text' => 'Welche Aussagen über Binärzahlen sind WAHR? (Mehrfachauswahl)',
        'position' => 3,
        'code_template' => 'bin(5)  # returns "0b101"',
        'solution_code' => 'bin(5)  # 5 = 0b101',
        'correct_answer' => 'option1,option3',
        'options' => json_encode([
            'option1' => 'Binär 101 = Dezimal 5',
            'option2' => 'bin() gibt immer ganzzahligen Wert zurück',
            'option3' => 'Python bin() gibt Strings mit "0b" Präfix zurück',
            'option4' => 'Binärzahlen sind nur für spezielle Anwendungen nützlich'
        ]),
    ],
    [
        'task_type' => 'multiple_choice',
        'title' => 'Multiple Choice 2: Schleifen und Konvertierungen',
        'description' => 'Aufgabentyp: Multiple Choice - Mehrfache Aussagen prüfen',
        'question_text' => 'Welche Aussagen zu int(x, 2) sind korrekt?',
        'position' => 4,
        'code_template' => 'int("1010", 2)  # Konvertiert Binärstring zu Dezimal',
        'solution_code' => 'int("1010", 2)  # returns 10',
        'correct_answer' => 'option1,option2',
        'options' => json_encode([
            'option1' => 'int("1010", 2) konvertiert Binärstring zu Dezimal',
            'option2' => 'Das Ergebnis von int("1010", 2) ist 10',
            'option3' => 'int(1010, 2) und int("1010", 2) geben das gleiche Ergebnis',
            'option4' => 'Die Basis-Parameter können nur 2 oder 10 sein'
        ]),
    ],
    
    // ===== FREE_TEXT (2 questions) =====
    [
        'task_type' => 'free_text',
        'title' => 'Free Text 1: Binär erklärt',
        'description' => 'Aufgabentyp: Freier Text - Erklärung erforderlich',
        'question_text' => 'Erkläre in 1-2 Sätzen, wie die Funktion bin() funktioniert und was sie zurückgibt.',
        'position' => 5,
        'code_template' => 'result = bin(7)\nprint(result)  # ?',
        'solution_code' => '# bin() konvertiert eine Dezimalzahl zu Binär\n# Rückgabe: String mit "0b" Präfix\nresult = bin(7)  # "0b111"',
        'correct_answer' => '',
    ],
    [
        'task_type' => 'free_text',
        'title' => 'Free Text 2: Konvertierungsfunktion',
        'description' => 'Aufgabentyp: Freier Text - Beschreibung der Konvertierung',
        'question_text' => 'Beschreibe, wie man einen Binärstring mit int() und der Basis 2 in eine Dezimalzahl umwandelt. Gib ein Beispiel.',
        'position' => 6,
        'code_template' => '# Konvertiere Binärstring zu Dezimal\nbinary_str = "1101"\n# Wie konvertierst du das?',
        'solution_code' => 'binary_str = "1101"\ndecimal = int(binary_str, 2)  # Ergebnis: 13',
        'correct_answer' => '',
    ],
    
    // ===== CODE (2 questions) =====
    [
        'task_type' => 'code',
        'title' => 'Code 1: Dezimal zu Binär Konverter',
        'description' => 'Aufgabentyp: Code - Schreibe eine Funktion',
        'question_text' => 'Schreibe eine Funktion decimal_to_binary(num), die eine Dezimalzahl in Binär konvertiert (ohne bin() zu benutzen).',
        'position' => 7,
        'code_template' => 'def decimal_to_binary(num):\n    """Konvertiere Dezimal zu Binär ohne bin()"""\n    # TODO: Implementiere hier\n    pass\n\n# Test\nresult = decimal_to_binary(13)\nprint(f"13 in Binary: {result}")',
        'solution_code' => 'def decimal_to_binary(num):\n    """Konvertiere Dezimal zu Binär ohne bin()"""\n    if num == 0:\n        return "0"\n    binary = ""\n    while num > 0:\n        binary = str(num % 2) + binary\n        num //= 2\n    return binary\n\nresult = decimal_to_binary(13)\nprint(f"13 in Binary: {result}")',
        'correct_answer' => 'def decimal_to_binary',
        'test_cases' => json_encode([
            ['input' => '', 'expected_output' => '13 in Binary: 1101'],
            ['input' => '', 'expected_output' => '5 in Binary: 101'],
        ]),
    ],
    [
        'task_type' => 'code',
        'title' => 'Code 2: Binär zu Dezimal mit Schleife',
        'description' => 'Aufgabentyp: Code - Implementiere die Umkehrfunktion',
        'question_text' => 'Schreibe eine Funktion binary_to_decimal(binary_str), die einen Binärstring in Dezimal konvertiert (ohne int() mit Basis zu benutzen).',
        'position' => 8,
        'code_template' => 'def binary_to_decimal(binary_str):\n    """Konvertiere Binärstring zu Dezimal ohne int(x, 2)"""\n    # TODO: Implementiere hier\n    pass\n\n# Test\nresult = binary_to_decimal("1101")\nprint(f"Binary 1101: {result}")',
        'solution_code' => 'def binary_to_decimal(binary_str):\n    """Konvertiere Binärstring zu Dezimal ohne int(x, 2)"""\n    decimal = 0\n    for i, bit in enumerate(reversed(binary_str)):\n        if bit == "1":\n            decimal += 2 ** i\n    return decimal\n\nresult = binary_to_decimal("1101")\nprint(f"Binary 1101: {result}")',
        'correct_answer' => 'def binary_to_decimal',
    ],
    
    // ===== CODE_READING (2 questions) =====
    [
        'task_type' => 'code_reading',
        'title' => 'Code Reading 1: Binäre Schleife analysieren',
        'description' => 'Aufgabentyp: Code Reading - Analysiere den Code und gib das Ergebnis ein',
        'question_text' => 'Was gibt dieser Code aus?\nfor i in range(1, 4):\n    print(bin(i), "=", i)',
        'position' => 9,
        'code_template' => 'for i in range(1, 4):\n    print(bin(i), "=", i)',
        'solution_code' => 'for i in range(1, 4):\n    print(bin(i), "=", i)\n# Output:\n# 0b1 = 1\n# 0b10 = 2\n# 0b11 = 3',
        'correct_answer' => 'result',
    ],
    [
        'task_type' => 'code_reading',
        'title' => 'Code Reading 2: Konvertierungslogik',
        'description' => 'Aufgabentyp: Code Reading - Verstehe die Konvertierungslogik',
        'question_text' => 'Welchen Wert hat die Variable "result" nach diesem Code?\ndecimal = 0\nfor i, bit in enumerate(reversed("1010")):\n    if bit == "1":\n        decimal += 2 ** i\nresult = decimal',
        'position' => 10,
        'code_template' => 'decimal = 0\nfor i, bit in enumerate(reversed("1010")):\n    if bit == "1":\n        decimal += 2 ** i\nresult = decimal\n# result = ?',
        'solution_code' => 'decimal = 0\nfor i, bit in enumerate(reversed("1010")):\n    if bit == "1":\n        decimal += 2 ** i\nresult = decimal  # = 10',
        'correct_answer' => 'result',
    ],
    
    // ===== CODE_RANDOM_COMPLEX (2 questions) =====
    [
        'task_type' => 'code_random_complex',
        'title' => 'Code Random 1: Dezimal zu Binär',
        'description' => 'Aufgabentyp: Code Random Complex - Dezimal zu Binär mit verschiedenen Werten',
        'question_text' => 'Konvertiere die Dezimalzahl {decimal} zu Binär. Gib das Ergebnis aus.',
        'position' => 11,
        'code_template' => 'def decimal_to_binary(num):\n    if num == 0:\n        return "0"\n    binary = ""\n    while num > 0:\n        binary = str(num % 2) + binary\n        num //= 2\n    return binary\n\ndecimal = {decimal}\nresult = decimal_to_binary(decimal)\nprint(f"{decimal} in Binary: {result}")',
        'solution_code' => 'def decimal_to_binary(num):\n    if num == 0:\n        return "0"\n    binary = ""\n    while num > 0:\n        binary = str(num % 2) + binary\n        num //= 2\n    return binary\n\ndecimal = {decimal}\nresult = decimal_to_binary(decimal)\nprint(f"{decimal} in Binary: {result}")',
        'correct_answer' => 'result',
        'variable_overrides' => json_encode(['decimal' => [7, 15, 31]]),
    ],
    [
        'task_type' => 'code_random_complex',
        'title' => 'Code Random 2: Binär zu Dezimal',
        'description' => 'Aufgabentyp: Code Random Complex - Binär zu Dezimal mit verschiedenen Werten',
        'question_text' => 'Konvertiere den Binärstring "{binary}" zu Dezimal. Gib das Ergebnis aus.',
        'position' => 12,
        'code_template' => 'def binary_to_decimal(binary_str):\n    decimal = 0\n    for i, bit in enumerate(reversed(binary_str)):\n        if bit == "1":\n            decimal += 2 ** i\n    return decimal\n\nbinary = "{binary}"\nresult = binary_to_decimal(binary)\nprint(f"Binary {binary}: {result}")',
        'solution_code' => 'def binary_to_decimal(binary_str):\n    decimal = 0\n    for i, bit in enumerate(reversed(binary_str)):\n        if bit == "1":\n            decimal += 2 ** i\n    return decimal\n\nbinary = "{binary}"\nresult = binary_to_decimal(binary)\nprint(f"Binary {binary}: {result}")',
        'correct_answer' => 'result',
        'variable_overrides' => json_encode(['binary' => ["1010", "1111", "10101"]]),
    ],
];

// Insert all tasks
foreach ($tasks as $index => $task) {
    $stmt = $conn->prepare(
        'INSERT INTO tasks (
            assignment_id, title, description, position, task_type,
            question_text, code_template, solution_code, correct_answer,
            variable_overrides, test_cases, show_solution
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    
    $overrides = $task['variable_overrides'] ?? null;
    $tests = $task['test_cases'] ?? null;
    $showSolution = 1;
    
    $stmt->bind_param(
        'issiissssssi',
        $assignmentId,
        $task['title'],
        $task['description'],
        $task['position'],
        $task['task_type'],
        $task['question_text'],
        $task['code_template'],
        $task['solution_code'],
        $task['correct_answer'],
        $overrides,
        $tests,
        $showSolution
    );
    
    if ($stmt->execute()) {
        $taskId = $conn->insert_id;
        echo "✓ Task {$task['position']}: {$task['title']} (ID: $taskId)\n";
    } else {
        echo "✗ Task {$task['position']}: {$task['title']} - ERROR: {$stmt->error}\n";
    }
    $stmt->close();
}

echo "\n✓ Assignment created successfully!\n";
echo "Assignment ID: $assignmentId\n";
echo "Assignment Title: $assignmentTitle\n";
echo "Total Tasks: " . count($tasks) . "\n";

$conn->close();
?>
