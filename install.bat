@echo off
setlocal

:: Check if running as admin
net session >nul 2>&1
if %errorlevel% == 0 goto :ELEVATED

:: Re-launch as admin (UAC prompt will appear)
echo Requesting administrator privileges...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Start-Process cmd -ArgumentList '/c cd /d ""%~dp0"" && powershell -NoProfile -ExecutionPolicy Bypass -File ""%~dp0install.ps1""' -Verb RunAs"
exit /b

:ELEVATED
:: Already admin - run install.ps1 directly
pushd "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"
popd
