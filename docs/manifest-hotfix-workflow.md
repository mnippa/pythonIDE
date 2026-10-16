# Manifest- und Hotfix-Workflow

## Ziel

Dieser Workflow trennt sauber zwischen:

- Vollständigem Vergleich gegen einen Soll-Stand
- Kompaktem Sicherheits-Check für kleine Hotfixes
- Git-basiertem Vergleich
- reinen Paket-Transport ohne Manifest

Damit lassen sich Live-Checks, lokale Prüfungen und Hotfix-Erzeugung ohne Verwirrung zuverlässig durchführen.

---

## Grundprinzip

Ein Manifest beschreibt den Soll-Zustand eines Pakets. 
Es enthält Pfad, Dateigröße und SHA-256-Prüfsumme.

Das Compare-Endpoint prüft dann:

- übereinstimmende Dateien
- abweichende Dateien
- fehlende Dateien
- zusätzliche Dateien, die im Vergleichsstand nicht enthalten sind

Wichtig: Wenn das Manifest nur 2 Dateien enthält, dann sind alle anderen deploybaren Dateien im Live-System automatisch `extra`.

Das ist kein Fehler im Vergleich, sondern ein Problem des Manifest-Scopes.

---

## Die vier Standard-Modi plus der neue Entwicklungs-Scope

### 1) Voll-Local Manifest

Erzeugt ein vollständiges Manifest des lokalen Zustands.

Verwendung:

- komplette Referenz für einen Soll-Stand
- vollständiger Sicherheits-Check
- Vergleich gegen lokal aktuelle Dateien

Empfohlener Standard für eine einmalige vollständige Baseline-Prüfung.

### 2) Entwicklungs-Scope Manifest

Erzeugt ein realistisches, kleineres Manifest mit genau den Bereichen, die wir tatsächlich entwickeln und anpassen.

Ausgeschlossen werden dabei typischerweise Dateien wie:

- externe JS-Bibliotheken
- vendor-/lib-artige Ressourcen
- Build-/Dist-Artefakte
- andere Bereiche, die wir in der produktiven Programmierung nicht manuell anfassen

Verwendung:

- Normalfall für den täglichen Vergleich
- Vergleich des eigentlichen Projekt- und Programmierbereichs
- kleinerer Scope ohne ständige manuelle Pfad-Spezifikation

Das ist der neue Standard-Scope zwischen "voll" und "punktuell".

### 3) Punktuell / Paths

Erzeugt ein Manifest nur für gezielt ausgewählte Dateien oder Ordner.

Verwendung:

- Spezialfall
- konkrete Änderung mit expliziter Spezifikation
- gezielte Prüfung einzelner Bereiche

Diese Variante erfordert immer eine manuelle Auswahl und wird nicht als Standard verwendet.

### 4) Git Manifest

Erzeugt ein Manifest basierend auf dem Git-Stand.

Verwendung:

- LIVE gegen GIT
- lokal gegen GIT
- Vergleich mit definierter Basis oder Commit

Diese Variante ist wichtig, wenn der Soll-Stand durch Git bestimmt werden soll.

### 5) Kein Manifest

Es wird nur ein Paket ohne Vergleichsbasis erzeugt.

Verwendung:

- reiner Transport bzw. Deployment-Container
- Notfallfall, wenn kein Vergleich nötig ist

Nicht als Standard verwenden.

---

## Empfohlener Standard-Workflow

### Standard für einen normalen Hotfix

1. Voll-Local-Manifest erzeugen
2. Paket mit Voll-Local-Manifest erzeugen
3. optional zusätzlich Kompakt-Local-Manifest als Sicherheits-Check

### Nur wenn nötig

- Git-Manifest erzeugen
- Live gegen Git prüfen
- lokal gegen Git prüfen

---

## Chat-Befehle

Für die Nutzung im Chat sind die folgenden Befehle sinnvoll und kurz:

- `Erzeuge Voll-Local-Manifest`
- `Erzeuge Entwicklungs-Scope-Manifest`
- `Erzeuge Punktuell-Manifest`
- `Erzeuge Git-Manifest`
- `Erzeuge Hotfix mit Voll-Local-Manifest`
- `Erzeuge Hotfix mit Entwicklungs-Scope-Manifest`
- `Erzeuge Hotfix mit Punktuell-Manifest`
- `Erzeuge Hotfix mit Git-Manifest`
- `Erzeuge Hotfix ohne Manifest`
- `Hotfix-Paket erstellen`
- `Hotfix-Paket letzte Aenderungen`
- `copy beta`

Diese Befehle sollten im Projekt als Standardworkflow gelten. Sie sind verständlich und vermeiden lange Skript-Aufrufe im Chat.

---

## Praktische Reihenfolge

### Variante A: Standard-Hotfix

1. `Erzeuge Voll-Local-Manifest`
2. `Erzeuge Hotfix mit Voll-Local-Manifest`
3. danach im Alltag: `Erzeuge Entwicklungs-Scope-Manifest` und mit diesem weiterarbeiten

### Variante B: Live gegen Git

1. `Erzeuge Git-Manifest`
2. Live-Compare mit dem erzeugten Manifest durchführen
3. wenn nötig, Paket mit Git-Manifest erzeugen

### Variante C: lokal gegen Manifest prüfen

1. `Erzeuge Voll-Local-Manifest`
2. Manifest lokal hochladen oder mit dem Compare-Endpoint prüfen
3. Ergebnis interpretieren
4. für den normalen Alltag auf `Entwicklungs-Scope` wechseln

### Variante D: nur reiner Manifest-Check

1. `Erzeuge Voll-Local-Manifest`
2. Live-Compare durchführen
3. Danach Standard-Workflow auf `Entwicklungs-Scope` umstellen

---

## Was bei einer Vergleichsentscheidung zu beachten ist

### Wenn das Manifest klein ist

Dann sind alle nicht enthaltenen Dateien als `extra` sichtbar. Das ist normal.

Beispiel:

- Manifest enthält 2 Dateien
- Live-Verzeichnis enthält 2340 deploybare Dateien
- Ergebnis: 2340 `extra`

Das bedeutet nicht, dass der Mechanismus kaputt ist.

### Wenn das Manifest vollständig ist

Dann sollten echte Unterschiede sauber erscheinen:

- match
- different
- missing
- extra nur für tatsächlich nicht im Soll enthaltene Dateien

---

## Empfehlung für den Produktiv-Workflow

Die folgende Standardstrategie ist sinnvoll:

- Standardmanifest: Voll-Local
- Sicherheitscheck: Kompakt-Local
- Git-Vergleich: nur bei Bedarf
- kein Manifest: nur Ausnahmefall

So bleibt der Workflow einfach, schnell und nachvollziehbar.

---

## Verknüpfung zur bestehenden Doku

Die bestehende Delta-Paket-Doku bleibt gültig für die technische Paket-Erzeugung und den Deployment-Mechanismus:

- `DEPLOY_DELTA_PACKAGES.md`

Diese Datei ergänzt sie um den praktischen Manifest- und Vergleichs-Workflow:

- welche Modi es gibt
- welche Variante für welchen Zweck genutzt wird
- wie die Chat-Befehle sinnvoll eingesetzt werden

---

## Zusammenfassung

Der richtige Umgang ist:

- Voll-Local Manifest = vollständige Referenz
- Kompakt-Local Manifest = kleiner, realistischer Sicherheits-Check
- Git Manifest = Vergleich gegen Git-Stand
- Kein Manifest = reine Transport-Variante

Damit bleibt der Hotfix-Prozess überschaubar und die Vergleichsergebnisse sind interpretierbar.
