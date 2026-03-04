<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getPdoConnection();

$solution21 = <<<'PY'
import idegui as ui

try:
    netto = float(ui.get("netto"))
    mwst = float(ui.get("mwst"))

    mwst_betrag = netto * (mwst / 100)
    brutto = netto + mwst_betrag

    ui.set("result_netto", f"{netto:.2f} €")
    ui.set("result_mwst", f"{mwst_betrag:.2f} €")
    ui.set("result_brutto", f"{brutto:.2f} €")
    ui.set("result_error", "")
except ValueError:
    ui.set("result_error", "Bitte gib gültige Zahlen ein.")
PY;

$solution169 = <<<'PY'
import idegui as ui

try:
    a = float(ui.get("a"))
    b = float(ui.get("b"))
    trigger = ui.get("__trigger__")

    if trigger == "plus":
        result = a + b
    elif trigger == "minus":
        result = a - b
    elif trigger == "mal":
        result = a * b
    elif trigger == "geteilt":
        if b == 0:
            raise ZeroDivisionError("Division durch 0 ist nicht erlaubt.")
        result = a / b
    else:
        ui.set("error", f"Unbekannter Trigger: {trigger}")
        raise ValueError("invalid trigger")

    ui.set("result", f"{result:.2f}")
    ui.set("error", "")
except ValueError:
    if ui.get("error") == "":
        ui.set("error", "Bitte gib gültige Zahlen ein.")
except ZeroDivisionError:
    ui.set("error", "Division durch 0 ist nicht erlaubt.")
PY;

$updates = [
    21 => $solution21,
    169 => $solution169,
];

$stmt = $pdo->prepare('UPDATE tasks SET solution_code = ?, updated_at = NOW() WHERE id = ? AND task_type = "code_ui"');

foreach ($updates as $taskId => $code) {
    $stmt->execute([$code, $taskId]);
    echo "Updated task {$taskId}\n";
}

echo "Done.\n";
