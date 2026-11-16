@echo off
echo ========================================================
echo   UPDATE DAN PUSH PERUBAHAN KE GITHUB
echo   Aplikasi RAKERNAS Perkumpulan Jama'ah Al Khidmah
echo ========================================================
echo.

set /p PESAN="Tuliskan keterangan perubahan (contoh: update halaman presensi): "

if "%PESAN%"=="" (
    set PESAN=Update aplikasi Rakernas
)

echo.
echo [1/3] Mendeteksi dan merekam perubahan berkas...
git add .

echo.
echo [2/3] Menyimpan catatan perubahan (commit)...
git commit -m "%PESAN%"

echo.
echo [3/3] Mengirim perubahan ke GitHub...
git push origin main
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo Menyelaraskan data lokal dengan GitHub...
    git pull origin main --rebase
    git push origin main
)
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo Menerapkan sinkronisasi penuh ke GitHub...
    git push origin main --force
)

echo.
echo ========================================================
echo   SELESAI! GitHub Anda sekarang sudah diperbarui.
echo ========================================================
pause
