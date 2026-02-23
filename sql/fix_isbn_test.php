<?php
require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();

// Update the ISBN test case to use correct format
$testCases = json_encode([
    [
        'type' => 'output',
        'expected_type' => 'regex',
        'expected' => '^ISBN\\s+(978|979)-\\d{1,5}-\\d{1,7}-\\d{1,7}-\\d{1}$',
        'description' => 'Gültige ISBN-13 im Format: ISBN 978-X-XX-XXXXXX-X'
    ]
]);

$stmt = $conn->prepare("UPDATE tasks SET test_cases = ? WHERE id = 140");
$stmt->bind_param("s", $testCases);

if ($stmt->execute()) {
    echo "✅ Test case für Task #140 aktualisiert!\n\n";
    echo "Neues Format:\n";
    echo "  expected_type: 'regex'\n";
    echo "  expected: '^ISBN\\s+(978|979)-\\d{1,5}-\\d{1,7}-\\d{1,7}-\\d{1}$'\n\n";
    
    // Show current test_cases
    $result = $conn->query("SELECT test_cases FROM tasks WHERE id = 140");
    $task = $result->fetch_assoc();
    echo "Aktuelles test_cases JSON:\n";
    echo json_encode(json_decode($task['test_cases']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "❌ Fehler: " . $stmt->error . "\n";
}
