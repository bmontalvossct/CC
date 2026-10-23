# ==============================================================================
# ClassCheck Windows Executable & Installer Build Script
# ==============================================================================
param(
    [string]$PhpSourceDir = (Join-Path $PSScriptRoot 'scratch\runtime\php-8.5.12')
)

$ErrorActionPreference = "Stop"
$WorkspaceRoot = $PSScriptRoot
$PhpExe = Join-Path $PhpSourceDir 'php.exe'
if (!(Test-Path -LiteralPath $PhpExe)) {
    throw 'PHP runtime missing. Run node packaging/install-php.mjs first, or pass -PhpSourceDir.'
}
$PhpVersionId = & $PhpExe -r 'echo PHP_VERSION_ID;'
if ($LASTEXITCODE -ne 0 -or [int]$PhpVersionId -lt 80512 -or [int]$PhpVersionId -ge 80600) {
    throw 'Packaging requires PHP 8.5.12 or a newer PHP 8.5 security release.'
}
$DistDir = Join-Path $WorkspaceRoot ("dist\releases\" + (Get-Date -Format "yyyy.MM.dd-HHmmss"))
if (Test-Path -LiteralPath $DistDir) { throw "Release output already exists: $DistDir" }
$PayloadDir = Join-Path $DistDir "ClassCheck"
$CscPath = "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
$ManifestPath = Join-Path $WorkspaceRoot "packaging\app.manifest"
$IconPath = Join-Path $WorkspaceRoot "packaging\app.ico"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " Building ClassCheck Windows Standalone Executable & Setup" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Compile Frontend Assets
Write-Host "`n[1/7] Compiling production frontend assets with Vite..." -ForegroundColor Yellow
Set-Location $WorkspaceRoot
npm.cmd run build
if ($LASTEXITCODE -ne 0) {
    throw "Frontend compilation failed"
}

# Always build in a new directory; never remove an old portable installation.
Write-Host "`n[2/7] Preparing a new release directory..." -ForegroundColor Yellow
New-Item -ItemType Directory -Path $PayloadDir -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "bin\php\ext") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "storage\app\sections") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "storage\app\public\photos") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "storage\app\public\modules") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "storage\framework\cache\data") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "storage\framework\sessions") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "storage\framework\views") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "storage\logs") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "bootstrap\cache") -Force | Out-Null

# 3. Copy Portable PHP Runtime
Write-Host "`n[3/7] Copying patched portable PHP 8.5 runtime and extensions..." -ForegroundColor Yellow
$PhpDestDir = Join-Path $PayloadDir "bin\php"

# Essential PHP Executables & Core DLLs
$PhpFiles = @(
    "php.exe", "php-win.exe", "php-cgi.exe",
    "libcrypto-3-x64.dll", "libssl-3-x64.dll", "libsqlite3.dll",
    "libsodium.dll", "nghttp2.dll", "libssh2.dll", "icudt72.dll", "icuin72.dll", "icuio72.dll", "icuuc72.dll"
)
foreach ($file in $PhpFiles) {
    $src = Join-Path $PhpSourceDir $file
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $PhpDestDir -Force
    }
}

# Any other DLLs in PHP root
Get-ChildItem -Path $PhpSourceDir -Filter "*.dll" | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination $PhpDestDir -Force
}

# Copy full suite of MSVC 140 CRT DLLs from System32 to guarantee zero dependency on external VC++ Redistributable
$VcDlls = @(
    "vcruntime140.dll", "vcruntime140_1.dll", "vcruntime140_threads.dll",
    "msvcp140.dll", "msvcp140_1.dll", "msvcp140_2.dll", "msvcp140_atomic_wait.dll", "msvcp140_codecvt_ids.dll",
    "vccorlib140.dll", "concrt140.dll"
)
foreach ($vc in $VcDlls) {
    $sysDll = Join-Path "C:\Windows\System32" $vc
    if (Test-Path $sysDll) {
        Copy-Item -Path $sysDll -Destination $PhpDestDir -Force
    }
}

# Extensions
$ExtDir = Join-Path $PhpSourceDir "ext"
$ExtDest = Join-Path $PhpDestDir "ext"
$ExtList = @(
    "php_pdo_sqlite.dll", "php_sqlite3.dll", "php_openssl.dll",
    "php_mbstring.dll", "php_curl.dll", "php_fileinfo.dll",
    "php_gd.dll", "php_zip.dll", "php_intl.dll", "php_opcache.dll"
)
foreach ($ext in $ExtList) {
    $src = Join-Path $ExtDir $ext
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $ExtDest -Force
    }
}

# Portable relative php.ini
$PhpIniContent = @"
[PHP]
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
serialize_precision = -1
zend.enable_gc = On
expose_php = Off

max_execution_time = 300
max_input_time = 60
memory_limit = 512M
error_reporting = E_ALL & ~E_DEPRECATED
display_errors = Off
display_startup_errors = Off
log_errors = On
log_errors_max_len = 1024
ignore_repeated_errors = Off
ignore_repeated_source = Off
report_memleaks = On

variables_order = "GPCS"
request_order = "GP"
register_argc_argv = Off
auto_globals_jit = On
post_max_size = 64M
default_mimetype = "text/html"
default_charset = "UTF-8"

enable_dl = Off
file_uploads = On
upload_max_filesize = 64M
max_file_uploads = 20

allow_url_fopen = On
allow_url_include = Off
default_socket_timeout = 60

extension_dir = "ext"

extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip

[Date]
date.timezone = "Asia/Manila"

[opcache]
# OPcache is built into PHP 8.5; do not load the removed extension DLL.
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
"@
Set-Content -Path (Join-Path $PhpDestDir "php.ini") -Value $PhpIniContent -Encoding UTF8

# 4. Copy Application Payload
Write-Host "`n[4/7] Copying Laravel application codebase and assets..." -ForegroundColor Yellow
$RootDirsToCopy = @("app", "bootstrap", "config", "routes", "vendor")
foreach ($dir in $RootDirsToCopy) {
    $src = Join-Path $WorkspaceRoot $dir
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $PayloadDir -Recurse -Force
    }
}

# Never copy workspace databases, backups, uploads, storage junctions, or dev hot files.
New-Item -ItemType Directory -Path (Join-Path $PayloadDir "database") -Force | Out-Null
foreach ($dir in @("migrations", "factories", "seeders")) {
    Copy-Item -LiteralPath (Join-Path $WorkspaceRoot "database\$dir") -Destination (Join-Path $PayloadDir "database") -Recurse
}
$PublicDest = Join-Path $PayloadDir "public"
New-Item -ItemType Directory -Path $PublicDest -Force | Out-Null
Get-ChildItem -LiteralPath (Join-Path $WorkspaceRoot "public") -Force | Where-Object {
    $_.Name -notin @("storage", "uploads", "hot") -and -not ($_.Attributes -band [IO.FileAttributes]::ReparsePoint)
} | ForEach-Object { Copy-Item -LiteralPath $_.FullName -Destination $PublicDest -Recurse }
# Only disposable bootstrap cache files in the NEW payload are cleared.
Get-ChildItem -LiteralPath (Join-Path $PayloadDir "bootstrap\cache") -Filter "*.php" | Remove-Item -Force
Copy-Item -LiteralPath (Join-Path $WorkspaceRoot "packaging\desktop-maintenance.php") -Destination $PayloadDir

# Resources (views and raw assets)
$ResourcesDest = Join-Path $PayloadDir "resources"
New-Item -ItemType Directory -Path $ResourcesDest -Force | Out-Null
if (Test-Path (Join-Path $WorkspaceRoot "resources\views")) {
    Copy-Item -Path (Join-Path $WorkspaceRoot "resources\views") -Destination (Join-Path $ResourcesDest "views") -Recurse -Force
}

# Artisan, Composer & Server router
Copy-Item -Path (Join-Path $WorkspaceRoot "artisan") -Destination $PayloadDir -Force
Copy-Item -Path (Join-Path $WorkspaceRoot "composer.json") -Destination $PayloadDir -Force
if (Test-Path (Join-Path $WorkspaceRoot "server.php")) {
    Copy-Item -Path (Join-Path $WorkspaceRoot "server.php") -Destination $PayloadDir -Force
}

# Copy README documentation
if (Test-Path (Join-Path $WorkspaceRoot "packaging\README.txt")) {
    Copy-Item -Path (Join-Path $WorkspaceRoot "packaging\README.txt") -Destination $PayloadDir -Force
    Copy-Item -Path (Join-Path $WorkspaceRoot "packaging\README.txt") -Destination $DistDir -Force
}

# Ensure bundled SQLite database starts completely blank (zero accounts / zero data)
$SqliteDest = Join-Path $PayloadDir "database\database.sqlite"
if (Test-Path $SqliteDest) { throw "New payload unexpectedly contains a database." }
[System.IO.File]::WriteAllBytes($SqliteDest, [byte[]]@())

# Production standalone .env
$EnvContent = @"
APP_NAME=ClassCheck
APP_ENV=production
APP_OFFLINE=true
APP_KEY=base64:7K0bE2q5Qv7X6g9r8+t1uF2w3x4y5z6a7b8c9d0e1f2=
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=sqlite
DB_FOREIGN_KEYS=true

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file
CACHE_PREFIX=classcheck_cache_

OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_CHAT_MODEL=hermes3:8b
OLLAMA_CODE_MODEL=qwen2.5-coder:7b
OLLAMA_GENERAL_MODEL=hermes3:8b
AUTOCHECKER_DEFAULT_MODEL=hermes3:8b
"@
Set-Content -Path (Join-Path $PayloadDir ".env") -Value $EnvContent -Encoding UTF8

# Pre-migrate SQLite database so the bundled database has all tables fully initialized with zero accounts
Write-Host "    Pre-migrating bundled SQLite database tables (zero accounts)..." -ForegroundColor DarkGray
# Isolate the build child from development/test database environment variables.
$BuildPhp = New-Object System.Diagnostics.ProcessStartInfo
$BuildPhp.FileName = Join-Path $PhpDestDir "php.exe"
$BuildPhp.WorkingDirectory = $PayloadDir
$BuildPhp.UseShellExecute = $false
$BuildPhp.CreateNoWindow = $true
$BuildPhp.RedirectStandardOutput = $true
$BuildPhp.RedirectStandardError = $true
foreach ($key in @($BuildPhp.EnvironmentVariables.Keys)) {
    if ($key -match '^(APP_|DB_|DATABASE_URL|POSTGRES_URL|CACHE_|SESSION_)') { $BuildPhp.EnvironmentVariables.Remove($key) }
}
$BuildPhp.EnvironmentVariables['DB_DATABASE'] = $SqliteDest
$BuildPhp.EnvironmentVariables['APP_OFFLINE'] = 'true'
$BuildPhp.EnvironmentVariables['APP_ENV'] = 'production'
$BuildPhp.Arguments = '-c "' + (Join-Path $PhpDestDir "php.ini") + '" -d extension_dir="' + $ExtDest + '" "' + (Join-Path $PayloadDir "desktop-maintenance.php") + '"'
$BuildProcess = [Diagnostics.Process]::Start($BuildPhp)
$BuildOutput = $BuildProcess.StandardOutput.ReadToEndAsync()
$BuildErrors = $BuildProcess.StandardError.ReadToEndAsync()
$BuildProcess.WaitForExit()
if ($BuildProcess.ExitCode -ne 0) { throw ("Bundled database initialization failed: " + $BuildErrors.Result + $BuildOutput.Result) }
$VerifyDbScript = Join-Path $DistDir 'verify-empty.php'
'<?php $pdo = new PDO("sqlite:".$argv[1]); echo $pdo->query("SELECT count(*) FROM users")->fetchColumn();' | Set-Content -LiteralPath $VerifyDbScript -Encoding ASCII
$BuildPhp.Arguments = '-c "' + (Join-Path $PhpDestDir "php.ini") + '" -d extension_dir="' + $ExtDest + '" "' + $VerifyDbScript + '" "' + $SqliteDest + '"'
$BuildProcess = [Diagnostics.Process]::Start($BuildPhp)
$UserCount = $BuildProcess.StandardOutput.ReadToEnd().Trim()
$BuildErrors = $BuildProcess.StandardError.ReadToEnd()
$BuildProcess.WaitForExit()
if ($BuildProcess.ExitCode -ne 0 -or $UserCount -ne '0') { throw "Could not verify the empty bundled database: $UserCount $BuildErrors" }
Write-Host "    Bundled database verified clean: User count = $UserCount (Zero accounts)" -ForegroundColor Green
@{ version='2026.10.21.1'; built_at=(Get-Date).ToUniversalTime().ToString('o'); frontend_sha256=(Get-FileHash -LiteralPath (Join-Path $PayloadDir 'public\build\manifest.json')).Hash.ToLowerInvariant() } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $PayloadDir 'classcheck-release.json') -Encoding UTF8

# 5. Compile ClassCheck.exe (Main Launcher)
Write-Host "`n[5/7] Compiling native Windows launcher (ClassCheck.exe)..." -ForegroundColor Yellow
$LauncherSource = Join-Path $WorkspaceRoot "packaging\launcher\ClassCheckLauncher.cs"
$LauncherOut = Join-Path $PayloadDir "ClassCheck.exe"

$CscLauncherArgs = @(
    "/target:winexe",
    "/optimize+",
    "/platform:x64",
    "/win32manifest:$ManifestPath",
    "/win32icon:$IconPath",
    "/out:$LauncherOut",
    "/reference:System.Windows.Forms.dll",
    "/reference:System.Drawing.dll",
    $LauncherSource
)
& $CscPath $CscLauncherArgs
if ($LASTEXITCODE -ne 0) {
    throw "Failed to compile ClassCheck.exe"
}

# 6. Create Zip Archive (Portable Edition)
Write-Host "`n[6/7] Creating Portable ZIP package (ClassCheck_Portable.zip)..." -ForegroundColor Yellow
$ZipOut = Join-Path $DistDir "ClassCheck_Portable.zip"
$PayloadZip = Join-Path $DistDir "payload.zip"

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($PayloadDir, $PayloadZip)
Copy-Item -Path $PayloadZip -Destination $ZipOut -Force

# 7. Compile ClassCheck_Setup.exe (Self-Extracting Installer)
Write-Host "`n[7/7] Compiling standalone Windows installer (ClassCheck_Setup.exe)..." -ForegroundColor Yellow
$InstallerSource = Join-Path $WorkspaceRoot "packaging\installer\ClassCheckInstaller.cs"
$InstallerOut = Join-Path $DistDir "ClassCheck_Setup.exe"

$CscInstallerArgs = @(
    "/target:winexe",
    "/optimize+",
    "/platform:x64",
    "/win32manifest:$ManifestPath",
    "/win32icon:$IconPath",
    "/resource:$PayloadZip,payload.zip",
    "/out:$InstallerOut",
    "/reference:System.Windows.Forms.dll",
    "/reference:System.Drawing.dll",
    "/reference:System.IO.Compression.dll",
    "/reference:System.IO.Compression.FileSystem.dll",
    $InstallerSource,
    (Join-Path $WorkspaceRoot "packaging\installer\SafeUpdate.cs"),
    (Join-Path $WorkspaceRoot "packaging\installer\InstallDiscovery.cs")
)
& $CscPath $CscInstallerArgs
if ($LASTEXITCODE -ne 0) {
    throw "Failed to compile ClassCheck_Setup.exe"
}

# Remove temporary payload.zip
Remove-Item -Path $PayloadZip -Force -ErrorAction SilentlyContinue

# Do not create certificates or alter the Windows certificate store during a build.
$Checksum = Get-FileHash -LiteralPath $InstallerOut -Algorithm SHA256
($Checksum.Hash.ToLowerInvariant() + "  ClassCheck_Setup.exe") | Set-Content -LiteralPath (Join-Path $DistDir "SHA256SUMS.txt") -Encoding ASCII

# Publish the new setup at the familiar paths as well as the dated release.
# Never touch portable app folders or their databases while publishing installers.
$PublishDirs = @((Join-Path $WorkspaceRoot 'dist'))
if ((Split-Path $WorkspaceRoot -Parent | Split-Path -Leaf) -eq '.worktrees') {
    $RepositoryRoot = Split-Path (Split-Path $WorkspaceRoot -Parent) -Parent
    $PublishDirs += Join-Path $RepositoryRoot 'dist'
}
foreach ($PublishDir in $PublishDirs) {
    New-Item -ItemType Directory -Path $PublishDir -Force | Out-Null
    try {
        $PublishedSetup = Join-Path $PublishDir 'ClassCheck_Setup.exe'
        Copy-Item -LiteralPath $InstallerOut -Destination $PublishedSetup -Force -ErrorAction Stop
        if ((Get-FileHash -LiteralPath $PublishedSetup).Hash -ne $Checksum.Hash) { throw 'Published installer checksum mismatch.' }
        Copy-Item -LiteralPath (Join-Path $DistDir 'SHA256SUMS.txt') -Destination (Join-Path $PublishDir 'ClassCheck_Setup.sha256') -Force
        Copy-Item -LiteralPath (Join-Path $WorkspaceRoot 'packaging\README.txt') -Destination (Join-Path $PublishDir 'ClassCheck_Update_Readme.txt') -Force
    } catch {
        Write-Warning "Could not replace $PublishDir\ClassCheck_Setup.exe. Close the older Setup window. Use the new installer at $InstallerOut. Details: $_"
    }
}

Write-Host "`n==========================================================" -ForegroundColor Green
Write-Host " BUILD SUCCESSFUL! Generated packages in ./dist/:" -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host " 1. Setup Installer (for other units):" -ForegroundColor Cyan
Write-Host "    $InstallerOut" -ForegroundColor White
Write-Host "`n 2. Portable ZIP (USB drive / zero install):" -ForegroundColor Cyan
Write-Host "    $ZipOut" -ForegroundColor White
Write-Host "`n 3. Standalone Application Folder:" -ForegroundColor Cyan
Write-Host "    $PayloadDir\ClassCheck.exe" -ForegroundColor White
Write-Host "==========================================================" -ForegroundColor Green
