<?php
/**
 * Add solution_code to test tasks 47 and 50
 */

require_once __DIR__ . '/config/database.php';

// Get database connection
$conn = getDbConnection();

$solutions = [
    47 => "x = 10\ny = 5\n# TODO: setze result auf True wenn x > y, sonst False\nresult = x > y\nprint(result)",
    50 => "age = 19\nhas_ticket = True\n# TODO: erlauben wenn age >= 18 AND has_ticket\nif age >= 18 and has_ticket:\n    print(\"allowed\")\nelse:\n    print(\"denied\")"
];

foreach ($solutions as $taskId => $code) {
    $sql = "UPDATE tasks SET solution_code = ? WHERE id = ? AND task_type = 'code'";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('si', $code, $taskId);
    
    if ($stmt->execute()) {
        echo "✓ Task $taskId updated successfully\n";
    } else {
        die("Execute failed for task $taskId: " . $stmt->error);
    }
    
    $stmt->close();
}

echo "\n✅ All tasks updated with solution_code!\n";
?>
