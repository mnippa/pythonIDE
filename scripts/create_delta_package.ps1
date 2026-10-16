[CmdletBinding()]
param(
    [ValidateSet("Git", "Recent", "Paths", "Full", "Development")]
    [string]$Mode = "Git",
    [ValidateSet("None", "Git", "Local")]
    [string]$ManifestMode = "Local",
    [ValidateSet("Package", "ManifestOnly", "PackageAndManifest")]
    [string]$OutputMode = "Package",
    [string]$SourceDir = "",
    [string]$BaseRef = "HEAD",
    [ValidateRange(1, 525600)]
    [int]$SinceMinutes = 120,
    [string[]]$Paths = @(),
    [string]$OutputDir = "",
    [string]$PackageName = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$resolvedSourceDir = if ([string]::IsNullOrWhiteSpace($SourceDir)) {
    $scriptPath = @(
        $PSScriptRoot,
        $PSCommandPath,
        $MyInvocation.MyCommand.Path
    ) | Where-Object { $_ -and -not [string]::IsNullOrWhiteSpace($_) } | Select-Object -First 1

    if ($scriptPath) {
        Split-Path -Parent $scriptPath
    } else {
        (Get-Location).Path
    }
} else {
    $SourceDir
}

$sourceRoot = [System.IO.Path]::GetFullPath($resolvedSourceDir).TrimEnd('\', '/')
$resolvedOutputDir = if ([string]::IsNullOrWhiteSpace($OutputDir)) {
    Join-Path $sourceRoot "deploy-packages"
} else {
    $OutputDir
}
$allowedDirectories = @("api", "public", "config", "components")
$allowedRootFiles = @(".htaccess", "index.php", "composer.json", "composer.lock", "web.config")
$protectedFiles = @(
    "config/database.php",
    "config/database.beta_live.local.php",
    "config/local.php"
)

function Normalize-RelativePath {
    param([string]$Path)

    $candidate = $Path.Replace('\', '/').Trim()
    if ([System.IO.Path]::IsPathRooted($candidate)) {
        $fullPath = [System.IO.Path]::GetFullPath($candidate)
        $prefix = $sourceRoot + [System.IO.Path]::DirectorySeparatorChar
        if (-not $fullPath.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            throw "Path is outside SourceDir: $Path"
        }
        $candidate = $fullPath.Substring($prefix.Length).Replace('\', '/')
    }

    while ($candidate.StartsWith('./')) {
        $candidate = $candidate.Substring(2)
    }

    if ([string]::IsNullOrWhiteSpace($candidate) -or $candidate.StartsWith('/') -or $candidate -match '(^|/)\.\.(/|$)') {
        throw "Invalid relative path: $Path"
    }

    return $candidate
}

function Test-DeployablePath {
    param([string]$RelativePath)

    if (
        $protectedFiles -contains $RelativePath -or
        $RelativePath -match '^config/.*\.local\.php$' -or
        $RelativePath -match '^config/database(?:[._-].*)?\.php$'
    ) {
        return $false
    }
    if ($allowedRootFiles -contains $RelativePath) {
        return $true
    }

    $topLevel = ($RelativePath -split '/', 2)[0]
    return $allowedDirectories -contains $topLevel
}

function Get-AllDeployablePaths {
    $all = New-Object System.Collections.Generic.List[string]

    foreach ($directory in $allowedDirectories) {
        $directoryPath = Join-Path $sourceRoot $directory
        if (-not (Test-Path -LiteralPath $directoryPath -PathType Container)) {
            continue
        }
        Get-ChildItem -LiteralPath $directoryPath -File -Recurse | ForEach-Object {
            $relative = Normalize-RelativePath $_.FullName
            if (Test-DeployablePath $relative) {
                $all.Add($relative)
            }
        }
    }

    foreach ($rootFile in $allowedRootFiles) {
        $filePath = Join-Path $sourceRoot $rootFile
        if (Test-Path -LiteralPath $filePath -PathType Leaf) {
            $relative = Normalize-RelativePath $filePath
            if (Test-DeployablePath $relative) {
                $all.Add($relative)
            }
        }
    }

    return @($all | Sort-Object -Unique)
}

function Test-DevelopmentPath {
    param([string]$RelativePath)

    $normalized = $RelativePath.Replace('\\', '/').Trim('/').ToLowerInvariant()
    $excludedPrefixes = @(
        'public/vendor',
        'public/js/vendor',
        'public/js/lib',
        'public/js/pyonide',
        'public/pyonide',
        'public/assets',
        'public/build',
        'public/dist'
    )

    foreach ($prefix in $excludedPrefixes) {
        $prefixNormalized = $prefix.ToLowerInvariant()
        if ($normalized -eq $prefixNormalized -or $normalized.StartsWith($prefixNormalized + '/')) {
            return $false
        }
    }

    return $true
}

function Get-DevelopmentPaths {
    $all = New-Object System.Collections.Generic.List[string]

    foreach ($directory in $allowedDirectories) {
        $directoryPath = Join-Path $sourceRoot $directory
        if (-not (Test-Path -LiteralPath $directoryPath -PathType Container)) {
            continue
        }
        Get-ChildItem -LiteralPath $directoryPath -File -Recurse | ForEach-Object {
            $relative = Normalize-RelativePath $_.FullName
            if ((Test-DeployablePath $relative) -and (Test-DevelopmentPath $relative)) {
                $all.Add($relative)
            }
        }
    }

    foreach ($rootFile in $allowedRootFiles) {
        $filePath = Join-Path $sourceRoot $rootFile
        if (Test-Path -LiteralPath $filePath -PathType Leaf) {
            $relative = Normalize-RelativePath $filePath
            if ((Test-DeployablePath $relative) -and (Test-DevelopmentPath $relative)) {
                $all.Add($relative)
            }
        }
    }

    return @($all | Sort-Object -Unique)
}

function Get-GitPaths {
    $null = Get-Command git -ErrorAction Stop
    & git -C $sourceRoot rev-parse --is-inside-work-tree 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "SourceDir is not a Git worktree: $sourceRoot"
    }

    $changed = @(& git -C $sourceRoot diff --name-only --diff-filter=ACMRTUXB $BaseRef --)
    if ($LASTEXITCODE -ne 0) {
        throw "Could not compare against Git ref '$BaseRef'."
    }
    $untracked = @(& git -C $sourceRoot ls-files --others --exclude-standard)
    if ($LASTEXITCODE -ne 0) {
        throw "Could not list untracked Git files."
    }

    return @($changed + $untracked)
}

function Get-RecentPaths {
    $cutoff = (Get-Date).AddMinutes(-$SinceMinutes)
    $recent = New-Object System.Collections.Generic.List[string]

    foreach ($directory in $allowedDirectories) {
        $directoryPath = Join-Path $sourceRoot $directory
        if (-not (Test-Path -LiteralPath $directoryPath -PathType Container)) {
            continue
        }
        Get-ChildItem -LiteralPath $directoryPath -File -Recurse | Where-Object {
            $_.LastWriteTime -ge $cutoff
        } | ForEach-Object {
            $recent.Add($_.FullName)
        }
    }

    foreach ($rootFile in $allowedRootFiles) {
        $rootPath = Join-Path $sourceRoot $rootFile
        if ((Test-Path -LiteralPath $rootPath -PathType Leaf) -and (Get-Item -LiteralPath $rootPath).LastWriteTime -ge $cutoff) {
            $recent.Add($rootPath)
        }
    }

    return $recent.ToArray()
}

$candidatePaths = switch ($Mode) {
    "Git" { @(Get-GitPaths) }
    "Recent" { @(Get-RecentPaths) }
    "Paths" {
        if ($Paths.Count -eq 0) {
            throw "Mode Paths requires at least one value in -Paths."
        }
        @($Paths)
    }
    "Full" { @(Get-AllDeployablePaths) }
    "Development" { @(Get-DevelopmentPaths) }
    default { @(Get-AllDeployablePaths) }
}

$deployablePaths = New-Object System.Collections.Generic.List[string]
$skippedPaths = New-Object System.Collections.Generic.List[string]

foreach ($candidatePath in $candidatePaths) {
    if ([string]::IsNullOrWhiteSpace([string]$candidatePath)) {
        continue
    }
    $relativePath = Normalize-RelativePath ([string]$candidatePath)
    $sourcePath = Join-Path $sourceRoot ($relativePath.Replace('/', '\'))
    if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf) -or -not (Test-DeployablePath $relativePath)) {
        $skippedPaths.Add($relativePath)
        continue
    }
    $deployablePaths.Add($relativePath)
}

$deployablePaths = @($deployablePaths | Sort-Object -Unique)
if ($deployablePaths.Count -eq 0) {
    throw "No deployable changed files found for mode $Mode."
}

$baseCommit = $null
$headCommit = $null
$headCommitShort = $null
try {
    $headCommit = (& git -C $sourceRoot rev-parse HEAD).Trim()
    if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace($headCommit)) {
        $headCommitShort = (& git -C $sourceRoot rev-parse --short HEAD).Trim()
    }
} catch {
    $headCommit = $null
    $headCommitShort = $null
}

if ($Mode -eq "Git") {
    $baseCommit = (& git -C $sourceRoot rev-parse $BaseRef).Trim()
}
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$modeTag = $Mode.ToLowerInvariant()
$suffix = if (-not [string]::IsNullOrWhiteSpace($headCommitShort)) {
    if ($modeTag -eq 'git') {
        "g$headCommitShort"
    } else {
        "g$headCommitShort-$modeTag"
    }
} elseif ($baseCommit) {
    $baseCommit.Substring(0, [Math]::Min(8, $baseCommit.Length))
} else {
    $modeTag
}
$packageId = "delta-$timestamp-$suffix"
if ([string]::IsNullOrWhiteSpace($PackageName)) {
    $PackageName = "$packageId.zip"
} elseif (-not $PackageName.EndsWith('.zip', [System.StringComparison]::OrdinalIgnoreCase)) {
    $PackageName += '.zip'
}

$outputRoot = [System.IO.Path]::GetFullPath($resolvedOutputDir)
New-Item -ItemType Directory -Path $outputRoot -Force | Out-Null
$packagePath = Join-Path $outputRoot $PackageName
$stagingRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("pythonide-" + [Guid]::NewGuid().ToString('N'))
$filesRoot = Join-Path $stagingRoot "files"
New-Item -ItemType Directory -Path $filesRoot -Force | Out-Null

try {
    $manifestFiles = New-Object System.Collections.Generic.List[object]
    foreach ($relativePath in $deployablePaths) {
        $sourcePath = Join-Path $sourceRoot ($relativePath.Replace('/', '\'))
        $stagedPath = Join-Path $filesRoot ($relativePath.Replace('/', '\'))
        $stagedDirectory = Split-Path -Parent $stagedPath
        New-Item -ItemType Directory -Path $stagedDirectory -Force | Out-Null
        Copy-Item -LiteralPath $sourcePath -Destination $stagedPath -Force

        $fileInfo = Get-Item -LiteralPath $sourcePath
        $manifestFiles.Add([ordered]@{
            path = $relativePath
            action = "replace"
            size = [long]$fileInfo.Length
            sha256 = (Get-FileHash -LiteralPath $sourcePath -Algorithm SHA256).Hash.ToLowerInvariant()
        })
    }

    $manifestOutputPath = Join-Path $outputRoot ("$packageId.manifest.json")
    if ($ManifestMode -ne 'None') {
        $manifest = [ordered]@{
            schema_version = 1
            package_id = $packageId
            created_at = (Get-Date).ToString('yyyy-MM-ddTHH:mm:ssK')
            manifest_mode = $ManifestMode.ToLowerInvariant()
            scope = $Mode.ToLowerInvariant()
            excluded_paths = if ($Mode -eq "Development") { @(
                'public/vendor',
                'public/js/vendor',
                'public/js/lib',
                'public/js/pyonide',
                'public/pyonide',
                'public/assets',
                'public/build',
                'public/dist'
            ) } else { @() }
            source = [ordered]@{
                mode = $ManifestMode.ToLowerInvariant()
                base_ref = if ($ManifestMode -eq "Git") { $BaseRef } else { $null }
                base_commit = if ($ManifestMode -eq "Git") { $baseCommit } else { $null }
                head_commit = $headCommit
                since_minutes = if ($Mode -eq "Recent") { $SinceMinutes } else { $null }
            }
            files = $manifestFiles
        }

        $manifestJson = $manifest | ConvertTo-Json -Depth 8
        [System.IO.File]::WriteAllText(
            (Join-Path $stagingRoot 'manifest.json'),
            $manifestJson,
            (New-Object System.Text.UTF8Encoding($false))
        )

        if ($OutputMode -in @('ManifestOnly', 'PackageAndManifest')) {
            [System.IO.File]::WriteAllText(
                $manifestOutputPath,
                $manifestJson,
                (New-Object System.Text.UTF8Encoding($false))
            )
        }
    }

    if ($OutputMode -eq 'ManifestOnly') {
        Write-Host "[manifest] Created: $manifestOutputPath"
        Write-Host "[manifest] Mode: $Mode"
        Write-Host "[manifest] Manifest: $ManifestMode"
        Write-Host "[manifest] Files: $($deployablePaths.Count)"
        return
    }

    if (Test-Path -LiteralPath $packagePath) {
        Remove-Item -LiteralPath $packagePath -Force
    }
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zipStream = [System.IO.File]::Open($packagePath, [System.IO.FileMode]::CreateNew)
    try {
        $archive = New-Object System.IO.Compression.ZipArchive(
            $zipStream,
            [System.IO.Compression.ZipArchiveMode]::Create,
            $false
        )
        try {
            $stagingPrefix = $stagingRoot.TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar
            Get-ChildItem -LiteralPath $stagingRoot -File -Recurse | ForEach-Object {
                $entryName = $_.FullName.Substring($stagingPrefix.Length).Replace('\', '/')
                [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $archive,
                    $_.FullName,
                    $entryName,
                    [System.IO.Compression.CompressionLevel]::Optimal
                ) | Out-Null
            }
        } finally {
            $archive.Dispose()
        }
    } finally {
        $zipStream.Dispose()
    }
} finally {
    if (Test-Path -LiteralPath $stagingRoot) {
        Remove-Item -LiteralPath $stagingRoot -Recurse -Force
    }
}

if ($OutputMode -eq 'ManifestOnly') {
    return
}

Write-Host "[package] Created: $packagePath"
Write-Host "[package] Mode: $Mode"
Write-Host "[package] Scope: $Mode"
Write-Host "[package] Manifest: $ManifestMode"
Write-Host "[package] Files: $($deployablePaths.Count)"
foreach ($relativePath in $deployablePaths) {
    Write-Host "  + $relativePath"
}
if ($skippedPaths.Count -gt 0) {
    Write-Host "[package] Skipped protected, missing, or non-deployable paths:"
    $skippedPaths | Sort-Object -Unique | ForEach-Object { Write-Host "  - $_" }
}

[pscustomobject]@{
    PackagePath = $packagePath
    PackageId = $packageId
    Mode = $Mode
    FileCount = $deployablePaths.Count
}