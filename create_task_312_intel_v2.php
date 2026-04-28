<?php
require 'config/database.php';

$db = getDbConnection();

// Get max ID
$r = $db->query('SELECT MAX(id) as maxid FROM tasks');
$row = $r->fetch_assoc();
$nextId = (int)$row['maxid'] + 1;

$assignmentId = 1;
$position = 999;
$title = 'Haeufigkeitstabelle diskrete Werte (Intelligent)';
$description = '<p>Erstelle eine Häufigkeitstabelle als Dictionary.</p>';
$taskType = 'code';
$problemType = 'code_completion';

$codeTemplate = "#INIT START\nnoten = []\n#INIT END\n\nhaeufigkeit = {}\n\n# TODO: Durchlaufe die noten-Liste und zaehle jeden Notenwert\n";

$solutionCode = "#INIT START\nnoten = []\n#INIT END\n\nhaeufigkeit = {}\n\nfor note in noten:\n    if note in haeufigkeit:\n        haeufigkeit[note] += 1\n    else:\n        haeufigkeit[note] = 1\n";

$randomizerCode = "import random\n\nalle_noten = [\"1.0\", \"1.3\", \"1.7\", \"2.0\", \"2.3\", \"2.7\", \"3.0\", \"3.3\", \"3.7\", \"4.0\", \"5.0\", \"6.0\"]\n\nliste_laenge = random.randint(15, 30)\nnoten = [random.choice(alle_noten) for _ in range(liste_laenge)]\n\nvalues = {\n    \"noten\": noten\n}\n";

$testCases = '{"mode":"vars","tests":5,"inputs":["noten"],"outputs":["haeufigkeit"]}';

$stoff = '<h4>Dictionary mit Häufigkeiten füllen</h4>';

$maxAttempts = 10;
$showSolution = 0;
$showSolutionCode = 0;

// Direct insert
$sql = "INSERT INTO tasks (
    id, assignment_id, title, description, position, task_type, problem_type, 
    code_template, solution_code, randomizer_code, test_cases, 
    max_attempts, show_solution, show_solution_code, stoff
) VALUES (
    $nextId, $assignmentId, ?, ?, $position, ?, ?,
    ?, ?, ?, ?,
    $maxAttempts, $showSolution, $showSolutionCode, ?
)";

$stmt = $db->prepare($sql);
$stmt->bind_param('sssssssss', $title, $description, $taskType, $problemType, $codeTemplate, $solutionCode, $randomizerCode, $testCases, $stoff);

if ($stmt->execute()) {
    echo "✓ Task created: ID $nextId\n";
    echo "  Test Cases: $testCases\n";
} else {
    echo "✗ Error: " . $stmt->error . "\n";
}
$stmt->close();
