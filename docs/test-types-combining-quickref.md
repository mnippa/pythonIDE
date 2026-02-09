# Test-Typen Kombination - Quick Demo

## Proof of Concept

Die 3 Test-Typen können **beliebig kombiniert** werden!

```python
# Student-Code
def verdoppeln(x):
    return x * 2

result = verdoppeln(5)
print(f"Ergebnis: {result}")
```

## Test-Cases (alle 3 Typen!)

```json
[
  {
    "type": "function",
    "function_name": "verdoppeln",
    "args": [7],
    "expected": 14
  },
  {
    "type": "variable",
    "init_vars": {"x": 10},
    "expected_vars": {"result": 20}
  },
  {
    "type": "output",
    "expected": ["Ergebnis: 20"]
  }
]
```

## Was wird getestet?

1. ✅ **FUNCTION**: `verdoppeln(7) → 14` (Funktion korrekt?)
2. ✅ **VARIABLE**: `x=10 → result=20` (Nutzung korrekt?)
3. ✅ **OUTPUT**: `"Ergebnis: 20"` (Ausgabe korrekt?)

## System-Verhalten

**assignments.js** führt jeden Test nacheinander aus:

```javascript
for (const test of testCases) {
  const type = test.type; // 'function', 'variable', oder 'output'
  
  switch (type) {
    case 'function':
      // Rufe Funktion mit args auf, vergleiche mit expected
      break;
    case 'variable':
      // Setze init_vars, führe Code aus, prüfe expected_vars
      break;
    case 'output':
      // Führe Code aus, fange stdout, vergleiche mit expected
      break;
  }
}
```

## Feedback für Studenten

**Szenario 1: Alle Tests bestanden**
```
✅ Test 1 (FUNCTION): verdoppeln(7) → 14
✅ Test 2 (VARIABLE): result = 20
✅ Test 3 (OUTPUT): "Ergebnis: 20"

🎉 Alle Tests bestanden!
```

**Szenario 2: Granulares Feedback**
```
✅ Test 1 (FUNCTION): verdoppeln(7) → 14
❌ Test 2 (VARIABLE): result = 19 (erwartet: 20)
❌ Test 3 (OUTPUT): "Ergebnis 19" (erwartet: "Ergebnis: 20")

→ Funktion korrekt, aber falsch genutzt!
```

## Vorteile

| Aspekt | Einzelner Typ | Kombiniert |
|--------|--------------|------------|
| **Genauigkeit** | Grob | Präzise |
| **Feedback** | "Test failed" | "Funktion ok, Variable falsch" |
| **Realismus** | Isoliert | Wie echter Code |
| **Debugging** | Schwierig | Einfach |

## Beispiele

### Einfach (1 Typ)
```python
print("Hello")
```
Test: `{"type": "output", "expected": ["Hello"]}`

### Mittel (2 Typen)
```python
def add(a, b):
    return a + b

result = add(5, 10)
```
Tests:
- `{"type": "function", ...}` → Funktion
- `{"type": "variable", ...}` → Variable

### Komplex (3 Typen)
```python
def berechne(x):
    return x * 2

wert = berechne(10)
print(f"Ergebnis: {wert}")
```
Tests:
- `{"type": "function", ...}` → Funktion
- `{"type": "variable", ...}` → Variable
- `{"type": "output", ...}` → Output

## Fazit

✅ **Flexibel** - Jede Kombination möglich
✅ **Granular** - Genau sehen wo Fehler sind
✅ **Realistisch** - Wie echter Code funktioniert
✅ **Einfach** - System erkennt Typ automatisch

**Die Kombination macht das Testing präzise und umfassend!**
