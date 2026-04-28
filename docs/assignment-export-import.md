# Assignment Import/Export Format

## Hinweis zum aktuellen Stand

Diese Datei beschreibt das JSON-Grundformat fuer Assignment-Export und -Import, aber nicht alle spaeter hinzugekommenen Plattformdetails.

Vor dem Erstellen neuer Inhalte zusaetzlich lesen:
- [TASK_AUTHORING_GUIDE.md](TASK_AUTHORING_GUIDE.md)
- [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)

Wichtig:
- Felder und Laufzeitverhalten fuer Folder-Tasks und `code_ui` gehen ueber dieses reine JSON-Schema hinaus
- Dateiinhalte ausserhalb von `init.py` koennen im Filesystem liegen und muessen fuer die Runtime separat beruecksichtigt werden

## Übersicht

Das Assignment-Format ermöglicht den Export und Import von kompletten Assignments mit allen zugehörigen Tasks als JSON-Datei.

## Verwendung

### Export
1. Im Admin-Dashboard zur "Assignments"-Sektion navigieren
2. Auf "📤 Export" beim gewünschten Assignment klicken
3. JSON-Datei wird heruntergeladen

### Import
1. Im Admin-Dashboard zur "Assignments"-Sektion navigieren
2. Auf "📥 Import JSON" klicken
3. JSON-Datei auswählen
4. Assignment wird mit allen Tasks importiert

## JSON-Format

```json
{
  "version": "1.0",
  "exported_at": "2026-02-10 12:00:00",
  "assignment": {
    "title": "Assignment-Titel",
    "description": "Beschreibung des Assignments",
    "difficulty": "beginner|intermediate|advanced",
    "is_active": true|false
  },
  "tasks": [
    {
      "position": 1,
      "title": "Task-Titel",
      "description": "Task-Beschreibung",
      "type": "code_completion|code_fix|multiple_choice|essay",
      "template": "Code-Template",
      "hint1": "Erster Hinweis",
      "hint2": "Zweiter Hinweis",
      "hint3": "Dritter Hinweis",
      "stoff": "Lerninhalt/Ressourcen",
      "validation_mode": "strict|loose|intelligent",
      "test_cases": [...],
      "solution": "Musterlösung"
    }
  ]
}
```

## Felder-Beschreibung

### Assignment

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|--------------|
| `version` | String | Ja | Format-Version (aktuell: "1.0") |
| `exported_at` | String | Nein | Zeitstempel des Exports |
| `assignment.title` | String | Ja | Titel des Assignments |
| `assignment.description` | String | Nein | Beschreibung |
| `assignment.difficulty` | String | Nein | Schwierigkeitsgrad (default: "beginner") |
| `assignment.is_active` | Boolean | Nein | Aktiv-Status (default: true) |

### Tasks

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|--------------|
| `position` | Integer | Nein | Reihenfolge (default: 1) |
| `title` | String | Nein | Task-Titel |
| `description` | String | Nein | Task-Beschreibung |
| `type` | String | Nein | Task-Typ (default: "code_completion") |
| `template` | String | Nein | Code-Vorlage für Studenten |
| `hint1`, `hint2`, `hint3` | String | Nein | Hilfestellungen |
| `stoff` | String | Nein | Verwandte Lerninhalte |
| `validation_mode` | String | Nein | Validierungsmodus |
| `test_cases` | Array | Nein | Test-Szenarien |
| `solution` | String | Nein | Musterlösung |

## Test Cases Format

### OUTPUT Test (Console Output)
```json
{
  "type": "output",
  "test_cases": [
    {
      "input": "",
      "expected": "Hello World"
    }
  ]
}
```

### FUNCTION Test (Return Values)
```json
{
  "type": "function",
  "function": {
    "name": "addiere",
    "test_cases": [
      {
        "args": [2, 3],
        "expected": 5
      }
    ]
  }
}
```

### VARIABLE Test (Variable Values)
```json
{
  "type": "variable",
  "init_var_names": ["x"],
  "expected_var_names": ["result"],
  "test_cases": [
    {
      "init_values": [5],
      "expected_values": [25]
    }
  ]
}
```

### INTELLIGENT Test (Solution Comparison)
```json
{
  "type": "intelligent",
  "function": {
    "name": "funktion_name"
  }
}
```

## Beispiele

Siehe `assignment-format-example.json` für ein vollständiges Beispiel mit verschiedenen Task-Typen.

## API-Endpunkte

- **Export**: `GET api/admin/assignments/export.php?id={assignment_id}`
- **Import**: `POST api/admin/assignments/import.php` (JSON im Body)

## Hinweise

- Beim Import wird ein **neues** Assignment erstellt (keine ID-Konflikte)
- Vorhandene Test-Cases werden automatisch migriert
- UTF-8 Encoding wird unterstützt (Umlaute, Sonderzeichen)
- Große Assignments können mehrere MB groß werden
