<?php
/**
 * Quick debug: Compare tasks 148 and 158
 */
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$pdo = getPdoConnection();

$stmt = $pdo->query('
  SELECT 
    id, 
    title, 
    task_type, 
    LENGTH(test_cases) as test_cases_len,
    SUBSTRING(test_cases, 1, 100) as test_cases_preview,
    LENGTH(variable_overrides) as var_overrides_len,
    SUBSTRING(variable_overrides, 1, 100) as var_overrides_preview,
    LENGTH(randomizer_code) as randomizer_len,
    iterations_count
  FROM tasks 
  WHERE id IN (148, 158)
  ORDER BY id
');

$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
