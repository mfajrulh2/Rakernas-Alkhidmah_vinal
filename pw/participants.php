<?php
/**
 * Daftar Delegasi Terdaftar (Khusus PW Terkait)
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin_pw');

$pdo = getDB();
$user = currentUser();
$pw_id = $user['pw_id'];

// Handle Hapus Delegasi oleh PW (hanya jika belum check-in)
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmtC = $pdo->prepare("SELECT full_name, is_attended FROM participants WHERE id = ? AND pw_id = ?");
    $stmtC->execute([$delId, $pw_id]);
    $row = $stmtC->fetch();

    if ($row) {
        if ($row['is_attended']) {
            setFlash('danger', 'Peserta yang sudah check-in kehadiran di lokasi tidak dapat dihapus.');
        } else {
            $pdo->prepare("DELETE FROM participants WHERE id = ? AND pw_id = ?")->execute([$delId, $pw_id]);
            setFlash('info', "Delegasi {$row['full_name']} berhasil dihapus.");
        }
    }
    header("Location: " . BASE_URL . "pw/participants.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$filterUnsur = trim($_GET['unsur'] ?? '');

$where = ["p.pw_id = :pw_id"];
$params = [':pw_id' => $pw_id];

if (!empty($search)) {
    $where[] = "(p.full_name LIKE :s OR p.phone LIKE :s OR p.pd_name LIKE :s OR p.registration_code LIKE :s OR p.jabatan_organisasi LIKE :s)";
    $params[':s'] = "%{$search}%";
}
if (!empty($filterUnsur)) {
    $where[] = "p.unsur_organisasi = :u";
    $params[':u'] = $filterUnsur;
}

$sql = "
    SELECT p.*, r.building_name, r.room_number 
    FROM participants p
    LEFT JOIN rooms r ON p.room_id = r.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY p.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$delegates = $stmt->fetchAll();

// Ambil status pembayaran PW ini
$payInfo = getPWPaymentInfo($pw_id);

$page_title = "Daftar Delegasi Wilayah";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 mb-16">
    <!-- Payment Status Alert -->
    <?php if (!$payInfo['is_verified']): ?>
    <div class="p-4 rounded-2xl border-2 <?= $payInfo['status'] === 'pending' ? 'bg-amber-50 border-amber-400' : 'bg-orange-50 border-orange-300' ?> flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="text-2xl"><?= $payInfo['status'] === 'pending' ? '⏳' : '⚠️' ?></div>
            <div>
                <p class="font-bold text-sm <?= $payInfo['status'] === 'pending' ? 'text-amber-800' : 'text-orange-800' ?>">
                    <?= $payInfo['status'] === 'pending' ? 'Konfirmasi Pembayaran Sedang Diverifikasi' : 'Pembayaran Iuran Belum Dikonfirmasi' ?>
                </p>
                <p class="text-xs mt-0.5 <?= $payInfo['status'] === 'pending' ? 'text-amber-700' : 'text-orange-700' ?>">
                    <?php if ($payInfo['status'] === 'pending'): ?>
                        Konfirmasi pembayaran Anda sedang diperiksa oleh Panitia Pusat. Tombol cetak ID Card akan aktif setelah diverifikasi.
                    <?php elseif ($payInfo['status'] === 'rejected'): ?>
                        Konfirmasi pembayaran Anda <strong>ditolak</strong>. Silakan kirim ulang konfirmasi dengan data yang benar.
                    <?php else: ?>
                        Tagihan iuran delegasi: <strong><?= formatRupiah($payInfo['expected_total']) ?></strong> (<?= $payInfo['total_participants'] ?> orang × <?= formatRupiah($payInfo['fee_per_person']) ?>).
                        Cetak ID Card hanya tersedia setelah pembayaran terverifikasi.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>pw/payment.php"
           class="flex-shrink-0 px-4 py-2 rounded-xl <?= $payInfo['status'] === 'pending' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-orange-600 hover:bg-orange-700' ?> text-white font-bold text-xs transition whitespace-nowrap">
            <?= $payInfo['status'] === 'pending' ? 'Pantau Status' : 'Konfirmasi Bayar' ?>
        </a>
    </div>
    <?php endif; ?>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Daftar Delegasi Wilayah</h1>
            <p class="text-xs text-slate-500 mt-0.5">Seluruh utusan dan peninjau yang didaftarkan oleh <?= clean($user['pw_name']) ?></p>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>pw/register_bulk.php" class="px-4 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Daftarkan Kolektif</span>
            </a>
            <a href="<?= BASE_URL ?>print_proof.php?pw_id=<?= $pw_id ?>&all=1" target="_blank" class="px-3.5 py-2.5 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 font-bold text-xs hover:bg-emerald-100 transition flex items-center gap-1.5 shadow-sm" title="Cetak Dokumen Bukti Pendaftaran untuk seluruh delegasi">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Cetak Semua Bukti</span>
            </a>
            <?php if ($payInfo['is_verified']): ?>
            <a href="<?= BASE_URL ?>pw/print_cards.php" class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak Semua ID Card</span>
            </a>
            <?php else: ?>
            <a href="<?= BASE_URL ?>pw/payment.php" title="Selesaikan pembayaran iuran untuk mencetak ID Card"
               class="px-3.5 py-2.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-400 font-bold text-xs cursor-not-allowed flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>Cetak ID Card (Terkunci)</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded-3xl border border-slate-200/90 shadow-sm">
        <form action="" method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Cari nama, WhatsApp, jabatan, atau daerah..." class="flex-grow px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            
            <select name="unsur" class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                <option value="">Semua Unsur Organisasi</option>
                <?php foreach (getUnsurOrganisasiOptions() as $uOpt): ?>
                    <option value="<?= clean($uOpt) ?>" <?= $filterUnsur === $uOpt ? 'selected' : '' ?>><?= clean($uOpt) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="px-5 py-2 rounded-xl bg-slate-800 text-white font-bold text-xs hover:bg-slate-900 transition">
                Filter
            </button>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-6">Kode & Kehadiran</th>
                        <th class="py-3 px-6">Foto & Nama Delegasi</th>
                        <th class="py-3 px-4">Unsur Organisasi</th>
                        <th class="py-3 px-4">Pengurus Daerah & Jabatan/Bidang</th>
                        <th class="py-3 px-4">Komisi Sidang & Kamar</th>
                        <th class="py-3 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($delegates)): ?>
                        <tr><td colspan="6" class="py-10 text-center text-slate-400">Tidak ada delegasi yang ditemukan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($delegates as $d): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-6">
                                    <div class="font-mono font-bold text-slate-800"><?= clean($d['registration_code']) ?></div>
                                    <div class="mt-1"><?= renderAttendanceBadge($d['is_attended'], $d['attended_at']) ?></div>
                                </td>

                                <td class="py-3.5 px-6">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($d['photo_file'])): ?>
                                            <a href="<?= BASE_URL . clean($d['photo_file']) ?>" target="_blank" class="flex-shrink-0">
                                                <img src="<?= BASE_URL . clean($d['photo_file']) ?>" alt="Foto" class="w-10 h-12 object-cover rounded-lg border border-slate-200 shadow-sm hover:opacity-80 transition">
                                            </a>
                                        <?php else: ?>
                                            <div class="w-10 h-12 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 text-[10px] font-bold flex-shrink-0">
                                                <?= clean($d['gender']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="font-bold text-slate-900 text-sm"><?= clean($d['full_name']) ?> <span class="text-[10px] font-semibold text-slate-400">(<?= clean($d['gender']) ?>)</span></div>
                                            <div class="text-[11px] text-slate-500 font-mono">WA: <?= clean($d['phone']) ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <?= renderUnsurBadge($d['unsur_organisasi'] ?? 'Al Khidmah') ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-800"><?= clean($d['pd_name'] ?: '-') ?></div>
                                    <div class="text-[11px] text-emerald-800"><?= clean($d['jabatan_organisasi']) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="text-xs font-semibold text-slate-800"><?= clean($d['komisi']) ?></div>
                                    <div class="mt-1">
                                        <?php if (!empty($d['building_name'])): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700">
                                                <?= clean($d['building_name']) ?> - <?= clean($d['room_number']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] text-slate-400 italic">Kamar: Ditetapkan Panitia</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="py-3.5 px-6 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="<?= BASE_URL ?>pw/participant_edit.php?id=<?= $d['id'] ?>" class="px-2.5 py-1.5 text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg font-bold text-xs inline-flex items-center gap-1 transition" title="Edit Data Delegasi">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            <span>Edit</span>
                                        </a>
                                        <a href="<?= BASE_URL ?>print_proof.php?code=<?= urlencode($d['registration_code']) ?>" target="_blank" class="p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition" title="Cetak Bukti Pendaftaran (A4)">
                                            <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </a>
                                        <?php if ($payInfo['is_verified']): ?>
                                        <a href="<?= BASE_URL ?>print_card.php?code=<?= urlencode($d['registration_code']) ?>" target="_blank" class="p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition" title="Cetak ID Card">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>
                                        <?php else: ?>
                                        <span class="p-1.5 text-slate-200 cursor-not-allowed" title="Bayar iuran dulu untuk cetak">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!$d['is_attended']): ?>
                                            <a href="?delete_id=<?= $d['id'] ?>" onclick="return confirm('Hapus pendaftaran <?= clean($d['full_name']) ?>?');" class="p-1.5 text-slate-400 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition" title="Hapus">
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
