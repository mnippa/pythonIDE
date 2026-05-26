<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$task1Title = 'RC BFS Goldspiel: Muenzen und Ziel';
$task2Title = 'RC BFS Labyrinth: kuerzester Weg vs Rechtsregel';

// Hide solution code blocks for both tasks to reduce confusion.
$hideStmt = $conn->prepare('UPDATE tasks SET show_solution_code = 0, show_solution = 0, updated_at = NOW() WHERE title IN (?, ?)');
$hideStmt->bind_param('ss', $task1Title, $task2Title);
$hideStmt->execute();
$hiddenRows = $hideStmt->affected_rows;
$hideStmt->close();

// Make gold task wording focus on board representation and paper sketch.
$newTaskText = 'Nutze die Brettdarstellung. Trage Start, Ziel und Muenzen bei Bedarf auf Papier ein und bestimme die Antwort als "a;b": (1) kuerzeste Schritte fuer alle Muenzen + Ziel, (2) kuerzeste Schritte Start->Ziel.';
$newQuestion = 'Antwortformat: collect_then_goal;direct_goal';

$taskStmt = $conn->prepare('UPDATE tasks SET task_text = ?, question_text = ?, updated_at = NOW() WHERE title = ?');
$taskStmt->bind_param('sss', $newTaskText, $newQuestion, $task1Title);
$taskStmt->execute();
$taskRows = $taskStmt->affected_rows;
$taskStmt->close();

echo "Updated show_solution/show_solution_code rows: {$hiddenRows}\n";
echo "Updated task text rows (Goldspiel): {$taskRows}\n";

$checkSql = "SELECT id, title, show_solution, show_solution_code FROM tasks WHERE title IN ('RC BFS Goldspiel: Muenzen und Ziel', 'RC BFS Labyrinth: kuerzester Weg vs Rechtsregel') ORDER BY id";
$res = $conn->query($checkSql);
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
