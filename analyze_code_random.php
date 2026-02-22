<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

echo "=== CODE_RANDOM_COMPLEX TASKS - ANALYSE ===\n\n";

$result = $conn->query("
  SELECT 
    id, title, code_template, solution_code, expected_output, test_cases
  FROM tasks 
  WHERE task_type = 'code_random_complex'
  LIMIT 3
");

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    echo "==========================================\n";
    echo "ID: {$row['id']} | {$row['title']}\n";
    echo "==========================================\n\n";
    
    echo "CODE_TEMPLATE:\n";
    echo "---\n";
    echo $row['code_template'] . "\n";
    echo "---\n\n";
    
    echo "SOLUTION_CODE:\n";
    echo "---\n";
    echo $row['solution_code'] . "\n";
    echo "---\n\n";
    
    echo "EXPECTED_OUTPUT:\n";
    echo "---\n";
    echo $row['expected_output'] . "\n";
    echo "---\n\n";
    
    echo "TEST_CASES:\n";
    echo "---\n";
    if ($row['test_cases']) {
      echo json_encode(json_decode($row['test_cases']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
      echo "[NULL/KEINE]\n";
    }
    echo "---\n\n";
  }
} else {
  echo "✗ Keine code_random_complex Tasks gefunden\n";
}

$conn->close();
