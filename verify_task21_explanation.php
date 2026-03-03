<?php
require_once __DIR__ . '/config/database.php';
$pdo = getPdoConnection();
$stmt = $pdo->prepare('SELECT id, title, LEFT(task_text, 220) AS task_preview, LEFT(description, 260) AS description_preview, COALESCE(question_text, "") AS question_text_value, LEFT(stoff, 260) AS stoff_preview, hint1, hint2, hint3, LEFT(code_template, 320) AS template_preview, LEFT(solution_code, 280) AS solution_preview FROM tasks WHERE id = 21');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "❌ Task #21 nicht gefunden\n";
    exit(1);
}

echo "Task: #{$row['id']} {$row['title']}\n\n";
echo "task_text Preview:\n{$row['task_preview']}...\n\n";
echo "description Preview:\n{$row['description_preview']}...\n\n";
echo "question_text: " . ($row['question_text_value'] === '' ? '[leer/deprecated]' : $row['question_text_value']) . "\n\n";
echo "stoff Preview:\n{$row['stoff_preview']}...\n";
echo "\nHints:\n";
echo "1) {$row['hint1']}\n";
echo "2) {$row['hint2']}\n";
echo "3) {$row['hint3']}\n";
echo "\ncode_template Preview:\n{$row['template_preview']}...\n";
echo "\nsolution_code Preview:\n{$row['solution_preview']}...\n";
