# Test-Typen kombinieren

## Übersicht

Die 3 Test-Typen (OUTPUT, FUNCTION, VARIABLE) können **flexibel kombiniert** werden für umfassende Code-Validierung.

## Kombinations-Möglichkeiten

### 1. Verschiedene Typen in einem Assignment

Ein Assignment kann beliebig viele Tasks mit unterschiedlichen Test-Typen enthalten:

```
Assignment: "Python Grundlagen"
├── Task 1: Begrüßung (OUTPUT)
├── Task 2: Addition (FUNCTION)
├── Task 3: Quadrat (VARIABLE)
├── Task 4: Komplexe Berechnung (FUNCTION + VARIABLE + OUTPUT)
└── Task 5: Listen-Verarbeitung (FUNCTION + VARIABLE)
```

**Vorteil:** Verschiedene Konzepte mit jeweils passendem Test-Typ validieren.

### 2. Mehrere Tests pro Task

Eine einzelne Task kann mehrere Test-Cases unterschiedlicher Typen haben:

```json
{
  "test_cases": [
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
}
```

**Vorteil:** Umfassende Validierung eines Code-Blocks.

## Praktische Beispiele

### Beispiel 1: Funktion + Nutzung + Output

**Aufgabe:** Implementieren Sie `verdoppeln(x)` und nutzen Sie die Funktion.

**Student-Code:**
```python
#INIT Start#
x = 5
#INIT End#

def verdoppeln(x):
    return x * 2

result = verdoppeln(x)
print(f"Ergebnis: {result}")
```

**Test-Cases:**
```json
[
  {
    "type": "function",
    "function_name": "verdoppeln",
    "args": [7],
    "expected": 14,
    "description": "Testet ob Funktion korrekt"
  },
  {
    "type": "variable",
    "init_vars": {"x": 10},
    "expected_vars": {"result": 20},
    "description": "Testet ob Funktion richtig genutzt wird"
  },
  {
    "type": "output",
    "expected": ["Ergebnis: 10", "Ergebnis: 20"],
    "description": "Testet ob Output korrekt formatiert"
  }
]
```

**Was wird getestet:**
1. ✅ **FUNCTION**: Ist die Logik korrekt? (`verdoppeln(7) → 14`)
2. ✅ **VARIABLE**: Wird sie richtig verwendet? (`x=10 → result=20`)
3. ✅ **OUTPUT**: Stimmt die Ausgabe? (`"Ergebnis: ..."`)`

### Beispiel 2: Mehrere Funktionen validieren

**Aufgabe:** Kreis-Berechnungen (Fläche + Umfang)

**Student-Code:**
```python
import math

#INIT Start#
radius = 5
#INIT End#

def flaeche(r):
    return math.pi * r ** 2

def umfang(r):
    return 2 * math.pi * r

kreis_flaeche = flaeche(radius)
kreis_umfang = umfang(radius)

print(f"Fläche: {kreis_flaeche:.2f}")
print(f"Umfang: {kreis_umfang:.2f}")
```

**Test-Cases:**
```json
[
  {
    "type": "function",
    "function_name": "flaeche",
    "args": [5],
    "expected": 78.54
  },
  {
    "type": "function",
    "function_name": "flaeche",
    "args": [10],
    "expected": 314.16
  },
  {
    "type": "function",
    "function_name": "umfang",
    "args": [5],
    "expected": 31.42
  },
  {
    "type": "variable",
    "init_vars": {"radius": 5},
    "expected_vars": {
      "kreis_flaeche": 78.54,
      "kreis_umfang": 31.42
    }
  },
  {
    "type": "output",
    "expected": ["Fläche: 78.54", "Umfang: 31.42"]
  }
]
```

**Was wird getestet:**
1. ✅ **FUNCTION** (3x): Beide Funktionen mit verschiedenen Werten
2. ✅ **VARIABLE** (1x): Werden beide Variablen korrekt berechnet?
3. ✅ **OUTPUT** (1x): Wird korrekt formatiert ausgegeben?

### Beispiel 3: Listen-Verarbeitung

**Aufgabe:** Filtere gerade Zahlen und gib Anzahl aus

**Student-Code:**
```python
#INIT Start#
zahlen = [1, 2, 3, 4, 5]
#INIT End#

def ist_gerade(n):
    return n % 2 == 0

gerade = [x for x in zahlen if ist_gerade(x)]
anzahl = len(gerade)

print(f"Es gibt {anzahl} gerade Zahlen")
```

**Test-Cases:**
```json
[
  {
    "type": "function",
    "function_name": "ist_gerade",
    "args": [4],
    "expected": true
  },
  {
    "type": "function",
    "function_name": "ist_gerade",
    "args": [5],
    "expected": false
  },
  {
    "type": "variable",
    "init_vars": {"zahlen": [1, 2, 3, 4, 5]},
    "expected_vars": {
      "gerade": [2, 4],
      "anzahl": 2
    }
  },
  {
    "type": "output",
    "expected": ["Es gibt 2 gerade Zahlen"]
  }
]
```

## Vorteile der Kombination

### 1. Umfassende Validierung
- **Problem:** Ein Test-Typ allein deckt nicht alles ab
- **Lösung:** Kombiniere mehrere Typen
- **Ergebnis:** Funktions-Logik, Nutzung UND Output werden geprüft

### 2. Präzises Feedback
- **Ohne Kombination:** "Test fehlgeschlagen" 😕
- **Mit Kombination:** "Funktion korrekt ✅, Variable falsch ❌, Output nicht getestet"
- **Vorteil:** Student weiß genau wo der Fehler ist

### 3. Realistische Szenarien
- **Isolierte Tests:** Nur Funktion ODER nur Variable
- **Kombinierte Tests:** Funktion definieren → nutzen → ausgeben
- **Wie in der Praxis:** Code wird nicht isoliert geschrieben

### 4. Granulare Bewertung
```
FUNCTION-Test 1: ✅ verdoppeln(7) → 14
FUNCTION-Test 2: ✅ verdoppeln(10) → 20
VARIABLE-Test:   ❌ result = 19 (erwartet: 20)
OUTPUT-Test:     ❌ "Ergebnis 19" (erwartet: "Ergebnis: 20")

→ Funktion korrekt, aber falsch genutzt!
```

## Best Practices

### Wann welche Kombination?

#### Einfache Aufgaben → 1 Test-Typ
```python
# Nur Output testen
print("Hello World")
```
Test: `{"type": "output", "expected": ["Hello World"]}`

#### Mittlere Aufgaben → 2 Test-Typen
```python
# Funktion + Nutzung
def quadrat(x):
    return x * x

result = quadrat(5)
```
Tests:
- `{"type": "function", ...}` → Funktion korrekt?
- `{"type": "variable", ...}` → Variable korrekt?

#### Komplexe Aufgaben → 3 Test-Typen
```python
# Vollständiger Workflow
def berechne():
    ...
    return ergebnis

wert = berechne()
print(f"Ergebnis: {wert}")
```
Tests:
- `{"type": "function", ...}` → Funktion
- `{"type": "variable", ...}` → Variable
- `{"type": "output", ...}` → Output

### Empfohlene Reihenfolge

**1. FUNCTION zuerst**
- Testet isolierte Logik
- Funktioniert unabhängig von Rest

**2. VARIABLE danach**  
- Testet Nutzung der Funktion
- Setzt korrekte Funktion voraus

**3. OUTPUT zuletzt**
- Testet finale Ausgabe
- Setzt korrekte Variable voraus

**Vorteil:** Logische Fehler-Hierarchie
- Funktion falsch → alle anderen Tests sinnlos
- Funktion ok, Variable falsch → Output zeigt falschen Wert
- Alle ok → Volle Punktzahl

## PHP Task-Erstellung

### Template für kombinierte Tests

```php
$task = [
    'title' => 'Funktion + Nutzung + Output',
    'code_template' => '#INIT Start#
x = 5
#INIT End#

def verdoppeln(x):
    return x * ___

result = verdoppeln(x)
print(f"Ergebnis: {___}")',
    'test_cases' => json_encode([
        // Funktion testen
        [
            'type' => 'function',
            'function_name' => 'verdoppeln',
            'args' => [7],
            'expected' => 14
        ],
        // Variable testen
        [
            'type' => 'variable',
            'init_vars' => ['x' => 10],
            'expected_vars' => ['result' => 20]
        ],
        // Output testen
        [
            'type' => 'output',
            'expected' => ['Ergebnis: 10', 'Ergebnis: 20']
        ]
    ])
];
```

## Technische Umsetzung

### JavaScript (assignments.js)

```javascript
async function runTests(pyodide, code, testCases, mode) {
  const results = [];
  
  for (const test of testCases) {
    const type = detectTestType([test]);
    
    let result;
    switch (type) {
      case 'output':
        result = await runOutputTests(pyodide, code, [test], mode);
        break;
      case 'function':
        result = await runFunctionTests(pyodide, code, [test], mode);
        break;
      case 'variable':
        result = await runVariableTests(pyodide, code, [test], mode);
        break;
    }
    
    results.push(result[0]);
  }
  
  return results;
}
```

**Verhalten:**
- Jeder Test wird einzeln ausgeführt
- Typ wird pro Test erkannt
- Ergebnisse werden aggregiert
- UI zeigt alle Ergebnisse an

## Häufige Muster

### Pattern 1: "Implement & Use"
```
FUNCTION: Implementiere Funktion
VARIABLE: Nutze Funktion
```

### Pattern 2: "Calculate & Display"
```
VARIABLE: Berechne Werte
OUTPUT: Gib formatiert aus
```

### Pattern 3: "Full Workflow"
```
FUNCTION: Definiere Logik
VARIABLE: Nutze Logik
OUTPUT: Präsentiere Ergebnis
```

## Zusammenfassung

✅ **Flexibel kombinierbar** - Jede Kombination möglich
✅ **Granulares Feedback** - Genau sehen wo Fehler sind
✅ **Realistische Tests** - Wie echter Code funktioniert
✅ **Best Practice** - Komplexität steigend: 1 → 2 → 3 Typen

**Die Kombination von Test-Typen macht das Testing präzise, umfassend und praxisnah!**
