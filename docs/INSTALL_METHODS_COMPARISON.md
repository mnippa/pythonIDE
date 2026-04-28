# MwSt-Rechner Installation - Vergleich der Methoden

## Legacy/Current-state Banner

Diese Datei ist eine auf ein konkretes Task-Beispiel bezogene Vergleichsnotiz.
Sie ist nicht die allgemeine Referenz fuer heutiges Task-Authoring oder Deploy-Flows.

Fuer den aktuellen Produktstand zuerst lesen:
- [TASK_AUTHORING_GUIDE.md](TASK_AUTHORING_GUIDE.md)
- [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)

## 📊 Installations-Methoden im Vergleich

| Kriterium | 🌟 JSON-Import (UI) | 🔧 SQL-Script |
|-----------|---------------------|---------------|
| **Schwierigkeit** | ⭐ Sehr einfach | ⭐⭐ Mittel |
| **Kenntnisse** | Keine | SQL-Grundkenntnisse |
| **Werkzeug** | Browser | MySQL CLI / phpMyAdmin |
| **Zeit** | 30 Sekunden | 1-2 Minuten |
| **Fehleranfällig** | Nein (validiert) | Ja (Syntax-Fehler) |
| **Assignment-Auswahl** | In UI auswählbar | Fest auf #21 |
| **Position** | Auto (letzte + 1) | Auto (letzte + 1) |
| **Empfohlen für** | Alle Nutzer ✅ | Entwickler / Batch-Import |

---

## 🌟 Methode 1: JSON-Import über UI (Empfohlen)

### Vorteile:
- ✅ Keine technischen Kenntnisse nötig
- ✅ Automatische Validierung
- ✅ Assignment flexibel wählbar
- ✅ Fehlerbehandlung in UI
- ✅ Keine Konsole/Terminal nötig
- ✅ Sofortige Vorschau

### Anleitung:
```
1. Öffne: http://localhost/pythonIDE/public/admin.php
2. Wähle: Assignment #21 (oder ein anderes)
3. Klicke: "Task importieren" Button
4. Datei: tasks/mwst-rechner-task.json auswählen
5. Klicke: "Importieren"
6. ✅ Fertig!
```

### Datei:
```
tasks/mwst-rechner-task.json
```

---

## 🔧 Methode 2: SQL-Script (Für Entwickler)

### Vorteile:
- ✅ Schnell für Batch-Import vieler Tasks
- ✅ Automatisierbar (Scripts)
- ✅ Keine Browser-UI nötig
- ✅ Gut für Migrations-Workflows
- ✅ Version-Control-freundlich

### Nachteile:
- ❌ Erfordert MySQL-Zugang
- ❌ Assignment fest codiert (nur #21)
- ❌ Fehlersuche komplexer
- ❌ Keine Validierung vor Insert

### Anleitung:

**Option A: MySQL CLI**
```bash
cd c:\xampp\htdocs\pythonIDE
mysql -u root -p pythonide < sql/add_mwst_task.sql
```

**Option B: phpMyAdmin**
```
1. http://localhost/phpmyadmin
2. Datenbank "pythonide"
3. Tab "SQL"
4. sql/add_mwst_task.sql öffnen & kopieren
5. Einfügen & "Ausführen"
```

### Datei:
```
sql/add_mwst_task.sql
```

---

## 🎯 Welche Methode wählen?

### Nutze **JSON-Import** wenn:
- ✅ Du kein SQL kannst
- ✅ Du über Browser arbeitest
- ✅ Du das Assignment später wählen willst
- ✅ Du Fehler vermeiden willst
- ✅ **→ Empfohlen für 95% der Nutzer**

### Nutze **SQL-Script** wenn:
- ✅ Du viele Tasks auf einmal importierst
- ✅ Du Migration-Scripts schreibst
- ✅ Du in CI/CD-Pipelines arbeitest
- ✅ Du Kommandozeile bevorzugst
- ✅ **→ Empfohlen für Entwickler & Automation**

---

## 🔄 Konvertierung

### JSON → SQL (Automatisch)
Das System konvertiert JSON automatisch beim Import:
```
JSON-Datei → UI-Import → Validierung → SQL INSERT → Datenbank
```

### SQL → JSON (Manuell / Export)
Über Admin-Panel:
```
Task auswählen → "Export" Button → JSON-Download
```

---

## 📋 Datei-Inhalte

### JSON-Datei (`tasks/mwst-rechner-task.json`)
```json
{
  "version": "1.0",
  "title": "MwSt-Rechner (19%)",
  "problem_type": "code_completion",
  "code_template": "# Code hier...",
  "test_cases": [...],
  "solution_code": "..."
}
```

**Felder:** 19  
**Größe:** ~3 KB  
**Format:** Standard Task-Export (siehe docs/taskexport.md)

### SQL-Datei (`sql/add_mwst_task.sql`)
```sql
USE pythonide;
SET @next_position = ...;
INSERT INTO tasks (...) VALUES (...);
SELECT ... as 'Status';
```

**Zeilen:** ~140  
**Größe:** ~6 KB  
**Funktionen:** Position-Berechnung, Status-Ausgabe, Verification

---

## ⚠️ Wichtige Hinweise

### Beide Methoden:
- 🔄 Erstellen **neue** Task (kein Update)
- 📍 Position automatisch (letzte + 1)
- ✅ Alle Felder werden gesetzt
- 🧪 Test-Cases als JSON gespeichert

### Unterschiede:
| Feld | JSON | SQL |
|------|------|-----|
| Assignment | Wählbar | Fest (21) |
| Validierung | Vor Import | Nach Import |
| Fehler | In UI | In Konsole |
| Batch-Import | Einzeln | Möglich |

---

## 🚀 Schnell-Referenz

### JSON-Import (1 Minute)
```bash
1. Admin-Panel → Assignment wählen
2. "Import" → JSON-Datei wählen
3. "Importieren" → Fertig ✅
```

### SQL-Import (2 Minuten)
```bash
mysql -u root -p pythonide < sql/add_mwst_task.sql
```

---

## 📖 Weitere Ressourcen

- **JSON-Format Doku:** [docs/taskexport.md](../docs/taskexport.md)
- **Task-Import README:** [tasks/README.md](../tasks/README.md)
- **Installation Guide:** [docs/INSTALL_MWST_TASK.md](../docs/INSTALL_MWST_TASK.md)
- **Aufgaben-Details:** [docs/task-mwst-rechner.md](../docs/task-mwst-rechner.md)

---

**Empfehlung:** 🌟 Nutze JSON-Import für einfache, fehlerfreie Installation!

**Version:** 1.0  
**Stand:** März 2026  
**Status:** Production Ready ✅
