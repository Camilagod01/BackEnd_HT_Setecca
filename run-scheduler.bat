@echo off
cd /d C:\laragon\www\BackEnd_HT_Setecca
C:\laragon\bin\php\php-8.2.29-Win32-vs16-x64\php.exe artisan schedule:run --no-interaction >> C:\Temp\htsched.log 2>&1
