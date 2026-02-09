<?php
/**
 * Update Variable Test Templates
 * Fügt Kommentare für manuelles Testen hinzu
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "========================================\n";
echo "UPDATE: Code-Templates für VARIABLE-Tests\n";
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

// Update Task 4: Quadrat (VARIABLE)
$updateStmt = $conn->prepare("
    UPDATE tasks 
    SET 
        code_template = ?,
        description = ?
    WHERE assignment_id = ? 
    AND title LIKE '%Quadrat berechnen (VARIABLE)%'
");

$template4 = '# Berechne das Quadrat von x
# Für manuelles Testen: Füge temporär "x = 7" ein, dann RUN klicken
# Für CHECK: Entferne alle x = ... Zeilen!
quadrat = x * ___';

$desc4 = '**TEST-TYP: VARIABLE**

Berechnen Sie das Quadrat der Variablen `x` und speichern Sie es in `quadrat`.

**So arbeiten Sie:**

1. **Entwickeln & Testen:**
   - Fügen Sie temporär `x = 7` am Anfang ein
   - Klicken Sie ▶ **RUN** zum Testen
   - Debuggen Sie Ihren Code

2. **Abgeben:**
   - **WICHTIG:** Entfernen Sie alle `x = ...` Zeilen!
   - Nur die Berechnung bleibt: `quadrat = x * x`
   - Klicken Sie ✓ **CHECK**
   - System testet mit verschiedenen x-Werten

**Warum?** Wenn `x = 7` im Code bleibt, überschreibt das die Auto-Test-Werte!

**Test-Struktur:**
```json
{
  "type": "variable",
  "init_vars": {"x": 5},
  "expected_vars": {"quadrat": 25}
}
```';

$updateStmt->bind_param('ssi', $template4, $desc4, $assignmentId);
$updateStmt->execute();
echo "✓ Task 4 aktualisiert (Zeilen: {$updateStmt->affected_rows})\n";

// Update Task 5: Summe/Produkt (VARIABLE)
$updateStmt2 = $conn->prepare("
    UPDATE tasks 
    SET 
        code_template = ?,
        description = ?
    WHERE assignment_id = ? 
    AND title LIKE '%Summe und Produkt%'
");

$template5 = '# Berechne Summe und Produkt von a und b
# Für manuelles Testen: Füge temporär "a = 8" und "b = 12" ein
# Für CHECK: Entferne alle a = ... und b = ... Zeilen!
summe = a ___ b
produkt = a ___ b';

$desc5 = '**TEST-TYP: VARIABLE (mehrere Variablen)**

Berechnen Sie Summe und Produkt von `a` und `b`.

**So arbeiten Sie:**

1. **Entwickeln & Testen:**
   - Fügen Sie temporär `a = 8` und `b = 12` am Anfang ein
   - Klicken Sie ▶ **RUN** zum Testen
   - Debuggen Sie Ihren Code

2. **Abgeben:**
   - **WICHTIG:** Entfernen Sie alle `a = ...` und `b = ...` Zeilen!
   - Nur die Berechnungen bleiben
   - Klicken Sie ✓ **CHECK**
   - System testet mit verschiedenen Wertepaaren

**Warum?** Wertzuweisungen überschreiben die Auto-Test-Werte!

**Test-Struktur:**
```json
{
  "type": "variable",
  "init_vars": {"a": 5, "b": 10},
  "expected_vars": {"summe": 15, "produkt": 50}
}
```';

$updateStmt2->bind_param('ssi', $template5, $desc5, $assignmentId);
$updateStmt2->execute();
echo "✓ Task 5 aktualisiert (Zeilen: {$updateStmt2->affected_rows})\n";

// Update Task 6: Gerade Zahlen (VARIABLE)
$updateStmt3 = $conn->prepare("
    UPDATE tasks 
    SET 
        code_template = ?,
        description = ?
    WHERE assignment_id = ? 
    AND title LIKE '%Gerade Zahlen%'
");

$template6 = '# Filtere gerade Zahlen aus der Liste
# Für manuelles Testen: Füge temporär "zahlen = [13, 14, 15, 16]" ein
# Für CHECK: Entferne alle zahlen = ... Zeilen!
gerade = [x for x in zahlen if x % 2 ___ 0]';

$desc6 = '**TEST-TYP: VARIABLE (mit Listen)**

Filtern Sie aus der Liste `zahlen` alle geraden Zahlen in `gerade`.

**So arbeiten Sie:**

1. **Entwickeln & Testen:**
   - Fügen Sie temporär `zahlen = [13, 14, 15, 16]` am Anfang ein
   - Klicken Sie ▶ **RUN** zum Testen
   - Debuggen Sie Ihren Code

2. **Abgeben:**
   - **WICHTIG:** Entfernen Sie alle `zahlen = ...` Zeilen!
   - Nur die Berechnung bleibt
   - Klicken Sie ✓ **CHECK**
   - System testet mit verschiedenen Listen

**Warum?** Wertzuweisungen überschreiben die Auto-Test-Werte!

**Test-Struktur:**
```json
{
  "type": "variable",
  "init_vars": {"zahlen": [1,2,3,4,5]},
  "expected_vars": {"gerade": [2,4]}
}
```';

$updateStmt3->bind_param('ssi', $template6, $desc6, $assignmentId);
$updateStmt3->execute();
echo "✓ Task 6 aktualisiert (Zeilen: {$updateStmt3->affected_rows})\n";

echo "\n========================================\n";
echo "✓ Alle VARIABLE-Templates aktualisiert!\n";
echo "========================================\n\n";

echo "WIE ES FUNKTIONIERT:\n";
echo "-------------------\n\n";

echo "1. MANUELLES TESTEN (RUN-Button):\n";
echo "   - Student kommentiert Test-Zeilen aus:\n";
echo "     # x = 7  →  x = 7\n";
echo "   - Klickt ▶ RUN\n";
echo "   - Code läuft mit x=7\n\n";

echo "2. OFFIZIELLES TESTEN (CHECK-Button):\n";
echo "   - Test-Zeilen bleiben auskommentiert (oder nicht, egal)\n";
echo "   - Klickt ✓ CHECK\n";
echo "   - System setzt init_vars (überschreibt alles)\n";
echo "   - Mehrere Tests laufen automatisch\n\n";

echo "WICHTIG:\n";
echo "--------\n";
echo "Bei CHECK überschreiben init_vars IMMER die Werte!\n";
echo "Auch wenn Student eigene Werte setzt, werden sie ignoriert.\n";
echo "Das garantiert faire Tests.\n\n";

echo "CODE-STRUKTUR:\n";
echo "--------------\n";
echo "namespace = {}\n";
echo "namespace.update(test['init_vars'])  # System setzt Werte\n";
echo "exec(user_code, namespace)           # Student-Code läuft\n";
echo "# Wenn Student 'a = 999' schreibt, überschreibt das init_vars!\n";
echo "# → Deshalb sollten Test-Zeilen auskommentiert bleiben\n\n";

$conn->close();
