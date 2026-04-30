<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$title = 'Haeufigkeit mit Klassen (C: mit Daten und Hinweisen)';

$code_template = <<<'PYTHON'
#INIT START
werte = [3, 7, 0, 9, 5, 2, 8, 1, 6, 4,
         12, 17, 10, 19, 15, 11, 18, 13, 16, 14,
         23, 28, 20, 29, 25, 22, 27, 24, 26, 21,
         33, 38, 30, 39, 35, 32, 37, 34, 36, 31,
         -3, -1, 45, 50, -5, 42, 9, 19, 29, 39]
#INIT END

haeufigkeit_klassen = {
    '0-9': 0,
    '10-19': 0,
    '20-29': 0,
    '30-39': 0,
    'ausserhalb': 0
}

# Ergaenze hier die Auswertung
for wert in werte:
    pass  # TODO: Klasse bestimmen und Zaehler erhoehen

print("Haeufigkeiten:")
for klasse, anzahl in haeufigkeit_klassen.items():
    print(f"  {klasse}: {anzahl}")
PYTHON;

$hint1 = <<<'TEXT'
Gehe jeden Wert aus der Liste durch. Prüfe mit if/elif, in welche Klasse er fällt, und erhöhe den passenden Zähler:

for wert in werte:
    if 0 <= wert <= 9:
        haeufigkeit_klassen['0-9'] += 1
    elif ...

Denke daran: Werte, die in keine Klasse passen, zählen zu 'ausserhalb'.
TEXT;

$hint2 = <<<'TEXT'
Die ersten zwei Klassen sehen so aus:

for wert in werte:
    if 0 <= wert <= 9:
        haeufigkeit_klassen['0-9'] += 1
    elif 10 <= wert <= 19:
        haeufigkeit_klassen['10-19'] += 1
    elif ...  # weiter so für 20-29 und 30-39

Ergänze die fehlenden elif-Zweige und den else-Zweig für 'ausserhalb'.
TEXT;

$hint3 = <<<'TEXT'
Alle vier Klassen sind bereits gefüllt – nur 'ausserhalb' fehlt noch:

for wert in werte:
    if 0 <= wert <= 9:
        haeufigkeit_klassen['0-9'] += 1
    elif 10 <= wert <= 19:
        haeufigkeit_klassen['10-19'] += 1
    elif 20 <= wert <= 29:
        haeufigkeit_klassen['20-29'] += 1
    elif 30 <= wert <= 39:
        haeufigkeit_klassen['30-39'] += 1
    else:
        ...  # Hier den Zähler für 'ausserhalb' erhöhen
TEXT;

$stmt = $pdo->prepare(
    "UPDATE tasks SET title=?, code_template=?, hint1=?, hint2=?, hint3=? WHERE id=319"
);
$stmt->execute([$title, $code_template, $hint1, $hint2, $hint3]);

echo "Updated task 319:\n";
echo "  Title:    $title\n";
echo "  Rows affected: " . $stmt->rowCount() . "\n";

// Verify
$check = $pdo->query("SELECT title, LEFT(code_template,80) AS tpl_preview, LEFT(hint1,60) AS h1, LEFT(hint2,60) AS h2, LEFT(hint3,60) AS h3 FROM tasks WHERE id=319")->fetch(PDO::FETCH_ASSOC);
echo "\nVerification:\n";
print_r($check);
