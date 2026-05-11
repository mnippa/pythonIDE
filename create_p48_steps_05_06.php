<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

$projectId = 48;

function ensureFolder(mysqli $conn, int $projectId, string $name): int {
    $stmt = $conn->prepare('SELECT id FROM project_folders WHERE project_id=? AND parent_folder_id IS NULL AND name=? LIMIT 1');
    $stmt->bind_param('is', $projectId, $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }

    $insert = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name, created_at, updated_at) VALUES (?, NULL, ?, NOW(), NOW())');
    $insert->bind_param('is', $projectId, $name);
    $insert->execute();
    return (int)$conn->insert_id;
}

function upsertFile(mysqli $conn, int $projectId, int $folderId, string $name, string $content): void {
    $size = strlen($content);

    $check = $conn->prepare('SELECT id FROM project_files WHERE project_id=? AND folder_id=? AND name=? LIMIT 1');
    $check->bind_param('iis', $projectId, $folderId, $name);
    $check->execute();
    $res = $check->get_result();

    if ($res && $row = $res->fetch_assoc()) {
        $update = $conn->prepare('UPDATE project_files SET content=?, file_size=?, updated_at=NOW() WHERE id=?');
        $fileId = (int)$row['id'];
        $update->bind_param('sii', $content, $size, $fileId);
        $update->execute();
        echo "  updated $name (id=$fileId)\n";
        return;
    }

    $insert = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, file_size, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    $insert->bind_param('iissi', $projectId, $folderId, $name, $content, $size);
    $insert->execute();
    echo "  inserted $name (id=" . $conn->insert_id . ")\n";
}

$folder05 = ensureFolder($conn, $projectId, '05_operator_tasten');
$folder06 = ensureFolder($conn, $projectId, '06_taschenrechner_nur_tasten');

echo "Folder 05 id=$folder05\n";
echo "Folder 06 id=$folder06\n";

$index05 = <<<'HTML'
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Taschenrechner - Operator-Tasten</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="codeui-app">
    <h1>Taschenrechner</h1>
    <p class="subtitle">Schritt 05: Operatoren als Knoepfe (+, -, *)</p>

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
        <label for="operator">Operator (wird per Taste gesetzt)</label>
        <input type="text" id="operator" data-element="operator" value="+" readonly>
      </div>

      <div class="ops-row">
        <button class="op" data-function="waehle_operator" name="plus" value="+">+</button>
        <button class="op" data-function="waehle_operator" name="minus" value="-">-</button>
        <button class="op" data-function="waehle_operator" name="mal" value="*">*</button>
      </div>

      <button class="primary" data-function="berechnen" name="berechnen" value="run">Berechnen</button>

      <div class="result-section">
        <div class="result-row">
          <span class="result-label">Ergebnis:</span>
          <span class="result-value" id="ergebnis" data-element="ergebnis">-</span>
        </div>
        <div id="meldung" data-element="meldung"></div>
      </div>

      <div class="form-group" style="margin-top:18px;">
        <label for="verlauf">Verlauf (neueste zuerst)</label>
        <div id="verlauf" data-element="verlauf" class="history-box"></div>
      </div>
    </section>
  </main>
</body>
</html>
HTML;

$init05 = <<<'PY'
import idegui as ui

historie = []


def parse_float(text, fallback=0.0):
    try:
        return float(str(text).replace(',', '.').strip())
    except Exception:
        return fallback


def waehle_operator(trigger):
    op = str(getattr(trigger, 'value', '') or '').strip()
    if op not in ['+', '-', '*']:
        op = '+'
    ui.set('operator', op)
    ui.set('meldung', 'Operator gesetzt: ' + op)


def berechne(a, b, op):
    if op == '+':
        return a + b
    if op == '-':
        return a - b
    if op == '*':
        return a * b
    raise ValueError('Unbekannter Operator: ' + str(op))


def berechnen(trigger):
    global historie

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

    historie.insert(0, eintrag)
    ui.set('verlauf', '\n'.join(historie))
PY;

$readme05 = <<<'MD'
# 05 Operator-Tasten

Ziel:
- Operator wird nicht getippt, sondern per Taste gewaehlt.
- Nur +, -, * (geteilt folgt spaeter didaktisch).

Ablauf:
- Ein Klick auf + / - / * ruft `waehle_operator(trigger)` auf.
- Dort wird `trigger.value` gelesen und in `data-element="operator"` geschrieben.
- `berechnen(trigger)` verarbeitet dann den gesetzten Operator.
- Verlauf bleibt event-driven und wird oben erweitert (neueste zuerst).
MD;

$style05 = <<<'CSS'
.codeui-app {
  --primary: #0f766e;
  --primary-dark: #115e59;
  --accent: #f59e0b;
  --gray-50: #f8fafc;
  --gray-200: #e2e8f0;
  --gray-700: #334155;

  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background: linear-gradient(160deg, #0ea5e9 0%, #14b8a6 100%);
  border-radius: 12px;
  padding: 20px;
}

.codeui-app *,
.codeui-app *::before,
.codeui-app *::after { box-sizing: border-box; }

.codeui-app h1 {
  margin: 0;
  color: #fff;
  text-align: center;
}

.codeui-app .subtitle {
  margin: 8px 0 18px 0;
  color: #e6fffa;
  text-align: center;
  font-size: 13px;
}

.codeui-app .panel {
  background: #fff;
  border-radius: 12px;
  padding: 22px;
  max-width: 520px;
  margin: 0 auto;
  box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
}

.codeui-app .form-group { margin-bottom: 14px; }

.codeui-app label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--gray-700);
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.codeui-app input[type="text"] {
  width: 100%;
  padding: 11px 12px;
  border: 2px solid var(--gray-200);
  border-radius: 8px;
  font-size: 16px;
  background: var(--gray-50);
}

.codeui-app .ops-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin: 8px 0 12px 0;
}

.codeui-app button {
  border: none;
  border-radius: 8px;
  padding: 12px 10px;
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  cursor: pointer;
}

.codeui-app button.op {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.codeui-app button.primary {
  width: 100%;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
}

.codeui-app .result-section {
  margin-top: 16px;
  padding: 12px;
  background: var(--gray-50);
  border-radius: 8px;
  border-left: 4px solid var(--primary);
}

.codeui-app .result-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.codeui-app .result-label {
  font-size: 14px;
  font-weight: 700;
  color: var(--gray-700);
}

.codeui-app .result-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--primary-dark);
}

.codeui-app #meldung {
  margin-top: 8px;
  padding: 9px 10px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid var(--gray-200);
  color: var(--gray-700);
  min-height: 32px;
  white-space: pre-line;
}

.codeui-app .history-box {
  width: 100%;
  min-height: 120px;
  max-height: 180px;
  overflow-y: auto;
  padding: 10px 12px;
  border: 2px solid var(--gray-200);
  border-radius: 8px;
  font-size: 14px;
  font-family: 'Courier New', Courier, monospace;
  background: var(--gray-50);
  color: var(--gray-700);
  white-space: pre-wrap;
}
CSS;

$index06 = <<<'HTML'
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Demo - Taschenrechner nur mit Tasten</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="calc-app">
    <section class="calc-shell">
      <h1>Demo 06</h1>
      <p class="subtitle">Nachbau: Taschenrechner nur mit Tasten</p>

      <div class="display-wrap">
        <div class="expr" data-element="ausdruck">0</div>
        <div class="result" data-element="anzeige">0</div>
      </div>

      <div class="keys">
        <button class="k fn" data-function="taste" value="C">C</button>
        <button class="k op" data-function="taste" value="+">+</button>
        <button class="k op" data-function="taste" value="-">-</button>
        <button class="k op" data-function="taste" value="*">*</button>

        <button class="k num" data-function="taste" value="7">7</button>
        <button class="k num" data-function="taste" value="8">8</button>
        <button class="k num" data-function="taste" value="9">9</button>
        <button class="k eq tall" data-function="taste" value="=">=</button>

        <button class="k num" data-function="taste" value="4">4</button>
        <button class="k num" data-function="taste" value="5">5</button>
        <button class="k num" data-function="taste" value="6">6</button>

        <button class="k num" data-function="taste" value="1">1</button>
        <button class="k num" data-function="taste" value="2">2</button>
        <button class="k num" data-function="taste" value="3">3</button>

        <button class="k num wide" data-function="taste" value="0">0</button>
      </div>

      <div class="hint" data-element="meldung">Bereit.</div>
    </section>
  </main>
</body>
</html>
HTML;

$init06 = <<<'PY'
import idegui as ui

eingabe = ''
links = None
operator = None


def format_num(value):
    if int(value) == value:
        return str(int(value))
    return str(value)


def parse_int_text(text):
    text = str(text).strip()
    if text == '':
        return 0
    return int(text)


def rechne(a, b, op):
    if op == '+':
        return a + b
    if op == '-':
        return a - b
    if op == '*':
        return a * b
    raise ValueError('Unbekannter Operator: ' + str(op))


def refresh_view(message=''):
    global eingabe, links, operator

    if operator is None:
        if eingabe == '':
            expr = '0'
        else:
            expr = eingabe
    else:
        left_text = format_num(float(links)) if links is not None else '0'
        right_text = eingabe if eingabe != '' else ''
        expr = left_text + ' ' + operator + ' ' + right_text

    if eingabe != '':
        anzeige = eingabe
    elif links is not None:
        anzeige = format_num(float(links))
    else:
        anzeige = '0'

    ui.set('ausdruck', expr)
    ui.set('anzeige', anzeige)
    ui.set('meldung', message if message else 'Bereit.')


def set_operator(op):
    global eingabe, links, operator

    if eingabe != '':
        value = parse_int_text(eingabe)
        if links is None:
            links = value
        elif operator is not None:
            links = rechne(float(links), float(value), operator)
        eingabe = ''

    if links is None:
        links = 0

    operator = op
    refresh_view('Operator gesetzt: ' + op)


def calc_equals():
    global eingabe, links, operator

    if operator is None:
        refresh_view('Kein Operator gesetzt.')
        return

    rechts = parse_int_text(eingabe)
    links_wert = float(links) if links is not None else 0.0
    result = rechne(links_wert, float(rechts), operator)

    links = result
    eingabe = ''
    operator = None
    refresh_view('Ergebnis berechnet.')


def taste(trigger):
    global eingabe, links, operator

    value = str(getattr(trigger, 'value', '') or '').strip()

    if value == 'C':
        eingabe = ''
        links = None
        operator = None
        refresh_view('Zurueckgesetzt.')
        return

    if value in ['+', '-', '*']:
        set_operator(value)
        return

    if value == '=':
        try:
            calc_equals()
        except Exception as ex:
            refresh_view('Fehler: ' + str(ex))
        return

    if value.isdigit():
        if eingabe == '0':
            eingabe = value
        else:
            eingabe = eingabe + value
        refresh_view('Zahl erweitert.')
        return

    refresh_view('Unbekannte Taste: ' + value)
PY;

$readme06 = <<<'MD'
# 06 Demo - Taschenrechner nur mit Tasten

Ziel:
- Komplett ohne Texteingabe arbeiten.
- Alle Interaktionen laufen ueber Buttons mit `data-function="taste"`.

Didaktik:
- `eingabe` speichert die aktuell getippte Zahl als Text.
- `links` und `operator` halten den Zwischenzustand.
- Tasten:
  - `0-9`: Zahl aufbauen
  - `+ - *`: Operator setzen
  - `=`: Berechnen
  - `C`: Zuruecksetzen

Hinweis:
- Division ist bewusst noch nicht enthalten.
MD;

$style06 = <<<'CSS'
.calc-app {
  --bg1: #0f172a;
  --bg2: #1e293b;
  --shell: #0b1220;
  --screen: #020617;
  --num: #1f2937;
  --op: #0ea5e9;
  --eq: #22c55e;
  --fn: #ef4444;
  --txt: #e2e8f0;

  min-height: 100%;
  border-radius: 12px;
  padding: 20px;
  background: radial-gradient(circle at 20% 10%, #334155 0%, var(--bg1) 55%, #020617 100%);
  font-family: 'Trebuchet MS', 'Segoe UI', sans-serif;
  color: var(--txt);
}

.calc-shell {
  max-width: 380px;
  margin: 0 auto;
  border-radius: 18px;
  padding: 18px;
  background: linear-gradient(165deg, var(--bg2) 0%, var(--shell) 100%);
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
}

.calc-shell h1 {
  margin: 0;
  font-size: 22px;
  text-align: center;
}

.calc-shell .subtitle {
  margin: 6px 0 14px 0;
  font-size: 12px;
  text-align: center;
  color: #94a3b8;
}

.display-wrap {
  background: var(--screen);
  border-radius: 12px;
  padding: 10px 12px;
  margin-bottom: 12px;
  border: 1px solid #1e293b;
}

.expr {
  min-height: 22px;
  font-size: 13px;
  color: #94a3b8;
  text-align: right;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.result {
  min-height: 40px;
  font-size: 30px;
  font-weight: 700;
  line-height: 1.2;
  text-align: right;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.keys {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.k {
  border: none;
  border-radius: 10px;
  min-height: 48px;
  font-size: 18px;
  font-weight: 700;
  color: #fff;
  cursor: pointer;
  transition: transform 0.08s ease;
}

.k:active {
  transform: scale(0.96);
}

.k.num { background: var(--num); }
.k.op { background: var(--op); }
.k.eq { background: var(--eq); }
.k.fn { background: var(--fn); }

.k.wide {
  grid-column: span 3;
}

.k.tall {
  grid-row: span 2;
}

.hint {
  margin-top: 10px;
  min-height: 24px;
  font-size: 12px;
  color: #94a3b8;
  text-align: center;
}
CSS;

$files05 = [
    'index.html' => $index05,
    'init.py' => $init05,
    'README.md' => $readme05,
    'style.css' => $style05,
];

$files06 = [
    'index.html' => $index06,
    'init.py' => $init06,
    'README.md' => $readme06,
    'style.css' => $style06,
];

echo "Writing folder 05 files...\n";
foreach ($files05 as $name => $content) {
    upsertFile($conn, $projectId, $folder05, $name, $content);
}

echo "Writing folder 06 files...\n";
foreach ($files06 as $name => $content) {
    upsertFile($conn, $projectId, $folder06, $name, $content);
}

echo "Done.\n";
