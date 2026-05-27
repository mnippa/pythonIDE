<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $assignmentId = 21;

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
}
.calculator {
  width: min(560px, 92vw);
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
}
h2 { margin-top: 0; }
.hint {
  margin-bottom: 14px;
  font-size: 14px;
  color: #374151;
}
.input-group { margin-bottom: 12px; }
.input-group label { display: block; margin-bottom: 6px; font-weight: 600; }
.input-group input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
}
.button-group {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-top: 12px;
}
.btn {
  border: 0;
  border-radius: 8px;
  padding: 10px 12px;
  font-weight: 600;
  cursor: pointer;
  background: #2563eb;
  color: #fff;
}
.result {
  margin-top: 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 12px;
  min-height: 44px;
  font-weight: 700;
}
CSS;

    $ideguiPy = <<<'PY'
import idegui as ui

# Das Runtime-Objekt wird von der Plattform bereitgestellt.
PY;

    $codeDrivenHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Code-Driven Taschenrechner</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="calculator">
    <h2>Code-Driven Taschenrechner</h2>
    <p class="hint">Ergaenze per Copy/Paste einen weiteren Button fuer EXP (a hoch b).</p>

    <div class="input-group">
      <label>Zahl a</label>
      <input type="number" data-element="a" step="any" value="2">
    </div>

    <div class="input-group">
      <label>Zahl b</label>
      <input type="number" data-element="b" step="any" value="3">
    </div>

    <div class="button-group">
      <button class="btn" data-run-python="true" data-run-name="plus">+ Addition</button>
      <button class="btn" data-run-python="true" data-run-name="minus">- Subtraktion</button>
      <button class="btn" data-run-python="true" data-run-name="mal">* Multiplikation</button>
      <button class="btn" data-run-python="true" data-run-name="geteilt">/ Division</button>
    </div>

    <div class="result" data-element="result">---</div>
  </div>
</body>
</html>
HTML;

        $codeDrivenSolutionHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code-Driven Taschenrechner</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="calculator">
        <h2>Code-Driven Taschenrechner</h2>
        <p class="hint">Loesung: EXP-Button ist bereits ergaenzt.</p>

        <div class="input-group">
            <label>Zahl a</label>
            <input type="number" data-element="a" step="any" value="2">
        </div>

        <div class="input-group">
            <label>Zahl b</label>
            <input type="number" data-element="b" step="any" value="3">
        </div>

        <div class="button-group">
            <button class="btn" data-run-python="true" data-run-name="plus">+ Addition</button>
            <button class="btn" data-run-python="true" data-run-name="minus">- Subtraktion</button>
            <button class="btn" data-run-python="true" data-run-name="mal">* Multiplikation</button>
            <button class="btn" data-run-python="true" data-run-name="geteilt">/ Division</button>
            <button class="btn" data-run-python="true" data-run-name="exp">EXP (a^b)</button>
        </div>

        <div class="result" data-element="result">---</div>
    </div>
</body>
</html>
HTML;

    $codeDrivenTemplate = <<<'PY'
import idegui as ui

try:
    a = float(ui.get('a', '0'))
    b = float(ui.get('b', '0'))
except ValueError:
    ui.set('result', 'Fehler: Bitte gueltige Zahlen eingeben.')
    raise SystemExit

trigger = ui.get('__trigger__')

if trigger == 'plus':
    ui.set('result', f'{a} + {b} = {a + b}')
elif trigger == 'minus':
    ui.set('result', f'{a} - {b} = {a - b}')
elif trigger == 'mal':
    ui.set('result', f'{a} * {b} = {a * b}')
elif trigger == 'geteilt':
    if b == 0:
        ui.set('result', 'Fehler: Division durch 0.')
    else:
        ui.set('result', f'{a} / {b} = {a / b}')

# TODO: Ergaenze hier den Trigger-Fall fuer "exp" (a ** b).
PY;

    $codeDrivenSolution = <<<'PY'
import idegui as ui

try:
    a = float(ui.get('a', '0'))
    b = float(ui.get('b', '0'))
except ValueError:
    ui.set('result', 'Fehler: Bitte gueltige Zahlen eingeben.')
    raise SystemExit

trigger = ui.get('__trigger__')

if trigger == 'plus':
    ui.set('result', f'{a} + {b} = {a + b}')
elif trigger == 'minus':
    ui.set('result', f'{a} - {b} = {a - b}')
elif trigger == 'mal':
    ui.set('result', f'{a} * {b} = {a * b}')
elif trigger == 'geteilt':
    if b == 0:
        ui.set('result', 'Fehler: Division durch 0.')
    else:
        ui.set('result', f'{a} / {b} = {a / b}')
elif trigger == 'exp':
    ui.set('result', f'{a} ** {b} = {a ** b}')
PY;

    $codeDrivenStoff = <<<'HTML'
<div class="stoff-block">
<h4>IDEGUI-Befehle (Kurzuebersicht)</h4>
<ul>
<li><code>ui.get("a", "0")</code> / <code>ui.get("b", "0")</code>: Liest die Eingabewerte aus HTML-Elementen mit <code>data-element</code>.</li>
<li><code>ui.set("result", wert)</code>: Schreibt das Ergebnis in das Ausgabefeld mit <code>data-element="result"</code>.</li>
<li><code>ui.get("__trigger__")</code>: Liefert im code-driven Modus den Namen des geklickten Buttons.</li>
<li><code>data-element="..."</code> (HTML): Verbindet HTML-Felder mit Python.</li>
<li><code>data-run-python="true"</code> (HTML): Startet Python beim Klick auf den Button.</li>
<li><code>data-run-name="..."</code> (HTML): Setzt den Trigger-Namen (z. B. <code>plus</code>, <code>exp</code>).</li>
</ul>
</div>
HTML;

    $eventDrivenHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Event-Driven Taschenrechner</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="calculator">
    <h2>Event-Driven Taschenrechner</h2>
    <p class="hint">Ergaenze per Copy/Paste einen EXP-Button mit data-function="exp".</p>

    <div class="input-group">
      <label>Zahl a</label>
      <input type="number" data-element="a" step="any" value="2">
    </div>

    <div class="input-group">
      <label>Zahl b</label>
      <input type="number" data-element="b" step="any" value="3">
    </div>

    <div class="button-group">
      <button class="btn" data-function="plus">+ Addition</button>
      <button class="btn" data-function="minus">- Subtraktion</button>
      <button class="btn" data-function="mal">* Multiplikation</button>
      <button class="btn" data-function="geteilt">/ Division</button>
    </div>

    <div class="result" data-element="result">---</div>
  </div>
</body>
</html>
HTML;

        $eventDrivenSolutionHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event-Driven Taschenrechner</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="calculator">
        <h2>Event-Driven Taschenrechner</h2>
        <p class="hint">Loesung: EXP-Button ist bereits ergaenzt.</p>

        <div class="input-group">
            <label>Zahl a</label>
            <input type="number" data-element="a" step="any" value="2">
        </div>

        <div class="input-group">
            <label>Zahl b</label>
            <input type="number" data-element="b" step="any" value="3">
        </div>

        <div class="button-group">
            <button class="btn" data-function="plus">+ Addition</button>
            <button class="btn" data-function="minus">- Subtraktion</button>
            <button class="btn" data-function="mal">* Multiplikation</button>
            <button class="btn" data-function="geteilt">/ Division</button>
            <button class="btn" data-function="exp">EXP (a^b)</button>
        </div>

        <div class="result" data-element="result">---</div>
    </div>
</body>
</html>
HTML;

    $eventDrivenTemplate = <<<'PY'
import idegui as ui

def lese_zahlen():
    try:
        return float(ui.get('a', '0')), float(ui.get('b', '0'))
    except ValueError:
        ui.set('result', 'Fehler: Bitte gueltige Zahlen eingeben.')
        return None, None


def plus(trigger):
    a, b = lese_zahlen()
    if a is None:
        return
    ui.set('result', f'{a} + {b} = {a + b}')


def minus(trigger):
    a, b = lese_zahlen()
    if a is None:
        return
    ui.set('result', f'{a} - {b} = {a - b}')


def mal(trigger):
    a, b = lese_zahlen()
    if a is None:
        return
    ui.set('result', f'{a} * {b} = {a * b}')


def geteilt(trigger):
    a, b = lese_zahlen()
    if a is None:
        return
    if b == 0:
        ui.set('result', 'Fehler: Division durch 0.')
    else:
        ui.set('result', f'{a} / {b} = {a / b}')


# TODO: Ergaenze eine Funktion def exp(trigger): ... mit a ** b.
PY;

    $eventDrivenSolution = <<<'PY'
import idegui as ui

def lese_zahlen():
    try:
        return float(ui.get('a', '0')), float(ui.get('b', '0'))
    except ValueError:
        ui.set('result', 'Fehler: Bitte gueltige Zahlen eingeben.')
        return None, None


def plus(trigger):
    a, b = lese_zahlen()
    if a is None:
        return
    ui.set('result', f'{a} + {b} = {a + b}')


def minus(trigger):
    a, b = lese_zahlen()
    if a is None:
        return
    ui.set('result', f'{a} - {b} = {a - b}')


def mal(trigger):
    a, b = lese_zahlen()
    if a is None:
        return
    ui.set('result', f'{a} * {b} = {a * b}')


def geteilt(trigger):
    a, b = lese_zahlen()
    if a is None:
        return
    if b == 0:
        ui.set('result', 'Fehler: Division durch 0.')
    else:
        ui.set('result', f'{a} / {b} = {a / b}')


def exp(trigger):
    a, b = lese_zahlen()
    if a is None:
        return
    ui.set('result', f'{a} ** {b} = {a ** b}')
PY;

    $tasks = [
        [
            'task_id' => 349,
            'title' => 'EXP-Taste (Code-Driven)',
            'description' => 'Fuege im HTML einen EXP-Button mit data-run-python="true" und data-run-name="exp" hinzu. Ergaenze danach in Python den Trigger-Fall fuer exp mit a ** b.',
            'task_text' => 'Ergaenze den Rechner um die EXP-Taste (a hoch b).',
            'hint1' => 'Code-Driven nutzt den Trigger ueber ui.get("__trigger__").',
            'hint2' => 'Der neue HTML-Button braucht data-run-name="exp".',
            'hint3' => 'In Python reicht ein neuer elif-Zweig fuer exp mit ui.set(...).',
            'stoff' => $codeDrivenStoff,
            'files' => [
                'index.html' => $codeDrivenHtml,
                'style.css' => $styleCss,
                'idegui.py' => $ideguiPy,
            ],
            'solution_files' => [
                'index.html' => $codeDrivenSolutionHtml,
            ],
            'code_template' => $codeDrivenTemplate,
            'solution_code' => $codeDrivenSolution,
        ],
        [
            'task_id' => 350,
            'title' => 'EXP-Taste (Event-Driven)',
            'description' => 'Fuege im HTML einen EXP-Button mit data-function="exp" hinzu. Ergaenze in Python eine Funktion exp(trigger), die a ** b berechnet.',
            'task_text' => 'Ergaenze den Rechner um eine Event-Driven EXP-Taste (a hoch b).',
            'hint1' => 'Event-Driven Buttons verwenden data-function="funktionsname".',
            'hint2' => 'Ergaenze nur eine neue Funktion exp(trigger), die lese_zahlen() nutzt.',
            'hint3' => 'Setze das Ergebnis mit ui.set("result", ...).',
            'stoff' => 'Event-Driven Trigger mit data-function, kleine Funktions-Erweiterung in Python. Diese Aufgabe ist manuell zu pruefen.',
            'files' => [
                'index.html' => $eventDrivenHtml,
                'style.css' => $styleCss,
                'idegui.py' => $ideguiPy,
            ],
            'solution_files' => [
                'index.html' => $eventDrivenSolutionHtml,
            ],
            'code_template' => $eventDrivenTemplate,
            'solution_code' => $eventDrivenSolution,
        ],
    ];

    $pdo->beginTransaction();

    $posStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM tasks WHERE assignment_id = ?');
    $findByIdStmt = $pdo->prepare('SELECT id, title FROM tasks WHERE assignment_id = ? AND id = ? LIMIT 1');
    $findByTitleStmt = $pdo->prepare('SELECT id, title FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');

    $insertSql = 'INSERT INTO tasks (
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
    )';

    $updateSql = 'UPDATE tasks SET
        description = ?, task_text = ?,
        task_type = ?, problem_type = ?, folderstructure = ?, allowDownload = ?, allow_code_ui_web_edit = ?,
        code_template = ?, solution_code = ?, hint1 = ?, hint2 = ?, hint3 = ?, stoff = ?,
        max_attempts = ?, iterations_count = ?, show_solution = ?, show_solution_code = ?,
        manual_review_required = ?,
        updated_at = NOW()
        WHERE id = ?';

    $insertStmt = $pdo->prepare($insertSql);
    $updateStmt = $pdo->prepare($updateSql);

    $results = [];

    foreach ($tasks as $cfg) {
        $existing = null;
        if (isset($cfg['task_id'])) {
            $findByIdStmt->execute([$assignmentId, (int)$cfg['task_id']]);
            $existing = $findByIdStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$existing) {
            $findByTitleStmt->execute([$assignmentId, $cfg['title']]);
            $existing = $findByTitleStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($existing) {
            $taskId = (int)$existing['id'];
            $updateStmt->execute([
                $cfg['description'],
                $cfg['task_text'],
                'code_ui',
                'code_completion',
                1,
                1,
                1,
                $cfg['code_template'],
                $cfg['solution_code'],
                $cfg['hint1'],
                $cfg['hint2'],
                $cfg['hint3'],
                $cfg['stoff'],
                10,
                1,
                1,
                1,
                1,
                $taskId,
            ]);
            $mode = 'updated';
        } else {
            $posStmt->execute([$assignmentId]);
            $nextPos = (int)$posStmt->fetch(PDO::FETCH_ASSOC)['next_pos'];

            $insertStmt->execute([
                $assignmentId,
                $cfg['title'],
                $cfg['description'],
                $cfg['task_text'],
                $nextPos,
                'code_ui',
                'code_completion',
                1,
                1,
                1,
                $cfg['code_template'],
                $cfg['solution_code'],
                $cfg['hint1'],
                $cfg['hint2'],
                $cfg['hint3'],
                $cfg['stoff'],
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

        foreach ($cfg['files'] as $name => $content) {
            file_put_contents($folder . '/' . $name, $content);
        }

        if (!empty($cfg['solution_files']) && is_array($cfg['solution_files'])) {
            $solutionFolder = $folder . '/.solution';
            if (!is_dir($solutionFolder) && !mkdir($solutionFolder, 0755, true) && !is_dir($solutionFolder)) {
                throw new RuntimeException('Solution-Ordner konnte nicht erstellt werden: ' . $solutionFolder);
            }
            foreach ($cfg['solution_files'] as $name => $content) {
                file_put_contents($solutionFolder . '/' . $name, $content);
            }
        }

        $results[] = [
            'id' => $taskId,
            'title' => (string)($existing['title'] ?? $cfg['title']),
            'mode' => $mode,
        ];
    }

    $pdo->commit();

    echo "Zwei einfache IDEGUI-Tasks erfolgreich erstellt/aktualisiert.\n";
    foreach ($results as $r) {
        echo sprintf("- [%s] Task %d: %s\n", strtoupper($r['mode']), $r['id'], $r['title']);
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
