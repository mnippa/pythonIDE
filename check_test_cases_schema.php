<?php
require_once __DIR__ . '/config/database.php';
$c = getDbConnection();
$r = $c->query('DESCRIBE test_cases');
while($row = $r->fetch_assoc()) { 
    echo $row['Field'] . "\n";
}
