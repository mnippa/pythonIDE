<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

// Check existing folders
$res = $conn->query("SELECT id, name FROM project_folders WHERE project_id=48 AND parent_folder_id IS NULL ORDER BY id");
echo "Existing folders:\n";
while ($row = $res->fetch_assoc()) {
    echo "  id={$row['id']} name={$row['name']}\n";
}

// Create folder 04
$folderName = '04_globale_variable';
$stmt = $conn->prepare("INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (48, NULL, ?)");
$stmt->bind_param('s', $folderName);
$stmt->execute();
$folderId = $conn->insert_id;
echo "Created folder id=$folderId name=$folderName\n";

// --- index.html ---
$html = <<<'HTML'
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Taschenrechner</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="codeui-app">
    <h1>Taschenrechner</h1>

    <section class="panel">
      <div class="form-group">
        <label for="zahl_a">Zahl A</label>
        <input type="text" id="zahl_a" data-element="zahl_a" value="0">
      </div>

      <div class="form-group">
        <label for="zahl_b">Zahl B</label>
        <input type="text" id="zahl_b" data-element="zahl_b" value="0">
      </div>

      <div class="form-group">
        <label for="operator">Operator (+, -, *, /)</label>
        <input type="text" id="operator" data-element="operator" value="+">
      </div>

      <button data-function="berechnen" name="berechnen" value="run">Berechnen</button>

      <div class="result-section">
        <div class="result-row">
          <span class="result-label">Ergebnis:</span>
          <span class="result-value" id="ergebnis" data-element="ergebnis">-</span>
        </div>
        <div id="meldung" data-element="meldung"></div>
      </div>

      <div class="form-group" style="margin-top:18px;">
        <label for="verlauf">Verlauf (neueste zuerst)</label>
        <textarea id="verlauf" data-element="verlauf" rows="6" readonly></textarea>
      </div>
    </section>
  </main>
</body>
</html>
HTML;

// --- init.py ---
$py = <<<'PY'
import idegui as ui

verlauf = []


def parse_float(text, fallback=0.0):
    try:
        return float(str(text).replace(',', '.').strip())
    except Exception:
        return fallback


def berechne(a, b, op):
    if op == '+':
        return a + b
    if op == '-':
        return a - b
    if op == '*':
        return a * b
    if op == '/':
        if b == 0:
            raise ValueError('Division durch 0 ist nicht erlaubt.')
        return a / b
    raise ValueError('Unbekannter Operator: ' + str(op))


def berechnen(trigger):
    global verlauf
    text_a = ui.get('zahl_a', '0')
    text_b = ui.get('zahl_b', '0')
    op = ui.get('operator', '+').strip()

    a = parse_float(text_a, 0.0)
    b = parse_float(text_b, 0.0)

    try:
        result = berechne(a, b, op)
        eintrag = f"{text_a} {op} {text_b} = {result}"
        ui.set('ergebnis', str(result))
        ui.set('meldung', 'Ergebnis berechnet.')
    except Exception as ex:
        eintrag = f"{text_a} {op} {text_b} = Fehler: {ex}"
        ui.set('ergebnis', '-')
        ui.set('meldung', 'Fehler: ' + str(ex))

    verlauf.insert(0, eintrag)
    ui.set('verlauf', '\n'.join(verlauf))
PY;

// --- README.md ---
$readme = <<<'MD'
# 04 Globale Variable

Ziel:
- Ergebnisse in einer globalen Liste sammeln und im Verlauf anzeigen

Konzept:
- `verlauf = []` wird flach im Modulcode definiert (keine Klasse, kein Dict)
- Beim ersten Aufruf ist die Liste leer
- Bei jedem Button-Klick fuegt `berechnen(trigger)` den neuen Eintrag vorne ein
- `ui.set('verlauf', ...)` aktualisiert die Textarea mit allen Eintraegen (neueste zuerst)
- `global verlauf` in der Funktion erlaubt die Zuweisung an die Modullevel-Variable
MD;

// --- style.css (copy from 03) ---
$res2 = $conn->query("SELECT content FROM project_files WHERE project_id=48 AND folder_id=121 AND name='style.css'");
$cssRow = $res2->fetch_assoc();
$css = $cssRow ? $cssRow['content'] : '';

// Append textarea style
$css .= <<<'ADDCSS'


.codeui-app textarea {
  width: 100%;
  padding: 10px 12px;
  border: 2px solid var(--gray-200);
  border-radius: 8px;
  font-size: 14px;
  font-family: 'Courier New', Courier, monospace;
  background: var(--gray-50);
  color: var(--gray-700);
  resize: vertical;
}
ADDCSS;

$files = [
    'index.html' => $html,
    'init.py'    => $py,
    'README.md'  => $readme,
    'style.css'  => $css,
];

$stmt2 = $conn->prepare("INSERT INTO project_files (project_id, folder_id, name, content, file_size, created_at, updated_at) VALUES (48, ?, ?, ?, ?, NOW(), NOW())");
foreach ($files as $name => $content) {
    $size = strlen($content);
    $stmt2->bind_param('issi', $folderId, $name, $content, $size);
    $stmt2->execute();
    echo "  inserted $name (" . $conn->insert_id . ")\n";
}

echo "Done.\n";
