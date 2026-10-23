param([Parameter(Mandatory=$true)][string]$AssemblyPath)
$ErrorActionPreference = 'Stop'
[Reflection.Assembly]::LoadFile((Resolve-Path -LiteralPath $AssemblyPath).Path) | Out-Null
$Workspace = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Fixture = Join-Path $Workspace ('output\discovery-check-' + [Guid]::NewGuid().ToString('N'))
$Legacy = Join-Path $Fixture 'portable\ClassCheck'
New-Item -ItemType Directory -Path (Join-Path $Legacy 'public') -Force | Out-Null
[IO.File]::WriteAllText((Join-Path $Legacy 'ClassCheck.exe'), 'fixture')
[IO.File]::WriteAllText((Join-Path $Legacy 'artisan'), 'fixture')
$Missing = Join-Path $Fixture 'stale-registration'
$Found = [ClassCheckInstaller.InstallDiscovery]::Find([string[]]@($Missing, (Join-Path $Legacy 'ClassCheck.exe')))
if ($Found -ne $Legacy) { throw 'A valid shortcut target was not found after a stale/missing registry entry.' }
if ([ClassCheckInstaller.InstallDiscovery]::ExistingFolder((Split-Path $Legacy)) -ne $Legacy) { throw 'Nested ClassCheck folder was not resolved.' }
if ([ClassCheckInstaller.InstallDiscovery]::ExistingFolder($Legacy) -ne $Legacy) { throw 'An existing folder was nested twice.' }
if ([ClassCheckInstaller.InstallDiscovery]::Find([string[]]@($Missing)) -ne $null) { throw 'A nonexistent installation was accepted.' }
$Actual = [ClassCheckInstaller.InstallDiscovery]::Detect()
@{passed=$true; detectedInstallation=$Actual; checks=@('missing registry with shortcut candidate','stale candidate ignored','parent folder resolves nested app','exact app folder stays unchanged','nonexistent target rejected')} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $Fixture 'result.json')
Write-Output "PASS: Installation discovery; current existing app: $Actual"
