"""
Test-Szenarien für MwSt-Rechner Aufgabe
Zeigt verschiedene gültige und ungültige Lösungen
"""

print("=" * 60)
print("Test-Szenarien für MwSt-Rechner")
print("=" * 60)

# ===== GÜLTIGE LÖSUNGEN =====

print("\n✅ GÜLTIGE LÖSUNG 1: Standard-Implementation")
print("-" * 60)
code1 = '''
netto = input("Nettopreis: ")
netto = float(netto)
brutto = netto * 1.19
print(f"Bruttopreis: {brutto:.2f} Euro")
'''
print(code1)
print("→ Erfüllt alle Anforderungen: input(), float(), *1.19, print()")

print("\n✅ GÜLTIGE LÖSUNG 2: Kompakte Version")
print("-" * 60)
code2 = '''
netto = float(input("Nettopreis: "))
print(f"Brutto: {netto * 1.19:.2f}€")
'''
print(code2)
print("→ Erfüllt alle Anforderungen (kompakter)")

print("\n✅ GÜLTIGE LÖSUNG 3: Alternative Berechnung")
print("-" * 60)
code3 = '''
netto = int(input("Netto: "))
mwst = netto * 0.19
brutto = netto + mwst
print("Brutto:", brutto)
'''
print(code3)
print("→ Erfüllt alle Anforderungen (Addition statt Multiplikation)")

print("\n✅ GÜLTIGE LÖSUNG 4: Mit zusätzlichen Informationen")
print("-" * 60)
code4 = '''
netto = float(input("Nettopreis eingeben: "))
brutto = netto * 1.19
mwst = netto * 0.19
print(f"Bruttopreis: {brutto:.2f} Euro")
print(f"(enthält {mwst:.2f} Euro MwSt)")
'''
print(code4)
print("→ Erfüllt alle Anforderungen + Zusatzinfo")


# ===== UNGÜLTIGE LÖSUNGEN =====

print("\n\n❌ UNGÜLTIGE LÖSUNGEN")
print("=" * 60)

print("\n❌ FEHLER 1: Kein input()")
print("-" * 60)
code_bad1 = '''
netto = 100
brutto = netto * 1.19
print(f"Brutto: {brutto}")
'''
print(code_bad1)
print("→ Fehlt: input() für Benutzereingabe")

print("\n❌ FEHLER 2: Keine Typ-Konvertierung")
print("-" * 60)
code_bad2 = '''
netto = input("Netto: ")
brutto = netto * 1.19  # TypeError: String kann nicht multipliziert werden!
print(brutto)
'''
print(code_bad2)
print("→ Fehlt: float() oder int() Konvertierung")

print("\n❌ FEHLER 3: Falsche Berechnung")
print("-" * 60)
code_bad3 = '''
netto = float(input("Netto: "))
brutto = netto * 0.19  # Nur die MwSt, nicht Brutto!
print(f"Brutto: {brutto}")
'''
print(code_bad3)
print("→ Fehlt: Falsche Formel (nur MwSt statt Brutto)")

print("\n❌ FEHLER 4: Kein print()")
print("-" * 60)
code_bad4 = '''
netto = float(input("Netto: "))
brutto = netto * 1.19
# Keine Ausgabe!
'''
print(code_bad4)
print("→ Fehlt: print() für Ausgabe")


# ===== TEST-CASES PRÜFUNG =====

print("\n\n🧪 AUTOMATISCHE TEST-CASES")
print("=" * 60)

test_cases = [
    {
        "name": "Code-Check 1",
        "pattern": r"input\s*\(",
        "description": "Verwendet input() für Eingabe",
        "status": "✓ Pattern gefunden" if "input(" in code1 else "✗ Pattern fehlt"
    },
    {
        "name": "Code-Check 2", 
        "pattern": r"(float|int)\s*\(",
        "description": "Konvertiert Eingabe zu float oder int",
        "status": "✓ Pattern gefunden" if "float(" in code1 or "int(" in code1 else "✗ Pattern fehlt"
    },
    {
        "name": "Code-Check 3",
        "pattern": r"(\*\s*1\.19|\+.*0\.19|\*\s*0\.19)",
        "description": "Multipliziert mit 1.19 oder addiert 0.19",
        "status": "✓ Pattern gefunden" if "*1.19" in code1 or "*0.19" in code1 or "+0.19" in code1.replace(" ", "") else "✗ Pattern fehlt"
    },
    {
        "name": "Code-Check 4",
        "pattern": r"print\s*\(",
        "description": "Gibt Ergebnis mit print() aus",
        "status": "✓ Pattern gefunden" if "print(" in code1 else "✗ Pattern fehlt"
    }
]

for test in test_cases:
    print(f"\n{test['name']}: {test['description']}")
    print(f"   Pattern: {test['pattern']}")
    print(f"   Status: {test['status']}")

print("\n" + "=" * 60)
print("✅ Alle Test-Cases definiert und dokumentiert!")
print("=" * 60)


# ===== BEISPIEL-AUSFÜHRUNGEN =====

print("\n\n📊 BEISPIEL-AUSFÜHRUNGEN")
print("=" * 60)

test_values = [100, 49.99, 250, 1000, 0.99, 15.50]

print("\n| Nettopreis | Bruttopreis (19% MwSt) | MwSt-Betrag |")
print("|------------|------------------------|-------------|")

for netto in test_values:
    brutto = netto * 1.19
    mwst = netto * 0.19
    print(f"| {netto:>10.2f} € | {brutto:>20.2f} € | {mwst:>9.2f} € |")

print("\n" + "=" * 60)
