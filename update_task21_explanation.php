<?php
require_once __DIR__ . '/config/database.php';

$pdo = getPdoConnection();

$title = 'MwSt-Rechner';
$taskText = 'Berechne aus Nettopreis und MwSt-Satz den MwSt-Betrag und den Bruttopreis und gib beide Werte korrekt aus.';
$questionText = '';
$description = <<<'DESC'
<div class="task-details">
    <h4>Details zur Aufgabe</h4>
    <ul>
        <li>Lies die Werte aus den HTML-Feldern mit <code>data-input="netto"</code> und <code>data-input="mwst"</code>.</li>
        <li>Wandle beide Eingaben in <code>float</code> um.</li>
        <li>Berechne <code>mwst_betrag = netto * (mwst / 100)</code>.</li>
        <li>Berechne <code>brutto = netto + mwst_betrag</code>.</li>
        <li>Schreibe die Ergebnisse in <code>data-output="result_netto"</code>, <code>result_mwst</code> und <code>result_brutto</code>.</li>
        <li>Formatiere Geldbeträge mit 2 Nachkommastellen.</li>
    </ul>
    <h4>Prüfmethode</h4>
    <p>Die automatischen Tests prüfen die korrekte Berechnung der Variablen und die erwartete Ausgabezuordnung.</p>
</div>
DESC;

$stoff = <<<'STOFF'
<div class="stoff-block">
    <h4>Stoff: Prozentrechnung und UI-Anbindung</h4>
    <ul>
        <li><strong>Prozentwert:</strong> <code>mwst_betrag = netto * (mwst / 100)</code></li>
        <li><strong>Endpreis:</strong> <code>brutto = netto + mwst_betrag</code></li>
        <li><strong>Datentypen:</strong> Eingaben aus HTML sind Text und müssen mit <code>float(...)</code> umgewandelt werden.</li>
        <li><strong>Mapping:</strong> Namen in <code>data-input</code>/<code>data-output</code> müssen exakt zu den Python-Aufrufen passen.</li>
    </ul>
    <p><em>Merksatz:</em> HTML sammelt Daten, Python rechnet und schreibt Ergebnisse zurück.</p>
</div>
STOFF;

$hint1 = 'Lies zuerst beide Eingaben aus dem HTML: ui.get_input_value("netto") und ui.get_input_value("mwst").';
$hint2 = 'Wandle die Texte in Zahlen um (float), berechne dann mwst_betrag und brutto mit den Formeln aus der Aufgabe.';
$hint3 = 'Schreibe alle Ergebnisse zurück ins HTML mit ui.set_output("result_netto"/"result_mwst"/"result_brutto", ... ) und nutze try/except ValueError für Fehlerfälle.';

$codeTemplate = <<<'CODE'
import idegui as ui

try:
    # 1) Eingaben aus HTML lesen
    # Tipp: Namen müssen zu data-input im HTML passen (netto, mwst)
    netto_text = ui.get_input_value("netto")
    mwst_text = ui.get_input_value("mwst")

    # 2) In Zahlen umwandeln
    netto = float(netto_text)
    mwst = float(mwst_text)

    # 3) Berechnen
    # mwst_betrag = ?
    # brutto = ?

    # 4) Ergebnisse ausgeben
    # ui.set_output("result_netto", ...)
    # ui.set_output("result_mwst", ...)
    # ui.set_output("result_brutto", ...)

    # Optional: vorherige Fehlermeldung löschen
    ui.set_output("result_error", "")

except ValueError:
    # 5) Fehlerausgabe bei ungültigen Eingaben
    ui.set_output("result_error", "Bitte gib gültige Zahlen ein.")
CODE;

$solutionCode = <<<'SOLUTION'
import idegui as ui

try:
    # 1) Eingaben aus HTML lesen
    netto_text = ui.get_input_value("netto")
    mwst_text = ui.get_input_value("mwst")

    # 2) In Zahlen umwandeln
    netto = float(netto_text)
    mwst = float(mwst_text)

    # 3) Berechnen
    mwst_betrag = netto * (mwst / 100)
    brutto = netto + mwst_betrag

    # 4) Ergebnisse anzeigen
    ui.set_output("result_netto", f"{netto:.2f} €")
    ui.set_output("result_mwst", f"{mwst_betrag:.2f} €")
    ui.set_output("result_brutto", f"{brutto:.2f} €")
    ui.set_output("result_error", "")

except ValueError:
    ui.set_output("result_error", "Bitte gib gültige Zahlen ein.")
SOLUTION;

$stmt = $pdo->prepare('UPDATE tasks SET title = ?, task_text = ?, question_text = ?, description = ?, stoff = ?, hint1 = ?, hint2 = ?, hint3 = ?, code_template = ?, solution_code = ?, updated_at = NOW() WHERE id = 21');
$ok = $stmt->execute([$title, $taskText, $questionText, $description, $stoff, $hint1, $hint2, $hint3, $codeTemplate, $solutionCode]);

if (!$ok) {
    echo "❌ Update fehlgeschlagen\n";
    exit(1);
}

echo "✅ Task #21 aktualisiert: task_text + stoff + hints + code_template + solution_code\n";
