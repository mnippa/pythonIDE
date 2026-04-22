# 45-Minuten Unterrichtseinheit: UI API (HTML <-> Python)

## Zielgruppe
- Einstieg bis mittleres Niveau (Python-Grundlagen vorhanden)
- Fokus auf praktische HTML-Python-Kommunikation mit idegui

## Lernziele
Nach 45 Minuten koennen Lernende:
1. HTML-Felder mit `data-element` an Python binden.
2. Werte mit `ui.get()` lesen und mit `ui.set()` schreiben.
3. Den Unterschied zwischen `data-run` und `data-function` anwenden.
4. Trigger-Kontext ueber `__trigger__` bzw. `trigger.name` und `trigger.value` nutzen.
5. Eine kleine interaktive App mit Validierung erstellen.

## Benoetigte Dateien
- `index.html`
- `init.py`
- optional `style.css`

Hinweis: Die Runtime/Bridge ist bereits in der Plattform vorhanden (`import idegui as ui`).

## Zeitplan (45 Minuten)
### Phase 1: Einstieg und Konzept (0-8 min)
- Kurz erklaeren: HTML = UI, Python = Logik, `data-element` = Bindung.
- Mini-Demo zeigen: Input lesen, Text ausgeben.

### Phase 2: Guided Coding Full Run (8-20 min)
- Trigger mit `data-run="true"`.
- Python liest Eingabe und schreibt Ergebnis.
- Fehlerfall bei ungueltiger Zahl.

### Phase 3: Event-Driven Modus (20-32 min)
- Trigger mit `data-function`.
- Zwei Buttons (plus/minus) rufen je eigene Python-Funktion auf.
- `trigger.value` als Kontextparameter einsetzen.

### Phase 4: Uebung + Musterloesung (32-43 min)
- Lernende bauen Mini-Rechner mit Reset.
- Lehrkraft prueft anhand Checkliste.

### Phase 5: Abschluss (43-45 min)
- Unterschiede Run vs Event-Driven wiederholen.
- Typische Fehler und Debug-Check nennen.

---

## Abschnitt A: Startcode (Lehrkraft zeigt)

### A1) HTML
```html
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>UI API Einstieg</title>
</head>
<body>
  <div id="idegui-root" data-idegui-root="true"></div>
  <div id="idegui-output" data-idegui-output="true"></div>

  <h2>Summe berechnen</h2>
  <input type="number" data-element="a" placeholder="A" />
  <input type="number" data-element="b" placeholder="B" />
  <button data-run="true" name="calc" value="sum">Berechnen</button>

  <p>Ergebnis: <span data-element="result">-</span></p>
  <p data-element="error"></p>
</body>
</html>
```

### A2) Python
```python
import idegui as ui

try:
    a = float(ui.get('a', '0'))
    b = float(ui.get('b', '0'))
    ui.set('result', str(a + b))
    ui.set('error', '')
except ValueError:
    ui.set('error', 'Bitte gueltige Zahlen eingeben')
```

Didaktischer Punkt:
- `data-element="a"` gehoert zu `ui.get('a')`.
- `data-element="result"` gehoert zu `ui.set('result', ...)`.

---

## Abschnitt B: Event-Driven Umstellung

### B1) HTML anpassen
```html
<button data-function="plus" name="plus" value="+">+</button>
<button data-function="minus" name="minus" value="-">-</button>
<button data-function="clear" name="clear" value="0">Reset</button>
```

### B2) Python anpassen
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
        ui.set('error', f'Trigger: {trigger.name} ({trigger.value})')
    except ValueError:
        ui.set('error', 'Bitte gueltige Zahlen eingeben')

def minus(trigger):
    try:
        a, b = _read_numbers()
        ui.set('result', str(a - b))
        ui.set('error', f'Trigger: {trigger.name} ({trigger.value})')
    except ValueError:
        ui.set('error', 'Bitte gueltige Zahlen eingeben')

def clear(trigger):
    ui.set('a', '')
    ui.set('b', '')
    ui.set('result', '-')
    ui.set('error', '')

ui.set('result', '-')
ui.set('error', '')
```

Didaktischer Punkt:
- Event-Modus ruft gezielt Funktionen auf.
- `trigger.name` und `trigger.value` machen Buttons unterscheidbar.

---

## Uebungen

### Uebung 1 (leicht, 5 min)
Aufgabe:
- Ergaenze einen dritten Button `*` fuer Multiplikation.
- Schreibe die Python-Funktion `mal(trigger)`.

Erwartung:
- Klick auf `*` zeigt Produkt im Ergebnisfeld.

### Uebung 2 (mittel, 6 min)
Aufgabe:
- Fuege ein Eingabefeld `tax` (Mehrwertsteuer in Prozent) hinzu.
- Zeige Netto, Steuerbetrag und Brutto in drei Ausgabefeldern.

Erwartung:
- `ui.get()` fuer Eingaben, `ui.set()` fuer Ausgaben.
- Valider Fehlertext bei ungueltiger Eingabe.

### Uebung 3 (mittel, 5 min)
Aufgabe:
- Nutze `trigger.value`, um eine einzige Funktion `calc(trigger)` fuer +, -, *, / zu bauen.

Erwartung:
- HTML-Buttons haben alle `data-function="calc"` und unterschiedliche `value`.
- Python entscheidet per `if trigger.value == ...`.

---

## Musterloesung fuer Uebung 3

### HTML
```html
<input type="number" data-element="a" placeholder="A" />
<input type="number" data-element="b" placeholder="B" />

<button data-function="calc" name="calc" value="+">+</button>
<button data-function="calc" name="calc" value="-">-</button>
<button data-function="calc" name="calc" value="*">*</button>
<button data-function="calc" name="calc" value="/">/</button>

<p>Ergebnis: <span data-element="result">-</span></p>
<p data-element="error"></p>
```

### Python
```python
import idegui as ui

def calc(trigger):
    try:
        a = float(ui.get('a', '0'))
        b = float(ui.get('b', '0'))

        if trigger.value == '+':
            result = a + b
        elif trigger.value == '-':
            result = a - b
        elif trigger.value == '*':
            result = a * b
        elif trigger.value == '/':
            if b == 0:
                ui.set('error', 'Division durch 0 ist nicht erlaubt')
                return
            result = a / b
        else:
            ui.set('error', f'Unbekannter Operator: {trigger.value}')
            return

        ui.set('result', str(result))
        ui.set('error', '')
    except ValueError:
        ui.set('error', 'Bitte gueltige Zahlen eingeben')

ui.set('result', '-')
ui.set('error', '')
```

---

## Bewertungs-Checkliste (schnell)
1. Werden alle benoetigten `data-element`-Keys korrekt genutzt?
2. Passen alle `ui.get()`/`ui.set()`-Namen exakt zu HTML?
3. Ist bei Event-Modus mindestens eine Funktion mit `trigger` korrekt implementiert?
4. Gibt es Fehlerbehandlung (`ValueError`, Division durch 0)?
5. Ist die Initialisierung am Ende gesetzt (`ui.set(...)`)?

## Haeufige Fehler
1. Tippfehler im Key-Namen (`result` vs `ergebnis`).
2. `data-function` im HTML, aber Funktionsname in Python abweichend.
3. Zahleneingaben nicht mit `float(...)` umgewandelt.
4. Fehlende Rueckgabe/Abbruch bei Fehlerfaellen.

## Transferidee (Hausaufgabe)
Baue einen BMI-Rechner mit:
- zwei Inputs (`weight`, `height`)
- einem Button `data-function="calc_bmi"`
- Ausgaben fuer BMI-Wert und Kategorie (Untergewicht, Normalgewicht, Uebergewicht).
