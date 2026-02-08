# Test-Benutzer für Python IDE

Alle Testbenutzer wurden mit realistischen Daten angelegt.

## Admin-Zugang
- **E-Mail**: admin@pythonide.local
- **Passwort**: admin123
- **Name**: Sarah Schmidt
- **Rolle**: Administrator

## Standard-Benutzer (alle mit Passwort: test123)

### Max Müller
- **E-Mail**: max.mueller@example.com
- **Passwort**: test123
- **Projekte**: 2 Beispielprojekte
  - "Hallo Welt" - Einfacher Einstieg
  - "Fibonacci Folge" - Rekursive Berechnung

### Anna Schulz
- **E-Mail**: anna.schulz@example.com
- **Passwort**: test123
- **Projekte**: 2 Beispielprojekte
  - "Liste sortieren" - Array-Operationen
  - "Primzahlen" - Algorithmus bis 100

### Tom Weber
- **E-Mail**: tom.weber@example.com
- **Passwort**: test123
- **Projekte**: 1 Beispielprojekt
  - "Temperatur Umrechner" - Celsius zu Fahrenheit

### Lisa Fischer
- **E-Mail**: lisa.fischer@example.com
- **Passwort**: test123
- **Projekte**: Keine

## Hinweise

- **Keine Benutzernamen mehr**: Die Anmeldung erfolgt jetzt ausschließlich über die E-Mail-Adresse
- **Testdaten**: Alle Beispielprojekte enthalten lauffähigen Python-Code
- **Migration**: Für bestehende Datenbanken muss `sql/migration_remove_username.sql` ausgeführt werden

## Datenbank-Setup

### Neue Installation
```bash
mysql -u root -p < sql/schema.sql
```

### Migration von bestehender Datenbank
```bash
# Erst Namensspalten hinzufügen (falls noch nicht geschehen)
mysql -u root -p pythonide < sql/migration_names.sql

# Dann Username-Spalte entfernen
mysql -u root -p pythonide < sql/migration_remove_username.sql
```
