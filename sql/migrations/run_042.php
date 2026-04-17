<?php
/**
 * Migration 042: Lernstand Kontrollstrukturen & erste Programme (EVA)
 *
 * - 11x code (intelligent tests)
 * - 4x code_random_complex (code reading, source visible)
 */

require_once __DIR__ . '/../../config/database.php';

function getAdminCreatorId042(mysqli $conn): int {
    $res = $conn->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return 1;
}

function getOrCreateAssignment042(mysqli $conn, string $title, string $description): int {
    $stmt = $conn->prepare('SELECT id FROM assignments WHERE title = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Prepare failed (assignment select): ' . $conn->error);
    }
    $stmt->bind_param('s', $title);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        return (int)$existing['id'];
    }

    $creatorId = getAdminCreatorId042($conn);
    $difficulty = 'beginner';
    $isActive = 1;
    $codeTemplate = null;

    $ins = $conn->prepare('INSERT INTO assignments (title, description, code_template, created_by, is_active, difficulty, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
    if (!$ins) {
        throw new Exception('Prepare failed (assignment insert): ' . $conn->error);
    }
    $ins->bind_param('sssiss', $title, $description, $codeTemplate, $creatorId, $isActive, $difficulty);

    if (!$ins->execute()) {
        throw new Exception('Execute failed (assignment insert): ' . $ins->error);
    }

    $id = (int)$conn->insert_id;
    $ins->close();
    return $id;
}

function taskExistsByTitle042(mysqli $conn, int $assignmentId, string $title): bool {
    $stmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Prepare failed (task exists): ' . $conn->error);
    }
    $stmt->bind_param('is', $assignmentId, $title);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

function insertTaskRow042(mysqli $conn, array $task): int {
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
        show_solution,
        show_solution_code,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed (insert task): ' . $conn->error);
    }

    $assignmentId = (string)$task['assignment_id'];
    $title = $task['title'];
    $taskText = $task['task_text'];
    $description = $task['description'];
    $stoff = $task['stoff'];
    $position = (string)$task['position'];
    $taskType = $task['task_type'];
    $problemType = $task['problem_type'];
    $codeTemplate = $task['code_template'];
    $solutionCode = $task['solution_code'];
    $testCases = $task['test_cases'];
    $randomizerCode = $task['randomizer_code'];
    $variableOverrides = $task['variable_overrides'];
    $correctAnswer = $task['correct_answer'];
    $iterationsCount = isset($task['iterations_count']) ? (string)$task['iterations_count'] : null;
    $hint1 = $task['hint1'];
    $hint2 = $task['hint2'];
    $hint3 = $task['hint3'];
    $maxAttempts = (string)$task['max_attempts'];
    $showSolution = (string)$task['show_solution'];
    $showSolutionCode = (string)$task['show_solution_code'];

    $types = str_repeat('s', 21);
    $stmt->bind_param(
        $types,
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
        $maxAttempts,
        $showSolution,
        $showSolutionCode
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed (insert task): ' . $stmt->error);
    }

    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

function buildIntelligentFunctionTask042(
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
        'test_cases' => json_encode([
            'mode' => 'function',
            'tests' => $tests,
            'function' => [
                'name' => $functionName,
                'params' => $params
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'randomizer_code' => $randomizerCode,
        'variable_overrides' => null,
        'correct_answer' => null,
        'iterations_count' => null,
        'hint1' => $hint1,
        'hint2' => $hint2,
        'hint3' => $hint3,
        'max_attempts' => 10,
        'show_solution' => 0,
        'show_solution_code' => 0
    ];
}

function buildIntelligentVarsTask042(
    int $assignmentId,
    int $position,
    string $title,
    string $taskText,
    string $description,
    string $stoff,
    array $inputs,
    array $outputs,
    string $codeTemplate,
    string $solutionCode,
    string $randomizerCode,
    string $hint1,
    string $hint2,
    string $hint3,
    int $tests = 5
): array {
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
        'test_cases' => json_encode([
            'mode' => 'vars',
            'tests' => $tests,
            'inputs' => $inputs,
            'outputs' => $outputs
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'randomizer_code' => $randomizerCode,
        'variable_overrides' => null,
        'correct_answer' => null,
        'iterations_count' => null,
        'hint1' => $hint1,
        'hint2' => $hint2,
        'hint3' => $hint3,
        'max_attempts' => 10,
        'show_solution' => 0,
        'show_solution_code' => 0
    ];
}

function buildCodeRandomComplexTask042(
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
    foreach ($inputKeys as $k) {
        $inputs[$k] = '<random>';
    }

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
        'variable_overrides' => json_encode([
            [
                'inputs' => $inputs,
                'expected' => ['variable' => 'antwort']
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'correct_answer' => 'antwort',
        'iterations_count' => $iterations,
        'hint1' => $hint1,
        'hint2' => $hint2,
        'hint3' => $hint3,
        'max_attempts' => 10,
        'show_solution' => 0,
        'show_solution_code' => 1
    ];
}

try {
    $conn = getDbConnection();
    echo "Running Migration 042: Lernstand EVA assignment...\n";

    $assignmentTitle = 'Lernstand Kontrollstrukturen und erste Programme EVA';
    $assignmentDescription = 'Lernstand mit Fokus auf EVA, Eingaben, Bedingungen, Schleifen, erste Algorithmen und Code-Reading.';

    $assignmentId = getOrCreateAssignment042($conn, $assignmentTitle, $assignmentDescription);
    echo "Using assignment #{$assignmentId}: {$assignmentTitle}\n";

    $tasks = [];
    $pos = 1;

    $tasks[] = buildIntelligentVarsTask042(
        $assignmentId,
        $pos++,
        'E1 Volljaehrig mit Eingabe',
        'Lies ein Alter ein und setze status auf "volljaehrig" oder "minderjaehrig".',
        'EVA: Alter einlesen, vergleichen und Ergebnis in der Variable status speichern.',
        'input, int-Umwandlung, if/else.',
        ['alter'],
        ['status'],
        <<<'PY'
#INIT START
alter = int(input("Alter: "))
#INIT END

status = ""
# TODO
PY,
        <<<'PY'
#INIT START
alter = int(input("Alter: "))
#INIT END

if alter >= 18:
    status = "volljaehrig"
else:
    status = "minderjaehrig"
PY,
        <<<'PY'
import random
v = random.randint(12, 30)
values = {
    "INPUT_01": v,
    "alter": v
}
PY,
        'Vergleiche alter mit 18.',
        'Setze status je nach Fall.',
        'Exakte Texte nutzen.'
    );

    $tasks[] = buildIntelligentVarsTask042(
        $assignmentId,
        $pos++,
        'E2 Zahl klassifizieren',
        'Lies eine Zahl ein und setze klasse auf "negativ", "null" oder "positiv".',
        'Nutze if/elif/else fuer drei Faelle.',
        'Vergleichsoperatoren und Fallunterscheidung.',
        ['x'],
        ['klasse'],
        <<<'PY'
#INIT START
x = int(input("Zahl: "))
#INIT END

klasse = ""
# TODO
PY,
        <<<'PY'
#INIT START
x = int(input("Zahl: "))
#INIT END

if x < 0:
    klasse = "negativ"
elif x == 0:
    klasse = "null"
else:
    klasse = "positiv"
PY,
        <<<'PY'
import random
v = random.randint(-15, 15)
values = {
    "INPUT_01": v,
    "x": v
}
PY,
        'Denke an den Sonderfall 0.',
        'Nutze elif fuer den zweiten Fall.',
        'klasse muss einen der drei Texte enthalten.'
    );

    $tasks[] = buildIntelligentVarsTask042(
        $assignmentId,
        $pos++,
        'E3 Notenbewertung',
        'Lies eine Note ein und setze urteil auf "bestanden" oder "nicht bestanden".',
        'Bestanden gilt bei note <= 4.0.',
        'float-Eingabe, Vergleich, Ausgabevariable.',
        ['note'],
        ['urteil'],
        <<<'PY'
#INIT START
note = float(input("Note: "))
#INIT END

urteil = ""
# TODO
PY,
        <<<'PY'
#INIT START
note = float(input("Note: "))
#INIT END

if note <= 4.0:
    urteil = "bestanden"
else:
    urteil = "nicht bestanden"
PY,
        <<<'PY'
import random
v = random.choice([1.3, 2.0, 3.7, 4.0, 4.7, 5.0])
values = {
    "INPUT_01": v,
    "note": v
}
PY,
        'Grenze ist 4.0.',
        'Bis einschliesslich 4.0 = bestanden.',
        'urteil als Text setzen.'
    );

    $tasks[] = buildIntelligentVarsTask042(
        $assignmentId,
        $pos++,
        'E4 Rabatt und Endpreis',
        'Lies preis ein und berechne rabatt_prozent sowie endpreis.',
        'Praxis Shop: <50 kein Rabatt, <100 5%, sonst 10%.',
        'Gestufte Bedingungen, Berechnung mit Prozenten.',
        ['preis'],
        ['rabatt_prozent', 'endpreis'],
        <<<'PY'
#INIT START
preis = float(input("Preis: "))
#INIT END

rabatt_prozent = 0
endpreis = 0.0
# TODO
PY,
        <<<'PY'
#INIT START
preis = float(input("Preis: "))
#INIT END

if preis < 50:
    rabatt_prozent = 0
elif preis < 100:
    rabatt_prozent = 5
else:
    rabatt_prozent = 10

endpreis = preis * (1 - rabatt_prozent / 100)
PY,
        <<<'PY'
import random
v = random.randint(20, 180)
values = {
    "INPUT_01": v,
    "preis": v
}
PY,
        'Zuerst Rabattstufe bestimmen.',
        'Dann endpreis berechnen.',
        'Beide Variablen setzen.'
    );

    $tasks[] = buildIntelligentVarsTask042(
        $assignmentId,
        $pos++,
        'E5 Durchschnitt aus drei Eingaben',
        'Lies drei Messwerte ein und setze durchschnitt.',
        'EVA: drei Eingaben, eine Verarbeitung, eine Ausgabevariable.',
        'Mehrere Inputs und arithmetischer Mittelwert.',
        ['m1', 'm2', 'm3'],
        ['durchschnitt'],
        <<<'PY'
#INIT START
m1 = float(input("Messwert 1: "))
m2 = float(input("Messwert 2: "))
m3 = float(input("Messwert 3: "))
#INIT END

durchschnitt = 0.0
# TODO
PY,
        <<<'PY'
#INIT START
m1 = float(input("Messwert 1: "))
m2 = float(input("Messwert 2: "))
m3 = float(input("Messwert 3: "))
#INIT END

durchschnitt = (m1 + m2 + m3) / 3
PY,
        <<<'PY'
import random
a = random.randint(10, 30)
b = random.randint(10, 30)
c = random.randint(10, 30)
values = {
    "INPUT_01": a,
    "INPUT_02": b,
    "INPUT_03": c,
    "m1": a,
    "m2": b,
    "m3": c
}
PY,
        'Addiere alle drei Werte.',
        'Teile durch 3.',
        'Ergebnis in durchschnitt speichern.'
    );

    $tasks[] = buildIntelligentVarsTask042(
        $assignmentId,
        $pos++,
        'E6 Passwortpruefung mit Eingaben',
        'Lies drei Passwortversuche ein und setze zugriff auf "erlaubt" oder "verweigert".',
        'Wenn einer der drei Versuche python123 ist, dann erlaubt.',
        'Mehrere Eingaben, logische Verknuepfung, Textausgabe.',
        ['pw1', 'pw2', 'pw3'],
        ['zugriff'],
        <<<'PY'
#INIT START
pw1 = input("Passwort 1: ")
pw2 = input("Passwort 2: ")
pw3 = input("Passwort 3: ")
#INIT END

zugriff = ""
# TODO
PY,
        <<<'PY'
#INIT START
pw1 = input("Passwort 1: ")
pw2 = input("Passwort 2: ")
pw3 = input("Passwort 3: ")
#INIT END

if pw1 == "python123" or pw2 == "python123" or pw3 == "python123":
    zugriff = "erlaubt"
else:
    zugriff = "verweigert"
PY,
        <<<'PY'
import random
vals = ["abc", "pass", "test", "python123"]
a = random.choice(vals)
b = random.choice(vals)
c = random.choice(vals)
values = {
    "INPUT_01": a,
    "INPUT_02": b,
    "INPUT_03": c,
    "pw1": a,
    "pw2": b,
    "pw3": c
}
PY,
        'Richtiges Passwort ist python123.',
        'Ein Treffer reicht fuer erlaubt.',
        'Sonst verweigert.'
    );

    $tasks[] = buildIntelligentFunctionTask042(
        $assignmentId,
        $pos++,
        'F1 Gerade Zahlen filtern',
        'Schreibe filter_gerade(werte), das nur gerade Zahlen zurueckgibt.',
        'Verarbeite eine Liste und gib eine neue Liste zurueck.',
        'for ueber Liste, Bedingung mit % 2, append.',
        'filter_gerade',
        ['werte'],
        <<<'PY'
def filter_gerade(werte):
    result = []
    # TODO
    return result
PY,
        <<<'PY'
def filter_gerade(werte):
    result = []
    for n in werte:
        if n % 2 == 0:
            result.append(n)
    return result
PY,
        <<<'PY'
import random
values = {
    "werte": [random.randint(0, 30) for _ in range(random.randint(5, 9))]
}
PY,
        'Nutze n % 2 == 0.',
        'Nur passende Werte anhaengen.',
        'Neue Liste zurueckgeben.'
    );

    $tasks[] = buildIntelligentFunctionTask042(
        $assignmentId,
        $pos++,
        'F2 Vielfache von 3 bis n',
        'Schreibe vielfache_bis_n(n), das alle Vielfachen von 3 bis n als Liste liefert.',
        'Nutze eine Schleife mit range und eine Teilbarkeitsbedingung.',
        'for, range, modulo, Liste.',
        'vielfache_bis_n',
        ['n'],
        <<<'PY'
def vielfache_bis_n(n):
    result = []
    # TODO
    return result
PY,
        <<<'PY'
def vielfache_bis_n(n):
    result = []
    for i in range(1, n + 1):
        if i % 3 == 0:
            result.append(i)
    return result
PY,
        <<<'PY'
import random
values = {
    "n": random.randint(10, 40)
}
PY,
        'Bis einschliesslich n laufen.',
        'Nur i % 3 == 0 uebernehmen.',
        'Liste zurueckgeben.'
    );

    $tasks[] = buildIntelligentFunctionTask042(
        $assignmentId,
        $pos++,
        'F3 Lagerwarnung unter Mindestbestand',
        'Schreibe anzahl_kritisch(bestand, mindest), das kritische Werte zaehlt.',
        'Praxis Lager: Werte unter mindest gelten als kritisch.',
        'Liste durchlaufen, Bedingung, Zaehler.',
        'anzahl_kritisch',
        ['bestand', 'mindest'],
        <<<'PY'
def anzahl_kritisch(bestand, mindest):
    count = 0
    # TODO
    return count
PY,
        <<<'PY'
def anzahl_kritisch(bestand, mindest):
    count = 0
    for wert in bestand:
        if wert < mindest:
            count += 1
    return count
PY,
        <<<'PY'
import random
values = {
    "bestand": [random.randint(0, 20) for _ in range(random.randint(5, 10))],
    "mindest": random.randint(4, 9)
}
PY,
        'Vergleiche jeden Bestand mit mindest.',
        'Bei Unterschreitung count erhoehen.',
        'Anzahl zurueckgeben.'
    );

    $tasks[] = buildIntelligentFunctionTask042(
        $assignmentId,
        $pos++,
        'F4 Busauslastung berechnen',
        'Schreibe auslastung_prozent(fahrgaeste, kapazitaet), das den Prozentwert liefert.',
        'Praxis Mobilitaet: Anteil der belegten Plaetze als Prozent.',
        'Division, Multiplikation mit 100, Rueckgabewert.',
        'auslastung_prozent',
        ['fahrgaeste', 'kapazitaet'],
        <<<'PY'
def auslastung_prozent(fahrgaeste, kapazitaet):
    # TODO
    return 0.0
PY,
        <<<'PY'
def auslastung_prozent(fahrgaeste, kapazitaet):
    return (fahrgaeste / kapazitaet) * 100
PY,
        <<<'PY'
import random
k = random.randint(20, 60)
f = random.randint(0, k)
values = {
    "fahrgaeste": f,
    "kapazitaet": k
}
PY,
        'Formel: fahrgaeste / kapazitaet * 100.',
        'Achte auf float-Ergebnis.',
        'Direkt zurueckgeben oder in Variable speichern.'
    );

    $tasks[] = buildIntelligentFunctionTask042(
        $assignmentId,
        $pos++,
        'F5 Sitzplan mit verschachtelter Schleife',
        'Schreibe sitzplan_labels(reihen, plaetze), das alle Labels "Reihe X Platz Y" zurueckgibt.',
        'Verwende bewusst geschachtelte Schleifen.',
        'Nested loops: aeussere Schleife Reihe, innere Schleife Platz.',
        'sitzplan_labels',
        ['reihen', 'plaetze'],
        <<<'PY'
def sitzplan_labels(reihen, plaetze):
    result = []
    # TODO
    return result
PY,
        <<<'PY'
def sitzplan_labels(reihen, plaetze):
    result = []
    for r in range(1, reihen + 1):
        for p in range(1, plaetze + 1):
            result.append(f"Reihe {r} Platz {p}")
    return result
PY,
        <<<'PY'
import random
values = {
    "reihen": random.randint(2, 5),
    "plaetze": random.randint(3, 6)
}
PY,
        'Nutze zwei for-Schleifen.',
        'Aussen Reihe, innen Platz.',
        'Label im geforderten Format aufbauen.'
    );

    $tasks[] = buildCodeRandomComplexTask042(
        $assignmentId,
        $pos++,
        'C1 Code lesen if-elif-else',
        'Was ist der Endwert von antwort?',
        'Lies den Code und bestimme den finalen Wert.',
        'Entscheidungslogik in if/elif/else nachvollziehen.',
        <<<'PY'
x = {x}

g1 = {g1}
g2 = {g2}
if x < g1:
    out = "A"
elif x < g2:
    out = "B"
else:
    out = "C"

antwort = out
PY,
        <<<'PY'
x = {x}

g1 = {g1}
g2 = {g2}
if x < g1:
    out = "A"
elif x < g2:
    out = "B"
else:
    out = "C"

antwort = out
PY,
        <<<'PY'
import random
g1 = random.randint(4, 8)
g2 = g1 + random.randint(4, 9)
x = random.randint(0, g2 + 4)
PY,
        ['x', 'g1', 'g2'],
        'Nur ein Zweig wird ausgefuehrt.',
        'elif prueft nur nach falschem if.',
        'Antwort ist A, B oder C.'
    );

    $tasks[] = buildCodeRandomComplexTask042(
        $assignmentId,
        $pos++,
        'C2 Code lesen for und Bedingung',
        'Was ist der Endwert von antwort?',
        'Zaehle, wie oft die Bedingung in der Schleife wahr ist.',
        'for mit range und modulo-Bedingung.',
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
n = random.randint(8, 20)
teiler = random.choice([2, 3, 4])
PY,
        ['n', 'teiler'],
        'range(1, n) geht bis n-1.',
        'Nur teilbare i erhoehen count.',
        'Antwort ist eine ganze Zahl.'
    );

    $tasks[] = buildCodeRandomComplexTask042(
        $assignmentId,
        $pos++,
        'C3 Code lesen while Schleife',
        'Was ist der Endwert von antwort?',
        'Verfolge x bis die while-Bedingung nicht mehr gilt.',
        'while mit Zustandsaenderung.',
        <<<'PY'
x = {x0}
while x < {grenze}:
    x = x * {faktor}

antwort = x
PY,
        <<<'PY'
x = {x0}
while x < {grenze}:
    x = x * {faktor}

antwort = x
PY,
        <<<'PY'
import random
x0 = random.randint(1, 4)
grenze = random.randint(12, 45)
faktor = random.choice([2, 3])
PY,
        ['x0', 'grenze', 'faktor'],
        'while laeuft solange x < grenze.',
        'In jeder Runde wird x multipliziert.',
        'Endwert von x ist die Antwort.'
    );

    $tasks[] = buildCodeRandomComplexTask042(
        $assignmentId,
        $pos++,
        'C4 Code lesen verschachtelte Schleife',
        'Was ist der Endwert von antwort?',
        'Praxis Lager: gezaehlt wird, wie viele Faecher unter Mindestwert liegen.',
        '2D-Struktur und verschachtelte Schleifen lesen.',
        <<<'PY'
lager = [
    [{a11}, {a12}, {a13}],
    [{a21}, {a22}, {a23}],
    [{a31}, {a32}, {a33}]
]

mindest = {mindest}
count = 0
for regal in lager:
    for bestand in regal:
        if bestand < mindest:
            count = count + 1

antwort = count
PY,
        <<<'PY'
lager = [
    [{a11}, {a12}, {a13}],
    [{a21}, {a22}, {a23}],
    [{a31}, {a32}, {a33}]
]

mindest = {mindest}
count = 0
for regal in lager:
    for bestand in regal:
        if bestand < mindest:
            count = count + 1

antwort = count
PY,
        <<<'PY'
import random
a11 = random.randint(0, 15)
a12 = random.randint(0, 15)
a13 = random.randint(0, 15)
a21 = random.randint(0, 15)
a22 = random.randint(0, 15)
a23 = random.randint(0, 15)
a31 = random.randint(0, 15)
a32 = random.randint(0, 15)
a33 = random.randint(0, 15)
mindest = random.randint(4, 10)
PY,
        ['a11','a12','a13','a21','a22','a23','a31','a32','a33','mindest'],
        'Aussen ueber Regale, innen ueber Faecher.',
        'count steigt nur bei bestand < mindest.',
        'antwort ist die finale Anzahl.'
    );

    foreach ($tasks as $task) {
        if (taskExistsByTitle042($conn, $assignmentId, $task['title'])) {
            echo "⚠ Skipped (already exists): {$task['title']}\n";
            continue;
        }

        $id = insertTaskRow042($conn, $task);
        echo "✓ Created task #{$id}: {$task['title']}\n";
    }

    echo "\n✅ Migration 042: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 042 failed: " . $e->getMessage() . "\n";
    exit(1);
}
