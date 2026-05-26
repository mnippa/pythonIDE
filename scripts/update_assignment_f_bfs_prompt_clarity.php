<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$task1Title = 'RC BFS Goldspiel: Muenzen und Ziel';
$task2Title = 'RC BFS Labyrinth: kuerzester Weg vs Rechtsregel';

$task1Text = 'Gesucht sind genau zwei Zahlen. Gib die Antwort als a;b ein (ohne Leerzeichen). a = kuerzeste Schritte, um alle Muenzen einzusammeln und danach das Ziel zu erreichen. b = kuerzeste Schritte von Start direkt zum Ziel (ohne Muenzpflicht).';
$task1Question = 'Antwortformat: a;b  (a=collect_then_goal, b=direct_goal)';

$task2Text = 'Gesucht sind genau zwei Zahlen. Gib die Antwort als a;b ein (ohne Leerzeichen). a = kuerzeste Schritte von Start zu Ziel mit BFS. b = Schritte von Start zu Ziel mit rechter-Hand-Regel.';
$task2Question = 'Antwortformat: a;b  (a=shortest_bfs, b=right_hand_steps)';

$stmt = $conn->prepare('UPDATE tasks SET task_text = ?, question_text = ?, updated_at = NOW() WHERE title = ?');

$stmt->bind_param('sss', $task1Text, $task1Question, $task1Title);
$stmt->execute();
$u1 = $stmt->affected_rows;

$stmt->bind_param('sss', $task2Text, $task2Question, $task2Title);
$stmt->execute();
$u2 = $stmt->affected_rows;

$stmt->close();

echo "Updated rows task1: {$u1}\n";
echo "Updated rows task2: {$u2}\n";

$check = $conn->query("SELECT id, title, task_text, question_text FROM tasks WHERE title IN ('RC BFS Goldspiel: Muenzen und Ziel', 'RC BFS Labyrinth: kuerzester Weg vs Rechtsregel') ORDER BY id");
while ($row = $check->fetch_assoc()) {
    echo "---\n";
    echo $row['id'] . ' | ' . $row['title'] . "\n";
    echo 'task_text: ' . $row['task_text'] . "\n";
    echo 'question_text: ' . $row['question_text'] . "\n";
}
