# Test-Typen - Strukturierte Tests für Python Code

## Übersicht

Das Python IDE unterstützt **3 verschiedene Test-Typen**, die klar strukturiert sind:

1. **OUTPUT** - Prüft die Programmausgabe
2. **FUNCTION** - Testet Funktionen mit Argumenten
3. **VARIABLE** - Prüft Variablenwerte nach Code-Ausführung

---

## 1. OUTPUT-Testing

**Wann verwenden:** Code der direkt `print()` nutzt und Ausgaben produziert

### Format:
```json
{
  "type": "output",
  "input": "",
  "expected": "Hallo Welt"
}
```

### Mit mehreren akzeptierten Patterns:
```json
{
  "type": "output",
  "input": "",
  "expected": [
    "Ich bin Max und 25 Jahre alt.",
    "Ich bin Max und 25 Jahre alt"
  ]
}
```

### Beispiel Task:
```python
# Code-Template:
name = "Max"
alter = 25
print(f"Ich bin {name} und {alter} Jahre alt")

# Test Case:
{
  "type": "output",
  "input": "",
  "expected": [
    "Ich bin Max und 25 Jahre alt.",
    "Ich bin Max und 25 Jahre alt"
  ]
}
```

**Verhalten:**
- Code wird komplett ausgeführt
- Alle print()-Ausgaben werden erfasst
- Mit `expected` verglichen (String oder Array)
- Bei Array: EINE Variante muss matchen

---

## 2. FUNCTION-Testing

**Wann verwenden:** Funktionen mit klaren Inputs und Return-Werten

### Format (einzelner Argument):
```json
{
  "type": "function",
  "function_name": "quadrat",
  "args": [5],
  "expected": 25
}
```

### Format (mehrere Argumente):
```json
{
  "type": "function",
  "function_name": "im_bereich",
  "args": [5, 1, 10],
  "expected": true
}
```

### Format (String-Arguments):
```json
{
  "type": "function",
  "function_name": "umkehren",
  "args": ["Hallo"],
  "expected": "ollaH"
}
```

### Beispiel Task:
```python
# Code-Template:
def quadrat(x):
    return x * ___

# Test Cases:
[
  {
    "type": "function",
    "function_name": "quadrat",
    "args": [5],
    "expected": 25
  },
  {
    "type": "function",
    "function_name": "quadrat",
    "args": [10],
    "expected": 100
  },
  {
    "type": "function",
    "function_name": "quadrat",
    "args": [-3],
    "expected": 9
  }
]
```

**Verhalten:**
- Code wird ausgeführt (Funktionsdefinition)
- Für jeden Test: `function_name(*args)` wird aufgerufen
- Return-Wert wird mit `expected` verglichen
- Klare Struktur: Welche Funktion, welche Args, welches Ergebnis

**Vorteile:**
✅ Funktionsname explizit → kein Raten
✅ Args als Array → klare Typen
✅ Mehrere Funktionen testbar (nicht nur erste)
✅ Bessere Fehlerbehandlung

---

## 3. VARIABLE-Testing

**Wann verwenden:** Code der mit Variablen arbeitet und Berechnungen durchführt

### Format (einzelne Variable):
```json
{
  "type": "variable",
  "init_vars": {"x": 5},
  "expected_vars": {"ergebnis": 25}
}
```

### Format (mehrere Input-Variablen):
```json
{
  "type": "variable",
  "init_vars": {"a": 10, "b": 20},
  "expected_vars": {"summe": 30, "produkt": 200}
}
```

### Format (ohne Init-Variablen):
```json
{
  "type": "variable",
  "init_vars": {},
  "expected_vars": {"pi": 3.14159, "e": 2.71828}
}
```

### Beispiel Task:
```python
# Code-Template:
# Berechne Summe und Produkt
summe = a + b
produkt = a * b

# Test Cases:
[
  {
    "type": "variable",
    "init_vars": {"a": 5, "b": 10},
    "expected_vars": {"summe": 15, "produkt": 50}
  },
  {
    "type": "variable",
    "init_vars": {"a": 3, "b": 7},
    "expected_vars": {"summe": 10, "produkt": 21}
  },
  {
    "type": "variable",
    "init_vars": {"a": 0, "b": 100},
    "expected_vars": {"summe": 100, "produkt": 0}
  }
]
```

**Verhalten:**
- Für jeden Test: `init_vars` werden VOR Code-Ausführung gesetzt
- User-Code wird ausgeführt
- Nach Ausführung: Werte der `expected_vars` werden geprüft
- Alle erwarteten Variablen müssen korrekt sein

**Use Cases:**
- Berechnungen (Summe, Produkt, Durchschnitt)
- Algorithmen (Sortierung in `sorted_list`)
- Zustandsänderungen (Counter, Flags)

**Beispiele:**

### Pythagoras:
```python
# Template:
# Berechne Hypothenuse
c = (a**2 + b**2) ** 0.5

# Tests:
{
  "type": "variable",
  "init_vars": {"a": 3, "b": 4},
  "expected_vars": {"c": 5.0}
}
```

### Temperatur-Umrechnung:
```python
# Template:
# Celsius zu Fahrenheit
fahrenheit = celsius * 9/5 + 32

# Tests:
{
  "type": "variable",
  "init_vars": {"celsius": 0},
  "expected_vars": {"fahrenheit": 32.0}
}
```

### List-Verarbeitung:
```python
# Template:
# Filtere gerade Zahlen
gerade = [x for x in zahlen if x % 2 == 0]

# Tests:
{
  "type": "variable",
  "init_vars": {"zahlen": [1, 2, 3, 4, 5]},
  "expected_vars": {"gerade": [2, 4]}
}
```

---

## Vergleichstabelle

| Feature | OUTPUT | FUNCTION | VARIABLE |
|---------|--------|----------|----------|
| **Input-Art** | Keine | Funktions-Args | Init-Variablen |
| **Output-Art** | Print-Ausgabe | Return-Wert | Variable-Werte |
| **Mehrere Tests** | ✅ | ✅ | ✅ |
| **Mehrere Expected** | ✅ (Array) | ✅ (Array) | ❌ (exakt) |
| **Use Case** | Programme | Funktionen | Berechnungen |
| **Granularität** | Gesamtausgabe | Pro Aufruf | Pro Variablen-Set |

---

## Migration von Legacy zu neuen Typen

### Alt (implizit):
```json
{"input": "", "expected": "25"}
```
❌ Unklar: Output oder Funktion?

### Neu (explizit):
```json
// Output:
{"type": "output", "input": "", "expected": "25"}

// Function:
{"type": "function", "function_name": "get_result", "args": [], "expected": 25}

// Variable:
{"type": "variable", "init_vars": {}, "expected_vars": {"result": 25}}
```

✅ Kristallklar was getestet wird!

---

## Kombination von Test-Typen

**Erlaubt:** Mehrere Tests des **gleichen Typs** in einem Task

```json
[
  {"type": "function", "function_name": "add", "args": [1, 2], "expected": 3},
  {"type": "function", "function_name": "add", "args": [5, 5], "expected": 10}
]
```

**Nicht empfohlen:** Verschiedene Test-Typen mischen

```json
[
  {"type": "output", ...},
  {"type": "function", ...}  // ❌ Verwirrend
]
```

---

## Backward Compatibility

**Legacy Format (ohne `type`):**
```json
{"input": "5", "expected": "25"}
```

**Wird automatisch erkannt als:**
- `type: "output"` wenn `input` leer
- `type: "function"` wenn `input` nicht leer (Auto-Detection)

**Empfehlung:** Neue Tasks immer mit explizitem `type` erstellen!

---

## JSON-Schema

```json
{
  "OUTPUT": {
    "type": "output",
    "input": "",
    "expected": "string oder array"
  },
  
  "FUNCTION": {
    "type": "function",
    "function_name": "funktionsname",
    "args": [arg1, arg2, ...],
    "expected": "erwarteter return-wert"
  },
  
  "VARIABLE": {
    "type": "variable",
    "init_vars": {
      "var1": wert1,
      "var2": wert2
    },
    "expected_vars": {
      "result_var1": erwarteter_wert1,
      "result_var2": erwarteter_wert2
    }
  }
}
```

---

## Best Practices

### ✅ DO

- **Type explizit angeben** bei neuen Tasks
- **Konsistenz** innerhalb eines Tasks (alle Tests gleicher Typ)
- **Aussagekräftige Namen** bei Function/Variable-Tests
- **Edge Cases testen** (0, negative, leere Listen)
- **Mehrere Expected Patterns** bei Output wenn sinnvoll

### ❌ DON'T

- **Typen mischen** in einem Task
- **Komplexe Objekte** als Expected (noch nicht unterstützt)
- **Zu viele Tests** (max 10 pro Task)
- **Unpräzise Variable-Namen** (x, y statt summe, produkt)

---

## Beispiele siehe:

- `scripts/create_test_type_examples.php` - Erstellt Beispiele für alle 3 Typen
- `scripts/verify_test_types.php` - Zeigt Test-Struktur an
