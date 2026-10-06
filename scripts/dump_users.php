<?php
require_once __DIR__ . '/../config/database.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) { exit('DB_ERR ' . $mysqli->connect_error); }
$res = $mysqli->query("SELECT id, email, first_name, last_name, role, status FROM users ORDER BY id LIMIT 20");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
