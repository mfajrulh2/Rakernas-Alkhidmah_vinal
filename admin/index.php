<?php
/**
 * Dashboard Super Admin / Panitia Pusat
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('superadmin');

$pdo = getDB();

// Hitung metrik penting
$totalPeserta = (int)$pdo->query("SELECT COUNT(*) FROM participants")->fetchColumn();
$totalHadir   = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE is_attended = 1")->fetchColumn();
$totalKit     = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE kit_received = 1")->fetchColumn();
$totalPW      = (int)$pdo->query("SELECT COUNT(*) FROM wilayah")->fetchColumn();

// Breakdown Kategori
$countPP = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE kategori = 'PP' OR (kategori IS NULL AND participant_type = 'pengurus_pusat')")->fetchColumn();
$countPW = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE kategori = 'PW' OR (kategori IS NULL AND participant_type IN ('utusan', 'peninjau'))")->fetchColumn();
$countPD = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE kategori = 'PD'")->fetchColumn();
$countTamu = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE kategori = 'Tamu Undangan' OR (kategori IS NULL AND participant_type = 'undangan')")->fetchColumn();

// Kapasitas kamar
$totalKapasitasKamar = (int)$pdo->query("SELECT COALESCE(SUM(capacity), 0) FROM rooms")->fetchColumn();
$totalKamarTerisi    = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE room_id IS NOT NULL")->fetchColumn();

// Persentase kehadiran
$persenHadir = $totalPeserta > 0 ? round(($totalHadir / $totalPeserta) * 100, 1) : 0;
$persenKit   = $totalPeserta > 0 ? round(($totalKit / $totalPeserta) * 100, 1) : 0;

// Rekapitulasi per Wilayah
$stmtPW = $pdo->query("
    SELECT w.id, w.name, w.code, w.quota_utusan, w.quota_peninjau,
           COUNT(p.id) as total_delegasi,
           SUM(CASE WHEN p.is_attended = 1 THEN 1 ELSE 0 END) as total_hadir,
           SUM(CASE WHEN p.participant_type = 'utusan' THEN 1 ELSE 0 END) as utusan_count,
           SUM(CASE WHEN p.participant_type = 'peninjau' THEN 1 ELSE 0 END) as peninjau_count
    FROM wilayah w
    LEFT JOIN participants p ON w.id = p.pw_id
    GROUP BY w.id
    ORDER BY total_delegasi DESC, w.name ASC
");
$rekapPW = $stmtPW->fetchAll();

// 10 Pendaftar Terakhir (dengan info group dan kepengurusan)
$stmtRecentReg = $pdo->query("
    SELECT p.*, 
           w.name as pw_name, 
           g.group_code, g.total_participants as grp_total, g.total_amount as grp_amount
    FROM participants p
    LEFT JOIN wilayah w ON p.pw_id = w.id
    LEFT JOIN registration_groups g ON p.group_id = g.id
    ORDER BY p.id DESC
    LIMIT 10
");
$recentRegistrations = $stmtRecentReg->fetchAll();

// 5 Peserta Terakhir yang Check-in
$stmtRecent = $pdo->query("
    SELECT p.*, w.name as pw_name, r.building_name, r.room_number 
    FROM participants p
    LEFT JOIN wilayah w ON p.pw_id = w.id
    LEFT JOIN rooms r ON p.room_id = r.id
    WHERE p.is_attended = 1
    ORDER BY p.attended_at DESC
    LIMIT 5
");
$recentCheckins = $stmtRecent->fetchAll();

$page_title = "Dashboard Panitia Pusat";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8 mb-12">
    <!-- Welcome Header Banner -->
    <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs font-semibold uppercase tracking-wider mb-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Pusat Kendali Panitia Rakernas
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Selamat Datang, <?= clean($user['full_name']) ?>
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Pantau pendaftaran online mandiri (PP, PW, PD, Tamu Undangan), distribusi kamar, dan presensi check-in real-time.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="<?= BASE_URL ?>registrasi.php" target="_blank" class="px-4 py-2.5 rounded-2xl bg-white border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition shadow-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                <span>Buka Form Registrasi</span>
            </a>
            <a href="<?= BASE_URL ?>admin/scan.php" class="px-5 py-2.5 rounded-2xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition shadow-md shadow-emerald-700/20 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                <span>Scanner Presensi</span>
            </a>
            <a href="<?= BASE_URL ?>admin/participant_add.php" class="px-4 py-2.5 rounded-2xl bg-slate-900 text-white font-semibold text-xs hover:bg-slate-800 transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Tambah Peserta</span>
            </a>
        </div>
    </div>

    <!-- Quick Stat Cards: Total & Kehadiran -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Card 1: Total Peserta -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Delegasi</span>
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-900"><?= number_format($totalPeserta) ?></div>
            <div class="text-xs text-slate-500 mt-2 flex items-center justify-between">
                <span>Dari <strong><?= $totalPW ?></strong> Wilayah</span>
                <a href="<?= BASE_URL ?>admin/participants.php" class="text-emerald-700 font-bold hover:underline">Kelola &rarr;</a>
            </div>
        </div>

        <!-- Card 2: Kehadiran / Presensi -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Presensi Hadir</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-emerald-800"><?= number_format($totalHadir) ?></div>
            <div class="mt-2 w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-emerald-600 h-2 rounded-full transition-all duration-500" style="width: <?= $persenHadir ?>%"></div>
            </div>
            <div class="text-[11px] text-slate-500 mt-1.5 flex justify-between">
                <span>Tiba di lokasi:</span>
                <span class="font-bold text-emerald-700"><?= $persenHadir ?>%</span>
            </div>
        </div>

        <!-- Card 3: Kit Rakernas -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700">Kit Rakernas</span>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-amber-900"><?= number_format($totalKit) ?></div>
            <div class="mt-2 w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-amber-500 h-2 rounded-full transition-all duration-500" style="width: <?= $persenKit ?>%"></div>
            </div>
            <div class="text-[11px] text-slate-500 mt-1.5 flex justify-between">
                <span>Distribusi Goodie Bag:</span>
                <span class="font-bold text-amber-700"><?= $persenKit ?>%</span>
            </div>
        </div>

        <!-- Card 4: Alokasi Kamar -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-700">Kamar Terisi</span>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-indigo-900"><?= number_format($totalKamarTerisi) ?> <span class="text-sm font-normal text-slate-500">/ <?= $totalKapasitasKamar ?></span></div>
            <div class="text-[11px] text-slate-500 mt-2 flex items-center justify-between">
                <span>Sisa Tempat Tidur:</span>
                <span class="font-bold text-indigo-700"><?= max(0, $totalKapasitasKamar - $totalKamarTerisi) ?> bed</span>
            </div>
        </div>
    </div>

    <!-- Category Breakdown Ribbon -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-5 shadow-xs">
        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center justify-between">
            <span>Komposisi Delegasi Terdaftar Berdasarkan Kategori</span>
            <span class="text-[11px] font-medium text-slate-400">Total: <?= number_format($totalPeserta) ?> Peserta</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <!-- PP -->
            <a href="<?= BASE_URL ?>admin/participants.php?kategori=PP" class="p-4 rounded-2xl bg-blue-50/60 border border-blue-100 hover:border-blue-300 hover:bg-blue-50 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-blue-900">Pengurus Pusat (PP)</span>
                    <span class="text-[10px] font-bold bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">Tanpa Batas</span>
                </div>
                <div class="text-2xl font-black text-blue-950 mt-2 group-hover:translate-x-0.5 transition"><?= number_format($countPP) ?> <span class="text-xs font-medium text-blue-700">orang</span></div>
            </a>
            <!-- PW -->
            <a href="<?= BASE_URL ?>admin/participants.php?kategori=PW" class="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-100 hover:border-emerald-300 hover:bg-emerald-50 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-emerald-900">Pengurus Wilayah (PW)</span>
                    <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">Maks 10</span>
                </div>
                <div class="text-2xl font-black text-emerald-950 mt-2 group-hover:translate-x-0.5 transition"><?= number_format($countPW) ?> <span class="text-xs font-medium text-emerald-700">orang</span></div>
            </a>
            <!-- PD -->
            <a href="<?= BASE_URL ?>admin/participants.php?kategori=PD" class="p-4 rounded-2xl bg-amber-50/60 border border-amber-100 hover:border-amber-300 hover:bg-amber-50 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-amber-900">Pengurus Daerah (PD)</span>
                    <span class="text-[10px] font-bold bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full">Maks 5</span>
                </div>
                <div class="text-2xl font-black text-amber-950 mt-2 group-hover:translate-x-0.5 transition"><?= number_format($countPD) ?> <span class="text-xs font-medium text-amber-700">orang</span></div>
            </a>
            <!-- Tamu Undangan -->
            <a href="<?= BASE_URL ?>admin/participants.php?kategori=Tamu+Undangan" class="p-4 rounded-2xl bg-purple-50/60 border border-purple-100 hover:border-purple-300 hover:bg-purple-50 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-purple-900">Tamu Undangan</span>
                    <span class="text-[10px] font-bold bg-purple-100 text-purple-800 px-2 py-0.5 rounded-full">Tanpa Batas</span>
                </div>
                <div class="text-2xl font-black text-purple-950 mt-2 group-hover:translate-x-0.5 transition"><?= number_format($countTamu) ?> <span class="text-xs font-medium text-purple-700">orang</span></div>
            </a>
        </div>
    </div>

    <!-- Pendaftaran & Delegasi Terkini (Synced with Online Registration) -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>Pendaftaran Terkini Hasil Registrasi Online</span>
                    <span class="text-xs font-bold px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full">Sinkron Real-time</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Daftar 10 pendaftar delegasi terbaru yang masuk melalui formulir registrasi online</p>
            </div>
            <a href="<?= BASE_URL ?>admin/participants.php" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 flex items-center gap-1">
                <span>Buka Manajemen Peserta Lengkap</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4">Kode & Kategori</th>
                        <th class="py-3 px-4">Nama Lengkap & Kontak</th>
                        <th class="py-3 px-4">Asal Kepengurusan</th>
                        <th class="py-3 px-4">Komisi & Kedatangan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-6 text-right">Aksi Singkron</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentRegistrations)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400">Belum ada data pendaftaran yang tersimpan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentRegistrations as $r): 
                            $kat = $r['kategori'] ?? ($r['participant_type'] === 'pengurus_pusat' ? 'PP' : ($r['participant_type'] === 'undangan' ? 'Tamu Undangan' : 'PW'));
                            $photoPath = !empty($r['photo']) ? $r['photo'] : (!empty($r['photo_file']) ? $r['photo_file'] : null);
                        ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4">
                                    <div class="font-mono font-bold text-slate-900 text-xs"><?= clean($r['registration_code']) ?></div>
                                    <div class="mt-1 flex items-center gap-1 flex-wrap">
                                        <?php if ($kat === 'PP'): ?>
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-black bg-blue-50 text-blue-800 border border-blue-200">PP</span>
                                        <?php elseif ($kat === 'PW'): ?>
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-black bg-emerald-50 text-emerald-800 border border-emerald-200">PW</span>
                                        <?php elseif ($kat === 'PD'): ?>
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-black bg-amber-50 text-amber-800 border border-amber-200">PD</span>
                                        <?php else: ?>
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-black bg-purple-50 text-purple-800 border border-purple-200">Tamu</span>
                                        <?php endif; ?>

                                        <?php if (!empty($r['is_group_leader'])): ?>
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-900" title="Ketua Rombongan">Ketua</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($r['group_code'])): ?>
                                        <div class="text-[9px] font-mono text-slate-400 mt-0.5">Grp: <?= clean($r['group_code']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <?php if (!empty($photoPath) && file_exists(__DIR__ . '/../' . $photoPath)): ?>
                                            <img src="<?= BASE_URL . clean($photoPath) ?>" alt="Foto" class="w-8 h-10 object-cover rounded-lg border border-slate-200 shrink-0 shadow-xs">
                                        <?php else: ?>
                                            <div class="w-8 h-10 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center text-xs font-bold shrink-0">
                                                <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="<?= BASE_URL ?>admin/participant_detail.php?id=<?= $r['id'] ?>" class="font-bold text-slate-900 hover:text-emerald-700 transition">
                                                <?= clean($r['full_name']) ?>
                                            </a>
                                            <div class="text-[11px] text-slate-400 font-mono">WA: <?= clean($r['phone'] ?: '-') ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-800">
                                        <?php 
                                        if ($kat === 'PP') echo "Pengurus Pusat (PP)";
                                        elseif ($kat === 'Tamu Undangan') echo "Tamu Undangan Khusus";
                                        elseif ($kat === 'PW') echo clean($r['pw_name']);
                                        else echo clean($r['pd_name'] ?: '-');
                                        ?>
                                    </div>
                                    <?php if ($kat === 'PD' && !empty($r['pw_name'])): ?>
                                        <div class="text-[10px] text-slate-400"><?= clean($r['pw_name']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-[10px] text-slate-500 font-medium"><?= clean($r['jabatan_organisasi'] ?: '-') ?></div>
                                </td>

                                <td class="py-3 px-4">
                                    <?php if ($kat !== 'Tamu Undangan'): ?>
                                        <div class="text-[11px] text-slate-700 font-medium"><?= clean($r['komisi'] ?: '-') ?></div>
                                    <?php else: ?>
                                        <div class="text-[11px] text-purple-700 font-medium">Tamu Khusus</div>
                                    <?php endif; ?>
                                    <div class="text-[10px] text-slate-400">
                                        <?= clean($r['arrival_day'] ?: '-') ?> <?= clean($r['arrival_time'] ?? '') ?>
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-center">
                                    <?php if ($r['is_attended']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Hadir</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-500">Terdaftar</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-6 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Detail Pendaftar -->
                                        <a href="<?= BASE_URL ?>admin/participant_detail.php?id=<?= $r['id'] ?>" title="Lihat Detail Profil & Rombongan" class="p-1.5 text-slate-400 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <!-- Cetak Bukti Pendaftaran Resmi Baru -->
                                        <a href="<?= BASE_URL ?>print_proof.php?code=<?= urlencode($r['registration_code']) ?>" target="_blank" title="Cetak Bukti Pendaftaran Resmi (A4)" class="p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </a>
                                        <!-- Edit Data -->
                                        <a href="<?= BASE_URL ?>admin/participant_edit.php?id=<?= $r['id'] ?>" title="Edit Data" class="p-1.5 text-slate-400 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <!-- Hapus Data -->
                                        <a href="<?= BASE_URL ?>admin/participant_delete.php?id=<?= $r['id'] ?>&redirect=<?= urlencode(BASE_URL . 'admin/index.php') ?>" onclick="return confirm('Hapus peserta <?= clean($r['full_name']) ?>?')" title="Hapus" class="p-1.5 text-slate-400 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2 Column Layout: Rekapitulasi PW & Kehadiran Terakhir -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Kolom Kiri (2 Span): Rekapitulasi PW -->
        <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Rekapitulasi Delegasi Tiap PW</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Pantauan kuota dan kehadiran per Pengurus Wilayah</p>
                </div>
                <a href="<?= BASE_URL ?>admin/participants.php" class="text-xs font-bold text-alkhidmah-700 hover:text-alkhidmah-900">
                    Lihat Semua &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider font-semibold border-b border-slate-100">
                        <tr>
                            <th class="py-3 px-6">Pengurus Wilayah</th>
                            <th class="py-3 px-4 text-center">Utusan / Peninjau</th>
                            <th class="py-3 px-4 text-center">Total Terdaftar</th>
                            <th class="py-3 px-4 text-center">Hadir</th>
                            <th class="py-3 px-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php foreach ($rekapPW as $pw): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-6">
                                    <div class="font-bold text-slate-800"><?= clean($pw['name']) ?></div>
                                    <div class="text-[10px] font-mono text-slate-400"><?= clean($pw['code']) ?></div>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="text-emerald-700 font-semibold"><?= (int)$pw['utusan_count'] ?></span>
                                    <span class="text-slate-300">/</span>
                                    <span class="text-amber-700 font-semibold"><?= (int)$pw['peninjau_count'] ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-bold text-slate-800">
                                    <?= (int)$pw['total_delegasi'] ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700">
                                        <?= (int)$pw['total_hadir'] ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-right">
                                    <a href="<?= BASE_URL ?>admin/participants.php?pw_id=<?= $pw['id'] ?>" class="text-xs font-semibold text-alkhidmah-700 hover:text-alkhidmah-900">
                                        Rincian &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kolom Kanan (1 Span): Aktivitas Presensi Check-in Terkini -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-slate-900">Check-in Terkini</h2>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                </div>
                <p class="text-xs text-slate-500 mb-4">5 peserta terakhir yang baru saja hadir di meja registrasi:</p>

                <?php if (empty($recentCheckins)): ?>
                    <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200 text-center text-xs text-slate-400 my-4">
                        Belum ada peserta yang check-in hari ini.
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recentCheckins as $r): ?>
                            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-bold text-xs text-slate-800"><?= clean($r['full_name']) ?></div>
                                    <div class="text-[10px] text-slate-500"><?= clean($r['pw_name']) ?></div>
                                    <div class="text-[9px] text-emerald-700 font-medium">Kamar: <?= clean($r['room_number'] ?? 'Belum ditentukan') ?></div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-mono font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md">
                                        <?= date('H:i', strtotime($r['attended_at'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tombol Cepat Ekspor / Cetak -->
            <div class="mt-6 pt-6 border-t border-slate-100 space-y-2">
                <a href="<?= BASE_URL ?>admin/export.php" class="w-full py-2.5 px-4 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Download Laporan Excel (CSV)</span>
                </a>
                <a href="<?= BASE_URL ?>admin/print_cards.php" class="w-full py-2.5 px-4 rounded-xl bg-alkhidmah-50 hover:bg-alkhidmah-100 text-alkhidmah-800 text-xs font-bold transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 text-alkhidmah-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Massal ID Card (A4 Sheet)</span>
                </a>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
