<?php
/**
 * Migration 039: Workshop Kontrollstrukturen - assignment + tasks.
 */

require_once __DIR__ . '/../../config/database.php';

function getAdminCreatorId(mysqli $conn): int {
    $res = $conn->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return 1;
}

function getOrCreateAssignment(mysqli $conn, string $title, string $description): int {
    $stmt = $conn->prepare('SELECT id FROM assignments WHERE title = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Prepare failed (getOrCreateAssignment/select): ' . $conn->error);
    }
    $stmt->bind_param('s', $title);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        return (int)$existing['id'];
    }

    $creatorId = getAdminCreatorId($conn);
    $difficulty = 'beginner';
    $isActive = 1;
    $codeTemplate = null;

    $insert = $conn->prepare('INSERT INTO assignments (title, description, code_template, created_by, is_active, difficulty, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
    if (!$insert) {
        throw new Exception('Prepare failed (getOrCreateAssignment/insert): ' . $conn->error);
    }
    $insert->bind_param('sssiss', $title, $description, $codeTemplate, $creatorId, $isActive, $difficulty);

    if (!$insert->execute()) {
        throw new Exception('Execute failed (getOrCreateAssignment/insert): ' . $insert->error);
    }

    $id = (int)$conn->insert_id;
    $insert->close();

    return $id;
}

function taskExistsByTitle(mysqli $conn, int $assignmentId, string $title): bool {
    $stmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Prepare failed (taskExistsByTitle): ' . $conn->error);
    }

    $stmt->bind_param('is', $assignmentId, $title);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = (bool)$result->fetch_assoc();
    $stmt->close();

    return $exists;
}

function insertTaskRow(mysqli $conn, array $task): int {
    $sql = 'INSERT INTO tasks (
        assignment_id,
        title,
        task_text,
        description,
        stoff,
        position,
        task_type,
        problem_type,
        code_template,
        solution_code,
        test_cases,
        randomizer_code,
        variable_overrides,
        correct_answer,
        iterations_count,
        hint1,
        hint2,
        hint3,
        max_attempts,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed (insertTaskRow): ' . $conn->error);
    }

    $assignmentId = $task['assignment_id'];
    $title = $task['title'];
    $taskText = $task['task_text'];
    $description = $task['description'];
    $stoff = $task['stoff'];
    $position = $task['position'];
    $taskType = $task['task_type'];
    $problemType = $task['problem_type'];
    $codeTemplate = $task['code_template'];
    $solutionCode = $task['solution_code'];
    $testCases = $task['test_cases'];
    $randomizerCode = $task['randomizer_code'];
    $variableOverrides = $task['variable_overrides'];
    $correctAnswer = $task['correct_answer'];
    $iterationsCount = $task['iterations_count'];
    $hint1 = $task['hint1'];
    $hint2 = $task['hint2'];
    $hint3 = $task['hint3'];
    $maxAttempts = $task['max_attempts'];

    $stmt->bind_param(
        'issssissssssssisssi',
        $assignmentId,
        $title,
        $taskText,
        $description,
        $stoff,
        $position,
        $taskType,
        $problemType,
        $codeTemplate,
        $solutionCode,
        $testCases,
        $randomizerCode,
        $variableOverrides,
        $correctAnswer,
        $iterationsCount,
        $hint1,
        $hint2,
        $hint3,
        $maxAttempts
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed (insertTaskRow): ' . $stmt->error);
    }

    $taskId = (int)$conn->insert_id;
    $stmt->close();

    return $taskId;
}

function buildIntelligentFunctionTask(
    int $assignmentId,
    int $position,
    string $title,
    string $taskText,
    string $description,
    string $stoff,
    string $functionName,
    array $params,
    string $codeTemplate,
    string $solutionCode,
    string $randomizerCode,
    string $hint1,
    string $hint2,
    string $hint3,
    int $tests = 5
): array {
    $testCases = json_encode([
        'mode' => 'function',
        'tests' => $tests,
        'function' => [
            'name' => $functionName,
            'params' => $params
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return [
        'assignment_id' => $assignmentId,
        'title' => $title,
        'task_text' => $taskText,
        'description' => $description,
        'stoff' => $stoff,
        'position' => $position,
        'task_type' => 'code',
        'problem_type' => 'code_completion',
        'code_template' => $codeTemplate,
        'solution_code' => $solutionCode,
        'test_cases' => $testCases,
        'randomizer_code' => $randomizerCode,
        'variable_overrides' => null,
        'correct_answer' => null,
        'iterations_count' => null,
        'hint1' => $hint1,
        'hint2' => $hint2,
        'hint3' => $hint3,
        'max_attempts' => 10
    ];
}

function buildCodeRandomComplexTask(
    int $assignmentId,
    int $position,
    string $title,
    string $taskText,
    string $description,
    string $stoff,
    string $codeTemplate,
    string $solutionCode,
    string $randomizerCode,
    array $inputKeys,
    string $hint1,
    string $hint2,
    string $hint3,
    int $iterations = 5
): array {
    $inputs = [];
    foreach ($inputKeys as $key) {
        $inputs[$key] = '<random>';
    }

    $variableOverrides = json_encode([
        [
            'inputs' => $inputs,
            'expected' => ['variable' => 'antwort']
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return [
        'assignment_id' => $assignmentId,
        'title' => $title,
        'task_text' => $taskText,
        'description' => $description,
        'stoff' => $stoff,
        'position' => $position,
        'task_type' => 'code_random_complex',
        'problem_type' => 'code_completion',
        'code_template' => $codeTemplate,
        'solution_code' => $solutionCode,
        'test_cases' => null,
        'randomizer_code' => $randomizerCode,
        'variable_overrides' => $variableOverrides,
        'correct_answer' => 'antwort',
        'iterations_count' => $iterations,
        'hint1' => $hint1,
        'hint2' => $hint2,
        'hint3' => $hint3,
        'max_attempts' => 10
    ];
}

try {
    $conn = getDbConnection();
    echo "Running Migration 039: workshop assignment + tasks...\n";

    $assignmentTitle = 'Workshop Kontrollstrukturen und erste Programme';
    $assignmentDescription = 'Bedingungen, Schleifen, Listen und Codeverstaendnis. Schwerpunkt auf Code-Aufgaben und Code-Lesen mit randomisierten Ausgangswerten.';

    $assignmentId = getOrCreateAssignment($conn, $assignmentTitle, $assignmentDescription);
    echo "Using assignment #{$assignmentId}: {$assignmentTitle}\n";

    $tasks = [];
    $pos = 1;

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A1 Gerade oder ungerade',
        'Schreibe eine Funktion gerade_oder_ungerade(zahl), die "gerade" oder "ungerade" zurueckgibt.',
        'Gib je nach Teilbarkeit durch 2 den passenden Text zurueck.',
        'Modulo-Operator %, if/else, Rueckgabewert als String.',
        'gerade_oder_ungerade',
        ['zahl'],
        <<<'PY'
def gerade_oder_ungerade(zahl):
    # TODO: gib "gerade" oder "ungerade" zurueck
    return ""
PY,
        <<<'PY'
def gerade_oder_ungerade(zahl):
    if zahl % 2 == 0:
        return "gerade"
    return "ungerade"
PY,
        <<<'PY'
import random
values = {
    "zahl": random.randint(-200, 200)
}
PY,
        'Nutze zahl % 2.',
        'Bei Rest 0 ist die Zahl gerade.',
        'Rueckgabe muss genau "gerade" oder "ungerade" sein.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A2 Groesser als 100',
        'Schreibe eine Funktion groesse_100(zahl), die "gross" oder "klein" zurueckgibt.',
        'Wenn zahl > 100, dann "gross", sonst "klein".',
        'if/else mit Vergleichsoperator >.',
        'groesse_100',
        ['zahl'],
        <<<'PY'
def groesse_100(zahl):
    # TODO
    return ""
PY,
        <<<'PY'
def groesse_100(zahl):
    if zahl > 100:
        return "gross"
    return "klein"
PY,
        <<<'PY'
import random
values = {
    "zahl": random.randint(-50, 250)
}
PY,
        'Vergleiche mit 100.',
        'Nur > 100 ist gross.',
        'Ansonsten klein.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A3 Zahlen zaehlen bis -1',
        'Schreibe eine Funktion zaehle_bis_minus_eins(werte), die die Anzahl bis zum ersten -1 zaehlt.',
        'Die -1 selbst zaehlt nicht. Werte nach der -1 sollen ignoriert werden.',
        'for-Schleife, Abbruch mit break, Zaehler erhoehen.',
        'zaehle_bis_minus_eins',
        ['werte'],
        <<<'PY'
def zaehle_bis_minus_eins(werte):
    count = 0
    # TODO
    return count
PY,
        <<<'PY'
def zaehle_bis_minus_eins(werte):
    count = 0
    for wert in werte:
        if wert == -1:
            break
        count += 1
    return count
PY,
        <<<'PY'
import random
laenge = random.randint(3, 7)
daten = [random.randint(1, 30) for _ in range(laenge)]
stop_idx = random.randint(1, laenge - 1)
werte = daten[:stop_idx] + [-1] + [random.randint(100, 120), random.randint(130, 150)]
values = {"werte": werte}
PY,
        'Gehe Wert fuer Wert durch die Liste.',
        'Bei -1 sofort abbrechen.',
        'Nur vorherige Elemente zaehlen.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A4 Summe bis 0',
        'Schreibe eine Funktion summe_bis_null(werte), die alle Werte bis zur ersten 0 aufsummiert.',
        'Die 0 beendet die Verarbeitung. Werte nach der 0 werden ignoriert.',
        'Akkumulator-Muster: summe + break bei Stopwert.',
        'summe_bis_null',
        ['werte'],
        <<<'PY'
def summe_bis_null(werte):
    summe = 0
    # TODO
    return summe
PY,
        <<<'PY'
def summe_bis_null(werte):
    summe = 0
    for wert in werte:
        if wert == 0:
            break
        summe += wert
    return summe
PY,
        <<<'PY'
import random
laenge = random.randint(4, 8)
daten = [random.randint(-9, 20) for _ in range(laenge)]
stop_idx = random.randint(1, laenge - 1)
werte = daten[:stop_idx] + [0] + [random.randint(40, 60)]
values = {"werte": werte}
PY,
        'Starte mit summe = 0.',
        'Bei wert == 0 abbrechen.',
        'Nur davor addieren.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A5 Minimum bis 0',
        'Schreibe eine Funktion minimum_bis_null(werte), die das kleinste Element bis zur ersten 0 liefert.',
        'Die Liste enthaelt mindestens einen Wert vor der 0.',
        'Startwert ueber erstes Element setzen, danach vergleichen.',
        'minimum_bis_null',
        ['werte'],
        <<<'PY'
def minimum_bis_null(werte):
    # TODO
    return 0
PY,
        <<<'PY'
def minimum_bis_null(werte):
    minimum = None
    for wert in werte:
        if wert == 0:
            break
        if minimum is None or wert < minimum:
            minimum = wert
    return minimum
PY,
        <<<'PY'
import random
laenge = random.randint(4, 8)
daten = [random.randint(-25, 25) for _ in range(laenge)]
stop_idx = random.randint(1, laenge - 1)
werte = daten[:stop_idx] + [0] + [random.randint(70, 80)]
values = {"werte": werte}
PY,
        'Verwende eine Variable minimum.',
        'Bei der ersten Zahl minimum setzen.',
        'Danach nur bei kleineren Werten aktualisieren.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A6 Liste filtern > 20',
        'Schreibe eine Funktion filter_groesser_20(numbers), die nur Werte > 20 als Liste zurueckgibt.',
        'Durchlaufe die Eingabeliste und sammle passende Werte.',
        'for + if + Ergebnisliste.',
        'filter_groesser_20',
        ['numbers'],
        <<<'PY'
def filter_groesser_20(numbers):
    result = []
    # TODO
    return result
PY,
        <<<'PY'
def filter_groesser_20(numbers):
    result = []
    for n in numbers:
        if n > 20:
            result.append(n)
    return result
PY,
        <<<'PY'
import random
numbers = [random.randint(0, 50) for _ in range(random.randint(5, 10))]
values = {"numbers": numbers}
PY,
        'Lege eine leere Ergebnisliste an.',
        'Fuege nur n > 20 hinzu.',
        'Gib die Liste zurueck.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A7 Vielfache von 3',
        'Schreibe eine Funktion vielfache_von_drei(), die alle Vielfachen von 3 zwischen 1 und 30 als Liste zurueckgibt.',
        'Nutze eine Schleife von 1 bis 30 und pruefe Teilbarkeit durch 3.',
        'Range, Modulo und Listenaufbau.',
        'vielfache_von_drei',
        [],
        <<<'PY'
def vielfache_von_drei():
    result = []
    # TODO
    return result
PY,
        <<<'PY'
def vielfache_von_drei():
    result = []
    for i in range(1, 31):
        if i % 3 == 0:
            result.append(i)
    return result
PY,
        <<<'PY'
values = {}
PY,
        'Die Schleife muss von 1 bis 30 laufen.',
        'Nutze i % 3 == 0.',
        'Gib am Ende die Liste zurueck.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A8 Passwortpruefung',
        'Schreibe eine Funktion passwortpruefung(eingaben), die bei korrektem Passwort "Zugriff erlaubt" zurueckgibt.',
        'Die Liste eingaben enthaelt nacheinander eingegebene Passwoerter. Pruefe der Reihe nach bis python123 erreicht ist.',
        'while/for mit Vergleich auf den Zielstring.',
        'passwortpruefung',
        ['eingaben'],
        <<<'PY'
def passwortpruefung(eingaben):
    # TODO
    return ""
PY,
        <<<'PY'
def passwortpruefung(eingaben):
    for passwort in eingaben:
        if passwort == "python123":
            return "Zugriff erlaubt"
    return "Zugriff verweigert"
PY,
        <<<'PY'
import random
falsch = ["abc", "test", "pass", "qwerty", "hallo"]
anzahl = random.randint(1, 4)
pre = random.sample(falsch, anzahl)
eingaben = pre + ["python123"] + ["zzz"]
values = {"eingaben": eingaben}
PY,
        'Das richtige Passwort lautet python123.',
        'Bei Treffer sofort erfolgreich zurueckgeben.',
        'Alles danach ist egal.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A9 Kleines Einmaleins 1-3',
        'Schreibe eine Funktion einmaleins_1_bis_3(), die alle Produkte 1..3 mit 1..5 als Liste zurueckgibt.',
        'Erzeuge die Produkte in geschachtelten Schleifen in der Reihenfolge des Aufgabenblatts.',
        'Geschachtelte for-Schleifen.',
        'einmaleins_1_bis_3',
        [],
        <<<'PY'
def einmaleins_1_bis_3():
    result = []
    # TODO
    return result
PY,
        <<<'PY'
def einmaleins_1_bis_3():
    result = []
    for a in range(1, 4):
        for b in range(1, 6):
            result.append(a * b)
    return result
PY,
        <<<'PY'
values = {}
PY,
        'Aussen 1..3, innen 1..5.',
        'Jeden Produktwert anhaengen.',
        'Reihenfolge beibehalten.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A10 Durchschnitt bis 0',
        'Schreibe eine Funktion durchschnitt_bis_null(werte), die den Durchschnitt bis zur ersten 0 berechnet.',
        'Die 0 beendet die Eingabe und zaehlt nicht mit.',
        'Summe + Zaehler, dann summe/anzahl.',
        'durchschnitt_bis_null',
        ['werte'],
        <<<'PY'
def durchschnitt_bis_null(werte):
    # TODO
    return 0.0
PY,
        <<<'PY'
def durchschnitt_bis_null(werte):
    summe = 0
    anzahl = 0
    for wert in werte:
        if wert == 0:
            break
        summe += wert
        anzahl += 1
    return summe / anzahl
PY,
        <<<'PY'
import random
laenge = random.randint(3, 7)
daten = [random.randint(1, 20) for _ in range(laenge)]
stop_idx = random.randint(1, laenge - 1)
werte = daten[:stop_idx] + [0] + [random.randint(50, 60)]
values = {"werte": werte}
PY,
        'Du brauchst summe und anzahl.',
        'Bei 0 abbrechen.',
        'Am Ende teilen.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A11 Rabattpruefung',
        'Schreibe eine Funktion rabattstufe(preis), die "kein Rabatt", "5% Rabatt" oder "10% Rabatt" zurueckgibt.',
        'Unter 50: kein Rabatt, 50-99: 5% Rabatt, ab 100: 10% Rabatt.',
        'Gestufte Bedingungen mit if/elif/else.',
        'rabattstufe',
        ['preis'],
        <<<'PY'
def rabattstufe(preis):
    # TODO
    return ""
PY,
        <<<'PY'
def rabattstufe(preis):
    if preis < 50:
        return "kein Rabatt"
    if preis < 100:
        return "5% Rabatt"
    return "10% Rabatt"
PY,
        <<<'PY'
import random
values = {
    "preis": random.randint(1, 200)
}
PY,
        'Pruefe zuerst < 50.',
        'Dann den Bereich bis 99.',
        'Ab 100 gilt 10% Rabatt.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A12 Temperaturen ueber 20',
        'Schreibe eine Funktion anzahl_ueber_20(temperaturen), die zaehlt, wie viele Werte > 20 sind.',
        'Zaehle nur Tage mit mehr als 20 Grad.',
        'for + if + Zaehler.',
        'anzahl_ueber_20',
        ['temperaturen'],
        <<<'PY'
def anzahl_ueber_20(temperaturen):
    anzahl = 0
    # TODO
    return anzahl
PY,
        <<<'PY'
def anzahl_ueber_20(temperaturen):
    anzahl = 0
    for t in temperaturen:
        if t > 20:
            anzahl += 1
    return anzahl
PY,
        <<<'PY'
import random
temperaturen = [random.randint(8, 32) for _ in range(7)]
values = {"temperaturen": temperaturen}
PY,
        'Zaehler mit 0 starten.',
        'Nur bei t > 20 erhoehen.',
        'Zaehler zurueckgeben.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'A13 Sitzplan anzeigen',
        'Schreibe eine Funktion sitzplan_plaetze(), die alle Sitzplatz-Bezeichner fuer 4 Reihen und 5 Plaetze als Liste erzeugt.',
        'Format je Eintrag: "Reihe X Platz Y".',
        'Geschachtelte Schleifen fuer Reihe und Platz.',
        'sitzplan_plaetze',
        [],
        <<<'PY'
def sitzplan_plaetze():
    result = []
    # TODO
    return result
PY,
        <<<'PY'
def sitzplan_plaetze():
    result = []
    for reihe in range(1, 5):
        for platz in range(1, 6):
            result.append(f"Reihe {reihe} Platz {platz}")
    return result
PY,
        <<<'PY'
values = {}
PY,
        'Aussen: Reihen, innen: Plaetze.',
        'String genau im geforderten Format.',
        'Alles in einer Liste sammeln.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'B14 Lagerpruefung',
        'Schreibe eine Funktion lager_unter_fuenf(lager), die alle Faecher mit Bestand < 5 als Textliste ausgibt.',
        'Format je Treffer: "Regal r Fach f: n" (r und f ab 1).',
        '2D-Liste mit geschachtelten Schleifen und Bedingung < 5.',
        'lager_unter_fuenf',
        ['lager'],
        <<<'PY'
def lager_unter_fuenf(lager):
    result = []
    # TODO
    return result
PY,
        <<<'PY'
def lager_unter_fuenf(lager):
    result = []
    for r_idx, regal in enumerate(lager, start=1):
        for f_idx, bestand in enumerate(regal, start=1):
            if bestand < 5:
                result.append(f"Regal {r_idx} Fach {f_idx}: {bestand}")
    return result
PY,
        <<<'PY'
import random
zeilen = random.randint(2, 4)
spalten = random.randint(3, 5)
lager = []
for _ in range(zeilen):
    lager.append([random.randint(0, 15) for _ in range(spalten)])
values = {"lager": lager}
PY,
        'Mit enumerate(..., start=1) arbeiten.',
        'Nur Bestand < 5 aufnehmen.',
        'Textformat einhalten.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'B15 Stundenplan pruefen',
        'Schreibe eine Funktion finde_mathe_info(stundenplan), die Treffer fuer Mathe oder Info als Textliste liefert.',
        'Format: "Tag t, Stunde s: Fach" (t und s ab 1).',
        '2D-Liste + Bedingung auf zwei Faecher.',
        'finde_mathe_info',
        ['stundenplan'],
        <<<'PY'
def finde_mathe_info(stundenplan):
    result = []
    # TODO
    return result
PY,
        <<<'PY'
def finde_mathe_info(stundenplan):
    result = []
    for tag_idx, tag in enumerate(stundenplan, start=1):
        for stunde_idx, fach in enumerate(tag, start=1):
            if fach == "Mathe" or fach == "Info":
                result.append(f"Tag {tag_idx}, Stunde {stunde_idx}: {fach}")
    return result
PY,
        <<<'PY'
import random
pool = ["Mathe", "Info", "Deutsch", "Sport", "Biologie", "Chemie", "Kunst", "Musik", "Englisch"]
stundenplan = []
for _ in range(3):
    stundenplan.append([random.choice(pool) for _ in range(4)])
values = {"stundenplan": stundenplan}
PY,
        'Nur Mathe oder Info ausgeben.',
        'Tag- und Stundenindex ab 1.',
        'Textformat genau einhalten.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'B16 Kinosaal auswerten',
        'Schreibe eine Funktion anzahl_freie_plaetze(saal), die die Zahl freier Plaetze zurueckgibt.',
        'Ein Platz ist frei, wenn der Eintrag "frei" ist.',
        '2D-Liste durchlaufen und frei zaehlen.',
        'anzahl_freie_plaetze',
        ['saal'],
        <<<'PY'
def anzahl_freie_plaetze(saal):
    anzahl = 0
    # TODO
    return anzahl
PY,
        <<<'PY'
def anzahl_freie_plaetze(saal):
    anzahl = 0
    for reihe in saal:
        for platz in reihe:
            if platz == "frei":
                anzahl += 1
    return anzahl
PY,
        <<<'PY'
import random
saal = []
for _ in range(3):
    reihe = []
    for _ in range(4):
        reihe.append(random.choice(["frei", "belegt"]))
    saal.append(reihe)
values = {"saal": saal}
PY,
        'Durchlaufe alle Reihen und Plaetze.',
        'Bei "frei" den Zaehler erhoehen.',
        'Anzahl zurueckgeben.'
    );

    $tasks[] = buildCodeRandomComplexTask(
        $assignmentId,
        $pos++,
        'C17 Was wird ausgegeben',
        'Was ist der Endwert von x?',
        'Analysiere den if-Block und gib den finalen Wert von x an.',
        'Code lesen: if-Bedingung und Zuweisung nachverfolgen.',
        <<<'PY'
x = {x_start}

if x > {grenze}:
    x = x + {add}

antwort = x
PY,
        <<<'PY'
x = {x_start}

if x > {grenze}:
    x = x + {add}

antwort = x
PY,
        <<<'PY'
import random
x_start = random.randint(0, 10)
grenze = random.randint(2, 8)
add = random.randint(1, 5)
PY,
        ['x_start', 'grenze', 'add'],
        'Pruefe zuerst die Bedingung.',
        'Nur wenn sie wahr ist, wird addiert.',
        'Antwort ist der Endwert von x.'
    );

    $tasks[] = buildCodeRandomComplexTask(
        $assignmentId,
        $pos++,
        'C18 Variablen verfolgen',
        'Was ist der Endwert von x?',
        'Verfolge die for-Schleife schrittweise.',
        'Code lesen: Schleifendurchlaeufe und wiederholte Addition.',
        <<<'PY'
x = {x0}
y = {y0}

for i in range({n}):
    x = x + y

antwort = x
PY,
        <<<'PY'
x = {x0}
y = {y0}

for i in range({n}):
    x = x + y

antwort = x
PY,
        <<<'PY'
import random
x0 = random.randint(0, 8)
y0 = random.randint(1, 6)
n = random.randint(2, 5)
PY,
        ['x0', 'y0', 'n'],
        'x wird in jeder Runde um y erhoeht.',
        'Anzahl der Erhoehungen ist n.',
        'Endwert von x eintragen.'
    );

    $tasks[] = buildCodeRandomComplexTask(
        $assignmentId,
        $pos++,
        'C19 while verstehen',
        'Was ist der Endwert von x?',
        'Verfolge die while-Schleife bis die Bedingung nicht mehr gilt.',
        'Code lesen: wiederholte Multiplikation.',
        <<<'PY'
x = {x0}

a = {grenze}
while x < a:
    x = x * {faktor}

antwort = x
PY,
        <<<'PY'
x = {x0}

a = {grenze}
while x < a:
    x = x * {faktor}

antwort = x
PY,
        <<<'PY'
import random
x0 = random.randint(1, 4)
grenze = random.randint(10, 40)
faktor = random.randint(2, 3)
PY,
        ['x0', 'grenze', 'faktor'],
        'Die Schleife laeuft nur solange x < Grenze.',
        'In jeder Runde wird mit dem Faktor multipliziert.',
        'Gib den finalen x-Wert an.'
    );

    $tasks[] = buildCodeRandomComplexTask(
        $assignmentId,
        $pos++,
        'C20 if-elif-else',
        'Welcher Buchstabe wird ausgegeben?',
        'Bestimme den Ergebnisbuchstaben fuer den gegebenen x-Wert.',
        'if / elif / else Kette lesen.',
        <<<'PY'
x = {x0}

if x < {g1}:
    out = "A"
elif x < {g2}:
    out = "B"
else:
    out = "C"

antwort = out
PY,
        <<<'PY'
x = {x0}

if x < {g1}:
    out = "A"
elif x < {g2}:
    out = "B"
else:
    out = "C"

antwort = out
PY,
        <<<'PY'
import random
g1 = random.randint(4, 8)
g2 = g1 + random.randint(5, 10)
x0 = random.randint(0, g2 + 4)
PY,
        ['x0', 'g1', 'g2'],
        'Nur ein Zweig wird ausgefuehrt.',
        'elif prueft nur, wenn if falsch war.',
        'Antwort ist A, B oder C.'
    );

    $tasks[] = buildIntelligentFunctionTask(
        $assignmentId,
        $pos++,
        'C21 Fehler erkennen und beheben',
        'Schreibe eine Funktion ist_groesser_als_10(x_text), die True oder False zurueckgibt.',
        'x_text ist ein String wie bei input(). Wandle ihn in int um und pruefe > 10.',
        'input liefert String. Fuer Zahlenvergleich zuerst int(...) nutzen.',
        'ist_groesser_als_10',
        ['x_text'],
        <<<'PY'
def ist_groesser_als_10(x_text):
    # TODO
    return False
PY,
        <<<'PY'
def ist_groesser_als_10(x_text):
    x = int(x_text)
    return x > 10
PY,
        <<<'PY'
import random
values = {
    "x_text": str(random.randint(-25, 35))
}
PY,
        'x_text ist ein String.',
        'Nutze int(x_text).',
        'Vergleiche danach mit 10.'
    );

    $tasks[] = buildCodeRandomComplexTask(
        $assignmentId,
        $pos++,
        'C22 Werte am Ende',
        'Gib die Endwerte im Format x,y an.',
        'Verfolge x und y ueber alle Schleifendurchlaeufe.',
        'Zwei Variablen in der Schleife nachverfolgen.',
        <<<'PY'
x = {x0}
y = {y0}

for i in range({n}):
    y = y + x
    x = x + {step}

antwort = str(x) + "," + str(y)
PY,
        <<<'PY'
x = {x0}
y = {y0}

for i in range({n}):
    y = y + x
    x = x + {step}

antwort = str(x) + "," + str(y)
PY,
        <<<'PY'
import random
x0 = random.randint(1, 4)
y0 = random.randint(0, 3)
n = random.randint(3, 5)
step = random.randint(1, 3)
PY,
        ['x0', 'y0', 'n', 'step'],
        'x und y veraendern sich in jeder Runde.',
        'Am Ende beide Werte im Format x,y.',
        'Kein Leerzeichen im Ergebnis.'
    );

    $tasks[] = buildCodeRandomComplexTask(
        $assignmentId,
        $pos++,
        'C23 Schleife mit Bedingung',
        'Was ist der Endwert von count?',
        'Zaehle, wie oft die if-Bedingung in der Schleife zutrifft.',
        'for-Schleife + Teilbarkeitspruefung.',
        <<<'PY'
count = 0

for i in range(1, {n}):
    if i % {teiler} == 0:
        count = count + 1

antwort = count
PY,
        <<<'PY'
count = 0

for i in range(1, {n}):
    if i % {teiler} == 0:
        count = count + 1

antwort = count
PY,
        <<<'PY'
import random
n = random.randint(6, 18)
teiler = random.choice([2, 3, 4])
PY,
        ['n', 'teiler'],
        'Pruefe Zahlen von 1 bis n-1.',
        'count steigt nur bei teilbaren Werten.',
        'Antwort ist eine ganze Zahl.'
    );

    foreach ($tasks as $task) {
        if (taskExistsByTitle($conn, $assignmentId, $task['title'])) {
            echo "⚠ Skipped (already exists): {$task['title']}\n";
            continue;
        }

        $taskId = insertTaskRow($conn, $task);
        echo "✓ Created task #{$taskId}: {$task['title']}\n";
    }

    echo "\n✅ Migration 039: Success!\n";
    $conn->close();

} catch (Exception $e) {
    echo "❌ Migration 039 failed: " . $e->getMessage() . "\n";
    exit(1);
}
