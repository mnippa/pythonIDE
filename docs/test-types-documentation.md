# ⚠️ DEPRECATED - Bitte verwende test-types-documentation-v2.md

Diese Dokumentation ist **VERALTET** und wird nicht mehr gepflegt.

## 🔄 Migration zur neuen Dokumentation

**Neue offizielle Dokumentation:** [test-types-documentation-v2.md](test-types-documentation-v2.md)

Die v2-Dokumentation enthält:
- ✅ Vereinheitlichte Struktur für alle Test-Typen
- ✅ Korrekte OUTPUT-Test Dokumentation mit `expected_type` und `validation_mode`
- ✅ Vollständige Intelligent-Test Dokumentation (FUNCTION + VARS Modes)
- ✅ Regex-Pattern Support mit Examples
- ✅ Best Practices und häufige Fehler
- ✅ Legacy-Element Audit mit Entfernungs-Strategie

## Was hat sich geändert?

### Hauptänderungen:
1. **OUTPUT Tests:** Neue `expected_type` Optionen (text, regex, solution)
2. **Validation:** Unified `validation_mode` Konzept (strict, loose, contains)
3. **Intelligent Tests:** Klare MODE-Unterscheidung (function vs vars)
4. **Init-Block:** Mechanismus für VARS-Mode detailliert dokumentiert
5. **Auto-Description:** Einheitliche Tabellen-Generierung

## Alte Konzepte (DEPRECATED):
- ❌ `solution_compare: true` → Verwende `expected_type: "solution"`
- ❌ `validation_mode: 'pattern'` → Verwende `expected_type: "regex"`
- ❌ Uneinheitliche `input` Felder → Unified zu `inputs`/`outputs`

---

**→ [ZUR NEUEN DOKUMENTATION (test-types-documentation-v2.md)](test-types-documentation-v2.md)**



## ⚠️ WICHTIG: task_text Feld

**`task_text` ist die STUDENT-FACING AUFGABENSTELLUNG für ALLE Task-Typen:**
- Wird zentral über dem Code-Editor oder Quiz angezeigt
- Ist die Aufgabenbeschreibung/Frage für den Student
- **Muss für jeden Task gesetzt sein** (Code, Quiz, Code-Reading, etc.)
- Ist **UNABHÄNGIG** von `description` (das ist nur Metainformation für Code-Tasks)

---

## Überblick: Task Types und Test Types

### Task Types (in `task_type` Feld):
- `code` - Standard Code-Submission mit test_cases
- `code_reading` - Code analysieren (Werte aus Vorlage ablesen)
- `code_random_complex` - Code mit Zufallswerten
- `single_choice`, `multiple_choice`, `free_text` - Quiz-Typen

### Test Types (in `test_cases` bei Code-Tasks):
- `output` - Konsolen-Output vergleichen
- `function` - Funkionsaufrufe und Rückgabewerte testen
- `variable` - Variablenwerte nach Code-Ausführung testen
- `code_check` - Keywords/Operatoren im Code prüfen
- `intelligent` - Kombinierte Tests mit Random-Werten

---

## 1. OUTPUT Test (Code Task)

**Zweck**: Student schreibt Code, der Ausgabe via `print()` erzeugt

**Struktur:**
```json
{
  "type": "output",
  "input": {"message": "Hallo"},
  "expected": "Hallo World"
}
```

**Flow:**
1. code_template ausführen
2. Station input-Vars in Namespace
3. Student solution_code ausführen
4. Print-Output abfangen
5. **Vergleich**: Output vs expected

**Info für Teilnehmer**: ❌ KEINE (nur "Gib etwas aus")

---

## 2. FUNCTION Test (Code Task)

**Zweck**: Student implementiert Funktion, die aufgerufen wird

**Struktur:**
```json
{
  "type": "function",
  "func_name": "summe",
  "args": [5, 10],
  "expected": 15
}
```

**Flow:**
1. code_template gesetzt (optional)
2. Student solution_code mit Funktionsdefinition
3. **Funktionsaufruf**: `summe(5, 10)` → ergibt Rückgabewert
4. **Vergleich**: Rückgabewert vs expected

**Info für Teilnehmer**: ✅ 
```
Funktion: summe(2 Parameter)
```
**Hinweis**: Parameter-Namen spielen KEINE Rolle, **nur Anz ahl und Reihenfolge!**

---

## 3. VARIABLE Test (Code Task)

**Zweck**: Student berechnet Variablenwerte

**Struktur:**
```json
{
  "type": "variable",
  "input": {"x": 5},
  "var_name": "result",
  "expected": 25
}
```

**Flow:**
1. code_template ausführen (generiert input Variablen)
2. Station input-Vars: `x = 5`
3. Student solution_code ausführen
4. Variablenwert auslesen: `result` nach Code-Ausführung
5. **Vergleich**: Variable vs expected

**Info für Teilnehmer**: ✅
```
Variablen Init: x
Ergebnisvariablen: result
```

---

## 4. CODE_CHECK Test (Code Task)

**Zweck**: Student muss bestimmte Keywords verwenden/vermeiden

**Struktur:**
```json
{
  "type": "code_check",
  "keywords": ["for", "range"],
  "forbidden": ["while"]
}
```

**Flow:**
1. Code des Students parsen
2. **Muss enthalten**: alle Keywords aus `keywords` Array
3. **Darf nicht enthalten**: nichts aus `forbidden` Array
4. **Vergleich**: Pattern Matching im Code

**Info für Teilnehmer**: ❌ KEINE (Code wird eingebaut/überprüft)

---

## 5. INTELLIGENT Test (Code Task mit Random-Werten)

**Zweck**: Automatisierte Tests mit Zufallswerten - Student-Code vs. Musterlösung

**Zwei Modi:**

### 5a. MODE FUNCTION (Funktions-Test mit Random)

**JSON-Struktur:**
```json
{
  "type": "intelligent",
  "mode": "function",
  "tests": 4,
  "function": {
    "name": "verdoppeln",
    "params": ["x"]
  }
}
```

**Randomizer Code (separates Feld `randomizer_code`):**
```python
import random
values = {
    "x": random.randint(1, 100)
}
```

**Flow:**
1. **Randomizer ausführen** (Backend): `x = random.randint(1, 100)` → z.B. `x = 42`
2. **Student-Funktion aufrufen**: `verdoppeln(42)` → gibt z.B. `84` zurück
3. **Musterlösung aufrufen** (solution_code): `verdoppeln(42)` → gibt `84` zurück
4. **Vergleich**: Student-Ergebnis vs. Musterlösung-Ergebnis
5. **Wiederholung**: 4x mit verschiedenen Zufallswerten (tests = 4)

**Info für Teilnehmer**: ✅
```
Funktion: verdoppeln(1 Parameter)
```
**Hinweis**: Parameternamen egal, nur Anzahl zählt!

---

### 5b. MODE VARS (Variablen-Test mit Random)

**JSON-Struktur:**
```json
{
  "type": "intelligent",
  "mode": "vars",
  "tests": 4,
  "inputs": ["a", "b", "c"],
  "outputs": ["result1", "result2"]
}
```

**Randomizer Code (separates Feld `randomizer_code`):**
```python
import random
values = {
    "a": random.randint(1, 50),
    "b": random.randint(1, 50),
    "c": random.randint(1, 50)
}
```

**Code Template (mit Init-Block):**
```python
#INIT START
a = 0
b = 0
c = 0
#INIT END

# Student schreibt hier Code
result1 = a + b
result2 = a * c
```

**Flow:**
1. **Code Template** mit Init-Block laden
2. **Randomizer ausführen** (Backend nach Init): Setzt `a = 25, b = 30, c = 15`
3. **Student-Code ausführen**: Berechnet `result1`, `result2`
4. **Musterlösung ausführen** (solution_code): Berechnet `result1`, `result2`
5. **Vergleich**: Student-Variablen vs. Musterlösung-Variablen
6. **Wiederholung**: 4x mit verschiedenen Werten

**Info für Teilnehmer**: ✅
```
Input-Variablen: a, b, c
Ergebnis-Variablen: result1, result2
```

**Admin-Hinweis**: 
- Init-Block mit `#INIT START` / `#INIT END` markieren!
- Randomizer setzt Variablen NACH Init neu
- Student muss Variablen im Init deklarieren (0-Werte)

---

## 6. CODE_RANDOM_COMPLEX Task Type

**Nur für Zufalls-Iterationen mit verstecktem Code!**

**Kein test_cases Feld!** Stattdessen ausgelagerte Felder für Randomizer:

**randomizer_code**: VERSTECKT - generiert `values` dict mit Random
```python
import random
binary = format(random.randint(0, 255), '08b')
values = {"binary": binary}
```
⚠️ **Student sieht dies NICHT!**

**code_template**: Optional - was der Student SIEHT oder als Vorlage
```python
# Konvertiere Binärzahl zu Dezimal
result = ...
```
❌ Wenn leer: nur Eingabefeld "Ergebnis eingeben..."
✅ Wenn gesetzt: Template als Hilfe/Struktur zeigen

**solution_code**: Berechnet `result` aus `values`
```python
result = int(values["binary"], 2)
```

**Flow:**
1. **Backend**: randomizer_code ausführen → `values` dict erzeugen (versteckt)
2. **Frontend**: `values` anzeigen: `binary = 10101010`
3. **Wenn code_template gesetzt**: auch Template anzeigen
4. **Student**: Ergebnis eingeben: `170`
5. **Frontend**: solution_code ausführen mit `values`
6. **computed_value**: Musterlösung-Ergebnis = `170`
7. **Vergleich**: `text_answer` vs `computed_value`

**Iterationen**: 
- `max_iterations`: Anzahl Versuche mit VERSCHIEDENEN Zufallswerten
- Pro Iteration: Neue values via randomizer_code generieren
- Student muss alle Iterationen bestehen (neue values = komplett neuer Versuch)

**Info für Teilnehmer**: ✅
```
Eingabevariablen: binary, decimal, ...
Gesucht: result
```

---

## 7. CODE_READING Task Type

**Code analysieren - feste Werte, kein Random!**

**variable_overrides**: Feste Testwerte (kein Random!)
```json
{
  "A": true,
  "B": false,
  "C": true
}
```

**code_template**: Vorlage mit `{A}`, `{B}`, `{C}` Platzhaltern
```python
result = {A} and {B} or not {C}
```

**Flow:**
1. Platzhalter ersetzen: `A = true, B = false, ...`
2. Code ausführen: `result = true and false or not true` → `false`
3. Student gibt ein: `false`
4. **Vergleich**: input vs computed_value

**Iterationen**: 
- `iterations_count`: Anzahl VERSCHIEDENER Testfälle aus variable_overrides Sets
- Pro Iteration: ANDERE feste Werte aus set

---

## Summary: Admin muss setzen

### ⚠️ WICHTIG: `task_text` ist IMMER erforderlich!
- **task_text** = Student-facing Aufgabenstellung/Frage (zentral angezeigt)
- **Muss für jeden Task-Typ gesetzt sein** (code, single_choice, code_reading, etc.)
- Ist UNABHÄNGIG von `description` (das ist nur Meta-Info, optional, für alle Task-Typen verfügbar)

| Type | task_text | Description | Test Cases | Randomizer Code | Code Template | Solution Code | Var Overrides |
|------|-----------|-------------|-----------|---|---|---|---|
| **code** | ✅ PFLICHT | ℹ️ Optional (Kontext) | ✅ JSON Array | ❌ | ✅ (optional) | ✅ (student code) | ❌ |
| **output** | ✅ PFLICHT | ℹ️ Optional | ✅ JSON Array | ❌ | ✅ (optional) | ✅ (student code) | ❌ |
| **function** | ✅ PFLICHT | ℹ️ Optional | ✅ JSON Array | ❌ | ✅ (optional) | ✅ (student code + func) | ❌ |
| **variable** | ✅ PFLICHT | ℹ️ Optional | ✅ JSON Array | ❌ | ✅ (optional) | ✅ (student code) | ❌ |
| **code_check** | ✅ PFLICHT | ℹ️ Optional | ✅ JSON Array | ❌ | ❌ | ❌ | ❌ |
| **intelligent** | ✅ PFLICHT | ℹ️ Optional | ✅ JSON (mode function/vars) | ✅ (generiert values) | ✅ (vars mode: mit Init-Block) | ✅ (Musterlösung) | ❌ |
| **code_random_complex** | ✅ PFLICHT | ℹ️ Optional | ❌ (NULL) | ✅ (generiert values) | ✅ (optional) | ✅ (result aus values) | ❌ |
| **code_reading** | ✅ PFLICHT | ℹ️ Optional | ❌ (NULL) | ❌ | ✅ (mit `{A}` Platzhaltern) | ❌ | ✅ JSON dict |
| **single_choice** | ✅ PFLICHT | ℹ️ Optional | ❌ | ❌ | ❌ | ❌ | ❌ |
| **multiple_choice** | ✅ PFLICHT | ℹ️ Optional | ❌ | ❌ | ❌ | ❌ | ❌ |
| **free_text** | ✅ PFLICHT | ℹ️ Optional | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## Feld-Übersicht nach Task-Type

### code_random_complex
- **randomizer_code** 🔒 VERSTECKT: erzeugt values
- **code_template** (optional): Falls Student Vorlage braucht
- **solution_code**: Berechnet result
- **test_cases**: NULL

### code_reading
- **code_template**: Mit Platzhaltern `{varname}`
- **variable_overrides**: Feste Testwerte
- **solution_code**: NOT USED (Student ergänzt Code)
- **test_cases**: NULL

