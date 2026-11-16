<?php
/**
 * Data Delegasi & Peserta Rakernas - Super Admin
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['superadmin', 'petugas_scan']);

$pdo = getDB();

// Handle quick actions: toggle kehadiran / kit
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $pId = (int)$_GET['id'];

    if ($action === 'toggle_attend') {
        $stmtCurr = $pdo->prepare("SELECT is_attended FROM participants WHERE id = ?");
        $stmtCurr->execute([$pId]);
        $curr = (int)$stmtCurr->fetchColumn();
        $newVal = $curr ? 0 : 1;
        $attendedAt = $newVal ? date('Y-m-d H:i:s') : null;

        $stmtUp = $pdo->prepare("UPDATE participants SET is_attended = ?, attended_at = ? WHERE id = ?");
        $stmtUp->execute([$newVal, $attendedAt, $pId]);

        setFlash('success', 'Status kehadiran peserta berhasil diubah.');
        header("Location: " . BASE_URL . "admin/participants.php?" . http_build_query(array_diff_key($_GET, ['action' => '', 'id' => ''])));
        exit;
    }

    if ($action === 'toggle_kit') {
        $stmtCurr = $pdo->prepare("SELECT kit_received FROM participants WHERE id = ?");
        $stmtCurr->execute([$pId]);
        $curr = (int)$stmtCurr->fetchColumn();
        $newVal = $curr ? 0 : 1;
        $kitAt = $newVal ? date('Y-m-d H:i:s') : null;

        $stmtUp = $pdo->prepare("UPDATE participants SET kit_received = ?, kit_received_at = ? WHERE id = ?");
        $stmtUp->execute([$newVal, $kitAt, $pId]);

        setFlash('success', 'Status penerimaan Kit Rakernas diperbarui.');
        header("Location: " . BASE_URL . "admin/participants.php?" . http_build_query(array_diff_key($_GET, ['action' => '', 'id' => ''])));
        exit;
    }
}

// Filter variables
$search       = trim($_GET['search'] ?? '');
$filterKat    = trim($_GET['kategori'] ?? '');
$filterPW     = trim($_GET['pw_id'] ?? '');
$filterType   = trim($_GET['type'] ?? '');
$filterUnsur  = trim($_GET['unsur'] ?? '');
$filterAttend = trim($_GET['attend'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.full_name LIKE :s OR p.phone LIKE :s OR p.nik LIKE :s OR p.registration_code LIKE :s OR p.pd_name LIKE :s OR g.group_code LIKE :s)";
    $params[':s'] = "%{$search}%";
}
if (!empty($filterKat)) {
    $where[] = "p.kategori = :kat";
    $params[':kat'] = $filterKat;
}
if (!empty($filterPW)) {
    $where[] = "p.pw_id = :pw";
    $params[':pw'] = $filterPW;
}
if (!empty($filterType)) {
    $where[] = "p.participant_type = :type";
    $params[':type'] = $filterType;
}
if (!empty($filterUnsur)) {
    $where[] = "p.unsur_organisasi = :unsur";
    $params[':unsur'] = $filterUnsur;
}
if ($filterAttend !== '') {
    $where[] = "p.is_attended = :attend";
    $params[':attend'] = (int)$filterAttend;
}

$whereClause = implode(" AND ", $where);

// Ambil list peserta lengkap dengan info group dan wilayah
$sql = "
    SELECT p.*, 
           w.name as pw_name, w.code as pw_code, 
           d.name as db_daerah_name,
           r.building_name, r.room_number,
           g.group_code
    FROM participants p 
    LEFT JOIN wilayah w ON p.pw_id = w.id 
    LEFT JOIN daerah d ON p.daerah_id = d.id
    LEFT JOIN rooms r ON p.room_id = r.id 
    LEFT JOIN registration_groups g ON p.group_id = g.id
    WHERE {$whereClause}
    ORDER BY p.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$participants = $stmt->fetchAll();

// Ambil list Wilayah untuk dropdown filter
$wilayahList = $pdo->query("SELECT id, name FROM wilayah ORDER BY name ASC")->fetchAll();
$unsurOptions = getUnsurOrganisasiOptions();

$page_title = "Data Delegasi & Peserta Rakernas";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 mb-16">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Data Peserta & Delegasi</h1>
            <p class="text-xs text-slate-500 mt-0.5">Kelola seluruh peserta terdaftar (PP, PW, PD, Tamu Undangan), rombongan, presensi, dan kamar</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= BASE_URL ?>registrasi.php" target="_blank" class="px-4 py-2.5 rounded-xl bg-emerald-700 text-white font-bold text-xs hover:bg-emerald-800 transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Buka Form Registrasi</span>
            </a>
            <a href="<?= BASE_URL ?>admin/participant_add.php" class="px-4 py-2.5 rounded-xl bg-slate-800 text-white font-bold text-xs hover:bg-slate-900 transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                <span>Tambah Manual</span>
            </a>
            <a href="<?= BASE_URL ?>admin/export.php" class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 font-semibold text-xs hover:bg-slate-50 transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-5 rounded-3xl border border-slate-200/90 shadow-sm">
        <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Cari Peserta</label>
                <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Nama / NIK / No. HP / Kode / Rombongan" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Kategori</label>
                <select name="kategori" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-medium">
                    <option value="">Semua Kategori</option>
                    <option value="PP" <?= $filterKat === 'PP' ? 'selected' : '' ?>>Pengurus Pusat (PP)</option>
                    <option value="PW" <?= $filterKat === 'PW' ? 'selected' : '' ?>>Pengurus Wilayah (PW)</option>
                    <option value="PD" <?= $filterKat === 'PD' ? 'selected' : '' ?>>Pengurus Daerah (PD)</option>
                    <option value="Tamu Undangan" <?= $filterKat === 'Tamu Undangan' ? 'selected' : '' ?>>Tamu Undangan</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Wilayah (PW)</label>
                <select name="pw_id" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                    <option value="">Semua Wilayah</option>
                    <?php foreach ($wilayahList as $w): ?>
                        <option value="<?= $w['id'] ?>" <?= $filterPW == $w['id'] ? 'selected' : '' ?>><?= clean($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Unsur Organisasi</label>
                <select name="unsur" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-medium">
                    <option value="">Semua Unsur</option>
                    <?php foreach ($unsurOptions as $uo): ?>
                        <option value="<?= clean($uo) ?>" <?= $filterUnsur === $uo ? 'selected' : '' ?>><?= clean($uo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <div class="flex-grow">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Presensi</label>
                    <select name="attend" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                        <option value="">Semua</option>
                        <option value="1" <?= $filterAttend === '1' ? 'selected' : '' ?>>Hadir</option>
                        <option value="0" <?= $filterAttend === '0' ? 'selected' : '' ?>>Belum Hadir</option>
                    </select>
                </div>
                <button type="submit" class="py-2 px-4 rounded-xl bg-slate-800 text-white font-bold text-xs hover:bg-slate-900 transition flex items-center justify-center">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="p-4 px-6 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span>Menampilkan <strong><?= count($participants) ?></strong> data peserta terdaftar</span>
            <a href="<?= BASE_URL ?>admin/print_cards.php?<?= http_build_query($_GET) ?>" target="_blank" class="font-bold text-alkhidmah-700 hover:text-alkhidmah-900 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak ID Card Filter Ini
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">Kode & Kategori</th>
                        <th class="py-3 px-4">Foto & Identitas Delegasi</th>
                        <th class="py-3 px-4">Asal Kepengurusan</th>
                        <th class="py-3 px-4">Komisi & Kedatangan</th>
                        <th class="py-3 px-4">Kamar</th>
                        <th class="py-3 px-4 text-center">Presensi / Kit</th>
                        <th class="py-3 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($participants)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-10 text-slate-400">Tidak ada data yang cocok dengan kriteria pencarian.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($participants as $p): 
                            $photoPath = !empty($p['photo']) ? $p['photo'] : (!empty($p['photo_file']) ? $p['photo_file'] : null);
                            $kat = $p['kategori'] ?? ($p['participant_type'] === 'pengurus_pusat' ? 'PP' : ($p['participant_type'] === 'undangan' ? 'Tamu Undangan' : 'PW'));
                        ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 text-slate-400 font-mono"><?= $no++ ?></td>
                                
                                <td class="py-3 px-4">
                                    <div class="font-mono font-bold text-slate-800 text-[11px]"><?= clean($p['registration_code']) ?></div>
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

                                        <?php if (!empty($p['is_group_leader'])): ?>
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-900" title="Ketua Rombongan">Ketua</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($p['group_code'])): ?>
                                        <div class="text-[9px] font-mono text-slate-400 mt-0.5">Grp: <?= clean($p['group_code']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex items-start gap-3">
                                        <?php if (!empty($photoPath) && file_exists(__DIR__ . '/../' . $photoPath)): ?>
                                            <img src="<?= BASE_URL . clean($photoPath) ?>" alt="Foto" class="w-10 h-12 object-cover rounded-lg border border-slate-200 shrink-0 shadow-xs">
                                        <?php else: ?>
                                            <div class="w-10 h-12 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center text-xs font-bold shrink-0">
                                                <?= strtoupper(substr($p['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="<?= BASE_URL ?>admin/participant_detail.php?id=<?= $p['id'] ?>" class="font-bold text-slate-900 text-sm hover:text-emerald-700 transition">
                                                <?= clean($p['full_name']) ?>
                                            </a>
                                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold <?= $p['gender'] === 'L' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-pink-50 text-pink-700 border border-pink-200' ?>">
                                                    <?= $p['gender'] === 'L' ? 'L' : 'P' ?>
                                                </span>
                                                <?php if ($kat !== 'Tamu Undangan'): ?>
                                                    <?= renderUnsurBadge($p['unsur_organisasi'] ?? 'Al Khidmah') ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[11px] text-slate-500 font-mono mt-0.5">WA: <?= clean($p['phone']) ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-800">
                                        <?php 
                                        if ($kat === 'PP') echo "Pengurus Pusat (PP)";
                                        elseif ($kat === 'Tamu Undangan') echo "Tamu Undangan Khusus";
                                        elseif ($kat === 'PW') echo clean($p['pw_name']);
                                        else echo clean($p['pd_name'] ?: ($p['db_daerah_name'] ?? '-'));
                                        ?>
                                    </div>
                                    <?php if ($kat === 'PD' && !empty($p['pw_name'])): ?>
                                        <div class="text-[10px] text-slate-400"><?= clean($p['pw_name']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-[10px] text-slate-500 font-medium mt-0.5"><?= clean($p['jabatan_organisasi'] ?: '-') ?></div>
                                </td>

                                <td class="py-3 px-4">
                                    <?php if ($kat !== 'Tamu Undangan'): ?>
                                        <div class="text-[11px] text-slate-700 font-semibold bg-slate-50 px-2 py-0.5 rounded border border-slate-200 inline-block mb-1">
                                            <?= clean($p['komisi'] ?: '-') ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-[11px] text-purple-700 font-semibold mb-1">Tamu Khusus</div>
                                    <?php endif; ?>
                                    <div class="text-[10px] text-slate-500">
                                        <?= clean($p['arrival_day'] ?: '-') ?> <?= clean($p['arrival_time'] ?? '') ?>
                                    </div>
                                    <?php if (!empty($p['need_pickup'])): ?>
                                        <span class="inline-block text-[9px] bg-amber-100 text-amber-900 border border-amber-200 px-1.5 py-0.5 rounded font-bold mt-0.5 max-w-[170px] truncate block" title="Titik: <?= clean($p['pickup_location'] ?: 'Stasiun/Bandara') ?><?= !empty($p['pickup_time']) ? ' - Waktu: ' . clean($p['pickup_time']) : '' ?><?= !empty($p['pickup_notes']) ? ' - Catatan: ' . clean($p['pickup_notes']) : '' ?>">
                                            🚐 <?= clean($p['pickup_location'] ?: 'Perlu Jemputan') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4">
                                    <?php if (!empty($p['building_name'])): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            <?= clean($p['building_name']) ?> - <?= clean($p['room_number']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Belum diset</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4 text-center space-y-1">
                                    <div>
                                        <a href="?action=toggle_attend&id=<?= $p['id'] ?>&<?= http_build_query($_GET) ?>" title="Klik untuk ubah kehadiran" onclick="return confirm('Ubah status kehadiran peserta ini?')">
                                            <?= renderAttendanceBadge($p['is_attended'], $p['attended_at']) ?>
                                        </a>
                                    </div>
                                    <div>
                                        <a href="?action=toggle_kit&id=<?= $p['id'] ?>&<?= http_build_query($_GET) ?>" title="Klik untuk ubah status Kit">
                                            <?php if ($p['kit_received']): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-800">
                                                    Kit Diambil
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-semibold bg-gray-100 text-gray-400">
                                                    Kit Belum
                                                </span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                </td>

                                <td class="py-3 px-6 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Detail Pendaftar -->
                                        <a href="<?= BASE_URL ?>admin/participant_detail.php?id=<?= $p['id'] ?>" title="Lihat Detail Profil & Rombongan" class="p-1.5 text-slate-400 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <!-- Cetak Bukti Pendaftaran Resmi Baru -->
                                        <a href="<?= BASE_URL ?>print_proof.php?code=<?= urlencode($p['registration_code']) ?>" target="_blank" title="Cetak Bukti Pendaftaran Resmi (A4)" class="p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </a>
                                        <!-- Cetak ID Card -->
                                        <a href="<?= BASE_URL ?>print_card.php?code=<?= urlencode($p['registration_code']) ?>" target="_blank" title="Cetak ID Card" class="p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>
                                        <!-- Edit Data -->
                                        <a href="<?= BASE_URL ?>admin/participant_edit.php?id=<?= $p['id'] ?>" title="Edit Data" class="p-1.5 text-slate-400 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <!-- Hapus Data -->
                                        <?php if (isSuperAdmin()): ?>
                                            <a href="<?= BASE_URL ?>admin/participant_delete.php?id=<?= $p['id'] ?>" onclick="return confirm('Hapus peserta <?= clean($p['full_name']) ?>? Data tidak dapat dikembalikan.')" title="Hapus" class="p-1.5 text-slate-400 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
