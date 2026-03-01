# Input-Testing Best Practices für Aufgabenersteller

> **Version**: 1.0 | **Stand**: März 2026 | **Autor**: pythonIDE Team

---

## 📋 Überblick

Seit Phase 2.1 können Studierende `input()`-basierte Programme schreiben. Dieses Dokument beschreibt Best Practices für das Erstellen und Testen solcher Aufgaben.

---

## ✅ Was funktioniert

### Basic Input
```python
name = input("Wie heißt du? ")
print(f"Hallo, {name}!")
```

### Type Conversion
```python
age = int(input("Alter: "))
height = float(input("Größe in m: "))
```

### Multiple Inputs
```python
for i in range(3):
    value = input(f"Wert {i+1}: ")
    print(f"Eingabe war: {value}")
```

### Conditional Input
```python
choice = input("Weiter? (j/n) ")
if choice.lower() == 'j':
    print("OK, weiter...")
```

---

## 🧪 Test-Strategien

### 1. Output-basierte Tests (Empfohlen)

**Prinzip**: Prüfe die Ausgabe für gegebene Eingaben

**Beispiel-Aufgabe**: *"Schreibe ein Programm, das nach dem Namen fragt und eine Begrüßung ausgibt"*

**Test Case**:
```json
{
  "type": "output",
  "description": "Begrüßung mit Namen 'Max'",
  "inputs": ["Max"],
  "expected_output": "Hallo, Max!",
  "mode": "contains"
}
```

**Code-Check**:
```json
{
  "type": "code_check",
  "description": "input() wird verwendet",
  "pattern": "input\\s*\\(",
  "required": true
}
```

### 2. Keyword-Check

Prüfe, ob Studierende tatsächlich `input()` verwenden:

```json
{
  "type": "code_check",
  "description": "Verwendet input() Funktion",
  "pattern": "input\\s*\\([\"'].*[\"']\\)",
  "required": true,
  "hint": "Nutze input() um Benutzereingaben zu erhalten"
}
```

### 3. Type-Conversion Check

Stelle sicher, dass korrekte Datentypen verwendet werden:

```json
{
  "type": "code_check",
  "description": "Verwendet int() für Zahlen-Konvertierung",
  "pattern": "int\\s*\\(\\s*input",
  "required": true
}
```

### 4. Function-basierte Tests (Fortgeschritten)

**Wenn möglich**: Frage nach Funktionen statt direkter Ausführung

```python
# Aufgabenstellung:
# "Schreibe eine Funktion greet(name), die einen Namen entgegennimmt
#  und 'Hallo, {name}!' zurückgibt"

def greet(name):
    return f"Hallo, {name}!"

# Testbar ohne input() im Test
```

**Test Case**:
```json
{
  "type": "function",
  "function_name": "greet",
  "inputs": [["Max"], ["Anna"]],
  "expected_outputs": ["Hallo, Max!", "Hallo, Anna!"]
}
```

---

## 📚 Beispiel-Aufgaben

### Aufgabe 1: Einfache Begrüßung

**Titel**: "Persönliche Begrüßung"

**Beschreibung**:
```
Schreibe ein Programm, das nach deinem Namen fragt und dich begrüßt.

Beispiel:
Input: Max
Output: Hallo, Max!
```

**Test Cases**:
```json
[
  {
    "type": "code_check",
    "description": "input() wird verwendet",
    "pattern": "input\\s*\\(",
    "required": true
  },
  {
    "type": "output",
    "description": "Begrüßung mit Namen",
    "mock_inputs": ["Anna"],
    "expected_contains": ["Hallo", "Anna"]
  }
]
```

### Aufgabe 2: Altersberechnung

**Titel**: "Alter berechnen"

**Beschreibung**:
```
Frage nach dem Geburtsjahr und berechne das aktuelle Alter.

Beispiel:
Input: 2000
Output: Du bist 26 Jahre alt.
```

**Test Cases**:
```json
[
  {
    "type": "code_check",
    "description": "int() Konvertierung wird verwendet",
    "pattern": "int\\s*\\(\\s*input",
    "required": true
  },
  {
    "type": "output",
    "description": "Berechnung für Geburtsjahr 2000",
    "mock_inputs": ["2000"],
    "expected_contains": ["26"]
  }
]
```

### Aufgabe 3: Temperatur-Umrechner

**Titel**: "Celsius zu Fahrenheit"

**Beschreibung**:
```
Frage nach einer Temperatur in Celsius und rechne sie in Fahrenheit um.
Formel: F = C * 9/5 + 32

Beispiel:
Input: 20
Output: 20°C = 68.0°F
```

**Test Cases**:
```json
[
  {
    "type": "output",
    "description": "0°C = 32°F",
    "mock_inputs": ["0"],
    "expected_contains": ["32"]
  },
  {
    "type": "output",
    "description": "100°C = 212°F",
    "mock_inputs": ["100"],
    "expected_contains": ["212"]
  }
]
```

### Aufgabe 4: Multiple Inputs

**Titel**: "Zwei Zahlen addieren"

**Beschreibung**:
```
Frage nach zwei Zahlen und gib ihre Summe aus.

Beispiel:
Input: 5
Input: 7
Output: Die Summe ist 12
```

**Test Cases**:
```json
[
  {
    "type": "output",
    "description": "5 + 7 = 12",
    "mock_inputs": ["5", "7"],
    "expected_contains": ["12"]
  }
]
```

---

## ⚠️ Wichtige Hinweise

### 1. Input-Mocking (Noch nicht implementiert)

**Aktueller Status**: Tests müssen manuell durchgeführt werden

**Roadmap**: Input-Mocking für automatische Tests folgt in Phase 2.2b

### 2. Prompt-Text ist optional

Studierende können leere Prompts verwenden:
```python
name = input()  # Funktioniert, aber weniger benutzerfreundlich
```

Empfehlung: **Immer** aussagekräftige Prompts verlangen mittels Code-Check.

### 3. Error-Handling

Studierende sollten `try/except` für Type-Conversion lernen:
```python
try:
    age = int(input("Alter: "))
except ValueError:
    print("Bitte eine Zahl eingeben!")
```

### 4. Loop-basierte Inputs

Bei Schleifen mit `input()`:
- Stelle sicher, dass genug Test-Inputs bereitgestellt werden
- Dokumentiere die erwartete Anzahl an Eingaben

---

## 🚀 Testing Workflow

### Schritt 1: Aufgabe erstellen
- Definiere klare Eingabe/Ausgabe-Beispiele
- Gib Format-Anforderungen vor

### Schritt 2: Code-Checks definieren
- `input()` vorhanden?
- Korrekte Type-Conversion?
- Aussagekräftiger Prompt?

### Schritt 3: Output-Tests erstellen
- Mindestens 2-3 verschiedene Inputs testen
- Edge-Cases berücksichtigen (0, negative Zahlen, etc.)

### Schritt 4: Manuelles Testing
- Aufgabe selbst durchspielen
- Verschiedene Eingaben ausprobieren
- Fehlermeldungen prüfen

---

## 📖 Weitere Ressourcen

- [Test Types Documentation](test-types-documentation-v2.md)
- [Task Export/Import](taskexport.md)
- [Beispiel-Aufgaben](../test_input_examples.py)

---

## 🔮 Zukunft

### Phase 2.2b (geplant): Automatisches Input-Mocking
- Test-Framework mit vordefinierten Inputs
- Automatische Output-Validierung
- Keine manuellen Tests mehr nötig

### Phase 2.3: UI-Elemente
- Grafische Eingabe-Elemente (Buttons, Sliders)
- HTML-basierte Interfaces
- Interaktive Anwendungen

---

**Feedback & Fragen**: [GitHub Issues](https://github.com/yourrepo/pythonIDE/issues)
