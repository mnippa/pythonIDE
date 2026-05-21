<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.beta_live.local.php';

$conn = getBetaLiveDbConnection();

function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

echo 'tasks.manual_review_required: ' . (hasColumn($conn, 'tasks', 'manual_review_required') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'user_tasks.submission_comment: ' . (hasColumn($conn, 'user_tasks', 'submission_comment') ? 'OK' : 'MISSING') . PHP_EOL;
