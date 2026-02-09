<?php
/**
 * Update VARIABLE Tasks to use #INIT Start# / #INIT End# blocks
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "UPDATE: VARIABLE-Tasks mit INIT-Blöcken\n";
echo "========================================\n\n";

// Find assignment
$stmt = $conn->prepare("
    SELECT id FROM assignments 
    WHERE title = 'Test-Typen: Output, Function, Variable'
    ORDER BY id DESC LIMIT 1
");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "❌ Assignment nicht gefunden!\n";
    exit(1);
}

$assignment = $result->fetch_assoc();
$assignmentId = $assignment['id'];

echo "✓ Assignment gefunden (ID: $assignmentId)\n\n";

// ============================================
// Update Task 4: Quadrat (VARIABLE)
// ============================================
$template4 = '#INIT Start#
x = 7  # Testwert für RUN - wird bei CHECK ignoriert
#INIT End#

# Lösung:
quadrat = x * x';

$desc4 = '**TEST-TYP: VARIABLE**

Berechnen Sie das Quadrat der Variablen `x` und speichern Sie es in `quadrat`.

**So arbeiten Sie:**

📝 **INIT-Block:** Der Code zwischen `#INIT Start#` und `#INIT End#` wird bei CHECK ignoriert!

1. **▶ RUN (Entwickeln):**
   - Ändern Sie `x = 7` im INIT-Block auf andere Werte
   - Testen Sie Ihren Code

2. **✓ CHECK (Abgeben):**
   - Lassen Sie den INIT-Block unverändert
   - System ignoriert ihn und testet mit eigenen Werten

**Vorteil:** Sie müssen nichts löschen! Der INIT-Block hilft beim Testen und wird automatisch bei CHECK ignoriert.

**Test-Struktur:**
```json
{
  "type": "variable",
  "init_vars": {"x": 5},
  "expected_vars": {"quadrat": 25}
}
```';

$solution4 = '#INIT Start#
x = 7
#INIT End#

quadrat = x * x';

$updateStmt = $conn->prepare("
    UPDATE tasks 
    SET code_template = ?, description = ?, solution_code = ?
    WHERE assignment_id = ? AND title LIKE '%Quadrat berechnen (VARIABLE)%'
");
$updateStmt->bind_param('sssi', $template4, $desc4, $solution4, $assignmentId);
$updateStmt->execute();
echo "✓ Task 4 aktualisiert (Quadrat berechnen)\n";

// ============================================
// Update Task 5: Summe/Produkt (VARIABLE)
// ============================================
$template5 = '#INIT Start#
a = 8   # Testwerte für RUN
b = 12
#INIT End#

# Lösung:
summe = a + b
produkt = a * b';

$desc5 = '**TEST-TYP: VARIABLE (mehrere Variablen)**

Berechnen Sie Summe und Produkt von `a` und `b`.

**So arbeiten Sie:**

📝 **INIT-Block:** Der Code zwischen `#INIT Start#` und `#INIT End#` wird bei CHECK ignoriert!

1. **▶ RUN:** Ändern Sie die Werte im INIT-Block zum Testen
2. **✓ CHECK:** System ignoriert INIT-Block automatisch

**Vorteil:** Kein Löschen nötig! Einfach CHECK klicken.

**Test-Struktur:**
```json
{
  "type": "variable",
  "init_vars": {"a": 5, "b": 10},
  "expected_vars": {"summe": 15, "produkt": 50}
}
```';

$solution5 = '#INIT Start#
a = 8
b = 12
#INIT End#

summe = a + b
produkt = a * b';

$updateStmt2 = $conn->prepare("
    UPDATE tasks 
    SET code_template = ?, description = ?, solution_code = ?
    WHERE assignment_id = ? AND title LIKE '%Summe und Produkt%'
");
$updateStmt2->bind_param('sssi', $template5, $desc5, $solution5, $assignmentId);
$updateStmt2->execute();
echo "✓ Task 5 aktualisiert (Summe und Produkt)\n";

// ============================================
// Update Task 6: Gerade Zahlen (VARIABLE)
// ============================================
$template6 = '#INIT Start#
zahlen = [13, 14, 15, 16]  # Testwerte für RUN
#INIT End#

# Lösung:
gerade = [x for x in zahlen if x % 2 == 0]';

$desc6 = '**TEST-TYP: VARIABLE (mit Listen)**

Filtern Sie aus der Liste `zahlen` alle geraden Zahlen in `gerade`.

**So arbeiten Sie:**

📝 **INIT-Block:** Werte im INIT-Block werden bei CHECK ignoriert!

1. **▶ RUN:** Test mit eigenen Listen im INIT-Block
2. **✓ CHECK:** System verwendet eigene Test-Listen

**Vorteil:** Nichts löschen, einfach CHECK klicken!

**Test-Struktur:**
```json
{
  "type": "variable",
  "init_vars": {"zahlen": [1,2,3,4,5]},
  "expected_vars": {"gerade": [2,4]}
}
```';

$solution6 = '#INIT Start#
zahlen = [13, 14, 15, 16]
#INIT End#

gerade = [x for x in zahlen if x % 2 == 0]';

$updateStmt3 = $conn->prepare("
    UPDATE tasks 
    SET code_template = ?, description = ?, solution_code = ?
    WHERE assignment_id = ? AND title LIKE '%Gerade Zahlen%'
");
$updateStmt3->bind_param('sssi', $template6, $desc6, $solution6, $assignmentId);
$updateStmt3->execute();
echo "✓ Task 6 aktualisiert (Gerade Zahlen filtern)\n";

echo "\n========================================\n";
echo "✓ Alle VARIABLE-Tasks aktualisiert!\n";
echo "========================================\n\n";

echo "WIE INIT-BLÖCKE FUNKTIONIEREN:\n";
echo "===============================\n\n";

echo "1. ENTWICKELN & TESTEN (RUN):\n";
echo "   - Student ändert Werte im INIT-Block\n";
echo "   - Klickt ▶ RUN\n";
echo "   - Code läuft MIT INIT-Block\n";
echo "   - Python kennt Typen (x ist int, zahlen ist list)\n\n";

echo "2. OFFIZIELL TESTEN (CHECK):\n";
echo "   - Student lässt INIT-Block unverändert\n";
echo "   - Klickt ✓ CHECK\n";
echo "   - System entfernt INIT-Block via Regex\n";
echo "   - System setzt eigene init_vars\n";
echo "   - Mehrere Tests mit verschiedenen Werten\n\n";

echo "PYTHON-LOGIK BEI CHECK:\n";
echo "------------------------\n";
echo "import re\n";
echo "# Entferne INIT-Block\n";
echo "pattern = r'#INIT Start#.*?#INIT End#'\n";
echo "code_without_init = re.sub(pattern, '', user_code, flags=re.DOTALL)\n\n";
echo "# Setze Test-Werte\n";
echo "namespace = {'a': 5, 'b': 10}\n";
echo "exec(code_without_init, namespace)\n\n";

echo "VORTEILE:\n";
echo "---------\n";
echo "✅ Student muss nichts löschen\n";
echo "✅ Python bekommt Typinformationen\n";
echo "✅ Klare Trennung: Test vs. Lösung\n";
echo "✅ Weniger fehleranfällig\n";
echo "✅ IDE-Unterstützung (Autocomplete kennt Typen)\n\n";

echo "BEISPIEL:\n";
echo "---------\n\n";

echo "Code im Editor:\n";
echo "───────────────\n";
echo "#INIT Start#\n";
echo "a = 8\n";
echo "b = 12\n";
echo "#INIT End#\n\n";
echo "summe = a + b\n";
echo "produkt = a * b\n\n";

echo "Bei RUN:\n";
echo "────────\n";
echo "→ Läuft mit a=8, b=12\n";
echo "→ summe=20, produkt=96\n\n";

echo "Bei CHECK:\n";
echo "──────────\n";
echo "→ INIT-Block wird entfernt\n";
echo "→ Code wird: 'summe = a + b\\nprodukt = a * b'\n";
echo "→ System setzt a=3, b=7 (Test 1)\n";
echo "→ summe=10, produkt=21 ✓\n";
echo "→ System setzt a=5, b=10 (Test 2)\n";
echo "→ summe=15, produkt=50 ✓\n\n";

$conn->close();
