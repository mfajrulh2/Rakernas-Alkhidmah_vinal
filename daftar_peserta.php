<?php
/**
 * Daftar Peserta Terdaftar - Halaman Publik
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH 2026
 * 
 * Bersifat Publik & Read-Only (Tanpa Fasilitas Detail/Edit/Hapus/Cetak)
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

ensureRegistrationTablesExist();
$pdo = getDB();

// Filter parameters
$filterKategori = trim($_GET['kategori'] ?? '');
$filterPW       = !empty($_GET['pw_id']) ? (int)$_GET['pw_id'] : 0;
$filterPD       = trim($_GET['pd_name'] ?? '');
$filterPayment  = trim($_GET['payment_status'] ?? 'all'); // 'all', 'verified', 'unpaid'
$keyword        = trim($_GET['q'] ?? '');

// Ambil list PW untuk dropdown filter
$wilayahList = $pdo->query("SELECT id, name, code FROM wilayah ORDER BY name ASC")->fetchAll();

// Ambil list PD sesuai PW yang dipilih (jika ada)
$daerahList = [];
if ($filterPW > 0) {
    $stmtD = $pdo->prepare("SELECT id, name FROM daerah WHERE pw_id = ? ORDER BY name ASC");
    $stmtD->execute([$filterPW]);
    $daerahList = $stmtD->fetchAll();
} else {
    // Ambil seluruh PD unik
    $daerahList = $pdo->query("SELECT DISTINCT name FROM daerah ORDER BY name ASC")->fetchAll();
}

// Build query
$where = ["1=1"];
$params = [];

if (!empty($keyword)) {
    $where[] = "(p.full_name LIKE :kw OR p.jabatan_organisasi LIKE :kw2 OR p.pd_name LIKE :kw3)";
    $params[':kw']  = "%{$keyword}%";
    $params[':kw2'] = "%{$keyword}%";
    $params[':kw3'] = "%{$keyword}%";
}

if (!empty($filterKategori)) {
    $where[] = "p.kategori = :kat";
    $params[':kat'] = $filterKategori;
}

if ($filterPW > 0) {
    $where[] = "p.pw_id = :pw_id";
    $params[':pw_id'] = $filterPW;
}

if (!empty($filterPD)) {
    $where[] = "(p.pd_name = :pd OR d.name = :pd2)";
    $params[':pd']  = $filterPD;
    $params[':pd2'] = $filterPD;
}

if ($filterPayment === 'verified') {
    $where[] = "(g.payment_status = 'verified' OR p.kategori = 'Tamu Undangan')";
} elseif ($filterPayment === 'unpaid') {
    $where[] = "(g.payment_status != 'verified' OR g.payment_status IS NULL) AND p.kategori != 'Tamu Undangan'";
}

$sql = "
    SELECT 
        p.id,
        p.registration_code,
        p.full_name,
        p.gender,
        p.kategori,
        p.unsur_organisasi,
        p.jabatan_organisasi,
        p.komisi,
        p.pd_name,
        p.created_at,
        w.name AS pw_name,
        w.code AS pw_code,
        d.name AS db_daerah_name,
        g.group_code,
        g.payment_status AS group_pay_status
    FROM participants p
    LEFT JOIN wilayah w ON p.pw_id = w.id
    LEFT JOIN daerah d ON p.daerah_id = d.id
    LEFT JOIN registration_groups g ON p.group_id = g.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY p.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$participants = $stmt->fetchAll();
$totalFiltered = count($participants);

// Total statistik ringkas
$totalAllPeserta = (int)$pdo->query("SELECT COUNT(*) FROM participants")->fetchColumn();
$totalLunas      = (int)$pdo->query("
    SELECT COUNT(p.id) 
    FROM participants p 
    LEFT JOIN registration_groups g ON p.group_id = g.id 
    WHERE g.payment_status = 'verified' OR p.kategori = 'Tamu Undangan'
")->fetchColumn();

$page_title = "Daftar Peserta Terdaftar - RAKERNAS Al Khidmah 2026";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto mb-20 space-y-6">

    <!-- Header Section -->
    <div class="text-center max-w-2xl mx-auto">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-900 border border-emerald-300 mb-3">
            <svg class="w-3.5 h-3.5 text-emerald-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
            DATA PUBLIK DELEGASI RAKERNAS 2026
        </span>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Daftar Peserta Terdaftar</h1>
        <p class="text-xs sm:text-sm text-slate-600 mt-2">
            Memuat daftar seluruh delegasi resmi Pengurus Pusat (PP), Pengurus Wilayah (PW), Pengurus Daerah (PD), dan Tamu Undangan yang telah mendaftar.
        </p>
    </div>

    <!-- Ringkasan Singkat -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 max-w-3xl mx-auto">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-2xs text-center">
            <div class="text-2xl font-black text-slate-800"><?= number_format($totalAllPeserta) ?></div>
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mt-0.5">Total Delegasi Terdaftar</div>
        </div>
        <div class="bg-emerald-50/80 p-4 rounded-2xl border border-emerald-200 shadow-2xs text-center">
            <div class="text-2xl font-black text-emerald-800"><?= number_format($totalLunas) ?></div>
            <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider mt-0.5">Iuran Terverifikasi (Lunas)</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-2xs text-center col-span-2 sm:col-span-1">
            <div class="text-2xl font-black text-emerald-900"><?= number_format($totalFiltered) ?></div>
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mt-0.5">Hasil Sesuai Filter</div>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-5 sm:p-6 shadow-sm space-y-4">
        <form action="" method="GET" class="space-y-4" id="filterForm">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Search Input -->
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Cari Nama Peserta / Jabatan</label>
                    <div class="relative">
                        <input type="text" name="q" value="<?= clean($keyword) ?>" placeholder="Ketik nama atau jabatan..." class="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                <!-- Filter PW -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Pengurus Wilayah (PW)</label>
                    <select name="pw_id" onchange="document.getElementById('filterForm').submit()" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                        <option value="">-- Semua Wilayah --</option>
                        <?php foreach ($wilayahList as $w): ?>
                            <option value="<?= $w['id'] ?>" <?= $filterPW === (int)$w['id'] ? 'selected' : '' ?>>
                                <?= clean($w['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter PD -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Pengurus Daerah (PD)</label>
                    <select name="pd_name" onchange="document.getElementById('filterForm').submit()" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                        <option value="">-- Semua Daerah --</option>
                        <?php foreach ($daerahList as $d): ?>
                            <?php $dName = is_array($d) ? ($d['name'] ?? '') : $d; ?>
                            <option value="<?= clean($dName) ?>" <?= $filterPD === $dName ? 'selected' : '' ?>>
                                <?= clean($dName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Status Pembayaran -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Status Iuran</label>
                    <select name="payment_status" onchange="document.getElementById('filterForm').submit()" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white font-semibold">
                        <option value="all" <?= $filterPayment === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="verified" <?= $filterPayment === 'verified' ? 'selected' : '' ?>>✓ Sudah Lunas</option>
                        <option value="unpaid" <?= $filterPayment === 'unpaid' ? 'selected' : '' ?>>⏳ Belum Lunas / Proses</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <span class="text-xs text-slate-500">
                    Menampilkan <strong><?= $totalFiltered ?></strong> delegasi terdaftar
                </span>
                <div class="flex items-center gap-2">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs transition shadow-xs">
                        Terapkan Filter
                    </button>
                    <?php if (!empty($keyword) || $filterPW > 0 || !empty($filterPD) || $filterPayment !== 'all'): ?>
                        <a href="<?= BASE_URL ?>daftar_peserta.php" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-xs transition">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabel Peserta (Publik & Read-Only) -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-bold border-b border-slate-200/70">
                    <tr>
                        <th class="py-3 px-3 text-center w-10">No</th>
                        <th class="py-3 px-3">Kode</th>
                        <th class="py-3 px-4">Nama Lengkap Delegasi</th>
                        <th class="py-3 px-3 text-center">L/P</th>
                        <th class="py-3 px-4">Asal Kepengurusan</th>
                        <th class="py-3 px-4">Jabatan & Unsur</th>
                        <th class="py-3 px-4">Komisi Sidang</th>
                        <th class="py-3 px-4 text-center">Status Pembayaran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($participants)): ?>
                        <tr>
                            <td colspan="8" class="p-12 text-center text-slate-400">
                                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-2 text-slate-400 text-lg">
                                    🔍
                                </div>
                                <div class="font-bold text-slate-600">Tidak ada data delegasi yang sesuai filter</div>
                                <div class="text-[11px] mt-0.5">Silakan ganti kata kunci pencarian atau reset filter di atas.</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($participants as $idx => $p): ?>
                        <?php 
                        $isVerified = ($p['group_pay_status'] === 'verified') || ($p['kategori'] === 'Tamu Undangan');
                        ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3 px-3 text-center text-slate-400 font-bold"><?= $idx + 1 ?></td>
                            
                            <td class="py-3 px-3 font-mono font-bold text-emerald-800 text-[11px]">
                                <?= clean($p['registration_code']) ?>
                            </td>

                            <td class="py-3 px-4">
                                <div class="font-extrabold text-slate-900 text-xs sm:text-sm">
                                    <?= clean($p['full_name']) ?>
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    <?= $p['gender'] === 'L' ? 'Ikhwan' : 'Akhwat' ?>
                                </div>
                            </td>

                            <td class="py-3 px-3 text-center font-bold">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-black <?= $p['gender'] === 'L' ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
                                    <?= $p['gender'] === 'L' ? 'L' : 'P' ?>
                                </span>
                            </td>

                            <td class="py-3 px-4">
                                <span class="inline-block px-2 py-0.5 rounded text-[9px] font-black uppercase mb-0.5 <?= $p['kategori'] === 'PP' ? 'bg-blue-100 text-blue-900' : ($p['kategori'] === 'PW' ? 'bg-purple-100 text-purple-900' : ($p['kategori'] === 'Tamu Undangan' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900')) ?>">
                                    <?= clean($p['kategori']) ?>
                                </span>
                                <div class="font-bold text-slate-800">
                                    <?php 
                                    if ($p['kategori'] === 'PP') echo "Pengurus Pusat (PP)";
                                    elseif ($p['kategori'] === 'Tamu Undangan') echo "Tamu Undangan Khusus";
                                    elseif ($p['kategori'] === 'PW') echo clean($p['pw_name']);
                                    else echo clean($p['pd_name'] ?: ($p['db_daerah_name'] ?? '-'));
                                    ?>
                                </div>
                                <?php if ($p['kategori'] === 'PD' && !empty($p['pw_name'])): ?>
                                    <div class="text-[10px] text-slate-400"><?= clean($p['pw_name']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-800"><?= clean($p['jabatan_organisasi'] ?: '-') ?></div>
                                <?php if ($p['kategori'] !== 'Tamu Undangan'): ?>
                                    <div class="text-[10px] text-slate-400 mt-0.5"><?= clean($p['unsur_organisasi'] ?: 'Al Khidmah') ?></div>
                                <?php endif; ?>
                            </td>

                            <td class="py-3 px-4">
                                <span class="inline-block px-2 py-1 rounded-lg text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                    <?= clean($p['komisi'] ?: '-') ?>
                                </span>
                            </td>

                            <td class="py-3 px-4 text-center">
                                <?php if ($p['kategori'] === 'Tamu Undangan'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-100 text-purple-900 border border-purple-200">
                                        Undangan Khusus
                                    </span>
                                <?php elseif ($isVerified): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                                        <svg class="w-3 h-3 text-emerald-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                        Lunas
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                        Menunggu Pembayaran
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
