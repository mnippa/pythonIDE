<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

$py = <<<'PY'
import idegui as ui

ergebnis_liste = []


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
    global ergebnis_liste
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

    ergebnis_liste.insert(0, eintrag)
    ui.set('verlauf', '\n'.join(ergebnis_liste))
PY;

$size = strlen($py);
$name = 'init.py';
$stmt = $conn->prepare('UPDATE project_files SET content=?, file_size=?, updated_at=NOW() WHERE project_id=48 AND folder_id=122 AND name=?');
$stmt->bind_param('sis', $py, $size, $name);
$stmt->execute();
echo "Updated: " . $stmt->affected_rows . " row(s)\n";
