# Architektur: Template/Solution, Runtime und Persistenz

## Ziel
Diese Doku beschreibt die aktuelle, stabile Architektur fuer:

- Template/Solution-Modus in Aufgaben mit Ordnerstruktur
- sauberen Runtime-Wechsel (Pyodide, Imports, sys.path)
- persistente und nicht-persistente Aenderungen (Student, Admin, Testmodi)
- robustes Toggle-Verhalten ohne Race Conditions

Zusatznutzen: Die Logik ist so aufgeteilt, dass sie spaeter gezielt extrahiert werden kann.

## Kernprinzipien

1. Modus-Scope strikt trennen: template und solution haben getrennte Draft- und Snapshot-States.
2. Unsaved-Aenderungen sollen bei Dateiwechsel erhalten bleiben, aber nicht zwischen Taskwechseln leaken.
3. Runtime darf niemals mit falschem Scope laufen: vor Run werden stale Imports und falsche sys.path-Eintraege bereinigt.
4. Toggle darf nicht doppelt feuern: ein Handler, ein In-Progress-Lock.

## Wichtige Dateien

- Frontend State und Editor-Flow: public/js/assignments.js
- Run-Pipeline und Runtime-Payload: public/js/editor-setup.js

## Scope-Architektur (Template vs Solution)

### State-Key

In public/js/assignments.js wird Scope ueber taskId::scope gekeyed.

- getTaskModeScope() -> template oder solution
- getTaskStateKey(taskId, scope)

### Scope-spezifische Caches

- Drafts: setTaskDraftContentForScope(), getTaskDraftContentForScope()
- Saved Snapshots: setTaskSavedSnapshotForScope(), getTaskSavedSnapshotForScope()

Wirkung:

- Template und Solution koennen dieselbe Datei init.py oder function.py haben, ohne sich gegenseitig zu ueberschreiben.
- Unsaved-Aenderungen bleiben innerhalb task+scope erhalten.

## Dateiwechsel und Unsaved-Verhalten

### Dateiwechsel

openTaskFileInEditor(taskId, path) friert den Scope zu Beginn ein (scopeAtOpenStart) und arbeitet danach nur in diesem Scope.

Das verhindert:

- spaete Async-Antworten aus altem Scope, die den neuen Scope ueberschreiben
- Cross-Scope-Draft-Verwechslungen

### Unsaved ueber Dateiwechsel

Beim Wechsel wird vorher der aktuelle Editor-Draft gecacht (cacheCurrentEditorDraft()).

Beim Oeffnen einer Datei gilt Reihenfolge:

1. Draft aus Scope
2. Snapshot aus Scope
3. API Read

Damit bleiben Unsaved-Aenderungen bei Dateiwechsel erhalten, auch ohne Speichern.

## Toggle-Stabilitaet

In showTaskDetails() wurde der Toggle stabilisiert:

- nur ein onclick-Handler statt mehrfacher Bindings
- Lock assignmentState._solutionToggleInProgress gegen Re-Entry

Wirkung:

- ein Klick entspricht genau einem Moduswechsel
- keine Toggle-Kaskaden mehr bei haeufigem Umschalten

## Runtime-Sync und Import-Bereinigung

### Vor RUN

beforeAssignmentRunExecution() in public/js/assignments.js:

- loggt RUN_DEBUG Snapshots
- entfernt vor dem Run falsche sys.path-Teile (solution in template und umgekehrt)
- entfernt stale Module aus sys.modules anhand des Runtime-Pfads

### Beim Dateisync nach Pyodide

syncFolderTaskFilesToPyodide(pyodide, taskId, preferredMainPath):

- friert Scope ein (scopeAtSyncStart)
- baut Runtime-Verzeichnis passend zum Scope
- nutzt Drafts fuer alle Dateien im Scope

Wirkung:

- der Run sieht denselben Code wie der Editor (inklusive unsaved Dateiwechsel)
- keine Scope-Vermischung im Runtime-Dateibaum

### In der Run-Pipeline

buildFolderTaskRuntimePayload() in public/js/editor-setup.js:

- ermittelt modeScope je nach Seite/Modus
- liest Dateien mode-spezifisch
- nutzt Draft-Inhalte, wenn vorhanden

Zusatz-Cleanup in editor-setup.js:

- pre-run Bereinigung von /task_runtime-Imports
- Template/Solution-Fremdmodule werden entfernt

## Monaco-Modell-Stabilitaet

In public/js/assignments.js wurde von dispose/recreate auf Model-Reuse pro URI umgestellt.

Wirkung:

- deutlich weniger Canceled-Fehler bei schnellem Datei- und Moduswechsel
- stabileres Editorverhalten unter Stress

## Persistenz-Matrix

### 1) Admin Task-Lab (editor_assignment_test ohne test_user_id)

- Template-Modus:
  - init.py speichert in tasks.code_template
  - Dateien speichern in Template-Folder
- Solution-Modus:
  - init.py speichert in tasks.solution_code
  - Dateien speichern in .solution Overlay

### 2) Admin User-Test (editor_assignment_user_test mit test_user_id)

- keine permanenten Template/Solution-Updates
- Speichern geht als Student-Override ueber user_tasks (mit test_user_id)
- im Solution-Modus wird Speichern absichtlich blockiert (nur Studentencode erlaubt)

### 3) Student Normalbetrieb (assignments.php)

- Laden:
  - zuerst user_tasks.current_code
  - fallback auf task.code_template
- Speichern:
  - user_tasks.update current_code
- Ergebnis:
  - wenn leer, startet Student mit Template
  - danach immer persistenter eigener Stand

## Zu deiner Frage: assignment-test-user und Studentenfluss

Ja, die aktuelle Logik ist dafuer ausgelegt.

1. assignment-test-user:
- keine permanenten Aenderungen an task solution/template
- Aenderungen gehen in Student-Kontext (test_user_id)
- Solution-Speichern ist im User-Test-Modus geblockt

2. normale Studierenden-Bearbeitung:
- laedt Template, wenn kein gespeicherter Studentencode existiert
- sonst laedt persistierten Studentencode

## Extraktionsstrategie (spaeter herausziehen)

Wenn diese Architektur spaeter separat ausgelagert werden soll, dann in 4 Bloecken:

1. Scope-State-Modul
- getTaskModeScope, State-Key, Draft/Snapshot API

2. File-Open und Save-Orchestrierung
- openTaskFileInEditor, cacheCurrentEditorDraft, saveTaskFile, saveCode

3. Runtime-Sync-Modul
- syncFolderTaskFilesToPyodide, beforeAssignmentRunExecution Cleanup

4. Toggle/UI-Controller
- showTaskDetails Toggle-Teil inkl. _solutionToggleInProgress

Empfehlung:

- zuerst API-Schnittstellen einfrieren
- dann Funktionen 1:1 in neue Module verschieben
- zuletzt nur Imports verdrahten, Verhalten unveraendert lassen

## Regressions-Checkliste

Nach Aenderungen immer pruefen:

1. Toggle template -> solution -> template liefert jeweils korrekten Output.
2. Unsaved in function.py bleibt nach Wechsel auf spielfeld.py erhalten.
3. Run nutzt den sichtbaren Scope-Code, nicht den vorherigen.
4. Kein Canceled-Fehler-Spam bei schnellem Toggle/Run.
5. assignment-test-user schreibt nicht in task solution/template.
6. Student laedt bei leerem Stand Template, sonst eigenen persistierten Stand.
