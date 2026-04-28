# Phase 1 – Ultra-Kurz Copy/Paste

## Legacy/Current-state Banner

Diese Datei ist eine historische Kurzvorlage fuer einen konkreten Import-/Erstellungsdurchlauf.
Sie ist nicht als aktuelle Standard-Doku fuer Task-Authoring gedacht.

Fuer den aktuellen Produktstand zuerst lesen:
- [TASK_AUTHORING_GUIDE.md](TASK_AUTHORING_GUIDE.md)
- [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)

## D1.1 Test: Addition

**Type:** Code  
**Title:** Test: Addition

**Beschreibung**
```text
Schreibe ein Python-Programm, das die beiden vorgegebenen Zahlen x und y addiert.
Speichere das Ergebnis in der Variable result.
```

**Template**
```python
x = 5
y = 3
result =
```

**Solution**
```python
result = x + y
```

**Expected Output**
```text
8
```

---

## D1.2 Test: Multiplikation

**Type:** Code  
**Title:** Test: Multiplikation

**Beschreibung**
```text
Multipliziere die beiden vorgegebenen Zahlen a und b.
Speichere das Ergebnis in der Variable result.
```

**Template**
```python
a = 4
b = 7
result =
```

**Solution**
```python
result = a * b
```

**Expected Output**
```text
28
```

**Hint 1**
```text
Nutze den * Operator.
```

**Hint 2**
```text
Die gesuchte Rechnung ist: a * b
```

**Hint 3**
```text
Speichere das Ergebnis so: result = a * b
```

**Max Attempts:** `3`  
**Show Solution:** `Ja`

---

## D1.3 Test: Mini-Taschenrechner

**Type:** Project (Folder Structure)  
**Title:** Test: Mini-Taschenrechner

**Beschreibung**
```text
Baue einen Mini-Taschenrechner mit zwei Eingabefeldern und einem Addieren-Button.
Beim Klick auf den Button soll die Summe in einem Ausgabefeld angezeigt werden.
```

**init.py**
```python
import idegui as ui

def addieren(trigger):
    zahl1 = float(ui.get('input1'))
    zahl2 = float(ui.get('input2'))
    ergebnis = zahl1 + zahl2
    ui.set('output', str(ergebnis))

ui.set('output', '0')
```

**index.html**
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

**style.css**
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

## D1.4 Test: Intelligent Multiply

**Type:** Code (Intelligent Test aktiv)  
**Title:** Test: Intelligent Multiply

**Beschreibung**
```text
Multipliziere die zufällig vorgegebenen Zahlen x und y.
Speichere das Ergebnis in der Variable result.
```

**Template**
```python
# x und y werden automatisch vorgegeben
result =
```

**Solution**
```python
result = x * y
```

**Randomizer**
```python
import random
x = random.randint(1, 10)
y = random.randint(1, 10)
```

---

## D1.5 Test: Python Basics Quiz

**Type:** Quiz  
**Title:** Test: Python Basics Quiz

**Frage**
```text
Was gibt print(2+2) aus?
```

**Antworten**
- A: `4` ✅ korrekt
- B: `22`
- C: `Fehler`
- D: `2+2`

---

## D2.1 Assignment

**Title**
```text
Test-Assignment März 2026
```

**Kurzbeschreibung**
```text
Gemischtes Test-Assignment für Phase 1 (Code, Projekt, Intelligent Test, Quiz).
```

**Reihenfolge Tasks**
1. `Test: Addition`
2. `Test: Multiplikation`
3. `Test: Mini-Taschenrechner`
4. `Test: Intelligent Multiply`
5. `Test: Python Basics Quiz`
