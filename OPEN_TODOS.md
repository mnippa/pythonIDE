# Open TODOs

## 2026-10-18

- **Vertagt: GUI fuer strukturierte Variablen-Testwerte reparieren.** Reproduziert auf Live bei Assignment 45, Task 509: Nach Aenderung von Gruen auf Gelb war die Musterloesung korrekt, der Variablen-Test jedoch nicht mehr. Fuer die einzelne Variable `farben` muss die Liste ein einzelner Erwartungswert bleiben (`expected_values: [["Rot", "Gelb", "Blau"]]`), statt in drei Erwartungswerte zerlegt zu werden. Task 509 wurde direkt korrigiert; der GUI-Fehler ist noch offen.
  - Ursache in `public/js/admin-dashboard.js`: Anzeige per `.join(', ')` verliert die Struktur verschachtelter Werte; Eingabe per `.split(',')` zerlegt Listen und Texte mit Kommas. Nicht escapte Werte in HTML-Attributen koennen ausserdem Anfuehrungszeichen beschaedigen. Betrifft Initial- und Erwartungswerte.
  - Gewuenschte Loesung: komfortable, typgerechte GUI fuer Werte pro Variable, einschliesslich Listen/verschachtelter Werte. JSON nur als optionaler Notnagel/Expertenmodus, nicht als regulaerer Eingabeweg. Bestehende einfache Eingaben und gespeicherte Tests erhalten; Legacy- und neue Teststrukturen eindeutig behandeln.
  - Validierung: verlustfreier Laden-Bearbeiten-Speichern-Roundtrip; Anzahl der Werte gegen Variablennamen pruefen und Fehler sichtbar melden. Regressionstests fuer Listen, mehrere Variablen, Strings mit Kommas/Anfuehrungszeichen, Zahlen und Booleans sowie Abgleich mit dem Variablen-Test-Runner. Keine Aenderung an dieser GUI bis zur Wiederaufnahme.

## 2026-05-22

- Folder-task Clone: Beim Klonen von Aufgaben mit Ordnerstruktur werden die Dateien aktuell nicht konsistent mit uebernommen. Ursache analysieren und Clone-Flow fuer Folder-Inhalte und Policies nachziehen.
