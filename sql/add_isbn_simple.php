<?php
require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();

// Get next position
$result = $conn->query("SELECT COALESCE(MAX(position), 0) + 1 as next_position FROM tasks WHERE assignment_id = 21");
$nextPosition = $result->fetch_assoc()['next_position'];

// Test cases JSON
$testCases = json_encode([
    ['type' => 'output', 'validation_mode' => 'pattern', 'pattern' => '^ISBN\\s+(978|979)-\\d{1,5}-\\d{1,7}-\\d{1,7}-\\d{1}$', 'description' => 'Gültige ISBN-13']
]);

// Description HTML
$description = '<div class="test-requirements-section"><h3>Test-Anforderungen</h3><table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody><tr><td>OUTPUT</td><td>Pattern Match (Regex)</td></tr></tbody></table></div>';

// Task text
$taskText = "Schreibe ein Python-Programm, das eine gültige ISBN-13 ausgibt.\n\n**Format:** `ISBN 978-X-XX-XXXXXX-X` oder `ISBN 979-X-XX-XXXXXX-X`\n\n**Beispiele:**\n- `ISBN 978-3-16-148410-0`\n- `ISBN 979-1-23-456789-5`";

// Prepare statement
$sql = "INSERT INTO tasks (assignment_id, title, description, position, max_attempts, iterations_count, show_solution, show_solution_code, min_keywords_required, problem_type, code_template, hint1, hint2, hint3, stoff, expected_output, test_cases, solution_code, task_type, task_text, question_text, image_url, correct_answer, variable_overrides, randomizer_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

// Values
$aid = 21;
$tit = "ISBN Validierung";
$pos = $nextPosition;
$mat = 3;
$itc = 1;
$shs = 1;
$shc = 1;
$mkr = 0;
$pty = "code_completion";
$ctp = "# Gib eine gültige ISBN aus\nprint(\"ISBN \")";
$h1 = "Eine ISBN-13 beginnt mit 978 oder 979";
$h2 = "Das Format ist: ISBN XXX-X-XX-XXXXXX-X";
$h3 = "Verwende print() für die Ausgabe";
$sto = "ISBN ist eine weltweit eindeutige Produktkennzeichnung für Bücher.";
$exo = "";
$slc = "print(\"ISBN 978-3-16-148410-0\")";
$tty = "code";
$qut = "";
$imu = null;
$coa = null;
$var = null;
$rnd = null;

$stmt->bind_param("issiiiiiissssssssssssssss", $aid, $tit, $description, $pos, $mat, $itc, $shs, $shc, $mkr, $pty, $ctp, $h1, $h2, $h3, $sto, $exo, $testCases, $slc, $tty, $taskText, $qut, $imu, $coa, $var, $rnd);

if ($stmt->execute()) {
    echo "✅ Task #{$conn->insert_id} erfolgreich erstellt!\n";
    echo "Position: $nextPosition in Assignment #21\n\n";
    
    $r = $conn->query("SELECT id, position, title FROM tasks WHERE assignment_id = 21 ORDER BY position");
    while ($row = $r->fetch_assoc()) {
        echo "ID {$row['id']}: Pos {$row['position']} - {$row['title']}\n";
    }
} else {
    echo "❌ Fehler: " . $stmt->error . "\n";
}
