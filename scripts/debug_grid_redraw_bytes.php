<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();
$sql = "SELECT pf.id, pf.project_id, HEX(SUBSTRING(pf.content,1,16)) AS hexhead, LEFT(pf.content,80) AS head
        FROM project_files pf
        WHERE pf.name='init.py'
          AND pf.content LIKE '%Steuerung: w/a/s/d, q = Ende%'";

$res = $conn->query($sql);
if (!$res) {
    echo "QUERY_ERR: " . $conn->error . "\n";
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    echo "file_id=" . $row['id'] . " project_id=" . $row['project_id'] . " hex=" . $row['hexhead'] . "\n";
    $head = str_replace(["\r", "\n", "\t"], ["\\r", "\\n", "\\t"], $row['head']);
    echo "head=" . $head . "\n";
    echo "---\n";
}

$conn->close();
