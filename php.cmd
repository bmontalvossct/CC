@echo off
setlocal
set "CLASSCHECK_PHP=%~dp0scratch\runtime\php-8.5.12\php.exe"
if not exist "%CLASSCHECK_PHP%" (
    echo Run node packaging/install-php.mjs to install the verified PHP runtime. 1>&2
    exit /b 1
)
set "PATH=%~dp0scratch\runtime\php-8.5.12;%PATH%"
"%CLASSCHECK_PHP%" %*
