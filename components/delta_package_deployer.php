<?php

declare(strict_types=1);

function inspectAndApplyDeltaPackage(
    string $zipPath,
    string $targetRoot,
    string $backupRoot,
    bool $dryRun
): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZIP extension is not available.');
    }
    if (!is_file($zipPath)) {
        throw new RuntimeException('Package file does not exist.');
    }

    $resolvedTargetRoot = realpath($targetRoot);
    if ($resolvedTargetRoot === false || !is_dir($resolvedTargetRoot)) {
        throw new RuntimeException('Target directory does not exist.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open ZIP package.');
    }

    try {
        $manifestRaw = $zip->getFromName('manifest.json');
        if (!is_string($manifestRaw)) {
            throw new RuntimeException('manifest.json is missing from package root.');
        }

        $manifest = json_decode($manifestRaw, true);
        if (!is_array($manifest) || (int)($manifest['schema_version'] ?? 0) !== 1) {
            throw new RuntimeException('Unsupported or invalid package manifest.');
        }

        $packageId = trim((string)($manifest['package_id'] ?? ''));
        if ($packageId === '' || !preg_match('/^[A-Za-z0-9._-]{1,120}$/', $packageId)) {
            throw new RuntimeException('Invalid package_id in manifest.');
        }

        $manifestFiles = $manifest['files'] ?? null;
        if (!is_array($manifestFiles) || count($manifestFiles) < 1 || count($manifestFiles) > 500) {
            throw new RuntimeException('Manifest must contain between 1 and 500 files.');
        }

        $plan = [];
        $seenPaths = [];
        $totalBytes = 0;

        foreach ($manifestFiles as $entry) {
            if (!is_array($entry) || ($entry['action'] ?? '') !== 'replace') {
                throw new RuntimeException('Only replace actions are supported.');
            }

            $relativePath = normalizeDeltaPath((string)($entry['path'] ?? ''));
            if (!isDeployableDeltaPath($relativePath)) {
                throw new RuntimeException("Protected or non-deployable path: {$relativePath}");
            }

            $pathKey = strtolower($relativePath);
            if (isset($seenPaths[$pathKey])) {
                throw new RuntimeException("Duplicate path in manifest: {$relativePath}");
            }
            $seenPaths[$pathKey] = true;

            $expectedHash = strtolower((string)($entry['sha256'] ?? ''));
            $expectedSize = (int)($entry['size'] ?? -1);
            if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash) || $expectedSize < 0 || $expectedSize > 10 * 1024 * 1024) {
                throw new RuntimeException("Invalid hash or size for: {$relativePath}");
            }

            $archivePath = 'files/' . $relativePath;
            $archiveStat = $zip->statName($archivePath);
            if (!is_array($archiveStat) || (int)$archiveStat['size'] !== $expectedSize) {
                throw new RuntimeException("Package file size mismatch: {$relativePath}");
            }

            $contents = $zip->getFromName($archivePath);
            if (!is_string($contents) || strlen($contents) !== $expectedSize || hash('sha256', $contents) !== $expectedHash) {
                throw new RuntimeException("Package checksum mismatch: {$relativePath}");
            }

            $totalBytes += $expectedSize;
            if ($totalBytes > 50 * 1024 * 1024) {
                throw new RuntimeException('Package contents exceed 50 MB.');
            }

            assertDeltaTargetHasNoSymlinks($resolvedTargetRoot, $relativePath);
            $targetPath = deltaJoinPath($resolvedTargetRoot, $relativePath);
            $currentHash = is_file($targetPath) ? hash_file('sha256', $targetPath) : null;
            $status = $currentHash === $expectedHash ? 'unchanged' : (is_file($targetPath) ? 'replace' : 'new');

            $plan[] = [
                'path' => $relativePath,
                'status' => $status,
                'size' => $expectedSize,
                'sha256' => $expectedHash,
                'contents' => $contents,
            ];
        }

        $changedPlan = array_values(array_filter($plan, static fn(array $item): bool => $item['status'] !== 'unchanged'));
        if ($dryRun || count($changedPlan) === 0) {
            return buildDeltaResult($packageId, $dryRun, $plan, null);
        }

        $safeTargetName = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($resolvedTargetRoot)) ?: 'target';
        $backupDirectory = rtrim($backupRoot, '\/') . DIRECTORY_SEPARATOR
            . date('Ymd-His') . '-' . $safeTargetName . '-' . $packageId;
        if (!mkdir($backupDirectory, 0775, true) && !is_dir($backupDirectory)) {
            throw new RuntimeException('Could not create deployment backup directory.');
        }

        $applied = [];
        try {
            foreach ($changedPlan as $item) {
                $relativePath = $item['path'];
                $targetPath = deltaJoinPath($resolvedTargetRoot, $relativePath);
                $targetDirectory = dirname($targetPath);
                if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
                    throw new RuntimeException("Could not create target directory for: {$relativePath}");
                }

                $existed = is_file($targetPath);
                if ($existed) {
                    $backupPath = deltaJoinPath($backupDirectory, $relativePath);
                    $backupParent = dirname($backupPath);
                    if (!is_dir($backupParent) && !mkdir($backupParent, 0775, true) && !is_dir($backupParent)) {
                        throw new RuntimeException("Could not create backup directory for: {$relativePath}");
                    }
                    if (!@copy($targetPath, $backupPath)) {
                        throw new RuntimeException("Could not back up: {$relativePath}");
                    }
                }

                $applied[] = ['path' => $relativePath, 'existed' => $existed];

                $temporaryPath = tempnam($targetDirectory, '.delta-');
                if ($temporaryPath === false) {
                    throw new RuntimeException("Could not create temporary file for: {$relativePath}");
                }
                try {
                    if (file_put_contents($temporaryPath, $item['contents'], LOCK_EX) !== $item['size']) {
                        throw new RuntimeException("Could not write temporary file for: {$relativePath}");
                    }
                    if (!@copy($temporaryPath, $targetPath)) {
                        throw new RuntimeException("Could not replace: {$relativePath}");
                    }
                } finally {
                    @unlink($temporaryPath);
                }

                if (hash_file('sha256', $targetPath) !== $item['sha256']) {
                    throw new RuntimeException("Verification after write failed: {$relativePath}");
                }
            }

            $backupManifest = [
                'package_id' => $packageId,
                'target_root' => $resolvedTargetRoot,
                'created_at' => date(DATE_ATOM),
                'files' => $applied,
            ];
            $manifestWritten = file_put_contents(
                $backupDirectory . DIRECTORY_SEPARATOR . 'backup-manifest.json',
                json_encode($backupManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            if ($manifestWritten === false) {
                throw new RuntimeException('Could not write deployment backup manifest.');
            }
        } catch (Throwable $exception) {
            rollbackDeltaFiles($resolvedTargetRoot, $backupDirectory, array_reverse($applied));
            throw $exception;
        }

        return buildDeltaResult($packageId, false, $plan, $backupDirectory);
    } finally {
        $zip->close();
    }
}

function normalizeDeltaPath(string $path): string
{
    $normalized = trim(str_replace('\\', '/', $path));
    if (
        $normalized === ''
        || $normalized[0] === '/'
        || strpos($normalized, "\0") !== false
        || preg_match('/^[A-Za-z]:/', $normalized)
        || preg_match('#(^|/)\.\.(/|$)#', $normalized)
        || preg_match('#(^|/)\.(/|$)#', $normalized)
    ) {
        throw new RuntimeException("Invalid package path: {$path}");
    }
    return $normalized;
}

function isDeployableDeltaPath(string $path): bool
{
    $lowerPath = strtolower($path);
    $protected = [
        'config/database.php',
        'config/database.beta_live.local.php',
        'config/local.php',
    ];
    if (
        in_array($lowerPath, $protected, true)
        || strpos($lowerPath, 'storage/') === 0
        || strpos($lowerPath, '.env') === 0
        || (strpos($lowerPath, 'config/') === 0 && substr($lowerPath, -10) === '.local.php')
        || preg_match('#^config/database(?:[._-].*)?\.php$#', $lowerPath)
    ) {
        return false;
    }

    $allowedRootFiles = ['.htaccess', 'index.php', 'composer.json', 'composer.lock', 'web.config'];
    if (in_array($lowerPath, $allowedRootFiles, true)) {
        return true;
    }

    $firstSlash = strpos($lowerPath, '/');
    $topLevel = $firstSlash === false ? $lowerPath : substr($lowerPath, 0, $firstSlash);
    return in_array($topLevel, ['api', 'public', 'config', 'components'], true);
}

function assertDeltaTargetHasNoSymlinks(string $targetRoot, string $relativePath): void
{
    $current = rtrim($targetRoot, '\/');
    $segments = explode('/', $relativePath);
    array_pop($segments);
    foreach ($segments as $segment) {
        $current .= DIRECTORY_SEPARATOR . $segment;
        if (is_link($current)) {
            throw new RuntimeException("Symlinked target path is not allowed: {$relativePath}");
        }
    }
}

function deltaJoinPath(string $root, string $relativePath): string
{
    return rtrim($root, '\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function rollbackDeltaFiles(string $targetRoot, string $backupDirectory, array $applied): void
{
    foreach ($applied as $item) {
        $targetPath = deltaJoinPath($targetRoot, $item['path']);
        if ($item['existed']) {
            $backupPath = deltaJoinPath($backupDirectory, $item['path']);
            if (is_file($backupPath)) {
                @copy($backupPath, $targetPath);
            }
        } else {
            @unlink($targetPath);
        }
    }
}

function buildDeltaResult(string $packageId, bool $dryRun, array $plan, ?string $backupDirectory): array
{
    $counts = ['new' => 0, 'replace' => 0, 'unchanged' => 0];
    $publicPlan = [];
    foreach ($plan as $item) {
        $counts[$item['status']]++;
        $publicPlan[] = [
            'path' => $item['path'],
            'status' => $item['status'],
            'size' => $item['size'],
            'sha256' => $item['sha256'],
        ];
    }

    return [
        'package_id' => $packageId,
        'dry_run' => $dryRun,
        'counts' => $counts,
        'files' => $publicPlan,
        'backup_directory' => $backupDirectory,
    ];
}
