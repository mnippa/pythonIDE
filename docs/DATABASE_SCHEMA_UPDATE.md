## Datenbankschema-Aktualisierung (2026-02-08)

### Neue Felder

#### Users-Tabelle
- **registration_date** (TIMESTAMP): Automatisch auf aktuelles Datum gesetzt bei Registrierung
  - Format: YYYY-MM-DD HH:MM:SS
  - Default: CURRENT_TIMESTAMP

- **status** (ENUM): Status des Benutzers
  - `aktiv`: Normaler aktiver Benutzer
  - `archiviert`: Archivierter / inaktiver Benutzer
  - Default: `aktiv`

#### Semester-Berechnung
Die Semester werden automatisch aus dem Registrierungsdatum berechnet:

**Sommersemester (SoSe)**: 01.03 - 30.09
- Format: `SoSe{YY}` (z.B. SoSe26 für März-September 2026)

**Wintersemester (WiSe)**: 01.10 - 28./29.02 (nächstes Jahr)
- Format: `WiSe{YY}{YY+1}` (z.B. WiSe2627 für Oktober 2026 - Februar 2027)
- Format (Januar/Februar): `WiSe{YY-1}{YY}` (z.B. WiSe2526 für Oktober 2025 - Februar 2026)

#### Assignments-Tabelle
- **order_num** (INT): Basisnummer für Reihenfolge
- **position** (INT): Exakte Position in der Sortierung

#### user_assignments-Tabelle
Verwaltet die Zuweisung von Assignments zu Benutzern:
- Status-Tracking: `assigned`, `in_progress`, `submitted`, `passed`, `failed`
- Versuch-Zähler (attempts)
- Test-Ergebnisse (test_results als JSON)

### Struktur: Assignments und Tasks

```
Assignment (Sammlung von Aufgaben)
├─ task 1 (order_num = 1)
│  ├─ Beschreibung
│  ├─ Code-Template
│  └─ Test Cases
├─ task 2 (order_num = 2)
│  └─ ...
└─ task N (order_num = N)
```

Jede Assignment ist eine Sammlung von Task/Problems mit definierten Reihenfolgen.

### Helper-Funktionen

Location: [config/semester.php](../config/semester.php)

```php
// Semester vom Datum berechnen
calculateSemester('2026-03-15'); // Returns: SoSe26
calculateSemester('2026-10-01'); // Returns: WiSe2627

// Aktuelles Semester
getCurrentSemester(); // Rückgabe: SoSe26 oder WiSe2627

// Semester validieren
isValidSemester('SoSe26'); // Returns: true
```

### Datenbankänderungen durchführen

```php
// Automatisch beim Laden:
php scripts/load_testdata.php

// Manuell für bestehende Datenbank:
php scripts/update_schema.php
```

### Abfrage-Beispiele

**Alle aktiven Benutzer mit ihrem Semester:**
```sql
SELECT 
    id,
    email,
    first_name,
    last_name,
    CASE 
        WHEN MONTH(registration_date) >= 3 AND MONTH(registration_date) <= 9
        THEN CONCAT('SoSe', YEAR(registration_date) % 100)
        ELSE CONCAT('WiSe', YEAR(registration_date) % 100, (YEAR(registration_date) + 1) % 100)
    END as semester,
    registration_date,
    status
FROM users
WHERE status = 'aktiv'
ORDER BY registration_date DESC;
```

**Benutzer nach Semester:**
```sql
SELECT 
    CASE 
        WHEN MONTH(registration_date) >= 3 AND MONTH(registration_date) <= 9
        THEN CONCAT('SoSe', YEAR(registration_date) % 100)
        ELSE CONCAT('WiSe', YEAR(registration_date) % 100, (YEAR(registration_date) + 1) % 100)
    END as semester,
    COUNT(*) as count
FROM users
WHERE status = 'aktiv'
GROUP BY semester;
```

### Zeitplan: Semester

| Semester | Startdatum | Enddatum |
|----------|-----------|----------|
| SoSe26 | 01.03.2026 | 30.09.2026 |
| WiSe2627 | 01.10.2026 | 29.02.2027 |
| SoSe27 | 01.03.2027 | 30.09.2027 |
| WiSe2728 | 01.10.2027 | 28.02.2028 |
