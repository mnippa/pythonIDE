# 🧮 MwSt-Rechner - Produktions-fertige Beispielaufgabe

> **Assignment #21** | **Task Type:** Code mit `input()` | **Phase 2.1/2.2** | **Status:** ✅ Ready

---

## 📦 Was ist enthalten?

Diese vollständige Beispielaufgabe demonstriert Best Practices für `input()`-basierte Aufgaben:

### Dateien:

| Datei | Beschreibung |
|-------|--------------|
| **tasks/mwst-rechner-task.json** | 🌟 JSON-Import (UI) - **Empfohlen!** |
| **sql/add_mwst_task.sql** | SQL-Script (Datenbank direkt) |
| **docs/task-mwst-rechner.md** | Vollständige Aufgaben-Dokumentation |
| **docs/INSTALL_MWST_TASK.md** | Installation & Test-Anleitung |
| **test_mwst_solution.py** | Ausführbare Musterlösung |
| **test_mwst_scenarios.py** | Test-Szenarien (✅ gültig / ❌ ungültig) |

---

## ⚡ Schnellstart (2 Minuten)

### 1. Installation

**⭐ JSON-Import (Empfohlen - Keine SQL-Kenntnisse nötig)**
```
1. Admin-Panel öffnen: http://localhost/pythonIDE/public/admin.php
2. Assignment #21 auswählen
3. "Task importieren" klicken
4. Datei wählen: tasks/mwst-rechner-task.json
5. "Importieren" → Fertig! ✅
```

**Alternative: SQL-Script**
```bash
# In MySQL/MariaDB ausführen
mysql -u root -p pythonide < sql/add_mwst_task.sql
```

### 2. Test der Musterlösung
```bash
python test_mwst_solution.py
```

### 3. Im Editor öffnen
```
http://localhost/pythonIDE/public/assignment_editor.php?assignment_id=21
```

---

## 🎯 Aufgabenstellung

**Schreibe ein Programm, das:**
1. Nach dem **Nettopreis** fragt (mit `input()`)
2. Den **Bruttopreis** mit 19% MwSt berechnet
3. Das Ergebnis mit `print()` ausgibt

**Beispiel:**
```
Nettopreis in Euro: 100
Bruttopreis: 119.00 Euro
```

---

## ✅ Automatische Test-Cases

Das System prüft:

| # | Test | Pattern |
|---|------|---------|
| 1 | Verwendet `input()` | `input\s*\(` |
| 2 | Konvertiert zu `float()` oder `int()` | `(float\|int)\s*\(` |
| 3 | Berechnet mit 1.19 oder +0.19 | `*\s*1\.19\|+.*0\.19` |
| 4 | Verwendet `print()` | `print\s*\(` |

---

## 💡 Musterlösung

```python
# Nettopreis vom Benutzer einlesen
netto = input("Nettopreis in Euro: ")

# In Zahl konvertieren
netto = float(netto)

# Bruttopreis berechnen (19% MwSt)
brutto = netto * 1.19

# Ergebnis ausgeben
print(f"Bruttopreis: {brutto:.2f} Euro")
print(f"(enthält {netto * 0.19:.2f} Euro MwSt)")
```

**Kompakte Version:**
```python
netto = float(input("Nettopreis: "))
print(f"Bruttopreis: {netto * 1.19:.2f} Euro")
```

---

## 📊 Test-Ergebnisse

**Beispiel-Berechnungen:**

| Nettopreis | Bruttopreis (19%) | MwSt-Betrag |
|------------|-------------------|-------------|
| 100.00 €   | 119.00 €         | 19.00 €     |
| 49.99 €    | 59.49 €          | 9.50 €      |
| 250.00 €   | 297.50 €         | 47.50 €     |

---

## 🎓 Lernziele

Nach dieser Aufgabe können Studierende:

- ✅ `input()` für Benutzereingaben verwenden
- ✅ String zu Float konvertieren (`float()`)
- ✅ Prozentrechnung durchführen (MwSt)
- ✅ Formatierte Ausgabe mit f-Strings (`:.2f`)
- ✅ Mathematische Berechnungen in Python

---

## 🔗 Weitere Infos

- **Ausführliche Doku:** [docs/task-mwst-rechner.md](docs/task-mwst-rechner.md)
- **Installation:** [docs/INSTALL_MWST_TASK.md](docs/INSTALL_MWST_TASK.md)
- **Input-Testing Guide:** [docs/input-testing-guide.md](docs/input-testing-guide.md)
- **ROADMAP Phase 2:** [ROADMAP.md](ROADMAP.md)

---

## 🚀 Nächste Schritte

Diese Aufgabe dient als **Template** für weitere `input()`-Aufgaben:

### Empfohlene weitere Aufgaben:
1. **Temperatur-Umrechner** (Celsius → Fahrenheit)
2. **Altersberechnung** aus Geburtsjahr
3. **BMI-Rechner** (Gewicht + Größe → BMI)
4. **Prozentrechner** (3 von 4 Werten berechnen)
5. **Taschenrechner** (2 Zahlen + Operator)

---

## ✅ Quality Checklist

- [x] SQL-Script getestet in MariaDB 10.x
- [x] Musterlösung besteht alle Test-Cases
- [x] `input()` funktioniert in Browser (Phase 2.1)
- [x] Code-Checks validieren korrekt (Regex)
- [x] Dokumentation vollständig
- [x] Test-Szenarien abgedeckt (gültig + ungültig)
- [x] Troubleshooting-Guide vorhanden
- [x] Production-ready ✅

---

**Version:** 1.0  
**Erstellt:** März 2026  
**Phase:** 2.1 & 2.2 Complete  
**Maintainer:** pythonIDE Team  
**Status:** ✅ Production Ready
