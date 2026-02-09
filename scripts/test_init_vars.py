import json

# Simuliere das System
test_case = {
    'init_vars': {'a': 5, 'b': 10},
    'expected_vars': {'summe': 15, 'produkt': 50}
}

# === SZENARIO 1: Student lässt Test-Werte auskommentiert ===
print("SZENARIO 1: Test-Werte auskommentiert")
print("=" * 50)

user_code_1 = """
# a = 7  # auskommentiert
summe = a + b
produkt = a * b
"""

namespace_1 = {}
namespace_1.update(test_case['init_vars'])  # Setzt a=5, b=10
print(f"Namespace vor exec: {namespace_1}")

exec(compile(user_code_1, "<usercode>", "exec"), namespace_1)
print(f"Namespace nach exec: {namespace_1}")
print(f"Summe: {namespace_1['summe']} (erwartet: 15)")
print(f"Produkt: {namespace_1['produkt']} (erwartet: 50)")
print()

# === SZENARIO 2: Student vergisst auszukommentieren ===
print("SZENARIO 2: Student vergisst einzukommentieren")
print("=" * 50)

user_code_2 = """
a = 7  # NICHT auskommentiert!
summe = a + b
produkt = a * b
"""

namespace_2 = {}
namespace_2.update(test_case['init_vars'])  # Setzt a=5, b=10
print(f"Namespace vor exec: {namespace_2}")

exec(compile(user_code_2, "<usercode>", "exec"), namespace_2)
print(f"Namespace nach exec: {namespace_2}")
print(f"Summe: {namespace_2['summe']} (erwartet: 15)")
print(f"Produkt: {namespace_2['produkt']} (erwartet: 50)")
print()

# === PROBLEM ===
print("❌ PROBLEM:")
print("Wenn Student 'a = 7' im Code hat, überschreibt das die init_vars!")
print("Der Test schlägt fehl obwohl die Logik korrekt ist.\n")

# === LÖSUNG ===
print("✅ LÖSUNG:")
print("Student darf KEINE Wertzuweisungen für init_vars machen!")
print("Code-Template sollte nur Berechnungen enthalten.")
