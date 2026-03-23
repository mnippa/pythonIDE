<?php
include 'config/database.php';
$db = getPdoConnection();

// Fix: expected output was "195" but 77 + 59 = 136
$newTestCases = json_encode([
    [
        "type" => "output",
        "expected" => ["136"],
        "expected_type" => "text",
        "validation_mode" => "loose",
        "case_sensitive" => false
    ],
    [
        "type" => "code_check",
        "keywords" => ["print", "77", "59"],
        "operator" => "AND",
        "feedback" => ""
    ]
], JSON_UNESCAPED_UNICODE);

$stmt = $db->prepare('UPDATE tasks SET test_cases = ? WHERE id = 177');
$stmt->execute([$newTestCases]);
echo "Updated: " . $stmt->rowCount() . " row(s)\n";
echo "New test_cases:\n" . $newTestCases . "\n";
