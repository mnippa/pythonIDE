# INIT-Block System für VARIABLE Tests

## Das Problem

Bei VARIABLE-Tests müssen Studenten mit verschiedenen Werten experimentieren (RUN), aber das System testet mit anderen Werten (CHECK).

**Frühere Lösung:** "Werte hinzufügen → RUN → Werte löschen → CHECK"
- ❌ Fehleranfällig (Student vergisst Löschen)
- ❌ Umständlich
- ❌ Keine IDE-Unterstützung während Entwicklung

## Die Lösung: INIT-Blöcke

```python
#INIT Start#
x = 7  # Testwert für RUN
#INIT End#

# Lösung:
quadrat = x * x
```

### Wie es funktioniert

#### 1. RUN-Button (Entwickeln & Testen)
- Code läuft **MIT** INIT-Block
- Student kann Werte im INIT-Block ändern
- Python kennt Typen → IDE-Unterstützung

```python
# Student ändert x = 7 auf x = 10
#INIT Start#
x = 10
#INIT End#

quadrat = x * x  # quadrat = 100
```

#### 2. CHECK-Button (Offizielles Testen)
- System **entfernt** INIT-Block automatisch
- System setzt eigene `init_vars`
- Mehrere Tests mit verschiedenen Werten

**JavaScript/Python-Logik:**
```python
import re

# 1. Entferne INIT-Block
pattern = r'#INIT Start#.*?#INIT End#'
code_without_init = re.sub(pattern, '', user_code, flags=re.DOTALL)

# 2. Erstelle Namespace mit Test-Werten
namespace = {'x': 5}  # Test 1
exec(code_without_init, namespace)
# → quadrat = 25 ✓

namespace = {'x': -3}  # Test 2
exec(code_without_init, namespace)
# → quadrat = 9 ✓
```

## Vorteile

✅ **Kein Löschen nötig** - Student lässt INIT-Block unverändert
✅ **Typinformationen** - Python kennt `x` als int, `zahlen` als list
✅ **IDE-Unterstützung** - Autocomplete funktioniert
✅ **Klare Trennung** - Test-Code vs. Lösungs-Code visuell getrennt
✅ **Weniger Fehler** - System ignoriert automatisch

## Code-Template Struktur

### Einzelne Variable
```python
#INIT Start#
x = 7  # Testwert für RUN - wird bei CHECK ignoriert
#INIT End#

# Lösung:
quadrat = x * x
```

### Mehrere Variablen
```python
#INIT Start#
a = 8   # Testwerte für RUN
b = 12
#INIT End#

# Lösung:
summe = a + b
produkt = a * b
```

### Listen/Arrays
```python
#INIT Start#
zahlen = [13, 14, 15, 16]  # Testwerte für RUN
#INIT End#

# Lösung:
gerade = [x for x in zahlen if x % 2 == 0]
```

## Task Description Template

```markdown
**TEST-TYP: VARIABLE**

[Task-Beschreibung]

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

## Technische Implementierung

### JavaScript (assignments.js)

```javascript
async function runVariableTests(pyodide, code, testCases, validationMode) {
  const testOutputs = await pyodide.runPythonAsync(`
import re

user_code = ${JSON.stringify(code)}

# Remove INIT blocks for CHECK
pattern = r'#INIT Start#.*?#INIT End#'
code_without_init = re.sub(pattern, '', user_code, flags=re.DOTALL)

# Run tests with different init_vars
for test in test_cases:
    namespace = {}
    namespace.update(test['init_vars'])
    exec(code_without_init, namespace)
    # Check expected_vars...
  `);
}
```

### Regex Pattern

```python
pattern = r'#INIT Start#.*?#INIT End#'
```

- `#INIT Start#` - Literal start marker
- `.*?` - Non-greedy match (minimal)
- `#INIT End#` - Literal end marker
- `re.DOTALL` - `.` matches newlines

**Wichtig:** Non-greedy `.*?` statt greedy `.*` → Bei mehreren Blöcken wird nur der erste entfernt

### Edge Cases

**Fall 1: Mehrere INIT-Blöcke**
```python
#INIT Start#
x = 5
#INIT End#

# ... Code ...

#INIT Start#
y = 10
#INIT End#
```
→ Beide Blöcke werden entfernt (regex findet alle matches)

**Fall 2: Verschachtelte Kommentare**
```python
#INIT Start#
# Kommentar mit "#INIT End#" drin
x = 5
#INIT End#
```
→ Block wird korrekt bis zum echten `#INIT End#` entfernt

**Fall 3: INIT-Block in String**
```python
text = "#INIT Start# ... #INIT End#"
```
→ Wird AUCH entfernt! **Lösung:** Strings mit INIT-Markern vermeiden

## Workflow-Beispiel

### Schritt 1: Student entwickelt
```python
#INIT Start#
a = 8
b = 12
#INIT End#

summe = a + b
produkt = a * b
```
▶ **RUN** → Output: `summe=20, produkt=96`

### Schritt 2: Student testet andere Werte
```python
#INIT Start#
a = 3   # ← Geändert
b = 7   # ← Geändert
#INIT End#

summe = a + b
produkt = a * b
```
▶ **RUN** → Output: `summe=10, produkt=21`

### Schritt 3: Student gibt ab
```python
#INIT Start#
a = 8   # ← Unverändert lassen!
b = 12
#INIT End#

summe = a + b
produkt = a * b
```
✓ **CHECK**

**System macht:**
```python
# 1. Entferne INIT
code = "summe = a + b\nprodukt = a * b"

# 2. Test 1
namespace = {'a': 3, 'b': 7}
exec(code, namespace)
# summe=10 ✓, produkt=21 ✓

# 3. Test 2
namespace = {'a': 5, 'b': 10}
exec(code, namespace)
# summe=15 ✓, produkt=50 ✓

# 4. Test 3
namespace = {'a': -2, 'b': 4}
exec(code, namespace)
# summe=2 ✓, produkt=-8 ✓
```

## Vergleich: Alte vs. Neue Lösung

| Aspekt | Alte Lösung (Löschen) | Neue Lösung (INIT-Block) |
|--------|----------------------|--------------------------|
| Workflow | Add → RUN → **DELETE** → CHECK | Add → RUN → CHECK |
| Fehleranfälligkeit | Hoch (vergessen zu löschen) | Niedrig (automatisch) |
| Typinformationen | Nur nach manuellem Add | Immer vorhanden |
| IDE-Unterstützung | Nicht im Template | Ja, im Template |
| Student-Erfahrung | Umständlich | Intuitiv |
| Code-Klarheit | Unklar was gelöscht werden muss | Visuell klar getrennt |

## Best Practices

### ✅ DO
- INIT-Block immer am Anfang
- Klare Kommentare im Block
- Realistische Test-Werte (nicht zu einfach)
- Ein INIT-Block pro Task

### ❌ DON'T
- INIT-Marker in Strings verwenden
- INIT-Block mitten im Code
- Mehrere INIT-Blöcke ohne Grund
- INIT-Block für einfache Konstanten

## Migration von alten Tasks

**Alt:**
```python
# Für manuelles Testen: Füge temporär "x = 7" ein
# Für CHECK: Entferne alle x = ... Zeilen!
quadrat = x * ___
```

**Neu:**
```python
#INIT Start#
x = 7  # Testwert für RUN - wird bei CHECK ignoriert
#INIT End#

# Lösung:
quadrat = x * ___
```

**Migration Script:** `scripts/update_to_init_blocks.php`

## FAQ

**Q: Muss der Student den INIT-Block wirklich drin lassen?**
A: Ja! Das System entfernt ihn automatisch bei CHECK. Student kann Werte ändern für Tests, muss aber nichts löschen.

**Q: Was passiert wenn Student INIT-Block löscht?**
A: Bei CHECK würde Code ohne init_vars laufen → Fehler "NameError: name 'x' is not defined"

**Q: Kann Student eigene Variable im INIT-Block definieren?**
A: Ja, aber die werden bei CHECK auch entfernt. Nur Variablen in `init_vars` werden gesetzt.

**Q: Funktioniert das mit komplexen Typen (Dictionaries, Objects)?**
A: Ja! Beliebige Python-Objekte können in `init_vars` sein:
```json
{
  "init_vars": {
    "person": {"name": "Max", "age": 25},
    "scores": [85, 90, 78]
  }
}
```

## Zusammenfassung

Das INIT-Block System löst das RUN vs. CHECK Problem elegant:

1. **Student-Freundlich:** Kein Löschen, kein Fehler
2. **Technisch Sauber:** Regex entfernt Block zuverlässig
3. **IDE-Kompatibel:** Python kennt Typen während Entwicklung
4. **Visuell Klar:** INIT-Block zeigt klar "das ist nur zum Testen"

**Kern-Konzept:** 
- RUN = Mit INIT-Block
- CHECK = Ohne INIT-Block + system init_vars
