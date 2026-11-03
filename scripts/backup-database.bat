@echo off
rem =====================================================================
rem  نسخة احتياطية من قاعدة بيانات نظام المتأخرات (XAMPP على ويندوز)
rem  عدّل المتغيرات الأربعة اللي تحت لو مساراتك مختلفة.
rem  شغّله يدوي، أو من Task Scheduler مرة كل يوم.
rem =====================================================================
setlocal

set MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
set DB_NAME=arrears
set DB_USER=root
set BACKUP_DIR=D:\Zaynab\Backups

rem لو لـ MySQL باسورد: ضيف  -pكلمة_السر  بعد  -u %DB_USER%  (من غير مسافة بعد -p)

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HH-mm"') do set STAMP=%%i
set OUT=%BACKUP_DIR%\%DB_NAME%_%STAMP%.sql

"%MYSQLDUMP%" -u %DB_USER% --single-transaction --default-character-set=utf8mb4 %DB_NAME% > "%OUT%"

if errorlevel 1 (
    echo فشل النسخ الاحتياطي. اتأكد من المسارات واسم المستخدم والباسورد.
    del "%OUT%" 2>nul
    exit /b 1
)

echo تم: %OUT%

rem مسح النسخ الأقدم من 30 يوم
forfiles /p "%BACKUP_DIR%" /m %DB_NAME%_*.sql /d -30 /c "cmd /c del @path" 2>nul

endlocal
