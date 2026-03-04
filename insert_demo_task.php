<?php
// DB-Konfiguration
$db_host = 'localhost';
$db_name = 'pythonide';
$db_user = 'root';
$db_pass = 'start123';

try {
  $pdo = new PDO(
    "mysql:host={$db_host};dbname={$db_name}",
    $db_user,
    $db_pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  // Python code template for Event-Driven mode
  $code_template = <<<'PYTHON'
#Init
counter = 0

def multiply(trigger):
    global counter
    counter *= 2
    ui.set("output2", f"Ergebnis: {counter} (× 2)")

def divide(trigger):
    global counter
    counter /= 2
    ui.set("output2", f"Ergebnis: {counter:.1f} (÷ 2)")

def reset_event(trigger):
    global counter
    counter = 10
    ui.set("output2", "Ergebnis: Zurückgesetzt auf 10")

ui.set("output1", "Warte auf Klick...")
ui.set("output2", "Warte auf Klick...")
PYTHON;

  $solution_code = $code_template;

  $sql = "INSERT INTO tasks (
    assignment_id,
    title,
    description,
    task_type,
    problem_type,
    folderstructure,
    allow_code_ui_web_edit,
    code_template,
    solution_code,
    hint1,
    hint2,
    hint3,
    stoff,
    position
  ) VALUES (
    :assignment_id,
    :title,
    :description,
    :task_type,
    :problem_type,
    :folderstructure,
    :allow_code_ui_web_edit,
    :code_template,
    :solution_code,
    :hint1,
    :hint2,
    :hint3,
    :stoff,
    :position
  )";

  $stmt = $pdo->prepare($sql);
  $result = $stmt->execute([
    ':assignment_id' => 1,
    ':title' => 'Demo: Zwei Trigger-Modi (data-run-python vs data-function)',
    ':description' => 'Vergleiche Traditional Mode (data-run-python) mit Event-Driven Mode (data-function) nebeneinander.',
    ':task_type' => 'code_ui',
    ':problem_type' => 'code_completion',
    ':folderstructure' => 1,
    ':allow_code_ui_web_edit' => 1,
    ':code_template' => $code_template,
    ':solution_code' => $solution_code,
    ':hint1' => 'Traditional Mode (links): Jeder Klick führt den kompletten Code neu aus. Trigger wird mit if/elif geprüft.',
    ':hint2' => 'Event-Driven Mode (rechts): Nur die genannte Funktion wird aufgerufen. Globale Variablen bleiben erhalten!',
    ':hint3' => 'Versuche: Klick auf Button → Eingabe ändern → Klick nochmal. Beachte den Unterschied!',
    ':stoff' => 'Code-UI Event-Driven Architecture',
    ':position' => 500
  ]);

  if ($result) {
    $taskId = $pdo->lastInsertId();
    echo "✓ Task erfolgreich erstellt!\n";
    echo "Task ID: $taskId\n";
    echo "Type: code_ui\n";
    echo "Status: Bereit zum Nutzen!\n";
  } else {
    echo "✗ Insert fehlgeschlagen\n";
  }

} catch (PDOException $e) {
  echo "Datenbankfehler: " . $e->getMessage() . "\n";
  exit(1);
} catch (Exception $e) {
  echo "Fehler: " . $e->getMessage() . "\n";
  exit(1);
}
?>
