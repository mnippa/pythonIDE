# MwSt-Rechner Aufgabe - Installation & Test

## 🚀 Schnellstart

### 1. Installation in Datenbank

**⭐ Option A: JSON-Import über Admin-UI (Empfohlen)**
1. Öffne http://localhost/pythonIDE/public/admin.php
2. Gehe zu "Assignment #21" (oder wähle ein anderes Assignment)
3. Klicke auf "Task importieren" Button
4. Wähle Datei: `tasks/mwst-rechner-task.json`
5. Klicke "Importieren"
6. ✅ Fertig! Task wurde automatisch hinzugefügt

**Option B: MySQL Command Line**
```bash
# Navigiere zum pythonIDE Verzeichnis
cd c:\xampp\htdocs\pythonIDE

# Führe SQL-Script aus
mysql -u root -p pythonide < sql/add_mwst_task.sql
```

**Option C: phpMyAdmin**
1. Öffne http://localhost/phpmyadmin
2. Wähle Datenbank **pythonide**
3. Klicke auf Tab **SQL**
4. Öffne Datei `sql/add_mwst_task.sql` in einem Editor
5. Kopiere den gesamten Inhalt
6. Füge ihn in das SQL-Feld ein
7. Klicke **Ausführen**

**Option D: HeidiSQL / MySQL Workbench**
```sql
-- Öffne sql/add_mwst_task.sql und führe es aus
```

### 2. Überprüfung

Nach der Installation solltest du folgende Ausgabe sehen:

```
Task ID: [neue ID]
Title: MwSt-Rechner (19%)
Position: [letzte Position + 1]
Status: Task successfully added to Assignment #21
```

Und eine Liste aller Tasks in Assignment #21.

---

## ✅ Test-Checkliste

### Vor der Installation:
- [ ] MySQL/MariaDB läuft (XAMPP gestartet)
- [ ] Datenbank `pythonide` existiert
- [ ] Assignment #21 existiert

### Nach der Installation:
- [ ] Task erscheint in Assignment #21
- [ ] Task-Typ ist `code`
- [ ] `input()` Support ist aktiviert (Phase 2.1)
- [ ] Test-Cases sind korrekt formatiert (JSON)

---

## 🧪 Testing

### Test 1: Musterlösung ausführen
```bash
cd c:\xampp\htdocs\pythonIDE
python test_mwst_solution.py
```

**Eingabe:** `100`  
**Erwartete Ausgabe:**
```
Nettopreis:     100.00 €
MwSt (19%):      19.00 €
Bruttopreis:    119.00 €
```

### Test 2: Verschiedene Szenarien
```bash
python test_mwst_scenarios.py
```

Zeigt:
- ✅ 4 gültige Lösungen
- ❌ 4 ungültige Lösungen mit Erklärung
- 🧪 Automatische Test-Case Validierung
- 📊 Beispiel-Berechnungen

### Test 3: Im Editor testen

1. Öffne http://localhost/pythonIDE/public/assignment_editor.php?assignment_id=21
2. Wähle Task "MwSt-Rechner (19%)"
3. Schreibe Code:
   ```python
   netto = float(input("Nettopreis: "))
   brutto = netto * 1.19
   print(f"Bruttopreis: {brutto:.2f} Euro")
   ```
4. Klicke **Run** → Eingabe z.B. `100`
5. Klicke **Check** → Sollte alle Tests bestehen

---

## 📋 Erwartete Test-Ergebnisse

### Code-Checks (alle müssen bestehen):

| # | Test | Status |
|---|------|--------|
| 1 | Verwendet `input()` | ✓ PASS |
| 2 | Verwendet `float()` oder `int()` | ✓ PASS |
| 3 | Multipliziert mit 1.19 | ✓ PASS |
| 4 | Verwendet `print()` | ✓ PASS |

### Beispiel-Berechnungen:

| Nettopreis | Bruttopreis | MwSt-Betrag |
|------------|-------------|-------------|
| 100.00 €   | 119.00 €    | 19.00 €     |
| 49.99 €    | 59.49 €     | 9.50 €      |
| 250.00 €   | 297.50 €    | 47.50 €     |

---

## 🔧 Troubleshooting

### Problem: "Table 'tasks' doesn't exist"
**Lösung:** Führe zuerst `sql/schema.sql` aus

### Problem: "Unknown column 'test_cases' in 'field list'"
**Lösung:** Führe Migration aus:
```bash
mysql -u root -p pythonide < sql/migration_add_test_cases.sql
```

### Problem: "Assignment #21 existiert nicht"
**Lösung:** Erstelle Assignment #21 zuerst über Admin-Panel oder via SQL

### Problem: `input()` funktioniert nicht
**Lösung:** 
- Stelle sicher dass Phase 2.1 implementiert ist
- Prüfe ob `editor-setup.js` die Input-Funktion enthält
- Cache leeren (Ctrl+F5)

### Problem: Test-Cases schlagen fehl
**Lösung:** 
- JSON-Format in Datenbank prüfen
- Regex-Patterns müssen doppelt escaped sein (`\\s` statt `\s`)
- Browser-Konsole auf Fehler prüfen

---

## 📚 Dokumentation

- **Aufgaben-Details**: [docs/task-mwst-rechner.md](../docs/task-mwst-rechner.md)
- **Input-Testing Guide**: [docs/input-testing-guide.md](../docs/input-testing-guide.md)
- **ROADMAP Phase 2**: [ROADMAP.md](../ROADMAP.md)

---

## 🎯 Nächste Schritte

Nach erfolgreicher Installation dieser Aufgabe:

1. **Weitere input()-Aufgaben erstellen**:
   - Temperatur-Umrechner (Celsius → Fahrenheit)
   - Altersberechnung aus Geburtsjahr
   - BMI-Rechner
   - Einfacher Taschenrechner

2. **Testen mit echten Studierenden**

3. **Feedback sammeln**:
   - Sind Beschreibungen klar?
   - Sind Hints hilfreich?
   - Sind Test-Cases fair?

4. **Phase 2.3 starten**: UI-Elemente mit IFRAME

---

## ✅ Success Criteria

Die Installation ist erfolgreich wenn:

- [x] Task ist in Assignment #21 sichtbar
- [x] Musterlösung besteht alle Tests
- [x] `input()` funktioniert im Browser
- [x] Code-Checks validieren korrekt
- [x] Keine SQL/JavaScript Errors

---

**Erstellt:** März 2026  
**Phase:** 2.1 & 2.2  
**Status:** Production Ready ✅  
**Maintainer:** pythonIDE Team
