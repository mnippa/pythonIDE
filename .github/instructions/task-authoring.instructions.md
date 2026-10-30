---
description: "Use when creating or editing PythonIDE tasks and assignments, especially live tasks, German descriptions, randomized iterations and attempt limits."
---
# Aufgaben erstellen und bearbeiten

- In Titeln, Beschreibungen, Aufgabenstellungen und Hinweisen echte Umlaute und ß verwenden, nicht ae/oe/ue/ss als Ersatz.
- Variablennamen weiterhin mit ae/oe/ue/ss schreiben, zum Beispiel `binaerzahl`, `hoehe` und `groesse`.
- Aufgabenstellungen präzise auf fachlichen Auftrag und erwartete Lösungsform begrenzen, nicht den Lösungsweg vorgeben. Details zu Datenstruktur, Variablennamen, Datentypen und Ausgabe links in die Beschreibung aufnehmen.
- Wenn eigenständige Strukturierung das Lernziel ist, die gesuchte Gliederung, Schlüssel und Datentypen nicht in der Beschreibung vorwegnehmen. Fachliche Angaben und Ergebnisvariablen nennen; konkrete Strukturvorgaben für den Check in Hinweise auslagern. Erst einen eigenen Entwurf verlangen, dann vor dem Check den Abgleich mit den Hinweisen. Feste Prüfschlüssel transparent benennen: Plausible andere Benennungen werden dadurch nicht automatisch akzeptiert. Bei reinen Zugriffsaufgaben darf die vorgegebene Struktur sichtbar bleiben.
- Bei normalen Codeaufgaben sinnvolle `print`-Ausgaben verlangen und mitprüfen, wenn dafür keine aufwendigen Layoutvorgaben nötig sind. Zahlenformatierung und fachlich bedeutungslose Reihenfolgen tolerant prüfen.
- Das Versuchslimit gilt für die gesamte Aufgabe über alle Iterationen hinweg, nicht je Iteration. Bei iterativen Quizaufgaben zählen Fehlversuche; richtige Antworten verbrauchen keinen Versuch.
- Verlangte Anzahlen zufälliger Aufgabentypen exakt einhalten und nur ihre Reihenfolge zufällig mischen. Unabhängige Zufallsziehungen garantieren keine Verteilung.
- Bestehende Aufgaben nicht durch neue Generatorfunktionen verändern. Neue Laufzeitfunktionen ausdrücklich pro Aufgabe aktivieren und den unveränderten bisherigen Pfad testen.
- Für `code_random_complex` immer ein gültiges `code_template` mit `values` oder `{placeholder}` hinterlegen. Generator, Lösungscode und Eingabevariablen konsistent halten und auch Admin-Bearbeitung/Entsperren prüfen.
- Live-Aufgaben vor Änderungen erneut lesen und manuelle Anpassungen erhalten. Gespeicherte Bearbeitungsstände niemals ohne Zustimmung zurücksetzen.
