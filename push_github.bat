@echo off
echo ========================================================
echo   PUSH KE GITHUB - APLIKASI RAKERNAS AL KHIDMAH
echo ========================================================
echo.

:: Cek apakah git sudah terinstall di sistem
where git >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [PERINGATAN] Git belum terpasang (terinstall) di komputer ini!
    echo.
    echo Silakan download dan install Git terlebih dahulu:
    echo 1. Kunjungi: https://git-scm.com/download/win
    echo 2. Download "64-bit Git for Windows Setup" dan install (klik Next sampai selesai).
    echo 3. Setelah install selesai, buka kembali file ini (push_github.bat).
    echo.
    pause
    exit /b
)

set /p REPO_URL="Masukkan URL Repository GitHub Anda (contoh: https://github.com/mfajrulh2/Rakernas-Alkhidmah_vinal.git): "

if "%REPO_URL%"=="" (
    echo Error: URL Repository tidak boleh kosong!
    pause
    exit /b
)

echo.
echo [1/4] Menginisialisasi Git...
git init

echo.
echo [2/4] Menambahkan seluruh file aplikasi...
git add .

echo.
echo [3/4] Membuat Commit...
git commit -m "feat: Aplikasi Manajemen RAKERNAS Perkumpulan Jama'ah Al Khidmah 2026 (Fitur Lengkap)"

echo.
echo [4/4] Menghubungkan remote dan push ke branch main...
git branch -M main
git remote remove origin 2>nul
git remote add origin %REPO_URL%

:: Gunakan push --force agar tidak bentrok dengan commit awal .gitattributes di GitHub
git push -u origin main --force

echo.
echo ========================================================
echo   SELESAI! Project berhasil di-push ke GitHub.
echo ========================================================
pause
