# File Tree & Code Validation System

## Übersicht

Das System bietet:
1. **Dateibaum-Anzeige** - Zeigt die Standard-Ordnerstruktur pro Projekt
2. **Test Cases** - JSON-basierte Testfälle in der Task-Datenbank
3. **Code-Validierung** - Automatischer Vergleich von Ausgabe vs. erwartet

## 1. Dateibaum

### Struktur
Jedes Projekt erhält automatisch diese Struktur:
```
Projektname/
├── Projektname.py      (das Hauptskript - Leerzeichen → Unterstriche)
├── images/             (für Bilder/Plots)
└── scripts/            (für Hilfsskripte)
```

### Verwendung
```javascript
// In editor.php wird automatisch initialisiert:
const treeManager = new FileTreeManager('file-tree-wrapper');
const structure = treeManager.initializeDefaultStructure('Mein Projekt');
treeManager.render(structure);
```

### Toggle
- Dateibaum links neben dem Editor
- Ein-/ausklappbar mit Button "▶ Dateien"
- Responsive: nur auf Bildschirmen > 1200px sichtbar

## 2. Test Cases in Tasks

### Datenbank-Schema
```sql
ALTER TABLE tasks ADD COLUMN test_cases LONGTEXT;
ALTER TABLE tasks ADD COLUMN validation_mode VARCHAR(20) DEFAULT 'loose';
```

### JSON Format
```json
[
  {
    "input": "",
    "expected": "Erwartete Ausgabe"
  },
  {
    "input": "input data",
    "expected": "expected output"
  }
]
```

**Beispiel** (in Task anlegen):
```json
[
  {"input": "", "expected": "5"},
  {"input": "", "expected": "10"},
  {"input": "", "expected": "15"}
]
```

### Validierungsmodi
- **loose** (Standard): Ignoriert Unterschiede bei Whitespace
  - `"hello  world"` === `"hello world"`
  - Zeilenumbrüche werden normalisiert
  
- **strict**: Exakter 1:1 Vergleich

## 3. Code-Validator Klasse

### Initialisierung
```javascript
const validator = new CodeValidator();
```

### Methoden

#### `parseTestCases(jsonString)`
```javascript
const cases = validator.parseTestCases('[{"input":"", "expected":"hello"}]');
```

#### `validate(actualOutput, testCases, mode)`
```javascript
const result = validator.validate(
  'hello\nworld',  // Tatsächliche Ausgabe
  testCases,       // Array von Test Cases
  'loose'          // oder 'strict'
);

// result.passed: boolean
// result.total: int
// result.passedCount: int
// result.message: 'X/Y Tests bestanden'
// result.results: Array [{testNumber, passed, expected, actual, ...}]
```

#### `formatResults(validationResult)`
```javascript
const html = validator.formatResults(result);
// Gibt HTML mit schönem Report zurück
```

## 4. Integration in Editor

### Im Output-Panel
Nach jedem Run werden Test Cases automatisch validiert:

```javascript
// In output-panel wird angezeigt:
<div id="validation-container"></div>
```

Beispiel-HTML:
```html
<div class="validation-report">
  <div class="validation-status validation-success">
    ✓ 3/3 Tests bestanden
  </div>
  <div class="validation-details">
    <div class="test-result test-pass">
      <span class="test-icon">✓</span>
      <span class="test-info">Test 1: bestanden</span>
    </div>
    ...
  </div>
</div>
```

## 5. Admin Dashboard - Test Cases erstellen

Für Assignment-Tasks (admin.php):

```html
<form id="task-form">
  <input id="task-title" placeholder="Task-Name">
  <input id="task-expected" placeholder="Erwartete Ausgabe (einfach)">
  
  <!-- Optional: JSON Test Cases -->
  <textarea id="task-test-cases" placeholder='[{"input":"","expected":"output"}]'></textarea>
  <select id="task-validation-mode">
    <option value="loose">Loose (Whitespace egal)</option>
    <option value="strict">Strict (1:1 Vergleich)</option>
  </select>
</form>
```

## 6. Künftige Erweiterungen

- [ ] Test Cases im Admin-Dashboard editierbar
- [ ] Input-Parameter für Tests (noch nicht implementiert)
- [ ] Regex-Pattern Matching für erweiterte Validierung
- [ ] Test-Reports speichern für Lehrer

## 7. Deployment

### Migration durchführen:
```bash
php scripts/migrate_test_cases.php
```

### Files in Projekt:
- `public/js/file-tree.js` - FileTree Manager
- `public/js/code-validator.js` - Validator Engine
- `public/css/file-tree.css` - Styling
- `public/editor.php` - HTML Integration
- `scripts/migrate_test_cases.php` - DB Migration

### API nicht nötig
Alles läuft clientseitig im Browser!

---
Status: ✅ Implementiert  
Datum: 8. Februar 2026
