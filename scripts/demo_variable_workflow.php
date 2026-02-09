<?php
/**
 * Demo: VARIABLE-Testing Workflow
 * Zeigt wie RUN vs CHECK funktioniert
 */

echo "========================================\n";
echo "VARIABLE-TESTING: RUN vs CHECK DEMO\n";
echo "========================================\n\n";

echo "AUFGABE: Berechne Summe und Produkt von a und b\n\n";

// ============================================
// PHASE 1: ENTWICKELN (mit RUN)
// ============================================
echo "┌─────────────────────────────────────┐\n";
echo "│ PHASE 1: ENTWICKELN (RUN-Button)   │\n";
echo "└─────────────────────────────────────┘\n\n";

echo "Student schreibt:\n";
echo "─────────────────\n";
$dev_code = "# Testwerte für Entwicklung
a = 8
b = 12

# Lösung
summe = a + b
produkt = a * b

print(f'Summe: {summe}')
print(f'Produkt: {produkt}')";
echo $dev_code . "\n\n";

echo "Student klickt ▶ RUN:\n";
echo "─────────────────────\n";
echo "Ausgabe: Summe: 20\n";
echo "         Produkt: 96\n\n";
echo "✅ Code funktioniert!\n\n\n";

// ============================================
// PHASE 2: TESTEN mit anderen Werten
// ============================================
echo "┌─────────────────────────────────────┐\n";
echo "│ PHASE 2: TESTEN (RUN-Button)       │\n";
echo "└─────────────────────────────────────┘\n\n";

echo "Student testet mit anderen Werten:\n";
echo "───────────────────────────────────\n";
$test_code = "# Andere Testwerte
a = 5
b = 10

# Lösung (gleich)
summe = a + b
produkt = a * b

print(f'Test: {summe} == 15? {produkt} == 50?')";
echo $test_code . "\n\n";

echo "Student klickt ▶ RUN:\n";
echo "─────────────────────\n";
echo "Ausgabe: Test: 15 == 15? 50 == 50?\n\n";
echo "✅ Auch mit anderen Werten korrekt!\n\n\n";

// ============================================
// PHASE 3A: FALSCH - Mit Wertzuweisungen
// ============================================
echo "┌──────────────────────────────────────────┐\n";
echo "│ PHASE 3A: ❌ FALSCH (CHECK-Button)      │\n";
echo "└──────────────────────────────────────────┘\n\n";

echo "Student vergisst Testwerte zu entfernen:\n";
echo "─────────────────────────────────────────\n";
$wrong_code = "a = 5  # ❌ FALSCH! Muss entfernt werden!
b = 10

summe = a + b
produkt = a * b";
echo $wrong_code . "\n\n";

echo "Student klickt ✓ CHECK:\n";
echo "────────────────────────\n\n";

echo "System-Logik:\n";
echo "─────────────\n";
echo "1. namespace = {}\n";
echo "2. namespace.update({'a': 3, 'b': 7})  # Test 1: a=3, b=7\n";
echo "   → namespace = {'a': 3, 'b': 7}\n\n";
echo "3. exec(student_code, namespace)\n";
echo "   Führt aus: a = 5  ← ÜBERSCHREIBT a!\n";
echo "              b = 10 ← ÜBERSCHREIBT b!\n";
echo "              summe = a + b\n";
echo"              produkt = a * b\n\n";
echo "   → namespace = {'a': 5, 'b': 10, 'summe': 15, 'produkt': 50}\n\n";

echo "4. Check Expected:\n";
echo "   summe soll sein: 10 (3+7)\n";
echo "   summe ist: 15\n";
echo "   ❌ TEST FEHLGESCHLAGEN!\n\n";

echo "GRUND:\n";
echo "Student-Code überschreibt die Test-Werte!\n";
echo "System testet mit a=3, b=7 aber Code setzt a=5, b=10\n\n\n";

// ============================================
// PHASE 3B: RICHTIG - Ohne Wertzuweisungen
// ============================================
echo "┌──────────────────────────────────────────┐\n";
echo "│ PHASE 3B: ✅ RICHTIG (CHECK-Button)     │\n";
echo "└──────────────────────────────────────────┘\n\n";

echo "Student entfernt ALLE Wertzuweisungen:\n";
echo "───────────────────────────────────────\n";
$correct_code = "# Alle Testwerte entfernt!
summe = a + b
produkt = a * b";
echo $correct_code . "\n\n";

echo "Student klickt ✓ CHECK:\n";
echo "────────────────────────\n\n";

echo "System-Logik:\n";
echo "─────────────\n\n";

echo "TEST 1:\n";
echo "1. namespace = {}\n";
echo "2. namespace.update({'a': 3, 'b': 7})\n";
echo "   → namespace = {'a': 3, 'b': 7}\n\n";
echo "3. exec(student_code, namespace)\n";
echo "   Führt aus: summe = a + b  (a=3, b=7)\n";
echo "              produkt = a * b\n\n";
echo "   → namespace = {'a': 3, 'b': 7, 'summe': 10, 'produkt': 21}\n\n";
echo "4. Check Expected:\n";
echo "   summe soll sein: 10 ✓\n";
echo "   produkt soll sein: 21 ✓\n";
echo "   ✅ TEST 1 BESTANDEN!\n\n";

echo "TEST 2:\n";
echo "1. namespace = {}\n";
echo "2. namespace.update({'a': 5, 'b': 10})\n";
echo "   → namespace = {'a': 5, 'b': 10}\n\n";
echo "3. exec(student_code, namespace)\n";
echo "   Führt aus: summe = a + b  (a=5, b=10)\n";
echo "              produkt = a * b\n\n";
echo "   → namespace = {'a': 5, 'b': 10, 'summe': 15, 'produkt': 50}\n\n";
echo "4. Check Expected:\n";
echo "   summe soll sein: 15 ✓\n";
echo "   produkt soll sein: 50 ✓\n";
echo "   ✅ TEST 2 BESTANDEN!\n\n";

echo "TEST 3:\n";
echo "1. namespace = {}\n";
echo "2. namespace.update({'a': 0, 'b': 100})\n";
echo "   → namespace = {'a': 0, 'b': 100}\n\n";
echo "3. exec(student_code, namespace)\n";
echo "   Führt aus: summe = a + b  (a=0, b=100)\n";
echo "              produkt = a * b\n\n";
echo "   → namespace = {'a': 0, 'b': 100, 'summe': 100, 'produkt': 0}\n\n";
echo "4. Check Expected:\n";
echo "   summe soll sein: 100 ✓\n";
echo "   produkt soll sein: 0 ✓\n";
echo "   ✅ TEST 3 BESTANDEN!\n\n";

echo "═══════════════════════════════════════════\n";
echo "  ✅ ALLE 3 TESTS BESTANDEN!\n";
echo "═══════════════════════════════════════════\n\n";

// ============================================
// ZUSAMMENFASSUNG
// ============================================
echo "┌──────────────────────────────────────────┐\n";
echo "│ ZUSAMMENFASSUNG                          │\n";
echo "└──────────────────────────────────────────┘\n\n";

echo "▶ RUN-Button:\n";
echo "  • Student fügt temporär Testwerte ein\n";
echo "  • Code läuft mit diesen Werten\n";
echo "  • Für Entwicklung und Debugging\n\n";

echo "✓ CHECK-Button:\n";
echo "  • Student MUSS alle Wertzuweisungen entfernen!\n";
echo "  • System setzt automatisch verschiedene Werte\n";
echo "  • Mehrere Tests mit verschiedenen init_vars\n\n";

echo "🔑 REGEL:\n";
echo "   Bei VARIABLE-Tests darf der finale Code\n";
echo "   KEINE Wertzuweisungen für init_vars enthalten!\n\n";

echo "   Nur Berechnungen:\n";
echo "   ✅ summe = a + b\n";
echo "   ✅ produkt = a * b\n\n";

echo "   Keine Wertzuweisungen:\n";
echo "   ❌ a = 5\n";
echo "   ❌ b = 10\n\n";

echo "═══════════════════════════════════════════\n\n";
