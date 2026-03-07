<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare('SELECT p.id, f.name, f.content FROM projects p JOIN project_files f ON p.id = f.project_id WHERE p.name = ? AND f.name IN ("index.html", "init.py") ORDER BY f.name');
$name = 'Kniffel (Yahtzee)';
$stmt->bind_param('s', $name);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    echo "--- {$row['name']} (project {$row['id']}) ---\n";
    $content = (string)$row['content'];

    if ($row['name'] === 'index.html') {
        echo (strpos($content, 'data-run-name="hold_1"') !== false ? "hold_1 ok\n" : "hold_1 missing\n");
        echo (strpos($content, 'data-run-name="new_round"') !== false ? "new_round ok\n" : "new_round missing\n");
        echo (strpos($content, 'data-element="roll_info"') !== false ? "roll_info ok\n" : "roll_info missing\n");
    }

    if ($row['name'] === 'init.py') {
        echo (strpos($content, "def hold_1(trigger):") !== false ? "hold funcs ok\n" : "hold funcs missing\n");
        echo (strpos($content, "'rolls_left': 3") !== false ? "3-roll logic ok\n" : "3-roll logic missing\n");
        echo (strpos($content, 'Keine Würfel ausgewählt') !== false ? "selected text logic ok\n" : "selected text logic missing\n");
    }

    echo "\n";
}
