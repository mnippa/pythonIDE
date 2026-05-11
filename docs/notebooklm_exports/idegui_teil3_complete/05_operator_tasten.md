# IDEGUI Teil 3 - 05_operator_tasten

Projekt: 48 (IDEGUI Teil 3 - Taschenrechner systematisch)

## Inhalte

### README.md

```markdown
# 05 Operator-Tasten

Ziel:
- Operator wird nicht getippt, sondern per Taste gewaehlt.
- Nur +, -, * (geteilt folgt spaeter didaktisch).

Ablauf:
- Ein Klick auf + / - / * ruft `waehle_operator(trigger)` auf.
- Dort wird `trigger.value` gelesen und in `data-element="operator"` geschrieben.
- `berechnen(trigger)` verarbeitet dann den gesetzten Operator.
- Verlauf bleibt event-driven und wird oben erweitert (neueste zuerst).
```

### index.html

```html
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
```

### init.py

```python
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
```

### style.css

```css
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
```

