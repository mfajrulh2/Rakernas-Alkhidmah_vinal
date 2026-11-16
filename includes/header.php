<?php
/**
 * Header Template
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

if (!defined('APP_INIT')) {
    require_once __DIR__ . '/../config/database.php';
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$page_title = $page_title ?? APP_NAME;
$user = currentUser();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($page_title) ?> - Jama'ah Al Khidmah</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        alkhidmah: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                            950: '#022c22',
                        },
                        gold: {
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/custom.css">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Top Notification / Announcement Bar (Khidmah Theme) -->
    <div class="bg-gradient-to-r from-alkhidmah-950 via-alkhidmah-900 to-alkhidmah-800 text-gold-400 py-1.5 px-4 text-xs font-medium text-center tracking-wide border-b border-alkhidmah-700/50 flex justify-between items-center shadow-inner no-print">
        <div class="container mx-auto flex items-center justify-between">
            <span class="flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span><?= clean(getSetting('event_name', 'RAKERNAS PERKUMPULAN JAMA\'AH AL KHIDMAH')) ?></span>
            </span>
            <span class="hidden md:inline text-emerald-200 text-[11px]">
                <?= clean(getSetting('event_date', '15 - 17 November 2026')) ?> • <?= clean(getSetting('event_location', 'Pondok Pesantren Al Fithrah')) ?>
            </span>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Brand Logo & Title -->
                <?php
                $appLogoUrl = getAppLogoUrl();
                $brandTitle = getSetting('app_brand_title', 'AL KHIDMAH');
                $brandSubtitle = getSetting('app_brand_subtitle', 'Sistem Rakernas');
                ?>
                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>" class="flex items-center gap-3 group">
                        <?php if ($appLogoUrl): ?>
                            <img src="<?= $appLogoUrl ?>" alt="Logo" class="w-10 h-10 object-contain rounded-xl shadow-sm group-hover:scale-105 transition-transform">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-alkhidmah-700 to-alkhidmah-900 flex items-center justify-center text-white shadow-md shadow-emerald-900/20 group-hover:scale-105 transition-transform">
                                <!-- Emblem Icon -->
                                <svg class="w-6 h-6 text-gold-400" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="font-extrabold text-alkhidmah-900 text-lg leading-tight tracking-tight"><?= clean($brandTitle) ?></div>
                            <div class="text-[10px] uppercase tracking-widest font-semibold text-alkhidmah-600"><?= clean($brandSubtitle) ?></div>
                        </div>
                    </a>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center gap-1 font-medium text-sm">
                    <?php if (!$user): ?>
                        <!-- Menu Publik Sesuai Permintaan: Beranda, Daftar Peserta, Jadwal & Materi, Login Admin Area -->
                        <a href="<?= BASE_URL ?>index.php" class="px-3.5 py-2 rounded-xl <?= $current_page == 'index.php' ? 'text-alkhidmah-800 font-extrabold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50 font-medium' ?> transition">Beranda</a>
                        <a href="<?= BASE_URL ?>daftar_peserta.php" class="px-3.5 py-2 rounded-xl <?= $current_page == 'daftar_peserta.php' ? 'text-alkhidmah-800 font-extrabold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50 font-medium' ?> transition">Daftar Peserta</a>
                        <a href="<?= BASE_URL ?>rundown.php" class="px-3.5 py-2 rounded-xl <?= $current_page == 'rundown.php' ? 'text-alkhidmah-800 font-extrabold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50 font-medium' ?> transition">Jadwal & Materi</a>
                        <div class="ml-4 pl-4 border-l border-slate-200">
                            <a href="<?= BASE_URL ?>login.php" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-alkhidmah-800 text-white font-bold hover:bg-alkhidmah-900 transition shadow-sm text-xs">
                                <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                                <span>Login Admin Area</span>
                            </a>
                        </div>

                    <?php elseif ($user['role'] === 'superadmin'): ?>
                        <!-- Menu Super Admin -->
                        <a href="<?= BASE_URL ?>admin/index.php" class="px-3 py-2 rounded-lg <?= $current_page == 'index.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Dashboard</a>
                        <a href="<?= BASE_URL ?>admin/participants.php" class="px-3 py-2 rounded-lg <?= in_array($current_page, ['participants.php', 'participant_add.php', 'participant_edit.php']) ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Data Peserta</a>
                        <a href="<?= BASE_URL ?>admin/pw_accounts.php" class="px-3 py-2 rounded-lg <?= $current_page == 'pw_accounts.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">PW & Kuota</a>
                        <a href="<?= BASE_URL ?>admin/daerah.php" class="px-3 py-2 rounded-lg <?= $current_page == 'daerah.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Master PD</a>
                        <a href="<?= BASE_URL ?>admin/rooms.php" class="px-3 py-2 rounded-lg <?= $current_page == 'rooms.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Penginapan</a>
                        <a href="<?= BASE_URL ?>admin/scan.php" class="px-3 py-2 rounded-lg bg-emerald-100 text-emerald-800 font-bold hover:bg-emerald-200 transition flex items-center gap-1.5 shadow-sm">
                            <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                            Scan Presensi
                        </a>
                        <a href="<?= BASE_URL ?>admin/print_cards.php" class="px-3 py-2 rounded-lg <?= $current_page == 'print_cards.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Cetak ID Card</a>
                        <a href="<?= BASE_URL ?>admin/schedules.php" class="px-3 py-2 rounded-lg <?= $current_page == 'schedules.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Jadwal</a>
                        <a href="<?= BASE_URL ?>admin/settings.php" class="px-3 py-2 rounded-lg <?= $current_page == 'settings.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Pengaturan</a>
                        <?php $pendingPay = countPendingPayments(); ?>
                        <a href="<?= BASE_URL ?>admin/payments.php" class="relative px-3 py-2 rounded-lg <?= $current_page == 'payments.php' ? 'text-amber-700 font-bold bg-amber-50' : 'text-slate-600 hover:text-amber-700 hover:bg-amber-50' ?> transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Pembayaran
                            <?php if ($pendingPay > 0): ?>
                                <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-extrabold bg-rose-500 text-white rounded-full"><?= $pendingPay ?></span>
                            <?php endif; ?>
                        </a>

                    <?php elseif ($user['role'] === 'admin_pw'): ?>
                        <!-- Menu Admin PW -->
                        <?php $pwPayInfo = getPWPaymentInfo($user['pw_id']); ?>
                        <a href="<?= BASE_URL ?>pw/index.php" class="px-3 py-2 rounded-lg <?= $current_page == 'index.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Dashboard PW</a>
                        <a href="<?= BASE_URL ?>pw/participants.php" class="px-3 py-2 rounded-lg <?= $current_page == 'participants.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Daftar Delegasi</a>
                        <a href="<?= BASE_URL ?>pw/register_bulk.php" class="px-3 py-2 rounded-lg bg-emerald-50 text-emerald-800 font-semibold hover:bg-emerald-100 transition flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Daftarkan Kolektif
                        </a>
                        <a href="<?= BASE_URL ?>pw/payment.php" class="px-3 py-2 rounded-lg <?= $current_page == 'payment.php' ? 'text-amber-700 font-bold bg-amber-50' : 'text-slate-600 hover:text-amber-700 hover:bg-amber-50' ?> transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Iuran/Bayar
                            <?= renderPaymentBadge($pwPayInfo['status']) ?>
                        </a>
                        <a href="<?= BASE_URL ?>pw/print_cards.php" class="px-3 py-2 rounded-lg <?= $current_page == 'print_cards.php' ? 'text-alkhidmah-700 font-bold bg-alkhidmah-50' : 'text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50' ?> transition">Cetak ID Card PW</a>

                    <?php elseif ($user['role'] === 'petugas_scan'): ?>
                        <!-- Menu Petugas Scan -->
                        <a href="<?= BASE_URL ?>admin/scan.php" class="px-3 py-2 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                            Scan Presensi & Kit
                        </a>
                        <a href="<?= BASE_URL ?>admin/participants.php" class="px-3 py-2 rounded-lg text-slate-600 hover:text-alkhidmah-700 hover:bg-slate-50 transition">Data Presensi</a>
                    <?php endif; ?>

                    <?php if ($user): ?>
                        <!-- User Profile Dropdown / Logout Info -->
                        <div class="ml-4 pl-4 border-l border-slate-200 flex items-center gap-3">
                            <div class="text-right">
                                <div class="text-xs font-bold text-slate-800 leading-tight"><?= clean($user['full_name']) ?></div>
                                <div class="text-[10px] text-slate-500 font-medium">
                                    <?= $user['role'] === 'superadmin' ? 'Panitia Pusat' : ($user['role'] === 'admin_pw' ? clean($user['pw_name']) : 'Petugas Scan') ?>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>logout.php" title="Keluar / Logout" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" onclick="return confirm('Apakah Anda yakin ingin logout?')">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile Hamburger Button -->
                <div class="flex items-center md:hidden">
                    <button id="mobile-menu-btn" type="button" class="p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-200 bg-white px-4 pt-2 pb-4 space-y-1">
            <?php if (!$user): ?>
                <a href="<?= BASE_URL ?>index.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Beranda</a>
                <a href="<?= BASE_URL ?>daftar_peserta.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Daftar Peserta</a>
                <a href="<?= BASE_URL ?>rundown.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Jadwal & Materi</a>
                <a href="<?= BASE_URL ?>login.php" class="block px-3 py-2.5 rounded-xl text-base font-bold bg-alkhidmah-800 text-white text-center mt-3 shadow-sm">Login Admin Area</a>
            <?php elseif ($user['role'] === 'superadmin'): ?>
                <a href="<?= BASE_URL ?>admin/index.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Dashboard</a>
                <a href="<?= BASE_URL ?>admin/participants.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Data Peserta</a>
                <a href="<?= BASE_URL ?>admin/pw_accounts.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">PW & Kuota</a>
                <a href="<?= BASE_URL ?>admin/daerah.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Master PD</a>
                <a href="<?= BASE_URL ?>admin/rooms.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Penginapan</a>
                <a href="<?= BASE_URL ?>admin/scan.php" class="block px-3 py-2 rounded-md text-base font-medium bg-emerald-50 text-emerald-800 font-bold">Scan Presensi</a>
                <a href="<?= BASE_URL ?>admin/print_cards.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Cetak ID Card</a>
                <a href="<?= BASE_URL ?>admin/schedules.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Jadwal Rundown</a>
                <a href="<?= BASE_URL ?>admin/settings.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Pengaturan Kegiatan</a>
                <a href="<?= BASE_URL ?>admin/payments.php" class="block px-3 py-2 rounded-md text-base font-medium text-amber-700 bg-amber-50">💳 Validasi Pembayaran</a>
                <a href="<?= BASE_URL ?>logout.php" class="block px-3 py-2 rounded-md text-base font-medium text-rose-600 mt-2">Logout (<?= clean($user['username']) ?>)</a>
            <?php elseif ($user['role'] === 'admin_pw'): ?>
                <a href="<?= BASE_URL ?>pw/index.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Dashboard PW</a>
                <a href="<?= BASE_URL ?>pw/participants.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Daftar Delegasi</a>
                <a href="<?= BASE_URL ?>pw/register_bulk.php" class="block px-3 py-2 rounded-md text-base font-medium bg-emerald-50 text-emerald-800 font-semibold">Daftarkan Kolektif</a>
                <a href="<?= BASE_URL ?>pw/payment.php" class="block px-3 py-2 rounded-md text-base font-medium text-amber-700 bg-amber-50">💳 Iuran & Pembayaran</a>
                <a href="<?= BASE_URL ?>pw/print_cards.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Cetak ID Card PW</a>
                <a href="<?= BASE_URL ?>logout.php" class="block px-3 py-2 rounded-md text-base font-medium text-rose-600 mt-2">Logout (<?= clean($user['username']) ?>)</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>admin/scan.php" class="block px-3 py-2 rounded-md text-base font-medium bg-emerald-600 text-white">Scan Presensi</a>
                <a href="<?= BASE_URL ?>admin/participants.php" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700">Data Peserta</a>
                <a href="<?= BASE_URL ?>logout.php" class="block px-3 py-2 rounded-md text-base font-medium text-rose-600 mt-2">Logout</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="flex-grow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <?php displayFlash(); ?>
