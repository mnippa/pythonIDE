# idegui - Python-HTML Programmierung Guide für KIs

## Übersicht

**idegui** ist ein Framework zur Entwicklung von Python-basierten Webanwendungen, bei dem Python-Code im Browser via Pyodide ausgeführt wird und direkt mit HTML-Elementen interagiert.

## Architektur

### Grundprinzip
- **HTML** definiert die Benutzeroberfläche (GUI)
- **CSS** gestaltet das Aussehen
- **Python (init.py)** enthält die gesamte Programmlogik
- **idegui-Modul** stellt die Brücke zwischen Python und HTML dar
- **Kein JavaScript** im HTML erlaubt (außer Framework-intern)

### Ablauf
1. Benutzer öffnet Projekt
2. HTML wird gerendert (einmal beim ersten RUN)
3. Python-Code (init.py) wird via Pyodide im Browser ausgeführt
4. Python kann HTML-Elemente lesen/schreiben via `ui.get()` und `ui.set()`
5. Buttons mit Event-Handler rufen Python-Funktionen auf

## Zwei Logik-Arten

### 1. Direkte Python-Logik (Einmalige Ausführung)

**Konzept:** Python-Code wird beim Klick auf "RUN" ausgeführt, führt Berechnungen durch und schreibt Ergebnisse direkt ins HTML.

**Usecase:** Berechnungen, Datenverarbeitung, einfache Ausgaben

**Beispiel: Taschenrechner**

```python
# init.py
import idegui as ui

# Code wird bei RUN ausgeführt
zahl1 = 42
zahl2 = 17
ergebnis = zahl1 + zahl2

# Ergebnisse ins HTML schreiben
ui.set('zahl1', str(zahl1))
ui.set('zahl2', str(zahl2))
ui.set('ergebnis', str(ergebnis))
ui.set('nachricht', f'{zahl1} + {zahl2} = {ergebnis}')
```

```html
<!-- index.html -->
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Taschenrechner</title>
</head>
<body>
    <div class="container">
        <h1>Taschenrechner</h1>
        <p>Zahl 1: <span data-element="zahl1">-</span></p>
        <p>Zahl 2: <span data-element="zahl2">-</span></p>
        <p>Ergebnis: <span data-element="ergebnis">-</span></p>
        <p class="message" data-element="nachricht">Warte...</p>
    </div>
</body>
</html>
```

**Wichtig:**
- HTML-Elemente benötigen `data-element="name"` Attribut
- `ui.set('name', 'value')` schreibt `value` in das Element mit `data-element="name"`
- Code läuft einmalig von oben nach unten durch

### 2. Event-Handler-Logik (Interaktive Anwendungen)

**Konzept:** Python-Funktionen werden bei Button-Klicks aufgerufen. Funktionen lesen Input-Werte, verarbeiten sie und schreiben Ergebnisse zurück.

**Usecase:** Interaktive Apps, Spiele, Formulare

**Beispiel: Interaktiver Taschenrechner**

```python
# init.py
import idegui as ui

def berechnen(trigger):
    """Wird bei Klick auf Berechnen-Button aufgerufen"""
    # Werte aus HTML-Inputs lesen
    zahl1_str = ui.get('input1')
    zahl2_str = ui.get('input2')
    
    # Validierung und Berechnung
    try:
        zahl1 = float(zahl1_str) if zahl1_str else 0
        zahl2 = float(zahl2_str) if zahl2_str else 0
        ergebnis = zahl1 + zahl2
        
        # Ergebnis zurückschreiben
        ui.set('ergebnis', str(ergebnis))
        ui.set('nachricht', f'✓ {zahl1} + {zahl2} = {ergebnis}')
    except ValueError:
        ui.set('nachricht', '❌ Ungültige Eingabe!')

def reset(trigger):
    """Wird bei Klick auf Reset-Button aufgerufen"""
    ui.set('input1', '')
    ui.set('input2', '')
    ui.set('ergebnis', '0')
    ui.set('nachricht', 'Zurückgesetzt.')

# Initialisierung beim Start
ui.set('ergebnis', '0')
ui.set('nachricht', 'Gib Zahlen ein.')
```

```html
<!-- index.html -->
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Interaktiver Taschenrechner</title>
</head>
<body>
    <div class="container">
        <h1>Rechner</h1>
        
        <input type="number" data-element="input1" placeholder="Zahl 1">
        <input type="number" data-element="input2" placeholder="Zahl 2">
        
        <button data-run-python="true" data-run-name="berechnen">
            Berechnen
        </button>
        <button data-run-python="true" data-run-name="reset">
            Reset
        </button>
        
        <div class="result">
            <strong>Ergebnis:</strong> <span data-element="ergebnis">0</span>
        </div>
        <p data-element="nachricht">Gib Zahlen ein.</p>
    </div>
</body>
</html>
```

**Wichtig:**
- Button benötigt `data-run-python="true"` und `data-run-name="funktionsname"`
- Python-Funktion muss `trigger` Parameter haben (wird automatisch übergeben)
- `ui.get('name')` liest den `.value` von `<input data-element="name">`
- `ui.get('name')` liest `.textContent` von `<span data-element="name">`
- Funktionen können mehrfach aufgerufen werden (jeder Button-Klick)

## idegui API-Referenz

### ui.get(element_name)
Liest Wert eines HTML-Elements mit `data-element="element_name"`.

```python
wert = ui.get('eingabe')  # Liest value von <input data-element="eingabe">
```

**Rückgabe:**
- Bei `<input>`, `<textarea>`, `<select>`: `.value` Attribut
- Bei anderen Elementen: `.textContent`

### ui.set(element_name, value)
Schreibt Wert in HTML-Element mit `data-element="element_name"`.

```python
ui.set('ausgabe', 'Hallo Welt')  # Schreibt in <div data-element="ausgabe">
ui.set('eingabe', '42')          # Setzt value von <input data-element="eingabe">
```

**Verhalten:**
- Bei `<input>`, `<textarea>`, `<select>`: Setzt `.value`
- Bei anderen Elementen: Setzt `.textContent`

### Event-Handler Funktionen

**Signatur:** Alle Event-Handler benötigen `trigger` Parameter.

```python
def meine_funktion(trigger):
    # trigger enthält Informationen zum ausgelösten Event
    # trigger.name = Name der Funktion
    # trigger.value = Wert (falls vorhanden)
    pass
```

**HTML-Binding:**
```html
<button data-run-python="true" data-run-name="meine_funktion">
    Klick mich
</button>
```

## State Management

### Globale Variablen
Python-Code hat persistenten globalen Zustand zwischen Event-Handler-Aufrufen.

```python
# init.py
import idegui as ui

# Globale Variable für Spielzustand
if 'GAME_STATE' not in globals():
    GAME_STATE = {
        'score': 0,
        'level': 1
    }

def increase_score(trigger):
    global GAME_STATE
    GAME_STATE['score'] += 10
    ui.set('score', str(GAME_STATE['score']))

def reset_game(trigger):
    global GAME_STATE
    GAME_STATE = {'score': 0, 'level': 1}
    ui.set('score', '0')
    ui.set('level', '1')
```

**Wichtig:**
- Variablen außerhalb von Funktionen sind global persistent
- Bei Event-Handler-Logik: Zustand in dict speichern
- `if 'VAR' not in globals()` prüft ob Variable bereits existiert (wichtig bei Re-Run)

## Best Practices

### 1. HTML-Struktur
```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projekttitel</title>
</head>
<body>
    <div class="container">
        <!-- Hauptinhalt -->
        <h1>Titel</h1>
        
        <!-- Eingaben mit data-element -->
        <input type="text" data-element="name_input" placeholder="Name">
        
        <!-- Ausgaben mit data-element -->
        <p data-element="output">Ausgabe hier</p>
        
        <!-- Buttons für Event-Handler -->
        <button data-run-python="true" data-run-name="submit">
            Absenden
        </button>
    </div>
</body>
</html>
```

### 2. CSS-Styling
Nutze moderne CSS-Features für ansprechendes Design:

```css
body {
    font-family: system-ui, -apple-system, sans-serif;
    margin: 0;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

button {
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: transform 0.2s;
}

button:hover {
    transform: scale(1.05);
}

[data-element] {
    /* Styling für dynamische Elemente */
    color: #667eea;
    font-weight: 600;
}
```

### 3. Python-Code-Struktur

**Für Event-Handler-Logik:**

```python
import idegui as ui
import random  # Erlaubte Module

# Konstanten
KONSTANTE = 100

# Hilfsfunktionen (ohne trigger Parameter)
def berechne_etwas(x, y):
    return x * y + KONSTANTE

# State-Initialisierung
if 'APP_STATE' not in globals():
    APP_STATE = {
        'counter': 0,
        'data': []
    }

# Event-Handler (mit trigger Parameter)
def button_clicked(trigger):
    global APP_STATE
    result = berechne_etwas(5, 10)
    APP_STATE['counter'] += 1
    
    ui.set('output', str(result))
    ui.set('counter', str(APP_STATE['counter']))

def reset(trigger):
    global APP_STATE
    APP_STATE = {'counter': 0, 'data': []}
    ui.set('output', '0')
    ui.set('counter', '0')

# Initialisierung beim Start
ui.set('output', '0')
ui.set('counter', '0')
```

### 4. Validierung und Fehlerbehandlung

```python
def process_input(trigger):
    input_str = ui.get('user_input')
    
    # Validierung
    if not input_str:
        ui.set('error', '❌ Eingabe darf nicht leer sein!')
        return
    
    try:
        number = float(input_str)
        
        if number < 0:
            ui.set('error', '❌ Nur positive Zahlen erlaubt!')
            return
        
        # Verarbeitung
        result = number * 2
        
        # Erfolg
        ui.set('result', str(result))
        ui.set('error', '✓ Erfolgreich berechnet!')
        
    except ValueError:
        ui.set('error', '❌ Ungültige Zahl!')
```

## Verfügbare Python-Module

Folgende Python-Standardmodule und Pakete sind via Pyodide verfügbar:

### Standard-Bibliothek (immer verfügbar)
- `random` - Zufallszahlen
- `math` - Mathematische Funktionen
- `statistics` - Statistische Funktionen
- `datetime` - Datum und Zeit
- `json` - JSON-Verarbeitung
- `re` - Reguläre Ausdrücke
- `collections` - Counter, defaultdict, etc.
- `itertools` - Iterator-Tools
- `functools` - Funktions-Tools

### Wissenschaftliche Pakete (verfügbar in Pyodide)
- `numpy` - Numerische Berechnungen
- `pandas` - Datenanalyse
- `matplotlib` - Plotting (limitiert im Browser)
- `scipy` - Wissenschaftliche Berechnungen
- `sympy` - Symbolische Mathematik

### NICHT verfügbar
- `requests` (HTTP im Browser nicht möglich)
- `os.system()` / `subprocess` (Keine System-Zugriffe)
- File I/O außer In-Memory

## Vollständige Beispiele

### Beispiel 1: Würfelspiel (Event-Handler)

```python
# init.py
import idegui as ui
import random

if 'GAME' not in globals():
    GAME = {'score': 0, 'rolls': 0}

def roll_dice(trigger):
    global GAME
    dice1 = random.randint(1, 6)
    dice2 = random.randint(1, 6)
    total = dice1 + dice2
    
    GAME['score'] += total
    GAME['rolls'] += 1
    
    ui.set('dice1', str(dice1))
    ui.set('dice2', str(dice2))
    ui.set('total', str(total))
    ui.set('score', str(GAME['score']))
    ui.set('rolls', str(GAME['rolls']))

def reset(trigger):
    global GAME
    GAME = {'score': 0, 'rolls': 0}
    ui.set('dice1', '-')
    ui.set('dice2', '-')
    ui.set('total', '0')
    ui.set('score', '0')
    ui.set('rolls', '0')

ui.set('dice1', '-')
ui.set('dice2', '-')
ui.set('total', '0')
ui.set('score', '0')
ui.set('rolls', '0')
```

```html
<!-- index.html -->
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Würfelspiel</title>
</head>
<body>
    <div class="game">
        <h1>🎲 Würfelspiel 🎲</h1>
        
        <div class="dice">
            <div class="die" data-element="dice1">-</div>
            <div class="die" data-element="dice2">-</div>
        </div>
        
        <p>Summe: <span data-element="total">0</span></p>
        <p>Gesamtpunktzahl: <span data-element="score">0</span></p>
        <p>Würfe: <span data-element="rolls">0</span></p>
        
        <button data-run-python="true" data-run-name="roll_dice">
            🎲 Würfeln
        </button>
        <button data-run-python="true" data-run-name="reset">
            🔄 Reset
        </button>
    </div>
</body>
</html>
```

```css
/* style.css */
body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0;
}

.game {
    background: white;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    text-align: center;
    max-width: 500px;
}

h1 {
    color: #2c3e50;
    margin-bottom: 30px;
}

.dice {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin: 30px 0;
}

.die {
    width: 80px;
    height: 80px;
    background: #3498db;
    color: white;
    font-size: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    font-weight: bold;
}

button {
    margin: 10px;
    padding: 15px 30px;
    font-size: 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: transform 0.2s;
}

button:first-of-type {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
}

button:last-of-type {
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
    color: white;
}

button:hover {
    transform: scale(1.05);
}

p {
    font-size: 18px;
    margin: 10px 0;
}

[data-element] {
    color: #3498db;
    font-weight: bold;
}
```

### Beispiel 2: Liste mit Berechnungen (Direkte Logik)

```python
# init.py
import idegui as ui

# Daten
zahlen = [5, 12, 8, 23, 15, 7, 19]

# Berechnungen
summe = sum(zahlen)
durchschnitt = summe / len(zahlen)
maximum = max(zahlen)
minimum = min(zahlen)

# Ausgabe
ui.set('liste', ', '.join(str(z) for z in zahlen))
ui.set('summe', str(summe))
ui.set('durchschnitt', f'{durchschnitt:.2f}')
ui.set('maximum', str(maximum))
ui.set('minimum', str(minimum))

# Sortierte Liste
sortiert = sorted(zahlen)
ui.set('sortiert', ', '.join(str(z) for z in sortiert))
```

```html
<!-- index.html -->
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Zahlenanalyse</title>
</head>
<body>
    <div class="container">
        <h1>📊 Zahlenanalyse</h1>
        
        <div class="box">
            <h2>Eingabe</h2>
            <p data-element="liste">-</p>
        </div>
        
        <div class="box">
            <h2>Statistiken</h2>
            <p><strong>Summe:</strong> <span data-element="summe">-</span></p>
            <p><strong>Durchschnitt:</strong> <span data-element="durchschnitt">-</span></p>
            <p><strong>Maximum:</strong> <span data-element="maximum">-</span></p>
            <p><strong>Minimum:</strong> <span data-element="minimum">-</span></p>
        </div>
        
        <div class="box">
            <h2>Sortiert</h2>
            <p data-element="sortiert">-</p>
        </div>
    </div>
</body>
</html>
```

## Häufige Fehler und Lösungen

### 1. ❌ Element nicht gefunden
**Problem:** `ui.get('name')` oder `ui.set('name', ...)` funktioniert nicht.

**Lösung:** 
- Prüfe dass HTML-Element `data-element="name"` Attribut hat
- Prüfe Schreibweise (case-sensitive)

### 2. ❌ Event-Handler wird nicht aufgerufen
**Problem:** Button-Klick tut nichts.

**Lösung:**
- Button benötigt `data-run-python="true"` UND `data-run-name="funktionsname"`
- Funktionsname muss exakt mit Python-Funktion übereinstimmen
- Funktion muss `trigger` Parameter haben

### 3. ❌ State geht verloren
**Problem:** Variablen werden bei jedem Button-Klick zurückgesetzt.

**Lösung:**
- Nutze `global` keyword in Event-Handler-Funktionen
- Initialisiere State mit `if 'VAR' not in globals()` Check

### 4. ❌ Input-Werte werden nicht gelesen
**Problem:** `ui.get('input_field')` gibt leeren String.

**Lösung:**
- Bei `<input>`: Stelle sicher dass `data-element="input_field"` gesetzt ist
- HTML wird nur einmal gerendert, nicht bei jedem Button-Klick

## Checkliste für KI-generierte Programme

Wenn du als KI ein Programm erstellst, prüfe:

- [ ] **3 Dateien:** index.html, style.css, init.py
- [ ] **HTML:** Alle interaktiven Elemente haben `data-element="name"`
- [ ] **HTML:** Buttons haben `data-run-python="true"` und `data-run-name="funktion"`
- [ ] **Python:** Event-Handler-Funktionen haben `trigger` Parameter
- [ ] **Python:** State-Variablen sind global und haben Init-Check
- [ ] **Python:** Nur erlaubte Module werden importiert
- [ ] **Python:** Kein `print()` - nutze `ui.set()` für Ausgaben
- [ ] **CSS:** Moderne, ansprechende Gestaltung mit Gradients/Shadows
- [ ] **CSS:** Responsive Design (max-width, padding)
- [ ] **Logik:** Validierung von User-Inputs
- [ ] **Logik:** Fehlerbehandlung mit try/except

## Template-Auswahl

Beim Erstellen neuer Projekte gibt es folgende Vorlagen:

1. **Leeres Python Projekt** - Nur init.py mit Kommentar
2. **Leeres Python-HTML Projekt** - Grundgerüst ohne Funktionalität  
3. **Python-HTML mit Python-Logik** - Direkte Ausführung, Berechnungsbeispiel
4. **Python-HTML mit Event-Handler-Logik** - Interaktive Buttons, Input-Verarbeitung
5. **🎲 Demo: Kniffel (Yahtzee)** - Vollständiges Würfelspiel mit State
6. **🎰 Demo: Blackjack** - Kartenspiel mit komplexer Spiellogik

Wähle die passende Vorlage basierend auf den Anforderungen.

## Prompt-Template für KI-Assistenten

**Nutzung:** User kann KI mit diesem Format bitten, ein Programm zu erstellen:

```
@KI Schreibe mir ein Programm mit idegui und Python, das [BESCHREIBUNG].

Anforderungen:
- Event-Handler-Logik (interaktiv) / Direkte Logik (einmalig)
- [Spezifische Features]

Erstelle index.html, style.css und init.py nach der idegui-Architektur.
```

**Beispiel:**
```
@KI Schreibe mir ein Programm mit idegui und Python, das einen BMI-Rechner erstellt.

Anforderungen:
- Event-Handler-Logik (Benutzer gibt Gewicht und Größe ein)
- Button "Berechnen" führt Berechnung durch
- Zeige BMI-Wert und Kategorie (Untergewicht/Normal/Übergewicht)
- Modernes, freundliches Design

Erstelle index.html, style.css und init.py nach der idegui-Architektur.
```

---

**Version:** 1.0  
**Datum:** März 2026  
**Zielgruppe:** KI-Assistenten und Entwickler  
**Framework:** idegui mit Pyodide
