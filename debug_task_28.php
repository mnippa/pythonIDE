<?php
require_once 'config/database.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("SELECT id, title, test_cases, validation_mode FROM tasks WHERE id = 28");

if ($result && $result->num_rows > 0) {
  $row = $result->fetch_assoc();
  echo "<pre>";
  echo "Task ID: " . $row['id'] . "\n";
  echo "Title: " . $row['title'] . "\n";
  echo "test_cases length: " . strlen($row['test_cases']) . "\n";
  echo "test_cases value: " . var_export($row['test_cases'], true) . "\n";
  echo "validation_mode: " . var_export($row['validation_mode'], true) . "\n";
  echo "</pre>";
} else {
  echo "Keine Daten gefunden";
}

$conn->close();
?>
