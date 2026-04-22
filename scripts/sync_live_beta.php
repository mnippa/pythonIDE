<?php
/**
 * Sync helper for parallel live/beta deployments.
 *
 * Usage examples:
 *   php scripts/sync_live_beta.php --mode=hydrate-beta
 *   php scripts/sync_live_beta.php --mode=hydrate-beta --sync-db=1
 *   php scripts/sync_live_beta.php --mode=promote-live
 *   php scripts/sync_live_beta.php --mode=promote-live --dry-run=1
 *
 * Modes:
 *   hydrate-beta:
 *     Copy persistent/runtime data from LIVE -> BETA.
 *     Optional DB snapshot copy from LIVE DB -> BETA DB.
 *
 *   promote-live:
 *     Copy changed app files from BETA -> LIVE while preserving
 *     persistent/runtime data on LIVE.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI mode.\n");
    exit(1);
}

$opts = getopt('', [
    'mode:',
    'live-root::',
    'beta-root::',
    'dry-run::',
    'sync-db::',
    'delete::',
]);

$mode = (string)($opts['mode'] ?? '');
if ($mode === '') {
    usageAndExit(1, "Missing --mode");
}

$projectRoot = normalizePath(dirname(__DIR__));
$defaultLiveRoot = $projectRoot;
$defaultBetaRoot = normalizePath(dirname($projectRoot) . DIRECTORY_SEPARATOR . 'pythonIDEBeta');

$liveRoot = normalizePath((string)($opts['live-root'] ?? $defaultLiveRoot));
$betaRoot = normalizePath((string)($opts['beta-root'] ?? $defaultBetaRoot));

$dryRun = toBool($opts['dry-run'] ?? false);
$syncDb = toBool($opts['sync-db'] ?? false);
$delete = toBool($opts['delete'] ?? false);

if (!is_dir($liveRoot)) {
    fwrite(STDERR, "LIVE root does not exist: {$liveRoot}\n");
    exit(1);
}
if (!is_dir($betaRoot)) {
    fwrite(STDERR, "BETA root does not exist: {$betaRoot}\n");
    exit(1);
}
if (realpath($liveRoot) === realpath($betaRoot)) {
    fwrite(STDERR, "LIVE and BETA root must be different directories.\n");
    exit(1);
}

echo "Mode      : {$mode}\n";
echo "LIVE root : {$liveRoot}\n";
echo "BETA root : {$betaRoot}\n";
echo "Dry-run   : " . ($dryRun ? 'yes' : 'no') . "\n";
echo "Sync DB   : " . ($syncDb ? 'yes' : 'no') . "\n";
echo "Delete    : " . ($delete ? 'yes' : 'no') . "\n\n";

try {
    if ($mode === 'hydrate-beta') {
        hydrateBeta($liveRoot, $betaRoot, $dryRun, $syncDb);
    } elseif ($mode === 'promote-live') {
        promoteLive($betaRoot, $liveRoot, $dryRun, $delete);
    } else {
        usageAndExit(1, "Unknown mode: {$mode}");
    }

    echo "\nDone.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nERROR: " . $e->getMessage() . "\n");
    exit(1);
}

function hydrateBeta(string $liveRoot, string $betaRoot, bool $dryRun, bool $syncDb): void
{
    echo "[1/2] Sync persistent/config files LIVE -> BETA\n";

    $items = [
        'config/database.php',
        'config/local.php',
        '.env',
        '.env.local',
        '.env.production',
        'storage',
        'public/uploads',
    ];

    $stats = [
        'copiedFiles' => 0,
        'createdDirs' => 0,
        'skippedSame' => 0,
        'missingSource' => 0,
    ];

    foreach ($items as $relPath) {
        $src = joinPath($liveRoot, $relPath);
        $dst = joinPath($betaRoot, $relPath);

        if (!file_exists($src)) {
            $stats['missingSource']++;
            echo "  - missing source, skip: {$relPath}\n";
            continue;
        }

        if (is_dir($src)) {
            syncDirectory($src, $dst, $dryRun, $stats, []);
        } else {
            copyOneFile($src, $dst, $dryRun, $stats);
        }
    }

    printStats($stats);

    if ($syncDb) {
        echo "\n[2/2] Sync database LIVE -> BETA (snapshot)\n";
        syncDatabaseSnapshot($liveRoot, $betaRoot, $dryRun);
    } else {
        echo "\n[2/2] DB sync skipped (use --sync-db=1 to enable)\n";
    }
}

function promoteLive(string $betaRoot, string $liveRoot, bool $dryRun, bool $delete): void
{
    echo "[1/1] Promote changed app files BETA -> LIVE\n";

    $excludePrefixes = [
        '.git/',
        '.github/',
        'node_modules/',
        'vendor/',
        'storage/',
        'public/uploads/',
        'config/database.php',
        'config/local.php',
        '.env',
        '.env.local',
        '.env.production',
    ];

    $stats = [
        'copiedFiles' => 0,
        'createdDirs' => 0,
        'skippedSame' => 0,
        'missingSource' => 0,
        'deletedFiles' => 0,
        'deletedDirs' => 0,
    ];

    syncDirectory($betaRoot, $liveRoot, $dryRun, $stats, $excludePrefixes, true, $delete);
    printStats($stats);
}

/**
 * Sync one directory recursively.
 *
 * @param string[] $excludePrefixes Prefixes relative to sync root, using '/'.
 */
function syncDirectory(
    string $srcDir,
    string $dstDir,
    bool $dryRun,
    array &$stats,
    array $excludePrefixes,
    bool $rootMode = false,
    bool $delete = false
): void {
    if (!is_dir($srcDir)) {
        $stats['missingSource']++;
        return;
    }

    $syncRoot = $srcDir;
    $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, $flags),
        RecursiveIteratorIterator::SELF_FIRST
    );

    if (!is_dir($dstDir)) {
        if (!$dryRun) {
            if (!mkdir($dstDir, 0775, true) && !is_dir($dstDir)) {
                throw new RuntimeException("Failed to create directory: {$dstDir}");
            }
        }
        $stats['createdDirs']++;
    }

    /** @var SplFileInfo $entry */
    foreach ($it as $entry) {
        $srcPath = normalizePath($entry->getPathname());
        $relPath = ltrim(str_replace('\\', '/', substr($srcPath, strlen($syncRoot))), '/');

        if ($relPath === '') {
            continue;
        }
        if (isExcluded($relPath, $excludePrefixes)) {
            continue;
        }

        $dstPath = joinPath($dstDir, $relPath);

        if ($entry->isDir()) {
            if (!is_dir($dstPath)) {
                if (!$dryRun) {
                    if (!mkdir($dstPath, 0775, true) && !is_dir($dstPath)) {
                        throw new RuntimeException("Failed to create directory: {$dstPath}");
                    }
                }
                $stats['createdDirs']++;
            }
            continue;
        }

        if (!$entry->isFile()) {
            continue;
        }

        copyOneFile($srcPath, $dstPath, $dryRun, $stats);
    }

    if ($delete) {
        deleteExtraEntries($srcDir, $dstDir, $dryRun, $stats, $excludePrefixes);
    }
}

function deleteExtraEntries(string $srcDir, string $dstDir, bool $dryRun, array &$stats, array $excludePrefixes): void
{
    if (!is_dir($dstDir)) {
        return;
    }

    $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dstDir, $flags),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    /** @var SplFileInfo $entry */
    foreach ($it as $entry) {
        $dstPath = normalizePath($entry->getPathname());
        $relPath = ltrim(str_replace('\\', '/', substr($dstPath, strlen($dstDir))), '/');
        if ($relPath === '') {
            continue;
        }
        if (isExcluded($relPath, $excludePrefixes)) {
            continue;
        }

        $srcPath = joinPath($srcDir, $relPath);
        if (file_exists($srcPath)) {
            continue;
        }

        if ($entry->isDir()) {
            if (!$dryRun) {
                @rmdir($dstPath);
            }
            $stats['deletedDirs']++;
        } elseif ($entry->isFile()) {
            if (!$dryRun) {
                @unlink($dstPath);
            }
            $stats['deletedFiles']++;
        }
    }
}

function copyOneFile(string $src, string $dst, bool $dryRun, array &$stats): void
{
    $srcHash = sha1_file($src);
    $dstExists = is_file($dst);
    $dstHash = $dstExists ? sha1_file($dst) : null;

    if ($dstExists && $srcHash === $dstHash) {
        $stats['skippedSame']++;
        return;
    }

    $dstDir = dirname($dst);
    if (!is_dir($dstDir)) {
        if (!$dryRun) {
            if (!mkdir($dstDir, 0775, true) && !is_dir($dstDir)) {
                throw new RuntimeException("Failed to create directory: {$dstDir}");
            }
        }
        $stats['createdDirs']++;
    }

    if (!$dryRun) {
        if (!copy($src, $dst)) {
            throw new RuntimeException("Failed to copy file: {$src} -> {$dst}");
        }
    }

    $stats['copiedFiles']++;
}

function syncDatabaseSnapshot(string $liveRoot, string $betaRoot, bool $dryRun): void
{
    $liveCfg = parseDbConfig(joinPath($liveRoot, 'config/database.php'));
    $betaCfg = parseDbConfig(joinPath($betaRoot, 'config/database.php'));

    if (!$liveCfg || !$betaCfg) {
        throw new RuntimeException('Could not parse DB config for live and/or beta.');
    }

    if (($liveCfg['name'] ?? '') === '' || ($betaCfg['name'] ?? '') === '') {
        throw new RuntimeException('DB name missing in parsed config.');
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'pyide_db_sync_');
    if ($tmpFile === false) {
        throw new RuntimeException('Could not create temporary file for DB dump.');
    }

    try {
        $dumpCmd = buildMysqlDumpCommand($liveCfg, $tmpFile);
        $importCmd = buildMysqlImportCommand($betaCfg, $tmpFile);

        if ($dryRun) {
            echo "  DRY-RUN dump cmd  : {$dumpCmd}\n";
            echo "  DRY-RUN import cmd: {$importCmd}\n";
            return;
        }

        runShellCommand($dumpCmd, 'mysqldump failed');
        runShellCommand($importCmd, 'mysql import failed');

        echo "  DB snapshot copied: {$liveCfg['name']} -> {$betaCfg['name']}\n";
    } finally {
        @unlink($tmpFile);
    }
}

/**
 * Parse DB constants from config/database.php.
 * Expects define('DB_HOST', '...') style.
 */
function parseDbConfig(string $filePath): ?array
{
    if (!is_file($filePath)) {
        return null;
    }

    $content = (string)file_get_contents($filePath);
    if ($content === '') {
        return null;
    }

    $map = [
        'DB_HOST' => 'host',
        'DB_USER' => 'user',
        'DB_PASS' => 'pass',
        'DB_NAME' => 'name',
    ];

    $result = [
        'host' => 'localhost',
        'user' => '',
        'pass' => '',
        'name' => '',
    ];

    foreach ($map as $const => $key) {
        $pattern = '/define\s*\(\s*["\']' . preg_quote($const, '/') . '["\']\s*,\s*["\']([^"\']*)["\']\s*\)/';
        if (preg_match($pattern, $content, $m)) {
            $result[$key] = $m[1];
        }
    }

    return $result;
}

function buildMysqlDumpCommand(array $cfg, string $outFile): string
{
    $host = escapeshellarg((string)$cfg['host']);
    $user = escapeshellarg((string)$cfg['user']);
    $dbName = escapeshellarg((string)$cfg['name']);
    $out = escapeshellarg($outFile);
    $pass = (string)$cfg['pass'];

    $envPrefix = 'MYSQL_PWD=' . escapeshellarg($pass) . ' ';
    return $envPrefix
        . "mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 --host={$host} --user={$user} {$dbName} > {$out}";
}

function buildMysqlImportCommand(array $cfg, string $inFile): string
{
    $host = escapeshellarg((string)$cfg['host']);
    $user = escapeshellarg((string)$cfg['user']);
    $dbName = escapeshellarg((string)$cfg['name']);
    $in = escapeshellarg($inFile);
    $pass = (string)$cfg['pass'];

    $envPrefix = 'MYSQL_PWD=' . escapeshellarg($pass) . ' ';
    return $envPrefix
        . "mysql --default-character-set=utf8mb4 --host={$host} --user={$user} {$dbName} < {$in}";
}

function runShellCommand(string $cmd, string $errorPrefix): void
{
    $output = [];
    $exitCode = 0;
    exec($cmd . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException($errorPrefix . " (exit {$exitCode})\n" . implode("\n", $output));
    }
}

function isExcluded(string $relativePath, array $excludePrefixes): bool
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    foreach ($excludePrefixes as $prefix) {
        $normPrefix = ltrim(str_replace('\\', '/', $prefix), '/');
        if ($normPrefix === '') {
            continue;
        }
        if ($relativePath === $normPrefix) {
            return true;
        }
        if (str_starts_with($relativePath, rtrim($normPrefix, '/') . '/')) {
            return true;
        }
    }
    return false;
}

function joinPath(string $base, string $rel): string
{
    return normalizePath(rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($rel, DIRECTORY_SEPARATOR));
}

function normalizePath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function toBool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $v = strtolower(trim((string)$value));
    return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
}

function printStats(array $stats): void
{
    echo "  copied files : " . (int)($stats['copiedFiles'] ?? 0) . "\n";
    echo "  created dirs : " . (int)($stats['createdDirs'] ?? 0) . "\n";
    echo "  skipped same : " . (int)($stats['skippedSame'] ?? 0) . "\n";
    echo "  missing src  : " . (int)($stats['missingSource'] ?? 0) . "\n";
    if (array_key_exists('deletedFiles', $stats)) {
        echo "  deleted files: " . (int)($stats['deletedFiles'] ?? 0) . "\n";
        echo "  deleted dirs : " . (int)($stats['deletedDirs'] ?? 0) . "\n";
    }
}

function usageAndExit(int $code, string $msg = ''): void
{
    if ($msg !== '') {
        fwrite(STDERR, $msg . "\n\n");
    }

    $usage = <<<TXT
Usage:
  php scripts/sync_live_beta.php --mode=hydrate-beta [--sync-db=1] [--dry-run=1]
  php scripts/sync_live_beta.php --mode=promote-live [--dry-run=1] [--delete=1]

Options:
  --mode=...         hydrate-beta | promote-live
  --live-root=...    path to LIVE app root (default: current repo root)
  --beta-root=...    path to BETA app root (default: sibling ./pythonIDEBeta)
  --dry-run=1        print actions only, do not copy/import
  --sync-db=1        only for hydrate-beta: clone DB snapshot live -> beta
  --delete=1         only for promote-live: delete extra files in LIVE not in BETA (excluded paths are preserved)

TXT;

    fwrite($code === 0 ? STDOUT : STDERR, $usage);
    exit($code);
}
