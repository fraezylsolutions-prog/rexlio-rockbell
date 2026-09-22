@echo off
rem Rexlio - Sync to Online. Double-click, read the summary, press Y.
rem Runs tools\sync_to_online.php with WampServer's PHP. Extra options can be added after the file name,
rem e.g. --yes for a scheduled task (see the Technical Guide > Two-Click Sync).
setlocal
cd /d "%~dp0"
set "PHPEXE="
for /d %%D in ("C:\wamp64\bin\php\php*") do set "PHPEXE=%%~fD\php.exe"
if not exist "%PHPEXE%" (
    echo Could not find PHP under C:\wamp64\bin\php - is WampServer installed on this computer?
    pause
    exit /b 1
)
"%PHPEXE%" -d display_errors=1 sync_to_online.php %*
set "RC=%ERRORLEVEL%"
echo.
if "%RC%"=="0" (echo Finished: DONE.) else (echo Finished with a problem - read the message above.)
pause
exit /b %RC%
