<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$assignmentId = 21;
$anchorTaskId = 170;

$calcRunDescription = <<<'HTML'
<div class="task-details">
  <h4>Aufgabenbeschreibung</h4>
  <p>Nutze das gleiche UI-Layout wie beim vorherigen Taschenrechner, aber implementiere die Logik als <strong>Python-Run-Dispatch</strong> mit <code>if/elif</code> auf <code>ui.trigger.name</code>.</p>
  <h5>Anforderungen:</h5>
  <ol>
    <li>Zwei Zahlen aus <code>data-element="a"</code> und <code>data-element="b"</code> lesen</li>
    <li>Trigger über <code>ui._refresh_trigger()</code> und <code>ui.trigger.name</code> auswerten</li>
    <li>Operationen: plus, minus, mal, geteilt</li>
    <li>Fehlerfälle behandeln (ungültige Eingaben, Division durch 0)</li>
  </ol>
</div>
HTML;

$calcRunCodeTemplate = <<<'PY'
import idegui as ui

# Trigger-Werte aus der Runtime übernehmen
ui._refresh_trigger()

try:
    a = float(ui.get('a', '0'))
    b = float(ui.get('b', '0'))
except ValueError:
    ui.set('result', 'Fehler: Bitte gültige Zahlen eingeben')
else:
    if ui.trigger.name == 'plus':
        ui.set('result', f"{a} + {b} = {a + b}")
    elif ui.trigger.name == 'minus':
        ui.set('result', f"{a} - {b} = {a - b}")
    elif ui.trigger.name == 'mal':
        ui.set('result', f"{a} * {b} = {a * b}")
    elif ui.trigger.name == 'geteilt':
        if b == 0:
            ui.set('result', 'Fehler: Division durch 0')
        else:
            ui.set('result', f"{a} / {b} = {a / b}")
    else:
        ui.set('result', 'Bitte eine Operation klicken')
PY;

$dynamicSimpleDescription = <<<'HTML'
<div class="task-details">
  <h4>Aufgabenbeschreibung</h4>
  <p>Erzeuge die UI <strong>vollständig zur Laufzeit in Python</strong> mit der idegui-API.</p>
  <h5>Anforderungen:</h5>
  <ol>
    <li>Kein statisches Formular im HTML nötig</li>
    <li>Erzeuge Felder und Button in Python (<code>ui.number</code>, <code>ui.button</code>)</li>
    <li>Nutze <code>button.on_click(...)</code> für die Berechnung</li>
    <li>Ausgabe in einem dynamischen Output-Bereich</li>
  </ol>
</div>
HTML;

$dynamicSimpleTemplate = <<<'PY'
import idegui as ui

ui.clear()
ui.title('Dynamische UI: Einfaches Beispiel')

zahl_a = ui.number('Zahl A', 10)
zahl_b = ui.number('Zahl B', 20)
ausgabe = ui.output()

def addiere():
    ausgabe.clear()
    try:
        gesamt = float(zahl_a.value) + float(zahl_b.value)
        ausgabe.write(f'Summe: {gesamt}')
    except Exception:
        ausgabe.write('Fehler: Eingaben sind ungültig')

ui.button('Addieren').on_click(addiere)
ausgabe.write('Klicke auf "Addieren".')
PY;

$dynamicComplexDescription = <<<'HTML'
<div class="task-details">
  <h4>Aufgabenbeschreibung</h4>
  <p>Baue eine dynamische Summen-App mit <strong>ersetzbaren Eingabefeldern</strong>.</p>
  <h5>Anforderungen:</h5>
  <ol>
    <li>Eine Selectbox mit Anzahl 1 bis 5 bereitstellen</li>
    <li>Beim Neuaufbau alte Felder löschen und neue Felder erzeugen</li>
    <li>Mit den <em>neu erzeugten</em> Feldern rechnen</li>
    <li>Summe aller aktuellen Felder ausgeben</li>
  </ol>
</div>
HTML;

$dynamicComplexTemplate = <<<'PY'
import idegui as ui

if 'active_count' not in globals():
    active_count = 3


def render_app(count):
    global active_count
    active_count = max(1, min(5, int(count)))

    ui.clear()
    ui.title('Dynamische Summen-App (Elemente ersetzen)')

    count_select = ui.select('Wie viele Zahlen? (1-5)', options=[1, 2, 3, 4, 5], value=active_count)
    btn_rebuild = ui.button('Eingabefelder ersetzen')
    btn_calc = ui.button('Summe berechnen')

    number_widgets = []
    for index in range(active_count):
        number_widgets.append(ui.number(f'Zahl {index + 1}', 0))

    status = ui.output()
    status.write(f'{active_count} Eingabefelder aktiv.')

    def rebuild_ui():
        try:
            next_count = int(count_select.value)
        except Exception:
            next_count = active_count
        render_app(next_count)

    def calc_sum():
        total = 0.0
        for widget in number_widgets:
            total += float(widget.value)
        status.clear()
        status.write(f'Summe aus {len(number_widgets)} Feldern: {total}')

    btn_rebuild.on_click(rebuild_ui)
    btn_calc.on_click(calc_sum)


render_app(active_count)
PY;

$calcIndexHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Taschenrechner (Run-Logik)</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="calculator">
    <h2>Taschenrechner (Python-Run-Dispatch)</h2>

    <div class="input-group">
      <label>Erste Zahl:</label>
      <input type="number" data-element="a" step="any" placeholder="z.B. 10">
    </div>

    <div class="input-group">
      <label>Zweite Zahl:</label>
      <input type="number" data-element="b" step="any" placeholder="z.B. 5">
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

$calcStyleCss = <<<'CSS'
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
  width: min(520px, 92vw);
  background: #fff;
  border-radius: 14px;
  padding: 24px;
  box-shadow: 0 10px 24px rgba(0,0,0,.12);
}
.input-group { margin-bottom: 14px; }
.input-group label { display:block; margin-bottom: 6px; font-weight: 600; }
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
  margin-top: 16px;
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
  margin-top: 18px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 12px;
  min-height: 44px;
  font-weight: 700;
}
CSS;

$dynamicIndexHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dynamische idegui-UI</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="info">
    <h2>Dynamische UI</h2>
    <p>Die sichtbaren Eingabefelder werden komplett aus Python erzeugt.</p>
    <p>Klicke RUN, um die Oberfläche aufzubauen.</p>
  </div>
</body>
</html>
HTML;

$dynamicStyleCss = <<<'CSS'
body {
  margin: 0;
  padding: 20px;
  font-family: Segoe UI, sans-serif;
  background: #f8fafc;
  color: #111827;
}
.info {
  max-width: 760px;
  margin: 0 auto 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
}
.info h2 { margin: 0 0 8px; }
.info p { margin: 4px 0; }
CSS;

$taskConfigs = [
    [
        'title' => 'Taschenrechner (Run-Logik mit Trigger-Dispatch)',
        'description' => $calcRunDescription,
        'task_text' => 'Nutze das gleiche Rechner-Layout wie Task 170, aber löse die Operation über Python-Run-Dispatch mit ui.trigger.name und if/elif.',
        'code_template' => $calcRunCodeTemplate,
        'solution_code' => $calcRunCodeTemplate,
        'hint1' => 'Verwende ui._refresh_trigger() vor der Auswertung von ui.trigger.name.',
        'hint2' => 'Werte a und b sicher mit float(...) aus und fange ValueError ab.',
        'hint3' => 'Nutze if/elif für plus, minus, mal, geteilt und gib das Ergebnis mit ui.set("result", ...) aus.',
        'stoff' => 'Run-Dispatch: Trigger auslesen und die passende Operation per if/elif ausführen.',
        'files' => [
            'index.html' => $calcIndexHtml,
            'style.css' => $calcStyleCss,
            'idegui.py' => $calcRunCodeTemplate,
        ],
    ],
    [
        'title' => 'Dynamische UI in Python (einfach)',
        'description' => $dynamicSimpleDescription,
        'task_text' => 'Erzeuge Eingabefelder, Button und Ausgabe komplett zur Laufzeit mit idegui in Python.',
        'code_template' => $dynamicSimpleTemplate,
        'solution_code' => $dynamicSimpleTemplate,
        'hint1' => 'Starte mit ui.clear() und ui.title(...), damit die UI sauber aufgebaut wird.',
        'hint2' => 'Erzeuge Felder mit ui.number(...) und einen Button mit ui.button(...).',
        'hint3' => 'Verknüpfe die Aktion über button.on_click(callback).',
        'stoff' => 'Laufzeit-UI: Dynamische Elemente in Python erzeugen und per Callback verarbeiten.',
        'files' => [
            'index.html' => $dynamicIndexHtml,
            'style.css' => $dynamicStyleCss,
            'idegui.py' => $dynamicSimpleTemplate,
        ],
    ],
    [
        'title' => 'Dynamische Elemente ersetzen (Select 1-5)',
        'description' => $dynamicComplexDescription,
        'task_text' => 'Nutze eine Selectbox (1-5), ersetze alte Eingabefelder durch neue und berechne danach die Summe mit den neu erzeugten Feldern.',
        'code_template' => $dynamicComplexTemplate,
        'solution_code' => $dynamicComplexTemplate,
        'hint1' => 'Halte die Render-Logik in einer Funktion und rufe darin ui.clear() auf.',
        'hint2' => 'Lies den aktuellen Select-Wert, dann erzeugst du exakt so viele ui.number-Felder neu.',
        'hint3' => 'Die Berechnung muss über die aktuell erzeugte number_widgets-Liste laufen.',
        'stoff' => 'Dynamische Ersetzung: UI neu rendern, alte Controls verwerfen, mit neuen Werten rechnen.',
        'files' => [
            'index.html' => $dynamicIndexHtml,
            'style.css' => $dynamicStyleCss,
            'idegui.py' => $dynamicComplexTemplate,
        ],
    ],
];

$pdo->beginTransaction();

try {
    $anchorStmt = $pdo->prepare('SELECT id, position FROM tasks WHERE id = ? AND assignment_id = ?');
    $anchorStmt->execute([$anchorTaskId, $assignmentId]);
    $anchor = $anchorStmt->fetch();

    if (!$anchor) {
        throw new RuntimeException('Task 170 in Assignment #21 wurde nicht gefunden.');
    }

    $basePosition = (int)$anchor['position'];

    $existingIds = [];
    $findStmt = $pdo->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    foreach ($taskConfigs as $cfg) {
        $findStmt->execute([$assignmentId, $cfg['title']]);
        $row = $findStmt->fetch();
        if ($row) {
            $existingIds[] = (int)$row['id'];
        }
    }

    if (!empty($existingIds)) {
        $in = implode(',', array_fill(0, count($existingIds), '?'));
        $shiftSql = "UPDATE tasks SET position = position + " . count($taskConfigs) . " WHERE assignment_id = ? AND position > ? AND id NOT IN ($in)";
        $params = array_merge([$assignmentId, $basePosition], $existingIds);
    } else {
        $shiftSql = "UPDATE tasks SET position = position + " . count($taskConfigs) . " WHERE assignment_id = ? AND position > ?";
        $params = [$assignmentId, $basePosition];
    }

    $shiftStmt = $pdo->prepare($shiftSql);
    $shiftStmt->execute($params);

    $insertSql = 'INSERT INTO tasks (
        assignment_id, title, description, task_text, position,
        task_type, problem_type, folderstructure, allowDownload, allow_code_ui_web_edit,
        code_template, solution_code, hint1, hint2, hint3, stoff,
        max_attempts, iterations_count, show_solution, show_solution_code,
        created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        NOW(), NOW()
    )';

    $insertStmt = $pdo->prepare($insertSql);

    $updateSql = 'UPDATE tasks SET
        title = ?, description = ?, task_text = ?, position = ?,
        task_type = ?, problem_type = ?, folderstructure = ?, allowDownload = ?, allow_code_ui_web_edit = ?,
        code_template = ?, solution_code = ?, hint1 = ?, hint2 = ?, hint3 = ?, stoff = ?,
        max_attempts = ?, iterations_count = ?, show_solution = ?, show_solution_code = ?,
        updated_at = NOW()
        WHERE id = ? AND assignment_id = ?';

    $updateStmt = $pdo->prepare($updateSql);

    $taskResults = [];

    foreach ($taskConfigs as $index => $cfg) {
        $targetPosition = $basePosition + 1 + $index;

        $findStmt->execute([$assignmentId, $cfg['title']]);
        $existing = $findStmt->fetch();

        $commonParams = [
            $cfg['title'],
            $cfg['description'],
            $cfg['task_text'],
            $targetPosition,
            'code_ui',
            'code_completion',
            1,
            0,
            0,
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
        ];

        if ($existing) {
            $taskId = (int)$existing['id'];
            $updateStmt->execute(array_merge($commonParams, [$taskId, $assignmentId]));
            $mode = 'updated';
        } else {
            $insertStmt->execute(array_merge([
                $assignmentId,
            ], $commonParams));
            $taskId = (int)$pdo->lastInsertId();
            $mode = 'inserted';
        }

        $folder = __DIR__ . '/../storage/tasks/folders/task_' . $taskId;
        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            throw new RuntimeException('Ordner konnte nicht erstellt werden: ' . $folder);
        }

        foreach ($cfg['files'] as $fileName => $content) {
            $path = $folder . '/' . $fileName;
            file_put_contents($path, $content);
        }

        $taskResults[] = [
            'id' => $taskId,
            'title' => $cfg['title'],
            'position' => $targetPosition,
            'mode' => $mode,
        ];
    }

    $pdo->commit();

    echo "Assignment #21 erfolgreich aktualisiert.\n";
    foreach ($taskResults as $result) {
        echo sprintf(
            "- [%s] Task %d @ Position %d: %s\n",
            strtoupper($result['mode']),
            $result['id'],
            $result['position'],
            $result['title']
        );
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Fehler: " . $e->getMessage() . "\n");
    exit(1);
}
