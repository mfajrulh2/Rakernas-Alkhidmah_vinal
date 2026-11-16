<?php
/**
 * Landing Page / Halaman Utama
 * RAKERNAS I JAMAAH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Pastikan 2 berkas gambar asli terbaru dari user tersalin ke assets/img/
$userImg1 = 'C:/Users/CahNgroto/.gemini/antigravity/brain/3201d629-0ccc-48e0-b548-8409936228d2/.user_uploaded/media_1794842770516_37ab8828.png';
$userImg2 = 'C:/Users/CahNgroto/.gemini/antigravity/brain/3201d629-0ccc-48e0-b548-8409936228d2/.user_uploaded/media_1794842770586_4f07b6b6.png';
$targetDir1 = __DIR__ . '/assets/img/';
$targetDir2 = 'E:/JOB/2026/11. November/RAKERNAS/APP RAKERNAS/assets/img/';

if (!is_dir($targetDir1)) @mkdir($targetDir1, 0777, true);
if (!is_dir($targetDir2)) @mkdir($targetDir2, 0777, true);

if (file_exists($userImg1)) {
    if (!file_exists($targetDir1 . 'hero_title_logo.png') || filesize($targetDir1 . 'hero_title_logo.png') !== filesize($userImg1)) {
        @copy($userImg1, $targetDir1 . 'hero_title_logo.png');
        @copy($userImg1, $targetDir2 . 'hero_title_logo.png');
    }
}
if (file_exists($userImg2)) {
    if (!file_exists($targetDir1 . 'hero_bg_venue.png') || filesize($targetDir1 . 'hero_bg_venue.png') !== filesize($userImg2)) {
        @copy($userImg2, $targetDir1 . 'hero_bg_venue.png');
        @copy($userImg2, $targetDir2 . 'hero_bg_venue.png');
    }
}

$pdo = getDB();

$page_title = "Beranda - RAKERNAS I JAMAAH AL KHIDMAH";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section: Pristine Background Venue & Official Logo Graphic -->
<div class="relative overflow-hidden rounded-[2rem] sm:rounded-[2.5rem] bg-white border border-slate-200/90 shadow-xl mb-12">
    <!-- Venue Background Graphic (BBPMP Jawa Tengah) -->
    <img src="<?= BASE_URL ?>assets/img/hero_bg_venue.png" alt="BBPMP Jawa Tengah" class="absolute inset-0 w-full h-full object-cover object-left pointer-events-none select-none z-0">

    <!-- Hero Content Overlay (Logo + Title Graphic & Action Buttons) -->
    <div class="relative z-10 max-w-3xl mx-auto flex flex-col items-center justify-center text-center px-4 py-8 sm:py-12 md:py-14">
        <!-- Logo & Title Graphic (Official Artwork) -->
        <div class="w-full max-w-[320px] sm:max-w-[420px] md:max-w-[480px] mb-6 sm:mb-8">
            <img src="<?= BASE_URL ?>assets/img/hero_title_logo.png" alt="RAKERNAS I JAMAAH AL KHIDMAH" class="w-full h-auto object-contain mx-auto drop-shadow-sm select-none hover:scale-[1.01] transition-transform duration-300">
        </div>

        <!-- 2 Main Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-6 w-full max-w-lg">
            <!-- Tombol 1: Pendaftaran Peserta (Green) -->
            <a href="<?= BASE_URL ?>registrasi.php" class="w-full sm:w-auto flex-1 min-w-[210px] px-6 py-3.5 rounded-2xl bg-[#00a859] hover:bg-[#00924d] text-white transition-all duration-200 shadow-lg shadow-emerald-900/25 border border-emerald-400/30 flex items-center justify-center gap-3.5 transform hover:-translate-y-0.5 active:translate-y-0 group">
                <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center text-white font-extrabold text-base group-hover:rotate-90 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                </div>
                <div class="text-left leading-tight font-extrabold text-sm sm:text-base">
                    <div>Pendaftaran</div>
                    <div>Peserta</div>
                </div>
            </a>

            <!-- Tombol 2: Konfirmasi Pembayaran (Gold/Amber) -->
            <a href="<?= BASE_URL ?>konfirmasi_pembayaran.php" class="w-full sm:w-auto flex-1 min-w-[210px] px-6 py-3.5 rounded-2xl bg-[#f59e0b] hover:bg-[#d97706] text-slate-950 transition-all duration-200 shadow-lg shadow-amber-500/25 border border-amber-300/60 flex items-center justify-center gap-3.5 transform hover:-translate-y-0.5 active:translate-y-0 group">
                <div class="w-7 h-7 rounded-full bg-black/10 flex items-center justify-center text-slate-950 font-bold group-hover:scale-110 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="text-left leading-tight font-extrabold text-sm sm:text-base">
                    <div>Konfirmasi</div>
                    <div>Pembayaran</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Features Section: Alur & Layanan Aplikasi Rakernas -->
<div class="mb-14">
    <div class="text-center max-w-xl mx-auto mb-10">
        <h2 class="text-2xl font-bold text-slate-900">Alur & Layanan Aplikasi Rakernas</h2>
        <p class="text-sm text-slate-500 mt-1">Kemudahan koordinasi kafilah dari seluruh penjuru nusantara</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Pendaftaran Peserta (Online) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">1. Pendaftaran Peserta (Online)</h3>
            <p class="text-slate-600 text-sm leading-relaxed mb-4">
                peserta/rombongan mendaftarkan diri , kemudian melakuikan pembayaran dan konfirmasi pada portal yang telah tersedia
            </p>
            <a href="<?= BASE_URL ?>registrasi.php" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900 inline-flex items-center gap-1">
                Daftar Sekarang &rarr;
            </a>
        </div>

        <!-- Card 2: E-ID Card & Akomodasi -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">2. E-ID Card & Akomodasi</h3>
            <p class="text-slate-600 text-sm leading-relaxed mb-4">
                Setiap peserta terverifikasi mendapatkan barcode QR Code unik, penetapan kamar wisma penginapan, dan pembagian komisi sidang.
            </p>
            <a href="<?= BASE_URL ?>daftar_peserta.php" class="text-sm font-semibold text-amber-700 hover:text-amber-800 inline-flex items-center gap-1">
                Lihat Daftar Peserta &rarr;
            </a>
        </div>

        <!-- Card 3: Presensi Cepat di Lokasi -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">3. Fast Check-in di Lokasi</h3>
            <p class="text-slate-600 text-sm leading-relaxed mb-4">
                Meja registrasi cukup memindai (scan) QR Code di HP atau ID Card peserta untuk validasi kehadiran langsung dan pengambilan kit Rakernas.
            </p>
            <a href="<?= BASE_URL ?>rundown.php" class="text-sm font-semibold text-purple-700 hover:text-purple-800 inline-flex items-center gap-1">
                Lihat Jadwal Kegiatan &rarr;
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
