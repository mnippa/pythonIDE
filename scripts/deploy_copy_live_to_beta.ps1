[CmdletBinding()]
param(
    [string]$SourceDir = "C:\xampp\htdocs\pythonIDE",
    [string]$TargetDir = "C:\xampp\htdocs\pythonIDEBeta",
    [switch]$Mirror,
    [switch]$DryRun
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)
    Write-Host "[deploy] $Message"
}

if (-not (Test-Path -LiteralPath $SourceDir)) {
    throw "SourceDir not found: $SourceDir"
}

if (-not (Test-Path -LiteralPath $TargetDir)) {
    if ($DryRun) {
        Write-Step "DryRun: would create target directory $TargetDir"
    } else {
        New-Item -ItemType Directory -Path $TargetDir -Force | Out-Null
        Write-Step "Created target directory: $TargetDir"
    }
}

# Whitelist: only these directories are deployed by default.
$liveDirs = @(
    "api",
    "public",
    "config",
    "components",
    "storage"
)

# Whitelist: selected root files (adjust if your live setup needs more).
$rootFiles = @(
    ".htaccess",
    "index.php"
)

# Optional root files copied when present.
$optionalRootFiles = @(
    "composer.json",
    "composer.lock",
    "web.config"
)

# Environment-specific files must remain owned by the target installation.
$protectedTargetFiles = @(
    "config\database.php",
    "config\database.beta_live.local.php"
)

foreach ($file in $optionalRootFiles) {
    $candidate = Join-Path $SourceDir $file
    if (Test-Path -LiteralPath $candidate) {
        $rootFiles += $file
    }
}

# Global excludes for files that are typically non-production.
$excludeFiles = @(
    "*.md",
    "*.prompt.md",
    "*.sh",
    "*.ps1",
    "*.sql",
    "test_*",
    "debug_*",
    "check_*",
    "tmp_*",
    "README*",
    "ROADMAP*"
)

$excludeFiles += $protectedTargetFiles | ForEach-Object { Split-Path -Leaf $_ }

$protectedSnapshots = @{}
foreach ($relativePath in $protectedTargetFiles) {
    $targetPath = Join-Path $TargetDir $relativePath
    $existed = Test-Path -LiteralPath $targetPath -PathType Leaf
    $protectedSnapshots[$relativePath] = [pscustomobject]@{
        Existed = $existed
        Bytes = if ($existed) { [System.IO.File]::ReadAllBytes($targetPath) } else { $null }
    }
}

Write-Step "Source: $SourceDir"
Write-Step "Target: $TargetDir"
Write-Step "Mode: $([string]::Join(', ', @($(if($DryRun){'DryRun'}else{'Copy'}), $(if($Mirror){'Mirror'}else{'Incremental'}))))"

# Copy selected root files
foreach ($file in $rootFiles | Select-Object -Unique) {
    $src = Join-Path $SourceDir $file
    $dst = Join-Path $TargetDir $file

    if (-not (Test-Path -LiteralPath $src)) {
        continue
    }

    if ($DryRun) {
        Write-Step "DryRun: would copy root file $file"
    } else {
        $dstDir = Split-Path -Parent $dst
        if ($dstDir -and -not (Test-Path -LiteralPath $dstDir)) {
            New-Item -ItemType Directory -Path $dstDir -Force | Out-Null
        }
        Copy-Item -LiteralPath $src -Destination $dst -Force
        Write-Step "Copied root file: $file"
    }
}

# Copy selected live directories via robocopy
foreach ($dir in $liveDirs) {
    $srcDir = Join-Path $SourceDir $dir
    if (-not (Test-Path -LiteralPath $srcDir)) {
        Write-Step "Skip missing directory: $dir"
        continue
    }

    $dstDir = Join-Path $TargetDir $dir
    $args = @(
        $srcDir,
        $dstDir
    )

    if ($Mirror) {
        $args += "/MIR"
    } else {
        $args += "/E"
    }

    $args += @(
        "/R:1",
        "/W:1",
        "/NFL",
        "/NDL",
        "/NJH",
        "/NJS",
        "/NP",
        "/XJ"
    )

    if ($DryRun) {
        $args += "/L"
    }

    if ($excludeFiles.Count -gt 0) {
        $args += "/XF"
        $args += $excludeFiles
    }

    Write-Step "Sync directory: $dir"
    & robocopy @args | Out-Host

    $exitCode = $LASTEXITCODE
    # Robocopy exit codes < 8 are success/warnings.
    if ($exitCode -ge 8) {
        throw "Robocopy failed for '$dir' with exit code $exitCode"
    }
}

foreach ($relativePath in $protectedSnapshots.Keys) {
    $targetPath = Join-Path $TargetDir $relativePath
    $snapshot = $protectedSnapshots[$relativePath]
    $existsNow = Test-Path -LiteralPath $targetPath -PathType Leaf

    if (-not $snapshot.Existed) {
        if ($existsNow) {
            Remove-Item -LiteralPath $targetPath -Force
            throw "Protected target file was created and removed: $relativePath"
        }
        continue
    }

    $currentBytes = if ($existsNow) {
        [System.IO.File]::ReadAllBytes($targetPath)
    } else {
        $null
    }

    $wasModified = $null -eq $currentBytes -or
        [Convert]::ToBase64String($snapshot.Bytes) -ne [Convert]::ToBase64String($currentBytes)
    if ($wasModified) {
        [System.IO.File]::WriteAllBytes($targetPath, $snapshot.Bytes)
        throw "Protected target file was modified and restored: $relativePath"
    }
}

if (-not $DryRun) {
    $historyFile = Join-Path $TargetDir "storage\admin-deploy-history.json"
    $historyDir = Split-Path -Parent $historyFile
    if (-not (Test-Path -LiteralPath $historyDir)) {
        New-Item -ItemType Directory -Path $historyDir -Force | Out-Null
    }

    $history = @{
        entries = @()
        next_id = 1
    }

    if (Test-Path -LiteralPath $historyFile) {
        try {
            $loadedHistory = Get-Content -LiteralPath $historyFile -Raw | ConvertFrom-Json
            if ($null -ne $loadedHistory.entries) {
                $history.entries = @($loadedHistory.entries)
                $history.next_id = $history.entries.Count + 1
            }
        } catch {
            $history.entries = @()
            $history.next_id = 1
        }
    }

    $entry = [ordered]@{
        id = [int]$history.next_id
        kind = 'copy-beta'
        label = 'Copy Beta'
        target = 'beta'
        package_id = $null
        created_at = (Get-Date).ToString('o')
        actor = $null
        details = [ordered]@{
            source_dir = $SourceDir
            target_dir = $TargetDir
            mirror = [bool]$Mirror
        }
    }
    $history.entries += $entry
    $history.next_id = [int]$history.next_id + 1

    $historyJson = [ordered]@{
        entries = $history.entries
        next_id = $history.next_id
    } | ConvertTo-Json -Depth 8
    Set-Content -LiteralPath $historyFile -Value $historyJson -Encoding UTF8

    $versionFile = Join-Path $TargetDir "storage\admin-ui-version.json"
    $versionDir = Split-Path -Parent $versionFile
    if (-not (Test-Path -LiteralPath $versionDir)) {
        New-Item -ItemType Directory -Path $versionDir -Force | Out-Null
    }

    $versionPayload = [ordered]@{
        version = $entry.id
        updated_at = $entry.created_at
        reason = $entry.label
        kind = $entry.kind
        target = $entry.target
        package_id = $null
        actor = $null
    }
    $versionPayload | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $versionFile -Encoding UTF8
}

Write-Step "Done."
Write-Step "Copied directories: $($liveDirs -join ', ')"
Write-Step "Copied root files: $($rootFiles | Select-Object -Unique | Sort-Object | ForEach-Object { $_ } | Out-String)"
if (-not $DryRun) {
    Write-Step "Admin patch history updated with copy-beta entry #$($entry.id)"
}
