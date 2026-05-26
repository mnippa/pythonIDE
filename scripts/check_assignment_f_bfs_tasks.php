<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$sql = "SELECT id, assignment_id, position, title, task_type,
               CHAR_LENGTH(randomizer_code) AS rc_len,
               CHAR_LENGTH(solution_code) AS sc_len,
               CHAR_LENGTH(variable_overrides) AS vo_len,
               correct_answer
        FROM tasks
        WHERE title IN ('RC BFS Goldspiel: Muenzen und Ziel', 'RC BFS Labyrinth: kuerzester Weg vs Rechtsregel')
        ORDER BY id";

$res = $conn->query($sql);
if (!$res) {
    echo 'ERROR: ' . $conn->error . PHP_EOL;
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
