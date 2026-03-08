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
        echo (strpos($content, 'data-run-name="hold"') !== false ? "hold ok\n" : "hold missing\n");
        echo (strpos($content, 'data-run-name="action"') !== false ? "action ok\n" : "action missing\n");
        echo (strpos($content, 'data-element="roll_info"') !== false ? "roll_info ok\n" : "roll_info missing\n");
    }

    if ($row['name'] === 'init.py') {
        echo (strpos($content, "def hold(trigger):") !== false ? "hold func ok\n" : "hold func missing\n");
        echo (strpos($content, "def action(trigger):") !== false ? "action func ok\n" : "action func missing\n");
        echo (strpos($content, "'rolls_left': 3") !== false ? "3-roll logic ok\n" : "3-roll logic missing\n");
        echo (strpos($content, 'Keine Würfel ausgewählt') !== false ? "selected text logic ok\n" : "selected text logic missing\n");
    }

    echo "\n";
}
