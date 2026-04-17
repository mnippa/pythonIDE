<?php
/**
 * Migration 043: Create assignment "C: Bedingungen und Schleifen" and task #1.
 */

require_once __DIR__ . '/../../config/database.php';

function getAdminCreatorId043(mysqli $conn): int {
    $res = $conn->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return 1;
}

function getOrCreateAssignment043(mysqli $conn, string $title, string $description): int {
    $sel = $conn->prepare('SELECT id FROM assignments WHERE title = ? LIMIT 1');
    if (!$sel) {
        throw new Exception('Prepare failed (assignment select): ' . $conn->error);
    }
    $sel->bind_param('s', $title);
    $sel->execute();
    $existing = $sel->get_result()->fetch_assoc();
    $sel->close();

    if ($existing) {
        return (int)$existing['id'];
    }

    $creatorId = getAdminCreatorId043($conn);
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

    $assignmentId = (int)$conn->insert_id;
    $ins->close();

    return $assignmentId;
}

function taskExistsByTitle043(mysqli $conn, int $assignmentId, string $title): bool {
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

function getNextPosition043(mysqli $conn, int $assignmentId): int {
    $stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) AS max_pos FROM tasks WHERE assignment_id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed (next position): ' . $conn->error);
    }
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ((int)($row['max_pos'] ?? 0)) + 1;
}

function insertCodeRandomComplexTask043(mysqli $conn, array $task): int {
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed (insert code_random_complex): ' . $conn->error);
    }

    $assignmentId = (string)$task['assignment_id'];
    $title = $task['title'];
    $taskText = $task['task_text'];
    $description = $task['description'];
    $stoff = $task['stoff'];
    $position = (string)$task['position'];
    $taskType = 'code_random_complex';
    $problemType = 'code_completion';
    $codeTemplate = $task['code_template'];
    $solutionCode = $task['solution_code'];
    $randomizerCode = $task['randomizer_code'];
    $variableOverrides = $task['variable_overrides'];
    $correctAnswer = 'antwort';
    $iterationsCount = (string)$task['iterations_count'];
    $hint1 = $task['hint1'];
    $hint2 = $task['hint2'];
    $hint3 = $task['hint3'];
    $maxAttempts = (string)$task['max_attempts'];
    $showSolution = '0';
    $showSolutionCode = '1';

    $types = str_repeat('s', 20);
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
        throw new Exception('Execute failed (insert code_random_complex): ' . $stmt->error);
    }

    $taskId = (int)$conn->insert_id;
    $stmt->close();
    return $taskId;
}

try {
    $conn = getDbConnection();
    echo "Running Migration 043: assignment C + task #1...\n";

    $assignmentTitle = 'C: Bedingungen und Schleifen';
    $assignmentDescription = 'Schrittweise Aufgaben zu EVA-Prinzip, Bedingungen und Schleifen mit intelligenten Tests.';

    $assignmentId = getOrCreateAssignment043($conn, $assignmentTitle, $assignmentDescription);
    echo "Using assignment #{$assignmentId}: {$assignmentTitle}\n";

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
        hint1,
        hint2,
        hint3,
        max_attempts,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

    $taskTitle = '#1 EVA: Wie heisst Du?';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Frage nach einem Namen und antworte mit Hallo <eingegebener Name>.';
        $description = 'Setze das EVA-Prinzip um: Eingabe (Name einlesen), Verarbeitung (Begruessung zusammensetzen), Ausgabe (Antwort ausgeben). Speichere den finalen Text in der Variablen antwort.';
        $stoff = 'EVA-Prinzip, input(), String-Verkettung, Ausgabe mit print().';
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
#INIT START
name = input("Wie heisst du? ")
#INIT END

antwort = ""
# TODO: Erzeuge die Begruessung und gib sie aus
print(antwort)
PY;

        $solutionCode = <<<'PY'
#INIT START
name = input("Wie heisst du? ")
#INIT END

antwort = "Hallo " + name
print(antwort)
PY;

        $testCases = json_encode([
            'mode' => 'vars',
            'tests' => 5,
            'inputs' => ['name'],
            'outputs' => ['antwort']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = <<<'PY'
import random
namen = ["Anna", "Ben", "Cem", "Dina", "Emil", "Farah"]
name = random.choice(namen)
values = {
    "INPUT_01": name,
    "name": name
}
PY;

        $hint1 = 'Lies den Namen mit input() ein.';
        $hint2 = 'Verknuepfe "Hallo " und den Namen zu einem String.';
        $hint3 = 'Speichere das Ergebnis in antwort und gib es mit print() aus.';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#2 Wie alt bist Du?';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Lies ein Alter ein und entscheide mit Bedingungen ueber die passende Ausgabe.';
        $description = 'Setze das EVA-Prinzip um. Lies das Alter ein, pruefe zuerst auf ungueltige negative Werte, danach auf Volljaehrigkeit. Speichere den finalen Text in der Variablen antwort.';
        $stoff = 'if/elif/else, Vergleichsoperatoren, Reihenfolge von Bedingungen, input() und int().';
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
#INIT START
alter = int(input("Wie alt bist du? "))
#INIT END

antwort = ""

# TODO: Erzeuge die richtige Antwort mit if/elif/else
print(antwort)
PY;

        $solutionCode = <<<'PY'
#INIT START
alter = int(input("Wie alt bist du? "))
#INIT END

if alter < 0:
    antwort = "Das kann nicht sein"
elif alter >= 18:
    antwort = "Du bist volljaehrig"
else:
    antwort = "Du bist minderjaehrig"

print(antwort)
PY;

        $testCases = json_encode([
            'mode' => 'vars',
            'tests' => 6,
            'inputs' => ['alter'],
            'outputs' => ['antwort']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = <<<'PY'
import random
alter = random.choice([-3, -1, 0, 7, 12, 17, 18, 19, 25, 67])
values = {
    "INPUT_01": alter,
    "alter": alter
}
PY;

        $hint1 = 'Wandle die Eingabe mit int(input(...)) in eine Zahl um.';
        $hint2 = 'Pruefe zuerst: alter < 0, dann alter >= 18, sonst minderjaehrig.';
        $hint3 = 'Speichere den Text in antwort und gib ihn am Ende aus.';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }
    $taskTitle = '#3 Rate das Passwort';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Nutze eine Passwortliste mit 5 Eintraegen. Bei gegebenem Zufallsindex soll der User bis zu 3 Versuche ohne Schleife haben.';
        $description = 'Verwende if/elif/else ohne Schleife. Wenn ein Versuch stimmt, dann "Du hast gewonnen", sonst nach dem dritten Versuch "Du hast verloren". Speichere das Ergebnis in antwort.';
        $stoff = 'Listen, Indexzugriff, Zufallsindex, if/elif/else, mehrere Eingaben ohne Schleife.';
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
#INIT START
passwoerter = ["apfel", "banane", "sonne", "katze", "hallo"]
zufalls_index = int(input("Zufallszahl 0-4: "))
ziel_passwort = passwoerter[zufalls_index]
#INIT END

versuch1 = input("1. Versuch: ")
versuch2 = input("2. Versuch: ")
versuch3 = input("3. Versuch: ")

antwort = ""

# TODO: Pruefe ohne Schleife mit if/elif/else
print(antwort)
PY;

        $solutionCode = <<<'PY'
#INIT START
passwoerter = ["apfel", "banane", "sonne", "katze", "hallo"]
zufalls_index = int(input("Zufallszahl 0-4: "))
ziel_passwort = passwoerter[zufalls_index]
#INIT END

versuch1 = input("1. Versuch: ")
versuch2 = input("2. Versuch: ")
versuch3 = input("3. Versuch: ")

if versuch1 == ziel_passwort:
    antwort = "Du hast gewonnen"
elif versuch2 == ziel_passwort:
    antwort = "Du hast gewonnen"
elif versuch3 == ziel_passwort:
    antwort = "Du hast gewonnen"
else:
    antwort = "Du hast verloren"

print(antwort)
PY;

        $testCases = json_encode([
            'mode' => 'vars',
            'tests' => 8,
            'inputs' => ['zufalls_index', 'versuch1', 'versuch2', 'versuch3'],
            'outputs' => ['antwort']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = <<<'PY'
import random
passwoerter = ["apfel", "banane", "sonne", "katze", "hallo"]
idx = random.randint(0, 4)
ziel = passwoerter[idx]

modus = random.choice(["win1", "win2", "win3", "lose"])
falsch = [p for p in passwoerter if p != ziel]

if modus == "win1":
    v1 = ziel
    v2 = random.choice(falsch)
    v3 = random.choice(falsch)
elif modus == "win2":
    v1 = random.choice(falsch)
    v2 = ziel
    v3 = random.choice(falsch)
elif modus == "win3":
    v1 = random.choice(falsch)
    v2 = random.choice(falsch)
    v3 = ziel
else:
    v1 = random.choice(falsch)
    v2 = random.choice(falsch)
    v3 = random.choice(falsch)

values = {
    "INPUT_01": idx,
    "INPUT_02": v1,
    "INPUT_03": v2,
    "INPUT_04": v3,
    "zufalls_index": idx,
    "versuch1": v1,
    "versuch2": v2,
    "versuch3": v3
}
PY;

        $hint1 = 'Lege die Liste mit 5 Passwoertern an und hole das Ziel ueber passwoerter[zufalls_index].';
        $hint2 = 'Verwende keine Schleife, sondern if/elif/else fuer die 3 Versuche.';
        $hint3 = 'Bei Treffer: "Du hast gewonnen", sonst nach Versuch 3: "Du hast verloren".';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#4 Einfache Schleife';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Gib alle Zahlen von 25 bis 50 in einer Zeile aus, getrennt durch genau ein Leerzeichen.';
        $description = 'Nutze eine einfache for-Schleife. Ausgabeformat: 25 26 27 ... 50';
        $stoff = 'for-Schleife mit range(), Ausgabeformat mit Leerzeichen.';
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
# TODO: Gib 25 bis 50 mit genau einem Leerzeichen getrennt aus
PY;

        $solutionCode = <<<'PY'
zahlen = []
for i in range(25, 51):
    zahlen.append(str(i))

print(" ".join(zahlen))
PY;

        $testCases = json_encode([
            [
                'type' => 'code_check',
                'keywords' => [
                    'for\\s+\\w+\\s+in\\s+range\\s*\\(',
                ],
                'operator' => 'AND',
                'feedback' => 'Verwende eine for-Schleife mit range(...).',
            ],
            [
                'type' => 'output',
                'expected_type' => 'exact',
                'value' => '25 26 27 28 29 30 31 32 33 34 35 36 37 38 39 40 41 42 43 44 45 46 47 48 49 50',
                'feedback' => 'Die Ausgabe muss genau die Zahlen 25 bis 50 mit je einem Leerzeichen enthalten.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = null;
        $hint1 = 'Nutze range(25, 51), damit 50 enthalten ist.';
        $hint2 = 'Eine for-Schleife ist Pflicht.';
        $hint3 = 'Zwischen zwei Zahlen soll genau ein Leerzeichen stehen.';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#5 Schleife II';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Zeige alle Zahlen von 1 bis 100 an, die durch 3 teilbar sind.';
        $description = 'Nutze eine Schleife und eine Bedingung mit dem Modulo-Operator. Gib die passenden Zahlen in einer Zeile, mit einfachem Leerzeichen getrennt, aus.';
        $stoff = 'for-Schleife, if-Bedingung, Modulo %, formatierte Ausgabe.';
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
# TODO: Gib alle Zahlen von 1 bis 100 aus, die durch 3 teilbar sind
PY;

        $solutionCode = <<<'PY'
treffer = []
for i in range(1, 101):
    if i % 3 == 0:
        treffer.append(str(i))

print(" ".join(treffer))
PY;

        $testCases = json_encode([
            [
                'type' => 'code_check',
                'keywords' => [
                    'for\\s+\\w+\\s+in\\s+range\\s*\\(',
                    '%\\s*3\\s*==\\s*0',
                ],
                'operator' => 'AND',
                'feedback' => 'Nutze eine for-Schleife und pruefe die Teilbarkeit mit i % 3 == 0.',
            ],
            [
                'type' => 'output',
                'expected_type' => 'exact',
                'value' => '3 6 9 12 15 18 21 24 27 30 33 36 39 42 45 48 51 54 57 60 63 66 69 72 75 78 81 84 87 90 93 96 99',
                'feedback' => 'Ausgabe muss alle durch 3 teilbaren Zahlen von 1 bis 100 enthalten.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = null;
        $hint1 = 'Gehe mit for i in range(1, 101) durch alle Zahlen.';
        $hint2 = 'Teilbar durch 3 bedeutet: i % 3 == 0.';
        $hint3 = 'Formatiere die Ausgabe in einer Zeile mit Leerzeichen.';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#6 Code Reading while flexibel';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $inputKeys = ['start', 'schritt', 'grenze'];
        $inputs = [];
        foreach ($inputKeys as $k) {
            $inputs[$k] = '<random>';
        }

        $task = [
            'assignment_id' => $assignmentId,
            'title' => $taskTitle,
            'task_text' => 'Ziel: Programmablauf nachvollziehen. Was ist der Endwert von antwort?',
            'description' => 'Lies die while-Schleife mit flexibler Abbruchbedingung und einer kleinen Rechenoperation nach. Der Quellcode wird angezeigt.',
            'stoff' => 'while-Schleife, variable Abbruchbedingung, Zwischenschritte mit Rechenoperation.',
            'position' => $position,
            'code_template' => <<<'PY'
wert = {start}
schritt = {schritt}
grenze = {grenze}
summe = 0

while wert <= grenze:
    summe = summe + (wert * 2 - 1)
    wert = wert + schritt

antwort = summe
PY,
            'solution_code' => <<<'PY'
wert = {start}
schritt = {schritt}
grenze = {grenze}
summe = 0

while wert <= grenze:
    summe = summe + (wert * 2 - 1)
    wert = wert + schritt

antwort = summe
PY,
            'randomizer_code' => <<<'PY'
import random
start = random.randint(2, 8)
schritt = random.randint(2, 5)
grenze = start + random.randint(8, 20)
PY,
            'variable_overrides' => json_encode([
                [
                    'inputs' => $inputs,
                    'expected' => ['variable' => 'antwort']
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'iterations_count' => 6,
            'hint1' => 'Die while-Schleife laeuft nur, solange wert <= grenze gilt.',
            'hint2' => 'Pro Runde wird erst summe geaendert und danach wert um schritt erhoeht.',
            'hint3' => 'Gesucht ist der Endwert der Variable antwort.',
            'max_attempts' => 10,
        ];

        $taskId = insertCodeRandomComplexTask043($conn, $task);
        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#7 Code Reading geschachtelte Schleife';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $inputKeys = ['zeilen', 'spalten', 'offset'];
        $inputs = [];
        foreach ($inputKeys as $k) {
            $inputs[$k] = '<random>';
        }

        $task = [
            'assignment_id' => $assignmentId,
            'title' => $taskTitle,
            'task_text' => 'Ziel: geschachtelte Schleife nachvollziehen. Was ist der Endwert von antwort?',
            'description' => 'Verfolge die aeussere und innere Schleife. Innerhalb der Schleifen werden kleine Rechnungen gemacht. Der Quellcode wird angezeigt.',
            'stoff' => 'Verschachtelte for-Schleifen, einfache Rechenoperationen, Bedingung in der inneren Schleife.',
            'position' => $position,
            'code_template' => <<<'PY'
gesamt = 0
for r in range(1, {zeilen} + 1):
    for c in range(1, {spalten} + 1):
        feld = r * c + {offset}
        if feld % 2 == 0:
            gesamt = gesamt + feld
        else:
            gesamt = gesamt + 1

antwort = gesamt
PY,
            'solution_code' => <<<'PY'
gesamt = 0
for r in range(1, {zeilen} + 1):
    for c in range(1, {spalten} + 1):
        feld = r * c + {offset}
        if feld % 2 == 0:
            gesamt = gesamt + feld
        else:
            gesamt = gesamt + 1

antwort = gesamt
PY,
            'randomizer_code' => <<<'PY'
import random
zeilen = random.randint(2, 4)
spalten = random.randint(2, 5)
offset = random.randint(0, 3)
PY,
            'variable_overrides' => json_encode([
                [
                    'inputs' => $inputs,
                    'expected' => ['variable' => 'antwort']
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'iterations_count' => 6,
            'hint1' => 'Aussen laeuft r, innen laeuft c komplett durch.',
            'hint2' => 'Berechne pro Feld zuerst: feld = r * c + offset.',
            'hint3' => 'Gerade feld-Werte addieren feld, ungerade addieren 1.',
            'max_attempts' => 10,
        ];

        $taskId = insertCodeRandomComplexTask043($conn, $task);
        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#8 Finde die Namen mit r';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Gegeben ist Liste1 mit 40 Namen. Erzeuge Liste2 mit allen Namen, die ein "r" enthalten. Gib die Anzahl in Liste2 und das vierte Element aus.';
        $description = 'Filtere die Namen aus Liste1 in eine neue Liste2. Speichere die Anzahl in anzahl und das vierte Element in viertes_element.';
        $stoff = "String-Pruefung in Python:\n"
            . "- Mit 'r' in name.lower() pruefst du, ob ein r vorkommt (gross/klein egal).\n"
            . "- Alternative: name.lower().find('r') != -1.\n"
            . "- Fuer diese Aufgabe eignet sich besonders der in-Operator in einer Schleife.";
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
liste1 = [
    "Anna", "Ben", "Clara", "David", "Emir", "Fatma", "Greta", "Hassan", "Ines", "Jonas",
    "Karim", "Lena", "Mara", "Nora", "Omar", "Paula", "Quirin", "Rami", "Sara", "Tarek",
    "Uwe", "Vera", "Willi", "Xenia", "Yara", "Zoe", "Arda", "Boris", "Celine", "Daria",
    "Erik", "Frida", "Goran", "Hugo", "Irma", "Juri", "Kira", "Lars", "Mert", "Nuri"
]

liste2 = []

# TODO: Erzeuge liste2 mit Namen, die ein "r" enthalten

anzahl = 0
viertes_element = ""

# TODO: Speichere Anzahl und 4. Element aus liste2

print(anzahl)
print(viertes_element)
PY;

        $solutionCode = <<<'PY'
liste1 = [
    "Anna", "Ben", "Clara", "David", "Emir", "Fatma", "Greta", "Hassan", "Ines", "Jonas",
    "Karim", "Lena", "Mara", "Nora", "Omar", "Paula", "Quirin", "Rami", "Sara", "Tarek",
    "Uwe", "Vera", "Willi", "Xenia", "Yara", "Zoe", "Arda", "Boris", "Celine", "Daria",
    "Erik", "Frida", "Goran", "Hugo", "Irma", "Juri", "Kira", "Lars", "Mert", "Nuri"
]

liste2 = []
for name in liste1:
    if "r" in name.lower():
        liste2.append(name)

anzahl = len(liste2)
viertes_element = liste2[3]

print(anzahl)
print(viertes_element)
PY;

        $testCases = json_encode([
            'mode' => 'vars',
            'tests' => 4,
            'inputs' => [],
            'outputs' => ['liste2', 'anzahl', 'viertes_element']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = <<<'PY'
values = {}
PY;

        $hint1 = 'Gehe mit einer for-Schleife ueber alle Namen in liste1.';
        $hint2 = 'Pruefe mit "r" in name.lower(), ob der Name ein r enthaelt.';
        $hint3 = 'anzahl = len(liste2), viertes_element = liste2[3].';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#9 Arbeiten mit Zufallszahlen';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Erzeuge dynamisch mit einer for-Schleife eine Liste mit 30 Zufallszahlen. Schreibe danach zwei Funktionen fuer Maximum und Durchschnitt und gib alles aus.';
        $description = 'Nutze die vorgegebene Zufallszahl-Erzeugung als Ausgangspunkt. Bestimme Maximum und Durchschnitt selbst (nicht mit max()/sum()). Speichere die Ergebnisse in max_wert und durchschnitt.';
        $stoff = "Zufallszahlen und Listenaufbau:\n"
            . "- Eine einzelne Zufallszahl: random.randint(a, b).\n"
            . "- Mit einer for-Schleife kannst du 30 Werte erzeugen und per append() in eine Liste schreiben.\n"
            . "- Eigene Funktionen mit def: einmal Maximum, einmal Durchschnitt ueber Schleife selbst berechnen.";
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
import random

#INIT START
seed = int(input("Seed: "))
random.seed(seed)
beispiel = random.randint(1, 100)
#INIT END

def finde_maximum(werte):
    # TODO: Maximum selbst bestimmen
    return 0

def berechne_durchschnitt(werte):
    # TODO: Durchschnitt selbst bestimmen
    return 0

zahlen = []
# TODO: Dynamisch 30 Zufallszahlen in zahlen erzeugen (for)

max_wert = finde_maximum(zahlen)
durchschnitt = berechne_durchschnitt(zahlen)

print(zahlen)
print(max_wert)
print(durchschnitt)
PY;

        $solutionCode = <<<'PY'
import random

#INIT START
seed = int(input("Seed: "))
random.seed(seed)
beispiel = random.randint(1, 100)
#INIT END

def finde_maximum(werte):
    groesstes = werte[0]
    for x in werte:
        if x > groesstes:
            groesstes = x
    return groesstes

def berechne_durchschnitt(werte):
    gesamt = 0
    for x in werte:
        gesamt = gesamt + x
    return gesamt / len(werte)

zahlen = []
for _ in range(30):
    zahlen.append(random.randint(1, 100))

max_wert = finde_maximum(zahlen)
durchschnitt = berechne_durchschnitt(zahlen)

print(zahlen)
print(max_wert)
print(durchschnitt)
PY;

        $testCases = json_encode([
            'mode' => 'vars',
            'tests' => 6,
            'inputs' => ['seed'],
            'outputs' => ['zahlen', 'max_wert', 'durchschnitt']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = <<<'PY'
import random
seed = random.randint(1, 9999)
values = {
    "INPUT_01": seed,
    "seed": seed
}
PY;

        $hint1 = 'Nutze for _ in range(30): und append(random.randint(...)).';
        $hint2 = 'In finde_maximum startest du mit dem ersten Element und vergleichst dann alle weiteren.';
        $hint3 = 'Durchschnitt: alle Werte aufsummieren und durch len(zahlen) teilen.';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#10 Rechnung I';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Erfasse zuerst die Anzahl der Positionen. Lies danach genau so viele Betraege ein und berechne die Summe.';
        $description = 'EVA: Anzahl einlesen, n-mal Betrag einlesen, Summe ausgeben. Speichere das Ergebnis in summe.';
        $stoff = 'for-Schleife mit benutzerdefinierter Anzahl, float-Eingaben, Summenbildung.';
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
anzahl_positionen = int(input("Anzahl Positionen: "))

summe = 0.0

# TODO: Lies abhaengig von anzahl_positionen die Betraege ein und summiere sie

print(summe)
PY;

        $solutionCode = <<<'PY'
anzahl_positionen = int(input("Anzahl Positionen: "))

summe = 0.0
for i in range(anzahl_positionen):
    betrag = float(input(f"Betrag {i + 1}: "))
    summe = summe + betrag

print(summe)
PY;

        $testCases = json_encode([
            'mode' => 'vars',
            'tests' => 6,
            'inputs' => ['anzahl_positionen'],
            'outputs' => ['summe']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = <<<'PY'
import random
n = random.randint(2, 4)
b1 = round(random.uniform(1.0, 20.0), 2)
b2 = round(random.uniform(1.0, 20.0), 2)
b3 = round(random.uniform(1.0, 20.0), 2)
b4 = round(random.uniform(1.0, 20.0), 2)
values = {
    "INPUT_01": n,
    "INPUT_02": b1,
    "INPUT_03": b2,
    "INPUT_04": b3,
    "INPUT_05": b4,
    "anzahl_positionen": n
}
PY;

        $hint1 = 'Lies zuerst die Anzahl ein: anzahl_positionen.';
        $hint2 = 'Nutze for i in range(anzahl_positionen), um genau n Betraege zu lesen.';
        $hint3 = 'summe startet bei 0.0 und wird pro Eingabe um den Betrag erhoeht.';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#11 Rechnung II';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Lies Betraege ein, bis eine leere Eingabe kommt. Dann brich ab und gib die Summe aus.';
        $description = 'Nutze eine while-Schleife mit Abbruch bei leerem Input. Speichere das Ergebnis in summe.';
        $stoff = 'while-Schleife, Sentinel-Wert (leerer String), float-Umwandlung, Summenbildung.';
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
summe = 0.0

# TODO: Lies so lange Betraege ein, bis eine leere Eingabe kommt
# Danach Summe ausgeben

print(summe)
PY;

        $solutionCode = <<<'PY'
summe = 0.0

while True:
    text = input("Betrag (leer = Ende): ")
    if text == "":
        break
    summe = summe + float(text)

print(summe)
PY;

        $testCases = json_encode([
            'mode' => 'vars',
            'tests' => 6,
            'inputs' => [],
            'outputs' => ['summe']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = <<<'PY'
import random
b1 = round(random.uniform(1.0, 25.0), 2)
b2 = round(random.uniform(1.0, 25.0), 2)
b3 = round(random.uniform(1.0, 25.0), 2)
values = {
    "INPUT_01": b1,
    "INPUT_02": b2,
    "INPUT_03": b3,
    "INPUT_04": ""
}
PY;

        $hint1 = 'Mit while True laeuft die Eingabe-Schleife dauerhaft.';
        $hint2 = 'Bei leerer Eingabe ("" ) mit break beenden.';
        $hint3 = 'Nur nicht-leere Eingaben zu float machen und auf summe addieren.';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#12 Rechnung III';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Speichere Rechnungspositionen in einer Liste mit Dicts: Position, Art (Getraenk/Speise), Anzahl, Einzelpreis. Abbruch bei leerer Eingabe.';
        $description = 'Erfasse Positionen bis der Name leer ist. Berechne pro Position die positionssumme und die gesamtsumme. Gib Liste und gesamtsumme aus.';
        $stoff = "Datenstruktur fuer Rechnungen:\n"
            . "- Liste fuer mehrere Positionen.\n"
            . "- Dict pro Position mit Schluesseln: position, art, anzahl, einzelpreis, positionssumme.\n"
            . "- while-Schleife mit Abbruch bei leerer Eingabe.";
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
rechnung = []
gesamt_summe = 0.0
pos = 1

while True:
    name = input("Name der Position (leer = Ende): ")
    # TODO: Bei leerem Namen abbrechen

    art = input("Art (Getraenk/Speise): ")
    anzahl = int(input("Anzahl: "))
    einzelpreis = float(input("Einzelpreis: "))

    # TODO: positionssumme berechnen
    # TODO: Dict bauen und in rechnung speichern
    # TODO: gesamtsumme aktualisieren und Position weiterzaehlen

print(rechnung)
print(gesamt_summe)
PY;

        $solutionCode = <<<'PY'
rechnung = []
gesamt_summe = 0.0
pos = 1

    name = input("Name der Position (leer = Ende): ")
    while name != "":
    art = input("Art (Getraenk/Speise): ")
    anzahl = int(input("Anzahl: "))
    einzelpreis = float(input("Einzelpreis: "))

    positionssumme = anzahl * einzelpreis
    eintrag = {
        "position": pos,
        "name": name,
        "art": art,
        "anzahl": anzahl,
        "einzelpreis": einzelpreis,
        "positionssumme": positionssumme
    }
    rechnung.append(eintrag)

    gesamt_summe = gesamt_summe + positionssumme
    pos = pos + 1
    name = input("Name der Position (leer = Ende): ")

print(rechnung)
print(gesamt_summe)
PY;

        $testCases = json_encode([
            'mode' => 'vars',
            'tests' => 5,
            'inputs' => [],
            'outputs' => ['rechnung', 'gesamt_summe']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = <<<'PY'
import random
speisen = ["Pizza", "Salat", "Pasta", "Suppe"]
getraenke = ["Wasser", "Saft", "Tee", "Cola"]

name1 = random.choice(speisen)
art1 = "Speise"
anz1 = random.randint(1, 4)
preis1 = round(random.uniform(3.0, 14.0), 2)

name2 = random.choice(getraenke)
art2 = "Getraenk"
anz2 = random.randint(1, 5)
preis2 = round(random.uniform(1.5, 6.0), 2)

values = {
    "INPUT_01": name1,
    "INPUT_02": art1,
    "INPUT_03": anz1,
    "INPUT_04": preis1,
    "INPUT_05": name2,
    "INPUT_06": art2,
    "INPUT_07": anz2,
    "INPUT_08": preis2,
    "INPUT_09": ""
}
PY;

        $hint1 = 'Leerer Name ist das Abbruchsignal fuer die while-Schleife.';
        $hint2 = 'positionssumme = anzahl * einzelpreis und als Feld im Dict speichern.';
        $hint3 = 'Nach jedem Eintrag gesamt_summe erhoehen und pos um 1 steigern.';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#13 Schachbrettfelder';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $taskText = 'Stelle alle Felder eines Schachbretts von A8 bis H1 dar. In der obersten Zeile muessen A8 bis H8 stehen.';
        $description = 'Loese die Aufgabe mit zwei verschachtelten Schleifen ueber zwei Listen. Gib jede Reihe in einer neuen Zeile aus.';
        $stoff = "Verschachtelte Schleifen mit Listen:\n"
            . "- Lege eine Liste fuer Spalten (A bis H) und eine Liste fuer Reihen (8 bis 1) an.\n"
            . "- Aussen ueber Reihen, innen ueber Spalten iterieren und Felder zusammensetzen.\n"
            . "- Optional (Alternative): Spaltenzeichen mit ASCII erzeugen, z. B. chr(65) bis chr(72).";
        $taskType = 'code';
        $problemType = 'code_completion';

        $codeTemplate = <<<'PY'
# TODO: Gib die Schachbrettfelder aus
# Erwartete erste Zeile: A8 B8 C8 D8 E8 F8 G8 H8

spalten = ["A", "B", "C", "D", "E", "F", "G", "H"]
reihen = [8, 7, 6, 5, 4, 3, 2, 1]

# Nutze zwei verschachtelte for-Schleifen
PY;

        $solutionCode = <<<'PY'
spalten = ["A", "B", "C", "D", "E", "F", "G", "H"]
reihen = [8, 7, 6, 5, 4, 3, 2, 1]

for reihe in reihen:
    zeile_text = ""
    for spalte in spalten:
        feld = spalte + str(reihe)
        if spalte != "H":
            zeile_text = zeile_text + feld + " "
        else:
            zeile_text = zeile_text + feld
    print(zeile_text)
PY;

        $testCases = json_encode([
            [
                'type' => 'code_check',
                'keywords' => [
                    'for\\s+',
                ],
                'operator' => 'AND',
                'feedback' => 'Verwende mindestens eine for-Schleife (hier sollen es zwei verschachtelte sein).',
            ],
            [
                'type' => 'output',
                'expected_type' => 'exact',
                'value' => "A8 B8 C8 D8 E8 F8 G8 H8\nA7 B7 C7 D7 E7 F7 G7 H7\nA6 B6 C6 D6 E6 F6 G6 H6\nA5 B5 C5 D5 E5 F5 G5 H5\nA4 B4 C4 D4 E4 F4 G4 H4\nA3 B3 C3 D3 E3 F3 G3 H3\nA2 B2 C2 D2 E2 F2 G2 H2\nA1 B1 C1 D1 E1 F1 G1 H1",
                'feedback' => 'Die Ausgabe muss exakt die Felder von A8 bis H1 zeilenweise enthalten.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $randomizerCode = null;
        $hint1 = 'Nutze zwei Listen: Spalten A-H und Reihen 8-1.';
        $hint2 = 'Aussen ueber Reihen, innen ueber Spalten laufen.';
        $hint3 = 'Feldname entsteht aus Spalte + Reihenzahl, dann pro Reihe ausgeben.';
        $maxAttempts = 10;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed (task insert): ' . $conn->error);
        }

        $stmt->bind_param(
            'issssisssssssssi',
            $assignmentId,
            $taskTitle,
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
            $hint1,
            $hint2,
            $hint3,
            $maxAttempts
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed (task insert): ' . $stmt->error);
        }

        $taskId = (int)$conn->insert_id;
        $stmt->close();

        $diffStmt = $conn->prepare('UPDATE tasks SET task_difficulty = ? WHERE id = ?');
        if (!$diffStmt) {
            throw new Exception('Prepare failed (difficulty update): ' . $conn->error);
        }
        $taskDifficulty = 'hard';
        $diffStmt->bind_param('si', $taskDifficulty, $taskId);
        if (!$diffStmt->execute()) {
            throw new Exception('Execute failed (difficulty update): ' . $diffStmt->error);
        }
        $diffStmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    $taskTitle = '#14 Code Reading verschachtelte Schleife 3x2';
    if (taskExistsByTitle043($conn, $assignmentId, $taskTitle)) {
        echo "⚠ Skipped (already exists): {$taskTitle}\n";
    } else {
        $position = getNextPosition043($conn, $assignmentId);

        $inputKeys = ['a', 'b', 'c', 'd', 'e', 'f', 'grenze'];
        $inputs = [];
        foreach ($inputKeys as $k) {
            $inputs[$k] = '<random>';
        }

        $task = [
            'assignment_id' => $assignmentId,
            'title' => $taskTitle,
            'task_text' => 'Was ist der Endwert von antwort? Achte auf die verschachtelte Schleife und die Gewichtung.',
            'description' => 'Nicht nur zaehlen: Fuer Werte unter der Grenze wird positionsabhaengig addiert. Code ist sichtbar.',
            'stoff' => '<p>Vorgehen auf Papier (pro Schritt eine Zeile ausfuellen):</p>'
                . '<table class="stoff-trace-table" border="1" cellpadding="6" cellspacing="0">'
                . '<thead><tr><th>Schritt</th><th>i</th><th>j</th><th>v</th><th>counter</th><th>s</th></tr></thead>'
                . '<tbody>'
                . '<tr><td>1</td><td></td><td></td><td></td><td></td><td></td></tr>'
                . '<tr><td>2</td><td></td><td></td><td></td><td></td><td></td></tr>'
                . '<tr><td>3</td><td></td><td></td><td></td><td></td><td></td></tr>'
                . '<tr><td>4</td><td></td><td></td><td></td><td></td><td></td></tr>'
                . '<tr><td>5</td><td></td><td></td><td></td><td></td><td></td></tr>'
                . '<tr><td>6</td><td></td><td></td><td></td><td></td><td></td></tr>'
                . '</tbody></table>'
                . '<p>Hinweis: counter steigt bei jedem v-Wert. s steigt nur, wenn v &lt; grenze.</p>',
            'position' => $position,
            'code_template' => <<<'PY'
m = [[{a}, {b}], [{c}, {d}], [{e}, {f}]]
grenze = {grenze}

s = 0
counter = 0
for i in range(3):
    for j in range(2):
        v = m[i][j]
        counter = counter + 1
        if v < grenze:
            s = s + (i + 1) * (j + 2)

antwort = s
PY,
            'solution_code' => <<<'PY'
m = [[{a}, {b}], [{c}, {d}], [{e}, {f}]]
grenze = {grenze}

s = 0
counter = 0
for i in range(3):
    for j in range(2):
        v = m[i][j]
        counter = counter + 1
        if v < grenze:
            s = s + (i + 1) * (j + 2)

antwort = s
PY,
            'randomizer_code' => <<<'PY'
import random
a = random.randint(1, 20)
b = random.randint(1, 20)
c = random.randint(1, 20)
d = random.randint(1, 20)
e = random.randint(1, 20)
f = random.randint(1, 20)
grenze = random.randint(8, 15)
PY,
            'variable_overrides' => json_encode([
                [
                    'inputs' => $inputs,
                    'expected' => ['variable' => 'antwort']
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'iterations_count' => 6,
            'hint1' => 'counter laeuft immer 1..6, unabhaengig von der Bedingung.',
            'hint2' => 'Nur wenn v < grenze gilt, wird s veraendert.',
            'hint3' => 'Die Addition nutzt die Position: (i + 1) * (j + 2).',
            'max_attempts' => 10,
        ];

        $taskId = insertCodeRandomComplexTask043($conn, $task);

        $diffStmt = $conn->prepare('UPDATE tasks SET task_difficulty = ? WHERE id = ?');
        if (!$diffStmt) {
            throw new Exception('Prepare failed (difficulty update): ' . $conn->error);
        }
        $taskDifficulty = 'hard';
        $diffStmt->bind_param('si', $taskDifficulty, $taskId);
        if (!$diffStmt->execute()) {
            throw new Exception('Execute failed (difficulty update): ' . $diffStmt->error);
        }
        $diffStmt->close();

        echo "✓ Created task #{$taskId}: {$taskTitle}\n";
    }

    echo "\n✅ Migration 043: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 043 failed: " . $e->getMessage() . "\n";
    exit(1);
}
