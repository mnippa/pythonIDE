<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$result = $conn->query("
    SELECT id, title, test_cases 
    FROM tasks 
    WHERE title = 'String-Formatierung'
");

if ($row = $result->fetch_assoc()) {
    echo "Task {$row['id']}: {$row['title']}\n";
    echo str_repeat('=', 60) . "\n\n";
    
    $testCases = json_decode($row['test_cases'], true);
    echo "Test Cases JSON (formatted):\n";
    echo json_encode($testCases, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n";
    
    if (is_array($testCases) && count($testCases) > 0) {
        echo "Analyse:\n";
        echo "--------\n";
        
        foreach ($testCases as $idx => $tc) {
            echo "Test " . ($idx + 1) . ":\n";
            
            $expected = $tc['expected'] ?? null;
            
            if (is_array($expected)) {
                echo "  Typ: MEHRERE LÖSUNGEN MÖGLICH\n";
                echo "  Anzahl Optionen: " . count($expected) . "\n";
                echo "  Optionen:\n";
                foreach ($expected as $i => $opt) {
                    echo "    " . ($i + 1) . ". \"$opt\"\n";
                }
                echo "  ✓ Test besteht wenn EINE der Optionen ausgegeben wird\n";
            } else {
                echo "  Typ: EINZELNER WERT\n";
                echo "  Expected: \"$expected\"\n";
                echo "  Test besteht nur bei exakter Übereinstimmung\n";
            }
            echo "\n";
        }
    }
} else {
    echo "Task 'String-Formatierung' nicht gefunden\n";
}

$conn->close();
