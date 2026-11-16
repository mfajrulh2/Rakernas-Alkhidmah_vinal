<?php
/**
 * Landing Page / Halaman Utama
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDB();

$page_title = "Beranda - RAKERNAS Perkumpulan Jama'ah Al Khidmah";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section with Emerald Green & Gold Elegance -->
<div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-alkhidmah-950 via-alkhidmah-900 to-alkhidmah-800 text-white p-8 md:p-14 mb-10 shadow-xl border border-alkhidmah-700/50">
    <!-- Subtle Islamic Star Pattern Decorative -->
    <div class="absolute -right-10 -bottom-10 w-96 h-96 opacity-10 pointer-events-none">
        <svg viewBox="0 0 200 200" fill="currentColor" class="w-full h-full text-gold-400">
            <polygon points="100,10 120,70 185,75 135,115 150,180 100,140 50,180 65,115 15,75 80,70"/>
        </svg>
    </div>

    <div class="relative z-10 max-w-4xl mx-auto text-center">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-alkhidmah-800/80 border border-alkhidmah-600/60 text-gold-400 text-xs font-semibold uppercase tracking-wider mb-4 shadow-sm">
            <span class="w-2 h-2 rounded-full bg-gold-400 animate-ping"></span>
            Aplikasi Resmi Registrasi & Informasi Rakernas
        </div>

        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white mb-3 leading-tight">
            RAKERNAS PERKUMPULAN <br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-gold-400 via-amber-300 to-yellow-200">JAMA'AH AL KHIDMAH</span>
        </h1>

        <p class="text-emerald-100 text-base sm:text-lg mb-6 font-light leading-relaxed max-w-2xl mx-auto">
            <?= clean(getSetting('event_theme', 'Meneguhkan Khidmah, Menguatkan Ukhuwah, Menuju Kemandirian Jam\'iyyah')) ?>
        </p>

        <!-- Tanggal & Lokasi Badge -->
        <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-4 text-xs sm:text-sm text-emerald-200 mb-9">
            <div class="flex items-center gap-2 bg-alkhidmah-900/60 px-4 py-2 rounded-xl border border-alkhidmah-700/60 shadow-sm">
                <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span><?= clean(getSetting('event_date', '15 - 17 November 2026')) ?></span>
            </div>
            <div class="flex items-center gap-2 bg-alkhidmah-900/60 px-4 py-2 rounded-xl border border-alkhidmah-700/60 shadow-sm">
                <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span><?= clean(getSetting('event_location', 'Pondok Pesantren Al Fithrah & Gedung Serbaguna')) ?></span>
            </div>
        </div>

        <!-- 2 Main Action Buttons - Prominent & Centered -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 max-w-xl mx-auto">
            <!-- Tombol 1: Pendaftaran Peserta (Online) -->
            <a href="<?= BASE_URL ?>registrasi.php" class="w-full sm:w-auto flex-1 px-8 py-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 text-white font-extrabold text-base sm:text-lg transition-all duration-200 shadow-xl shadow-emerald-950/50 border border-emerald-400/40 flex items-center justify-center gap-3 transform hover:-translate-y-0.5 active:translate-y-0">
                <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-gold-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </div>
                <span>Pendaftaran Peserta</span>
            </a>

            <!-- Tombol 2: Konfirmasi Pembayaran -->
            <a href="<?= BASE_URL ?>konfirmasi_pembayaran.php" class="w-full sm:w-auto flex-1 px-8 py-4 rounded-2xl bg-gradient-to-r from-gold-500 via-amber-500 to-yellow-500 hover:from-gold-400 hover:to-amber-400 text-slate-950 font-extrabold text-base sm:text-lg transition-all duration-200 shadow-xl shadow-gold-500/25 border border-gold-300/60 flex items-center justify-center gap-3 transform hover:-translate-y-0.5 active:translate-y-0">
                <div class="w-8 h-8 rounded-xl bg-black/10 flex items-center justify-center text-slate-950">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span>Konfirmasi Pembayaran</span>
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
            <div class="w-12 h-12 rounded-xl bg-alkhidmah-100 text-alkhidmah-800 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">1. Pendaftaran Peserta (Online)</h3>
            <p class="text-slate-600 text-sm leading-relaxed mb-4">
                peserta/rombongan mendaftarkan diri , kemudian melakuikan pembayaran dan konfirmasi pada portal yang telah tersedia
            </p>
            <a href="<?= BASE_URL ?>registrasi.php" class="text-sm font-semibold text-alkhidmah-700 hover:text-alkhidmah-900 inline-flex items-center gap-1">
                Daftar Sekarang &rarr;
            </a>
        </div>

        <!-- Card 2: E-ID Card & Akomodasi -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 rounded-xl bg-gold-100 text-gold-700 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">2. E-ID Card & Akomodasi</h3>
            <p class="text-slate-600 text-sm leading-relaxed mb-4">
                Setiap peserta terverifikasi mendapatkan barcode QR Code unik, penetapan kamar wisma penginapan, dan pembagian komisi sidang.
            </p>
            <a href="<?= BASE_URL ?>daftar_peserta.php" class="text-sm font-semibold text-gold-700 hover:text-gold-800 inline-flex items-center gap-1">
                Lihat Daftar Peserta &rarr;
            </a>
        </div>

        <!-- Card 3: Presensi Cepat di Lokasi -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
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
