# Tasks/Problems Structure - Dokumentation

## Hinweis zum aktuellen Stand

Diese Datei enthält weiterhin nützliche Strukturhinweise für Tasks und Inhaltsfelder.
Einige Statusbezeichnungen und ältere Flows entsprechen aber nicht mehr exakt dem aktuellen UI- und Runtime-Verhalten.

Für den aktuellen Produktstand zuerst lesen:
- [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)
- [ASSIGNMENTS_API_DOCUMENTATION.md](ASSIGNMENTS_API_DOCUMENTATION.md)

Besonders wichtig:
- Assignment-Status und Task-Status sind zwei verschiedene Ebenen.
- Folder-Tasks nutzen heute eine Kombination aus DB, Filesystem und Pyodide-Runtime-Sync.

## 🏗️ Aufbau: Assignment → Tasks

```
Assignment (Sammlung)
│
├─ Task 1 (position: 1)
│  ├─ Titel: "Variablen deklarieren"
│  ├─ Typ: code_completion
│  ├─ Template: x = ___
│  └─ Erwartetes Output: x = 42
│
├─ Task 2 (position: 2)
│  ├─ Titel: "Schleifen schreiben"
│  ├─ Typ: code_completion
│  └─ ...
│
└─ Task N (position: N)
   └─ ...
```

## � Pflichtschema: Titel / Aufgabentext / Details

Jede Aufgabe hat drei Inhaltsfelder. **Alle drei müssen bewusst befüllt werden.**

| Feld (DB) | Wo sichtbar | Zweck | Regel |
|-----------|-------------|-------|-------|
| `title` | Aufgabenliste + Header | Thema, prägnant | Max. ~60 Zeichen, kein ganzer Satz |
| `task_text` | **Über dem Editor** (groß) | Präzise Aufgabenstellung | Sagt genau, was der Student tun soll |
| `description` | Details-Tab (Seitenleiste) | Erläuterung / Zusatzkontext | Kann `task_text` vertiefen, ist optional |
| `stoff` | Details-Tab (Seitenleiste) | Lerninhalt / Theoriebezug | Optional, z.B. Kapitelangabe |

### Beispiele (richtig vs. falsch)

```
❌ FALSCH:
  title:      "Der Student gibt das dritte Element einer Liste mit 4 Fächern aus"
  task_text:  "Liste"
  description: (leer)

✅ RICHTIG:
  title:      "Fächerliste: drittes Element"
  task_text:  "Definiere eine Liste mit genau 4 Fächernamen. Gib das dritte Fach (Index 2) aus."
  description: "Verwende den Index 2, da Python-Listen bei 0 beginnen. Das dritte Element hat also den Index 2."
```

### Regel in einem Satz

> `title` = Thema für die Liste · `task_text` = Was ist zu tun? (präzise, kurz) · `description` = Warum / Wie (wenn nötig)

---

## 📊 Tabellen-Schema

### tasks Tabelle

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `id` | INT UNSIGNED | Eindeutige Task-ID |
| `assignment_id` | INT UNSIGNED | Bezug zur Assignment |
| `title` | VARCHAR(255) | Thema, prägnant für die Aufgabenliste |
| `task_text` | TEXT | Aufgabenstellung über dem Editor – präzise, kurz |
| `description` | TEXT | Details-Tab: Erläuterung, wenn `task_text` nicht reicht |
| `stoff` | TEXT | Details-Tab: Lerninhalt / Theoriebezug |
| `position` | INT | Reihenfolge innerhalb Assignment (1, 2, 3, ...) |
| `problem_type` | ENUM | Art der Aufgabe (siehe unten) |
| `code_template` | MEDIUMTEXT | Starter-Code für Schüler |
| `hint1/2/3` | TEXT | Hinweise (werden einzeln aufgedeckt) |
| `expected_output` | TEXT | Erwartete Ausgabe (legacy, besser test_cases nutzen) |
| `created_at` | TIMESTAMP | Erstellungsdatum |
| `updated_at` | TIMESTAMP | Letztes Änderungsdatum |

### Problem-Typen

```
code_completion  - Code vervollständigen (Lücken füllen)
code_fix         - Code korrigieren (Bug beheben)
multiple_choice  - Multiple Choice Frage
essay            - Freie Text-Antwort
```

### user_tasks Tabelle (Progress-Tracking)

Hinweis: Die untenstehenden Statuswerte stammen aus einer älteren Modellbeschreibung.
Im aktuellen Frontend treten u. a. `unbearbeitet`, `in-progress`, `passed`, `failed` als Task-Progress-Werte auf.

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `id` | INT UNSIGNED | Eindeutige Zuordnungs-ID |
| `user_id` | INT UNSIGNED | Schüler-ID |
| `task_id` | INT UNSIGNED | Task-ID |
| `status` | ENUM | `not_started`, `in_progress`, `completed`, `failed` |
| `user_code` | MEDIUMTEXT | Schülerlösung |
| `attempts` | INT | Anzahl Versuche |
| `submitted_at` | TIMESTAMP | Einreichungszeit |
| `completed_at` | TIMESTAMP | Abschlusszeit |
| `test_results` | JSON | Test-Ergebnisse als JSON |
| `feedback` | TEXT | Automatisches oder manuelles Feedback |

## 💾 Beispiel-Datenstruktur

### Assignment erstellen
```sql
INSERT INTO assignments (title, description, created_by, is_active, difficulty)
VALUES ('Python Basics', 'Einführung in Python', 1, TRUE, 'beginner');
```
→ `assignment_id = 1`

### Tasks hinzufügen
```sql
INSERT INTO tasks (assignment_id, title, description, position, problem_type, code_template, expected_output)
VALUES 
(1, 'Hello World', 'Schreibe dein erstes Programm', 1, 'code_completion', 
 'print("___")', 'Hello World'),
(1, 'Variablen', 'Verwende Variablen', 2, 'code_completion',
 'name = "___"\nprint(name)', 'Max'),
(1, 'Schleifen', 'Schreibe eine Schleife', 3, 'code_completion',
 'for i in range(___):\n    print(i)', '0\n1\n2\n3\n4');
```

### Schüler löst Task
```sql
-- Task wird in Bearbeitung genommen
INSERT INTO user_tasks (user_id, task_id, status, user_code)
VALUES (2, 1, 'in_progress', 'print("Hello World")');

-- Task wird eingereicht
UPDATE user_tasks 
SET status = 'completed', 
    submitted_at = NOW(),
    attempts = 1
WHERE user_id = 2 AND task_id = 1;
```

## 🔄 API-Endpoints (geplant)

### Task abrufen
```
GET /api/tasks/{task_id}
```

### User-Task status
```
GET /api/user-tasks/{user_id}/{assignment_id}
```

### Task einreichen
```
POST /api/user-tasks/{task_id}/submit
Body: { code: "...", test_results: {...} }
```

### Alle Tasks einer Assignment
```
GET /api/assignments/{assignment_id}/tasks
```

## 📈 Status-Flow für Schüler

```
not_started
    ↓
in_progress  ← Schüler startet Task
    ↓
submitted    ← Code eingereicht
    ↓
[completed]  ← Alle Tests bestanden
    ↓
[failed]     ← Tests nicht bestanden
```

## 🎯 Beispiel: "Python Basics" Assignment

```
Assignment: "Python Basics"
Status: aktiv
Schwierigkeit: beginner
Autor: Sarah Schmidt (admin)

├─ Task 1: Hello World (position: 1)
│  Typ: code_completion
│  Template: print("___")
│  Hint: "Der String geht in Anführungszeichen"
│  Expected: Hello World
│
├─ Task 2: Variablen (position: 2)  
│  Typ: code_completion
│  Template: name = "___"
│           print(name)
│  Hint: "Setze deinen Namen ein"
│  Expected: Max
│
└─ Task 3: Schleifen (position: 3)
   Typ: code_completion
   Template: for i in range(___):
                 print(i)
   Hint: "Nutze range() für eine Zahl"
   Expected: 0...9
```

## 📝 Für Schüler

1. **Task öffnen** - Position und Anforderungen lesen
2. **Code vervollständigen** - Template mit Lösung füllen
3. **Test ausführen** - Expected Output prüfen
4. **Einreichen** - Lösung abspeichern
5. **Feedback** - Automatische oder manuelle Rückmeldung

## 🔧 Verwaltung (für Admin)

1. **Assignment erstellen** - Sammlung für Aufgaben
2. **Tasks definieren** - Einzelne Aufgaben mit Templates
3. **Reihenfolge setzen** - Position-Feld für Sortierung
4. **Test-Cases** - Expected Output definieren
5. **Monitoring** - user_tasks für Schüler-Fortschritt ansehen
