# Datenbankstruktur - Komplette Übersicht (08.02.2026)

## Hinweis zum aktuellen Stand

Diese Datei ist als Strukturueberblick weiterhin nuetzlich, beschreibt aber nicht in allen Punkten den heutigen Produktstand vollstaendig.

Vor allem beachten:
- aktuelle Task-Progress-Werte im Frontend weichen teils von aelteren Tabellenbeispielen ab
- folder-basierte Tasks nutzen neben DB-Tabellen auch Dateien im Filesystem und User-Overrides
- fuer den aktuellen Gesamtzusammenhang zuerst [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md) lesen

## 🎯 Erweiterungen in dieser Version

### 1. Semester-System ✅
- **Automatische Berechnung** aus Registrierungsdatum
- **Logik korrekt:**
  - `SoSe{YY}`: 01.03 - 30.09 (z.B. SoSe26)
  - `WiSe{YY}{YY+1}`: 01.10 - Ende Feb nächstes Jahr (z.B. WiSe2627)
  - October onwards = WiSe2627, Jan/Feb = WiSe2526
- **Aktuelles System:** WiSe2526 (08.02.2026 ist noch im WiSe 2025/2026)

### 2. User-Status ✅
- **Feld:** `status` (ENUM: 'aktiv', 'archiviert')
- Ermöglicht Verwaltung inaktiver Benutzer ohne zu löschen

### 3. Tasks/Problems Struktur ✅
- **Neue Tabelle:** `tasks`
- **Neue Tabelle:** `user_tasks`
- Ermöglicht granulare Aufgabenverwaltung innerhalb Assignments
- Problem-Typen: code_completion, code_fix, multiple_choice, essay

---

## 📋 Tabellen-Übersicht

### users
```
id (PK)
email (UNIQUE)
first_name
last_name
password_hash
role (admin, user)
status (aktiv, archiviert)
registration_date (→ Semester)
created_at
last_login
```

### assignments
```
id (PK)
title
description
code_template
created_by (FK → users)
is_active
difficulty (beginner, intermediate, advanced)
time_limit_minutes
order_num
position
created_at
updated_at
```

### tasks ⭐ NEW
```
id (PK)
assignment_id (FK)
title
description
position (Reihenfolge)
problem_type (code_completion, code_fix, multiple_choice, essay)
code_template
hint
expected_output
created_at
updated_at
```

### user_tasks ⭐ NEW
```
id (PK)
user_id (FK)
task_id (FK)
status (not_started, in_progress, completed, failed)
user_code
attempts
submitted_at
completed_at
test_results (JSON)
feedback
created_at
updated_at
```

Hinweis: Diese Statusauflistung ist historisch. Im aktuellen Frontend treten unter anderem `unbearbeitet`, `in-progress`, `passed`, `failed` als Task-Progress-Werte auf.

### test_cases (Existing)
```
id (PK)
assignment_id (FK)
description
test_input (JSON)
expected_output
is_hidden
order_num
created_at
```

### user_assignments (Existing)
```
id (PK)
user_id (FK)
assignment_id (FK)
status (assigned, in_progress, submitted, passed, failed)
current_code
submitted_at
test_results (JSON)
attempts
assigned_at
```

### projects (Existing)
```
id (PK)
user_id (FK)
name
description
code
visibility (private, public)
share_token
created_at
updated_at
```

---

## 🔗 Beziehungen

```
users (1) ──→ (N) assignments (as creator)
        │
        └───→ (N) projects
        └───→ (N) user_assignments
        └───→ (N) user_tasks

assignments (1) ──→ (N) tasks (⭐)
             │
             └──→ (N) test_cases
             └──→ (N) user_assignments

tasks (1) ──────→ (N) user_tasks (⭐)

user_assignments (N) ──→ (1) assignments
                    └──→ (1) users

user_tasks (N) ──→ (1) users (⭐)
           └──→ (1) tasks (⭐)
```

---

## 📊 Semester-Berechnung

### SQL-Funktion (für zukünftige Nutzung)
```sql
SELECT 
    id,
    email,
    CASE 
        WHEN MONTH(registration_date) >= 3 AND MONTH(registration_date) <= 9
        THEN CONCAT('SoSe', YEAR(registration_date) % 100)
        WHEN MONTH(registration_date) >= 10
        THEN CONCAT('WiSe', YEAR(registration_date) % 100, (YEAR(registration_date) + 1) % 100)
        ELSE CONCAT('WiSe', (YEAR(registration_date) - 1) % 100, YEAR(registration_date) % 100)
    END as semester
FROM users;
```

### PHP-Helper ([config/semester.php](../config/semester.php))
```php
calculateSemester('2026-02-08');  // → WiSe2526 ✓
calculateSemester('2026-03-01');  // → SoSe26 ✓
calculateSemester('2026-10-01');  // → WiSe2627 ✓
getCurrentSemester();              // → WiSe2526 (aktuell)
```

---

## 🚀 Initiales Setup

### Testdaten laden
```bash
php scripts/load_testdata.php
```

### Schema aktualisieren (bestehende DB)
```bash
php scripts/update_schema.php
php scripts/create_tasks_table.php
```

### Tasks-Tabelle erstellen
```bash
php scripts/create_tasks_table.php
```

---

## 📈 Beispiel: Assignment mit Tasks

### Assignment
```sql
INSERT INTO assignments (title, description, created_by, is_active, difficulty)
VALUES ('Python Basics', 'Grundlagen der Programmierung', 1, TRUE, 'beginner');
-- → assignment_id = 1
```

### Tasks (Aufgaben)
```sql
INSERT INTO tasks (assignment_id, title, position, problem_type, code_template, expected_output)
VALUES 
(1, 'Hello World', 1, 'code_completion', 'print("___")', 'Hello World'),
(1, 'Variablen', 2, 'code_completion', 'name = "___"\nprint(name)', 'Max'),
(1, 'Schleifen', 3, 'code_completion', 'for i in range(___):\n    print(i)', '0-9');
```

### Schüler-Fortschritt
```sql
-- Max Müller (user_id=2) arbeitet an Task 1
INSERT INTO user_tasks (user_id, task_id, status, user_code)
VALUES (2, 1, 'in_progress', 'print("Hello World")');

-- Task einreichen
UPDATE user_tasks
SET status = 'completed', submitted_at = NOW(), attempts = 1
WHERE user_id = 2 AND task_id = 1;
```

---

## 🔍 Nützliche Queries

### Benutzer mit Semester
```sql
SELECT 
    email,
    first_name,
    last_name,
    status,
    CASE 
        WHEN MONTH(registration_date) >= 3 AND MONTH(registration_date) <= 9
        THEN CONCAT('SoSe', YEAR(registration_date) % 100)
        WHEN MONTH(registration_date) >= 10
        THEN CONCAT('WiSe', YEAR(registration_date) % 100, (YEAR(registration_date) + 1) % 100)
        ELSE CONCAT('WiSe', (YEAR(registration_date) - 1) % 100, YEAR(registration_date) % 100)
    END as semester
FROM users
WHERE status = 'aktiv'
ORDER BY registration_date DESC;
```

### Tasks einer Assignment
```sql
SELECT 
    t.id,
    t.title,
    t.position,
    t.problem_type,
    COUNT(ut.user_id) as student_count,
    SUM(CASE WHEN ut.status = 'completed' THEN 1 ELSE 0 END) as completed_count
FROM tasks t
LEFT JOIN user_tasks ut ON t.id = ut.task_id
WHERE t.assignment_id = 1
GROUP BY t.id
ORDER BY t.position;
```

### Schüler-Fortschritt
```sql
SELECT 
    u.email,
    u.first_name,
    COUNT(DISTINCT ut.task_id) as total_tasks,
    SUM(CASE WHEN ut.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    ROUND(100 * SUM(CASE WHEN ut.status = 'completed' THEN 1 ELSE 0 END) / COUNT(DISTINCT ut.task_id), 0) as completion_percent
FROM users u
LEFT JOIN user_tasks ut ON u.id = ut.user_id
WHERE u.status = 'aktiv'
GROUP BY u.id
ORDER BY completion_percent DESC;
```

---

## 📝 Migrate bestehende Datenbank

### Schritt 1: Semester-Spalten (falls noch nicht vorhanden)
```bash
php scripts/update_schema.php
```

### Schritt 2: Tasks-Struktur
```bash
php scripts/create_tasks_table.php
```

### Schritt 3: Testdaten laden (optional, löscht bestehende Daten!)
```bash
php scripts/load_testdata.php
```

---

## ✅ Status

- ✅ Users mit Semester-System
- ✅ Semester-Automatik (SoSe/WiSe)
- ✅ Status-Feld für aktiv/archiviert
- ✅ Tasks/Problems-Tabelle
- ✅ User-Tasks Progress-Tracking
- ✅ Helper-Funktionen
- ✅ API-Endpoints (partial)
- ✅ Testdaten
- ⏳ API-Endpoints (vollständig)
- ⏳ Frontend-Integration

---

**Kontakt:** For questions or issues, check [docs/](.) folder.
