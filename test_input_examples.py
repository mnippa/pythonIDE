# Test Examples for input() Support

# Example 1: Simple greeting
print("=== Beispiel 1: Begrüßung ===")
name = input("Wie heißt du? ")
print(f"Hallo, {name}!")

# Example 2: Age calculator
print("\n=== Beispiel 2: Altersberechnung ===")
birth_year = input("In welchem Jahr wurdest du geboren? ")
current_year = 2026
age = current_year - int(birth_year)
print(f"Du bist ungefähr {age} Jahre alt.")

# Example 3: Simple calculator
print("\n=== Beispiel 3: Taschenrechner ===")
a = input("Erste Zahl: ")
b = input("Zweite Zahl: ")
result = float(a) + float(b)
print(f"{a} + {b} = {result}")

# Example 4: Temperature converter
print("\n=== Beispiel 4: Temperatur-Umrechner ===")
celsius = input("Temperatur in Celsius: ")
fahrenheit = float(celsius) * 9/5 + 32
print(f"{celsius}°C = {fahrenheit:.1f}°F")

# Example 5: Multiple inputs loop
print("\n=== Beispiel 5: Zahlen addieren ===")
total = 0
count = input("Wie viele Zahlen möchtest du addieren? ")
for i in range(int(count)):
    num = input(f"Zahl {i+1}: ")
    total += float(num)
print(f"Summe: {total}")
