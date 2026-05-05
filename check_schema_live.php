<?php
/**
 * check_schema_live.php
 *
 * Vergleicht das lokale DB-Schema (XAMPP/Dev) mit dem Beta/Live-Schema.
 * Nutzt config/database.beta_live.local.php für read-only Live-Zugriff.
 *
 * Ausführung:
 *   php check_schema_live.php          – kompakter Diff
 *   php check_schema_live.php --full   – zeigt auch identische Tabellen
 *   php check_schema_live.php --table=user_assignments  – nur eine Tabelle
 *
 * NICHT für Deployment vorgesehen (check_* wird vom Deploy-Skript ausgeschlossen).
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database.beta_live.local.php';

// ── CLI-Optionen ───────────────────────────────────────────────────────────────
$opts     = getopt('', ['full', 'table:']);
$showFull = isset($opts['full']);
$onlyTable = isset($opts['table']) ? trim((string)$opts['table']) : null;

// ── Verbindungen ───────────────────────────────────────────────────────────────
$local = getDbConnection();
$live  = getBetaLiveDbConnection();

// ── Schema-Helfer ─────────────────────────────────────────────────────────────
function fetchTables(mysqli $conn): array {
    $res = $conn->query('SHOW TABLES');
    $tables = [];
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }
    sort($tables);
    return $tables;
}

function fetchColumns(mysqli $conn, string $table): array {
    $safe = $conn->real_escape_string($table);
    $res  = $conn->query("SHOW FULL COLUMNS FROM `{$safe}`");
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[$row['Field']] = [
            'type'    => $row['Type'],
            'null'    => $row['Null'],
            'default' => $row['Default'],
            'extra'   => $row['Extra'],
            'comment' => $row['Comment'] ?? '',
        ];
    }
    return $cols;
}

function fetchIndexes(mysqli $conn, string $table): array {
    $safe = $conn->real_escape_string($table);
    $res  = $conn->query("SHOW INDEX FROM `{$safe}`");
    $idx  = [];
    while ($row = $res->fetch_assoc()) {
        $name = $row['Key_name'];
        $idx[$name][] = $row['Column_name'];
    }
    ksort($idx);
    foreach ($idx as $name => $cols) sort($idx[$name]);
    return $idx;
}

// ── Ausgabe-Helfer ────────────────────────────────────────────────────────────
function line(string $s = ''): void { echo $s . PHP_EOL; }
function head(string $s): void { line(); line('━━━  ' . $s . '  ━━━'); }
function ok(string $s): void   { echo '  ✓  ' . $s . PHP_EOL; }
function add(string $s): void  { echo '  +  ' . $s . PHP_EOL; }
function del(string $s): void  { echo '  –  ' . $s . PHP_EOL; }
function chg(string $s): void  { echo '  ≠  ' . $s . PHP_EOL; }

// ── Haupt-Diff ────────────────────────────────────────────────────────────────
line('Schema-Abgleich: LOCAL vs LIVE');
line('Generated: ' . date('Y-m-d H:i:s'));
line('Local DB : ' . DB_NAME . ' @ ' . DB_HOST);
line('Live  DB : ' . BETA_LIVE_DB_NAME . ' @ ' . BETA_LIVE_DB_HOST);
line(str_repeat('─', 60));

$localTables = fetchTables($local);
$liveTables  = fetchTables($live);

if ($onlyTable !== null) {
    $localTables = in_array($onlyTable, $localTables, true) ? [$onlyTable] : [];
    $liveTables  = in_array($onlyTable, $liveTables,  true) ? [$onlyTable] : [];
    if (empty($localTables) && empty($liveTables)) {
        line("Tabelle '{$onlyTable}' weder lokal noch live vorhanden.");
        exit(1);
    }
}

$allTables = array_unique(array_merge($localTables, $liveTables));
sort($allTables);

$summary = ['only_local' => [], 'only_live' => [], 'schema_diff' => [], 'identical' => []];

foreach ($allTables as $table) {
    $inLocal = in_array($table, $localTables, true);
    $inLive  = in_array($table, $liveTables,  true);

    if ($inLocal && !$inLive) {
        $summary['only_local'][] = $table;
        continue;
    }
    if (!$inLocal && $inLive) {
        $summary['only_live'][] = $table;
        continue;
    }

    // Both have the table – compare columns and indexes
    $localCols = fetchColumns($local, $table);
    $liveCols  = fetchColumns($live,  $table);
    $localIdx  = fetchIndexes($local, $table);
    $liveIdx   = fetchIndexes($live,  $table);

    $allCols = array_unique(array_merge(array_keys($localCols), array_keys($liveCols)));
    sort($allCols);

    $diffs = [];

    foreach ($allCols as $col) {
        $hasLocal = array_key_exists($col, $localCols);
        $hasLive  = array_key_exists($col, $liveCols);

        if ($hasLocal && !$hasLive) {
            $diffs[] = "  + Spalte lokal vorhanden, live fehlt:  {$col}  ({$localCols[$col]['type']})";
        } elseif (!$hasLocal && $hasLive) {
            $diffs[] = "  – Spalte live vorhanden, lokal fehlt:  {$col}  ({$liveCols[$col]['type']})";
        } else {
            $lc = $localCols[$col];
            $rc = $liveCols[$col];
            $parts = [];
            if ($lc['type'] !== $rc['type']) {
                $parts[] = "type: [{$lc['type']}] vs [{$rc['type']}]";
            }
            if ($lc['null'] !== $rc['null']) {
                $parts[] = "null: [{$lc['null']}] vs [{$rc['null']}]";
            }
            if ((string)$lc['default'] !== (string)$rc['default']) {
                $parts[] = "default: [" . ($lc['default'] ?? 'NULL') . "] vs [" . ($rc['default'] ?? 'NULL') . "]";
            }
            if ($lc['extra'] !== $rc['extra']) {
                $parts[] = "extra: [{$lc['extra']}] vs [{$rc['extra']}]";
            }
            if ($parts) {
                $diffs[] = "  ≠ Spalte {$col}: " . implode(', ', $parts);
            }
        }
    }

    // Index diff
    $allIdx = array_unique(array_merge(array_keys($localIdx), array_keys($liveIdx)));
    sort($allIdx);
    foreach ($allIdx as $idxName) {
        $hasLocal = array_key_exists($idxName, $localIdx);
        $hasLive  = array_key_exists($idxName, $liveIdx);
        if ($hasLocal && !$hasLive) {
            $diffs[] = "  + Index lokal vorhanden, live fehlt:  {$idxName} (" . implode(', ', $localIdx[$idxName]) . ")";
        } elseif (!$hasLocal && $hasLive) {
            $diffs[] = "  – Index live vorhanden, lokal fehlt:  {$idxName} (" . implode(', ', $liveIdx[$idxName]) . ")";
        } elseif ($localIdx[$idxName] !== $liveIdx[$idxName]) {
            $diffs[] = "  ≠ Index {$idxName} Spalten weichen ab: [" . implode(',', $localIdx[$idxName]) . "] vs [" . implode(',', $liveIdx[$idxName]) . "]";
        }
    }

    if ($diffs) {
        $summary['schema_diff'][] = $table;
        head("DIFF  {$table}");
        foreach ($diffs as $d) { line($d); }
    } else {
        $summary['identical'][] = $table;
        if ($showFull) {
            ok("GLEICH  {$table}");
        }
    }
}

// ── Zusammenfassung ───────────────────────────────────────────────────────────
line();
line(str_repeat('═', 60));
line('ZUSAMMENFASSUNG');
line(str_repeat('─', 60));

if ($summary['only_local']) {
    line('Nur lokal vorhanden (' . count($summary['only_local']) . '):');
    foreach ($summary['only_local'] as $t) { add($t); }
}

if ($summary['only_live']) {
    line('Nur live vorhanden (' . count($summary['only_live']) . '):');
    foreach ($summary['only_live'] as $t) { del($t); }
}

if ($summary['schema_diff']) {
    line('Schema-Unterschiede (' . count($summary['schema_diff']) . '):');
    foreach ($summary['schema_diff'] as $t) { chg($t); }
}

$identicalCount = count($summary['identical']);
$totalTables    = count($allTables);
line("Identisch: {$identicalCount} / {$totalTables} Tabellen");

if (!$summary['only_local'] && !$summary['only_live'] && !$summary['schema_diff']) {
    line();
    line('✓ Kein Schema-Unterschied gefunden.');
} else {
    line();
    line('→ Tipp: Fehlende Live-Migrationen: sql/migrations/');
    line('  Zum Detail einer Tabelle: php check_schema_live.php --table=TABELLENNAME');
}
