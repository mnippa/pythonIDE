# INIT-Block System - Summary

## ✅ Implementiert & Verifiziert

### Kern-Konzept
Student setzt Test-Werte im `#INIT Start#` / `#INIT End#` Block:
- **RUN:** Block wird AUSGEFÜHRT → Student kann testen
- **CHECK:** Block wird ENTFERNT → System setzt eigene Werte

### Workflow

**Student:**
```python
#INIT Start#
x = 7  # ← Student kann Wert ändern
#INIT End#

quadrat = x * x
```

1. **Entwickeln:** Code schreiben
2. **Testen:** `x = 7` ändern → ▶ **RUN** → Output sehen
3. **Abgeben:** Nichts ändern → ✓ **CHECK** → System testet

### System (CHECK)

**JavaScript → Python:**
```python
import re

# 1. Entferne INIT-Block
pattern = r'#INIT Start#.*?#INIT End#'
code = re.sub(pattern, '', user_code, flags=re.DOTALL)
# → code = "quadrat = x * x"

# 2. Setze Test-Werte
namespace = {'x': 5}  # Test 1
exec(code, namespace)
# → quadrat = 25 ✓

namespace = {'x': -3}  # Test 2
exec(code, namespace)
# → quadrat = 9 ✓
```

## 📁 Dateien

### Core Implementation
- [public/js/assignments.js](../public/js/assignments.js) - `runVariableTests()` mit Regex
  ```javascript
  pattern = r'#INIT Start#.*?#INIT End#'
  code_without_init = re.sub(pattern, '', user_code, flags=re.DOTALL)
  ```

### Scripts
- [scripts/create_test_type_examples.php](../scripts/create_test_type_examples.php) - Task-Templates mit INIT
- [scripts/update_to_init_blocks.php](../scripts/update_to_init_blocks.php) - Update Script
- [scripts/verify_init_blocks.php](../scripts/verify_init_blocks.php) - Verifikation
- [scripts/demo_init_blocks.php](../scripts/demo_init_blocks.php) - Visuelle Demo

### Documentation
- [docs/init-block-system.md](init-block-system.md) - Vollständige Dokumentation
- [docs/init-block-quickref.md](init-block-quickref.md) - Schnell-Referenz
- [docs/test-types.md](test-types.md) - Alle Test-Typen
- [docs/variable-testing-workflow.md](variable-testing-workflow.md) - Alter Ansatz (überholt)

### Database
- **Tasks 25, 26, 27** (Assignment 5) - Alte Tasks mit INIT aktualisiert
- **Tasks 31, 32, 33** (Assignment 5) - Neue Tasks mit INIT

## 🎯 Vorteile

| Aspekt | Alter Ansatz | INIT-Block |
|--------|-------------|-----------|
| Workflow | Add → RUN → **DELETE** → CHECK | Add → RUN → CHECK |
| Fehler | ❌ Vergessen zu löschen | ✅ Automatisch |
| Typen | ❌ Nur nach Add | ✅ Immer vorhanden |
| IDE | ❌ Keine Hilfe | ✅ Autocomplete |
| UX | ❌ Umständlich | ✅ Intuitiv |

## 📋 Beispiele

### Template: Einzelne Variable
```python
#INIT Start#
x = 7  # Testwert für RUN - wird bei CHECK ignoriert
#INIT End#

# Lösung:
quadrat = x * ___
```

### Template: Mehrere Variablen
```python
#INIT Start#
a = 8   # Testwerte für RUN
b = 12
#INIT End#

# Lösung:
summe = a ___ b
produkt = a ___ b
```

### Template: Listen
```python
#INIT Start#
zahlen = [13, 14, 15, 16]  # Testwerte für RUN
#INIT End#

# Lösung:
gerade = [x for x in zahlen if x % 2 ___ 0]
```

## 🔧 JSON Test Cases

```json
{
  "type": "variable",
  "init_vars": {"x": 5},
  "expected_vars": {"quadrat": 25}
}
```

**Mehrere Variablen:**
```json
{
  "type": "variable",
  "init_vars": {"a": 5, "b": 10},
  "expected_vars": {"summe": 15, "produkt": 50}
}
```

**Listen:**
```json
{
  "type": "variable",
  "init_vars": {"zahlen": [1,2,3,4,5]},
  "expected_vars": {"gerade": [2,4]}
}
```

## 📝 Task Description Template

```markdown
**TEST-TYP: VARIABLE**

[Aufgabenbeschreibung]

**So arbeiten Sie:**

📝 **INIT-Block:** Der Code zwischen `#INIT Start#` und `#INIT End#` wird bei CHECK ignoriert!

1. **▶ RUN (Entwickeln):**
   - Ändern Sie Werte im INIT-Block zum Testen
   - Debuggen Sie Ihren Code

2. **✓ CHECK (Abgeben):**
   - Lassen Sie den INIT-Block unverändert
   - System ignoriert ihn und testet mit eigenen Werten

**Vorteil:** Sie müssen nichts löschen! Der INIT-Block hilft beim Testen und wird automatisch bei CHECK ignoriert.
```

## ✅ Verifikation

```bash
# Verify INIT blocks in database
php scripts/verify_init_blocks.php

# Demo: Show how INIT blocks work
php scripts/demo_init_blocks.php

# Update existing tasks to use INIT blocks
php scripts/update_to_init_blocks.php

# Create new tasks with INIT blocks
php scripts/create_test_type_examples.php
```

**Ergebnis:**
```
✅ ALLE VARIABLE-Tasks haben INIT-Blöcke!
  - code_template: ✓ VORHANDEN
  - solution_code: ✓ VORHANDEN
  - description: ✓ Erklärt INIT-Block
```

## 🎓 Student-Perspektive

**Früher (Löschen-Ansatz):**
```python
# Für Test: x = 7 hinzufügen
# Für CHECK: x = 7 löschen ← FEHLERANFÄLLIG!
quadrat = x * x
```

**Jetzt (INIT-Block):**
```python
#INIT Start#
x = 7  # Kann ich ändern für Tests
#INIT End#

quadrat = x * x  # ← Lösung bleibt unverändert
```

**Workflow:**
1. ▶ **RUN:** Wert im INIT ändern → testen
2. ✓ **CHECK:** Einfach klicken, nichts löschen!

## 🔬 Technische Details

### Regex Pattern
```python
pattern = r'#INIT Start#.*?#INIT End#'
flags = re.DOTALL
```

- `#INIT Start#` - Literal Start-Marker
- `.*?` - Non-greedy match (minimal)
- `#INIT End#` - Literal End-Marker
- `re.DOTALL` - `.` matched auch Newlines

### JavaScript Implementation
```javascript
const testOutputs = await pyodide.runPythonAsync(`
import re

user_code = ${JSON.stringify(code)}

# Remove INIT blocks for CHECK
pattern = r'#INIT Start#.*?#INIT End#'
code_without_init = re.sub(pattern, '', user_code, flags=re.DOTALL)

# Run tests
for test in test_cases:
    namespace = {}
    namespace.update(test['init_vars'])
    exec(code_without_init, namespace)
    # ... validate expected_vars ...
`);
```

### Edge Cases

**Mehrere Blöcke:**
```python
#INIT Start#
x = 5
#INIT End#

# Code

#INIT Start#
y = 10
#INIT End#
```
→ Beide werden entfernt ✓

**INIT in String:**
```python
text = "#INIT Start# test #INIT End#"
```
→ Wird AUCH entfernt! ⚠️ Vermeiden!

**Student löscht INIT:**
- RUN: Funktioniert ✓
- CHECK: `NameError: name 'x' is not defined` ❌

## 🚀 Migration

### Alte Tasks aktualisieren

**Alt:**
```python
# Für manuelles Testen: x = 7 hinzufügen
# Für CHECK: x = ... entfernen!
quadrat = x * ___
```

**Neu:**
```python
#INIT Start#
x = 7  # Testwert
#INIT End#

quadrat = x * ___
```

**Script:**
```bash
php scripts/update_to_init_blocks.php
```

## ❓ FAQ

**Q: Muss INIT-Block am Anfang sein?**
A: Ja, für Konsistenz. Technisch funktioniert überall.

**Q: Was wenn Student INIT löscht?**
A: RUN ok, CHECK fehlt Variable → NameError

**Q: Können Studenten eigene Variablen im INIT definieren?**
A: Ja, aber die werden bei CHECK auch entfernt. Nur `init_vars` bleiben.

**Q: Funktioniert mit komplexen Typen?**
A: Ja! Listen, Dicts, nested Objects - alles möglich.

**Q: Wie sieht Student dass INIT ignoriert wird?**
A: Description erklärt es klar mit Emoji 📝

## 🎉 Zusammenfassung

### Problem Gelöst
Student brauchte Werte zum Testen (RUN), aber System braucht andere Werte (CHECK).

### Lösung: INIT-Block
- Student: Werte im Block ändern → RUN → testen
- System: Block entfernen → init_vars setzen → CHECK

### Ergebnis
✅ Kein Löschen mehr nötig
✅ Weniger Fehler
✅ IDE-Unterstützung
✅ Bessere UX
✅ Typen immer bekannt

**Der INIT-Block macht VARIABLE-Testing so einfach wie OUTPUT/FUNCTION-Testing!**
