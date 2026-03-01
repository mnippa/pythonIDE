# Task Import/Export - JSON Files

Dieser Ordner enthält fertige Task-Definitionen im JSON-Format für den Import über die Admin-UI.

---

## 📦 Verfügbare Tasks

### 1. MwSt-Rechner (19%)
- **Datei:** `mwst-rechner-task.json`
- **Typ:** Code mit `input()`
- **Schwierigkeit:** Leicht
- **Features:**
  - 4 automatische Code-Checks
  - Vollständige Musterlösung
  - 3 Hints
  - Formatierte Task-Beschreibung
- **Für:** Assignment #21 (oder beliebiges anderes)

---

## 🚀 Import-Anleitung

### Option 1: Über Admin-UI (Empfohlen)

1. **Login als Admin**
   ```
   http://localhost/pythonIDE/public/admin.php
   ```

2. **Gehe zu Assignment Management**
   - Klicke auf "Assignments verwalten"
   - Wähle das Ziel-Assignment (z.B. #21)

3. **Task importieren**
   - Klicke auf "Task importieren" oder "Import" Button
   - Wähle die JSON-Datei: `tasks/mwst-rechner-task.json`
   - Klicke "Hochladen" oder "Importieren"

4. **Überprüfung**
   - Task sollte in der Task-Liste erscheinen
   - Position wird automatisch vergeben (letzte + 1)

### Option 2: Über SQL-Script (Alternative)

Falls die UI-Import-Funktion nicht verfügbar ist:

```bash
mysql -u root -p pythonide < sql/add_mwst_task.sql
```

---

## 📋 JSON-Format

Alle Task-Dateien folgen dem Standard-Format aus [docs/taskexport.md](../docs/taskexport.md):

```json
{
  "version": "1.0",
  "title": "Task-Titel",
  "problem_type": "code_completion",
  "task_type": "code",
  "description": "HTML-Beschreibung",
  "task_text": "Markdown-Aufgabenstellung",
  "code_template": "# Template-Code",
  "hint1": "Erster Tipp",
  "hint2": "Zweiter Tipp",
  "hint3": "Dritter Tipp",
  "solution_code": "# Musterlösung",
  "test_cases": [...]
}
```

---

## ✅ Vorteile JSON-Import

- ✅ Keine SQL-Kenntnisse erforderlich
- ✅ Über Browser-UI bedienbar
- ✅ Automatische Validierung
- ✅ Position wird automatisch vergeben
- ✅ Einfaches Teilen von Tasks
- ✅ Versionskontrolle möglich (Git)

---

## 🔧 Eigene Tasks erstellen

### 1. Exportiere einen bestehenden Task
```
Admin-Panel → Task auswählen → "Export" Button → JSON-Datei speichern
```

### 2. Bearbeite die JSON-Datei
- Ändere `title`, `description`, `code_template`, etc.
- Passe `test_cases` an
- Aktualisiere `solution_code`

### 3. Importiere die modifizierte Task
- Folge der Import-Anleitung oben
- Task wird als neue Task angelegt (nicht überschrieben)

---

## 📖 Weitere Ressourcen

- **Task-Export-Format:** [docs/taskexport.md](../docs/taskexport.md)
- **Assignment Export:** [docs/assignment-export-import.md](../docs/assignment-export-import.md)
- **Input-Testing Guide:** [docs/input-testing-guide.md](../docs/input-testing-guide.md)
- **MwSt-Task Doku:** [docs/task-mwst-rechner.md](../docs/task-mwst-rechner.md)

---

## 🎯 Task-Templates

Diese fertigen Tasks können als **Templates** für eigene Aufgaben dienen:

| Template | Use Case | Features |
|----------|----------|----------|
| `mwst-rechner-task.json` | `input()` + Berechnung | Code-Checks, Formatierung |
| (weitere folgen) | ... | ... |

---

## 💡 Tipps

### Regex-Patterns in test_cases
- Pattern muss im JSON **single-escaped** sein: `input\\s*\\(`
- In der Datenbank wird es **double-escaped**: `input\\\\s*\\\\(`
- System konvertiert automatisch beim Import

### Sonderzeichen
- Nutze Unicode-Escapes wenn nötig: `\u00e4` für ä
- Oder direkt UTF-8 (empfohlen)

### Große Code-Templates
- Nutze `\n` für Zeilenumbrüche
- Oder mehrzeilige Strings im JSON-Editor

---

**Hinweis:** Der tasks-Ordner ist für production-ready Tasks gedacht.  
Experimentelle oder Test-Tasks bitte in separatem Ordner ablegen.

---

**Version:** 1.0  
**Letzte Aktualisierung:** März 2026  
**Maintainer:** pythonIDE Team
