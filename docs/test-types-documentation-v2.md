# Test-Typen und Validierungs-Flow - Neue Dokumentation (v2)

## ⚠️ WICHTIG: task_text Feld

**`task_text` ist die STUDENT-FACING AUFGABENSTELLUNG für ALLE Task-Typen:**
- Wird zentral über dem Code-Editor angezeigt
- Ist die Aufgabenbeschreibung/Frage für den Student
- **Muss für jeden Task gesetzt sein** (Code, Quiz, etc.)
- Ist **UNABHÄNGIG** von `description` (nur Meta-Info für Admin)

---

## System Overview

### Task Types (Container-Level in `task_type` Feld):
- **`code`** - Standard Code-Submission mit test_cases (Einzeltests)
- **`code_reading`** - Code analysieren mit festen Testwerten
- **`code_random_complex`** - Code mit zufälligen Eingabewerten
- **`single_choice`, `multiple_choice`, `free_text`** - Quiz-Typen

### Test Types (innerhalb Code-Tasks in `test_cases` Array):
- **`output`** - Konsolen-Output validieren
- **`function`** - Funktionsaufrufe und Rückgabewerte testen
- **`variable`** - Variablenwerte nach Code-Ausführung testen
- **`code_check`** - Keywords/Operatoren im Code überprüfen
- **`intelligent`** - Automatisierte Tests mit Zufallswerten

---

## 1. OUTPUT Test (Text/Regex/Solution Comparison)

### Zweck
Student schreibt Code, der Output via `print()` erzeugt. Output wird validiert basierend auf `expected_type`.

### Basis-Struktur
```json
{
  "type": "output",
  "expected_type": "text",
  "validation_mode": "loose",
  "expected": "Hallo World"
}
```

### expected_type Options

#### 1a. `expected_type: "text"` (Default) - Pattern Matching
Vergleicht Output mit Wildcard-Pattern oder String.

```json
{
  "type": "output",
  "expected_type": "text",
  "validation_mode": "strict",
  "expected": "Das Ergebnis ist: 42"
}
```

**validation_mode Optionen:**
- `"strict"` - Exact string match (mit `compareTestOutput` Wildcard-Logik)
- `"loose"` - Whitespace normalisiert (mehrere Spaces = ein Space)
- `"contains"` - Substring-Match (erwartet enthält expected)
- `"default"` → fallback auf `"loose"`

#### 1b. `expected_type: "regex"` - Regex Pattern Matching
Validiert Output gegen Regex-Pattern (case-insensitive).

```json
{
  "type": "output",
  "expected_type": "regex",
  "expected": "^ISBN\\s+(978|979)-\\d{1,5}-\\d{1,7}-\\d{1,7}-\\d{1}$"
}
```

**Wichtig:** Output wird automatisch `.trim()` angewendet (Newlines entfernt).

#### 1c. `expected_type: "solution"` - Musterlösung Vergleich
Vergleicht Student-Output mit Musterlösung-Output (benötigt `solution_code`).

```json
{
  "type": "output",
  "expected_type": "solution",
  "validation_mode": "loose"
}
```

Erfordert: `solution_code` Feld in der Task gesetzt!

### Auto-Description Output
- `expected_type: "text"` + `validation_mode: "loose"` → "Flexible Match"
- `expected_type: "text"` + `validation_mode: "strict"` → "Exact Match"
- `expected_type: "regex"` → "Regex Pattern"
- `expected_type: "solution"` → "Solution Code Output"

---

## 2. FUNCTION Test - Funktionsaufrufe

### Zweck
Student implementiert Funktion. Test ruft Funktion mit bekannten Argumenten auf und prüft Rückgabewert.

### Struktur
```json
{
  "type": "function",
  "function_name": "quadrat",
  "args": [5],
  "expected": 25
}
```

**Felder:**
- `function_name` - Name der Funktion (✓ Required)
- `args` - Array von Argumenten zum Übergeben (✓ Required)
- `expected` - Erwarteter Rückgabewert (✓ Required)

### Execution Flow
1. Student-Code ausführen (definiert Funktion)
2. Funktion aufrufen: `function_name(*args)` 
3. Rückgabewert extrahieren
4. Mit `expected` vergleichen (exakter Numeric/String Vergleich)

### Hinweise
- **Parameter-Namen sind egal** - nur Anzahl und Reihenfolge zählt
- Funktionsdefinition muss in Student-Code enthalten sein
- Rückgabewert wird strikte verglichen (Typ + Wert)

### Auto-Description
```
Funktion: quadrat(1 Parameter)
```

---

## 3. VARIABLE Test - Variablenwerte

### Zweck
Student schreibt Code der Variablen berechnet. Nach Code-Ausführung werden Variablenwerte extrahiert und verglichen.

### Struktur
```json
{
  "type": "variable",
  "init_var_names": ["x"],
  "expected_var_names": ["result"],
  "test_cases": [
    {
      "inputs": {"x": 5},
      "expected": {"result": 25}
    }
  ]
}
```

**Felder:**
- `init_var_names` - Variablen die vor Student-Code gesetzt werden
- `expected_var_names` - Variablen die nach Student-Code extrahiert werden
- `test_cases.inputs` - Werte für init_var_names
- `test_cases.expected` - Erwartete Werte für expected_var_names

### Execution Flow
1. Setze Input-Variablen im Namespace: `x = 5`
2. Student-Code ausführen
3. Output-Variablen extrahieren: `result` aus Namespace
4. Mit expected vergleichen

### Auto-Description
```
Input-Variablen: x, y
Checking: result, balance
```

---

## 4. CODE_CHECK Test - Keyword Validierung

### Zweck
Überprüft, dass Student bestimmte Keywords/Operatoren MUSS enthalten und andere NICHT enthalten darf.

### Struktur
```json
{
  "type": "code_check",
  "keywords": ["for", "range"],
  "forbidden": ["while"]
}
```

**Felder:**
- `keywords` - Keywords die enthalten sein MÜSSEN (✓ Required)
- `forbidden` - Keywords die NICHT enthalten sein dürfen (✓ Required)

### Execution Flow
1. Parse Student-Code
2. Prüfe: Alle `keywords` enthalten? (Case-insensitive)
3. Prüfe: Keine `forbidden` Keywords? (Case-insensitive)
4. Test bestanden wenn beide Prüfungen OK

### Auto-Description
```
Erforderliche Keywords: for, range
Verbotene Keywords: while
```

---

## 5. INTELLIGENT Test - Automatisierte Zufallstests

### Zweck
Automatisierte Tests mit Zufallswerten die Student-Code gegen Musterlösung vergleichen.

### Zwei Modi: FUNCTION und VARS

---

### 5a. MODE: FUNCTION (Funktions-Test mit Random)

**Szenario:** Student implementiert Funktion. Test ruft sie mit randomisierten Parametern auf und vergleicht mit Musterlösung.

#### Struktur
```json
{
  "type": "intelligent",
  "mode": "function",
  "tests": 5,
  "function": {
    "name": "verdoppeln",
    "params": ["x"]
  }
}
```

**Separates Feld:**
```
randomizer_code:
import random
values = {
    "x": random.randint(1, 100)
}
```

#### Execution Loop (für jeden Test)
1. **Randomizer ausführen** → generiert `values` dict
2. **Student-Funktion aufrufen** → `verdoppeln(42)` → gibt z.B. `84` zurück
3. **Musterlösung-Funktion aufrufen** → `verdoppeln(42)` → gibt `84` zurück
4. **Vergleich** → `Student-Result == Solution-Result`?
5. Wiederholung `tests` mal mit verschiedenen Zufallswerten

#### Required Felder
- `function.name` - Funktionsname
- `function.params` - Array von Parameternamen (in Reihenfolge)
- `tests` - Anzahl Iterationen (default: 5)
- `randomizer_code` - Python Code der `values` dict generiert
- `solution_code` - Musterlösung mit Funktionsdefinition

#### Auto-Description
```
Funktionsname: verdoppeln
Parameter: 1
```

---

### 5b. MODE: VARS (Variablen-Test mit Random)

**Szenario:** Student berechnet Variablen basierend auf Input-Variablen. Randomizer variiert die Eingaben pro Test.

#### Struktur
```json
{
  "type": "intelligent",
  "mode": "vars",
  "tests": 5,
  "inputs": ["a", "b", "c"],
  "outputs": ["result1", "result2"]
}
```

**Separates Feld:**
```
randomizer_code:
import random
values = {
    "a": random.randint(1, 50),
    "b": random.randint(1, 50),
    "c": random.randint(1, 50)
}
```

**Code-Template (mit Init-Block!):**
```python
#INIT START
a = 0
b = 0
c = 0
#INIT END

# Student-Code hier:
result1 = a + b
result2 = a * c
```

#### Execution Loop (für jeden Test)
1. **Randomizer ausführen** → `a=23, b=17, c=8`
2. **Student-Code ausführen** (mit INIT-Block) → berechnet `result1`, `result2`
3. **INIT-Block wird ignoriert**, randomizer-Werte override die 0-Werte
4. **Calculation Code nach #INIT END wird re-executed** mit randomized values
5. **Musterlösung ausführen** (mit gleichen randomized values)
6. **Vergleich** → Student-Outputs == Solution-Outputs?
7. Wiederholung `tests` mal mit verschiedenen Zufallswerten

#### Required Felder
- `inputs` - Array von Input-Variablen
- `outputs` - Array von Output-Variablen
- `tests` - Anzahl Iterationen (default: 5)
- `randomizer_code` - Python Code der `values` dict generiert
- `solution_code` - Musterlösung (mit oder ohne INIT-Block)
- Code-Template MUSS `#INIT START` und `#INIT END` Marker haben!

#### INIT-Block Mechanismus
```python
#INIT START
x = 0  # Demo-Wert für Student
y = 0
#INIT END

# Student schreibt hier Code
result = x + y * 2
```

**Flow:**
1. Kompletter Code wird ausgeführt (lädt INIT-Werte: x=0, y=0)
2. Randomizer setzt Werte NACH INIT: x=23, y=14
3. Nur Code nach `#INIT END` wird RE-EXECUTED mit neuen Werten
4. Output-Variablen extrahieren

#### Auto-Description
```
Input-Variablen: a, b, c
Checking: result1, result2
```

---

## 6. CODE_RANDOM_COMPLEX Task Type

### Zweck
Student gibt EINE Antwort ein. Test generiert zufällige Eingabewerte und berechnet Musterlösung zur Validierung.

### Struktur
```json
// NO test_cases field!
```

**Separates Feld:**
```
randomizer_code:
import random
binary = format(random.randint(0, 255), '08b')
values = {"binary": binary}
```

**Code-Template (optional):**
```python
# Konvertiere Binärzahl zu Dezimal
result = ...
```

**Solution-Code:**
```python
result = int(values["binary"], 2)
```

#### Execution Flow
1. **Backend**: `randomizer_code` ausführen → `values` dict
2. **Frontend**: Anzeige: "Binary: 10101010" (aus `values`)
3. **Code-Template** anzeigen (falls gesetzt)
4. **Student antwortet:** Text-Eingabe: "170"
5. **Backend**: `solution_code` ausführen mit `values` → `computed_value`
6. **Vergleich**: `student_answer` == `computed_value`?
7. Pro Iteration neue Zufallswerte (per `max_iterations`)

#### Required Felder
- `randomizer_code` - Generiert `values` dict
- `solution_code` - Berechnet `result` aus `values`
- `max_iterations` (optional, default: 3) - Anzahl Versuche

#### Optional
- `code_template` - Hilfe/Struktur für Student
- `task_text` - Aufgabenbeschreibung

---

## 7. CODE_READING Task Type

### Zweck
Student analysiert vorgegebenen Code mit Platzhaltern und berechnet/errät Ausgabe oder Variable-Werte.

### Struktur: variable_overrides
```json
[
  {
    "inputs": {"a": 1, "b": 5},
    "expected": {"variable": "summe"}
  },
  {
    "inputs": {"a": 2, "b": 6},
    "expected": {"value": 360}
  }
]
```

**Code-Template (mit Platzhaltern für inputs):**
```python
a = {a}
b = {b}
summe = 1
for n in range(a, b):
    summe = summe + n * summe
```

**⚠️ WICHTIG - solution_code Hinweis:**
- Nutzt die **gleichen Platzhalter** `{a}`, `{b}` wie code_template
- Diese werden mit den Werten aus `inputs` ersetzt
- Wird ausgeführt um das erwartete Ergebnis zu berechnen (bei variable mode)
- **Die Ergebnisvariable ist NICHT ein Platzhalter**, sondern der **Wert der Variablen am ENDE des Scripts**
  - z.B. wenn CODE endet mit `summe = 120`, wird dieser Wert `120` ausgelesen (NICHT der Platzhalter)
  - Das ist NICHT `{summe}` sondern die echte Variable namens `summe`

**solution_code Beispiel:**
```python
a = {a}
b = {b}
summe = 1
for n in range(a, b):
    summe = summe + n * summe
# => variable "summe" hat am Ende diesen Wert
```

#### expected Feld: Zwei Modi

**Modus 1: `{"variable": "summe"}`**
- Liest Variablenwert am ende des Scripts aus
- Zu vergleichender Wert = Was "summe" am Ende ist
- Admin definiert: Welche Variable sollte der Student ablesen?

**Modus 2: `{"value": 360}`**
- Direkter erwarteter Wert (hardcoded)
- Zu vergleichender Wert = Dieser Literal-Wert
- Admin definiert: Das Ergebnis muss genau diesen Wert sein

#### Execution Flow (pro Iteration)
1. Wähle einen Testwert-Set aus `variable_overrides[i]`
2. Extrahiere `inputs` dict: `{a: 1, b: 5}`
3. Extrahiere `expected` dict: `{"variable": "summe"}`
4. Code-Template Platzhalter ersetzen: `{a}` → 1, `{b}` → 5
5. Code anzeigen (mit ersetzten Werten): Student sieht fertigen Code
6. Student gibt Ergebnis ein
7. **Expected-Wert bestimmen:**
   - Wenn `expected.variable` gesetzt: solution_code ausführen, Variable auslesen
   - Wenn `expected.value` gesetzt: Diesen Wert direkt nutzen
8. **Vergleich:** `student_answer` == expected_value?
9. Nächster Testwert-Set aus `variable_overrides`

#### Required Felder
- `variable_overrides` - Array mit `{inputs: {...}, expected: {...}}`
- `code_template` - Template mit `{varname}` Platzhaltern
- `max_iterations` - Auto-calculated: `len(variable_overrides)`
- `solution_code` - Berechnet erwartetes Ergebnis (für variable mode)

#### Optional
- Keine

#### 💡 Admin-Hinweis: Auto-Modus nutzen

**Für bessere UX - verwende AUTO-Modus:**
```json
{
  "inputs": {"a": 1, "b": 5},
  "expected": {"variable": "summe"}  // ← AUTO: Berechnet aus solution_code
}
```

**Statt MANUAL-Modus:**
```json
{
  "inputs": {"a": 1, "b": 5},
  "expected": {"value": 120}  // ← MANUAL: Hardcodierter Wert
}
```

**Warum AUTO besser ist:**
- ✅ Automatisch aus `solution_code` berechnet = keine manuellen Fehler
- ✅ Wenn Code später geändert wird, stimmt Lösung automatisch
- ✅ Einfacher zu administrieren

**Code_Reading Anforderungen:**
- 💡 **NUR feste Wert-Sets** (keine Zufallswerte!)
- 💡 **Iteration = Set-Reihenfolge** (1. Set → Iteration 1, 2. Set → Iteration 2)
- 💡 **Format:** `[{"var1": 1, "var2": "A"}, {...}]`
- 💡 **Im Code Template:** `{varName}` verwenden. Beispiel: `binary = "{binary}"` oder `x = {x}`
- 💡 `max_iterations` wird automatisch berechnet: Anzahl Sets in `variable_overrides`

---

## Summary: Admin-Felder pro Task-Type

| Feld | code | code_reading | code_random_complex | single_choice |
|------|------|--------------|-------------------|---------------|
| **task_text** | ✅ PFLICHT | ✅ PFLICHT | ✅ PFLICHT | ✅ PFLICHT |
| **description** | ℹ️ Optional | ℹ️ Optional | ℹ️ Optional | ℹ️ Optional |
| **code_template** | ✅ Optional | ✅ Required | ✅ Optional | ❌ N/A |
| **test_cases** | ✅ Required | ❌ NULL | ❌ NULL | ❌ N/A |
| **solution_code** | ✅ Optional | ✅ Optional | ✅ Required | ❌ N/A |
| **randomizer_code** | ❌ N/A | ❌ N/A | ✅ Required | ❌ N/A |
| **variable_overrides** | ❌ N/A | ✅ Required | ❌ N/A | ❌ N/A |
| **options** | ❌ N/A | ❌ N/A | ❌ N/A | ✅ Required |

---

## Auto-Description Generiert

Wenn Admin "Auto-Description generieren" klickt, wird eine einheitliche Tabelle erstellt mit:

```
Test-Anforderungen
Aspekt | Details
-------|--------
Funktionsname | quadrat
Parameter | 1
Input-Variablen | x, y
Checking | result
OUTPUT | Regex Pattern
OUTPUT | Flexible Match
Erforderliche Keywords | for, range
Verbotene Keywords | while
```

---

## Legacy-Elemente (REMOVED/Deprecated)

### ❌ Entfernt:
- `solution_compare: true` → Verwende `expected_type: "solution"`
- `input` Feld in OUTPUT/FUNCTION Tests → nicht standardisiert
- `test_cases` für Intelligent VARS Mode → direkt in JSON mit `inputs/outputs`

### ⚠️ Deprecated aber noch unterstützt (Backward Compat):
- `test_cases.solution_compare` → Verwende `expected_type`
- `validation_mode: 'pattern'` → Verwende `expected_type: 'regex'`

---

## Best Practices

### Regex-Tests
- Nutze `^...$` um Start/End zu definieren
- Output wird automatisch `.trim()` angewendet
- Case-insensitive matching (Flag `i` im RegExp)
- Backslashes müssen doppelt escaped sein: `\\s`, `\\d`

### Intelligent VARS Mode
- **INIT-Block ERFORDERLICH** in code_template mit `#INIT START` / `#INIT END`
- Zero-Werte im INIT-Block (`a = 0`) dienen als Demonstration
- Randomizer setzt echte Werte DANACH
- Calculation-Code nach `#INIT END` wird mit echten Werten re-executed

### Intelligent FUNCTION Mode
- Parameter-Reihenfolge in `function.params` muss mit Randomizer `values` Keys matchen
- Beispiel: `params: ["x", "y"]` → `values: {"x": 5, "y": 10}` → `func(5, 10)`

### Code-Reading
- Verwende Python-kompatible Syntax in Platzhaltern
- `{A}` für boolean → wird zu `True` oder `False`
- `{x}` für int → wird zu `42`, `-5`, etc.
- Escaping: `{var}` nicht in Strings verschachteln

