# PythonIDE Dokumentation

## 📚 Übersicht

Dieses Verzeichnis enthält die Dokumentation für PythonIDE.

Wichtig: Nicht alle älteren Dateien bilden den aktuellen Implementierungsstand vollständig ab.
Für neue Chats, neue Entwickler und KI-Assistenten zuerst lesen:

### [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)
**Aktueller Gesamt-Kontext der Plattform**

Enthält den derzeit relevanten Stand zu:
- Assignment- und Task-Modell
- Statuslogik im Dashboard
- Folder-Tasks und Dateispeicher (DB + Filesystem)
- Pyodide-Runtime-Flow
- Live/Beta-Deploy-Sync

**Zielgruppe:** Entwickler, Admins, KI-Assistenten, neue Chats

## 📖 Dokumentationsdateien

### [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)
**Master-Kontext für den aktuellen Stand**

Empfohlener erster Einstieg bei:
- Weiterentwicklung von PythonIDE
- Debugging von Assignment-/Task-Logik
- Fragen zu Folder-Dateien, Pyodide oder Statuslogik
- Deploy-/Live-Beta-Themen

### [idegui-programming-guide.md](idegui-programming-guide.md)
**Hauptdokumentation für KI-Assistenten und Entwickler**

Vollständiger Guide zur Programmierung mit idegui, einschließlich:
- Architektur-Übersicht
- Zwei Logik-Paradigmen (Direkt vs Event-Handler)
- Vollständige API-Referenz
- Best Practices
- Code-Beispiele
- Häufige Fehler und Lösungen

**Zielgruppe:** KI-Assistenten (z.B. für Prompts wie "@KI erstelle ein Programm mit idegui"), Entwickler

### [ui-python-api-tutorial.md](ui-python-api-tutorial.md)
**Kompaktes Tutorial zur UI-API (HTML <-> Python Kommunikation)**

Didaktische Herleitung der Syntax mit direkt nutzbaren Beispielcodes:
- Datenbindung mit `data-element` + `ui.get()`/`ui.set()`
- Trigger-Modi `data-run` und `data-function`
- Trigger-Kontext (`__trigger__`, `trigger.name`, `trigger.value`)
- Copy-Paste Startvorlage fuer eigene Aufgaben und Tutorials

**Zielgruppe:** Dozierende, Autoren von Lerninhalten, KI-gestuetzte Inhaltserstellung

### [ui-python-api-lesson-45min.md](ui-python-api-lesson-45min.md)
**Unterrichtseinheit fuer 45 Minuten (inkl. Uebungen und Musterloesung)**

Fertiger Ablaufplan fuer den direkten Einsatz im Unterricht:
- Zeitplan in 5 Phasen
- Lernziele und didaktischer Leitfaden
- Guided Coding fuer Run- und Event-Modus
- 3 Uebungen + Musterloesung + Bewertungs-Checkliste

**Zielgruppe:** Lehrkraefte, Tutorinnen/Tutoren, Kurs-Autoren

### [architecture.md](architecture.md)
Technische Architektur der Plattform

### [setup.md](setup.md)
Setup- und Installations-Anleitung (falls vorhanden)

### [code-ui.md](code-ui.md)
Spezifika für `code_ui`, Folderstruktur, Reset und Policies.

### [code-ui-architecture.md](code-ui-architecture.md)
Folder- und Runtime-Modell für Code-UI-Tasks.

### [ASSIGNMENTS_API_DOCUMENTATION.md](ASSIGNMENTS_API_DOCUMENTATION.md)
Assignment- und User-Assignment-APIs.

### [TASK_AUTHORING_GUIDE.md](TASK_AUTHORING_GUIDE.md)
Kompakter Leitfaden fuer neue Aufgaben, Feldbelegung, Tests und Folder-Tasks.

### [DEPLOYMENT_DEBIAN.md](DEPLOYMENT_DEBIAN.md)
Shell-/Server-Sicht auf Deployments und Rechte.

## 🎯 Schnellstart für KIs

Für PythonIDE-Arbeit allgemein:

1. [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)
2. Danach themenspezifisch weiter in die passende Fach-Doku

Für neue Aufgaben und Task-Inhalte:

1. [TASK_AUTHORING_GUIDE.md](TASK_AUTHORING_GUIDE.md)
2. Danach bei Bedarf [test-types-documentation-final-v2.md](test-types-documentation-final-v2.md)
3. Bei Folder-/Code-UI-Themen zusätzlich [code-ui.md](code-ui.md)

Um ein neues idegui-Programm zu erstellen, lies danach die [idegui-programming-guide.md](idegui-programming-guide.md) und folge diesem Format:

**User-Prompt:**
```
@KI Schreibe mir ein Programm mit idegui, das [BESCHREIBUNG].
Erstelle index.html, style.css und init.py.
```

**KI-Antwort sollte beinhalten:**
1. `index.html` - HTML-Struktur mit `data-element` Attributen
2. `style.css` - Modernes, responsives Design
3. `init.py` - Python-Logik mit idegui API

## 🎨 Verfügbare Templates

Beim Erstellen neuer Projekte stehen folgende Vorlagen zur Verfügung:

1. **Leeres Python Projekt** - Minimales Python-Projekt
2. **Leeres Python-HTML Projekt** - Grundgerüst mit HTML/CSS/Python
3. **Python-HTML mit Python-Logik** - Direkte Ausführung, Berechnungsbeispiel
4. **Python-HTML mit Event-Handler-Logik** - Interaktive Anwendung mit Buttons
5. **🎲 Demo: Kniffel (Yahtzee)** - Vollständiges Würfelspiel
6. **🎰 Demo: Blackjack** - Kartenspiel-Beispiel

## 🔑 idegui API Kurzreferenz

```python
import idegui as ui

# Wert aus HTML lesen
wert = ui.get('element_name')

# Wert in HTML schreiben
ui.set('element_name', 'Neuer Wert')

# Event-Handler (Button-Klick)
def button_clicked(trigger):
    # Deine Logik hier
    pass
```

**HTML-Binding:**
```html
<!-- Element zum Lesen/Schreiben -->
<input data-element="element_name" value="42">
<p data-element="output">Ausgabe</p>

<!-- Button mit Event-Handler -->
<button data-function="button_clicked" name="button_clicked" value="click">
    Klick mich
</button>
```

## 📝 Beispiel-Workflow

1. User: "Erstelle ein BMI-Rechner-Programm"
2. KI liest idegui-programming-guide.md
3. KI erstellt:
   - index.html mit Inputs für Größe/Gewicht
   - style.css mit modernem Design
   - init.py mit Event-Handler für Berechnung
4. User kann Projekt sofort nutzen

## 🚀 Weitere Ressourcen

- Template-Definitionen: `/api/projects/templates.php`
- Projekt-Erstellung API: `/api/projects/create.php`
- Demo-Projekte: Kniffel und Blackjack in der Projektauswahl

## Hinweis zu älteren Docs

Einige ältere Dateien dokumentieren frühere Zwischenstände.
Wenn Statuswerte, Dateispeicher oder Runtime-Verhalten unklar wirken, immer mit [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md) und dem aktuellen Code gegenprüfen.

---

**Letzte Aktualisierung:** April 2026  
**Version:** 1.1
