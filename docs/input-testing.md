# Input-Based Testing - Dokumentation

## Übersicht

Das Python IDE unterstützt jetzt **Input-basierte Tests** für Funktionen. Damit können Tasks erstellt werden, bei denen die gleiche Funktion mit verschiedenen Inputs getestet wird.

## Zwei Testing-Modi

### 1. Legacy Mode (ohne Input)

**Verwendung:** Code der direkt `print()` nutzt

**Format:**
```json
[
  {"input": "", "expected": "Hallo Welt"}
]
```

**Verhalten:**
- Der gesamte Code wird ausgeführt
- Die gesamte Ausgabe wird mit `expected` verglichen
- Ideal für Programme die direkt Ausgaben produzieren

**Beispiel:**
```python
# Code-Template:
name = "Max"
alter = 25
print(f"Ich bin {name} und {alter} Jahre alt.")

# Test Case:
{"input": "", "expected": "Ich bin Max und 25 Jahre alt."}
```

---

### 2. Function Testing Mode (mit Input)

**Verwendung:** Funktionen die Parameter haben und Return-Werte liefern

**Format:**
```json
[
  {"input": "5", "expected": "25"},
  {"input": "10", "expected": "100"},
  {"input": "-3", "expected": "9"}
]
```

**Verhalten:**
- Der Code wird einmal ausgeführt (Funktionsdefinition)
- Für jeden Test-Case wird die Funktion mit dem Input aufgerufen
- Return-Wert oder Print-Ausgabe wird erfasst
- Jeder Test wird separat validiert

**Beispiel:**
```python
# Code-Template:
def quadrat(x):
    return x * x

# Test Cases:
[
  {"input": "5", "expected": "25"},    # quadrat(5) → 25
  {"input": "10", "expected": "100"},  # quadrat(10) → 100
  {"input": "-3", "expected": "9"}     # quadrat(-3) → 9
]
```

---

## Input-Formate

### Einzelner Parameter

**Zahlen:**
```json
{"input": "5", "expected": "25"}
```
→ Wird ausgeführt als: `funktion(5)`

**Strings:**
```json
{"input": "Hallo", "expected": "ollaH"}
```
→ Wird ausgeführt als: `funktion("Hallo")`

---

### Mehrere Parameter (Komma-getrennt)

```json
{"input": "5,1,10", "expected": "True"}
```
→ Wird ausgeführt als: `funktion(5, 1, 10)`

**Gemischte Typen:**
```json
{"input": "Max,25,true", "expected": "..."}
```
→ Wird ausgeführt als: `funktion("Max", 25, True)`

---

## Mehrere akzeptierte Lösungen

Das System unterstützt auch **mehrere korrekte Outputs** für einen Input (OR-Logik):

```json
[
  {
    "input": "5",
    "expected": ["25", "5²", "5 * 5"]
  }
]
```

**Verhalten:**
- Test besteht, wenn **EINE** der Optionen matched
- Ideal für Formatierungs-Varianten
- Gut für verschiedene Schreibweisen (mit/ohne Punkt, etc.)

---

## Validation Modes

### `loose` (Standard)

- Ignoriert mehrfache Leerzeichen
- Trim von Whitespace
- Toleranter Vergleich

**Wann verwenden:** Wenn exakte Formatierung nicht wichtig ist

### `strict`

- Exakter String-Vergleich
- Whitespace muss perfekt matchen
- Keine Normalisierung

**Wann verwenden:** Wenn exakte Ausgabe erforderlich ist

---

## Beispiel-Tasks

### Beispiel 1: Quadrat-Funktion

```php
[
    'title' => 'Quadrat berechnen',
    'code_template' => 'def quadrat(x):
    return x * ___',
    'test_cases' => json_encode([
        ['input' => '5', 'expected' => '25'],
        ['input' => '10', 'expected' => '100'],
        ['input' => '-3', 'expected' => '9'],
        ['input' => '0', 'expected' => '0']
    ]),
    'validation_mode' => 'strict'
]
```

**Student sieht:**
```
✓ Test 1
  Input: 5
  Ausgabe: 25
  Erwartet: 25

✓ Test 2
  Input: 10
  Ausgabe: 100
  Erwartet: 100
  
...
```

---

### Beispiel 2: String-Umkehrung

```php
[
    'title' => 'String umkehren',
    'code_template' => 'def umkehren(text):
    return text[___]',
    'test_cases' => json_encode([
        ['input' => 'Hallo', 'expected' => 'ollaH'],
        ['input' => 'Python', 'expected' => 'nohtyP'],
        ['input' => 'Test', 'expected' => 'tseT']
    ]),
    'validation_mode' => 'strict'
]
```

---

### Beispiel 3: Mehrere Parameter

```php
[
    'title' => 'Zahl in Bereich prüfen',
    'code_template' => 'def im_bereich(zahl, minimum, maximum):
    return ___ <= zahl <= ___',
    'test_cases' => json_encode([
        ['input' => '5,1,10', 'expected' => 'True'],
        ['input' => '15,1,10', 'expected' => 'False'],
        ['input' => '0,-5,5', 'expected' => 'True']
    ]),
    'validation_mode' => 'strict'
]
```

---

## Technische Details

### Code-Ausführung

Das System:

1. **Findet die Funktion** im User-Code (erste definierte Funktion)
2. **Parst jeden Input**:
   - Versucht Werte als Python-Literals zu evaluieren (eval)
   - Falls das fehlschlägt, behandelt sie als Strings
3. **Ruft die Funktion auf** mit den geparsten Parametern
4. **Erfasst Output**:
   - Return-Wert (wenn vorhanden)
   - Print-Ausgaben (wenn vorhanden)
5. **Vergleicht** mit expected value(s)

### Fehlerbehandlung

```python
try:
    output = funktion(args)
except Exception as e:
    # Fehler wird als Test-Failure gewertet
    result["error"] = str(e)
```

Studenten sehen:
```
✗ Test 1
  Input: 5
  Fehler: name 'quadrat' is not defined
```

---

## Best Practices

### ✅ DO

- **Eine Funktion pro Task** (erste gefundene wird getestet)
- **Klare, eindeutige Inputs** (5 statt 5.0 wenn Integer erwartet)
- **Aussagekräftige Expected Values** ("True"/"False" statt 1/0)
- **Mehrere Test-Cases** für Edge Cases (0, negative Zahlen, leere Strings)
- **Validation Mode bewusst wählen** (strict für exakte, loose für flexible)

### ❌ DON'T

- **Mehrere Funktionen** im Code-Template (nur erste wird getestet)
- **Komplexe Datenstrukturen** als Input (noch nicht unterstützt)
- **Tests ohne Input mischen** mit Tests mit Input im gleichen Task
- **Zu viele Tests** (>10 Tests werden unübersichtlich)

---

## Migration von Legacy zu Function Testing

**Vorher (Legacy):**
```json
[
  {"input": "", "expected": "25\n100\n9"}
]
```

Der Student musste alle Fälle in einem Code abdecken.

**Nachher (Function Testing):**
```json
[
  {"input": "5", "expected": "25"},
  {"input": "10", "expected": "100"},
  {"input": "-3", "expected": "9"}
]
```

Klare Trennung, besseres Feedback bei Fehlern.

---

## UI-Feedback

### Bei erfolgreichen Tests:

```
✓ Test 1
  Input: 5
  Ausgabe: 25
  Erwartet: 25

✓ Test 2
  Input: 10
  Ausgabe: 100
  Erwartet: 100
```

### Bei fehlgeschlagenen Tests:

```
✗ Test 2
  Input: 10
  Ausgabe: 101
  Erwartet: 100
```

### Bei mehreren Optionen (und Match):

```
✓ Test 1
  Input: Max,25
  Ausgabe: Ich bin Max und 25 Jahre alt.
  Erwartet: Ich bin Max und 25 Jahre alt. ODER Ich bin Max und 25 Jahre alt
  (Matched: Option 1)
```

---

## Zusammenfassung

| Feature | Legacy Mode | Function Testing Mode |
|---------|-------------|----------------------|
| Input verwendet | ❌ Nein | ✅ Ja |
| Mehrere Tests | ✅ Möglich | ✅ Empfohlen |
| Funktionsaufruf | ❌ Nein | ✅ Ja |
| Return-Werte | ❌ Nur print() | ✅ Return + print() |
| Granulares Feedback | ❌ Nein | ✅ Pro Test |
| Use Case | Programme | Funktionen |

---

## Weiterführende Beispiele

Für vollständige Beispiele siehe:
- `scripts/create_input_examples.php` - Erstellt 3 Beispiel-Tasks
- `scripts/verify_input_examples.php` - Zeigt Task-Details an
- `scripts/example_multiple_solutions.php` - Mehrere Lösungsoptionen

Teste in der IDE:
```
http://localhost/pythonIDE/public/assignments.php
→ Assignment: "Funktionen mit verschiedenen Eingaben"
```
