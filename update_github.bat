@echo off
echo ========================================================
echo   UPDATE & PUSH PERUBAHAN KE GITHUB
echo   Aplikasi RAKERNAS Perkumpulan Jama'ah Al Khidmah
echo ========================================================
echo.

:: Minta pesan commit dari user
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

echo.
echo ========================================================
echo   BERHASIL! GitHub Anda sekarang sudah diperbarui.
echo ========================================================
pause
