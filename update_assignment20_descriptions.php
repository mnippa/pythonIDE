<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

// Define descriptions for each task type
$descriptions = [
  // Code tasks
  116 => "=== MUSTERAUFGABE CODE (OUTPUT-Test) ===\n\nLogik: test_cases definieren Input-Output Szenarien\nEvaluation: code_template + student solution wird ausgeführt\ntest_cases Format: [{type: 'output', input: {...}, expected: '...'}, ...]\nRückgabe: Student gibt text via print() aus\nValidierung: Output mit expected_output vergleichen",
  
  117 => "=== MUSTERAUFGABE CODE (FUNCTION-Test) ===\n\nLogik: test_cases definieren Input-Output Szenarien\nEvaluation: code_template + student solution wird ausgeführt\ntest_cases Format: [{type: 'function', func_name: 'summe', args: [...], expected: ...}, ...]\nRückgabe: Student muss Funktion implementieren\nValidierung: Funktionsrückgabewert mit expected vergleichen",
  
  118 => "=== MUSTERAUFGABE CODE (VARIABLE-Test) ===\n\nLogik: test_cases definieren Input-Output Szenarien\nEvaluation: code_template + student solution wird ausgeführt\ntest_cases Format: [{type: 'variable', var_name: 'result', expected: ...}, ...]\nRückgabe: Student muss Variable berechnen und setzen\nValidierung: Variablenwert mit expected vergleichen",
  
  119 => "=== MUSTERAUFGABE CODE (CODE_CHECK-Test) ===\n\nLogik: test_cases definieren Code-Pattern Szenarien\nEvaluation: student solution wird als Text geparst\ntest_cases Format: [{type: 'code_check', keywords: ['for', 'range'], forbidden: ['while']}, ...]\nValidierung: Student-Code muss gewisse Keywords enthalten/ausschließen",
  
  120 => "=== MUSTERAUFGABE CODE (INTELLIGENT-Test) ===\n\nLogik: Intelligente Tests kombinieren mehrere Validierungsmethoden\nEvaluation: code_template + student solution wird ausgeführt + Code geparst\ntest_cases Format: [{mode: 'intelligent', tests: [...], ...}]\nValidierung: Output + Variablen + Keywords + Funktionen alle kombiniert",
  
  121 => "=== MUSTERAUFGABE CODE (KOMBINIERTE Tests) ===\n\nLogik: Mehrere test_cases in einem, verschiedene Types kombiniert\nEvaluation: code_template + student solution wird ausgeführt\ntest_cases Format: [{type: 'output', ...}, {type: 'function', ...}, {type: 'variable', ...}]\nValidierung: Alle Tests müssen bestanden werden",
  
  // Skip 122, 123 (single_choice, multiple_choice) - AUSNAHME
  
  // Free_text tasks
  126 => "=== MUSTERAUFGABE FREE_TEXT ===\n\nLogik: Student gibt Freitext ein, wird gegen Matching-Optionen geprüft\nMatching-Typen: exact (exakte Übereinstimmung), substring (Text enthalten), keywords (Schlüsselwörter)\nValidierung: correct_answer enthält String oder Regex\nFormat: kann auch JSON mit multiple Matching-Optionen sein\n\nMatchtypen:\n- exact: exact match (Groß/Kleinschreibung ignoriert)\n- substring: mindestens einer der Strings muss enthalten sein\n- keywords: mindestens N von M Schlüsselwörtern\n- regex: Regular Expression Match",
  
  127 => "=== MUSTERAUFGABE FREE_TEXT ===\n\nLogik: Student gibt Freitext ein, wird gegen Matching-Optionen geprüft\nMatching-Typen: exact (exakte Übereinstimmung), substring (Text enthalten), keywords (Schlüsselwörter)\nValidierung: correct_answer enthält String oder Regex\nFormat: kann auch JSON mit multiple Matching-Optionen sein\n\nMatchtypen:\n- exact: exact match (Groß/Kleinschreibung ignoriert)\n- substring: mindestens einer der Strings muss enthalten sein\n- keywords: mindestens N von M Schlüsselwörter\n- regex: Regular Expression Match",
  
  // Code_reading tasks
  128 => "=== MUSTERAUFGABE CODE_READING ===\n\nAnforderung: code_template muss komplette, ausführbare Code-Zeile enthalten\nTestwert-Übergabe: variable_overrides als JSON {varname: value, ...}\nBeispiel: variable_overrides = {\"A\": true, \"B\": false, \"C\": true}\nStudent ergänzt fehlende Zeile(n), result wird mit expected_output verglichen\nWichtig: seed-based randomization NICHT verwenden, nur fixed values\nIterationen: Wenn iterations_count > 1, student muss bei jedem Durchlauf neue fehlende Zeile ergänzen",
  
  129 => "=== MUSTERAUFGABE CODE_READING ===\n\nAnforderung: code_template muss komplette, ausführbare Code-Zeile enthalten\nTestwert-Übergabe: variable_overrides als JSON {varname: value, ...}\nBeispiel: variable_overrides = {\"A\": true, \"B\": false, \"C\": true}\nStudent ergänzt fehlende Zeile(n), result wird mit expected_output verglichen\nWichtig: seed-based randomization NICHT verwenden, nur fixed values\nIterationen: Wenn iterations_count > 1, student muss bei jedem Durchlauf neue fehlende Zeile ergänzen",
  
  // Code_random_complex tasks
  130 => "=== MUSTERAUFGABE CODE_RANDOM_COMPLEX ===\n\nZufallsfunktion-Aufbau: import random am Anfang, random.choice() oder random.randint()\ncode_template generiert dictionary mit random values: values = {\"VAR\": random.choice([True, False]), ...}\nInput-Format: values dict mit string keys: {\"n\": int, ...}\nPrüfvariablen: solution_code muss 'result' setzen mit Berechnung basierend auf values\nValidierung: result value wird mit expected_output (Funktion basierend auf Eingaben) verglichen\nIteration: max_iterations bestimmt Anzahl Zufalls-Versuche bis korrekt",
  
  131 => "=== MUSTERAUFGABE CODE_RANDOM_COMPLEX ===\n\nZufallsfunktion-Aufbau: import random am Anfang, random.choice() oder random.randint()\ncode_template generiert dictionary mit random values: values = {\"VAR\": random.choice([...]), ...}\nInput-Format: values dict mit string keys: {\"binary\": '101010', ...}\nPrüfvariablen: solution_code muss 'result' setzen mit Berechnung basierend auf values\nValidierung: result value wird mit expected_output (Funktion basierend auf Eingaben) verglichen\nIteration: max_iterations bestimmt Anzahl Zufalls-Versuche bis korrekt",
  
  132 => "=== MUSTERAUFGABE CODE_RANDOM_COMPLEX ===\n\nZufallsfunktion-Aufbau: import random am Anfang, random.choice() oder random.randint()\ncode_template generiert dictionary mit random values: values = {\"VAR\": random.choice([...]), ...}\nInput-Format: values dict mit string keys: {\"celsius\": float, ...}\nPrüfvariablen: solution_code muss 'result' setzen mit Berechnung basierend auf values\nValidierung: result value wird mit expected_output (Funktion basierend auf Eingaben) verglichen\nIteration: max_iterations bestimmt Anzahl Zufalls-Versuche bis korrekt"
];

echo "=== UPDATING DESCRIPTIONS IN ASSIGNMENT #20 ===\n\n";

$updated = 0;
foreach ($descriptions as $taskId => $description) {
  $taskId = intval($taskId);
  $description = $conn->real_escape_string($description);
  
  $sql = "UPDATE tasks SET description = '$description' WHERE id = $taskId AND assignment_id = 20";
  
  if ($conn->query($sql)) {
    echo "✓ Task ID $taskId updated\n";
    $updated++;
  } else {
    echo "✗ Task ID $taskId failed: " . $conn->error . "\n";
  }
}

echo "\n=== RESULTAT ===\n";
echo "Updated: $updated/14 tasks\n";
echo "Nicht geändert (AUSNAHME): 122, 123 (single_choice, multiple_choice)\n";

$conn->close();
