<?php
/**
 * Demonstration: INIT-Block System
 */

echo "═══════════════════════════════════════════\n";
echo "   INIT-BLOCK SYSTEM - LIVE DEMO\n";
echo "═══════════════════════════════════════════\n\n";

// ============================================
// BEISPIEL 1: Quadrat berechnen
// ============================================
echo "BEISPIEL 1: Quadrat berechnen\n";
echo "───────────────────────────────────────────\n\n";

$studentCode1 = '#INIT Start#
x = 7  # Testwert für RUN
#INIT End#

quadrat = x * x';

echo "Code im Editor:\n";
echo "┌─────────────────────────────────────────┐\n";
foreach (explode("\n", $studentCode1) as $line) {
    echo "│ " . str_pad($line, 41) . "│\n";
}
echo "└─────────────────────────────────────────┘\n\n";

// RUN simulation
echo "▶ RUN-Button geklickt:\n";
echo "  → Code läuft MIT INIT-Block\n";
echo "  → x = 7\n";
echo "  → quadrat = 49\n";
echo "  ✓ Output angezeigt\n\n";

// CHECK simulation
echo "✓ CHECK-Button geklickt:\n\n";

// Regex removes INIT block
$pattern = '/#INIT Start#.*?#INIT End#/s';
$codeWithoutInit = preg_replace($pattern, '', $studentCode1);
$codeWithoutInit = trim($codeWithoutInit);

echo "  1. System entfernt INIT-Block:\n";
echo "  ┌─────────────────────────────────────────┐\n";
foreach (explode("\n", $codeWithoutInit) as $line) {
    echo "  │ " . str_pad($line, 41) . "│\n";
}
echo "  └─────────────────────────────────────────┘\n\n";

echo "  2. System führt Tests aus:\n\n";

// Test cases
$tests = [
    ['x' => 5, 'expected' => 25],
    ['x' => -3, 'expected' => 9],
    ['x' => 10, 'expected' => 100],
];

foreach ($tests as $i => $test) {
    echo "  Test " . ($i + 1) . ":\n";
    echo "    namespace = {'x': {$test['x']}}\n";
    echo "    exec(code_without_init, namespace)\n";
    echo "    → quadrat = {$test['expected']} ";
    echo "✓\n\n";
}

echo "  🎉 Alle Tests bestanden!\n\n";

// ============================================
// BEISPIEL 2: Summe und Produkt
// ============================================
echo "\n═══════════════════════════════════════════\n";
echo "BEISPIEL 2: Summe und Produkt\n";
echo "───────────────────────────────────────────\n\n";

$studentCode2 = '#INIT Start#
a = 8
b = 12
#INIT End#

summe = a + b
produkt = a * b';

echo "Code im Editor:\n";
echo "┌─────────────────────────────────────────┐\n";
foreach (explode("\n", $studentCode2) as $line) {
    echo "│ " . str_pad($line, 41) . "│\n";
}
echo "└─────────────────────────────────────────┘\n\n";

// Student changes values and tests
echo "▶ RUN (Test 1): a=8, b=12\n";
echo "  → summe = 20\n";
echo "  → produkt = 96\n\n";

$studentCode2Modified = '#INIT Start#
a = 3   # Student ändert Werte
b = 7
#INIT End#

summe = a + b
produkt = a * b';

echo "▶ RUN (Test 2): Student ändert Werte\n";
echo "┌─────────────────────────────────────────┐\n";
foreach (explode("\n", $studentCode2Modified) as $line) {
    echo "│ " . str_pad($line, 41) . "│\n";
}
echo "└─────────────────────────────────────────┘\n";
echo "  → summe = 10\n";
echo "  → produkt = 21\n\n";

echo "✓ CHECK-Button geklickt:\n\n";

$codeWithoutInit2 = preg_replace($pattern, '', $studentCode2Modified);
$codeWithoutInit2 = trim($codeWithoutInit2);

echo "  1. INIT-Block entfernt:\n";
echo "  ┌─────────────────────────────────────────┐\n";
foreach (explode("\n", $codeWithoutInit2) as $line) {
    echo "  │ " . str_pad($line, 41) . "│\n";
}
echo "  └─────────────────────────────────────────┘\n\n";

echo "  2. System Tests:\n\n";

$tests2 = [
    ['a' => 3, 'b' => 7, 'summe' => 10, 'produkt' => 21],
    ['a' => 5, 'b' => 10, 'summe' => 15, 'produkt' => 50],
    ['a' => -2, 'b' => 4, 'summe' => 2, 'produkt' => -8],
];

foreach ($tests2 as $i => $test) {
    echo "  Test " . ($i + 1) . ":\n";
    echo "    init_vars = {'a': {$test['a']}, 'b': {$test['b']}}\n";
    echo "    → summe = {$test['summe']} ✓\n";
    echo "    → produkt = {$test['produkt']} ✓\n\n";
}

echo "  🎉 Alle Tests bestanden!\n\n";

// ============================================
// BEISPIEL 3: Listen filtern
// ============================================
echo "\n═══════════════════════════════════════════\n";
echo "BEISPIEL 3: Gerade Zahlen filtern\n";
echo "───────────────────────────────────────────\n\n";

$studentCode3 = '#INIT Start#
zahlen = [13, 14, 15, 16]
#INIT End#

gerade = [x for x in zahlen if x % 2 == 0]';

echo "Code im Editor:\n";
echo "┌─────────────────────────────────────────┐\n";
foreach (explode("\n", $studentCode3) as $line) {
    echo "│ " . str_pad($line, 41) . "│\n";
}
echo "└─────────────────────────────────────────┘\n\n";

echo "▶ RUN:\n";
echo "  → zahlen = [13, 14, 15, 16]\n";
echo "  → gerade = [14, 16]\n\n";

echo "✓ CHECK:\n\n";

$codeWithoutInit3 = preg_replace($pattern, '', $studentCode3);
$codeWithoutInit3 = trim($codeWithoutInit3);

echo "  1. INIT-Block entfernt:\n";
echo "  ┌─────────────────────────────────────────┐\n";
foreach (explode("\n", $codeWithoutInit3) as $line) {
    echo "  │ " . str_pad($line, 41) . "│\n";
}
echo "  └─────────────────────────────────────────┘\n\n";

echo "  2. System Tests:\n\n";

$tests3 = [
    ['zahlen' => [1, 2, 3, 4, 5], 'gerade' => [2, 4]],
    ['zahlen' => [10, 15, 20], 'gerade' => [10, 20]],
    ['zahlen' => [1, 3, 5], 'gerade' => []],
];

foreach ($tests3 as $i => $test) {
    $zahlenStr = json_encode($test['zahlen']);
    $geradeStr = json_encode($test['gerade']);
    echo "  Test " . ($i + 1) . ":\n";
    echo "    init_vars = {'zahlen': {$zahlenStr}}\n";
    echo "    → gerade = {$geradeStr} ✓\n\n";
}

echo "  🎉 Alle Tests bestanden!\n\n";

// ============================================
// TECHNISCHE DETAILS
// ============================================
echo "\n═══════════════════════════════════════════\n";
echo "TECHNISCHE DETAILS\n";
echo "═══════════════════════════════════════════\n\n";

echo "REGEX PATTERN:\n";
echo "──────────────\n";
echo "pattern = r'#INIT Start#.*?#INIT End#'\n";
echo "flags = re.DOTALL\n\n";
echo "- '#INIT Start#' → Literal Start-Marker\n";
echo "- '.*?' → Non-greedy match (minimal)\n";
echo "- '#INIT End#' → Literal End-Marker\n";
echo "- re.DOTALL → '.' matched auch Newlines\n\n";

echo "PYTHON AUSFÜHRUNG BEI CHECK:\n";
echo "─────────────────────────────\n";
echo "import re\n\n";
echo "# Schritt 1: INIT-Block entfernen\n";
echo "pattern = r'#INIT Start#.*?#INIT End#'\n";
echo "code = re.sub(pattern, '', user_code, flags=re.DOTALL)\n\n";
echo "# Schritt 2: Namespace vorbereiten\n";
echo "namespace = {}\n";
echo "namespace.update(test['init_vars'])  # z.B. {'x': 5}\n\n";
echo "# Schritt 3: Code ausführen\n";
echo "exec(code, namespace)\n\n";
echo "# Schritt 4: Ergebnis prüfen\n";
echo "actual = namespace['quadrat']\n";
echo "expected = test['expected_vars']['quadrat']\n";
echo "assert actual == expected\n\n";

echo "VORTEILE:\n";
echo "─────────\n";
echo "✅ Student muss nichts löschen\n";
echo "✅ Python kennt Typen während Entwicklung\n";
echo "✅ IDE-Autocomplete funktioniert\n";
echo "✅ Klare visuelle Trennung\n";
echo "✅ Weniger fehleranfällig\n";
echo "✅ Einfacher Workflow: RUN → CHECK\n\n";

echo "WORKFLOW:\n";
echo "─────────\n";
echo "1. Student entwickelt mit INIT-Block\n";
echo "2. Student ändert Werte im INIT für Tests (▶ RUN)\n";
echo "3. Student lässt INIT unverändert (✓ CHECK)\n";
echo "4. System entfernt INIT automatisch\n";
echo "5. System testet mit verschiedenen init_vars\n\n";

echo "═══════════════════════════════════════════\n";
echo "✓ DEMO ABGESCHLOSSEN\n";
echo "═══════════════════════════════════════════\n";
