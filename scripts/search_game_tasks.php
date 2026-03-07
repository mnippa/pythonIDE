<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

// Search for Blackjack and Kniffel tasks
$stmt = $conn->prepare('SELECT id, title, task_type FROM tasks WHERE title LIKE ? OR title LIKE ? ORDER BY id DESC LIMIT 20');
$searchBlackjack = '%black%';
$searchKniffel = '%kniffel%';
$stmt->bind_param('ss', $searchBlackjack, $searchKniffel);
$stmt->execute();
$result = $stmt->get_result();

echo "=== Searching for Blackjack and Kniffel Tasks ===\n\n";

if ($result->num_rows === 0) {
    echo "No Blackjack or Kniffel tasks found.\n\n";
} else {
    echo "Found tasks:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - Task " . $row['id'] . ": " . $row['title'] . " (type: " . $row['task_type'] . ")\n";
    }
    echo "\n";
}

// List all code_ui tasks to see what's available
echo "=== All code_ui Tasks (might be game-like) ===\n";
$stmt = $conn->prepare('SELECT id, title FROM tasks WHERE task_type = ? ORDER BY id DESC LIMIT 30');
$taskType = 'code_ui';
$stmt->bind_param('s', $taskType);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  - Task " . $row['id'] . ": " . $row['title'] . "\n";
    }
}
