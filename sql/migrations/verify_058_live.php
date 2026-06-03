<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.beta_live.local.php';

$conn = getBetaLiveDbConnection();
$res = $conn->query("SHOW COLUMNS FROM user_tasks LIKE 'status'");
$row = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
$type = (string)($row['Type'] ?? '');
echo $type . PHP_EOL;
echo (stripos($type, 'submitted') !== false ? 'submitted: OK' : 'submitted: MISSING') . PHP_EOL;
