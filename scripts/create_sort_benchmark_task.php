<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$assignmentId = 29;
$title = 'Sortieralgorithmen vergleichen: Datensatz A und B';

function generateDatasetA(): array
{
    mt_srand(29042026);
    $values = range(1, 600);
    shuffle($values);
    return $values;
}

function generateDatasetB(): array
{
    // 1000 Werte, fast komplett sortiert und nur minimal durchmischt.
    // Genau 4 Vertauschungen halten den Datensatz sehr TimSort-freundlich,
    // ohne Quicksort (erstes Pivot) in einen extremen Worst Case zu druecken.
    $values = range(1, 1000);

    $swaps = [
        [0, 499],
        [120, 121],
        [700, 701],
        [900, 901],
    ];

    foreach ($swaps as [$a, $b]) {
        $tmp = $values[$a];
        $values[$a] = $values[$b];
        $values[$b] = $tmp;
    }

    return $values;
}

function renderDatasetModule(array $datasetA, array $datasetB): string
{
    $renderList = static function (string $name, array $values): array {
        $lines = [$name . ' = ['];
        foreach (array_chunk($values, 20) as $chunk) {
            $lines[] = '    ' . implode(', ', $chunk) . ',';
        }
        $lines[] = ']';
        $lines[] = '';
        return $lines;
    };

    $lines = [
        '# Zwei Datensaetze fuer den Sortiervergleich',
        '# Datensatz A: stark durchmischt',
        '# Datensatz B: 1000 fast sortierte Werte mit nur 4 Vertauschungen (minimal durchmischt)',
        '',
    ];

    $lines = array_merge($lines, $renderList('datensatz_a', $datasetA));
    $lines = array_merge($lines, $renderList('datensatz_b', $datasetB));

    return implode(PHP_EOL, $lines);
}

$description = <<<'HTML'
<div class="task-details">
    <p>Vergleiche fuenf Sortieralgorithmen auf zwei verschiedenen Datensaetzen aus <code>sortierdaten.py</code>.</p>
    <p><strong>Datensatz A</strong> enthaelt 600 stark durchmischte Werte. <strong>Datensatz B</strong> enthaelt 1000 fast sortierte Werte und ist nur minimal durchmischt (4 Vertauschungen).</p>
    <p>Implementiere die fuenf nummerierten Algorithmen. Verwende dabei diese Zuordnung: <strong>#1 Bubblesort</strong>, <strong>#2 Mergesort</strong>, <strong>#3 Insertionsort</strong>, <strong>#4 Quicksort</strong>, <strong>#5 Timsort</strong>.</p>
    <p>Zusätzlich soll <strong>Python-Sort</strong> mit der eingebauten Listenmethode automatisch <strong>ausser Konkurrenz</strong> ausgegeben werden. Diese Referenz zaehlt nicht fuer die Variablen mit den schnellsten und langsamsten Verfahren.</p>
    <p>Jede Funktion soll:</p>
  <ul>
    <li>eine Kopie der uebergebenen Liste sortieren,</li>
    <li>den Algorithmusnamen, die Anzahl der Elemente und die Durchfuehrungszeit in Millisekunden ausgeben,</li>
    <li>die sortierte Liste zurueckgeben.</li>
  </ul>
    <p>Rufe danach alle fuenf Algorithmen nacheinander fuer Datensatz A und Datensatz B auf. Gib ausserdem Python-Sort automatisch als Referenz aus, aber ohne diese Zeit in den Vergleich aufzunehmen. Vergleiche dann nur die fuenf nummerierten Algorithmen und setze diese Variablen. Als Antwort soll jeweils nur die passende <strong>Nummer</strong> des Algorithmus gespeichert werden, also 1, 2, 3, 4 oder 5:</p>
  <ul>
    <li><code>schnellster_A</code></li>
    <li><code>langsamster_A</code></li>
    <li><code>schnellster_B</code></li>
    <li><code>langsamster_B</code></li>
  </ul>
</div>
<div class="test-requirements-section"><h3>Test-Anforderungen</h3><table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody><tr><td>Checking</td><td>schnellster_A, langsamster_A, schnellster_B, langsamster_B</td></tr><tr><td>Keyword-Pruefung</td><td>aktiv</td></tr></tbody></table></div>
HTML;

$taskText = 'Vergleiche die Laufzeiten von fünf nummerierten Sortieralgorithmen auf zwei Datensaetzen, gib Python-Sort ausser Konkurrenz aus und speichere fuer A und B jeweils die Nummer des schnellsten und langsamsten Algorithmus.';

$stoff = <<<'HTML'
<h4>Vergleich von Sortieralgorithmen</h4>
<p>Die Laufzeit eines Sortieralgorithmus haengt nicht nur vom Verfahren selbst, sondern auch von der Struktur der Eingabedaten ab.</p>
<p>Stark durchmischte Daten koennen andere Laufzeiten erzeugen als Datensaetze mit vielen gleichen Werten oder bewusst unguenstig angeordneten Teilfolgen.</p>
<p>Durch Zeitmessungen auf mehreren Datensaetzen lassen sich Unterschiede zwischen einfachen und optimierten Sortierverfahren sichtbar machen.</p>
HTML;

$codeTemplate = <<<'PYTHON'
#INIT START
from sortierdaten import datensatz_a, datensatz_b
#INIT END

import time

letzte_laufzeiten = {}

#1 Bubblesort

def bubblesort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    for i in range(len(daten) - 1):
        for j in range(len(daten) - 1 - i):
            if daten[j] > daten[j + 1]:
                hilfe = daten[j]
                daten[j] = daten[j + 1]
                daten[j + 1] = hilfe

    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Bubblesort'] = dauer_ms
    print(f"Bubblesort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

#2 Mergesort

def mergesort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    def merge_sort_intern(liste):
        if len(liste) <= 1:
            return liste

        mitte = len(liste) // 2
        links = merge_sort_intern(liste[:mitte])
        rechts = merge_sort_intern(liste[mitte:])
        return merge(links, rechts)

    def merge(links, rechts):
        ergebnis = []
        i = 0
        j = 0

        while i < len(links) and j < len(rechts):
            if links[i] <= rechts[j]:
                ergebnis.append(links[i])
                i += 1
            else:
                ergebnis.append(rechts[j])
                j += 1

        ergebnis.extend(links[i:])
        ergebnis.extend(rechts[j:])
        return ergebnis

    daten = merge_sort_intern(daten)

    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Mergesort'] = dauer_ms
    print(f"Mergesort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

#3 Insertionsort

def insertionsort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    for i in range(1, len(daten)):
        aktueller_wert = daten[i]
        j = i - 1

        while j >= 0 and daten[j] > aktueller_wert:
            daten[j + 1] = daten[j]
            j -= 1

        daten[j + 1] = aktueller_wert

    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Insertionsort'] = dauer_ms
    print(f"Insertionsort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

#4 Quicksort

def quicksort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    def quicksort_intern(liste):
        if len(liste) <= 1:
            return liste

        pivot = liste[0]
        kleiner = []
        gleich = []
        groesser = []

        for wert in liste:
            if wert < pivot:
                kleiner.append(wert)
            elif wert > pivot:
                groesser.append(wert)
            else:
                gleich.append(wert)

        return quicksort_intern(kleiner) + gleich + quicksort_intern(groesser)

    daten = quicksort_intern(daten)

    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Quicksort'] = dauer_ms
    print(f"Quicksort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

#5 Timsort

def timsort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    min_run = 32

    def insertion_sort_bereich(liste, links, rechts):
        for i in range(links + 1, rechts + 1):
            aktueller_wert = liste[i]
            j = i - 1

            while j >= links and liste[j] > aktueller_wert:
                liste[j + 1] = liste[j]
                j -= 1

            liste[j + 1] = aktueller_wert

    def merge_bereiche(liste, links, mitte, rechts):
        links_teil = liste[links:mitte + 1]
        rechts_teil = liste[mitte + 1:rechts + 1]

        i = 0
        j = 0
        k = links

        while i < len(links_teil) and j < len(rechts_teil):
            if links_teil[i] <= rechts_teil[j]:
                liste[k] = links_teil[i]
                i += 1
            else:
                liste[k] = rechts_teil[j]
                j += 1
            k += 1

        while i < len(links_teil):
            liste[k] = links_teil[i]
            i += 1
            k += 1

        while j < len(rechts_teil):
            liste[k] = rechts_teil[j]
            j += 1
            k += 1

    n = len(daten)

    for start_index in range(0, n, min_run):
        ende_index = min(start_index + min_run - 1, n - 1)
        insertion_sort_bereich(daten, start_index, ende_index)

    groesse = min_run
    while groesse < n:
        for links in range(0, n, 2 * groesse):
            mitte = min(n - 1, links + groesse - 1)
            rechts = min((links + 2 * groesse - 1), (n - 1))

            if mitte < rechts:
                merge_bereiche(daten, links, mitte, rechts)

        groesse *= 2

    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Timsort'] = dauer_ms
    print(f"Timsort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

def python_sort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    daten.sort()

    dauer_ms = (time.perf_counter() - start) * 1000
    print(f"Python-Sort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

zeiten_a = {}
zeiten_b = {}

algorithmus_nummern = {
    'Bubblesort': 1,
    'Mergesort': 2,
    'Insertionsort': 3,
    'Quicksort': 4,
    'Timsort': 5
}

# Datensatz A vergleichen
bubblesort(datensatz_a)
zeiten_a['Bubblesort'] = letzte_laufzeiten['Bubblesort']
mergesort(datensatz_a)
zeiten_a['Mergesort'] = letzte_laufzeiten['Mergesort']
insertionsort(datensatz_a)
zeiten_a['Insertionsort'] = letzte_laufzeiten['Insertionsort']
quicksort(datensatz_a)
zeiten_a['Quicksort'] = letzte_laufzeiten['Quicksort']
timsort(datensatz_a)
zeiten_a['Timsort'] = letzte_laufzeiten['Timsort']
python_sort(datensatz_a)

# Datensatz B vergleichen
bubblesort(datensatz_b)
zeiten_b['Bubblesort'] = letzte_laufzeiten['Bubblesort']
mergesort(datensatz_b)
zeiten_b['Mergesort'] = letzte_laufzeiten['Mergesort']
insertionsort(datensatz_b)
zeiten_b['Insertionsort'] = letzte_laufzeiten['Insertionsort']
quicksort(datensatz_b)
zeiten_b['Quicksort'] = letzte_laufzeiten['Quicksort']
timsort(datensatz_b)
zeiten_b['Timsort'] = letzte_laufzeiten['Timsort']
python_sort(datensatz_b)

schnellster_A = 0
langsamster_A = 0
schnellster_B = 0
langsamster_B = 0

schnellster_A = algorithmus_nummern[min(zeiten_a, key=zeiten_a.get)]
langsamster_A = algorithmus_nummern[max(zeiten_a, key=zeiten_a.get)]
schnellster_B = algorithmus_nummern[min(zeiten_b, key=zeiten_b.get)]
langsamster_B = algorithmus_nummern[max(zeiten_b, key=zeiten_b.get)]
PYTHON;

$solutionCode = <<<'PYTHON'
from sortierdaten import datensatz_a, datensatz_b
import time

letzte_laufzeiten = {}

#1 Bubblesort

def bubblesort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    for i in range(len(daten) - 1):
        for j in range(len(daten) - 1 - i):
            if daten[j] > daten[j + 1]:
                hilfe = daten[j]
                daten[j] = daten[j + 1]
                daten[j + 1] = hilfe

    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Bubblesort'] = dauer_ms
    print(f"Bubblesort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

#2 Mergesort

def mergesort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    def merge_sort_intern(liste):
        if len(liste) <= 1:
            return liste

        mitte = len(liste) // 2
        links = merge_sort_intern(liste[:mitte])
        rechts = merge_sort_intern(liste[mitte:])
        return merge(links, rechts)

    def merge(links, rechts):
        ergebnis = []
        i = 0
        j = 0

        while i < len(links) and j < len(rechts):
            if links[i] <= rechts[j]:
                ergebnis.append(links[i])
                i += 1
            else:
                ergebnis.append(rechts[j])
                j += 1

        ergebnis.extend(links[i:])
        ergebnis.extend(rechts[j:])
        return ergebnis

    daten = merge_sort_intern(daten)
    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Mergesort'] = dauer_ms
    print(f"Mergesort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

#3 Insertionsort

def insertionsort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    for i in range(1, len(daten)):
        aktueller_wert = daten[i]
        j = i - 1

        while j >= 0 and daten[j] > aktueller_wert:
            daten[j + 1] = daten[j]
            j -= 1

        daten[j + 1] = aktueller_wert

    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Insertionsort'] = dauer_ms
    print(f"Insertionsort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

#4 Quicksort

def quicksort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    def quicksort_intern(liste):
        if len(liste) <= 1:
            return liste

        pivot = liste[0]
        kleiner = []
        gleich = []
        groesser = []

        for wert in liste:
            if wert < pivot:
                kleiner.append(wert)
            elif wert > pivot:
                groesser.append(wert)
            else:
                gleich.append(wert)

        return quicksort_intern(kleiner) + gleich + quicksort_intern(groesser)

    daten = quicksort_intern(daten)
    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Quicksort'] = dauer_ms
    print(f"Quicksort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

#5 Timsort

def timsort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    min_run = 32

    def insertion_sort_bereich(liste, links, rechts):
        for i in range(links + 1, rechts + 1):
            aktueller_wert = liste[i]
            j = i - 1

            while j >= links and liste[j] > aktueller_wert:
                liste[j + 1] = liste[j]
                j -= 1

            liste[j + 1] = aktueller_wert

    def merge_bereiche(liste, links, mitte, rechts):
        links_teil = liste[links:mitte + 1]
        rechts_teil = liste[mitte + 1:rechts + 1]

        i = 0
        j = 0
        k = links

        while i < len(links_teil) and j < len(rechts_teil):
            if links_teil[i] <= rechts_teil[j]:
                liste[k] = links_teil[i]
                i += 1
            else:
                liste[k] = rechts_teil[j]
                j += 1
            k += 1

        while i < len(links_teil):
            liste[k] = links_teil[i]
            i += 1
            k += 1

        while j < len(rechts_teil):
            liste[k] = rechts_teil[j]
            j += 1
            k += 1

    n = len(daten)

    for start_index in range(0, n, min_run):
        ende_index = min(start_index + min_run - 1, n - 1)
        insertion_sort_bereich(daten, start_index, ende_index)

    groesse = min_run
    while groesse < n:
        for links in range(0, n, 2 * groesse):
            mitte = min(n - 1, links + groesse - 1)
            rechts = min((links + 2 * groesse - 1), (n - 1))

            if mitte < rechts:
                merge_bereiche(daten, links, mitte, rechts)

        groesse *= 2

    dauer_ms = (time.perf_counter() - start) * 1000
    letzte_laufzeiten['Timsort'] = dauer_ms
    print(f"Timsort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

def python_sort(zahlen):
    daten = zahlen[:]
    start = time.perf_counter()

    daten.sort()

    dauer_ms = (time.perf_counter() - start) * 1000
    print(f"Python-Sort | {len(daten)} Elemente | {dauer_ms:.3f} ms")
    return daten

zeiten_a = {}
zeiten_b = {}

algorithmus_nummern = {
    'Bubblesort': 1,
    'Mergesort': 2,
    'Insertionsort': 3,
    'Quicksort': 4,
    'Timsort': 5
}

# Datensatz A vergleichen
bubblesort(datensatz_a)
zeiten_a['Bubblesort'] = letzte_laufzeiten['Bubblesort']
mergesort(datensatz_a)
zeiten_a['Mergesort'] = letzte_laufzeiten['Mergesort']
insertionsort(datensatz_a)
zeiten_a['Insertionsort'] = letzte_laufzeiten['Insertionsort']
quicksort(datensatz_a)
zeiten_a['Quicksort'] = letzte_laufzeiten['Quicksort']
timsort(datensatz_a)
zeiten_a['Timsort'] = letzte_laufzeiten['Timsort']
python_sort(datensatz_a)

# Datensatz B vergleichen
bubblesort(datensatz_b)
zeiten_b['Bubblesort'] = letzte_laufzeiten['Bubblesort']
mergesort(datensatz_b)
zeiten_b['Mergesort'] = letzte_laufzeiten['Mergesort']
insertionsort(datensatz_b)
zeiten_b['Insertionsort'] = letzte_laufzeiten['Insertionsort']
quicksort(datensatz_b)
zeiten_b['Quicksort'] = letzte_laufzeiten['Quicksort']
timsort(datensatz_b)
zeiten_b['Timsort'] = letzte_laufzeiten['Timsort']
python_sort(datensatz_b)

schnellster_A = algorithmus_nummern[min(zeiten_a, key=zeiten_a.get)]
langsamster_A = algorithmus_nummern[max(zeiten_a, key=zeiten_a.get)]
schnellster_B = algorithmus_nummern[min(zeiten_b, key=zeiten_b.get)]
langsamster_B = algorithmus_nummern[max(zeiten_b, key=zeiten_b.get)]
PYTHON;

$hint1 = 'Miss die Zeit in jeder Funktion mit `start = time.perf_counter()` und berechne danach die Dauer in Millisekunden.';
$hint2 = 'Arbeite in jeder Funktion mit einer Kopie der Liste (`daten = zahlen[:]`), damit die Originaldaten fuer die anderen Algorithmen erhalten bleiben. Python-Sort wird nur als Referenz ausgegeben und nicht mitgezaehlt.';
$hint3 = 'Timsort ist hier eine Mischung aus Insertion Sort und Merge Sort: erst kurze Runs per Insertion Sort sortieren, dann die Runs iterativ mergen. In `timsort` darf keine direkte `.sort()`-Sortierung passieren.';

$testCases = [
    [
        'type' => 'variable',
        'init_vars' => [],
        'expected_vars' => [
            'schnellster_A' => 4,
            'langsamster_A' => 1,
            'schnellster_B' => 3,
            'langsamster_B' => 1,
        ],
    ],
    [
        'type' => 'code_check',
        'keywords' => ['def bubblesort', 'def mergesort', 'def insertionsort', 'def quicksort', 'def timsort', 'def python_sort', 'min_run', 'insertion_sort_bereich', 'merge_bereiche', 'print(', 'time.perf_counter'],
        'forbidden' => ['sorted('],
        'operator' => 'AND',
        'feedback' => '',
    ],
];

$datasetA = generateDatasetA();
$datasetB = generateDatasetB();

$pdo = getPdoConnection();
$selectStmt = $pdo->prepare('SELECT id, position FROM tasks WHERE assignment_id = :assignment_id AND title = :title LIMIT 1');
$selectStmt->execute([
    ':assignment_id' => $assignmentId,
    ':title' => $title,
]);
$existingTask = $selectStmt->fetch(PDO::FETCH_ASSOC);

if ($existingTask) {
    $taskId = (int)$existingTask['id'];
    $position = (int)$existingTask['position'];
    $sql = <<<'SQL'
UPDATE tasks
SET title = :title,
    description = :description,
    position = :position,
    max_attempts = :max_attempts,
    iterations_count = :iterations_count,
    show_solution = :show_solution,
    show_solution_code = :show_solution_code,
    min_keywords_required = :min_keywords_required,
    problem_type = :problem_type,
    code_template = :code_template,
    hint1 = :hint1,
    hint2 = :hint2,
    hint3 = :hint3,
    stoff = :stoff,
    expected_output = :expected_output,
    test_cases = :test_cases,
    solution_code = :solution_code,
    task_type = :task_type,
    task_text = :task_text,
    question_text = :question_text,
    image_url = :image_url,
    correct_answer = :correct_answer,
    variable_overrides = :variable_overrides,
    randomizer_code = :randomizer_code,
    folderstructure = :folderstructure,
    allowDownload = :allow_download,
    allow_code_ui_web_edit = :allow_code_ui_web_edit,
    task_difficulty = :task_difficulty
WHERE id = :id
SQL;
    $stmt = $pdo->prepare($sql);
} else {
    $positionStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_position FROM tasks WHERE assignment_id = :assignment_id');
    $positionStmt->execute([':assignment_id' => $assignmentId]);
    $position = (int)$positionStmt->fetchColumn();
    $sql = <<<'SQL'
INSERT INTO tasks (
    assignment_id, title, description, position, max_attempts, iterations_count,
    show_solution, show_solution_code, min_keywords_required, problem_type,
    code_template, hint1, hint2, hint3, stoff, expected_output, test_cases,
    solution_code, task_type, task_text, question_text, image_url,
    correct_answer, variable_overrides, randomizer_code, folderstructure,
    allowDownload, allow_code_ui_web_edit, task_difficulty
) VALUES (
    :assignment_id, :title, :description, :position, :max_attempts, :iterations_count,
    :show_solution, :show_solution_code, :min_keywords_required, :problem_type,
    :code_template, :hint1, :hint2, :hint3, :stoff, :expected_output, :test_cases,
    :solution_code, :task_type, :task_text, :question_text, :image_url,
    :correct_answer, :variable_overrides, :randomizer_code, :folderstructure,
    :allow_download, :allow_code_ui_web_edit, :task_difficulty
)
SQL;
    $stmt = $pdo->prepare($sql);
}

$params = [
    ':assignment_id' => $assignmentId,
    ':title' => $title,
    ':description' => $description,
    ':position' => $position,
    ':max_attempts' => 10,
    ':iterations_count' => 1,
    ':show_solution' => 1,
    ':show_solution_code' => 0,
    ':min_keywords_required' => null,
    ':problem_type' => 'code_completion',
    ':code_template' => $codeTemplate,
    ':hint1' => $hint1,
    ':hint2' => $hint2,
    ':hint3' => $hint3,
    ':stoff' => $stoff,
    ':expected_output' => '',
    ':test_cases' => json_encode($testCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ':solution_code' => $solutionCode,
    ':task_type' => 'code',
    ':task_text' => $taskText,
    ':question_text' => '',
    ':image_url' => null,
    ':correct_answer' => null,
    ':variable_overrides' => null,
    ':randomizer_code' => null,
    ':folderstructure' => 1,
    ':allow_download' => 0,
    ':allow_code_ui_web_edit' => 1,
    ':task_difficulty' => 'hard',
];

if ($existingTask) {
    unset($params[':assignment_id']);
    $params[':id'] = $taskId;
}

$stmt->execute($params);

if (!$existingTask) {
    $taskId = (int)$pdo->lastInsertId();
}

$taskFolder = __DIR__ . '/../storage/tasks/folders/task_' . $taskId;
if (!is_dir($taskFolder) && !mkdir($taskFolder, 0755, true) && !is_dir($taskFolder)) {
    throw new RuntimeException('Task folder could not be created: ' . $taskFolder);
}

file_put_contents($taskFolder . '/sortierdaten.py', renderDatasetModule($datasetA, $datasetB));
file_put_contents(
    $taskFolder . '/.file-policies.json',
    json_encode([
        'files' => [
            'sortierdaten.py' => ['read_only' => true],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'Task ID: ' . $taskId . PHP_EOL;
echo 'Assignment ID: ' . $assignmentId . PHP_EOL;
echo 'Position: ' . $position . PHP_EOL;
echo 'Folder: ' . $taskFolder . PHP_EOL;
echo 'Dataset A size: ' . count($datasetA) . PHP_EOL;
echo 'Dataset B size: ' . count($datasetB) . PHP_EOL;
echo 'Mode: ' . ($existingTask ? 'updated' : 'created') . PHP_EOL;
