# Schnellreferenz: Test-Typen

## Legacy/Current-state Banner

Diese Schnellreferenz ist historisch nuetzlich, aber nicht vollstaendig fuer den aktuellen Plattformstand.

Fuer den aktuellen Produktstand zuerst lesen:
- [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)
- [TASK_AUTHORING_GUIDE.md](TASK_AUTHORING_GUIDE.md)

Bei Widerspruechen gilt der aktuelle Code plus `CONTEXT_CURRENT.md`.

## Übersicht

3 Test-Typen mit klarer Struktur:

| Typ | Wann verwenden | Was wird getestet |
|-----|----------------|-------------------|
| **OUTPUT** | Programme mit print() | Programmausgabe |
| **FUNCTION** | Funktionen | Return-Wert |
| **VARIABLE** | Berechnungen | Variablenwerte |

---

## 1. OUTPUT

**Test-Struktur:**
```json
{
  "type": "output",
  "input": "",
  "expected": "Ausgabetext"
}
```

**Mit mehreren Patterns:**
```json
{
  "type": "output",
  "input": "",
  "expected": ["Variante 1", "Variante 2"]
}
```

**PHP-Beispiel:**
```php
'test_cases' => json_encode([
    [
        'type' => 'output',
        'input' => '',
        'expected' => ['Hallo!', 'Hallo']
    ]
])
```

---

## 2. FUNCTION

**Test-Struktur (ein Argument):**
```json
{
  "type": "function",
  "function_name": "quadrat",
  "args": [5],
  "expected": 25
}
```

**Mit mehreren Argumenten:**
```json
{
  "type": "function",
  "function_name": "addiere",
  "args": [3, 7],
  "expected": 10
}
```

**PHP-Beispiel:**
```php
'test_cases' => json_encode([
    [
        'type' => 'function',
        'function_name' => 'quadrat',
        'args' => [5],
        'expected' => 25
    ],
    [
        'type' => 'function',
        'function_name' => 'quadrat',
        'args' => [10],
        'expected' => 100
    ]
])
```

---

## 3. VARIABLE

**Test-Struktur (eine Variable):**
```json
{
  "type": "variable",
  "init_vars": {"x": 5},
  "expected_vars": {"ergebnis": 25}
}
```

**Mit mehreren Variablen:**
```json
{
  "type": "variable",
  "init_vars": {"a": 5, "b": 10},
  "expected_vars": {"summe": 15, "produkt": 50}
}
```

**Mit Listen:**
```json
{
  "type": "variable",
  "init_vars": {"zahlen": [1, 2, 3, 4, 5]},
  "expected_vars": {"gerade": [2, 4]}
}
```

**PHP-Beispiel:**
```php
'test_cases' => json_encode([
    [
        'type' => 'variable',
        'init_vars' => ['a' => 5, 'b' => 10],
        'expected_vars' => ['summe' => 15, 'produkt' => 50]
    ],
    [
        'type' => 'variable',
        'init_vars' => ['a' => 3, 'b' => 7],
        'expected_vars' => ['summe' => 10, 'produkt' => 21]
    ]
])
```

---

## Entscheidungshilfe

### Wähle OUTPUT wenn:
- ✅ Der Code direkt `print()` nutzt
- ✅ Die gesamte Ausgabe geprüft wird
- ✅ Mehrere Formatierungen OK sind

**Beispiel:** Begrüßungsprogramm

### Wähle FUNCTION wenn:
- ✅ Funktionen geschrieben werden sollen
- ✅ Return-Werte wichtig sind
- ✅ Verschiedene Inputs getestet werden

**Beispiel:** Mathematische Funktionen

### Wähle VARIABLE wenn:
- ✅ Berechnungen durchgeführt werden
- ✅ Variablenwerte geprüft werden sollen
- ✅ Keine Funktionsdefinition nötig

**Beispiel:** Formelberechnungen

---

## Mehrere Tests

**Immer empfohlen:** 3-5 Test Cases pro Task

```php
'test_cases' => json_encode([
    [...], // Test 1
    [...], // Test 2
    [...], // Test 3
])
```

**Denke an Edge Cases:**
- 0, negative Zahlen
- Leere Listen/Strings
- Grenzwerte (min/max)

---

## Validation Mode

```php
'validation_mode' => 'loose'   // Ignoriert Whitespace
'validation_mode' => 'strict'  // Exakter Vergleich
```

**Empfehlung:**
- OUTPUT: `loose` (Formatierung flexibel)
- FUNCTION: `strict` (Return-Werte exakt)
- VARIABLE: `strict` (Werte exakt)

---

## Code-Template Hinweise

### OUTPUT Tasks:
```python
# Direkt executable
name = "Alice"
print(f"Hallo {name}")
```

### FUNCTION Tasks:
```python
# Funktion definieren
def quadrat(x):
    return x * ___  # Student ergänzt
```

### VARIABLE Tasks:
```python
# Variablen werden vom System gesetzt
# Student schreibt nur Berechnung
ergebnis = a + b
```

---

## Vollständiges Beispiel

```php
[
    'title' => 'Würfel berechnen',
    'description' => '**TEST-TYP: FUNCTION**
    
Schreibe eine Funktion `wuerfel(x)` die x³ berechnet.',
    
    'code_template' => 'def wuerfel(x):
    return x ** ___',
    
    'test_cases' => json_encode([
        [
            'type' => 'function',
            'function_name' => 'wuerfel',
            'args' => [2],
            'expected' => 8
        ],
        [
            'type' => 'function',
            'function_name' => 'wuerfel',
            'args' => [3],
            'expected' => 27
        ],
        [
            'type' => 'function',
            'function_name' => 'wuerfel',
            'args' => [5],
            'expected' => 125
        ]
    ]),
    
    'validation_mode' => 'strict',
    'max_attempts' => 10
]
```

---

## Wichtige Regeln

✅ **DO:**
- Type explizit angeben
- Mehrere Tests pro Task
- Edge Cases einbeziehen
- Konsistente Typen in einem Task

❌ **DON'T:**
- Typen mischen in einem Task
- Zu komplexe Datenstrukturen
- Zu viele Tests (>10)
- Type weglassen bei neuen Tasks

---

## Siehe auch:

- `docs/test-types.md` - Ausführliche Dokumentation
- `scripts/create_test_type_examples.php` - Vollständige Beispiele
- `scripts/verify_test_types.php` - Struktur-Verifikation
