param([Parameter(Mandatory=$true)][string]$Installer, [Parameter(Mandatory=$true)][string]$PreviousDatabase)
$ErrorActionPreference = 'Stop'
$Workspace = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$TestRoot = Join-Path $Workspace ('output\installer-check-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $TestRoot | Out-Null
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression
$Assembly = [Reflection.Assembly]::LoadFile((Resolve-Path -LiteralPath $Installer).Path)
$Engine = $Assembly.GetType('ClassCheckInstaller.SafeUpdate')
$Method = $Engine.GetMethod('Install')
function Install-Payload([string]$Destination) {
    $Stream = $Assembly.GetManifestResourceStream('payload.zip')
    $Archive = New-Object IO.Compression.ZipArchive($Stream)
    try { [ClassCheckInstaller.SafeUpdate]::Install($Archive, $Destination) } finally { $Archive.Dispose(); $Stream.Dispose() }
}
function Assert([bool]$Condition, [string]$Message) { if (-not $Condition) { throw $Message } }
$Fresh = Join-Path $TestRoot 'fresh'
Install-Payload $Fresh | Out-Null
$Php = Join-Path $Fresh 'bin\php\php.exe'
$Ini = Join-Path $Fresh 'bin\php\php.ini'
$Ext = Join-Path $Fresh 'bin\php\ext'
function Run-Php([string[]]$Arguments) {
    & $Php -c $Ini -d "extension_dir=$Ext" @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'PHP verification failed.' }
}
Assert (Test-Path (Join-Path $Fresh 'ClassCheck.exe')) 'Fresh installation failed.'
Assert (-not (Test-Path (Join-Path $Fresh 'public\storage'))) 'Workspace uploads/junction were packaged.'
$Installed = Join-Path $TestRoot 'existing'
New-Item -ItemType Directory -Path (Join-Path $Installed 'database') -Force | Out-Null
Copy-Item -LiteralPath $PreviousDatabase -Destination (Join-Path $Installed 'database\database.sqlite')
$EnvText = "APP_NAME=ClassCheck`nAPP_ENV=production`nAPP_OFFLINE=true`nAPP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=`nDB_CONNECTION=sqlite`nCACHE_STORE=file`nSESSION_DRIVER=file`nQUEUE_CONNECTION=sync`nFILESYSTEM_DISK=local`n"
[IO.File]::WriteAllText((Join-Path $Installed '.env'), $EnvText)
foreach ($Relative in @('storage\app\public\photos\student.png', 'storage\app\private\sections\essay.txt', 'storage\app\reports\grade.csv', 'public\uploads\original.txt', 'database\custom.sqlite')) {
    $File = Join-Path $Installed $Relative
    New-Item -ItemType Directory -Path (Split-Path $File) -Force | Out-Null
    [IO.File]::WriteAllText($File, "Original fixture content: $Relative")
}
Run-Php -Arguments @((Join-Path $PSScriptRoot 'seed-upgrade.php'), $Installed, 'seed')
$Original = @{}
Get-ChildItem -LiteralPath $Installed -File -Recurse | ForEach-Object { $Original[$_.FullName] = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash }
$Backup = Install-Payload $Installed
Assert (Test-Path (Join-Path $Backup 'SHA256SUMS.txt')) 'Verified pre-update backup missing.'
foreach ($File in $Original.Keys) {
    Assert ((Get-FileHash -LiteralPath $File -Algorithm SHA256).Hash -eq $Original[$File]) "Update overwrote $File"
    $Relative = $File.Substring($Installed.Length + 1)
    if ($Relative -ne 'fixture-records.json') { Assert (Test-Path (Join-Path $Backup $Relative)) "Backup missing $Relative" }
}
# Run precisely the helper used by the launcher, on an old schema with real fixture rows.
Push-Location $Installed
try { Run-Php -Arguments @((Join-Path $Installed 'desktop-maintenance.php')) } finally { Pop-Location }
Run-Php -Arguments @((Join-Path $PSScriptRoot 'seed-upgrade.php'), $Installed, 'verify')
Assert ((Get-FileHash -LiteralPath (Join-Path $Installed '.env')).Hash -eq $Original[(Join-Path $Installed '.env')]) '.env or APP_KEY changed.'
# Re-running the updater must also preserve the already upgraded installation.
Install-Payload $Installed | Out-Null
Run-Php -Arguments @((Join-Path $PSScriptRoot 'seed-upgrade.php'), $Installed, 'verify')
# Prove backup failure prevents code extraction.
$Code = Join-Path $Installed 'ClassCheck.exe'
$CodeHash = (Get-FileHash -LiteralPath $Code).Hash
$Locked = [IO.File]::Open((Join-Path $Installed '.env'), 'Open', 'ReadWrite', 'None')
$Failed = $false
try { Install-Payload $Installed | Out-Null } catch { $Failed = $true } finally { $Locked.Dispose() }
Assert $Failed 'A locked configuration did not abort the update.'
Assert ((Get-FileHash -LiteralPath $Code).Hash -eq $CodeHash) 'Code changed after backup failure.'
# Reject traversal before extraction.
$Memory = New-Object IO.MemoryStream
$Zip = New-Object IO.Compression.ZipArchive($Memory, [IO.Compression.ZipArchiveMode]::Create, $true)
$Entry = $Zip.CreateEntry('../escape.txt'); $Writer = New-Object IO.StreamWriter($Entry.Open()); $Writer.Write('invalid'); $Writer.Dispose(); $Zip.Dispose()
$Memory.Position = 0
$Zip = New-Object IO.Compression.ZipArchive($Memory, [IO.Compression.ZipArchiveMode]::Read)
$Failed = $false
try { [ClassCheckInstaller.SafeUpdate]::Install($Zip, (Join-Path $TestRoot 'traversal')) | Out-Null } catch { $Failed = $true } finally { $Zip.Dispose(); $Memory.Dispose() }
Assert $Failed 'Archive traversal was accepted.'
Assert (-not (Test-Path (Join-Path $TestRoot 'escape.txt'))) 'Archive escaped its destination.'
# A deliberately bad migration in this disposable fixture must be rolled back.
$Probe = Join-Path $Installed 'database\migrations\2026_10_21_999999_upgrade_guard_probe.php'
@'
<?php
return new class extends Illuminate\Database\Migrations\Migration {
    public function up(): void {
        Illuminate\Support\Facades\DB::table('assessment_scores')->where('id', 1)->update(['score' => 0]);
        Illuminate\Support\Facades\Schema::create('upgrade_guard_probe', function ($table) { $table->id(); });
    }
};
'@ | Set-Content -LiteralPath $Probe -Encoding ASCII
$Info = New-Object Diagnostics.ProcessStartInfo
$Info.FileName = $Php
$Info.Arguments = '-c "' + $Ini + '" -d extension_dir="' + $Ext + '" "' + (Join-Path $Installed 'desktop-maintenance.php') + '"'
$Info.WorkingDirectory = $Installed
$Info.UseShellExecute = $false
$Info.CreateNoWindow = $true
$Info.RedirectStandardOutput = $true
$Info.RedirectStandardError = $true
try {
    $Process = [Diagnostics.Process]::Start($Info)
    $Out = $Process.StandardOutput.ReadToEndAsync()
    $Errors = $Process.StandardError.ReadToEndAsync()
    $Process.WaitForExit()
    Assert ($Process.ExitCode -ne 0 -and $Errors.Result.Contains('rolled back')) 'Unsafe migration was not blocked.'
} finally { Remove-Item -LiteralPath $Probe }
Run-Php -Arguments @((Join-Path $PSScriptRoot 'seed-upgrade.php'), $Installed, 'verify')
@{ passed=$true; installer=(Resolve-Path -LiteralPath $Installer).Path; testRoot=$TestRoot; checks=@('fresh install','database and configuration byte preservation','uploads preserved','verified backup','old-schema upgrade','all original records preserved','repeat update','locked backup abort','archive traversal rejection','unsafe migration rollback') } | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $TestRoot 'result.json')
Write-Output "PASS: Installer update checks. Evidence: $TestRoot\result.json"
