@echo off
title Tat Du An - Nha Khoa Quoc Te Loc Phat
color 0C

echo ===================================================
echo       DANG TAT HE THONG VA CAC TIEN TRINH
echo ===================================================
echo.

:: 1. Tat server PHP Artisan
echo [..] Dang tat PHP server...
taskkill /F /IM php.exe /T 2>nul
if "%ERRORLEVEL%"=="0" (
    echo [OK] Da tat PHP server.
) else (
    echo [OK] PHP server khong chay hoac da tat.
)

:: 2. Tat MySQL (XAMPP)
echo [..] Dang tat MySQL (XAMPP)...
taskkill /F /IM mysqld.exe /T 2>nul
if "%ERRORLEVEL%"=="0" (
    echo [OK] Da tat MySQL.
) else (
    echo [OK] MySQL khong chay hoac da tat.
)

echo.
echo ===================================================
echo     DA TAT DU AN THANH CONG! CHUC BAN NGU NGON!
echo ===================================================
echo.
timeout /t 3
