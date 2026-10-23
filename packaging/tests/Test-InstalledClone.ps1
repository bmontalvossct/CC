param([Parameter(Mandatory=$true)][string]$Installer, [Parameter(Mandatory=$true)][string]$ExistingInstall)
$ErrorActionPreference = 'Stop'
$Workspace = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Clone = Join-Path $Workspace ('output\installed-clone-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path (Join-Path $Clone 'database') -Force | Out-Null
$SourceFiles = @((Join-Path $ExistingInstall 'database\database.sqlite'))
foreach ($Suffix in @('-wal', '-shm', '-journal')) {
    $File = Join-Path $ExistingInstall ('database\database.sqlite' + $Suffix)
    if (Test-Path -LiteralPath $File) { $SourceFiles += $File }
}
$Handles = @()
$RawHashes = @{}
try {
    # Hold every source file read-only, denying writers for the entire copy.
    # SQLite recovery/checkpointing happens only in the writable clone.
    foreach ($File in $SourceFiles) { $Handles += [IO.File]::Open($File, 'Open', 'Read', 'Read') }
    for ($Index=0; $Index -lt $SourceFiles.Count; $Index++) {
        $Copy = Join-Path $Clone ('database\' + [IO.Path]::GetFileName($SourceFiles[$Index]))
        $Output = [IO.File]::Open($Copy, 'CreateNew', 'Write', 'None')
        try { $Handles[$Index].CopyTo($Output) } finally { $Output.Dispose() }
        $Hash = (Get-FileHash -LiteralPath $SourceFiles[$Index]).Hash.ToLowerInvariant()
        if ($Hash -ne (Get-FileHash -LiteralPath $Copy).Hash.ToLowerInvariant()) { throw 'Source clone checksum mismatch.' }
        $RawHashes[[IO.Path]::GetFileName($SourceFiles[$Index])] = $Hash
    }
    $RawHashes | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $Clone 'source-files.json') -Encoding ASCII
} finally { foreach ($Handle in $Handles) { $Handle.Dispose() } }
& php (Join-Path $PSScriptRoot 'clone-upgrade.php') $ExistingInstall $Clone before
if ($LASTEXITCODE -ne 0) { throw 'Could not clone the installed database safely.' }
Add-Type -AssemblyName System.IO.Compression
[Reflection.Assembly]::LoadFile((Resolve-Path -LiteralPath $Installer).Path) | Out-Null
$Assembly = [ClassCheckInstaller.SafeUpdate].Assembly
$Stream = $Assembly.GetManifestResourceStream('payload.zip')
$Archive = New-Object IO.Compression.ZipArchive($Stream)
try { $Backup = [ClassCheckInstaller.SafeUpdate]::Install($Archive, $Clone) } finally { $Archive.Dispose(); $Stream.Dispose() }
$Info = New-Object Diagnostics.ProcessStartInfo
$Info.FileName = Join-Path $Clone 'bin\php\php.exe'
$Info.Arguments = '-c "' + (Join-Path $Clone 'bin\php\php.ini') + '" -d extension_dir="' + (Join-Path $Clone 'bin\php\ext') + '" "' + (Join-Path $Clone 'desktop-maintenance.php') + '"'
$Info.WorkingDirectory = $Clone
$Info.UseShellExecute = $false
$Info.CreateNoWindow = $true
$Info.RedirectStandardOutput = $true
$Info.RedirectStandardError = $true
foreach ($Key in @($Info.EnvironmentVariables.Keys)) {
    if ($Key -match '^(APP_|DB_|DATABASE_URL|POSTGRES_URL|CACHE_|SESSION_)') { $Info.EnvironmentVariables.Remove($Key) }
}
$Info.EnvironmentVariables['APP_OFFLINE'] = 'true'
$Info.EnvironmentVariables['DB_CONNECTION'] = 'sqlite'
$Info.EnvironmentVariables['DB_DATABASE'] = Join-Path $Clone 'database\database.sqlite'
$Info.EnvironmentVariables['CACHE_STORE'] = 'file'
$Process = [Diagnostics.Process]::Start($Info)
$Out = $Process.StandardOutput.ReadToEndAsync()
$Errors = $Process.StandardError.ReadToEndAsync()
$Process.WaitForExit()
if ($Process.ExitCode -ne 0) { throw ($Errors.Result + $Out.Result) }
Write-Output $Out.Result
& php (Join-Path $PSScriptRoot 'clone-upgrade.php') $ExistingInstall $Clone after
if ($LASTEXITCODE -ne 0) { throw 'Installed clone verification failed.' }
$Version = (Get-Item -LiteralPath (Join-Path $Clone 'ClassCheck.exe')).VersionInfo.FileVersion
if ($Version -ne '2026.10.21.1') { throw 'Clone launcher was not updated.' }
if (-not (Test-Path -LiteralPath (Join-Path $Clone 'last-update.txt'))) { throw 'Update receipt missing.' }
@{passed=$true; original=$ExistingInstall; clone=$Clone; installedVersion=$Version; originalDatabaseUntouched=$true; originalRecordsPreserved=$true; backup=$Backup} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $Clone 'result.json')
Write-Output "PASS: Real installed database clone upgraded. Evidence: $Clone\result.json"
