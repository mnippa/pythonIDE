<?php
require 'config/database.php';

$db = getDbConnection();

// Get max ID from tasks
$r = $db->query('SELECT MAX(id) as maxid FROM tasks');
$row = $r->fetch_assoc();
$nextId = (int)$row['maxid'] + 1;

echo "Creating new task ID: $nextId (copy of 312 with intelligent vars)\n\n";

$title = 'Haeufigkeitstabelle diskrete Werte (Intelligent)';
$description = '<p>Erstelle eine Häufigkeitstabelle als Dictionary. Die Notenliste wird mit randomisierten Werten generiert. Überprüfe:</p>
<ul>
<li>Das Dictionary <code>haeufigkeit</code> muss jeden Notenwert als Schlüssel haben</li>
<li>Der Wert zu jedem Schlüssel muss die Häufigkeit (Anzahl der Vorkommen) sein</li>
<li>Nur die Notentypen, die in der Liste <code>noten</code> vorkommen, müssen im Dictionary sein</li>
</ul>
<p><strong>Format:</strong> <code>haeufigkeit[note]</code> = Anzahl Vorkommen</p>';

$stoff = '<h4>Dictionary mit Häufigkeiten füllen</h4>
<ul>
<li>Durchlaufe jede Note aus der Liste</li>
<li>Prüfe, ob die Note bereits im Dictionary ist</li>
<li>Falls ja: Inkrementieren</li>
<li>Falls nein: Auf 1 setzen</li>
</ul>
<p><strong>Beispiel Ablauf:</strong></p>
<pre>noten = ["1.0", "2.0", "1.0"]
haeufigkeit = {}

Erste Note "1.0": nicht im Dict → haeufigkeit["1.0"] = 1
Zweite Note "2.0": nicht im Dict → haeufigkeit["2.0"] = 1
Dritte Note "1.0": bereits im Dict → haeufigkeit["1.0"] = 2

Ergebnis: {"1.0": 2, "2.0": 1}
</pre>';

// Code template with INIT block
$codeTemplate = <<<'PY'
#INIT START
noten = []
#INIT END

# Erzeuge die Haeufigkeitstabelle im Dictionary
haeufigkeit = {}

# TODO: Durchlaufe die noten-Liste und zaehle jeden Notenwert
PY;

// Solution code
$solutionCode = <<<'PY'
#INIT START
noten = []
#INIT END

# Erzeuge die Haeufigkeitstabelle im Dictionary
haeufigkeit = {}

# Durchlaufe die noten-Liste und zaehle jeden Notenwert
for note in noten:
    if note in haeufigkeit:
        haeufigkeit[note] += 1
    else:
        haeufigkeit[note] = 1
PY;

// Randomizer code - würfelt verschiedene Notenlisten
$randomizerCode = <<<'PY'
import random

# Verfügbare Notenstufen
alle_noten = ["1.0", "1.3", "1.7", "2.0", "2.3", "2.7", "3.0", "3.3", "3.7", "4.0", "5.0", "6.0"]

# Würfle eine zufällige Liste zwischen 15-30 Noten
liste_laenge = random.randint(15, 30)
noten = [random.choice(alle_noten) for _ in range(liste_laenge)]

values = {
    "noten": noten
}
PY;

// Test cases - intelligent vars mode
$testCases = json_encode([
    'mode' => 'vars',
    'tests' => 5,
    'inputs' => ['noten'],
    'outputs' => ['haeufigkeit']
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Insert the new task
$stmt = $db->prepare('
    INSERT INTO tasks (
        id, assignment_id, title, description, position, task_type, problem_type, 
        code_template, solution_code, randomizer_code, test_cases, 
        max_attempts, show_solution, show_solution_code, stoff
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');

$assignmentId = 1; // Adjust if needed, same as task 312
$position = 999;   // Adjust position as needed
$taskType = 'code';
$problemType = 'code_completion';
$maxAttempts = 10;
$showSolution = 0;
$showSolutionCode = 0;

$stmt->bind_param(
    'iississsssiiis',
    $nextId,
    $assignmentId,
    $title,
    $description,
    $position,
    $taskType,
    $problemType,
    $codeTemplate,
    $solutionCode,
    $randomizerCode,
    $testCases,
    $maxAttempts,
    $showSolution,
    $showSolutionCode,
    $stoff
);

if ($stmt->execute()) {
    echo "✓ New task created: ID $nextId\n";
    echo "  Title: $title\n";
    echo "  Type: intelligent vars mode\n";
    echo "  Inputs: noten\n";
    echo "  Outputs: haeufigkeit\n";
    echo "\nTest cases JSON:\n";
    echo $testCases . "\n";
} else {
    echo "✗ Error: " . $stmt->error . "\n";
}

$stmt->close();
