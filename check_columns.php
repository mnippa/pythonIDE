<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

echo "=== ALLE SPALTEN IN TABELLE 'tasks' ===\n\n";
$result = $conn->query("DESCRIBE tasks");
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $nullable = $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
    echo str_pad($row['Field'], 25) . " | " . str_pad($row['Type'], 20) . " | " . $nullable . "\n";
  }
}

echo "\n=== SUCHE NACH 'task_text' ===\n";
$result = $conn->query("SHOW COLUMNS FROM tasks WHERE Field = 'task_text'");
if ($result && $result->num_rows > 0) {
  echo "✓ task_text GEFUNDEN\n";
  $col = $result->fetch_assoc();
  echo "Type: " . $col['Type'] . "\n";
  echo "Null: " . $col['Null'] . "\n";
} else {
  echo "✗ task_text NICHT GEFUNDEN\n";
  
  echo "\n=== ALTERNATIV: Spalten mit 'text' oder 'description' ===\n";
  $result = $conn->query("SHOW COLUMNS FROM tasks WHERE Field LIKE '%text%' OR Field LIKE '%description%'");
  if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
  }
}

$conn->close();
