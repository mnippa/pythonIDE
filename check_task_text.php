<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

// Check if task_text column exists
$result = $conn->query("SHOW COLUMNS FROM tasks LIKE 'task_text'");
echo "=== task_text Spalte ===\n";
if ($result && $result->num_rows > 0) {
  echo "✓ Spalte existiert\n";
  $col = $result->fetch_assoc();
  echo "Type: " . $col['Type'] . "\n\n";
} else {
  echo "✗ Spalte existiert NICHT\n\n";
}

// Show sample data
echo "=== Beispieldaten (erste 5 Tasks) ===\n";
$result = $conn->query("SELECT id, title, task_text, question_text, description FROM tasks LIMIT 5");
if ($result) {
  while ($row = $result->fetch_assoc()) {
    echo "\nID {$row['id']}: {$row['title']}\n";
    echo "  task_text: " . (empty($row['task_text']) ? "[LEER]" : substr($row['task_text'], 0, 60) . "...") . "\n";
    echo "  question_text: " . (empty($row['question_text']) ? "[LEER]" : substr($row['question_text'], 0, 40) . "...") . "\n";
    echo "  description: " . (empty($row['description']) ? "[LEER]" : substr($row['description'], 0, 40) . "...") . "\n";
  }
}

// Count non-empty task_text
$count = $conn->query("SELECT COUNT(*) as cnt FROM tasks WHERE task_text IS NOT NULL AND task_text != ''");
$countRow = $count->fetch_assoc();
echo "\n=== Zusammenfassung ===\n";
echo "Tasks mit task_text gefüllt: {$countRow['cnt']}\n";

$conn->close();
