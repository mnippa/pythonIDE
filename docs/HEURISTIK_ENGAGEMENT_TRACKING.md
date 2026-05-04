# Heuristik-Plan: Engagement-Tracking & Plausibilitätsprüfung

**Ziel:** Nicht exakte Zeiterfassung, sondern belastbare Signale darüber,  
ob ein Student eine Aufgabe ernsthaft bearbeitet hat — und langfristig  
Heuristiken, die auffällige Einreichungen (Abtippen, Musterlösung, zu schnell) erkennen.

---

## Bereits umgesetzt (Mai 2026)

| Feature | Datei | Status |
|---|---|---|
| `active_seconds` per Heartbeat | `api/user_tasks/heartbeat.php` | ✅ |
| `run_count` | `api/user_tasks/update.php` | ✅ |
| Page Visibility API (pausiert bei Tab-Wechsel / Bildschirmsperre) | `public/js/assignments.js` | ✅ |
| Named Event Listener (werden bei Task-Wechsel sauber entfernt) | `public/js/assignments.js` | ✅ |
| Aktivitätszone: gesamtes `document` statt nur Monaco-Editor | `public/js/assignments.js` | ✅ |

---

## Schritt 2 — `edit_event_count` (leichtgewichtig, hohe Aussagekraft)

### Idee
Zählen, wie oft der Student den Code tatsächlich verändert hat —  
unabhängig von Zeit oder Mausbewegung.

### Implementierung

**DB-Migration:**
```sql
ALTER TABLE user_tasks ADD COLUMN edit_event_count INT NOT NULL DEFAULT 0;
```

**Frontend (`public/js/assignments.js`):**
```js
// In loadTaskIntoEditor, nach setEditorToInitPy:
let _editEventBuffer = 0;
editor.onDidChangeModelContent(() => {
  _editEventBuffer++;
});

// Im bestehenden Heartbeat-Interval (alle 30s) zusätzlich mitsenden:
if (_editEventBuffer > 0) {
  sendActivityHeartbeat(taskId, seconds, true, _editEventBuffer);
  _editEventBuffer = 0;
}
```

**Backend (`api/user_tasks/heartbeat.php`):**
```php
$edit_delta = isset($data['edit_event_delta']) ? (int)$data['edit_event_delta'] : 0;
// Im INSERT ... ON DUPLICATE KEY UPDATE:
edit_event_count = edit_event_count + VALUES(edit_event_count)
```

### Warum wertvoll
- Robust gegen Idle, Tab-Wechsel, aufgeklappten Laptop
- < 3 Edit-Events bei `passed` → sehr verdächtig
- Unterscheidet "Lösung angeschaut + copy-paste" von echtem Tippen

---

## Schritt 3 — `code_delta_score` (server-seitig beim Submit)

### Idee
Beim Einreichen/Speichern: Wie stark weicht `current_code` vom  
`code_template` ab? Wert 0–1, wobei 1 = identisch = nie verändert.

### Implementierung

**DB-Migration:**
```sql
ALTER TABLE user_tasks ADD COLUMN code_delta_score DECIMAL(4,3) DEFAULT NULL;
```

**Backend (`api/user_tasks/submit.php` oder `update.php`):**
```php
// PHP similar_text() oder Levenshtein als einfache Approximation:
similar_text($code_template, $current_code, $percent);
$score = round((100 - $percent) / 100, 3); // 0 = gleich, 1 = komplett verschieden
// score > 0.9 → stark verändert (gut), score < 0.1 → kaum verändert (verdächtig)
```

### Warum wertvoll
- Erkennt "Template unverändert submitted"
- Erkennt, ob Musterlösung direkt eingefügt wurde (Ähnlichkeit zu `solution_code` prüfbar)
- Kein Frontend-Overhead

---

## Schritt 4 — `first_run_elapsed_seconds`

### Idee
Zeit zwischen erstem Öffnen der Aufgabe (`started_at` aus DB) und  
erstem RUN-Klick. Sehr kurze Werte (< 10s) = Template direkt ausgeführt.

### Implementierung

**DB:** Feld `started_at` bereits vorhanden. Kein Schema-Change nötig.

**Backend (`api/user_tasks/heartbeat.php` oder `update.php`):**  
Beim ersten Increment von `run_count` von 0 auf 1:
```sql
ALTER TABLE user_tasks ADD COLUMN first_run_elapsed_seconds INT DEFAULT NULL;

-- Beim ersten run_count-Increment:
UPDATE user_tasks SET
  first_run_elapsed_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
WHERE user_id = ? AND task_id = ? AND run_count = 1 AND first_run_elapsed_seconds IS NULL;
```

---

## Schritt 5 — Plausibilitäts-Score (Admin-Ansicht)

### Scoring-Logik (server-seitig, on-demand berechnet)

```
score = 100 (Ausgangswert)

// Abzüge:
if run_count = 0:          score -= 40
if run_count = 1:          score -= 20
if edit_event_count < 3:   score -= 30
if code_delta_score < 0.1: score -= 25   (kaum verändert)
if first_run_elapsed < 8s: score -= 20   (sofort ausgeführt)
if active_seconds < 30 AND status = passed: score -= 20

// score 0–100; unter 40 = auffällig
```

### Admin-Ansicht
Neue Spalte in der Task-Ergebnistabelle im Admin-Dashboard:

| Student | Status | Zeit | Runs | Edit-Events | Plausibilität |
|---|---|---|---|---|---|
| Max M. | passed | 8 min | 12 | 87 | 🟢 95 |
| Jana K. | passed | 0:45 | 1 | 2 | 🔴 15 |

Score-Anzeige: 🟢 ≥70 / 🟡 40–69 / 🔴 <40

---

## Reihenfolge-Empfehlung

1. **Schritt 2** (`edit_event_count`) — größter Mehrwert, kleiner Aufwand  
2. **Schritt 3** (`code_delta_score`) — rein server-seitig, kein Frontend  
3. **Schritt 4** (`first_run_elapsed_seconds`) — minimale DB-Änderung  
4. **Schritt 5** — erst wenn Signale 2–4 vorliegen

---

## Betroffene Dateien (Vorschau)

| Datei | Änderung |
|---|---|
| `sql/migrations/run_052.php` | ADD COLUMN edit_event_count, code_delta_score, first_run_elapsed_seconds |
| `api/user_tasks/heartbeat.php` | edit_event_delta entgegennehmen |
| `api/user_tasks/update.php` | code_delta_score + first_run_elapsed beim Submit berechnen |
| `public/js/assignments.js` | Monaco onDidChangeModelContent → edit_event_buffer |
| `public/admin.php` | Plausibilitäts-Score-Spalte in Task-Ergebnistabelle |
