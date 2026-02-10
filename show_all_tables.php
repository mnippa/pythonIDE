<?php
/**
 * Show all tables and their row counts
 */

require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

echo "=== ALLE TABELLEN IN DER DATENBANK ===\n\n";

$tables = $db->query("SHOW TABLES");
while ($table = $tables->fetch_array()) {
    $tableName = $table[0];
    $count = $db->query("SELECT COUNT(*) as cnt FROM `$tableName`")->fetch_assoc()['cnt'];
    echo sprintf("%-30s | %d rows\n", $tableName, $count);
}
