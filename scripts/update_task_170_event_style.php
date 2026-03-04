<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$description = <<<'HTML'
<div class="task-details">
  <h4>Aufgabenbeschreibung</h4>
  <p>Erstelle einen <strong>Taschenrechner</strong> im <strong>Ereignisstil</strong> ohne zentrale <code>if/else</code>-Steuerung.</p>

  <h5>Anforderungen:</h5>
  <ol>
    <li><strong>Vier Handler-Funktionen:</strong> <code>plus(trigger)</code>, <code>minus(trigger)</code>, <code>mal(trigger)</code>, <code>geteilt(trigger)</code></li>
    <li><strong>Direkter Trigger-Aufruf:</strong> Der Runtime-Trigger ruft automatisch die passende Funktion auf</li>
    <li><strong>Trigger-Objekt:</strong> Nutze <code>ui.trigger.name</code> und <code>ui.trigger.value</code> bei Bedarf</li>
    <li><strong>UI in der Funktion:</strong> Lesen/Schreiben der UI direkt im jeweiligen Event-Handler</li>
    <li><strong>Fehlerbehandlung:</strong> Division durch 0 und ungültige Eingaben behandeln</li>
  </ol>
</div>
HTML;

$taskText = 'Baue einen Mini-Taschenrechner als reine Sammlung von Event-Funktionen. Der geklickte Button ruft direkt die gleichnamige Python-Funktion auf (z.B. plus(trigger)). Keine if/else-Dispatch-Logik im Hauptprogramm.';

$initialCode = <<<'PY'
import idegui as ui

# Definiere hier vier Event-Funktionen.
# Jede Funktion wird automatisch aufgerufen, wenn der passende Button geklickt wird.
# Beispiel: data-run-name="plus" ruft plus(ui.trigger) auf.

def plus(trigger):
    """Event-Handler für Addition"""
    try:
        a = float(ui.get("a"))
        b = float(ui.get("b"))
        ui.set("result", a + b)
    except ValueError:
        ui.set("result", "Fehler: Bitte Zahlen eingeben")


def minus(trigger):
    """Event-Handler für Subtraktion"""
    try:
        a = float(ui.get("a"))
        b = float(ui.get("b"))
        ui.set("result", a - b)
    except ValueError:
        ui.set("result", "Fehler: Bitte Zahlen eingeben")


def mal(trigger):
    """Event-Handler für Multiplikation"""
    try:
        a = float(ui.get("a"))
        b = float(ui.get("b"))
        ui.set("result", a * b)
    except ValueError:
        ui.set("result", "Fehler: Bitte Zahlen eingeben")


def geteilt(trigger):
    """Event-Handler für Division"""
    try:
        a = float(ui.get("a"))
        b = float(ui.get("b"))
        if b == 0:
            ui.set("result", "Fehler: Division durch 0 nicht möglich")
            return
        ui.set("result", a / b)
    except ValueError:
        ui.set("result", "Fehler: Bitte Zahlen eingeben")
PY;

$solutionCode = $initialCode;

$stoff = <<<'HTML'
<div class="stoff-block">
  <h4>Stoff: Ereignisfunktionen statt zentraler Dispatch-Logik</h4>
  <ul>
    <li><strong>Event-Handler:</strong> Jede Aktion hat eine eigene Funktion (z.B. <code>plus(trigger)</code>)</li>
    <li><strong>Direkte Zuordnung:</strong> <code>data-run-name="plus"</code> → Funktion <code>plus(...)</code></li>
    <li><strong>Trigger-Kontext:</strong> <code>ui.trigger.name</code> und <code>ui.trigger.value</code> sind verfügbar</li>
    <li><strong>Weniger Verzweigung:</strong> Kein großes <code>if/elif</code>-Konstrukt nötig</li>
  </ul>
</div>
HTML;

$sql = "
UPDATE tasks
SET
  title = :title,
  description = :description,
  task_text = :task_text,
  code_template = :code_template,
  solution_code = :solution_code,
  hint1 = :hint1,
  hint2 = :hint2,
  hint3 = :hint3,
  stoff = :stoff,
  updated_at = NOW()
WHERE id = 170
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':title' => 'Taschenrechner mit Event-Funktionen',
  ':description' => $description,
  ':task_text' => $taskText,
  ':code_template' => $initialCode,
  ':solution_code' => $solutionCode,
  ':hint1' => 'Funktion plus(trigger) wird automatisch bei data-run-name="plus" aufgerufen.',
  ':hint2' => 'Du kannst optional ui.trigger.name und ui.trigger.value im Handler verwenden.',
  ':hint3' => 'Jede Funktion liest die Inputs mit ui.get(...) und schreibt das Ergebnis mit ui.set(...).',
  ':stoff' => $stoff,
]);

echo "Task 170 updated to event-function style.\n";
