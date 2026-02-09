# INIT-Block Quick Reference

## Übersicht

Mit INIT-Blöcken können Studenten VARIABLE-Tasks einfach testen ohne Werte löschen zu müssen.

```python
#INIT Start#
x = 7  # Testwert
#INIT End#

# Lösung
quadrat = x * x
```

## Workflow

### Student Perspective

1. **Entwickeln:** Code schreiben
2. **Testen:** Werte im `#INIT Start#` Block ändern → ▶ **RUN**
3. **Abgeben:** INIT-Block unverändert lassen → ✓ **CHECK**

✅ **Kein Löschen nötig!**

### System Behavior

- **RUN:** Code läuft mit INIT-Block
- **CHECK:** INIT-Block wird ignoriert, System setzt eigene Werte

## Syntax

### Marker
```python
#INIT Start#
# ... Variablen hier ...
#INIT End#
```

### Regeln
- ✅ Beliebige Python-Statements im Block
- ✅ Mehrere Variablen
- ✅ Listen, Dictionaries, etc.
- ❌ Nicht in Strings verwenden
- ❌ Nicht verschachteln

## Templates

### Einzelne Variable
```python
#INIT Start#
x = 7  # Testwert für RUN - wird bei CHECK ignoriert
#INIT End#

# Lösung:
quadrat = x * x
```

### Zwei Variablen
```python
#INIT Start#
a = 8   # Testwerte für RUN
b = 12
#INIT End#

# Lösung:
summe = a + b
produkt = a * b
```

### Listen
```python
#INIT Start#
zahlen = [13, 14, 15, 16]  # Testwerte für RUN
#INIT End#

# Lösung:
gerade = [x for x in zahlen if x % 2 == 0]
```

### Dictionary
```python
#INIT Start#
person = {"name": "Max", "age": 25}  # Testwert
#INIT End#

# Lösung:
intro = f"{person['name']} ist {person['age']} Jahre alt"
```

## Task Description Template

```markdown
**TEST-TYP: VARIABLE**

[Beschreibung was zu tun ist]

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

## PHP Task Creation

```php
$task = [
    'title' => 'Quadrat berechnen (VARIABLE)',
    'description' => '...mit INIT-Block Beschreibung...',
    'code_template' => '#INIT Start#
x = 7
#INIT End#

quadrat = x * ___',
    'solution_code' => '#INIT Start#
x = 7
#INIT End#

quadrat = x * x',
    'test_cases' => json_encode([
        [
            'type' => 'variable',
            'init_vars' => ['x' => 5],
            'expected_vars' => ['quadrat' => 25]
        ],
        [
            'type' => 'variable',
            'init_vars' => ['x' => -3],
            'expected_vars' => ['quadrat' => 9]
        ]
    ])
];
```

## Technische Details

### JavaScript Implementation

```javascript
async function runVariableTests(pyodide, code, testCases, validationMode) {
  const testOutputs = await pyodide.runPythonAsync(`
import re

# Remove INIT blocks
pattern = r'#INIT Start#.*?#INIT End#'
code_without_init = re.sub(pattern, '', user_code, flags=re.DOTALL)

# Run tests
for test in test_cases:
    namespace = {}
    namespace.update(test['init_vars'])
    exec(code_without_init, namespace)
    # ... check expected_vars ...
  `);
}
```

### Regex Pattern

```python
pattern = r'#INIT Start#.*?#INIT End#'
flags = re.DOTALL
```

- `.*?` = Non-greedy (minimal match)
- `re.DOTALL` = `.` matches newlines

## Beispiele

### Beispiel 1: RUN mit verschiedenen Werten

```python
#INIT Start#
x = 5  # ← Student ändert auf 5
#INIT End#

quadrat = x * x  # → quadrat = 25
```

▶ **RUN** → Output: `25`

```python
#INIT Start#
x = 10  # ← Student ändert auf 10
#INIT End#

quadrat = x * x  # → quadrat = 100
```

▶ **RUN** → Output: `100`

### Beispiel 2: CHECK mit System-Werten

```python
#INIT Start#
x = 7  # ← Wird ignoriert!
#INIT End#

quadrat = x * x
```

✓ **CHECK**

System macht:
```python
# 1. Entfernt INIT-Block
code = "quadrat = x * x"

# 2. Test 1: x=5
namespace = {'x': 5}
exec(code, namespace)
# → quadrat = 25 ✓

# 3. Test 2: x=-3
namespace = {'x': -3}
exec(code, namespace)
# → quadrat = 9 ✓
```

## FAQ

**Q: Muss der INIT-Block am Anfang sein?**
A: Ja, aus Konsistenz-Gründen. Technisch könnte er überall sein.

**Q: Können mehrere INIT-Blöcke existieren?**
A: Technisch ja (alle werden entfernt), aber nicht empfohlen. Ein Block am Anfang ist clearer.

**Q: Was wenn Student INIT-Block löscht?**
A: Bei RUN funktioniert's, bei CHECK kommt `NameError: name 'x' is not defined`.

**Q: Können INIT-Blöcke verschachtelt sein?**
A: Nein, nicht unterstützt. Ein Block pro Task.

**Q: Was wenn INIT-Marker in String?**
A: Wird auch entfernt! Vermeiden Sie:
```python
text = "#INIT Start# test #INIT End#"  # ❌ Wird entfernt!
```

## Vorteile vs. "Löschen"-Ansatz

| Aspekt | Löschen-Ansatz | INIT-Block |
|--------|---------------|-----------|
| Workflow | Add → RUN → DELETE → CHECK | Add → RUN → CHECK |
| Fehler | Oft vergessen zu löschen | Automatisch |
| Typen | Nur nach Add | Immer da |
| IDE | Keine Hilfe | Autocomplete |
| UX | Umständlich | Intuitiv |

## Zusammenfassung

✅ **Student:** INIT-Block unverändert lassen, einfach CHECK klicken
✅ **System:** INIT-Block automatisch entfernen, eigene Werte setzen
✅ **Vorteil:** Kein manuelles Löschen, weniger Fehler, bessere UX
