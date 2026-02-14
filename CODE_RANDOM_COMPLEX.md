# Code Random Complex Task Type - Implementation Complete

## Migration erfolgreich durchgeführt ✅

**Migration 011 ausgeführt:**
```
✓ Added code_random_complex to task_type enum
```

## 2 Beispiel-Aufgaben erstellt ✅

### Aufgabe 1: Binärzahl in Dezimal (Task ID: 69)
- **Titel:** Binärzahl in Dezimal umwandeln
- **Aufgabe:** Wandle die gegebene 8-stellige Binärzahl in eine Dezimalzahl um
- **Generator-Code:**
  ```python
  import random
  binary = format(random.randint(0, 255), '08b')
  values = {"binary": binary}
  ```
- **Solution-Code:**
  ```python
  result = int(values["binary"], 2)
  ```
- **Expected Answer Variable:** result
- **Max Attempts:** 3
- **Show Solution:** Ja

### Aufgabe 2: Dezimalzahl in Binär (Task ID: 70)
- **Titel:** Dezimalzahl in Binär umwandeln
- **Aufgabe:** Wandle die gegebene Dezimalzahl (100-255) in eine 8-stellige Binärzahl um
- **Generator-Code:**
  ```python
  import random
  decimal = random.randint(100, 255)
  values = {"decimal": decimal}
  ```
- **Solution-Code:**
  ```python
  result = format(values["decimal"], '08b')
  ```
- **Expected Answer Variable:** result
- **Max Attempts:** 3
- **Show Solution:** Ja

## Wie es funktioniert

### Für Studierende:
1. Aufgabe öffnen
2. Zufällige Werte werden einmal pro User/Task generiert (z.B. "binary = 10101101")
3. Student rechnet **manuell** das Ergebnis aus
4. Student gibt das Ergebnis ein (z.B. "173")
5. System wertet mit dem versteckten Solution-Code aus
6. Feedback: grün (richtig) oder rot (falsch)
7. Max 3 Versuche, dann Lösung anzeigen

### Technisch:
- **Werte speicherung:** Einmal pro User/Task in `user_tasks.variable_values` gekapselt
- **Code-Ausführung:** Pyodide im Browser (versteckt vom User, nur Werte sichtbar)
- **Vergleich:** `compareAnswers()` vergleicht nummerisch oder als String

## Next Steps

1. **Testen im Assignment 7:** Beide Aufgaben sollten jetzt verfügbar sein
2. **Weitere Beispiele:** Du kannst weitere Code Random Complex Aufgaben nachlegen nach demselben Muster
3. **Weitere Konversions-Typen** möglich:
   - Hex ↔ Dezimal
   - Oktal ↔ Dezimal
   - Temperatur-Konversionieren
   - Einheiten-Konvertierung

## Dateiänderungen
- [sql/migrations/run_011.php](sql/migrations/run_011.php) - Enum-Update-Script
- [sql/migrations/011_add_code_random_complex.sql](sql/migrations/011_add_code_random_complex.sql) - SQL-Migration
- [sql/migrations/007_add_task_types_and_options.sql](sql/migrations/007_add_task_types_and_options.sql) - Enum included in initial migration
- [scripts/create_example_code_random_complex.php](scripts/create_example_code_random_complex.php) - Beispiel-Aufgaben-Generator
