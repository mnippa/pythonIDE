# Python IDE - Browser-basiert mit Live-Execution

Eine leistungsstarke, browserbasierte Python-IDE mit **Monaco Editor**, **Pyodide**, und **PHP-Backend**. Code schreiben, ausführen und debuggen direkt im Browser mit integriertem Help-System.

---

## ✨ Funktionen

### 🎯 Kern-Features
- ✅ **Live Python-Ausführung** - Python im Browser via Pyodide (WebAssembly)
- ✅ **Monaco Editor** - Professioneller Code-Editor mit Syntax-Highlighting
- ✅ **Intelligente Autocompletion** - Kontext-bewusste Vorschläge für NumPy, Matplotlib, Math, Strings
- ✅ **Integriertes Help-System** - Instant-Dokumentation für 220+ Funktionen und Methoden
- ✅ **Matplotlib-Integration** - Plots direkt im IDE rendern
- ✅ **Multi-Package-Support** - NumPy, Matplotlib und weitere Libraries laden

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
│   ├── css/
│   │   └── ide.css                    # Editor & Layout Styling
│   └── js/
│       ├── editor-setup.js            # Monaco Initialisierung
│       ├── editor.js                  # Editor Kernfunktionalität
│       ├── editor-completions.js      # Autocompletion Engine
│       ├── editor-completions.config.js # Kurierte Funktionslisten
│       ├── output.js                  # Output & Plot Rendering
│       ├── pyodide-init.js            # Pyodide Setup
│       └── pyodide.js                 # Pyodide Loader
│
├── storage/
│   └── help/
│       └── help.json                  # 220 Help-Einträge (220 KB)
│
├── scripts/
│   ├── scrape_geeksforgeeks.php       # NumPy/Matplotlib Scraper
│   ├── scrape_w3schools.php           # Math/String Scraper
│   └── list_help.php                  # Help-DB Monitor
│
├── docs/
│   ├── architecture.md                # System-Design
│   └── setup.md                       # Detaillierte Setup-Anleitung
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
  "ok": true,
  "found": true,
  "resolved_key": "np.array",
  "title": "numpy.array()",
  "md": "Create an array...",
  "source": "geeksforgeeks",
  "fetched_at": "2025-03-15"
}
```

---

## 🔄 Help-Datenbank aktualisieren

**NumPy/Matplotlib von GeeksforGeeks scrapen**:
```bash
php scripts/scrape_geeksforgeeks.php
```

**Math/Strings von W3Schools scrapen**:
```bash
php scripts/scrape_w3schools.php
```

**Help-DB überwachen**:
```bash
php scripts/list_help.php
```

Ausgabe:
```
Total Entries: 220
  NumPy: 55
  Matplotlib: 17
  Math: 35
  String: 41
  List: 11
```

---

## 🎨 Styling & Theme

### Farschema
- **Editor BG**: `#1e1e1e`
- **Text Primary**: `#e8e8e8`
- **Text Secondary**: `#858585`
- **Accent/Links**: `#3b82f6`
- **Code BG**: `#2d2d2d`
- **Border**: `#3e3e42`

### Theme anpassen
In `public/index.php` (Zeilen 200-230):
```css
:root {
  --bg: #1e1e1e;
  --text-primary: #e8e8e8;
  --code-bg: #2d2d2d;
  /* ... weitere Variablen */
}
```

---

## ⚙️ Für Entwickler

### Autocompletion anpassen
Datei: `public/js/editor-completions.config.js`

```javascript
export const NUMPY_COMPLETIONS = [
  "array", "arange", "linspace", "zeros", // ...
];
```

### Help-Datenbank prüfen
Browser DevTools → Console:
```javascript
// Config prüfen
console.log(NUMPY_COMPLETIONS);

// Help abrufen
fetch("index.php?api=help&key=np.array")
  .then(r => r.json())
  .then(d => console.log(d.md));
```

### Pyodide debuggen
```javascript
// Pyodide-Objekt inspizieren
console.log(pyodide);

// Python direkt ausführen
await pyodide.runPython("print('Test!')");
```

---

## 📊 Performance & Statistiken

| Metrik | Wert |
|--------|------|
| **Help-DB Größe** | ~220 KB |
| **Startup-Zeit** | 2-3 Sekunden (Pyodide-Load) |
| **Autocompletion Suggestions** | 30 pro Modul (konfigurierbar) |
| **Funktionen in Help** | 220+ |
| **Unterstützte Module** | NumPy, Matplotlib, Math, Strings, Lists |

---

## 🐛 Bekannte Einschränkungen & Zukunftspläne

### Aktuelle Limits
- ⚠️ **Keine persistenten Sessions** - Code nach Browser-Close weg
- ⚠️ **Datei-I/O limitiert** - Nur In-Memory Operationen
- ⚠️ **Package-Einschränkung** - Nur vorgeladene Module
- ⚠️ **Kein Debugger** - Keine Step-Through Debugging

### Geplante Features
- [ ] **Code speichern/laden** - LocalStorage oder Datenbank
- [ ] **Code-Sharing** - Teilbare Links generieren
- [ ] **Mehr Libraries** - SciPy, Pandas, Scikit-learn
- [ ] **Dark/Light Toggle** - Theme-Umschalter
- [ ] **Shell-Modus** - Interaktive REPL
- [ ] **Linting** - Echtzeit Code-Analyse
- [ ] **Export** - Download von Code/Plots

---

## 🎓 Einsatzszenarien

Perfekt für:
- 📚 Python-Anfänger-Unterricht (Schleifen, Funktionen, Datenstrukturen)
- 🔬 NumPy/Matplotlib-Konzepte demonstrieren
- 🧪 Quick Prototyping (keine lokale Installation nötig)
- 📊 Daten-Visualisierungs-Übungen
- 💻 Web-Dev Anfänger (Python im Browser kennenlernen)

**Kein Setup notwendig für Schüler** — einfach Link teilen!

---

## 📄 Lizenz

Proprietary — XAMPP Python IDE (2025)

---

## 📞 Support & Dokumentation

- **Detaillierte Docs**: Siehe `/docs/`
- **Fehlersuche**: Browser Console (F12)
- **Logs**: XAMPP Log-Verzeichnis
- **Issues**: GitHub Issues (falls Repository öffentlich)

---

**Status**: ✅ Produktiv-ready (v1.0)  
**Letzte Aktualisierung**: 7. Februar 2025  
**Team**: XAMPP Python IDE Contributors

Kontakt / Weiteres
------------------
Bei Fragen oder Wunsch nach Live-Demo bitte melden.

---
Stand: 06.02.2026

\# Python Web IDE



Browserbasierte Web-IDE für Python mit Monaco Editor und Pyodide.



\## Tech Stack

\- Monaco Editor

\- Pyodide (Python WASM)

\- PHP 7.4

\- MariaDB (später)



\## Ziel

Studierende schreiben und testen Python-Code direkt im Browser.



