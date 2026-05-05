<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database.beta_live.local.php';

$local = getDbConnection();
$live  = getBetaLiveDbConnection();

echo 'Local DB: ' . DB_HOST . ' / ' . DB_NAME . PHP_EOL;
echo 'Live  DB: ' . BETA_LIVE_DB_HOST . ' / ' . BETA_LIVE_DB_NAME . PHP_EOL;
echo PHP_EOL;

function getCols(mysqli $conn, string $table): array {
    $r = $conn->query("SHOW COLUMNS FROM `$table`");
    $cols = [];
    while ($row = $r->fetch_assoc()) $cols[$row['Field']] = $row['Type'];
    return $cols;
}

$localCols = getCols($local, 'user_assignments');
$liveCols  = getCols($live,  'user_assignments');

$all = array_unique(array_merge(array_keys($localCols), array_keys($liveCols)));
sort($all);

echo str_pad('Column', 30) . str_pad('Local', 30) . 'Live' . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;
foreach ($all as $col) {
    $l = $localCols[$col] ?? '(fehlt)';
    $r = $liveCols[$col]  ?? '(fehlt)';
    $marker = ($l !== $r) ? ' <<<' : '';
    echo str_pad($col, 30) . str_pad($l, 30) . $r . $marker . PHP_EOL;
}
