# 🚀 pythonIDE Roadmap 2026

> **Status**: In Progress | **Last Updated**: Feb 24, 2026

---

## 📋 Phase Overview

| Phase | Fokus | Dauer | Status |
|-------|-------|-------|--------|
| **Phase 1** | Unblock & Stabilize | Diese Woche | ✅ Complete |
| **Phase 2** | User Input Support | Nächste Woche | 🟡 Partial (2/3) |
| **Phase 3** | AI Task Generator | +2 Wochen | ⚪ Waiting |
| **Phase 4** | Engagement Metrics | Laufend | ⚪ Waiting |

---

## 🔴 Phase 1: Unblock & Stabilize (Diese Woche)

### Task 1.1: Projekt-Editor Debuggen
- **Ziel**: Projekt-Editor wieder vollständig funktionsfähig machen
- **Aufwand**: 2-3h
- **Status**: ⏳ IN PROGRESS
- **Details**:
  - [ ] Fehlernhafte Funktionen identifizieren
  - [ ] Bugs nach Priorität ordnen
  - [ ] Pro Bug testen & verifizieren
  - [ ] Regressions-Tests durchführen

### Task 1.2: File-Ansicht für Projekt-Editor
- **Ziel**: Projektdateien in Editor-UI anzeigen/navigieren
- **Aufwand**: 1-2h
- **Status**: ⏳ Dependent on 1.1
- **Details**:
  - [ ] UI-Mock erstellen
  - [ ] File-Baum Loading implementieren
  - [ ] Dateien in Editor anzeigen können
  - [ ] Integration mit Assignment-Editor testen

**Exit Criteria**: Projekt-Editor ist stabil & neue Features funktionieren

---

## ⚪ Phase 2: User Input Support (Nächste Woche)

### Task 2.1: `input()` in Pyodide aktivieren
- **Ziel**: Studierende können `input()`-basierte Programme schreiben
- **Aufwand**: 1h
- **Status**: ✅ COMPLETED
- **Implementation**: Phase 1 (Browser `prompt()`) implementiert
- **Details**:
  - [x] Pyodide stdin Configuration researchen
  - [x] `builtins.input()` überschrieben in editor-setup.js
  - [x] Nutzt `window.prompt()` für User-Input (synchron)
  - [x] Test-Programme erstellt (5 Beispiele in test_input_examples.py)
- **Technische Ansätze**:
  - ✅ **Option A (Implementiert)**: `window.prompt()` - Einfach, synchron, funktioniert sofort
  - ⏳ **Option B (Future)**: Custom Modal mit `pythonInput()` - Schönere UI, asyncron
  - ❌ **Option C**: Pre-run Input-Panel - Komplexer, weniger intuitiv
- **Testing**:
  ```python
  name = input("Wie heißt du? ")
  print(f"Hallo, {name}!")
  ```
- **Known Limitations**:
  - Nutzt Browser-nativen `prompt()` Dialog (funktional aber nicht schön)
  - Modal-Version (`pythonInput()`) bereit, aber benötigt async-Wrapper für nahtlose Integration
- **Next Steps (Optional)**:
  - [ ] Modal-basierte Lösung fertigstellen (besseres UX)
  - [ ] Multi-Input Optimierung (Batch-Eingaben)

### Task 2.2: Input-Testing Pattern freigeben
- **Ziel**: Aufgabenersteller kennen beste Practices
- **Aufwand**: 30m
- **Status**: ✅ COMPLETED
- **Details**:
  - [x] Keyword-Check (`input`, type-conversion) + Output-Test Documentation
  - [x] Beispiel-Aufgaben erstellt (5 vollständige Aufgaben mit Test Cases)
  - [x] Guide dokumentiert: docs/input-testing-guide.md
  - [x] Test-Strategien definiert (Output-Tests, Code-Checks, Function-Tests)
  - [x] **Produktions-fertige Beispielaufgabe**: MwSt-Rechner für Assignment #21
- **Deliverables**:
  - ✅ `docs/input-testing-guide.md` - Vollständiger Guide mit:
    - Test-Strategien (Code-Check, Output-Tests)
    - 4 fertige Beispiel-Aufgaben (Begrüßung, Alter, Temperatur, Addition)
    - Best Practices für Error-Handling
    - Workflow für Aufgabenersteller
  - ✅ `test_input_examples.py` - 5 funktionierende Test-Programme
  - ✅ **MwSt-Rechner Beispiel-Aufgabe (Assignment #21)**:
    - `sql/add_mwst_task.sql` - SQL-Script für Datenbank-Installation
    - `docs/task-mwst-rechner.md` - Vollständige Aufgaben-Dokumentation
    - `docs/INSTALL_MWST_TASK.md` - Installation & Test-Anleitung
    - `test_mwst_solution.py` - Ausführbare Musterlösung
    - `test_mwst_scenarios.py` - Test-Szenarien (gültig/ungültig)
- **Beispiel-Aufgabe Features**:
  - ✓ 4 Code-Check Test-Cases (input, float/int, Berechnung, print)
  - ✓ Vollständige Musterlösung mit Kommentaren
  - ✓ 3 alternative Lösungsvarianten
  - ✓ Beispiel-Berechnungen für 6 verschiedene Eingaben
  - ✓ Troubleshooting-Guide
  - ✓ Production-ready für sofortigen Einsatz
- **Usage**:
  ```json
  {
    "type": "code_check",
    "pattern": "input\\s*\\(",
    "description": "Verwendet input() Funktion"
  }
  ```

### Task 2.3: UI-Elemente & Interaktive Widgets
- **Ziel**: Studierende können grafische UI-Elemente erstellen (z.B. Taschenrechner)
- **Aufwand**: 3-4h (Research + POC)
- **Status**: 🔵 DESIGN PHASE
- **Use Case**: Taschenrechner mit Buttons, Input-Feldern, Event-Handling
- **Technische Diskussion**:
  
  #### Ansatz 1: IFRAME mit HTML (Vorschlag)
  - **Pro**: 
    - Volle HTML/CSS-Kontrolle
    - Sandbox-Isolation
    - Kann mit `document.getElementById()` aus Python interagieren
  - **Contra**: 
    - CORS-Issues bei lokalen Files
    - Komplexere Python↔HTML Bridge nötig
  - **Python-Interaktion**: 
    - Pyodide's `js` module: `from js import document`
    - Event-Handler in Python registrieren
    - DOM-Manipulation via `js.document.querySelector()`
  
  #### Ansatz 2: Panel/PyScript Widgets
  - **Pro**: 
    - Native Python-Syntax für UI
    - Built-in Pyodide-Integration
  - **Contra**: 
    - Zusätzliche Dependencies
    - Learning Curve für Studierende
  
  #### Ansatz 3: Direct DOM Manipulation
  - **Pro**: 
    - Kein IFRAME nötig
    - Direkter Zugriff auf Output-Container
  - **Contra**: 
    - Namespace-Pollution
    - Schwerer zu isolieren/zurücksetzen
  
  #### Offene Fragen:
  - [ ] Wo wird das HTML definiert? (Task-Template? Python-Code generiert?)
  - [ ] Wie wird die UI zurückgesetzt beim erneuten Run?
  - [ ] Wie werden Event-Handler gebunden? (Python-Callbacks vs. JS Bridge)
  - [ ] Welche UI-Bibliotheken sind erlaubt? (Pure HTML? Bootstrap? Custom?)
  
  #### Implementierungs-Schritte:
  - [ ] POC 1: IFRAME-Ansatz mit einfachem Taschenrechner testen
  - [ ] POC 2: Direct DOM mit `js.document` testen
  - [ ] Performance & Isolation vergleichen
  - [ ] Didaktisches Beispiel ausarbeiten
  - [ ] Testing-Strategy für UI-Aufgaben definieren (Screenshot-Tests? DOM-Assertions?)

**Exit Criteria**: 
- ✅ Aufgaben mit `input()` kommen regulär vor (2.1/2.2)
  - ✅ `input()` funktioniert in allen Editor-Modi
  - ✅ Dokumentation verfügbar
  - ✅ 4+ Beispiel-Aufgaben bereit
- ⏳ Mindestens 1 interaktive UI-Aufgabe funktioniert produktiv (2.3 - IN DESIGN)

**Phase Status**: 🟡 Partial Complete (2.1 ✅ | 2.2 ✅ | 2.3 🔵 Design Phase)

---

## ⚪ Phase 3: AI Task Generator Upgrade (2 Wochen)

### Task 3.1: Task-Konfigurator bauen
- **Ziel**: Nutzer konfiguriert Task-Eigenschaften, AI sieht gefilterte Docu
- **Aufwand**: 3-4h
- **Status**: ⏳ Waiting for Phase 1
- **Details**:
  - [ ] Modal mit Fragen entwerfen:
    - Task-Typ? (code, quiz, code_reading, code_random_complex)
    - Iterationen? (1, 3, 5, ...)
    - Test-Art? (output, function, variable, intelligent, code_check)
    - Schwierigkeit? (easy, medium, hard)
  - [ ] "AI Context Prompt" erzeugen (filtered docstring)
  - [ ] Konfigurator in AI-Generator Modal integrieren

### Task 3.2: AI Generator mit Konfigurator updaten
- **Ziel**: AI bekommt nur relevante Dokumentation + Konfiguration
- **Aufwand**: 2-3h
- **Status**: ⏳ Waiting for Phase 1
- **Details**:
  - [ ] Konfigurator-Output parsen
  - [ ] Docu-Snippets nach Task-Typ filtern
  - [ ] AI-Prompt mit Konfiguration erweitern
  - [ ] Mit 5-10 Beispiel-Aufgaben testen

**Exit Criteria**: AI-generierte Aufgaben sind konsistent & reproduzierbar

---

## ⚪ Phase 4: Engagement Metrics (Laufend)

### Task 4.1: Analytics Dashboard erweitern
- **Ziel**: Nachvollziehen, wer "wirklich" arbeitet vs. durchklickt
- **Aufwand**: 3-4h
- **Status**: ⏳ Waiting for Phase 1
- **Details**:
  - [ ] **Zeit-Tracking**: Session-Dauer pro Task
  - [ ] **Fehlerrate**: Erste ≠ letzte Versuch? (Learning-Indicator?)
  - [ ] **Versuchsmuster**: Abstände zwischen Versuchen (durchdacht vs. rapid-fire?)
  - [ ] **Hint-Nutzung**: Wie viele Tipps pro Student?
  - [ ] **Code-Komplexität**: LOC, Verschachtelung (Copy-Paste Detector?)
  - [ ] Heatmaps für verdächtige Pattern

### Task 4.2: Alert-System für Anomalien
- **Ziel**: Admin wird auf verdächtige Muster hingewiesen
- **Aufwand**: 2h
- **Status**: ⏳ Waiting for Phase 1
- **Details**:
  - [ ] Rule-Engine für Anomalien (z.B. "0 sec elapsed, 0 errors, perfect first try")
  - [ ] Admin-Alerts generieren
  - [ ] Optional: Student-View für Motivation

**Exit Criteria**: Admin sieht realistische Arbeits-Metriken

---

## 🔧 Technical Debt & Known Issues

### Current Blockers
- [ ] Projekt-Editor buggy (Phase 1)
- [ ] Keine `input()`-Unterstützung
- [ ] AI-Generator nutzt komplette Docu (zu viel Kontext)

### Future Improvements
- [ ] File-Upload für Projekte
- [ ] Collaborative Editing
- [ ] Code-Linting Integration
- [ ] Performance-Optimierung für große Projekte

---

## 📊 Metrics & Success Criteria

| Phase | KPI | Target |
|-------|-----|--------|
| P1 | Editor-Uptime | 99% |
| P2 | `input()` Tasks created | 5+ |
| P3 | AI-generated consistent tasks | 80%+ |
| P4 | Engagement Detection Accuracy | 90%+ |

---

## 🔗 Related Documents

- [Task Types Documentation](docs/test-types-documentation-v2.md)
- [Export/Import Schema](docs/taskexport.md)
- [Projekt-Editor Current Code](public/editor.php)

---

## 📝 Notes

### Phase 1 Focus Questions
- [ ] Was genau ist im Projekt-Editor buggy? (Crashes? Falsche Outputs? Design-Issues?)
- [ ] Welche neuen Features wurden zuletzt hinzugefügt?
- [ ] Gibt es Regression-Tests?

### Phase 3 AI Scope
- **Nur Task-Definition** generieren: title, task_text, hints, test_cases
- **Kein Code-Beispiel** generieren (zu error-prone)
- AI schlägt Struktur vor, User schreibt Code

### Phase 4 Implementation
- Zeit tracking: `user_tasks` table mit `time_spent_seconds`
- Fehleranalyse: erste vs. letzte Versuche vergleichen
- Anomaly Detection: einfache Rules zunächst (später ML?)

---

**Last Checkpoint**: ✅ Phase 2 Tasks 2.1 & 2.2 Complete - `input()` Support aktiv!  
**Next Checkpoint**: Phase 2 Task 2.3 - UI-Elemente Diskussion & POC  
**Current Sprint**: Phase 2 (2/3 Complete) | Phase 3 Ready to Start
