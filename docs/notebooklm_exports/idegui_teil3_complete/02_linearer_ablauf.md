# IDEGUI Teil 3 - 02_linearer_ablauf

Projekt: 48 (IDEGUI Teil 3 - Taschenrechner systematisch)

## Inhalte

### README.md

```markdown
# 02 Linearer Ablauf

Ziel:
- Echte lineare Verarbeitung im Run-Modus

Trigger-Prinzip:
- Der Berechnen-Button nutzt `data-run="true"`
- Jeder Klick startet den kompletten `init.py`-Code neu
- Python liest `trigger = ui.get('__trigger__')`
- Bei `trigger == 'berechnen'`: `get -> parse -> rechnen -> set`

Abgrenzung zu Schritt 03:
- Hier: kompletter Neustart pro Klick
- Schritt 03: Event-Driven mit persistentem Zustand
```

### index.html

```html
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

    <button
      data-run="true"
      data-run-name="berechnen"
      name="berechnen"
      value="run"
    >Berechnen</button>

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
```

### init.py

```python
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


trigger = ui.get('__trigger__', '')

if trigger == 'berechnen':
    text_a = ui.get('zahl_a', '0')
    text_b = ui.get('zahl_b', '0')
    op = ui.get('operator', '+').strip()

    a = parse_float(text_a, 0.0)
    b = parse_float(text_b, 0.0)

    try:
        result = berechne(a, b, op)
        ui.set('ergebnis', str(result))
        ui.set('meldung', 'Linear: kompletter init.py-Run. Ergebnis: ' + str(a) + ' ' + op + ' ' + str(b) + ' = ' + str(result))
    except Exception as ex:
        ui.set('ergebnis', '-')
        ui.set('meldung', 'Fehler: ' + str(ex))
else:
    ui.set('meldung', 'Bereit. Klick auf Berechnen startet den kompletten init.py-Run.')
```

### style.css

```css
.codeui-app {
  --primary: #3b82f6;
  --primary-dark: #1e40af;
  --primary-light: #dbeafe;
  --success: #10b981;
  --danger: #ef4444;
  --gray-50: #f9fafb;
  --gray-100: #f3f4f6;
  --gray-200: #e5e7eb;
  --gray-700: #374151;
  --gray-900: #111827;

  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  padding: 20px;
}

.codeui-app *,
.codeui-app *::before,
.codeui-app *::after {
  box-sizing: border-box;
}

.codeui-app h1 {
  text-align: center;
  color: #ffffff;
  margin: 0 0 20px 0;
  font-size: 28px;
  font-weight: 700;
}

.codeui-app .panel {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
  padding: 24px;
  max-width: 480px;
  margin: 0 auto;
}

.codeui-app .form-group {
  margin-bottom: 16px;
}

.codeui-app label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: var(--gray-700);
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.codeui-app input[type="text"],
.codeui-app input[type="number"] {
  width: 100%;
  padding: 11px 12px;
  border: 2px solid var(--gray-200);
  border-radius: 8px;
  font-size: 16px;
  background: var(--gray-50);
  transition: all 0.2s ease;
}

.codeui-app input[type="text"]:focus,
.codeui-app input[type="number"]:focus {
  outline: none;
  border-color: var(--primary);
  background: #ffffff;
  box-shadow: 0 0 0 3px var(--primary-light);
}

.codeui-app button {
  width: 100%;
  padding: 12px 14px;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 700;
  color: #ffffff;
  cursor: pointer;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  box-shadow: 0 6px 18px rgba(59, 130, 246, 0.35);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.codeui-app button:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(59, 130, 246, 0.45);
}

.codeui-app .result-section {
  margin-top: 18px;
  padding: 14px;
  background: var(--gray-50);
  border-radius: 8px;
  border-left: 4px solid var(--primary);
}

.codeui-app .result-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
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

.codeui-app #ergebnis {
  display: inline-block;
  min-width: 90px;
  text-align: center;
  background: #ffffff;
  padding: 7px 10px;
  border-radius: 6px;
}

.codeui-app #meldung {
  margin-top: 10px;
  padding: 10px 12px;
  border-radius: 6px;
  background: #ffffff;
  border: 1px solid var(--gray-200);
  color: var(--gray-700);
  min-height: 34px;
  white-space: pre-line;
}
```

