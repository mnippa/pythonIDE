<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

// Get Task 170 solution_code
$stmt = $conn->prepare('SELECT solution_code FROM tasks WHERE id = 170');
$stmt->execute();
$task170 = $stmt->get_result()->fetch_assoc();
$code170 = $task170['solution_code'];

// Get Task 172 solution_code
$stmt = $conn->prepare('SELECT solution_code FROM tasks WHERE id = 172');
$stmt->execute();
$task172 = $stmt->get_result()->fetch_assoc();
$code172 = $task172['solution_code'];

echo "=== DIFFERENCE BETWEEN Task 170 AND Task 172 ===\n\n";

echo "Task 170 (Projekt 22) - Event-Funktionen:\n";
echo "----------------------------------------\n";
echo "- Definiert 4 Funktionen: plus(), minus(), mal(), geteilt()\n";
echo "- Jede Funktion wird automatisch aufgerufen wenn Button geklickt\n";
echo "- data-run-name=\"plus\" triggert automatisch plus() Funktion\n";
echo "- EVENT-HANDLER Stil\n\n";

echo "Task 172 (Projekt 23) - Run-Logik mit Trigger-Dispatch:\n";
echo "----------------------------------------\n";
echo "- HAT KEINE Funktionsdefinitionen\n";
echo "- Alle Operationen sind im Main-Code\n";
echo "- ui._refresh_trigger() liest den Trigger aus HTML\n";
echo "- if ui.trigger.name == \"plus\": ... führt Logik aus\n";
echo "- RUN-LOGIK mit Trigger-Dispatch Stil\n\n";

echo "=== Task 172 Code Preview ===\n";
echo substr($code172, 0, 500);
echo "\n...\n";
