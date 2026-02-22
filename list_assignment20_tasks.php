<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

echo "=== TASKS IN ASSIGNMENT #20 ===\n\n";
$result = $conn->query("
  SELECT id, title, task_type, description 
  FROM tasks 
  WHERE assignment_id = 20 
  ORDER BY position, id
");

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} | Type: {$row['task_type']} | Title: {$row['title']}\n";
    echo "  Current description: " . (empty($row['description']) ? "[LEER]" : substr($row['description'], 0, 60) . "...") . "\n\n";
  }
} else {
  echo "✗ KEINE TASKS IN ASSIGNMENT #20 GEFUNDEN\n";
}

$conn->close();
