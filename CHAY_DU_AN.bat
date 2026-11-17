@echo off
title Khoi chay Nha Khoa Quoc Te Loc Phat
color 0A

echo ===================================================
echo     HE THONG QUAN LY PHONG KHAM VA DAT LICH KHAM
echo ===================================================
echo.

:: 1. Kiem tra va khoi dong MySQL (XAMPP)
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MySQL dang chay.
) else (
    echo [..] Dang khoi dong MySQL tu XAMPP...
    start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    timeout /t 3 /nobreak >nul
    echo [OK] MySQL da duoc khoi dong.
)

:: 2. Chuyen vao thu muc du an clinic
cd /d "D:\Doantotnghiep\QL-phong-kham\clinic"

:: 3. Them duong dan PHP vao PATH tam thoi cua cua so nay
set "PATH=C:\xampp\php;C:\xampp\mysql\bin;%PATH%"

echo.
echo ===================================================
echo  Server dang chay tai: http://127.0.0.1:8000
echo  Trang dang nhap:      http://127.0.0.1:8000/dang-nhap
echo.
echo  Tai khoan test (Pass: password):
echo   - Admin:      admin@phongkham.test
echo   - Bac si:     bs.quang@phongkham.test
echo   - Le tan:     letan@phongkham.test
echo   - Benh nhan:  benhnhan@phongkham.test
echo.
echo  Nhan [Ctrl + C] de tat server khi khong dung.
echo ===================================================
echo.

:: Tu dong mo trinh duyet
start http://127.0.0.1:8000

:: Chay Laravel server
php artisan serve --host=127.0.0.1 --port=8000

pause
