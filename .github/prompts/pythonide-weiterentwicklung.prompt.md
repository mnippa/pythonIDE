---
name: "PythonIDE-Weiterentwicklung"
description: "Use when working on PythonIDE platform development, architecture, runtime, APIs, dashboard logic, deploy flow, or bug fixes in the current workspace."
argument-hint: "Beschreibe das Entwicklungsziel oder den Bug in PythonIDE"
agent: "agent"
model: "GPT-5 (copilot)"
---
Arbeite ausschliesslich mit Fokus auf die Weiterentwicklung von PythonIDE als Plattform.

Projektpfad:
- `c:\xampp\htdocs\pythonIDE`

Wichtige Arbeitsbereiche im Projekt:
- `public/` fuer UI, Editor, Admin und Browser-Integration
- `public/js/` fuer Dashboard-, Assignment-, Editor- und Pyodide-Logik
- `api/` fuer PHP-Endpunkte
- `scripts/` fuer Admin-, Daten- und Wartungsskripte
- `storage/` fuer folder-basierte Task-Dateien und Laufzeitdaten
- `docs/` fuer aktuelle Architektur- und Kontextdokumentation

Primärer Fokus dieses Chats:
- PythonIDE-Architektur und Plattformlogik
- Monaco-, Pyodide- und Laufzeitverhalten
- Assignment-Dashboard, Statuslogik und Progress-Flow
- Admin-Bereich, Deploy-Sync, Berechtigungen, APIs
- Bugfixes, Refactoring, technische Dokumentation

Nicht der Hauptfokus dieses Chats:
- neue Lernaufgaben inhaltlich entwerfen
- didaktische Ausarbeitung einzelner Tasks
- reine Task-Authoring-Arbeit ohne Plattformbezug

Starte immer mit dem aktuellen Plattformkontext:
- [Aktueller Gesamt-Kontext](../../docs/CONTEXT_CURRENT.md)
- [Architektur](../../docs/architecture.md)
- [README Doku-Einstieg](../../docs/README.md)

Arbeitsweise:
- behandle dieses Repo als die aktive Quelle der Wahrheit
- pruefe bestehende Implementierung vor konzeptionellen Aenderungen
- bevorzuge root-cause fixes statt Workarounds
- halte Task-Authoring-Fragen nur insoweit im Blick, wie sie die Plattform betreffen

Wenn die Anfrage eigentlich neue Aufgaben oder Testfaelle entwerfen soll, weise auf den Prompt `PythonIDE-Task-Authoring` als passenderen Einstieg hin.