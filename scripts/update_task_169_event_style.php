<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$description = <<<'HTML'
<div class="task-details">
  <h4>Aufgabenbeschreibung</h4>
  <p>Baue einen Mini-Taschenrechner als <strong>reine Ereignislogik</strong> ohne zentrale <code>if/elif</code>-Verzweigung.</p>
  <ul>
    <li>Jeder Button ruft direkt die passende Funktion auf (z. B. <code>plus(trigger)</code>).</li>
    <li>Der Trigger wird als Objekt übergeben: <code>trigger.name</code> und <code>trigger.value</code>.</li>
    <li>Die UI wird direkt in der jeweiligen Funktion gelesen/geschrieben.</li>
    <li>Fehlerfälle wie ungültige Zahlen und Division durch 0 werden pro Handler behandelt.</li>
  </ul>
</div>
HTML;

$taskText = 'Erstelle einen Mini-Taschenrechner mit vier Event-Funktionen plus(trigger), minus(trigger), mal(trigger), geteilt(trigger). Kein if/elif-Dispatch im Hauptprogramm. Jede Funktion verarbeitet die Eingaben und schreibt direkt ins UI.';

$codeTemplate = <<<'PY'
import idegui as ui


def _read_inputs():
    a = float(ui.get("a"))
    b = float(ui.get("b"))
    ui.set("error", "")
    return a, b


def plus(trigger):
    try:
        a, b = _read_inputs()
        ui.set("result", a + b)
    except ValueError:
        ui.set("error", "Bitte gültige Zahlen eingeben.")


def minus(trigger):
    try:
        a, b = _read_inputs()
        ui.set("result", a - b)
    except ValueError:
        ui.set("error", "Bitte gültige Zahlen eingeben.")


def mal(trigger):
    try:
        a, b = _read_inputs()
        ui.set("result", a * b)
    except ValueError:
        ui.set("error", "Bitte gültige Zahlen eingeben.")


def geteilt(trigger):
    try:
        a, b = _read_inputs()
        if b == 0:
            ui.set("error", "Division durch 0 ist nicht erlaubt.")
            ui.set("result", "-")
            return
        ui.set("result", a / b)
    except ValueError:
        ui.set("error", "Bitte gültige Zahlen eingeben.")
PY;

$solutionCode = <<<'PY'
import idegui as ui


def _read_inputs():
    a = float(ui.get("a"))
    b = float(ui.get("b"))
    ui.set("error", "")
    return a, b


def plus(trigger):
    try:
        a, b = _read_inputs()
        ui.set("result", a + b)
    except ValueError:
        ui.set("error", "Bitte gültige Zahlen eingeben.")


def minus(trigger):
    try:
        a, b = _read_inputs()
        ui.set("result", a - b)
    except ValueError:
        ui.set("error", "Bitte gültige Zahlen eingeben.")


def mal(trigger):
    try:
        a, b = _read_inputs()
        ui.set("result", a * b)
    except ValueError:
        ui.set("error", "Bitte gültige Zahlen eingeben.")


def geteilt(trigger):
    try:
        a, b = _read_inputs()
        if b == 0:
            ui.set("error", "Division durch 0 ist nicht erlaubt.")
            ui.set("result", "-")
            return
        ui.set("result", a / b)
    except ValueError:
        ui.set("error", "Bitte gültige Zahlen eingeben.")
PY;

$stoff = <<<'HTML'
<div class="stoff-block">
  <h4>Stoff: Event-Handler mit Trigger-Objekt</h4>
  <ul>
    <li><strong>Direktes Ereignismodell:</strong> <code>data-run-name="plus"</code> ruft <code>plus(trigger)</code> auf.</li>
    <li><strong>Trigger-Kontext:</strong> Name/Wert über <code>trigger.name</code> und <code>trigger.value</code>.</li>
    <li><strong>Klare Verantwortung:</strong> Jeder Handler erledigt genau eine Operation.</li>
    <li><strong>UI-Bridge:</strong> Lesen über <code>ui.get(...)</code>, Schreiben über <code>ui.set(...)</code>.</li>
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
WHERE id = 169
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':title' => 'Mini-Taschenrechner (Event-Funktionen)',
  ':description' => $description,
  ':task_text' => $taskText,
  ':code_template' => $codeTemplate,
  ':solution_code' => $solutionCode,
  ':hint1' => 'Lege vier Handler an: plus(trigger), minus(trigger), mal(trigger), geteilt(trigger).',
  ':hint2' => 'Kein if/elif-Dispatch nötig: der Trigger ruft die passende Funktion direkt auf.',
  ':hint3' => 'Nutze ui.get(...) für Eingaben und ui.set(...) für result/error.',
  ':stoff' => $stoff,
]);

echo "Task 169 updated to event style.\n";
