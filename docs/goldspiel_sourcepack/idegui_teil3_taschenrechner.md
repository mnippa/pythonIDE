# Teil 3 - IDEGUI systematisch: Taschenrechner

Ziel dieses Teils:
- IDEGUI an einem sehr einfachen Beispiel verstehen.
- Fast kein HTML-Fokus.
- Fokus auf das Prinzip: Python liest/schreibt UI-Werte ueber `data-element`.
- Erst normaler Programmablauf (linear), dann event-driven.

---

## 1) Kernprinzip in einem Satz

Du gibst HTML-Elementen ein `data-element`.
Python greift dann mit diesem Namen darauf zu:

- lesen: `ui.get('name')`
- setzen: `ui.set('name', 'wert')`

Beispiel:
- HTML: `<input data-element="zahl_a" ...>`
- Python: `a = ui.get('zahl_a', '0')`

---

## 2) Minimales HTML (nur zum Copy/Paste)

Dieses HTML ist die gemeinsame Basis.
Der Unterschied liegt im Button-Attribut:
- Linear/Run-Mode: `data-run="true"` + `name="berechnen"`
- Event-Driven: `data-function="berechnen_click"`

```html
<!doctype html>
<html>
<body>
  <h1>Taschenrechner</h1>

  <label>Zahl A</label>
  <input data-element="zahl_a" value="0">

  <label>Zahl B</label>
  <input data-element="zahl_b" value="0">

  <label>Operator (+, -, *, /)</label>
  <input data-element="operator" value="+">

    <button data-run="true" name="berechnen" value="run">Berechnen</button>

  <p>Ergebnis: <span data-element="ergebnis">-</span></p>
  <p data-element="meldung"></p>
</body>
</html>
```

Merke:
- Entscheidend sind die `data-element` Namen.
- Das Design/Styling ist hier absichtlich egal.

---

## 3) Variante A: Basis-Konzept (Mapping erkunden)

Ziel: Das `data-element`-Konzept demonstrieren.

### 01_data_element_basis/init.py

Der Button triggert einen kompletten Run (kein `ui.on`).

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

Wichtig:
- Jeder Button-Klick startet den kompletten `init.py`-Run neu.
- Der Trigger kommt ueber `trigger = ui.get('__trigger__')`.

---

## 4) Variante B: Linear (Button triggert Berechnung)

Ein "linearer Durchlauf" bedeutet:
- Button mit `data-run="true"` wird geklickt
- `init.py` laeuft komplett neu durch
- `trigger = ui.get('__trigger__')` entscheidet, was passiert
- Bei `trigger == 'berechnen'`: `get -> parse -> rechnen -> set`

### 02_linearer_ablauf/init.py

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

Wichtig:
- Jeder Button-Klick startet **den gesamten init.py-Code neu**.
- `ui.get(...)` liest immer die **aktuellen Werte** aus der UI.
- `ui.set(...)` schreibt das Ergebnis zurück.

---

## 5) Variante C: Event-driven (persistent)

Event-driven bedeutet:
- **Funktionen bleiben im Speicher** und behalten ihren Zustand.
- Jedes Event (z.B. Button-Klick) ruft eine Handler-Funktion auf.
- Der Handler kann auf globale Variablen zugreifen (persistent).
- Das Programm läuft **nicht neu von oben**, sondern wechselt in Event-Handling.

### 03_event_driven/init.py

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


def berechnen_click(trigger):
    text_a = ui.get('zahl_a', '0')
    text_b = ui.get('zahl_b', '0')
    op = ui.get('operator', '+').strip()

    a = parse_float(text_a, 0.0)
    b = parse_float(text_b, 0.0)

    try:
        result = berechne(a, b, op)
        ui.set('ergebnis', str(result))
        ui.set('meldung', 'Event verarbeitet (Button-Klick).')
    except Exception as ex:
        ui.set('ergebnis', '-')
        ui.set('meldung', 'Fehler: ' + str(ex))


# Initialzustand
ui.set('meldung', 'Event-driven bereit. data-function ruft berechnen_click(trigger) auf.')
```

Wichtig:
- HTML bindet ueber `data-function="berechnen_click"` direkt auf die Python-Funktion.
- Bei jedem Event wird nur der Handler aufgerufen.
- Globalvariablen koennen zwischen Events bestehen bleiben.

---

## 6) Direktvergleich: alle drei Varianten

| Aspekt | 01 Basis | 02 Linear | 03 Event-Driven |
|--------|----------|-----------|-----------------|
| **Button-Klick triggert** | Run-Trigger | Run-Trigger | Function-Trigger |
| **Code läuft** | Kompletter `init.py`-Run | Kompletter `init.py`-Run | Nur Handler-Funktion |
| **Globalvariablen** | Keine | Werden nicht genutzt | Können persistent sein |
| **Fokus** | Verstehen des Mappings | `get->parse->rechnen->set` | Event-Programmierung |
| **Komplexität** | Einfach | Mittel | Fortgeschritten |

---

## 7) Typische Fehlerbilder

- Vertippter Name im `data-element`
  - HTML: `data-element="ergebniss"`
  - Python: `ui.set('ergebnis', '...')`
  - Folge: keine sichtbare Ausgabe am erwarteten Ort

- Zahlen nicht geparst
  - `ui.get(...)` liefert String
  - Ohne `float(...)` oder `int(...)` kommt es zu falschem Verhalten

- Division durch 0 nicht abgefangen
  - Mit `try/except` sauber in `meldung` anzeigen

---

## 8) Mini-Cheat-Sheet

- Wert lesen:
  - `text = ui.get('zahl_a', '0')`

- Wert setzen:
  - `ui.set('ergebnis', '42')`

- Run-Trigger lesen:
    - `trigger = ui.get('__trigger__', '')`

- Event-Trigger setzen:
    - `<button data-function="berechnen_click">Berechnen</button>`

- Grundmuster Handler:
  - lesen -> parsen -> verarbeiten -> set/feedback

Damit hast du die zentrale IDEGUI-Idee sauber isoliert: Python steuert die UI ueber stabile `data-element`-Namen.
