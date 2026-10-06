# Architekturvorschlag: Gemeinsame Task-Editor-Basis für Assignment-Tasks

## Ziel

Die bestehende manuelle db_model-Variante soll nicht als isolierte Speziallösung weiterentwickelt werden. Stattdessen soll eine gemeinsame Task-Editor-Basis geschaffen werden, auf der verschiedene Task-Typen aufgebaut werden können, zunächst:

- db_model
- später UML

Die erste Umsetzung soll im Assignment-Kontext erfolgen, weil dort Nutzung, Bewertung und Persistenz direkt sichtbar sind. Die Architektur soll aber von Anfang an so angelegt sein, dass sie später auch im Projektmodus wiederverwendet werden kann.

---

## Grundprinzip

Nicht „für jeden Task-Typ eine eigene Ansicht bauen“, sondern:

- eine gemeinsame Shell für die Task-Ausgabe
- einen task-spezifischen Renderer pro Task-Typ
- dieselbe Persistenz- und Bewertungslogik für alle Task-Typen

Damit entsteht eine klare Trennung zwischen:

- allgemeiner Task-UI
- spezieller Editor-Logik
- task-spezifischer Datenstruktur

---

## Warum diese Reihenfolge sinnvoll ist

Die Umsetzung zuerst im Assignment-Kontext hat mehrere Vorteile:

1. Der Lern- und Bewertungs-Workflow ist dort direkt sichtbar.
2. Die Persistenz über user_tasks ist dort bereits relevant.
3. Die Admin-/Test-Ansicht kann direkt mitgetestet werden.
4. Die UX kann früh validiert werden.
5. Der Projektmodus kann später als „andere Umgebung derselben Engine“ folgen.

Wichtig ist nur: Die Architektur darf nicht Assignment-spezifisch werden. Sie soll eine generische Task-Editor-Basis sein, die im Assignment zuerst verwendet wird.

---

## Architekturmodell

### 1. Gemeinsame Task-Editor-Shell

Diese Shell ist für alle Task-Typen gleich und enthält:

- Task-Header
  - Titel
  - Status
  - Versuche
  - ggf. Bewertungsstatus
- Task-Prompt / Aufgabenbeschreibung
- Output-Bereich
  - eigentlicher Editor bzw. interaktiver Bereich
- Sidebar / Task-Details
  - Hinweise
  - Struktur-/Details-Ansicht
  - optionales Bild
- Toolbar
  - Speichern
  - Abgeben
  - Check / Validate
  - Undo / Redo
- globale Statuslogik
  - unbearbeitet
  - in Bearbeitung
  - submitted
  - passed
  - failed

Diese Shell ist unabhängig vom konkreten Task-Typ.

### 2. Task-spezifische Renderer

Für jeden Task-Typ gibt es einen Renderer, der die gemeinsame Shell füllt.

Beispiele:

- db_model Renderer
  - Tabellenstruktur
  - Spalten
  - Beziehungen
  - Datenzeilen
  - ggf. Tabellenbaum
- UML Renderer
  - Klassen
  - Attribute
  - Methoden
  - Beziehungen
  - Layout-Editor

Jeder Renderer bekommt:

- task definition
- current state
- persistence adapter
- optional solution/reference model
- optional validation logic

Damit ist der eigentliche Editor-Teil modular und austauschbar.

### 3. Gemeinsame Persistenz-Schicht

Die Task-Daten sollen nicht pro Task-Typ getrennt gespeichert werden. Stattdessen sollte ein einheitliches Task-State-Format verwendet werden.

Beispiel:

```json
{
  "taskType": "db_model",
  "version": 1,
  "model": {},
  "meta": {
    "lastSavedAt": "...",
    "dirty": false
  }
}
```

Für UML wäre dann analog:

```json
{
  "taskType": "uml",
  "version": 1,
  "model": {},
  "meta": {
    "lastSavedAt": "...",
    "dirty": false
  }
}
```

Wichtig ist, dass die Persistenz nicht direkt an die UI-Komponenten gekoppelt ist, sondern über eine gemeinsame Schnittstelle läuft.

---

## Technische Aufteilung

### A. Shell-Layer

Diese Schicht kümmert sich um alles, was bei allen Tasks gleich ist:

- Layout
- Task-Header
- Prompt-Bereich
- Sidebar
- Toolbar
- Status- und Submit-Logik
- Save/Load-Mechanik

### B. Renderer-Layer

Diese Schicht ist task-spezifisch:

- db_model renderer
- uml renderer

Jeder Renderer implementiert dieselben Grundfunktionen:

- init
- render
- update
- serialize
- deserialize
- validate

### C. Adapter-Layer

Dieser Layer verbindet Renderer mit dem Rest-System:

- Assignment-Integration
- Projektmodus-Integration
- Admin-/Test-Ansicht
- Persistenz in user_tasks
- Musterlösungs-/Check-Integration

---

## Datenmodell-Ansatz

Ein gemeinsames Modell sollte möglichst generisch sein, aber trotzdem genug Struktur haben, um Task-Typen zu unterscheiden.

### Grundstruktur

```json
{
  "taskType": "db_model",
  "version": 1,
  "model": {},
  "solution": null,
  "template": null,
  "image": null,
  "validation": {
    "enabled": false,
    "mode": "manual"
  }
}
```

### Für db_model

```json
{
  "taskType": "db_model",
  "model": {
    "tables": [],
    "relations": []
  }
}
```

### Für UML

```json
{
  "taskType": "uml",
  "model": {
    "classes": [],
    "relationships": []
  }
}
```

---

## Musterlösung und Template

Diese beiden Funktionen sollten ebenfalls in derselben Architektur vorgesehen werden.

### Musterlösung

Die Musterlösung ist nicht nur ein „Lösungs-Text“, sondern ein referenzierbares Modell.

Beispiele:

- db_model: Referenz-Tabellenmodell
- UML: Referenz-Klassendiagramm

### Template / Ausgangsstand

Ein Template ist der Startzustand des Editors.

Beispiele:

- db_model: initiale leere Tabellenstruktur oder vordefinierte Basis
- UML: initiale leere Klassenfläche oder vorgegebene Starter-Klassen

Beide sollten als Teil der Task-Definition mitgeliefert werden.

---

## Bildanzeige

Die Aufgabe soll später auch Bilder im Task-Output oder in der Sidebar anzeigen können.

Die gemeinsame Shell sollte dafür eine eigene Fläche bereitstellen, z. B.:

- image panel
- optional preview area
- optional sidebar image block

Das Bild ist dabei nicht Task-spezifisch, sondern Teil der gemeinsamen Task-UI.

---

## Checks gegen Musterlösung

Auch das sollte grundsätzlich als gemeinsame Funktion aufgebaut werden.

### Vorschlag

Die Validation soll nicht direkt im Renderer „eingeschrieben“ werden, sondern über einen gemeinsamen Validator-Mechanismus laufen.

Beispiele:

- db_model validator:
  - prüft Tabellen
  - prüft Spalten
  - prüft Primär-/Fremdschlüssel
  - prüft Beziehungen
- UML validator:
  - prüft Klassen
  - prüft Attribute/Methoden
  - prüft Beziehungsstruktur

Die gemeinsame Shell stellt nur die Oberfläche bereit:

- Check-Button
- Ergebnisliste
- Feedback-Status

---

## Integration in Assignment

Die Integration in das Assignment soll so laufen, dass die Task-Engine einen gemeinsamen Status verwendet.

### Workflow

1. Task wird geladen
2. Shell wird aufgebaut
3. passender Renderer wird initialisiert
4. Persistenz wird geladen
5. Task kann bearbeitet werden
6. Speichern / Abgeben / Check laufen über die gemeinsame Shell
7. Status wird in user_tasks gespeichert

Damit bleiben Assignment, Admin-Test-Ansicht und spätere Projekt-Ansicht konsistent.

---

## Späterer Projektmodus

Wenn später der Projektmodus dieselbe Engine nutzt, dann ist der Ablauf identisch:

- gleiche Task-Shell
- anderer Renderer
- andere Umgebung/Integration

Das ist der eigentliche Gewinn dieser Architektur.

---

## Empfehlung für die Umsetzung

### Phase 1

- gemeinsame Shell einführen
- db_model als erster Renderer
- Persistenz und Assignment-Integration sauber abbilden

### Phase 2

- Musterlösung / Template / Bildanzeige ergänzen
- Validation-Mechanik einbauen

### Phase 3

- UML als zweiter Renderer auf derselben Basis

---

## Konkreter Aktionsplan: UML-Designer und neuer Task-Mode "uml"

### Zielbild

Ein neuer Task-Typ "uml" soll im Assignment-Editor als eigener Task-Mode verfügbar sein. Die Bedienung soll grafisch erfolgen und sich auf ein eingeschränktes, didaktisch sinnvolles Spektrum beschränken.

### Erste Version: Anforderungen an die grafische Zeichenoberfläche

Die erste Version soll eine echte grafische UML-Zeichenoberfläche sein. Die Elemente werden direkt auf einer Zeichenfläche angelegt und visualisiert, nicht über ein Formular oder eine Textansicht, die anschließend das Diagramm erzeugt.

Die erste Version soll folgende Elemente unterstützen:

- Klassen
  - direkt auf der Zeichenfläche anlegen
  - löschen, umbenennen und verschieben
  - Klassenname wird als sichtbarer Diagrammteil dargestellt
  - Attribute direkt in der Klassenbox anzeigen
  - Datentypen für Attribute definieren
- Assoziationen
  - direkt zwischen zwei Klassen auf der Zeichenfläche zeichnen
  - einfache Richtung und ggf. Beschriftung unterstützen
- Enumerationen
  - direkt als eigene Diagrammelemente anlegen
  - Werte direkt im Element anzeigen
- Darstellung
  - echte UML-Canvas mit Drag-and-Drop
  - Auswahl eines Elements durch Klick
  - Löschen über Kontextmenü oder Toolbar
  - einfache visuelle Trennung zwischen Klassen, Assoziationen und Enumerationen
- Persistenz
  - Diagrammstate soll gespeichert und wieder geladen werden können

Wichtige Gestaltungsgrundlage:

- Die Oberfläche soll sich an Konzepten wie Rational Rose oder Altova UModel orientieren
- Der Fokus liegt auf einer direkten, grafischen Modellierungsinteraktion
- Die Bedienung soll nicht an ein Formular- oder Texteditor-Muster erinnern

### Optionaler Erweiterungsumfang für spätere Schritte

Die folgenden Funktionen sind nicht zwingend für die erste Version, aber sinnvoll als nächster Schritt:

- Vererbung
- Methoden
- komplexere Beziehungsarten
- automatische Layout-Hilfe
- Vorschau der Musterlösung

### MVP-Fokus

Für die erste Realisierung soll der Schwerpunkt bewusst klein gehalten werden:

- Klassen
- Attribute
- Datentypen
- Assoziationen
- Enumerationen

Damit bleibt die Oberfläche lernbar, schnell nutzbar und für erste Aufgaben geeignet.

### Architekturziel für spätere Wiederverwendbarkeit

Die UML-GUI soll von Anfang an als modulare, wiederverwendbare Diagramm-Engine konzipiert werden. Das bedeutet:

- dieselbe Zeichenoberfläche soll später sowohl im Assignment- als auch im Projektmodus eingesetzt werden können
- die grafische Oberfläche soll vom fachlichen Kontext getrennt sein
- die Integration in die jeweilige Umgebung soll über Adapter erfolgen
- die eigentliche Diagramm-Logik soll unabhängig von Assignment- oder Projekt-UI entwickelt werden

Damit wird die Oberfläche nicht nur als einzelne Task-Ansicht gebaut, sondern als allgemeine Modellierungskomponente für zukünftige Nutzung.

### Phasenplan

#### Phase 0 – Anforderungen und Datenmodell festlegen

- Task-Typ "uml" im Task-Definition-Format einführen
- Gemeinsames State-Format erweitern um:
  - taskType = "uml"
  - version
  - model
  - solution
  - template
  - validation
- MVP-Umfang definieren:
  - Klassen
  - Attribute
  - Methoden
  - einfache Beziehungen
  - grundlegendes Layout
- Beispiel-Task und Beispiel-Lösung vorbereiten

#### Phase 1 – Gemeinsame Task-Engine vorbereiten

- Eine gemeinsame Shell für Header, Prompt, Sidebar, Toolbar und Statuslogik einführen
- Ein Renderer-Interface festlegen mit den Basisfunktionen:
  - init
  - render
  - update
  - serialize
  - deserialize
  - validate
- Die bestehende Assignment-Integration auf die neue Struktur umstellen, sodass der Renderer nicht direkt an die Seite gekoppelt ist
- Persistenz über dieselbe Schnittstelle wie für andere Task-Typen sicherstellen

#### Phase 2 – UML-Renderer MVP bauen

- Einen ersten UML-Renderer als task-spezifischen Renderer implementieren
- Grafische Elemente für folgende Bausteine bereitstellen:
  - Klasse
  - Attribute
  - Methode
  - Beziehungstypen wie Assoziation, Vererbung, Aggregation
- Basisfunktionen umsetzen:
  - Hinzufügen / Bearbeiten / Löschen
  - Verschieben und Layout-Adjustierung
  - Auswahl und Fokus auf ein Element
  - Undo / Redo
- Einfache Starter-Vorlage für neue Aufgaben bereitstellen

#### Phase 3 – Validation und Musterlösung einbauen

- Einen gemeinsamen Validator-Mechanismus für UML ergänzen
- Prüfschritte definieren:
  - Klassen vorhanden
  - Attribute und Methoden korrekt modelliert
  - Beziehungen sinnvoll aufgebaut
  - Pflichtklassen oder Pflichtmethoden erfüllt
- Musterlösung als referenzierbares UML-Modell abbilden
- Rückmeldung im gleichen Feedback-Mechanismus wie bei anderen Task-Typen anzeigen

#### Phase 4 – Assignment- und Admin-Integration abschließen

- Der neue Task-Mode im Assignment-Editor sichtbar machen
- In der Admin-/Test-Ansicht dieselbe Darstellung und denselben Status verwenden
- Speichern, Abgeben und Check über den gemeinsamen Workflow testen
- Beispielaufgaben und Testfälle für UML ergänzen

### Einfache Umsetzungsreihenfolge

1. Schritt 1 – Grundgerüst schaffen
   - gemeinsame Task-Shell vorbereiten
   - einen ersten task-agnostischen Editor-Container bauen
   - den bestehenden Assignment-Workflow an diese Shell anbinden

2. Schritt 2 – UML-MVP definieren
   - festlegen, welche UML-Elemente im ersten Schritt wirklich nötig sind
   - Klassen, Attribute, Methoden und einfache Beziehungen als MVP festhalten
   - die erste Beispielaufgabe definieren

3. Schritt 3 – UML-Editor bauen
   - Klassen hinzufügen, bearbeiten und löschen
   - Beziehungen zeichnen
   - grundlegendes Layout und Auswahl ermöglichen

4. Schritt 4 – Speichern und Laden verbinden
   - den UML-Status in die gleiche Persistenzstruktur wie andere Tasks einhängen
   - das Modell wieder laden und anzeigen

5. Schritt 5 – Prüfung und Bewertung ergänzen
   - erste Validierungsregeln definieren
   - Musterlösung vergleichen
   - Feedback im bestehenden Check-Workflow zeigen

6. Schritt 6 – Polieren und erweitern
   - UX verbessern
   - weitere Beziehungstypen oder Komfortfunktionen ergänzen
   - später auf andere Task-Typen übertragen

### Technische Arbeitsbausteine

- Frontend-Shell:
  - gemeinsame Task-Editor-Oberfläche in [public/assignment_editor.php](public/assignment_editor.php)
  - zentrale Logik in [public/js/assignments.js](public/js/assignments.js)
- Renderer- und UI-Logik:
  - separater UML-Renderer, später als eigener Modulbaustein
  - Ausgangspunkt kann die bestehende Aufgaben-Renderer-Logik in [public/js/quiz-renderer.js](public/js/quiz-renderer.js) sein
- Persistenz und API:
  - gemeinsame State-Lade- und Save-Logik in [api/user_tasks/get.php](api/user_tasks/get.php) und [api/user_tasks/submit_quiz.php](api/user_tasks/submit_quiz.php)
- Erweiterung der Task-Definition:
  - neue Task-Definitionen für den Task-Typ "uml"
  - Template- und Musterlösungsstruktur vorbereiten

### Abnahmekriterien

Die Umsetzung gilt als erfolgreich, wenn:

- ein neuer UML-Task-Mode im Assignment-Editor verwendbar ist
- Klassen, Attribute, Methoden und Beziehungen grafisch bearbeitet werden können
- Daten persistiert und wieder geladen werden können
- Check- und Bewertungsworkflow mit derselben Shell wie andere Task-Typen laufen
- der Aufbau so modular ist, dass zukünftige Task-Typen ohne großen Umbau ergänzt werden können

### Reihenfolge der Umsetzung

1. Gemeinsame Shell und Renderer-Contract vorbereiten
2. UML-Renderer MVP bauen
3. Persistenz und Assignment-Integration anpassen
4. Validation und Musterlösung ergänzen
5. UX-Polish und Rollout

### Bewertbare Umsetzungscheckliste

Die folgende Liste kann direkt als Review-Checkliste verwendet werden.

#### A. Scope und Zielsetzung

- [ ] Der neue Task-Mode "uml" ist klar definiert und von anderen Task-Typen abgegrenzt
- [ ] Das MVP ist begrenzt auf die wichtigsten UML-Bausteine
- [ ] Die Zielgruppe und der Lernzweck der Aufgabe sind beschrieben

#### B. Funktionale Anforderungen

- [ ] Es können Klassen angelegt, bearbeitet und gelöscht werden
- [ ] Attribute und Methoden können gepflegt werden
- [ ] Mindestens ein einfacher Beziehungstyp ist umsetzbar
- [ ] Das Diagramm kann verschoben und layoutmäßig angepasst werden
- [ ] Der Editor kann gespeichert und erneut geladen werden
- [ ] Der Task kann abgegeben und geprüft werden

#### C. Datenmodell und Persistenz

- [ ] Das gemeinsame State-Format ist für UML eindeutig definiert
- [ ] Die Struktur für Klassen, Attribute, Methoden und Beziehungen ist festgelegt
- [ ] Die Persistenz ist unabhängig vom Renderer und über dieselbe Schnittstelle erreichbar
- [ ] Template und Musterlösung sind als eigene Datenbestandteile abgebildet

#### D. UI/UX

- [ ] Die gemeinsame Shell ist für UML sinnvoll nutzbar
- [ ] Die Bedienung ist verständlich und nicht zu komplex für den ersten Schritt
- [ ] Fehlermeldungen und Feedback sind klar sichtbar
- [ ] Die Oberfläche lässt sich auch in kleinen Bildschirmbreiten sinnvoll bedienen

#### E. Bewertung und Validation

- [ ] Es gibt eine klare Validierungslogik für UML
- [ ] Pflichtbestandteile können geprüft werden
- [ ] Der Check-Feedback-Mechanismus ist konsistent mit anderen Task-Typen
- [ ] Die Musterlösung kann als Vergleichsmodell dienen

#### F. Integration

- [ ] Der neue Mode funktioniert im Assignment-Editor
- [ ] Die Integration in Admin-/Test-Ansicht ist geplant
- [ ] Der Workflow ist identisch zu bestehenden Task-Editoren
- [ ] Die Architektur bleibt generisch und nicht nur auf UML begrenzt

### Wo Details angegeben werden sollten

Für eine belastbare Bewertung sollten folgende Punkte konkret ausgearbeitet werden:

1. Welche UML-Elemente sind im MVP zwingend erforderlich?
   - Klassen
   - Attribute
   - Methoden
   - Beziehungen
   - Sichtbarkeiten
   - Abstrakte Klassen oder Interfaces

2. Welche Beziehungstypen sollen zunächst unterstützt werden?
   - Vererbung
   - Assoziation
   - Aggregation
   - Komposition
   - andere

3. Wie soll die Layout-Logik aussehen?
   - freie Positionierung
   - automatisch ausgerichtet
   - feste Grid-Strategie

4. Welche Prüfungen sind sinnvoll?
   - nur Struktur vorhanden
   - semantische Prüfung
   - exakte Übereinstimmung zur Musterlösung

5. Wie soll die Persistenz aussehen?
   - JSON-Format
   - Speicherung einzelner Komponenten
   - Versionierung für spätere Erweiterungen

6. Welche Aufgabenformate sollen unterstützt werden?
   - reine Modellierung
   - Modellierung mit vorgegebenen Klassen
   - Modellierung mit Pflichtbeziehungen

### Offene Entscheidungsfragen

- Soll der erste UML-Mode eher ein einfacher Editor oder ein stärker visueller Designer sein?
- Soll die erste Version nur eine 2D-Canvas-Ansicht oder bereits eine etwas erweiterte Interaktionsfläche bieten?
- Soll die Validierung eher permissiv sein oder streng an die Musterlösung gekoppelt werden?
- Soll der UML-Mode sofort im Assignment-Editor verfügbar sein oder zuerst nur als interne Prototype-Variante?

---

## Fazit

Die Architektur sollte nicht „Assignment-spezifisch“ sein, sondern „gemeinsame Task-Engine mit task-spezifischen Renderer-Adaptern“.

Das bedeutet:

- Assignment zuerst entwickeln
- aber mit einer Architektur, die später auch im Projektmodus verwendet werden kann
- db_model und UML als zwei Renderer derselben Basis

Das ist der sauberste und nachhaltigste Weg.

---

## Relevante bestehende Stellen im Codebase

Die aktuelle db_model-Umsetzung ist in folgenden Bereichen sichtbar:

- [public/js/quiz-renderer.js](public/js/quiz-renderer.js)
- [public/js/assignments.js](public/js/assignments.js)
- [public/assignment_editor.php](public/assignment_editor.php)
- [api/user_tasks/submit_quiz.php](api/user_tasks/submit_quiz.php)
- [api/user_tasks/get.php](api/user_tasks/get.php)

Diese Stellen sollten als Ausgangspunkt für die gemeinsame Architektur dienen.

---

## Wichtige Leitlinie für die Umsetzung

Die gemeinsame Architektur soll von Anfang an so gedacht werden, dass:

- die Shell immer gleich bleibt,
- nur der Renderer wechselt,
- die Persistenz immer dieselbe bleibt,
- und die spätere UML-Variante nur ein weiterer Adapter auf derselben Basis ist.
