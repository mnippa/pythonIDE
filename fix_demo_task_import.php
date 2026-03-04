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

  // Python code template - WITH import idegui!
  $code_template = <<<'PYTHON'
import idegui as ui

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

  // UPDATE task 171 with correct code
  $sql = "UPDATE tasks SET 
    code_template = :code_template,
    solution_code = :solution_code
  WHERE id = 171";

  $stmt = $pdo->prepare($sql);
  $result = $stmt->execute([
    ':code_template' => $code_template,
    ':solution_code' => $solution_code
  ]);

  if ($result) {
    echo "✓ Task 171 aktualisiert mit 'import idegui'!\n";
    echo "Code: \n";
    echo $code_template . "\n";
  } else {
    echo "✗ Update fehlgeschlagen\n";
  }

} catch (PDOException $e) {
  echo "Datenbankfehler: " . $e->getMessage() . "\n";
  exit(1);
} catch (Exception $e) {
  echo "Fehler: " . $e->getMessage() . "\n";
  exit(1);
}
?>
