<?php
/**
 * Add test cases to existing assignment tasks
 * Fügt Test Cases zu den bereits erstellten Aufgaben hinzu
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Test Cases für "Bedingungen Grundlagen" (Assignment 1)
$testCases1 = [
    [
        'task_title' => 'Vergleichsoperatoren',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => 'True']
        ]),
        'validation_mode' => 'strict'
    ],
    [
        'task_title' => 'Gerade oder ungerade',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => 'odd']
        ]),
        'validation_mode' => 'loose'
    ],
    [
        'task_title' => 'Notenlogik',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => 'B']
        ]),
        'validation_mode' => 'strict'
    ],
    [
        'task_title' => 'Bereichsprüfung',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => 'allowed']
        ]),
        'validation_mode' => 'loose'
    ]
];

// Test Cases für "Schleifen und Bedingungen" (Assignment 2)
$testCases2 = [
    [
        'task_title' => 'Gerade Zahlen zählen',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => '3']
        ]),
        'validation_mode' => 'strict'
    ],
    [
        'task_title' => 'Summe bis Grenze',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => '10']
        ]),
        'validation_mode' => 'strict'
    ],
    [
        'task_title' => 'Break bei Treffer',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => '10']
        ]),
        'validation_mode' => 'strict'
    ],
    [
        'task_title' => 'Kombinierte Bedingungen',
        'test_cases' => json_encode([
            ['input' => '', 'expected' => '5']
        ]),
        'validation_mode' => 'strict'
    ]
];

echo "========================================\n";
echo "Adding Test Cases to Assignment Tasks\n";
echo "========================================\n\n";

$updated = 0;
$allCases = array_merge($testCases1, $testCases2);

foreach ($allCases as $testData) {
    $stmt = $conn->prepare('
        UPDATE tasks 
        SET test_cases = ?, 
            validation_mode = ?
        WHERE title = ? 
        LIMIT 1
    ');
    
    $stmt->bind_param(
        'sss',
        $testData['test_cases'],
        $testData['validation_mode'],
        $testData['task_title']
    );
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "✓ Updated: {$testData['task_title']}\n";
            $updated++;
        }
    } else {
        echo "✗ Error updating {$testData['task_title']}: " . $conn->error . "\n";
    }
}

echo "\n========================================\n";
echo "✓ Complete! Updated $updated tasks\n";
echo "========================================\n";

// Show results
$result = $conn->query('
    SELECT id, title, test_cases, validation_mode 
    FROM tasks 
    WHERE test_cases IS NOT NULL
    ORDER BY id
');

echo "\nCurrent Test Cases:\n";
while ($row = $result->fetch_assoc()) {
    echo "- Task {$row['id']}: {$row['title']}\n";
    echo "  Mode: {$row['validation_mode']}\n";
    if ($row['test_cases']) {
        $cases = json_decode($row['test_cases'], true);
        echo "  Tests: " . count($cases) . "\n";
    }
}

?>
