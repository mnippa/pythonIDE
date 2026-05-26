<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $assignmentId = 21;
    $title = 'IDEGUI: Einfacher BMI-Rechner';

    $description = <<<'HTML'
<div class="task-details">
  <h4>Details</h4>
  <p>Baue einen sehr einfachen BMI-Rechner in IDEGUI.</p>
  <ul>
    <li>Das HTML-Template ist vorbereitet, aber die noetigen <code>data-*</code>-Attribute fehlen absichtlich.</li>
    <li>Ein Eingabefeld fuer <strong>Gewicht</strong> und ein <strong>Ausgabecontainer</strong> sind bereits vorhanden.</li>
    <li>Ein Eingabefeld fuer <strong>Groesse in cm</strong> soll von dir ergaenzt werden.</li>
  </ul>

  <h5>Hinweise zur BMI-Berechnung</h5>
  <ul>
    <li>Formel: <code>BMI = gewicht_kg / (groesse_m * groesse_m)</code></li>
    <li>Umrechnung: <code>groesse_m = groesse_cm / 100</code></li>
    <li>Optional: Ergebnis auf 1-2 Nachkommastellen runden.</li>
  </ul>
</div>
HTML;

    $taskText = 'Ergaenze das HTML um noetige data-Attribute und ein Eingabefeld fuer Groesse in cm. Implementiere danach in Python die BMI-Berechnung und gib das Ergebnis im vorbereiteten Ausgabebereich aus.';

    $stoff = <<<'HTML'
<div class="stoff-block">
  <h4>IDEGUI-Befehle (Kurzuebersicht)</h4>
  <ul>
    <li><code>ui.get("name", "default")</code>: Liest einen Wert aus einem Element mit passendem <code>data-element</code>.</li>
    <li><code>ui.set("name", wert)</code>: Schreibt Text/HTML in ein Ziel mit passendem <code>data-element</code>.</li>
    <li><code>ui.get("__trigger__")</code>: Liefert den ausloesenden Trigger-Namen im code-driven Modus.</li>
    <li><code>data-element="..."</code> (HTML): Verbindet HTML-Elemente mit Python.</li>
    <li><code>data-run-python="true"</code> (HTML): Laesst Python bei Klick auf den Button laufen.</li>
    <li><code>data-run-name="..."</code> (HTML): Setzt den Trigger-Namen fuer die Python-Abfrage.</li>
  </ul>
</div>
HTML;

    $indexHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BMI-Rechner (einfach)</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="card">
    <h2>BMI-Rechner</h2>
    <p class="intro">Ergaenze die fehlenden data-Attribute und den Input fuer Groesse in cm.</p>

    <label for="gewicht">Gewicht (kg)</label>
    <input id="gewicht" type="number" step="any" placeholder="z.B. 72">

    <!-- TODO: Hier ein zweites Eingabefeld fuer Groesse in cm ergaenzen. -->

    <button id="berechnen">BMI berechnen</button>

    <div class="result-box">
      <strong>Ergebnis:</strong>
      <div id="ausgabe">---</div>
    </div>
  </div>
</body>
</html>
HTML;

    $solutionIndexHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BMI-Rechner (einfach)</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="card">
    <h2>BMI-Rechner</h2>
    <p class="intro">Ergaenze die fehlenden data-Attribute und den Input fuer Groesse in cm.</p>

    <label for="gewicht">Gewicht (kg)</label>
    <input id="gewicht" data-element="gewicht" type="number" step="any" placeholder="z.B. 72">

    <label for="groesse_cm">Groesse (cm)</label>
    <input id="groesse_cm" data-element="groesse_cm" type="number" step="any" placeholder="z.B. 180">

    <button id="berechnen" data-run-python="true" data-run-name="bmi">BMI berechnen</button>

    <div class="result-box">
      <strong>Ergebnis:</strong>
      <div id="ausgabe" data-element="ausgabe">---</div>
    </div>
  </div>
</body>
</html>
HTML;

    $styleCss = <<<'CSS'
* { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  background: #f3f4f6;
  font-family: Segoe UI, sans-serif;
  padding: 20px;
}
.card {
  width: min(480px, 95vw);
  background: #ffffff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
}
h2 { margin-top: 0; }
.intro { color: #374151; margin-bottom: 14px; }
label {
  display: block;
  margin-top: 10px;
  margin-bottom: 6px;
  font-weight: 600;
}
input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
}
button {
  margin-top: 14px;
  width: 100%;
  border: 0;
  border-radius: 8px;
  padding: 11px 12px;
  background: #2563eb;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}
.result-box {
  margin-top: 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 10px;
}
#ausgabe { margin-top: 6px; font-weight: 700; }
CSS;

    $ideguiPy = <<<'PY'
import idegui as ui

# Hilfsmodul fuer IDEGUI-Tasks.
PY;

    $codeTemplate = <<<'PY'
import idegui as ui

# Schritt 1:
# - Ergaenze im HTML data-element-Attribute fuer Gewicht, Groesse und Ausgabefeld.
# - Ergaenze den Button um data-run-python="true" und data-run-name="bmi".

# Schritt 2:
# - Lese Gewicht (kg) und Groesse (cm) mit ui.get(...).
# - Wandle die Eingaben in float um.

# Schritt 3:
# - Rechne groesse_m = groesse_cm / 100.
# - Berechne bmi = gewicht / (groesse_m * groesse_m).

# Schritt 4:
# - Gib das Ergebnis mit ui.set(...) im Ausgabefeld aus.
# - Optional: Runde das Ergebnis auf 1 oder 2 Nachkommastellen.

# Tipp: Trigger fuer code-driven pruefen mit ui.get("__trigger__") == "bmi".
PY;

    $solutionCode = <<<'PY'
  import idegui as ui

  def _read_first(*names):
    for name in names:
      value = str(ui.get(name, '') or '').strip()
      if value != '':
        return value
    return ''

  def _write_output(text):
    ui.set('ausgabe', text)

  trigger = str(ui.get('__trigger__', '') or '').strip().lower()
  should_run = trigger in ('', 'bmi', 'berechnen')

  if should_run:
    try:
      gewicht = float(_read_first('gewicht', 'weight'))
      groesse_cm = float(_read_first('groesse_cm', 'groesse', 'height_cm', 'height'))
      if groesse_cm <= 0:
        _write_output('Fehler: Groesse muss > 0 sein.')
      else:
        groesse_m = groesse_cm / 100
        bmi = gewicht / (groesse_m * groesse_m)
        _write_output(f'BMI: {bmi:.1f}')
    except ValueError:
      _write_output('Fehler: Bitte Zahlen eingeben.')
  PY;

    $findStmt = $pdo->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    $findStmt->execute([$assignmentId, $title]);
    $existing = $findStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $taskId = (int)$existing['id'];
        $updateStmt = $pdo->prepare('UPDATE tasks SET
            description = ?,
            task_text = ?,
            task_type = ?,
            problem_type = ?,
            folderstructure = ?,
            allowDownload = ?,
            allow_code_ui_web_edit = ?,
            code_template = ?,
            solution_code = ?,
            hint1 = ?,
            hint2 = ?,
            hint3 = ?,
            stoff = ?,
            max_attempts = ?,
            iterations_count = ?,
            show_solution = ?,
            show_solution_code = ?,
            manual_review_required = ?,
            updated_at = NOW()
            WHERE id = ?');

        $updateStmt->execute([
            $description,
            $taskText,
            'code_ui',
            'code_completion',
            1,
            1,
            1,
            $codeTemplate,
            $solutionCode,
            'Fuege zuerst data-element fuer gewicht, groesse_cm und ausgabe ein.',
            'Button im code-driven Modus: data-run-python="true" und data-run-name="bmi".',
            'BMI = gewicht / ((groesse_cm / 100) ** 2).',
            $stoff,
            10,
            1,
            1,
            1,
            1,
            $taskId,
        ]);
        $mode = 'updated';
    } else {
        $posStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM tasks WHERE assignment_id = ?');
        $posStmt->execute([$assignmentId]);
        $nextPos = (int)$posStmt->fetch(PDO::FETCH_ASSOC)['next_pos'];

        $insertStmt = $pdo->prepare('INSERT INTO tasks (
            assignment_id, title, description, task_text, position,
            task_type, problem_type, folderstructure, allowDownload, allow_code_ui_web_edit,
            code_template, solution_code, hint1, hint2, hint3, stoff,
            max_attempts, iterations_count, show_solution, show_solution_code,
            manual_review_required,
            created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?,
            NOW(), NOW()
        )');

        $insertStmt->execute([
            $assignmentId,
            $title,
            $description,
            $taskText,
            $nextPos,
            'code_ui',
            'code_completion',
            1,
            1,
            1,
            $codeTemplate,
            $solutionCode,
            'Fuege zuerst data-element fuer gewicht, groesse_cm und ausgabe ein.',
            'Button im code-driven Modus: data-run-python="true" und data-run-name="bmi".',
            'BMI = gewicht / ((groesse_cm / 100) ** 2).',
            $stoff,
            10,
            1,
            1,
            1,
            1,
        ]);

        $taskId = (int)$pdo->lastInsertId();
        $mode = 'inserted';
    }

    $folder = __DIR__ . '/../storage/tasks/folders/task_' . $taskId;
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Ordner konnte nicht erstellt werden: ' . $folder);
    }

    file_put_contents($folder . '/index.html', $indexHtml);
    file_put_contents($folder . '/style.css', $styleCss);
    file_put_contents($folder . '/idegui.py', $ideguiPy);

    $solutionFolder = $folder . '/.solution';
    if (!is_dir($solutionFolder) && !mkdir($solutionFolder, 0755, true) && !is_dir($solutionFolder)) {
      throw new RuntimeException('Loesungsordner konnte nicht erstellt werden: ' . $solutionFolder);
    }
    file_put_contents($solutionFolder . '/index.html', $solutionIndexHtml);
    file_put_contents($solutionFolder . '/style.css', $styleCss);

    echo 'BMI-Task ' . strtoupper($mode) . PHP_EOL;
    echo 'task_id=' . $taskId . PHP_EOL;
    echo 'title=' . $title . PHP_EOL;
    echo 'assignment_id=' . $assignmentId . PHP_EOL;
    echo 'folder=' . $folder . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
