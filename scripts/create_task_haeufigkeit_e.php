<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getPdoConnection();

// Get base from task 319
$base = $pdo->query('SELECT * FROM tasks WHERE id=319')->fetch(PDO::FETCH_ASSOC);

$nextPos = (int)$pdo->query(
    'SELECT COALESCE(MAX(position),0)+1 FROM tasks WHERE assignment_id=29'
)->fetchColumn();

$title = 'Haeufigkeit mit Klassen (E: ungleiche Verteilung)';

// 50 values with clearly unequal class frequencies:
// 0-9: 15, 10-19: 8, 20-29: 12, 30-39: 6, ausserhalb: 9  => total 50
$code_template = <<<'PYTHON'
#INIT START
werte = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 0, 2, 5, 7, 9,
         10, 11, 12, 13, 14, 15, 16, 17,
         20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 20, 25,
         30, 31, 32, 33, 34, 35,
         -5, -2, -1, 40, 45, 50, -3, 42, 48, 99]
#INIT END

haeufigkeit_klassen = {
    '0-9': 0,
    '10-19': 0,
    '20-29': 0,
    '30-39': 0,
    'ausserhalb': 0
}

# Ergaenze hier die Auswertung
for wert in werte:
    pass  # TODO: Klasse bestimmen und Zaehler erhoehen

print("Haeufigkeiten:")
for klasse in haeufigkeit_klassen:
    print(f"  {klasse}: {haeufigkeit_klassen[klasse]}")
PYTHON;

$solution_code = <<<'PYTHON'
#INIT START
werte = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 0, 2, 5, 7, 9,
         10, 11, 12, 13, 14, 15, 16, 17,
         20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 20, 25,
         30, 31, 32, 33, 34, 35,
         -5, -2, -1, 40, 45, 50, -3, 42, 48, 99]
#INIT END

haeufigkeit_klassen = {
    '0-9': 0,
    '10-19': 0,
    '20-29': 0,
    '30-39': 0,
    'ausserhalb': 0
}

for wert in werte:
    if 0 <= wert <= 9:
        haeufigkeit_klassen['0-9'] += 1
    elif 10 <= wert <= 19:
        haeufigkeit_klassen['10-19'] += 1
    elif 20 <= wert <= 29:
        haeufigkeit_klassen['20-29'] += 1
    elif 30 <= wert <= 39:
        haeufigkeit_klassen['30-39'] += 1
    else:
        haeufigkeit_klassen['ausserhalb'] += 1

print("Haeufigkeiten:")
for klasse in haeufigkeit_klassen:
    print(f"  {klasse}: {haeufigkeit_klassen[klasse]}")
PYTHON;

$expected_output = "Haeufigkeiten:\n  0-9: 15\n  10-19: 8\n  20-29: 12\n  30-39: 6\n  ausserhalb: 9";

// stoff: conceptual explanation, not implementation
$stoff = <<<'HTML'
<h4>Häufigkeitsklassen</h4>
<p>
  Wenn viele Messwerte vorliegen, fasst man sie in <strong>Klassen</strong>
  (Intervalle) zusammen und zählt, wie viele Werte in jede Klasse fallen.
  Das Ergebnis heißt <em>Klassenhäufigkeit</em>.
</p>
<p>
  <strong>Wann sinnvoll?</strong> Bei kontinuierlichen oder stark gestreuten
  Daten, bei denen eine Einzelwert-Häufigkeit unübersichtlich wäre – z.&nbsp;B.
  Messwerte, Alter, Temperaturen.
</p>
<p>
  Typische Schritte: Klassenbreite festlegen → Grenzen definieren →
  jeden Wert einer Klasse zuordnen → Zähler erhöhen.
</p>
HTML;

// description: autodescr format (test-requirements-section)
// Test case is intelligent vars mode: inputs=["werte"], outputs=["haeufigkeit_klassen"]
$description = '<div class="test-requirements-section"><h3>Test-Anforderungen</h3>'
    . '<table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody>'
    . '<tr><td>INPUTS erwartet</td><td>1</td></tr>'
    . '<tr><td>Input-Variablen</td><td>werte</td></tr>'
    . '<tr><td>Checking</td><td>haeufigkeit_klassen</td></tr>'
    . '</tbody></table></div>';

$hint1 = $base['hint1'];
$hint2 = $base['hint2'];
$hint3 = $base['hint3'];

$stmt = $pdo->prepare("INSERT INTO tasks
    (assignment_id, title, description, position, max_attempts, iterations_count,
     show_solution, show_solution_code, min_keywords_required, problem_type,
     code_template, hint1, hint2, hint3, stoff, expected_output, test_cases,
     solution_code, task_type, task_text, question_text, image_url, correct_answer,
     variable_overrides, randomizer_code)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$stmt->execute([
    29, $title, $description, $nextPos,
    (int)$base['max_attempts'], (int)$base['iterations_count'],
    (int)$base['show_solution'], (int)$base['show_solution_code'],
    (int)$base['min_keywords_required'], $base['problem_type'],
    $code_template, $hint1, $hint2, $hint3,
    $stoff, $expected_output, $base['test_cases'],
    $solution_code, $base['task_type'],
    $base['task_text'], '', '', '',
    null, $base['randomizer_code'] ?? ''
]);

$newId = $pdo->lastInsertId();
echo "Created task E: id=$newId  title=$title  pos=$nextPos\n";
echo "Expected output:\n$expected_output\n";
