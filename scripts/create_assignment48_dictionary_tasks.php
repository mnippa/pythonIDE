<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$tasks = [];
$tasks[] = [
    'title' => 'Dictionary: Artikelinformationen auslesen',
    'task_text' => 'Ermittle die Artikelbezeichnung und den Gesamtwert des vorhandenen Bestands. Speichere die Ergebnisse in bezeichnung und bestandswert und gib beide jeweils in einer eigenen Zeile aus.',
    'description' => '<p>Das Dictionary <code>artikel</code> enthält die Schlüssel <code>bezeichnung</code> (Zeichenkette), <code>preis</code> (Stückpreis als Zahl) und <code>bestand</code> (ganze Zahl).</p><p>Erwartet werden <code>bezeichnung</code> als Zeichenkette und <code>bestandswert</code> als Zahl: Stückpreis × Bestand. Das Dictionary bleibt unverändert.</p><p>Ausgabe: zuerst die Bezeichnung, danach der Bestandswert, jeweils in einer eigenen Zeile und ohne zusätzliche Beschriftung. Eine feste Anzahl an Nachkommastellen ist nicht erforderlich.</p><p>Die Prüfung verwendet auch andere Artikel, Preise und Bestände, einschließlich Bestand 0. Die vorgegebenen Eingabewerte stehen im INIT-Block; dein Lösungscode steht darunter.</p>',
    'init' => 'artikel = {"bezeichnung": "Ordner", "preis": 3.5, "bestand": 12}',
    'body' => "bezeichnung = artikel[\"bezeichnung\"]\nbestandswert = artikel[\"preis\"] * artikel[\"bestand\"]\nprint(bezeichnung)\nprint(bestandswert)",
    'inputs' => ['artikel'],
    'outputs' => ['bezeichnung', 'bestandswert', 'artikel'],
    'randomizer' => "import random\nvalues = {\"artikel\": {\"bezeichnung\": random.choice([\"Stift\", \"Mappe\", \"Heft\", \"Büroklammern\"]), \"preis\": random.randint(1, 200) / 4, \"bestand\": random.randint(0, 100)}}",
    'fixed' => [
        [['bezeichnung' => 'Heft', 'preis' => 1.25, 'bestand' => 0]],
        [['bezeichnung' => 'Mappe', 'preis' => 2.5, 'bestand' => 7]],
    ],
    'expected_fixed' => [
        ['Heft', 0, ['bezeichnung' => 'Heft', 'preis' => 1.25, 'bestand' => 0]],
        ['Mappe', 17.5, ['bezeichnung' => 'Mappe', 'preis' => 2.5, 'bestand' => 7]],
    ],
    'output_pattern' => '^Ordner\\s*\\r?\\n\\s*42(?:\\.0+)?$',
    'difficulty' => 'basic',
];
$tasks[] = [
    'title' => 'Dictionary: Lagerbestand nach einer Lieferung ändern',
    'task_text' => 'Erhöhe den Bestand in artikel um liefermenge. Alle anderen Artikelangaben bleiben unverändert. Gib den neuen Bestand als einzelne Zahl aus.',
    'description' => '<p>Gegeben sind das Dictionary <code>artikel</code> und die nicht negative ganze Zahl <code>liefermenge</code>. Der Schlüssel <code>bestand</code> enthält den bisherigen Lagerbestand.</p><p>Erwartet wird das aktualisierte Dictionary <code>artikel</code>: alter Bestand + Liefermenge unter <code>bestand</code>. Alle anderen Schlüssel und Werte bleiben erhalten, auch zusätzliche Artikelangaben.</p><p>Ausgabe: ausschließlich der neue Bestand als ganze Zahl. Die Prüfung verwendet unterschiedliche Bestände und Liefermengen, auch 0. Der Lösungscode steht unter dem INIT-Block.</p>',
    'init' => "artikel = {\"bezeichnung\": \"Ordner\", \"preis\": 3.5, \"bestand\": 12}\nliefermenge = 8",
    'body' => "artikel[\"bestand\"] += liefermenge\nprint(artikel[\"bestand\"])",
    'inputs' => ['artikel', 'liefermenge'],
    'outputs' => ['artikel'],
    'randomizer' => "import random\nvalues = {\"artikel\": {\"bezeichnung\": random.choice([\"Heft\", \"Stift\"]), \"preis\": random.randint(1, 100) / 4, \"bestand\": random.randint(0, 90), \"lagerort\": random.choice([\"A1\", \"B2\"])}, \"liefermenge\": random.randint(0, 40)}",
    'fixed' => [
        [['bezeichnung' => 'Heft', 'preis' => 1.5, 'bestand' => 0, 'lagerort' => 'B2'], 0],
        [['bezeichnung' => 'Stift', 'preis' => 2, 'bestand' => 9], 7],
    ],
    'expected_fixed' => [
        [['bezeichnung' => 'Heft', 'preis' => 1.5, 'bestand' => 0, 'lagerort' => 'B2']],
        [['bezeichnung' => 'Stift', 'preis' => 2, 'bestand' => 16]],
    ],
    'output_pattern' => '^20$',
    'difficulty' => 'basic',
];
$tasks[] = [
    'title' => 'Dictionary: einen Artikel im Preisverzeichnis ergänzen',
    'task_text' => 'Ergänze neue_artikelnummer mit neuer_preis im Dictionary preise. Speichere die Anzahl aller Artikel in artikelanzahl. Gib den Preis des neuen Artikels und die Artikelanzahl jeweils in einer eigenen Zeile aus.',
    'description' => '<p><code>preise</code> ordnet Artikelnummern (Zeichenketten) ihre Stückpreise (Zahlen) zu. <code>neue_artikelnummer</code> ist noch nicht enthalten; <code>neuer_preis</code> ist der zugehörige Stückpreis.</p><p>Erwartet werden das ergänzte Dictionary <code>preise</code> und die ganze Zahl <code>artikelanzahl</code>. Alle bisherigen Einträge bleiben unverändert.</p><p>Ausgabe: zuerst der Preis des neuen Artikels, danach die Gesamtzahl der Artikel, jeweils in einer eigenen Zeile ohne Beschriftung. Eine feste Anzahl an Nachkommastellen ist nicht erforderlich.</p><p>Die Prüfung verwendet auch ein leeres Preisverzeichnis und einen neuen Preis von 0. Der Lösungscode steht unter dem INIT-Block.</p>',
    'init' => "preise = {\"A10\": 2.5, \"B20\": 4.0}\nneue_artikelnummer = \"C30\"\nneuer_preis = 1.75",
    'body' => "preise[neue_artikelnummer] = neuer_preis\nartikelanzahl = len(preise)\nprint(preise[neue_artikelnummer])\nprint(artikelanzahl)",
    'inputs' => ['preise', 'neue_artikelnummer', 'neuer_preis'],
    'outputs' => ['preise', 'artikelanzahl'],
    'randomizer' => "import random\n_anzahl = random.randint(0, 8)\nvalues = {\"preise\": {\"A\" + str(i): random.randint(1, 200) / 4 for i in range(_anzahl)}, \"neue_artikelnummer\": \"N\" + str(random.randint(10, 99)), \"neuer_preis\": random.randint(0, 200) / 4}",
    'fixed' => [
        [(object)[], 'NEU', 0],
        [['ALT' => 3.5, 'B' => 9], 'X', 2.25],
    ],
    'expected_fixed' => [
        [['NEU' => 0], 1],
        [['ALT' => 3.5, 'B' => 9, 'X' => 2.25], 3],
    ],
    'output_pattern' => '^1\\.750*\\s*\\r?\\n\\s*3$',
    'difficulty' => 'basic',
];
$tasks[] = [
    'title' => 'Daten organisieren: Raummessungen als Dictionaries',
    'task_text' => 'Überführe die Raummessungen aus raumdaten in die Liste messungen mit einem Dictionary je Raum. Gib Raum und Temperatur der zweiten Messung jeweils in einer eigenen Zeile aus. Der Code soll auch für andere Raumdaten funktionieren.',
    'description' => '<p>Jede Zeile in <code>raumdaten</code> enthält Raum, Temperatur und Luftfeuchtigkeit, in dieser Reihenfolge.</p><p>Erwartet wird die Liste <code>messungen</code> mit den Schlüsseln <code>raum</code>, <code>temperatur</code> und <code>luftfeuchtigkeit</code> je Dictionary. Werte und Reihenfolge bleiben erhalten, <code>raumdaten</code> bleibt unverändert.</p><p>Ausgabe: zuerst die Raumbezeichnung der zweiten Messung, danach ihre Temperatur, jeweils in einer eigenen Zeile ohne Beschriftung.</p><div class="test-requirements-section"><h3>Test-Anforderungen</h3><table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody><tr><td>INPUTS erwartet</td><td>1</td></tr><tr><td>Input-Variablen</td><td>raumdaten</td></tr><tr><td>Checking</td><td>messungen, raumdaten</td></tr><tr><td>OUTPUT</td><td>Regex Pattern</td></tr><tr><td>Prüfung</td><td>automatisch</td></tr></tbody></table></div>',
    'init' => 'raumdaten = [["A101", 21.5, 45], ["A102", 23.0, 51], ["B201", 19.5, 48]]',
    'body' => "messungen = []\nfor zeile in raumdaten:\n    messungen.append({\"raum\": zeile[0], \"temperatur\": zeile[1], \"luftfeuchtigkeit\": zeile[2]})\nprint(messungen[1][\"raum\"])\nprint(messungen[1][\"temperatur\"])",
    'inputs' => ['raumdaten'],
    'outputs' => ['messungen', 'raumdaten'],
    'randomizer' => "import random\nvalues = {\"raumdaten\": [[\"R\" + str(i + 100), random.randint(-20, 120) / 2, random.randint(0, 100)] for i in range(random.randint(2, 7))]}",
    'fixed' => [
        [[['K1', -2.5, 0], ['K2', 0, 100]]],
    ],
    'expected_fixed' => [
        [[['raum' => 'K1', 'temperatur' => -2.5, 'luftfeuchtigkeit' => 0], ['raum' => 'K2', 'temperatur' => 0, 'luftfeuchtigkeit' => 100]], [['K1', -2.5, 0], ['K2', 0, 100]]],
    ],
    'output_pattern' => '^A102\\s*\\r?\\n\\s*23(?:\\.0+)?$',
    'difficulty' => 'medium',
];
$tasks[] = [
    'title' => 'Dictionaries auswerten: Materialbedarf zusammenfassen',
    'task_text' => 'Fasse die bestellten Mengen aus positionen je Artikelnummer im Dictionary gesamtmengen zusammen. Gib je Artikel eine Zeile mit Artikelnummer und Gesamtmenge aus. Der Code soll auch für andere Bestellungen funktionieren.',
    'description' => '<p><code>positionen</code> enthält Dictionaries mit <code>artikelnummer</code> und <code>menge</code>. Artikelnummern können mehrfach vorkommen.</p><p>Erwartet wird das Dictionary <code>gesamtmengen</code>: Artikelnummer als Schlüssel, Summe der bestellten Mengen als Wert. Artikel mit Gesamtmenge 0 bleiben enthalten. <code>positionen</code> bleibt unverändert.</p><p>Ausgabe: je Artikel eine Zeile mit Artikelnummer und Gesamtmenge, durch Leerzeichen getrennt, ohne Beschriftung. Die Zeilenreihenfolge ist beliebig. Bei leerer Eingabe bleibt das Dictionary leer und es erfolgt keine Ausgabe.</p><div class="test-requirements-section"><h3>Test-Anforderungen</h3><table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody><tr><td>INPUTS erwartet</td><td>1</td></tr><tr><td>Input-Variablen</td><td>positionen</td></tr><tr><td>Checking</td><td>gesamtmengen, positionen</td></tr><tr><td>OUTPUT</td><td>Regex Pattern</td></tr><tr><td>Prüfung</td><td>automatisch</td></tr></tbody></table></div>',
    'init' => 'positionen = [{"artikelnummer": "A10", "menge": 3}, {"artikelnummer": "B20", "menge": 2}, {"artikelnummer": "A10", "menge": 4}, {"artikelnummer": "C30", "menge": 0}]',
    'body' => "gesamtmengen = {}\nfor position in positionen:\n    artikelnummer = position[\"artikelnummer\"]\n    gesamtmengen[artikelnummer] = gesamtmengen.get(artikelnummer, 0) + position[\"menge\"]\nfor artikelnummer, menge in gesamtmengen.items():\n    print(artikelnummer, menge)",
    'inputs' => ['positionen'],
    'outputs' => ['gesamtmengen', 'positionen'],
    'randomizer' => "import random\nvalues = {\"positionen\": [{\"artikelnummer\": random.choice([\"A10\", \"B20\", \"C30\", \"D40\"]), \"menge\": random.randint(0, 20)} for _ in range(random.randint(0, 16))]}",
    'fixed' => [
        [[]],
        [[['artikelnummer' => 'X', 'menge' => 0], ['artikelnummer' => 'Y', 'menge' => 2], ['artikelnummer' => 'X', 'menge' => 3], ['artikelnummer' => 'Z', 'menge' => 0]]],
    ],
    'expected_fixed' => [
        [(object)[], []],
        [['X' => 3, 'Y' => 2, 'Z' => 0], [['artikelnummer' => 'X', 'menge' => 0], ['artikelnummer' => 'Y', 'menge' => 2], ['artikelnummer' => 'X', 'menge' => 3], ['artikelnummer' => 'Z', 'menge' => 0]]],
    ],
    'output_pattern' => '^(?=[\\s\\S]*\\bA10[ \\t]+7\\b)(?=[\\s\\S]*\\bB20[ \\t]+2\\b)(?=[\\s\\S]*\\bC30[ \\t]+0\\b)(?:A10[ \\t]+7|B20[ \\t]+2|C30[ \\t]+0)(?:\\s*\\r?\\n\\s*(?:A10[ \\t]+7|B20[ \\t]+2|C30[ \\t]+0)){2}$',
    'difficulty' => 'medium',
];

foreach ($tasks as &$task) {
    $task['code_template'] = "#INIT START\n" . $task['init'] . "\n#INIT END\n\n";
    $task['solution_code'] = $task['code_template'] . $task['body'];
    $fixedCases = [];
    foreach ($task['fixed'] as $index => $inputs) {
        $fixedCases[] = ['init_values' => $inputs, 'expected_values' => $task['expected_fixed'][$index]];
    }
    $task['test_cases'] = [
        ['type' => 'intelligent', 'mode' => 'vars', 'tests' => 8,
            'inputs' => $task['inputs'], 'outputs' => $task['outputs'], 'value_comparison' => 'structural'],
        ['type' => 'variable', 'init_var_names' => $task['inputs'],
            'expected_var_names' => $task['outputs'], 'test_cases' => $fixedCases],
        ['type' => 'output', 'expected_type' => 'regex', 'expected' => $task['output_pattern'],
            'case_sensitive' => true, 'feedback' => 'Prüfe die verlangten Ausgabezeilen. Zusätzliche Beschriftungen sind nicht vorgesehen.'],
    ];
}
unset($task);

if (in_array('--definitions', $argv, true)) {
    echo json_encode($tasks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    exit;
}

define('BETA_LIVE_ALLOW_WRITE', true);
require_once __DIR__ . '/../config/database.beta_live.local.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = getBetaLiveDbConnection();
$assignmentId = 48;
$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT id FROM assignments WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new RuntimeException('Live assignment 48 does not exist');
    }
    $created = [];
    foreach ($tasks as $task) {
        $stmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ?');
        $stmt->bind_param('is', $assignmentId, $task['title']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if ($existing) {
            $created[] = ['action' => 'EXISTS', 'id' => (int)$existing['id'], 'title' => $task['title']];
            continue;
        }
        $position = (int)$conn->query('SELECT COALESCE(MAX(position), 0) + 1 AS next_position FROM tasks WHERE assignment_id = 48')->fetch_assoc()['next_position'];
        $tests = json_encode($task['test_cases'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = $conn->prepare(
            "INSERT INTO tasks (
                assignment_id, position, title, description, task_text, task_type,
                problem_type, task_difficulty, code_template, solution_code,
                randomizer_code, test_cases, max_attempts, show_solution,
                show_solution_code, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'code', 'code_completion', ?, ?, ?, ?, ?, 10, 1, 0, NOW(), NOW())"
        );
        $stmt->bind_param(
            'iissssssss',
            $assignmentId, $position, $task['title'], $task['description'], $task['task_text'],
            $task['difficulty'], $task['code_template'], $task['solution_code'], $task['randomizer'], $tests
        );
        $stmt->execute();
        $created[] = ['action' => 'CREATED', 'id' => (int)$conn->insert_id, 'title' => $task['title']];
    }
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}
echo json_encode($created, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
