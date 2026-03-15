# Phase 1 – Copy/Paste Vorlagen für GUI-Anlage

Ziel: Diese Inhalte direkt beim Erstellen der Tasks in der Admin-GUI einfügen.

- Reihenfolge: D1.1 → D1.2 → D1.3 → D1.4 → D1.5 → D2.1
- Assignment-Name: `Test-Assignment März 2026`

---

## D1.1 – Code-Task: Test: Addition

**Type:** Code  
**Title:** Test: Addition

**Beschreibung (copy):**
```text
Schreibe ein Python-Programm, das die beiden vorgegebenen Zahlen x und y addiert.
Speichere das Ergebnis in der Variable result.
```

**Code Template (copy):**
```python
x = 5
y = 3

# Berechne hier die Summe und speichere sie in result
result =
```

**Solution Code (copy):**
```python
result = x + y
```

**Expected Output (copy):**
```text
8
```

**Optional Hint 1:**
```text
Nutze den + Operator.
```

---

## D1.2 – Code-Task mit Hints/Limits: Test: Multiplikation

**Type:** Code  
**Title:** Test: Multiplikation

**Beschreibung (copy):**
```text
Multipliziere die beiden vorgegebenen Zahlen a und b.
Speichere das Ergebnis in der Variable result.
```

**Code Template (copy):**
```python
a = 4
b = 7

# Berechne hier das Produkt und speichere es in result
result =
```

**Solution Code (copy):**
```python
result = a * b
```

**Expected Output (copy):**
```text
28
```

**Hint 1 (copy):**
```text
Nutze den * Operator.
```

**Hint 2 (copy):**
```text
Die gesuchte Rechnung ist: a * b
```

**Hint 3 (copy):**
```text
Speichere das Ergebnis so: result = a * b
```

**Max Attempts:** `3`  
**Show Solution:** `Ja`

---

## D1.3 – Projekt (Folder Structure): Test: Mini-Taschenrechner

**Type:** Project (Folder Structure)  
**Title:** Test: Mini-Taschenrechner

**Beschreibung (copy):**
```text
Baue einen Mini-Taschenrechner mit zwei Eingabefeldern und einem Addieren-Button.
Beim Klick auf den Button soll die Summe in einem Ausgabefeld angezeigt werden.
```

### Datei 1: init.py

**Template / Starter Code (copy):**
```python
import idegui as ui

def addieren(trigger):
    zahl1 = float(ui.get('input1'))
    zahl2 = float(ui.get('input2'))
    ergebnis = zahl1 + zahl2
    ui.set('output', str(ergebnis))

ui.set('output', '0')
```

**Solution Code (copy):**
```python
import idegui as ui

def addieren(trigger):
    zahl1 = float(ui.get('input1'))
    zahl2 = float(ui.get('input2'))
    ergebnis = zahl1 + zahl2
    ui.set('output', str(ergebnis))

ui.set('output', '0')
```

### Datei 2: index.html

**Template / Starter Code (copy):**
```html
<!DOCTYPE html>
<html>
  <body>
    <h3>Mini-Taschenrechner</h3>

    <input data-element="input1" type="number" placeholder="Zahl 1">
    <input data-element="input2" type="number" placeholder="Zahl 2">

    <button data-function="addieren">Addieren</button>

    <p>Ergebnis: <span data-element="output">0</span></p>
  </body>
</html>
```

**Solution Code (copy):**
```html
<!DOCTYPE html>
<html>
  <body>
    <h3>Mini-Taschenrechner</h3>

    <input data-element="input1" type="number" placeholder="Zahl 1">
    <input data-element="input2" type="number" placeholder="Zahl 2">

    <button data-function="addieren">Addieren</button>

    <p>Ergebnis: <span data-element="output">0</span></p>
  </body>
</html>
```

### Datei 3: style.css

**Template / Starter Code (copy):**
```css
body {
  font-family: Arial, sans-serif;
  padding: 12px;
}

input {
  margin-right: 8px;
}

button {
  margin-left: 4px;
}
```

**Solution Code (copy):**
```css
body {
  font-family: Arial, sans-serif;
  padding: 12px;
}

input {
  margin-right: 8px;
}

button {
  margin-left: 4px;
}
```

---

## D1.4 – Intelligent Test: Test: Intelligent Multiply

**Type:** Code (Intelligent Test aktiv)  
**Title:** Test: Intelligent Multiply

**Beschreibung (copy):**
```text
Multipliziere die zufällig vorgegebenen Zahlen x und y.
Speichere das Ergebnis in der Variable result.
Bei jedem Neuladen werden neue Werte für x und y erzeugt.
```

**Code Template (copy):**
```python
# x und y werden automatisch vorgegeben
# Beispiel: x = 3, y = 9

result =
```

**Solution Code (copy):**
```python
result = x * y
```

**Randomizer Code (copy):**
```python
import random
x = random.randint(1, 10)
y = random.randint(1, 10)
```

**Optional Hint 1:**
```text
Verwende Multiplikation: x * y
```

**Optional Hint 2:**
```text
Speichere das Ergebnis in der Variable result.
```

---

## D1.5 – Quiz-Task: Test: Python Basics Quiz

**Type:** Quiz  
**Title:** Test: Python Basics Quiz

**Frage (copy):**
```text
Was gibt print(2+2) aus?
```

**Antworten (copy):**
- A: `4` ✅ korrekt
- B: `22`
- C: `Fehler`
- D: `2+2`

**Erklärung/Feedback (optional):**
```text
print(2+2) berechnet zuerst die Addition und gibt danach das Ergebnis 4 aus.
```

---

## D2.1 – Assignment anlegen und befüllen

**Assignment Title (copy):**
```text
Test-Assignment März 2026
```

**Assignment Kurzbeschreibung (copy):**
```text
Ein gemischtes Test-Assignment mit Code-, Projekt-, Intelligent- und Quiz-Aufgaben.
Ziel ist die inhaltliche Validierung der Lern-Workflows vor dem Beta-Launch.
```

**Tasks in dieser Reihenfolge hinzufügen:**
1. `Test: Addition`
2. `Test: Multiplikation`
3. `Test: Mini-Taschenrechner`
4. `Test: Intelligent Multiply`
5. `Test: Python Basics Quiz`

---

## Schnell-Check nach dem Anlegen

- [ ] Alle 5 Tasks sichtbar
- [ ] Assignment aktiv
- [ ] Assignment an `student@test.local` zugewiesen
- [ ] D1.2 hat `max_attempts = 3`
- [ ] D1.4 hat Intelligent Test + Randomizer gespeichert

