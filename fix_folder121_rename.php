<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

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
    </section>
  </main>
</body>
</html>
HTML;

$py = <<<'PY'
import idegui as ui


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
    text_a = ui.get('zahl_a', '0')
    text_b = ui.get('zahl_b', '0')
    op = ui.get('operator', '+').strip()

    a = parse_float(text_a, 0.0)
    b = parse_float(text_b, 0.0)

    try:
        result = berechne(a, b, op)
        ui.set('ergebnis', str(result))
        ui.set('meldung', 'Event-driven: Ergebnis berechnet.')
    except Exception as ex:
        ui.set('ergebnis', '-')
        ui.set('meldung', 'Fehler: ' + str(ex))
PY;

$readme = <<<'MD'
# 03 Event Driven

Ziel:
- Event-Driven-Modus ohne persistente Zustandslogik

Trigger-Prinzip:
- Button nutzt `data-function="berechnen"`
- IDEGUI ruft gezielt `berechnen(trigger)` auf
- Nur die Funktion wird ausgefuehrt, kein kompletter `init.py`-Neustart

Hinweis:
- In diesem Schritt kein APP_STATE, kein persistenter Speicher
- Fokus nur auf dem Event-Aufruf und der direkten Ergebnisanzeige
MD;

$updates = [
    'index.html' => $html,
    'init.py'    => $py,
    'README.md'  => $readme,
];

foreach ($updates as $name => $content) {
    $size = strlen($content);
    $stmt = $conn->prepare('UPDATE project_files SET content=?, file_size=?, updated_at=NOW() WHERE project_id=48 AND folder_id=121 AND name=?');
    $stmt->bind_param('sis', $content, $size, $name);
    $stmt->execute();
    echo $name . ': ' . $stmt->affected_rows . " row(s) updated\n";
}
echo "Done.\n";
