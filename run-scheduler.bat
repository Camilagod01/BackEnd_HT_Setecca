@echo off
REM Esperar a que MySQL este escuchando en 127.0.0.1:3306
powershell -NoProfile -Command "while(-not (Test-NetConnection 127.0.0.1 -Port 3306 -InformationLevel Quiet)){ Start-Sleep -Seconds 5 }"

cd /d C:\laragon\www\BackEnd_HT_Setecca
C:\laragon\bin\php\php-8.2.29-Win32-vs16-x64\php.exe artisan schedule:run --no-interaction >> C:\Temp\htsched.log 2>&1
