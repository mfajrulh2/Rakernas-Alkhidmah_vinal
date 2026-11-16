<?php
/**
 * Detail Data Delegasi & Informasi Rombongan - Super Admin
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['superadmin', 'petugas_scan']);

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmtP = $pdo->prepare("
    SELECT p.*, 
           w.name as pw_name, w.code as pw_code, 
           d.name as db_daerah_name, 
           r.building_name, r.room_number,
           g.group_code, g.pic_name, g.pic_phone, g.total_participants as g_total,
           g.fee_per_person, g.total_amount, g.payment_status as g_payment_status, g.created_at as g_created_at
    FROM participants p
    LEFT JOIN wilayah w ON p.pw_id = w.id
    LEFT JOIN daerah d ON p.daerah_id = d.id
    LEFT JOIN rooms r ON p.room_id = r.id
    LEFT JOIN registration_groups g ON p.group_id = g.id
    WHERE p.id = ? LIMIT 1
");
$stmtP->execute([$id]);
$p = $stmtP->fetch();

if (!$p) {
    setFlash('danger', 'Data peserta tidak ditemukan.');
    header("Location: " . BASE_URL . "admin/participants.php");
    exit;
}

// Ambil anggota rombongan lain jika tergabung dalam group
$companions = [];
if (!empty($p['group_id'])) {
    $stmtComp = $pdo->prepare("
        SELECT * FROM participants 
        WHERE group_id = ? AND id != ?
        ORDER BY is_group_leader DESC, id ASC
    ");
    $stmtComp->execute([$p['group_id'], $p['id']]);
    $companions = $stmtComp->fetchAll();
}

$kat = $p['kategori'] ?? ($p['participant_type'] === 'pengurus_pusat' ? 'PP' : ($p['participant_type'] === 'undangan' ? 'Tamu Undangan' : 'PW'));
$isGuest = ($kat === 'Tamu Undangan');
$photoPath = !empty($p['photo']) ? $p['photo'] : (!empty($p['photo_file']) ? $p['photo_file'] : null);

$page_title = "Detail Peserta - " . clean($p['full_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6 mb-20">

    <!-- Header & Navigation Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="<?= BASE_URL ?>admin/participants.php" class="text-xs font-bold text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Kembali ke Data Peserta</span>
            </a>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Detail Profil Peserta</h1>
            <p class="text-xs text-slate-500 mt-0.5">Informasi lengkap identitas, kepengurusan, perjalanan, dan rombongan</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= BASE_URL ?>print_proof.php?code=<?= urlencode($p['registration_code']) ?>" target="_blank" class="px-3.5 py-2 rounded-xl bg-emerald-800 text-white font-bold text-xs hover:bg-emerald-900 transition flex items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Cetak Bukti Pendaftaran</span>
            </a>
            <a href="<?= BASE_URL ?>print_card.php?code=<?= urlencode($p['registration_code']) ?>" target="_blank" class="px-3.5 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition flex items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak ID Card</span>
            </a>
            <a href="<?= BASE_URL ?>admin/participant_edit.php?id=<?= $p['id'] ?>" class="px-3.5 py-2 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 font-bold text-xs hover:bg-blue-100 transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span>Edit</span>
            </a>
            <?php if (isSuperAdmin()): ?>
                <a href="<?= BASE_URL ?>admin/participant_delete.php?id=<?= $p['id'] ?>" onclick="return confirm('Hapus data peserta <?= clean($p['full_name']) ?>? Data tidak dapat dikembalikan.')" class="px-3 py-2 rounded-xl text-rose-600 hover:bg-rose-50 border border-rose-200 font-bold text-xs transition flex items-center gap-1">
                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Hapus</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Card Profil Utama -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 text-center sm:text-left">
            <!-- Foto Profil -->
            <?php if (!empty($photoPath) && file_exists(__DIR__ . '/../' . $photoPath)): ?>
                <img src="<?= BASE_URL . clean($photoPath) ?>" alt="Foto <?= clean($p['full_name']) ?>" class="w-24 h-28 object-cover rounded-2xl border-2 border-emerald-500 shadow-md shrink-0">
            <?php else: ?>
                <div class="w-24 h-28 rounded-2xl bg-gradient-to-br from-emerald-700 to-emerald-900 text-white flex flex-col items-center justify-center font-black text-2xl border-2 border-emerald-500 shadow-md shrink-0">
                    <span><?= strtoupper(substr($p['full_name'], 0, 1)) ?></span>
                    <span class="text-[9px] font-sans font-medium text-emerald-200 mt-1">NO PHOTO</span>
                </div>
            <?php endif; ?>

            <div class="space-y-2">
                <div class="flex items-center gap-2 flex-wrap justify-center sm:justify-start">
                    <span class="font-mono text-xs font-black px-2.5 py-0.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-300">
                        <?= clean($p['registration_code']) ?>
                    </span>
                    
                    <!-- Badge Kategori -->
                    <?php if ($kat === 'PP'): ?>
                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-blue-100 text-blue-900 border border-blue-300">Pengurus Pusat (PP)</span>
                    <?php elseif ($kat === 'PW'): ?>
                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-emerald-100 text-emerald-900 border border-emerald-300">Pengurus Wilayah (PW)</span>
                    <?php elseif ($kat === 'PD'): ?>
                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-amber-100 text-amber-900 border border-amber-300">Pengurus Daerah (PD)</span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-purple-100 text-purple-900 border border-purple-300">Tamu Undangan</span>
                    <?php endif; ?>

                    <?php if (!empty($p['is_group_leader'])): ?>
                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300">
                            Ketua Rombongan
                        </span>
                    <?php endif; ?>
                </div>

                <h2 class="text-xl sm:text-2xl font-black text-slate-900"><?= clean($p['full_name']) ?></h2>
                <p class="text-xs text-slate-500 font-medium">
                    <?= clean($p['jabatan_organisasi'] ?: ($p['unsur_organisasi'] ?? '-')) ?> • 
                    <strong>
                        <?php 
                        if ($kat === 'PP') echo "Pengurus Pusat Al Khidmah";
                        elseif ($kat === 'Tamu Undangan') echo "Tamu Undangan";
                        elseif ($kat === 'PW') echo clean($p['pw_name']);
                        else echo clean($p['pd_name'] ?: ($p['db_daerah_name'] ?? '-')) . " (" . clean($p['pw_name']) . ")";
                        ?>
                    </strong>
                </p>

                <div class="flex items-center gap-3 pt-1 text-xs justify-center sm:justify-start">
                    <div><?= renderAttendanceBadge($p['is_attended'], $p['attended_at']) ?></div>
                    <div>
                        <?php if ($p['kit_received']): ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                Kit: Sudah Diambil
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-300">
                                Kit: Belum Diambil
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Code -->
        <div class="flex flex-col items-center justify-center p-3.5 bg-slate-50 rounded-2xl border border-slate-200 text-center shrink-0">
            <img src="<?= getQRCodeUrl($p['registration_code'], 140) ?>" alt="QR Code" class="w-24 h-24 object-contain rounded-lg shadow-2xs">
            <span class="text-[10px] font-mono font-bold text-slate-700 mt-1.5"><?= clean($p['registration_code']) ?></span>
            <span class="text-[9px] text-slate-400">Barcode Check-in Presensi</span>
        </div>
    </div>

    <!-- Grid Data Rincian 4 Kotak -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- 1. Identitas Personal -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </span>
                <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Identitas Pribadi</h3>
            </div>

            <div class="space-y-3 text-xs">
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Nama Lengkap</span>
                    <span class="font-bold text-slate-900"><?= clean($p['full_name']) ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Jenis Kelamin</span>
                    <span class="font-semibold text-slate-800"><?= $p['gender'] === 'P' ? 'Perempuan (Akhwat)' : 'Laki-laki (Ikhwan)' ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Nomor WhatsApp</span>
                    <span class="font-mono font-bold text-emerald-800">
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $p['phone']) ?>" target="_blank" class="hover:underline flex items-center gap-1">
                            <?= clean($p['phone']) ?>
                            <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0012.04 2z"/></svg>
                        </a>
                    </span>
                </div>
                <?php if (!empty($p['nik'])): ?>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">NIK (KTP)</span>
                        <span class="font-mono text-slate-700"><?= clean($p['nik']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($p['email'])): ?>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Email</span>
                        <span class="text-slate-700"><?= clean($p['email']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400">Waktu Terdaftar</span>
                    <span class="font-mono text-slate-600"><?= !empty($p['created_at']) ? date('d F Y - H:i', strtotime($p['created_at'])) . ' WIB' : '-' ?></span>
                </div>
            </div>
        </div>

        <!-- 2. Data Organisasi & Sidang -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
                <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Organisasi & Komisi Sidang</h3>
            </div>

            <div class="space-y-3 text-xs">
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Kategori Kepengurusan</span>
                    <span class="font-bold text-slate-900"><?= clean($kat) ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Pengurus Wilayah (PW)</span>
                    <span class="font-semibold text-slate-800"><?= clean($p['pw_name'] ?: 'Pusat') ?></span>
                </div>
                <?php if ($kat === 'PD' || !empty($p['pd_name']) || !empty($p['db_daerah_name'])): ?>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Pengurus Daerah (PD)</span>
                        <span class="font-semibold text-slate-800"><?= clean($p['pd_name'] ?: ($p['db_daerah_name'] ?? '-')) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!$isGuest): ?>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Unsur Organisasi</span>
                        <span><?= renderUnsurBadge($p['unsur_organisasi'] ?? 'Al Khidmah') ?></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Jabatan</span>
                        <span class="font-bold text-slate-800"><?= clean($p['jabatan_organisasi'] ?: '-') ?></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Komisi Sidang</span>
                        <span class="font-semibold text-emerald-800"><?= clean($p['komisi'] ?: '-') ?></span>
                    </div>
                <?php else: ?>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Klasifikasi Tamu</span>
                        <span class="font-bold text-purple-900">Tamu Undangan Khusus</span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400">Alokasi Kamar</span>
                    <span class="font-semibold <?= !empty($p['building_name']) ? 'text-indigo-700' : 'text-slate-400 italic' ?>">
                        <?= !empty($p['building_name']) ? clean($p['building_name']) . " - Kamar " . clean($p['room_number']) : 'Belum Ditentukan' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- 3. Informasi Perjalanan & Transportasi -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Jadwal Perjalanan & Kedatangan</h3>
            </div>

            <div class="space-y-3 text-xs">
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Hari Kedatangan</span>
                    <span class="font-bold text-slate-900"><?= clean($p['arrival_day'] ?: '-') ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Estimasi Jam Tiba</span>
                    <span class="font-mono font-bold text-emerald-800"><?= clean($p['arrival_time'] ?: '-') ?> WIB</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Hari Kepulangan</span>
                    <span class="font-bold text-slate-900"><?= clean($p['departure_day'] ?: '-') ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Estimasi Jam Pulang</span>
                    <span class="font-mono text-slate-700"><?= clean($p['departure_time'] ?: '-') ?> WIB</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400">Moda Transportasi</span>
                    <span class="font-semibold text-slate-800"><?= clean($p['transport_mode'] ?: ($p['transport_type'] ?? '-')) ?></span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400">Penjemputan Panitia</span>
                    <?php if (!empty($p['need_pickup'])): ?>
                        <span class="inline-flex items-center gap-1 font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">
                            Ya, Membutuhkan Penjemputan
                        </span>
                    <?php else: ?>
                        <span class="text-slate-500 font-medium">Tidak Membutuhkan Penjemputan</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($p['need_pickup'])): ?>
                    <div class="bg-amber-50/80 border border-amber-200/90 rounded-2xl p-3.5 space-y-2 mt-2">
                        <div class="flex items-center justify-between text-xs pb-1.5 border-b border-amber-200/60">
                            <span class="font-bold text-amber-950 flex items-center gap-1.5">
                                <span>🚐</span> Titik Penjemputan:
                            </span>
                            <span class="font-black text-amber-900"><?= clean($p['pickup_location'] ?: 'Stasiun / Bandara') ?></span>
                        </div>
                        <?php if (!empty($p['pickup_time'])): ?>
                            <div class="flex justify-between text-xs py-1 border-b border-amber-200/60">
                                <span class="text-slate-600 font-medium">🕒 Waktu Dikehendaki:</span>
                                <span class="font-bold text-slate-800"><?= clean($p['pickup_time']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($p['pickup_notes'])): ?>
                            <div class="text-xs pt-1">
                                <span class="font-bold text-amber-950 block mb-1">Catatan Panitia Transportasi:</span>
                                <div class="bg-white/90 p-2.5 rounded-xl border border-amber-100 text-slate-700 leading-relaxed font-mono text-[11px]">
                                    <?= nl2br(clean($p['pickup_notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4. Transaksi & Status Iuran -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xs">
                    Rp
                </span>
                <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Transaksi & Iuran Akomodasi</h3>
            </div>

            <div class="space-y-3 text-xs">
                <?php if (!empty($p['group_code'])): ?>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Kode Transaksi Rombongan</span>
                        <span class="font-mono font-black text-emerald-900"><?= clean($p['group_code']) ?></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Ketua Rombongan / PIC</span>
                        <span class="font-bold text-slate-800"><?= clean($p['pic_name']) ?></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Total Delegasi Rombongan</span>
                        <span class="font-bold text-slate-800"><?= (int)$p['g_total'] ?> Orang</span>
                    </div>
                <?php endif; ?>

                <?php if (!$isGuest): ?>
                    <div class="flex justify-between py-1 border-b border-slate-50">
                        <span class="text-slate-400">Tarif Iuran per Orang</span>
                        <span class="font-mono text-slate-700">Rp <?= number_format($p['fee_per_person'] ?: 300000, 0, ',', '.') ?></span>
                    </div>
                    <?php if (!empty($p['total_amount'])): ?>
                        <div class="flex justify-between py-1 border-b border-slate-50">
                            <span class="text-slate-400">Total Tagihan Rombongan</span>
                            <span class="font-mono font-black text-emerald-800 text-sm">Rp <?= number_format($p['total_amount'], 0, ',', '.') ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-400">Status Pembayaran</span>
                        <span><?= renderPaymentBadge($p['g_payment_status'] ?? 'unpaid') ?></span>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-purple-50 rounded-2xl border border-purple-200 text-purple-900 text-center font-bold">
                        Bebas Biaya Iuran (Kategori Tamu Undangan)
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- 5. Bagian Peserta Satu Rombongan (Jika Ada) -->
    <?php if (!empty($companions)): ?>
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900">Delegasi Lain dalam Transaksi Ini (<?= count($companions) ?> Orang)</h3>
                    <p class="text-xs text-slate-500">Didaftarkan bersamaan melalui kode transaksi <?= clean($p['group_code']) ?></p>
                </div>
                <a href="<?= BASE_URL ?>print_proof.php?group=<?= urlencode($p['group_code']) ?>" target="_blank" class="text-xs font-bold text-emerald-700 hover:underline">
                    Cetak Rombongan Lengkap &rarr;
                </a>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-bold">
                        <tr>
                            <th class="py-2.5 px-3 text-center w-8">No</th>
                            <th class="py-2.5 px-3">Kode</th>
                            <th class="py-2.5 px-4">Nama Lengkap</th>
                            <th class="py-2.5 px-3 text-center">L/P</th>
                            <th class="py-2.5 px-4">Jabatan</th>
                            <th class="py-2.5 px-4">Kedatangan</th>
                            <th class="py-2.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($companions as $cIdx => $c): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-2.5 px-3 text-center text-slate-400"><?= $cIdx + 1 ?></td>
                                <td class="py-2.5 px-3 font-mono font-bold text-emerald-800 text-[11px]"><?= clean($c['registration_code']) ?></td>
                                <td class="py-2.5 px-4 font-bold text-slate-900">
                                    <?= clean($c['full_name']) ?>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= clean($c['phone']) ?></div>
                                </td>
                                <td class="py-2.5 px-3 text-center font-bold"><?= clean($c['gender']) ?></td>
                                <td class="py-2.5 px-4 text-slate-600"><?= clean($c['jabatan_organisasi'] ?: '-') ?></td>
                                <td class="py-2.5 px-4 text-slate-600"><?= clean($c['arrival_day'] ?: '-') ?> <?= clean($c['arrival_time'] ?? '') ?></td>
                                <td class="py-2.5 px-4 text-right">
                                    <a href="<?= BASE_URL ?>admin/participant_detail.php?id=<?= $c['id'] ?>" class="text-xs font-bold text-emerald-700 hover:text-emerald-900">
                                        Lihat &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
