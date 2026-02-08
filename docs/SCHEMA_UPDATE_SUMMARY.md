# Datenbankschema Update - Zusammenfassung

## 🎯 Durchgeführte Änderungen (08.02.2026)

### 1. Users-Tabelle erweitert

**Neue Spalten:**
- `registration_date` (TIMESTAMP)
  - Automatisch gesetzt auf Registrierungszeitpunkt
  - Verwendet zur Semester-Berechnung
  
- `status` (ENUM: 'aktiv', 'archiviert')
  - Default: 'aktiv'
  - Ermöglicht Archivierung inaktiver Benutzer

### 2. Semester-Automatik implementiert

**Logik:**
```
01.03 - 30.09 → SoSe (z.B. SoSe26)
01.10 - 28/29.02 → WiSe (z.B. WiSe2627)
```

**Helper-Funktionen** ([config/semester.php](../config/semester.php)):
```php
calculateSemester('2026-03-15')    // → SoSe26
calculateSemester('2026-10-01')    // → WiSe2627
getCurrentSemester()                // → WiSe2627
isValidSemester('SoSe26')          // → true
```

### 3. Assignments-Struktur optimiert

**Neue Felder:**
- `order_num` (INT) - Basisreihenfolge
- `position` (INT) - Exakte Sortierposition

Assignments sind nun Container für mehrere Tasks/Problems mit definierter Reihenfolge.

### 4. User-Status Management

**Status-Optionen:**
- `aktiv` - Normaler Benutzer
- `archiviert` - Inaktiv/archiviert

**Abfrage aktiver Benutzer:**
```sql
SELECT * FROM users WHERE status = 'aktiv'
```

---

## 🔧 API-Endpoints

### Semester-Information
**Basis-URL:** `api/system/semester.php`

#### 1. Aktuelles Semester
```
GET /api/system/semester.php?action=current
```

**Response:**
```json
{
  "ok": true,
  "semester": "WiSe2627",
  "timestamp": "2026-02-08 01:56:39"
}
```

#### 2. Semester berechnen
```
GET /api/system/semester.php?action=calculate&date=2026-03-15
```

**Response:**
```json
{
  "ok": true,
  "date": "2026-03-15",
  "semester": "SoSe26"
}
```

#### 3. Alle Semester mit Benutzerzahlen
```
GET /api/system/semester.php?action=list
```

**Response:**
```json
{
  "ok": true,
  "semesters": [
    {"semester": "SoSe26", "count": 3},
    {"semester": "WiSe2627", "count": 2}
  ],
  "current": "WiSe2627"
}
```

#### 4. Semester-Regeln Info
```
GET /api/system/semester.php?action=info
```

---

## 📊 Testdaten aktualisiert

**5 Testbenutzer - Alle Status: `aktiv`**

| Benutzer | Email | Registrierungsdatum | Semester |
|----------|-------|-------------------|----------|
| Sarah Schmidt (Admin) | admin@pythonide.local | 2026-02-08 | WiSe2627 |
| Max Müller | max.mueller@example.com | 2026-02-08 | WiSe2627 |
| Anna Schulz | anna.schulz@example.com | 2026-02-08 | WiSe2627 |
| Tom Weber | tom.weber@example.com | 2026-02-08 | WiSe2627 |
| Lisa Fischer | lisa.fischer@example.com | 2026-02-08 | WiSe2627 |

---

## 🚀 Verwendung

### Testdaten neu laden
```bash
php scripts/load_testdata.php
```

### Schema aktualisieren (für bestehende DB)
```bash
php scripts/update_schema.php
```

---

## 🗓️ Semester-Kalender 2026-2028

| Semester | Gültig von | Gültig bis |
|----------|-----------|-----------|
| **SoSe26** | 01.03.2026 | 30.09.2026 |
| **WiSe2627** | 01.10.2026 | 29.02.2027 |
| **SoSe27** | 01.03.2027 | 30.09.2027 |
| **WiSe2728** | 01.10.2027 | 28.02.2028 |

---

## 📝 Nächste Schritte (optional)

Der Benutzer hat erwähnt:
> "Jedes Assignment ist eine Sammlung unterschiedlicher Aufgaben mit einer Reihenfolge"

**Mögliche zukünftige Erweiterungen:**
1. Neue `tasks` oder `problems` Tabelle
2. Task-Unterteilung innerhalb Assignments
3. Abhängigkeiten zwischen Tasks
4. Task-Status-Tracking je Benutzer

Falls gewünscht, kann dies in einem separaten Update implementiert werden.

---

## 🔗 Dokumentation

- [DATABASE_SCHEMA_UPDATE.md](DATABASE_SCHEMA_UPDATE.md) - Detailliertes Schema-Dokumentation
- [config/semester.php](../config/semester.php) - Semester-Hilfsfunktionen
- [api/system/semester.php](../api/system/semester.php) - API-Endpoint
