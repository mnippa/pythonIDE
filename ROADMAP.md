# 🚀 pythonIDE Roadmap 2026

> **Status**: In Progress | **Last Updated**: Feb 24, 2026

---

## 📋 Phase Overview

| Phase | Fokus | Dauer | Status |
|-------|-------|-------|--------|
| **Phase 1** | Unblock & Stabilize | Diese Woche | 🔴 Active |
| **Phase 2** | User Input Support | Nächste Woche | ⚪ Waiting |
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
- **Status**: ⏳ Waiting for Phase 1
- **Details**:
  - [ ] Pyodide stdin Configuration researchen
  - [ ] Popup-Modal für Eingaben implementieren
  - [ ] Alternativ: Vorab-Input-Feld
  - [ ] mit 2-3 Test-Programmen validieren

### Task 2.2: Input-Testing Pattern freigeben
- **Ziel**: Aufgabenersteller kennen beste Practices
- **Aufwand**: 30m
- **Status**: ⏳ Waiting for Phase 1
- **Details**:
  - [ ] Keyword-Check (`input`, type-conversion) + Output-Test Documentation
  - [ ] Beispiel-Aufgaben erstellen
  - [ ] in Admin-Docu hinzufügen

**Exit Criteria**: Aufgaben mit `input()` kommen regulär vor

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

**Last Checkpoint**: Fertig mit Task-Export/Import Fixes ✅  
**Next Checkpoint**: Phase 1 Task 1.1 starten  
**Deadline Phase 1**: [TBD]
