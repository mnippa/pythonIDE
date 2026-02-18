# Performance-Analyse: api/tasks/list.php

## Gefundene Bottlenecks:

### 1. **N+2 Query Problem** 🔴 KRITISCH
**Zeilen 89-139**: Für JEDE Single/Multiple Choice Task werden 2 zusätzliche Queries gemacht:

```php
// Query 1: Check ob User versucht hat (pro Task!)
$attemptStmt = $conn->prepare('SELECT status FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1');

// Query 2: Load Options (pro Task!)
$optionsStmt = $conn->prepare('SELECT id, option_text, image_url, is_correct, order_num FROM task_options WHERE task_id = ? ...');
```

**Beispiel**: Bei 12 Tasks mit 6 Choice-Tasks:
- 1 Hauptquery (tasks)
- 6 Queries für attempt-checks = +6 Roundtrips
- 6 Queries für options = +6 Roundtrips
- **TOTAL: 13 Queries statt 1-2!**

Jede Query hat ~10-20ms Overhead → Das erklärt die **230ms!**

---

### 2. **Ineffiziente Spalten-Optimierung**
**Zeilen 40**: Lädt ALLE Spalten:
```php
SELECT id, assignment_id, title, description, position, problem_type, code_template, hint, hint1, hint2, hint3, stoff, max_attempts, iterations_count, show_solution, show_generator_code, test_cases, validation_mode, expected_output, solution_code, task_type, question_text, image_url, correct_answer, variable_overrides
```

Aber `solution_code` und `expected_output` werden nur bei bestimmten Bedingungen verwendet (Zeilen 77-82).
→ Unnötige Daten in RAM geladen und transferiert

---

### 3. **Fehlende Indizes**
Keine Indizes auf:
- `user_tasks(user_id, task_id)` - wird 6x abgefragt!
- `task_options(task_id)` - wird 6x abgefragt!

---

## Zeitmessung (geschätzt):

| Operation | Zeit | Anzahl | Summe |
|-----------|------|--------|-------|
| 1 Haupt-Query | 10ms | 1 | 10ms |
| attemptStmt pro Task | 5ms | 6 | 30ms |
| optionsStmt pro Task | 8ms | 6 | 48ms |
| JSON-Encoding | 5ms | 1 | 5ms |
| **Netzwerk-Latenz** | 20ms | 13 Queries | **260ms** |
| **TOTAL** | | | **353ms** |

→ **230ms verloren hauptsächlich wegen 13 Datenbankroundtrips!**

---

## Lösungsmaßnahmen (Priorität):

### 🟢 CRITICAL: Batch-Load implementieren
- [ ] Alle task_ids in einer Query laden und Options/Attempts batchen
- Ergebnis: 13 Queries → 3-4 Queries = 75% Reduktion!

### 🟡 IMPORTANT: Spalten optimieren  
- [ ] Nur benötigte Spalten initial laden
- [ ] solution_code/expected_output conditional fetch

### 🟡 IMPORTANT: Indizes erstellen
- [ ] CREATE INDEX idx_user_tasks_user_task ON user_tasks(user_id, task_id)
- [ ] CREATE INDEX idx_task_options_task ON task_options(task_id)
