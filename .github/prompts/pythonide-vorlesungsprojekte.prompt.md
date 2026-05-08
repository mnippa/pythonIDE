---
name: "PythonIDE-Vorlesungsprojekte"
description: "Use when working on lecture projects/programs inside PythonIDE, including staged project progression, project runtime behavior, and project-folder workflows."
argument-hint: "Beschreibe das Vorlesungsprojekt, den Entwicklungsschritt oder den Bug"
agent: "agent"
model: "GPT-5 (copilot)"
---
Arbeite ausschliesslich mit Fokus auf Vorlesungsprojekte innerhalb von PythonIDE.

Projektpfad:
- `c:\xampp\htdocs\pythonIDE`

Wichtige Arbeitsbereiche im Projekt:
- `public/` fuer Projekt-UI, Editor, Run/Check-Flow und Browser-Integration
- `public/js/` fuer Monaco-, Pyodide-, Worker- und Laufzeitlogik bei Projekten
- `api/` fuer Projekt-Endpunkte, File-/Folder-Zugriffe und Persistenz
- `scripts/` fuer Projektmigrationen, Datenpflege und Wartung
- `storage/` fuer Projektdateien, folder-basierte Laufzeitdaten und Assets
- `docs/` fuer Architektur-, Runtime- und Projektkontextdokumentation

Primaerer Fokus dieses Chats:
- Vorlesungsprojekte und Programme, die in PythonIDE ausgefuehrt werden
- didaktisch aufbauende Projektreihen (mehrere Schritte/Ordner/Versionen)
- Projektordner-Strukturen, Includes, gemeinsame Hilfsmodule, Assets
- Run-Verhalten im rechten Outputbereich (outputClear/outputFlush/input/plot)
- WebWorker-, Pyodide- und Runtime-Unterschiede zwischen Aufgaben und Projekten
- Bugfixes, Refactoring und technische Qualitaet fuer Projekt-Workflows

Nicht der Hauptfokus dieses Chats:
- neue Einzelaufgaben oder Assignment-Testfaelle ohne Projektbezug
- reine Task-Authoring-Arbeit fuer Aufgaben
- allgemeine Plattformthemen ohne Bezug zu Vorlesungsprojekten

Starte immer mit dem aktuellen Plattformkontext:
- [Aktueller Gesamt-Kontext](../../docs/CONTEXT_CURRENT.md)
- [Architektur](../../docs/architecture.md)
- [README Doku-Einstieg](../../docs/README.md)
- [Code-UI Architektur](../../docs/code-ui-architecture.md)
- [IDEGUI Programming Guide](../../docs/idegui-programming-guide.md)

Arbeitsweise:
- behandle dieses Repo als die aktive Quelle der Wahrheit
- pruefe zuerst bestehende Projektimplementierung und Datenhaltung (project_folders/project_files)
- bevorzuge root-cause fixes statt Projekt-spezifischer Workarounds
- halte didaktische Progression konsistent ueber Projektstufen hinweg
- sichere Kompatibilitaet fuer Browser/Pyodide/Worker-Run im rechten Outputbereich

Wenn die Anfrage eigentlich Assignment-/Task-Authoring betrifft, weise auf den Prompt `PythonIDE-Task-Authoring` als passenderen Einstieg hin.
Wenn die Anfrage primaer Plattform-Architektur oder Admin-/Deploy-Themen betrifft, weise auf den Prompt `PythonIDE-Weiterentwicklung` als passenderen Einstieg hin.
