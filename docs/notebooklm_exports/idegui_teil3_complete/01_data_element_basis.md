# IDEGUI Teil 3 - 01_data_element_basis

Projekt: 48 (IDEGUI Teil 3 - Taschenrechner systematisch)

## Inhalte

### README.md

```markdown
# 01 data-element Basis

Ziel:
- Mapping zwischen HTML und Python verstehen
- Trigger-Modus: `data-run="true"` startet den kompletten `init.py`-Run

Inhalt:
- Button hat `data-run="true"` + `name="berechnen"`
- Python liest `__trigger__`
- Bei Trigger `berechnen`: Werte lesen und Mapping anzeigen
- Noch keine echte Rechnung
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

trigger = ui.get('__trigger__', '')

if trigger == 'berechnen':
    a_text = ui.get('zahl_a', '0')
    b_text = ui.get('zahl_b', '0')
    op = ui.get('operator', '+')

    meldung = 'Gelesen von UI:\n'
    meldung = meldung + 'zahl_a = "' + str(a_text) + '"\n'
    meldung = meldung + 'zahl_b = "' + str(b_text) + '"\n'
    meldung = meldung + 'operator = "' + str(op) + '"\n'
    meldung = meldung + '__trigger__ = "' + str(trigger) + '"'

    ui.set('meldung', meldung)
    ui.set('ergebnis', 'Mapping OK')
else:
    ui.set('meldung', 'Bereit. Werte eingeben und auf Berechnen klicken.')
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

