<?php
/**
 * Migration 037: Add function intro tasks.
 *
 * Tasks created (all on assignment 23):
 *   #198  Funktion: verdreifachen             – function return test
 *   #199  Funktion: Fahrenheit zu Celsius     – function return test, formula in stoff
 *   #200  Funktion: Wuerfelzahl               – code_check + output range test
 *   #201  Funktion mit Liste: Korrekturwert   – pass-by-reference illustration
 *   #202  Code Lesen: Scope                   – code_reading, global vs. lokal
 */

require_once __DIR__ . '/../../config/database.php';

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
        variable_overrides,
        hint1,
        hint2,
        hint3,
        max_attempts,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed (insertTaskRow): ' . $conn->error);
    }

    $assignmentId   = $task['assignment_id'];
    $title          = $task['title'];
    $taskText       = $task['task_text'];
    $description    = $task['description'];
    $stoff          = $task['stoff'];
    $position       = $task['position'];
    $taskType       = $task['task_type'];
    $problemType    = $task['problem_type'];
    $codeTemplate   = $task['code_template'];
    $solutionCode   = $task['solution_code'];
    $testCases      = $task['test_cases'];
    $varOverrides   = $task['variable_overrides'];
    $hint1          = $task['hint1'];
    $hint2          = $task['hint2'];
    $hint3          = $task['hint3'];
    $maxAttempts    = $task['max_attempts'];

    $stmt->bind_param(
        'issssisssssssssi',
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
        $varOverrides,
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

// ---------------------------------------------------------------------------
try {
    $conn = getDbConnection();
    echo "Running Migration 037: add function intro tasks...\n";

    $assignmentId = 23;
    $posResult = $conn->query("SELECT MAX(position) AS max_pos FROM tasks WHERE assignment_id = {$assignmentId}");
    if (!$posResult) {
        throw new Exception('Failed to determine next position: ' . $conn->error);
    }

    $posRow  = $posResult->fetch_assoc();
    $nextPos = ((int)($posRow['max_pos'] ?? 0)) + 1;

    // -----------------------------------------------------------------------
    // Task 198 – Verdreifachen (function intro, return value)
    // -----------------------------------------------------------------------
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title'         => 'Funktion: verdreifachen',
        'task_text'     => 'Schreibe eine Funktion verdreifachen(zahl), die das Dreifache von zahl zurueckgibt.',
        'description'   => 'Einstiegsaufgabe zu Funktionen mit Rueckgabewert. '
            . 'Definiere die Funktion mit einem Parameter zahl, berechne das Dreifache '
            . 'und gib das Ergebnis mit return zurueck. '
            . 'Der Aufruf verdreifachen(5) soll 15 liefern.',
        'stoff'         => "Funktionen definieren und aufrufen\n\n"
            . "def verdreifachen(zahl):\n"
            . "    ergebnis = zahl * 3\n"
            . "    return ergebnis\n\n"
            . 'Der Schluesselbefehl return beendet die Funktion und gibt den berechneten Wert '
            . 'an den Aufrufer zurueck. Der Rueckgabewert kann einer Variablen zugewiesen oder '
            . 'direkt ausgegeben werden.',
        'position'      => $nextPos++,
        'task_type'     => 'code',
        'problem_type'  => 'code_completion',
        'code_template' => <<<'PY'
def verdreifachen(zahl):
    # Berechne das Dreifache von zahl und gib es zurueck
    ergebnis = 0
    return ergebnis

# Teste deine Funktion
resultat = verdreifachen(5)
print(resultat)
PY,
        'solution_code' => <<<'PY'
def verdreifachen(zahl):
    ergebnis = zahl * 3
    return ergebnis

resultat = verdreifachen(5)
print(resultat)
PY,
        'test_cases'        => json_encode([
            [
                'type'          => 'function',
                'function_name' => 'verdreifachen',
                'test_cases'    => [
                    ['args' => [5],  'expected' => 15],
                    ['args' => [7],  'expected' => 21],
                    ['args' => [0],  'expected' => 0],
                    ['args' => [-3], 'expected' => -9],
                ],
                'feedback' => 'verdreifachen(x) soll x * 3 zurueckgeben.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'variable_overrides' => null,
        'hint1' => 'Nutze das Schluesselwort def, um eine Funktion zu definieren.',
        'hint2' => 'Ein return-Statement gibt den berechneten Wert zurueck.',
        'hint3' => 'ergebnis = zahl * 3 berechnet das Dreifache.',
        'max_attempts' => 10,
    ];

    // -----------------------------------------------------------------------
    // Task 199 – Fahrenheit zu Celsius (function, formula in stoff)
    // -----------------------------------------------------------------------
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title'         => 'Funktion: Fahrenheit zu Celsius',
        'task_text'     => 'Schreibe eine Funktion fahrenheit_zu_celsius(f), die einen Fahrenheit-Wert in Grad Celsius umrechnet und zurueckgibt.',
        'description'   => 'Die Umrechnungsformel und zwei Kontrollwerte stehen im Stoff. '
            . 'Implementiere die Formel im Funktionsrumpf und liefere das Ergebnis per return. '
            . 'Der Aufruf fahrenheit_zu_celsius(32) soll 0.0 ergeben.',
        'stoff'         => "Umrechnungsformel Fahrenheit → Celsius:\n\n"
            . "    C = (F - 32) * 5 / 9\n\n"
            . "Kontrollwerte:\n"
            . "  32 °F  →   0.0 °C  (Gefrierpunkt)\n"
            . "  212 °F → 100.0 °C  (Siedepunkt)\n"
            . "  41 °F  →   5.0 °C\n\n"
            . "Beispiel:\n"
            . "def fahrenheit_zu_celsius(f):\n"
            . "    c = (f - 32) * 5 / 9\n"
            . "    return c",
        'position'      => $nextPos++,
        'task_type'     => 'code',
        'problem_type'  => 'code_completion',
        'code_template' => <<<'PY'
def fahrenheit_zu_celsius(f):
    # Berechne Celsius mit der Formel aus dem Stoff
    c = 0
    return c

# 32 Grad Fahrenheit entsprechen 0 Grad Celsius
ergebnis = fahrenheit_zu_celsius(32)
print(ergebnis)
PY,
        'solution_code' => <<<'PY'
def fahrenheit_zu_celsius(f):
    c = (f - 32) * 5 / 9
    return c

ergebnis = fahrenheit_zu_celsius(32)
print(ergebnis)
PY,
        'test_cases'        => json_encode([
            [
                'type'          => 'function',
                'function_name' => 'fahrenheit_zu_celsius',
                'test_cases'    => [
                    ['args' => [32],  'expected' => 0.0],
                    ['args' => [212], 'expected' => 100.0],
                    ['args' => [41],  'expected' => 5.0],
                ],
                'feedback' => 'fahrenheit_zu_celsius(f) soll (f - 32) * 5 / 9 zurueckgeben.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'variable_overrides' => null,
        'hint1' => 'Die Formel lautet: C = (F - 32) * 5 / 9.',
        'hint2' => 'Speichere das Ergebnis in einer Variablen c und gib sie mit return zurueck.',
        'hint3' => 'fahrenheit_zu_celsius(32) muss 0.0 ergeben: (32 - 32) * 5 / 9 = 0.0.',
        'max_attempts' => 10,
    ];

    // -----------------------------------------------------------------------
    // Task 200 – Wuerfelzahl (random.randint, stoff explains import)
    // -----------------------------------------------------------------------
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title'         => 'Funktion: Wuerfelzahl',
        'task_text'     => 'Schreibe eine Funktion wuerfel(), die eine zufaellige ganze Zahl zwischen 1 und 6 zurueckgibt.',
        'description'   => 'Importiere das Modul random und nutze random.randint(1, 6), '
            . 'um eine zufaellige Zahl im Bereich [1, 6] zu erzeugen. '
            . 'Das Modul und die Funktion randint werden im Stoff erklaert.',
        'stoff'         => "Zufallszahlen mit dem Modul random\n\n"
            . "import random\n\n"
            . "Das Modul random stellt Funktionen zur Erzeugung von Zufallszahlen bereit.\n\n"
            . "random.randint(a, b)\n"
            . "  Liefert eine zufaellige ganze Zahl n mit a <= n <= b.\n"
            . "  Beide Grenzen sind inklusive.\n\n"
            . "Beispiele:\n"
            . "  random.randint(1, 6)   # simuliert einen Wuerfel\n"
            . "  random.randint(0, 100) # Zahl zwischen 0 und 100\n\n"
            . "Import-Anweisung immer am Anfang der Datei platzieren:\n"
            . "  import random",
        'position'      => $nextPos++,
        'task_type'     => 'code',
        'problem_type'  => 'code_completion',
        'code_template' => <<<'PY'
import random

def wuerfel():
    # Gib eine zufaellige Zahl zwischen 1 und 6 zurueck
    return 0

ergebnis = wuerfel()
print(ergebnis)
PY,
        'solution_code' => <<<'PY'
import random

def wuerfel():
    return random.randint(1, 6)

ergebnis = wuerfel()
print(ergebnis)
PY,
        'test_cases'        => json_encode([
            [
                'type'     => 'code_check',
                'keywords' => [
                    'import\\s+random',
                    'def\\s+wuerfel\\s*\\(\\s*\\)',
                    'randint\\s*\\(\\s*1\\s*,\\s*6\\s*\\)',
                ],
                'operator' => 'AND',
                'feedback' => 'Importiere random, definiere eine Funktion wuerfel() und nutze random.randint(1, 6).',
            ],
            [
                'type'          => 'output',
                'expected_type' => 'regex',
                'pattern'       => '^\\s*[1-6]\\s*$',
                'feedback'      => 'Die Ausgabe muss eine ganze Zahl zwischen 1 und 6 sein.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'variable_overrides' => null,
        'hint1' => 'Ein Modul importierst du mit: import random',
        'hint2' => 'random.randint(a, b) liefert eine ganze Zahl >= a und <= b.',
        'hint3' => 'return random.randint(1, 6) genuegt als kompletter Funktionsrumpf.',
        'max_attempts' => 10,
    ];

    // -----------------------------------------------------------------------
    // Task 201 – Korrekturwert auf Temperaturlisten (list as argument, by-ref)
    // -----------------------------------------------------------------------
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title'         => 'Funktion mit Liste: Korrekturwert',
        'task_text'     => 'Schreibe eine Funktion korrektur_anwenden(messwerte), die 0.3 auf jeden der vier Listeneintraege addiert.',
        'description'   => 'Im Kopf sind vier Messreihen mit je vier Temperaturwerten vorgegeben. '
            . 'Die Funktion bekommt eine dieser Listen als Argument und veraendert sie direkt, '
            . 'indem sie jeden Index explizit neu berechnet (keine Schleifen – jeder Index '
            . 'wird einzeln geschrieben). '
            . 'Anschliessend wird die Funktion fuer alle vier Reihen aufgerufen. '
            . 'Gib serie_a und serie_b danach aus und pruefe, ob die Werte um 0.3 gestiegen sind.',
        'stoff'         => "Listen als Argumente – Referentielle Uebergabe\n\n"
            . "Wenn du eine Liste an eine Funktion uebergibst, arbeitet die Funktion\n"
            . "direkt mit der Originalliste – sie bekommt keine Kopie.\n"
            . "Aenderungen innerhalb der Funktion sind deshalb auch ausserhalb sichtbar.\n\n"
            . "Beispiel:\n"
            . "def erhoehe_ersten(liste):\n"
            . "    liste[0] = liste[0] + 1\n\n"
            . "zahlen = [10, 20, 30]\n"
            . "erhoehe_ersten(zahlen)\n"
            . "print(zahlen)  # [11, 20, 30]\n\n"
            . "Da noch keine Schleifen bekannt sind, wird jeder Index explizit angegeben:\n"
            . "    messwerte[0] = messwerte[0] + 0.3\n"
            . "    messwerte[1] = messwerte[1] + 0.3\n"
            . "    ...",
        'position'      => $nextPos++,
        'task_type'     => 'code',
        'problem_type'  => 'code_completion',
        'code_template' => <<<'PY'
serie_a = [18.2, 19.5, 21.0, 17.8]
serie_b = [22.1, 20.4, 19.9, 23.5]
serie_c = [15.7, 16.3, 18.0, 14.9]
serie_d = [25.2, 24.8, 22.6, 26.1]

def korrektur_anwenden(messwerte):
    # Addiere 0.3 auf jeden der vier Eintraege (jeden Index explizit angeben)
    messwerte[0] = 0
    messwerte[1] = 0
    messwerte[2] = 0
    messwerte[3] = 0

korrektur_anwenden(serie_a)
korrektur_anwenden(serie_b)
korrektur_anwenden(serie_c)
korrektur_anwenden(serie_d)

print(serie_a)
print(serie_b)
PY,
        'solution_code' => <<<'PY'
serie_a = [18.2, 19.5, 21.0, 17.8]
serie_b = [22.1, 20.4, 19.9, 23.5]
serie_c = [15.7, 16.3, 18.0, 14.9]
serie_d = [25.2, 24.8, 22.6, 26.1]

def korrektur_anwenden(messwerte):
    messwerte[0] = messwerte[0] + 0.3
    messwerte[1] = messwerte[1] + 0.3
    messwerte[2] = messwerte[2] + 0.3
    messwerte[3] = messwerte[3] + 0.3

korrektur_anwenden(serie_a)
korrektur_anwenden(serie_b)
korrektur_anwenden(serie_c)
korrektur_anwenden(serie_d)

print(serie_a)
print(serie_b)
PY,
        'test_cases'        => json_encode([
            [
                'type'     => 'code_check',
                'keywords' => [
                    'def\\s+korrektur_anwenden\\s*\\(\\s*messwerte\\s*\\)',
                    'messwerte\\[0\\]',
                    'messwerte\\[1\\]',
                    'messwerte\\[2\\]',
                    'messwerte\\[3\\]',
                    '0\\.3',
                ],
                'operator' => 'AND',
                'feedback' => 'Definiere korrektur_anwenden(messwerte) und addiere 0.3 auf alle vier Indizes (0 bis 3) explizit.',
            ],
            [
                'type'          => 'output',
                'expected_type' => 'regex',
                'pattern'       => '18\\.5',
                'feedback'      => 'serie_a[0] sollte nach der Korrektur 18.5 ergeben (18.2 + 0.3).',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'variable_overrides' => null,
        'hint1' => 'Jeder Index muss explizit neu berechnet werden: messwerte[0] = messwerte[0] + 0.3',
        'hint2' => 'Du veraenderst die Originalliste direkt – das ist die referentielle Uebergabe.',
        'hint3' => 'Vergiss keinen der Indizes 0, 1, 2 und 3.',
        'max_attempts' => 10,
    ];

    // -----------------------------------------------------------------------
    // Task 202 – Code Lesen: Scope (global vs. lokal, code_reading)
    // -----------------------------------------------------------------------
    $tasks[] = [
        'assignment_id' => $assignmentId,
        'title'         => 'Code Lesen: Lokale und globale Variablen',
        'task_text'     => 'Was gibt dieses Programm aus?',
        'description'   => 'Der Code definiert eine globale Variable x und eine Funktion veraendere(), '
            . 'die intern ebenfalls eine Variable x anlegt. '
            . 'Verfolge den Ablauf Schritt fuer Schritt: '
            . 'Welchen Wert hat x in der Funktion, welchen danach im Hauptprogramm?',
        'stoff'         => "Scope: lokale und globale Variablen\n\n"
            . "Eine Variable, die innerhalb einer Funktion definiert wird, ist lokal –\n"
            . "sie existiert nur waehrend der Ausfuehrung der Funktion und ist von einer\n"
            . "gleichnamigen globalen Variablen voellig unabhaengig.\n\n"
            . "Beispiel:\n"
            . "x = 10            # globale Variable\n\n"
            . "def zeige():\n"
            . "    x = 20        # neue, lokale Variable – anderes x!\n"
            . "    print(x)      # 20\n\n"
            . "zeige()\n"
            . "print(x)          # 10 – globales x unveraendert\n\n"
            . "Das globale x wird nur dann veraendert, wenn man es\n"
            . "explizit mit dem Schluesselwort global deklariert.\n"
            . "(global ist aber kein bewusster Programmierstil und wird hier nur erklaert.)",
        'position'      => $nextPos++,
        'task_type'     => 'code_reading',
        'problem_type'  => 'code_completion',
        'code_template' => <<<'PY'
x = a

def veraendere():
    x = b
    x = x + 1
    print("innen:", x)

veraendere()
print("aussen:", x)
PY,
        'solution_code' => null,
        'test_cases'    => null,
        'variable_overrides' => json_encode([
            'a' => [10, 20, 5],
            'b' => [3,  7,  1],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'hint1' => 'Verfolge den Ablauf: erst laeuft veraendere(), dann kommt print("aussen:", x).',
        'hint2' => 'Das x innerhalb der Funktion ist eine neue, lokale Variable – nicht das globale x.',
        'hint3' => 'innen zeigt b + 1; aussen zeigt den unveraenderten Wert a.',
        'max_attempts' => 10,
    ];

    // -----------------------------------------------------------------------
    // Insert all tasks
    // -----------------------------------------------------------------------
    foreach ($tasks as $task) {
        if (taskExistsByTitle($conn, $task['assignment_id'], $task['title'])) {
            echo "⚠ Skipped (already exists): {$task['title']}\n";
            continue;
        }

        $taskId = insertTaskRow($conn, $task);
        echo "✓ Created task #{$taskId}: {$task['title']}\n";
    }

    echo "\n✅ Migration 037: Success!\n";
    $conn->close();

} catch (Exception $e) {
    echo "❌ Migration 037 failed: " . $e->getMessage() . "\n";
    exit(1);
}
