<?php
require_once __DIR__ . '/../config/database.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "DB connection failed: " . $mysqli->connect_error . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$assignmentTitle = 'DB-Modus Demo: Kunden und Bestellungen';
$assignmentDescription = 'Eine kleine Beispielaufgabe im Datenbankmodus mit einem representativen DB-Modell-Auftrag.';

$adminQuery = $mysqli->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'admin' ORDER BY id");
$admins = [];
while ($row = $adminQuery->fetch_assoc()) {
    $admins[] = $row;
}
if (empty($admins)) {
    fwrite(STDERR, "No admin users found." . PHP_EOL);
    exit(1);
}

$assignmentStmt = $mysqli->prepare(
    'INSERT INTO assignments (title, description, created_by, is_active, difficulty, created_at, updated_at) VALUES (?, ?, ?, 1, ?, NOW(), NOW())'
);
$difficulty = 'beginner';
$assignmentStmt->bind_param('ssis', $assignmentTitle, $assignmentDescription, $admins[0]['id'], $difficulty);
$assignmentStmt->execute();
$assignmentId = $mysqli->insert_id;
$assignmentStmt->close();

$taskStmt = $mysqli->prepare(
    'INSERT INTO tasks (assignment_id, title, description, task_text, position, task_type, task_difficulty, problem_type, manual_review_required, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?, 1, NOW(), NOW())'
);
$taskTitle = 'Entwirf ein einfaches Datenbankmodell';
$taskDescription = 'Plane Tabellen und Beziehungen für Kunden, Bestellungen und Produkte.';
$taskText = 'Erstelle ein einfaches Datenbankmodell mit mindestens zwei Tabellen. Definiere passende Primär- und Fremdschlüssel und beschreibe kurz, wie die Tabellen zusammenhängen.';
$taskType = 'db_model';
$taskDifficulty = 'medium';
$problemType = 'essay';
$taskStmt->bind_param('issssss', $assignmentId, $taskTitle, $taskDescription, $taskText, $taskType, $taskDifficulty, $problemType);
$taskStmt->execute();
$taskId = $mysqli->insert_id;
$taskStmt->close();

foreach ($admins as $admin) {
    $existing = $mysqli->prepare('SELECT id FROM user_assignments WHERE user_id = ? AND assignment_id = ? LIMIT 1');
    $existing->bind_param('ii', $admin['id'], $assignmentId);
    $existing->execute();
    $existingResult = $existing->get_result();
    $existingRow = $existingResult->fetch_assoc();
    $existing->close();

    if ($existingRow) {
        continue;
    }

    $uaStmt = $mysqli->prepare('INSERT INTO user_assignments (user_id, assignment_id, status, assigned_by, assigned_at) VALUES (?, ?, "assigned", ?, NOW())');
    $uaStmt->bind_param('iii', $admin['id'], $assignmentId, $admins[0]['id']);
    $uaStmt->execute();
    $uaStmt->close();
}

echo "assignment_id=$assignmentId\n";
echo "task_id=$taskId\n";
echo "admins=" . count($admins) . "\n";
?>
