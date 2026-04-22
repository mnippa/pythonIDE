# UI API Tutorial: HTML mit Python (idegui)

## Ziel
Dieses Dokument erklaert die eigene UI-API so, dass daraus direkt ein Tutorial gebaut werden kann.

Die API verbindet:
- HTML-Elemente (mit `data-*` Attributen)
- Python-Code in `init.py` (Pyodide im Browser)
- Trigger fuer Run- oder Funktionsmodus

## 1) Grundidee in einem Satz
HTML definiert die UI, Python liest/schreibt ueber `ui.get()`/`ui.set()`, und Buttons/Forms loesen Python-Code ueber `data-run` oder `data-function` aus.

## 2) Minimale Struktur
### HTML (index.html)
```html
<div id="idegui-root" data-idegui-root="true"></div>
<div id="idegui-output" data-idegui-output="true"></div>

<input type="text" data-element="name" placeholder="Name">
<button data-run="true" name="gruessen" value="run">RUN</button>
<p data-element="result">-</p>
```

### Python (init.py)
```python
import idegui as ui

name = ui.get('name')
if not name:
    name = 'Welt'

ui.set('result', f'Hallo {name}!')
```

## 3) Syntax-Herleitung (systematisch)
Die komplette API-Syntax laesst sich aus 3 Regeln herleiten.

### Regel A: Datenbindung
- HTML-Seite markiert Felder mit `data-element="<key>"`
- Python liest: `ui.get('<key>')`
- Python schreibt: `ui.set('<key>', value)`

Merksatz: Gleiches `<key>` in HTML und Python = verbunden.

### Regel B: Trigger fuer Ausfuehrung
Es gibt zwei Modi:

1. Full Run (kompletter Python-Run)
- HTML: `data-run="true"`
- Optional: `name` und `value` fuer Trigger-Kontext
- Python kann Trigger ueber `ui.get('__trigger__')` auslesen

2. Event-Driven (direkter Funktionsaufruf)
- HTML: `data-function="funktionsname"`
- Python-Funktion: `def funktionsname(trigger): ...`
- `trigger.name` und `trigger.value` enthalten Kontext

### Regel C: Trigger-Kontext
Der Runtime-Kontext wird aus dem Trigger-Element gesetzt:
- Trigger-Name: bevorzugt `name`, sonst Fallback auf `id`, `data-run-name`, `data-function`
- Trigger-Value: bevorzugt `value`, sonst Fallback auf `data-run-value`

Zusatzfelder (intern):
- `__trigger__`
- `__trigger_value__`

## 4) API-Referenz (praxisnah)
### `ui.get(key, default='')`
Liest Wert von `data-element="key"`.

- `input/textarea/select`: `.value`
- andere Elemente: `.textContent`

```python
a = float(ui.get('a', '0'))
```

### `ui.set(key, value)`
Schreibt in `data-element="key"`.

- `input/textarea/select`: setzt `.value`
- andere Elemente: setzt `.textContent`

```python
ui.set('result', '42')
```

### Trigger-Objekt im Event-Modus
```python
def add(trigger):
    # trigger.name, trigger.value
    pass
```

## 5) Modus 1: Full Run (data-run)
Geeignet fuer einfache Aufgaben, linearen Ablauf, wenige Interaktionen.

### HTML
```html
<input type="number" data-element="a" placeholder="A">
<input type="number" data-element="b" placeholder="B">
<button data-run="true" name="calc" value="sum">Berechnen</button>
<p data-element="result">-</p>
```

### Python
```python
import idegui as ui

try:
    a = float(ui.get('a', '0'))
    b = float(ui.get('b', '0'))
    ui.set('result', str(a + b))
except ValueError:
    ui.set('result', 'Bitte gueltige Zahlen eingeben')
```

## 6) Modus 2: Event-Driven (data-function)
Geeignet fuer interaktive Apps, mehrere Buttons, persistenten Zustand.

### HTML
```html
<input type="number" data-element="a" placeholder="A">
<input type="number" data-element="b" placeholder="B">
<button data-function="plus" name="plus" value="+">+</button>
<button data-function="minus" name="minus" value="-">-</button>
<p data-element="result">-</p>
<p data-element="error"></p>
```

### Python
```python
import idegui as ui

def _read_numbers():
    a = float(ui.get('a', '0'))
    b = float(ui.get('b', '0'))
    return a, b

def plus(trigger):
    try:
        a, b = _read_numbers()
        ui.set('result', str(a + b))
        ui.set('error', '')
    except ValueError:
        ui.set('error', 'Bitte gueltige Zahlen eingeben')

def minus(trigger):
    try:
        a, b = _read_numbers()
        ui.set('result', str(a - b))
        ui.set('error', '')
    except ValueError:
        ui.set('error', 'Bitte gueltige Zahlen eingeben')

ui.set('result', '0')
ui.set('error', '')
```

## 7) Was ist der aktuelle Standard?
Beides wird unterstuetzt, aber fuer neue Inhalte gilt:
- Full Run: `data-run="true"`
- Event: `data-function="..."`

Legacy bleibt kompatibel (z. B. `data-run-python`, `data-run-name`, `data-run-value`), sollte aber fuer neue Inhalte nicht mehr bevorzugt werden.

## 8) Typische Fehler und schnelle Checks
1. `ui.get()` gibt leer zurueck
- Pruefen: existiert das passende `data-element` wirklich?

2. Button klickt, aber nichts passiert
- Full Run: hat das Element `data-run="true"`?
- Event: hat das Element `data-function="funktionsname"` und existiert diese Funktion in Python?

3. Falscher Trigger im Python-Code
- Event-Modus: `trigger.name` / `trigger.value` pruefen
- Run-Modus: `ui.get('__trigger__')` pruefen

4. Werte verschwinden nach Klick
- Event-Modus nutzen, wenn Zustand zwischen Klicks erhalten bleiben soll.

## 8b) Redraw im normalen Modus (ohne Event-Handler)
Fuer Demonstrationen mit schrittweiser Ausgabe stehen im normalen Run-Modus vier Helper bereit:

- `outputClear()` leert den Output-Bereich.
- `outputFlush()` erzwingt das Schreiben des aktuellen Output-Zustands.
- `clear_output()` (Alias) leert den Output-Bereich.
- `redraw(text)` ersetzt den Output-Bereich komplett mit `text`.

Damit kannst du sequentiell (ohne async/await) arbeiten.

```python
import time

board = [
    list("....."),
    list("..@.."),
    list("....."),
]

def board_text():
    return "\n".join("".join(row) for row in board)

for step in range(5):
    # Beispiel: Marker nach rechts bewegen
    x = 2 + step
    for r in range(3):
        for c in range(5):
            board[r][c] = '.'
    board[1][min(x, 4)] = '@'

    redraw(board_text())
    outputFlush()
    time.sleep(0.35)
```

Hinweis:
- Fuer Produktion/Interaktion bleibt `data-function` (Event-Driven) der bessere Weg.
- Fuer lineare Vorfuehrungen ist dieses Muster sehr praktisch.

## 9) Tutorial-Baukasten (didaktische Reihenfolge)
1. Hello-UI: 1 Input, 1 Output, 1 `ui.set`
2. Run-Modus mit `data-run`
3. Event-Modus mit `data-function`
4. Trigger-Kontext (`trigger.value`) nutzen
5. Zustand (globale Dict-Variable) einfuehren
6. Validierung und Fehlermeldungen

## 10) Copy-Paste Startvorlage
### index.html
```html
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>UI API Starter</title>
</head>
<body>
  <div id="idegui-root" data-idegui-root="true"></div>
  <div id="idegui-output" data-idegui-output="true"></div>

  <h2>Mini-Rechner</h2>
  <input type="number" data-element="a" placeholder="A" />
  <input type="number" data-element="b" placeholder="B" />
  <button data-function="add" name="add" value="add">Addieren</button>
  <button data-function="clear" name="clear" value="clear">Reset</button>

  <p>Ergebnis: <span data-element="result">0</span></p>
  <p data-element="error"></p>
</body>
</html>
```

### init.py
```python
import idegui as ui

def add(trigger):
    try:
        a = float(ui.get('a', '0'))
        b = float(ui.get('b', '0'))
        ui.set('result', str(a + b))
        ui.set('error', '')
    except ValueError:
        ui.set('error', 'Bitte gueltige Zahlen eingeben')

def clear(trigger):
    ui.set('a', '')
    ui.set('b', '')
    ui.set('result', '0')
    ui.set('error', '')

ui.set('result', '0')
ui.set('error', '')
```

---
Wenn du aus diesem Dokument ein Lern-Tutorial erzeugen willst, kannst du pro Kapitel jeweils ein Lernziel, eine Mini-Aufgabe und eine Loesungsversion ableiten.
