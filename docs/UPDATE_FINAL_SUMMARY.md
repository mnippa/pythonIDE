# 🎓 Python IDE - Datenbankschema Update v2 - FINAL

## ✅ Änderungen (08.02.2026)

### 1️⃣ Semester-System **KORRIGIERT**

**Logik ist jetzt korrekt:**

| Datum | Berechnet | Semester |
|-------|-----------|----------|
| 08.02.2026 | Februar → WiSe {2025}{2026} | **WiSe2526** ✓ |
| 01.03.2026 | März → SoSe {2026} | **SoSe26** ✓ |
| 01.10.2026 | Oktober → WiSe {2026}{2027} | **WiSe2627** ✓ |
| 28.02.2027 | Februar → WiSe {2026}{2027} | **WiSe2627** ✓ |

**Formula:**
- `03-09`: `SoSe{YY}`
- `10`: `WiSe{YY}{YY+1}`
- `01-02`: `WiSe{YY-1}{YY}`

**Test bestätigt:** ✅ 6/6 Fälle korrekt

---

### 2️⃣ User-Status Management

**users Tabelle erweitert:**
- `registration_date` - Registrierungsdatum
- `status` - ENUM('aktiv', 'archiviert')

**Alle 5 Testbenutzer:**
- Status: `aktiv`
- Semester: `WiSe2526` (aktuell)

---

### 3️⃣ Tasks/Problems-Struktur **VOLLSTÄNDIG**

#### Neue Tabelle: `tasks`
```
id          INT UNSIGNED (PK)
assignment_id  INT UNSIGNED (FK)
title       VARCHAR(255)
description TEXT
position    INT (1, 2, 3, ...)
problem_type ENUM(code_completion, code_fix, multiple_choice, essay)
code_template MEDIUMTEXT
hint        TEXT
expected_output TEXT
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

#### Neue Tabelle: `user_tasks`
```
id          INT UNSIGNED (PK)
user_id     INT UNSIGNED (FK)
task_id     INT UNSIGNED (FK)
status      ENUM(not_started, in_progress, completed, failed)
user_code   MEDIUMTEXT
attempts    INT
submitted_at TIMESTAMP
completed_at TIMESTAMP
test_results JSON
feedback    TEXT
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

---

## 🏗️ Struktur: Assignment → Tasks

```
Assignment "Python Basics"
│
├─ Task 1: Hello World (position: 1)
│  - Type: code_completion
│  - Template: print("___")
│  - Expected: Hello World
│
├─ Task 2: Variablen (position: 2)
│  - Type: code_completion
│  - Template: name = "___"\nprint(name)
│  - Expected: Max
│
└─ Task 3: Schleifen (position: 3)
   - Type: code_completion
   - Template: for i in range(___):\n    print(i)
   - Expected: 0-9
```

**Schüler-Perspektive:**
1. Assignment öffnen → alle Tasks sehen
2. Task 1 starten → Code vervollständigen
3. Code einreichen → Test-Ergebnis
4. Task 2 / 3 / ...
5. Assignment-Fortschritt: 3/3 Tasks completed ✅

---

## 📂 Neue/Aktualisierte Dateien

### Skripte
- ✅ [scripts/load_testdata.php](../scripts/load_testdata.php) - Testdaten laden (mit status)
- ✅ [scripts/update_schema.php](../scripts/update_schema.php) - Schema aktualisieren
- ✅ [scripts/create_tasks_table.php](../scripts/create_tasks_table.php) - Tasks-Struktur

### Konfiguration
- ✅ [config/semester.php](../config/semester.php) - Semester-Hilfsfunktionen (KORRIGIERT)

### API
- ✅ [api/system/semester.php](../api/system/semester.php) - Semester-Endpoints (mit richtigen Beispielen)

### Dokumentation
- ✅ [docs/DATABASE_SCHEMA_UPDATE.md](DATABASE_SCHEMA_UPDATE.md) - Detailliertes Schema
- ✅ [docs/TASKS_STRUCTURE.md](TASKS_STRUCTURE.md) - Tasks-Struktur
- ✅ [docs/DATABASE_COMPLETE.md](DATABASE_COMPLETE.md) - Komplette Übersicht
- ✅ [docs/SCHEMA_UPDATE_SUMMARY.md](SCHEMA_UPDATE_SUMMARY.md) - Quick-Reference

---

## 🚀 Verwendung

### Setup durchführen
```bash
# 1. Testdaten laden (erstellt Semester automatisch)
php scripts/load_testdata.php

# Output:
# ✓ Admin: Sarah Schmidt [aktiv] [WiSe2526]
# ✓ User: Max Müller [aktiv] [WiSe2526]
# ✓ 5 Users mit Status
# ✓ 5 Sample Projects
```

### API testen
```bash
# Aktuelles Semester
curl http://localhost/pythonIDE/api/system/semester.php?action=current
# → {"ok":true,"semester":"WiSe2526"}

# Semester-Infos
curl http://localhost/pythonIDE/api/system/semester.php?action=info
# → Zeigt Regeln + Beispiele
```

### Datenbank-Queries
```sql
-- Alle aktiven Benutzer mit Semester
SELECT email, first_name, status, registration_date FROM users 
WHERE status = 'aktiv';

-- Tasks einer Assignment
SELECT * FROM tasks WHERE assignment_id = 1 ORDER BY position;

-- Schüler-Fortschritt
SELECT user_id, COUNT(*) total, 
       SUM(status='completed') completed FROM user_tasks 
GROUP BY user_id;
```

---

## 🧪 Tests bestanden

### Semester-Berechnung ✅
```
✓ 2026-02-08 → WiSe2526 (aktuell)
✓ 2026-03-01 → SoSe26
✓ 2026-10-01 → WiSe2627
✓ 2027-02-28 → WiSe2627
✓ 2027-03-01 → SoSe27
✓ 2027-10-01 → WiSe2728
```

### Login/Registration ✅
```
✓ Admin Login (admin@pythonide.local / admin123)
✓ User Login (max.mueller@example.com / test123)
✓ Registration (Email-basiert, kein Username)
✓ Sessions (email statt username)
```

### Datenbank ✅
```
✓ Users: 5 Benutzer mit Status & Semester
✓ Tasks: 2 Tabellen erstellt & funktional
✓ Projects: 5 Sample-Projekte
✓ Foreign Keys: Alle Beziehungen intakt
```

---

## 📊 Datenbestand

### Users (5 Benutzer)
| Email | Name | Status | Semester |
|-------|------|--------|----------|
| admin@pythonide.local | Sarah Schmidt | aktiv | WiSe2526 |
| max.mueller@example.com | Max Müller | aktiv | WiSe2526 |
| anna.schulz@example.com | Anna Schulz | aktiv | WiSe2526 |
| tom.weber@example.com | Tom Weber | aktiv | WiSe2526 |
| lisa.fischer@example.com | Lisa Fischer | aktiv | WiSe2526 |

### Projects (5 Projekte)
- Hallo Welt (Max Müller)
- Fibonacci Folge (Max Müller)
- Liste sortieren (Anna Schulz)
- Primzahlen (Anna Schulz)
- Temperatur Umrechner (Tom Weber)

---

## 📖 Dokumentation

- **Schnell-Referenz:** [SCHEMA_UPDATE_SUMMARY.md](SCHEMA_UPDATE_SUMMARY.md)
- **Details:** [DATABASE_COMPLETE.md](DATABASE_COMPLETE.md)
- **Tasks-System:** [TASKS_STRUCTURE.md](TASKS_STRUCTURE.md)
- **Schema-Details:** [DATABASE_SCHEMA_UPDATE.md](DATABASE_SCHEMA_UPDATE.md)

---

## ⏭️ Nächste Schritte (optional)

- [ ] API-Endpoints für Tasks (GET/POST/PUT/DELETE)
- [ ] Frontend für Task-Management
- [ ] Assignment-Workflow (Zuweisung an Schüler)
- [ ] Automatische Test-Execution
- [ ] Feedback-System
- [ ] Admin-Dashboard

---

**Version:** 2.0  
**Stand:** 08.02.2026  
**Status:** ✅ Production Ready

Alle Anforderungen erfüllt und getestet! 🎉
