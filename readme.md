# Python IDE - Browser-basierte Lern-Plattform

Eine leistungsstarke, browserbasierte Python-IDE mit **Monaco Editor**, **Pyodide**, **intelligenten Autocompletion**, und **automatisiertem Test-System**. Ideal für Programmier-Unterricht mit integriertem Assignment- und Help-System.

**HS Pforzheim Edition** - Mit vollständigem Admin-Dashboard und Semester-Management.

---

## ✨ Hauptfunktionen

### 🎯 Kern-Features
- ✅ **Live Python-Ausführung** - Python im Browser via Pyodide (WebAssembly)
- ✅ **Monaco Editor** - Professioneller Code-Editor mit Syntax-Highlighting
- ✅ **Intelligente Autocompletion** - Kontext-bewusste Vorschläge für NumPy, Matplotlib, Math, Strings
- ✅ **Assignment-System** - Strukturierte Aufgaben mit automatischer Validierung
- ✅ **3 Test-Typen** - OUTPUT, FUNCTION, VARIABLE Testing (flexibel kombinierbar)
- ✅ **INIT-Block System** - Einfaches Testen ohne manuelles Löschen von Test-Werten
- ✅ **Integriertes Help-System** - Instant-Dokumentation für 220+ Funktionen und Methoden
- ✅ **Matplotlib-Integration** - Plots direkt im IDE rendern
- ✅ **Multi-Package-Support** - NumPy, Matplotlib und weitere Libraries laden

### 🎓 Admin-Features (NEU)
- ✅ **Admin Dashboard** - Zentrale Verwaltung für Assignments, Tasks und User
- ✅ **Assignment Management** - CRUD mit Clone/Duplicate, Import/Export (JSON)
- ✅ **Task Management** - Inline-Edit, Reorder (Position), Batch-Export
- ✅ **User Authentication** - Bcrypt-Password-Hashing, Session-Management
- ✅ **HS PF Branding** - Hochschule Pforzheim Theme Integration
- ✅ **Search & Filter** - Echtzeit-Suche, Sortierung, Pagination (10 Items/Page)
- ✅ **Progress Tracking** - User-Task-Status (pending/passed/failed)
- ✅ **Responsive Design** - Zebra-Striping, Icon-Buttons, kompakte Tabellenansicht

### 🎓 Assignment & Testing System

**3 Test-Typen für präzise Validierung:**

#### 1. OUTPUT Testing
Testet die Programmausgabe mit mehreren möglichen Patterns:
```json
{
  "type": "output",
  "input": "",
  "expected": ["Hallo Welt!", "Hello World!"]
}
```

#### 2. FUNCTION Testing
Testet Funktionen mit expliziten Argumenten:
```json
{
  "type": "function",
  "function_name": "quadrat",
  "args": [5],
  "expected": 25
}
```

#### 3. VARIABLE Testing mit INIT-Blöcken
Testet Variable mit automatischer Trennung von Test- und Lösungscode:
```python
#INIT Start#
x = 7  # Testwerte - werden bei CHECK ignoriert
#INIT End#

# Lösung:
quadrat = x * x
```
```json
{
  "type": "variable",
  "init_vars": {"x": 5},
  "expected_vars": {"quadrat": 25}
}
```

**Workflow:**
- **▶ RUN** - Code mit INIT-Block ausführen (Student-Test)
- **✓ CHECK** - INIT-Block automatisch entfernen, System-Tests ausführen

### 📚 Help-Datenbank (220 Einträge)
| Modul | Einträge | Quellen |
|-------|----------|---------|
| **NumPy** | 55 | GeeksforGeeks + W3Schools |
| **Matplotlib** | 17 | GeeksforGeeks + W3Schools |
| **Math** | 35 | W3Schools |
| **Strings** | 41 | W3Schools |
| **Lists** | 11 | W3Schools |
| **Gesamt** | **220** | Vollständig gefüllt |

### 🎨 UI/UX
- Dark Theme mit dunklem Editor (eye-friendly)
- **4-Panel-Layout**: Editor | Output+Plots | Help | (Erweiterbar)
- Kontext-sensitive Help (Hover oder Ctrl+Space)
- Keyboard Navigation in Autocomplete (↑/↓ + Enter)
- Semi-transparente Autocomplete-Dropdown (60% Opacity)

---

## 🚀 Quick Start

### Voraussetzungen
- PHP 7.4+
- XAMPP / Apache mit mod_rewrite
- Moderner Browser (Chrome, Firefox, Safari, Edge)

### Installation

**1. Projekt in XAMPP kopieren**
```bash
# In htdocs
cd c:\xampp\htdocs
# Oder Linux/Mac:
cp -r pythonIDE /path/to/htdocs/
```

**2. Apache starten**
```bash
# XAMPP Control Panel → Start Apache
# Oder CLI: xampp start
```

**3. Browser öffnen**
```
http://localhost/pythonIDE/public
```

**Fertig!** Code schreiben, `Run` drücken oder `Ctrl+Enter`.

---

## 📂 Projektstruktur

```
pythonIDE/
├── public/
│   ├── index.php                      # Main App + Help API
│   ├── assignments.php                # Assignment System
│   ├── login.php / register.php       # Authentication
│   ├── css/
│   │   ├── ide.css                    # Editor & Layout Styling
│   │   └── editor-tooltip.css         # Help Tooltips
│   └── js/
│       ├── editor-setup.js            # Monaco Initialisierung
│       ├── editor.js                  # Editor Kernfunktionalität
│       ├── editor-completions.js      # Autocompletion Engine
│       ├── editor-completions.config.js # Kurierte Funktionslisten
│       ├── assignments.js             # Assignment & Test System
│       ├── output.js                  # Output & Plot Rendering
│       ├── pyodide-init.js            # Pyodide Setup
│       └── pyodide.js                 # Pyodide Loader
│
├── api/
│   └── auth/
│       ├── login.php / logout.php     # Session Management
│       └── register.php               # User Registration
│
├── config/
│   └── database.php                   # DB Connection
│
├── sql/
│   └── schema.sql                     # Database Schema
│
├── storage/
│   └── help/
│       └── help.json                  # 220 Help-Einträge (220 KB)
│
├── scripts/
│   OUTPUT Test - Einfache Ausgabe
```python
# Task: Geben Sie "Hello World!" aus
print("Hello World!")
```
**Test:**
```json
{
  "type": "output",
  "expected": ["Hello World!", "Hallo Welt!"]
}
```

### FUNCTION Test - Funktion implementieren
```python
# Task: Implementieren Sie eine Quadrat-Funktion
def quadrat(x):
    return x * x
```
**Test:**
```json
{
  "type": "function",
  "function_name": "quadrat",
  "args": [5],
  "expected": 25
}
```

### VARIABLE Test - Mit INIT-Block
```python
#INIT Start#
a = Datenbank Setup
```bash
# 1. Datenbank erstellen
mysql -u root -p < sql/schema.sql

# 2. config/database.php anpassen
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'python_ide'
];
```

### Assignments erstellen
```bash
# Beispiel-Tasks mit allen 3 Test-Typen
php scripts/create_test_type_examples.php

# Output:
✓ Task 1: Begrüßung ausgeben (OUTPUT)
✓ Task 2: Quadrat-Funktion (FUNCTION)
✓ Task 3: Bereichsprüfung (FUNCTION, mehrere Args)
✓ Task 4: Quadrat berechnen (VARIABLE)
✓ Task 5: Summe und Produkt (VARIABLE, mehrere)
✓ Task 6: Gerade Zahlen filtern (VARIABLE)
```

### INIT-Block Verifikation
```bash
# Verifizieren dass alle VARIABLE-Tasks INIT-Blöcke haben
php scripts/verify_init_blocks.php

# End-to-End Tests
php scripts/test_e2e_init_blocks.php
```

### 8   # Testwerte für RUN
b = 12
#INIT End#

# Lösung:
summe = a + b
produkt = a * b
```
**Workflow:**
1. **Entwickeln:** Code schreiben, Werte im INIT ändern
2. **▶ RUN:** Testen mit eigenen Werten (a=8, b=12)
3. **✓ CHECK:** System entfernt INIT-Block, testet mit verschiedenen Werten

**Test:**
```json
{
  "type": "variable",
  "init_vars": {"a": 5, "b": 10},
  "expected_vars": {"summe": 15, "produkt": 50}
}s.php       # End-to-End Tests
│   └── list_help.php                  # Help-DB Monitor
│
├── docs/
│   ├── architecture.md                # System-Design
│   ├── setup.md                       # Setup-Anleitung
│   ├── test-types.md                  # Test-Typen Dokumentation
│   ├── test-types-quickref.md         # Quick Reference
│   ├── init-block-system.md           # INIT-Block System
│   ├── init-block-quickref.md         # INIT Quick Reference
│   └── init-block-summary.md          # INIT Summary
│
└── README.md                          # Diese Datei
```

---

## ⌨️ Beispiele

### Einfaches Python
```python
x = [1, 2, 3, 4, 5]
squared = [i**2 for i in x]
print(squared)
# Output: [1, 4, 9, 16, 25]
```

### NumPy & Matplotlib
```python
import numpy as np
import matplotlib.pyplot as plt

x = np.linspace(0, 2*np.pi, 100)
y = np.sin(x)
plt.plot(x, y)
plt.xlabel("x")
plt.ylabel("sin(x)")
plt.show()
# Plot erscheint rechts
```

### Help nutzen
- **Hover über Identifier** → Sofort Help anzeigen
- **Ctrl+Space** → Autocompletion + Live Help
- **↑/↓ in Dropdown** → Vorschläge navigieren, Help aktualisiert sich
- **Fehlende Help?** → Scraper erneut laufen: `?force=1`

---

## 🔧 Konfiguration

### Package Toggle (Toolbar)
- `[✓ NumPy]` - NumPy beim Start laden
- `[✓ Matplotlib]` - Matplotlib beim Start laden

### Help API
**Endpoint**: `index.php?api=help&key=<KEY>`

**Beispiele**:
```bash
# Alle Help-Keys auflisten
curl "http://localhost/pythonIDE/public/index.php?api=help&key=__list__"

# Spezifische Funktion
curl "http://localhost/pythonIDE/public/index.php?api=help&key=np.array"
curl "http://localhost/pythonIDE/public/index.php?api=help&key=str.split"
curl "http://localhost/pythonIDE/public/index.php?api=help&key=math.sqrt"
```

**Response-Format**:
```json
{
| **Test-Typen** | 3 (OUTPUT, FUNCTION, VARIABLE) |
| **Test-Validierung** | Automatisch, Echtzeit |

## 🧪 Test-System Details

### Test-Typen Übersicht

| Typ | Wann verwenden | Beispiel |
|-----|---------------|----------|
| **OUTPUT** | Programmausgabe testen | `print("Hello")` |
| **FUNCTION** | Funktions-Return-Werte | `def add(a, b): return a+b` |
| **VARIABLE** | Variable nach Berechnung | `result = x * 2` |

### Test-Typen kombinieren

**Verschiedene Typen in einem Assignment:**
Ein Assignment kann beliebig viele Tasks mit unterschiedlichen Test-Typen enthalten:

```php
// Assignment mit gemischten Test-Typen
$assignment = [
    'title' => 'Python Grundlagen',
    'tasks' => [
        // Task 1: OUTPUT
        ['type' => 'output', 'expected' => ['Hello']],
        // Task 2: FUNCTION  
        ['type' => 'function', 'function_name' => 'add', ...],
        // Task 3: VARIABLE
        ['type' => 'variable', 'init_vars' => [...], ...]
    ]
];
```

**Mehrere Tests pro Task:**
Eine einzelne Task kann mehrere Test-Cases unterschiedlicher Typen haben:

```json
{
  "test_cases": [
    {
      "type": "output",
      "expected": ["Test erfolgreich"]
    },
    {
      "type": "variable",
      "init_vars": {"x": 10},
      "expected_vars": {"result": 20}
    }
  ]
}
```

**Praktisches Beispiel - Komplexe Validierung:**
```python
# Student-Code
def verdoppeln(x):
    return x * 2

x = 5
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
    "expected": 14
  },
  {
    "type": "variable", 
    "init_vars": {"x": 10},
    "expected_vars": {"result": 20}
  },
  {
    "type": "output",
    "expected": ["Ergebnis: 10", "Ergebnis: 20"]
  }
]
```

**Vorteile der Kombination:**
- ✅ **Umfassende Validierung** - Funktion UND Nutzung testen
- ✅ **Flexibilität** - Unterschiedliche Aspekte prüfen
- ✅ **Realistische Szenarien** - Funktion + Variable + Output
- ✅ **Granulares Feedback** - Genau sehen wo Fehler sind

### INIT-Block System
x = 7  # Wird bei CHECK ignoriert
#INIT End#

quadrat = x * x  # Wird getestet
```

**Technische Umsetzung:**
```python
# Python in assignments.js
pattern = r'#INIT Start#.*?#INIT End#'
code_without_init = re.sub(pattern, '', user_code, flags=re.DOTALL)
```

**Vorteile:**
- ✅ Student muss nichts löschen
- ✅ Python kennt Typen (IDE-Support)
- ✅ Klare Trennung: Test vs. Lösung
- ✅ Weniger fehleranfällig

---

## 🐛 Bekannte Einschränkungen & Zukunftspläne

### Aktuelle Limits
- ⚠️ **Datei-I/O limitiert** - Nur In-Memory Operationen
- ⚠️ **Package-Einschränkung** - Nur vorgeladene Module (Pyodide-Libraries)
- ⚠️ **Kein Debugger** - Keine Step-Through Debugging

### Implementierte Features ✅
- ✅ **Assignment-System** - Strukturierte Aufgaben mit DB
- ✅ **3 Test-Typen** - OUTPUT, FUNCTION, VARIABLE (flexibel kombinierbar)
- ✅ **INIT-Block System** - Automatische Trennung Test/Lösung
- ✅ **User Authentication** - Login, Register, Sessions
- ✅ **Progress Tracking** - Task-Completion per User
- ✅ **Code-Validierung** - Automatische Tests mit granularem Feedback

### Geplante Features

#### 🔜 Phase 1: User-Management & Assignment-Zuweisung
**Priorität: HOCH**

**User-Verwaltung:**
- [ ] **User-Filter nach Semester** - Dropdown/Multi-Select für Semesterzuordnung
- [ ] **User-Suche** - Echtzeit-Filter: Name, Email, Matrikelnummer
- [ ] **Bulk-Assignment-Zuweisung** - Assignments an ganze Gruppen/Semester auf einmal zuweisen
- [ ] **Semester-/Gruppen-Management** - Semester als Entität mit User-Zuordnung

**Assignment-Auswertung:**
- [ ] **Progress-Dashboard** - Übersicht: Wieviel % aller Aufgaben hat jeder Student geschafft
- [ ] **Detaillierte Reports** - Pro Assignment: Completion-Rate, durchschnittliche Versuche
- [ ] **Export-Funktion** - CSV/Excel-Export für externe Auswertung

#### 🔜 Phase 2: Task-Preview & Test-Editor
**Priorität: HOCH**

**Admin-Task-Vorschau:**
- [ ] **Direct-to-Editor Link** - Aus Admin-Ansicht direkt in spezielle Editor-Sicht springen
- [ ] **Exakte Student-Kopie** - Identische Code-View wie Student sieht (Template, Tests)
- [ ] **Read-Only Modus** - Versuche werden NICHT hochgezählt, keine Abgabe möglich
- [ ] **Solution-Toggle** - Zwischen Student-Template und Musterlösung hin- und herspringen
- [ ] **Inline Solution-Edit** - Musterlösung direkt im Preview bearbeiten und speichern
- [ ] **Test-Validation Live** - Sofort sehen ob Test-Cases korrekt konfiguriert sind

**Nutzen:**
- ✅ Schnell überprüfen wie Student die Aufgabe sieht
- ✅ Tests direkt validieren ohne separaten Student-Account
- ✅ Musterlösung im Context der Task anpassen

#### 🔜 Phase 3: Test-/Prüfungs-Modus
**Priorität: MITTEL**

**Test-Szenario (zeitlich begrenzt):**
- [ ] **Test-Assignment-Typ** - Neuer Assignment-Type: "exam" vs. "practice"
- [ ] **Zeitbeschränkung** - Konfigurierbare Zeitlimits pro Test (z.B. 90 Minuten)
- [ ] **Timer-Display** - Sichtbarer Countdown, Warnung bei < 5 Minuten
- [ ] **Auto-Submit** - Automatische Abgabe wenn Zeit abläuft
- [ ] **Copy-Paste-Unterdrückung** - JavaScript-Handler blockiert Strg+C/V
- [ ] **Fokus-Tracking** - Protokollierung wenn Student Fenster verlässt (Tab-Switch, Minimize)
- [ ] **Session-Lock** - Test kann nur einmal gestartet werden (keine Wiederholung)

**Anwendung auch für normale Assignments:**
- [ ] **Copy-Paste-Warning** - Optionales Flag für jedes Assignment
- [ ] **Activity-Log** - Optional: Fenster-Fokus-Verlust protokollieren (Lern-Statistik)

#### 🔧 Phase 4: Weitere Features
- [ ] **Code-Sharing** - Teilbare Links generieren
- [ ] **Mehr Libraries** - SciPy, Pandas, Scikit-learn
- [ ] **Dark/Light Toggle** - Theme-Umschalter
- [ ] **Shell-Modus** - Interaktive REPL
- [ ] **Linting** - Echtzeit Code-Analyse
- [ ] **Code-Review** - Instructor Feedback direkt im Code

---

## 🎓 Einsatzszenarien

Perfekt für:
- 📚 **Python-Unterricht** - Strukturierte Assignments mit Auto-Grading
- 🔬 **NumPy/Matplotlib-Kurse** - Demonstrationen und Übungen
- 🧪 **Programmier-Übungen** - Sofortiges Feedback durch Tests
- 📊 **Algorithmen lehren** - OUTPUT/FUNCTION/VARIABLE Testing
- 💻 **Coding-Bootcamps** - Keine lokale Installation nötig
- 🎯 **Selbststudium** - Integriertes Help-System

**Vorteile für Lehrende:**
- ✅ Automatische Validierung spart Korrektur-Zeit
- ✅ 3 Test-Typen für präzises Testing
- ✅ INIT-Block System vereinfacht Übungen
- ✅ Progress Tracking pro Student
- ✅ Keine Student-Installation nötig

**Vorteile für Studierende:**
- ✅ Sofortiges Feedback (RUN & CHECK)
- ✅ Help-System mit 220+ Funktionen
- ✅ Autocomplete während Tippen
- ✅ Klarer Workflow (INIT für Tests, CHECK für Abgabe)
- ✅ Beispiele für jeden Test-Typ

---

## 📄 Lizenz

Proprietary — XAMPP Python IDE (2026)

---

## 📞 Support & Dokumentation

### Dokumentation
- **Test-Typen**: [docs/test-types.md](docs/test-types.md) - Vollständige Test-Typen Dokumentation
- **Test-Typen Quick Ref**: [docs/test-types-quickref.md](docs/test-types-quickref.md) - Quick Reference
- **Test-Typen kombinieren**: [docs/test-types-combining.md](docs/test-types-combining.md) - Kombinierte Tests
- **INIT-Block System**: [docs/init-block-system.md](docs/init-block-system.md) - INIT-Block Details
- **INIT Quick Ref**: [docs/init-block-quickref.md](docs/init-block-quickref.md) - Quick Reference
- **Architektur**: [docs/architecture.md](docs/architecture.md) - System-Design
- **Setup**: [docs/setup.md](docs/setup.md) - Detaillierte Installation

### Fehlersuche
- **Browser Console**: F12 → Console
- **XAMPP Logs**: XAMPP Log-Verzeichnis
- **PHP Errors**: `php -d display_errors=1 script.php`
- **Test Verifikation**: `php scripts/verify_init_blocks.php`

### Scripts
```bash
# Beispiel-Assignments erstellen
php scripts/create_test_type_examples.php

# INIT-Blöcke verifizieren
php scripts/verify_init_blocks.php

# End-to-End Tests
php scripts/test_e2e_init_blocks.php

# INIT-Block Demo
php scripts/demo_init_blocks.php

# Help-DB aktualisieren
php scripts/scrape_geeksforgeeks.php
php scripts/scrape_w3schools.php
```

---

## 🚀 Tech Stack

- **Frontend**: Monaco Editor, Vanilla JavaScript
- **Python Runtime**: Pyodide (WebAssembly)
- **Backend**: PHP 7.4+
- **Database**: MariaDB / MySQL
- **Testing**: Custom Test Framework (OUTPUT/FUNCTION/VARIABLE)
- **Auth**: Session-based Authentication

---

## 📈 Version & Status

**Status**: ✅ Produktiv-ready (v2.1)  
**Letzte Aktualisierung**: 11. Februar 2026  
**Neue Features (v2.1)**:
- ✅ Admin Dashboard mit Assignment/Task/User-Management
- ✅ Clone/Duplicate für Assignments mit allen Tasks
- ✅ Search, Filter, Sortierung, Pagination
- ✅ HS Pforzheim Branding (Custom Theme)
- ✅ Bcrypt Passwort-Hashing
- ✅ Import/Export Tasks (JSON)

**Features (v2.0)**:
- ✅ Assignment-System mit Datenbank
- ✅ 3 Test-Typen (OUTPUT, FUNCTION, VARIABLE)
- ✅ INIT-Block System für VARIABLE Tests
- ✅ User Authentication & Progress Tracking
- ✅ Automatische Test-Validierung

**Team**: Python IDE Contributors @ HS Pforzheim

---

## 🎯 Quick Start Zusammenfassung

```bash
# 1. XAMPP Setup
cd c:\xampp\htdocs
# pythonIDE Ordner hier platzieren

# 2. Datenbank Setup
mysql -u root -p < sql/schema.sql

# 3. Beispiel-Tasks erstellen
php scripts/create_test_type_examples.php

# 4. Browser öffnen
http://localhost/pythonIDE/public

# 5. Account erstellen & Tasks lösen!
```

**Viel Erfolg beim Programmieren lernen! 🎉**
