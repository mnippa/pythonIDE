---
name: "PythonIDE-Task-Authoring"
description: "Use when creating, revising, exporting, importing, or validating assignments and tasks for PythonIDE, including folder tasks and test-case design."
argument-hint: "Beschreibe die Aufgabe oder das Authoring-Ziel"
agent: "agent"
model: "GPT-5 (copilot)"
---
Arbeite ausschliesslich mit Fokus auf Task- und Assignment-Authoring fuer PythonIDE.

Projektpfad:
- `c:\xampp\htdocs\pythonIDE`

Wichtige Arbeitsbereiche im Projekt:
- `docs/` fuer Task-Authoring-, Test- und Export/Import-Dokumentation
- `tasks/` fuer JSON-Beispiele und importierbare Task-Dateien
- `scripts/` fuer Task-Erzeugung, Migrationen und Hilfsskripte
- `storage/tasks/folders/` fuer folder-basierte Task-Dateien
- `api/` fuer Task-, Assignment- und Folder-File-Endpunkte

Primärer Fokus dieses Chats:
- neue Aufgaben entwerfen und formulieren
- passende Feldbelegung fuer `title`, `task_text`, `description`, `stoff`
- Teststrategien mit `variable`, `output`, `function`, `code_check`, `intelligent`
- Assignment-/Task-JSON fuer Import und Export
- folder-basierte Tasks mit Zusatzdateien und Read-only-Policies

Nicht der Hauptfokus dieses Chats:
- allgemeine Plattform-Refactorings ohne direkten Authoring-Bezug
- Admin-/Deploy-Themen ohne Bezug zu Aufgaben
- tiefe Architekturarbeit ausser soweit sie fuer Task-Runtime oder Validierung relevant ist

Starte immer mit dem aktuellen Authoring-Kontext:
- [Task Authoring Guide](../../docs/TASK_AUTHORING_GUIDE.md)
- [Aktueller Gesamt-Kontext](../../docs/CONTEXT_CURRENT.md)
- [Task Export Format](../../docs/taskexport.md)
- [Test Types Doku](../../docs/test-types-documentation-final-v2.md)

Arbeitsweise:
- halte `task_text` kurz und student-facing
- verschiebe Nebenbedingungen nach `description`
- bevorzuge robuste Validierung mit `variable`-Tests, wenn Werte direkt pruefbar sind
- nutze `code_check` nur fuer echte Strukturvorgaben
- bei folder-basierten Tasks immer Dateispeicher und Pyodide-Runtime mitdenken

Wenn die Anfrage eigentlich einen Plattform-Bug, Dashboard-Flow, Deploy-Prozess oder API-Refactoring betrifft, weise auf den Prompt `PythonIDE-Weiterentwicklung` als passenderen Einstieg hin.