<?php
/**
 * Validasi Pembayaran Iuran Delegasi - Halaman Admin / Panitia Pusat
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('superadmin');
ensurePaymentTableExists();
ensureRegistrationTablesExist();

$pdo  = getDB();
$user = currentUser();

// =============================================
// HANDLE AJAX / POST ACTIONS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action   = $_POST['action'];
    $itemType = $_POST['item_type'] ?? 'group'; // 'group' or 'pw'

    if ($itemType === 'group') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        if (!$groupId) {
            echo json_encode(['success' => false, 'message' => 'ID rombongan tidak valid.']);
            exit;
        }

        $stmtG = $pdo->prepare("SELECT * FROM registration_groups WHERE id = ?");
        $stmtG->execute([$groupId]);
        $group = $stmtG->fetch();

        if (!$group) {
            echo json_encode(['success' => false, 'message' => 'Data rombongan tidak ditemukan.']);
            exit;
        }

        if ($action === 'verify') {
            $pdo->prepare("
                UPDATE registration_groups SET
                    payment_status = 'verified',
                    payment_verified_at = NOW(),
                    payment_verified_by = ?,
                    payment_reject_reason = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$user['full_name'] ?? $user['username'], $groupId]);

            echo json_encode(['success' => true, 'message' => "Pembayaran rombongan {$group['group_code']} berhasil diverifikasi (LUNAS)!"]);
            exit;

        } elseif ($action === 'reject') {
            $reason = trim($_POST['reject_reason'] ?? '');
            if (empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'Alasan penolakan wajib diisi.']);
                exit;
            }

            $pdo->prepare("
                UPDATE registration_groups SET
                    payment_status = 'rejected',
                    payment_verified_at = NOW(),
                    payment_verified_by = ?,
                    payment_reject_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$user['full_name'] ?? $user['username'], $reason, $groupId]);

            echo json_encode(['success' => true, 'message' => "Konfirmasi pembayaran rombongan {$group['group_code']} ditolak."]);
            exit;

        } elseif ($action === 'reset') {
            $pdo->prepare("
                UPDATE registration_groups SET
                    payment_status = 'unpaid',
                    payment_verified_at = NULL,
                    payment_verified_by = NULL,
                    payment_reject_reason = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$groupId]);

            echo json_encode(['success' => true, 'message' => "Status pembayaran rombongan {$group['group_code']} direset ke Belum Bayar."]);
            exit;
        }

    } else {
        // Legacy: table payments by PW
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        if (!$paymentId) {
            echo json_encode(['success' => false, 'message' => 'ID pembayaran tidak valid.']);
            exit;
        }

        $stmtP = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmtP->execute([$paymentId]);
        $payment = $stmtP->fetch();

        if (!$payment) {
            echo json_encode(['success' => false, 'message' => 'Data pembayaran tidak ditemukan.']);
            exit;
        }

        if ($action === 'verify') {
            $pdo->prepare("
                UPDATE payments SET
                    status = 'verified',
                    verified_by = ?,
                    verified_at = NOW(),
                    reject_reason = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$user['id'], $paymentId]);

            echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil diverifikasi! Status PW: LUNAS.']);
            exit;

        } elseif ($action === 'reject') {
            $reason = trim($_POST['reject_reason'] ?? '');
            if (empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'Alasan penolakan wajib diisi.']);
                exit;
            }

            $pdo->prepare("
                UPDATE payments SET
                    status = 'rejected',
                    verified_by = ?,
                    verified_at = NOW(),
                    reject_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$user['id'], $reason, $paymentId]);

            echo json_encode(['success' => true, 'message' => 'Pembayaran ditolak. PW akan diminta kirim ulang konfirmasi.']);
            exit;

        } elseif ($action === 'reset') {
            $pdo->prepare("
                UPDATE payments SET
                    status = 'unpaid',
                    verified_by = NULL,
                    verified_at = NULL,
                    reject_reason = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$paymentId]);

            echo json_encode(['success' => true, 'message' => 'Status pembayaran direset ke Belum Konfirmasi.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
    exit;
}

// =============================================
// TAB & FILTER
// =============================================
$activeTab    = $_GET['tab'] ?? 'groups'; // 'groups' or 'pw_legacy'
$filterStatus = $_GET['status'] ?? 'all';
$validStatuses = ['all', 'pending', 'verified', 'rejected', 'unpaid'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'all';

// =============================================
// DATA STATISTIK GROUPS (UTAMA)
// =============================================
$statGTotal    = (int)$pdo->query("SELECT COUNT(*) FROM registration_groups WHERE kategori != 'Tamu Undangan'")->fetchColumn();
$statGPending  = (int)$pdo->query("SELECT COUNT(*) FROM registration_groups WHERE kategori != 'Tamu Undangan' AND payment_status = 'pending'")->fetchColumn();
$statGVerified = (int)$pdo->query("SELECT COUNT(*) FROM registration_groups WHERE kategori != 'Tamu Undangan' AND payment_status = 'verified'")->fetchColumn();
$statGUnpaid   = (int)$pdo->query("SELECT COUNT(*) FROM registration_groups WHERE kategori != 'Tamu Undangan' AND (payment_status = 'unpaid' OR payment_status IS NULL)")->fetchColumn();
$statGRejected = (int)$pdo->query("SELECT COUNT(*) FROM registration_groups WHERE kategori != 'Tamu Undangan' AND payment_status = 'rejected'")->fetchColumn();

$statGVerifiedSum = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM registration_groups WHERE kategori != 'Tamu Undangan' AND payment_status = 'verified'")->fetchColumn();

// Query List Groups
$whereGroups = ["g.kategori != 'Tamu Undangan'"];
$paramsGroups = [];

if ($filterStatus === 'pending') {
    $whereGroups[] = "g.payment_status = 'pending'";
} elseif ($filterStatus === 'verified') {
    $whereGroups[] = "g.payment_status = 'verified'";
} elseif ($filterStatus === 'rejected') {
    $whereGroups[] = "g.payment_status = 'rejected'";
} elseif ($filterStatus === 'unpaid') {
    $whereGroups[] = "(g.payment_status = 'unpaid' OR g.payment_status IS NULL)";
}

$sqlGroups = "
    SELECT g.*, w.name AS pw_name, w.code AS pw_code, d.name AS db_daerah_name
    FROM registration_groups g
    LEFT JOIN wilayah w ON g.pw_id = w.id
    LEFT JOIN daerah d ON g.daerah_id = d.id
    WHERE " . implode(" AND ", $whereGroups) . "
    ORDER BY 
        CASE g.payment_status
            WHEN 'pending'  THEN 1
            WHEN 'rejected' THEN 2
            WHEN 'unpaid'   THEN 3
            WHEN 'verified' THEN 4
            ELSE 5
        END, g.id DESC
";
$stmtGList = $pdo->prepare($sqlGroups);
$stmtGList->execute($paramsGroups);
$groupsList = $stmtGList->fetchAll();

// =============================================
// DATA STATISTIK PW (LEGACY)
// =============================================
$feePerPerson = (float)getSetting('fee_per_participant', '300000');
$statPwTotal    = (int)$pdo->query("SELECT COUNT(*) FROM wilayah")->fetchColumn();
$statPwVerified = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'")->fetchColumn();
$statPwPending  = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();

$sqlPW = "
    SELECT
        w.id AS pw_id,
        w.name AS pw_name,
        w.code AS pw_code,
        COUNT(p.id) AS total_participants,
        (COUNT(p.id) * :fee) AS expected_total,
        pay.id AS payment_id,
        pay.invoice_code,
        pay.total_amount,
        pay.bank_name,
        pay.sender_name,
        pay.payment_date,
        pay.proof_file,
        pay.status AS pay_status,
        pay.reject_reason,
        pay.verified_at
    FROM wilayah w
    LEFT JOIN participants p ON p.pw_id = w.id
    LEFT JOIN payments pay ON pay.pw_id = w.id AND pay.id = (
        SELECT id FROM payments WHERE pw_id = w.id ORDER BY id DESC LIMIT 1
    )
    WHERE w.id > 0
    GROUP BY w.id
    ORDER BY w.name ASC
";
$stmtPW = $pdo->prepare($sqlPW);
$stmtPW->execute([':fee' => $feePerPerson]);
$pwPayments = $stmtPW->fetchAll();

$page_title = 'Validasi Pembayaran Iuran';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 mb-20">

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-sm">💳</span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Validasi Pembayaran Iuran</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Verifikasi bukti transfer iuran akomodasi delegasi RAKERNAS • Tarif: <strong><?= formatRupiah($feePerPerson) ?> / delegasi</strong>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= BASE_URL ?>admin/settings.php?tab=payment" class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition flex items-center gap-1.5 shadow-xs">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Atur Rekening Bank</span>
            </a>
            <a href="<?= BASE_URL ?>konfirmasi_pembayaran.php" target="_blank" class="px-3.5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shadow-sm transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                <span>Buka Form Konfirmasi Publik</span>
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-4">
        <a href="?tab=groups&status=all" class="bg-white rounded-2xl border <?= ($activeTab === 'groups' && $filterStatus === 'all') ? 'border-slate-800 ring-2 ring-slate-800/10' : 'border-slate-200' ?> shadow-sm p-4 text-center hover:bg-slate-50 transition block">
            <div class="text-2xl font-black text-slate-800"><?= $statGTotal ?></div>
            <div class="text-[11px] text-slate-500 font-bold uppercase mt-1">Total Rombongan</div>
        </a>

        <a href="?tab=groups&status=pending" class="bg-amber-50 rounded-2xl border-2 <?= ($activeTab === 'groups' && $filterStatus === 'pending') ? 'border-amber-600 ring-2 ring-amber-500/20' : 'border-amber-200' ?> shadow-sm p-4 text-center hover:border-amber-400 transition block">
            <div class="text-2xl font-black text-amber-700"><?= $statGPending ?></div>
            <div class="text-[11px] text-amber-800 font-extrabold uppercase mt-1 flex items-center justify-center gap-1">
                <?php if ($statGPending > 0): ?>
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                <?php endif; ?>
                <span>Menunggu Validasi</span>
            </div>
        </a>

        <a href="?tab=groups&status=verified" class="bg-emerald-50 rounded-2xl border-2 <?= ($activeTab === 'groups' && $filterStatus === 'verified') ? 'border-emerald-600 ring-2 ring-emerald-500/20' : 'border-emerald-200' ?> shadow-sm p-4 text-center hover:border-emerald-400 transition block">
            <div class="text-2xl font-black text-emerald-700"><?= $statGVerified ?></div>
            <div class="text-[11px] text-emerald-800 font-extrabold uppercase mt-1">Lunas / Terverifikasi</div>
        </a>

        <a href="?tab=groups&status=unpaid" class="bg-slate-50 rounded-2xl border-2 <?= ($activeTab === 'groups' && $filterStatus === 'unpaid') ? 'border-slate-400' : 'border-slate-200' ?> shadow-sm p-4 text-center hover:bg-slate-100 transition block">
            <div class="text-2xl font-black text-slate-600"><?= $statGUnpaid ?></div>
            <div class="text-[11px] text-slate-500 font-bold uppercase mt-1">Belum Konfirmasi</div>
        </a>

        <div class="bg-gradient-to-br from-emerald-900 to-teal-950 rounded-2xl p-4 text-center text-white col-span-2 md:col-span-1 shadow-sm">
            <div class="text-lg sm:text-xl font-black font-mono text-emerald-300"><?= formatRupiah($statGVerifiedSum) ?></div>
            <div class="text-[10px] text-emerald-200 font-bold uppercase mt-1">Total Dana Masuk</div>
        </div>
    </div>

    <!-- Tab Header Navigasi -->
    <div class="border-b border-slate-200 flex items-center gap-6">
        <a href="?tab=groups&status=all" class="pb-3 text-xs sm:text-sm font-extrabold border-b-2 transition flex items-center gap-2 <?= $activeTab === 'groups' ? 'border-emerald-700 text-emerald-900' : 'border-transparent text-slate-400 hover:text-slate-700' ?>">
            <span>Daftar Transaksi Rombongan (PP / PW / PD)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] <?= $statGPending > 0 ? 'bg-amber-100 text-amber-900 font-bold' : 'bg-slate-100 text-slate-600' ?>">
                <?= $statGTotal ?>
            </span>
        </a>

        <a href="?tab=pw_legacy" class="pb-3 text-xs sm:text-sm font-bold border-b-2 transition flex items-center gap-2 <?= $activeTab === 'pw_legacy' ? 'border-emerald-700 text-emerald-900' : 'border-transparent text-slate-400 hover:text-slate-700' ?>">
            <span>Rekap Kolektif per PW (Legacy)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600">
                <?= $statPwTotal ?>
            </span>
        </a>
    </div>

    <?php if ($activeTab === 'groups'): ?>

        <!-- FILTER STATUS BADGES -->
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-slate-500 mr-1">Filter Status:</span>
            <a href="?tab=groups&status=all" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filterStatus === 'all' ? 'bg-slate-800 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                Semua (<?= $statGTotal ?>)
            </a>
            <a href="?tab=groups&status=pending" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filterStatus === 'pending' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 border border-amber-200 text-amber-900 hover:bg-amber-100' ?>">
                Menunggu Validasi (<?= $statGPending ?>)
            </a>
            <a href="?tab=groups&status=verified" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filterStatus === 'verified' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-emerald-50 border border-emerald-200 text-emerald-900 hover:bg-emerald-100' ?>">
                Lunas (<?= $statGVerified ?>)
            </a>
            <a href="?tab=groups&status=unpaid" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filterStatus === 'unpaid' ? 'bg-slate-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                Belum Bayar (<?= $statGUnpaid ?>)
            </a>
            <a href="?tab=groups&status=rejected" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filterStatus === 'rejected' ? 'bg-rose-600 text-white shadow-xs' : 'bg-rose-50 border border-rose-200 text-rose-900 hover:bg-rose-100' ?>">
                Ditolak (<?= $statGRejected ?>)
            </a>
        </div>

        <!-- TABEL TRANSAKSI ROMBONGAN -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-bold border-b border-slate-200/70">
                        <tr>
                            <th class="py-3 px-3 text-center w-8">No</th>
                            <th class="py-3 px-3">Kode Transaksi</th>
                            <th class="py-3 px-4">Asal Kepengurusan</th>
                            <th class="py-3 px-4">Ketua / PIC</th>
                            <th class="py-3 px-3 text-center">Delegasi</th>
                            <th class="py-3 px-4 text-right">Total Tagihan</th>
                            <th class="py-3 px-4 text-center">Bukti Transfer</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Aksi Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($groupsList)): ?>
                            <tr>
                                <td colspan="9" class="p-8 text-center text-slate-400">
                                    Tidak ada data transaksi pembayaran yang sesuai filter.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($groupsList as $idx => $g): ?>
                            <tr class="hover:bg-slate-50/70 transition <?= $g['payment_status'] === 'pending' ? 'bg-amber-50/30' : '' ?>">
                                <td class="py-3 px-3 text-center text-slate-400 font-bold"><?= $idx + 1 ?></td>
                                
                                <td class="py-3 px-3">
                                    <div class="font-mono font-black text-emerald-900 text-xs"><?= clean($g['group_code']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5"><?= date('d/m/Y H:i', strtotime($g['created_at'])) ?></div>
                                </td>

                                <td class="py-3 px-4">
                                    <span class="inline-block px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider mb-0.5 <?= $g['kategori'] === 'PP' ? 'bg-blue-100 text-blue-900' : ($g['kategori'] === 'PW' ? 'bg-purple-100 text-purple-900' : 'bg-emerald-100 text-emerald-900') ?>">
                                        <?= clean($g['kategori']) ?>
                                    </span>
                                    <div class="font-extrabold text-slate-900">
                                        <?php 
                                        if ($g['kategori'] === 'PP') echo "Pengurus Pusat (PP)";
                                        elseif ($g['kategori'] === 'PW') echo clean($g['pw_name']);
                                        else echo clean($g['daerah_name'] ?: ($g['db_daerah_name'] ?? '-'));
                                        ?>
                                    </div>
                                    <?php if ($g['kategori'] === 'PD' && !empty($g['pw_name'])): ?>
                                        <div class="text-[10px] text-slate-400"><?= clean($g['pw_name']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800"><?= clean($g['pic_name']) ?></div>
                                    <div class="text-[10px] text-slate-500 font-mono flex items-center gap-1 mt-0.5">
                                        <span><?= clean($g['pic_phone']) ?></span>
                                        <?php 
                                        $cleanWa = preg_replace('/[^0-9]/', '', $g['pic_phone']);
                                        if (strpos($cleanWa, '0') === 0) $cleanWa = '62' . substr($cleanWa, 1);
                                        ?>
                                        <a href="https://wa.me/<?= $cleanWa ?>" target="_blank" title="Chat via WhatsApp" class="text-emerald-700 hover:text-emerald-900">
                                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                        </a>
                                    </div>
                                </td>

                                <td class="py-3 px-3 text-center">
                                    <span class="font-extrabold text-slate-800 text-xs"><?= (int)$g['total_participants'] ?></span>
                                    <span class="text-[10px] text-slate-400 block">Orang</span>
                                </td>

                                <td class="py-3 px-4 text-right">
                                    <div class="font-mono font-black text-slate-900 text-xs"><?= formatRupiah($g['total_amount']) ?></div>
                                    <?php if (!empty($g['payment_amount_paid']) && (float)$g['payment_amount_paid'] != (float)$g['total_amount']): ?>
                                        <div class="text-[10px] text-amber-700 font-mono">Transfer: <?= formatRupiah($g['payment_amount_paid']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4 text-center">
                                    <?php if (!empty($g['payment_proof'])): ?>
                                        <?php 
                                        $fExt = strtolower(pathinfo($g['payment_proof'], PATHINFO_EXTENSION));
                                        $proofUrl = BASE_URL . clean($g['payment_proof']);
                                        ?>
                                        <?php if (in_array($fExt, ['jpg', 'jpeg', 'png', 'webp'])): ?>
                                            <button type="button" onclick="previewImage('<?= $proofUrl ?>', '<?= clean($g['group_code']) ?>')" class="inline-block w-12 h-12 rounded-xl overflow-hidden border border-slate-300 shadow-2xs hover:scale-105 transition">
                                                <img src="<?= $proofUrl ?>" alt="Struk" class="w-full h-full object-cover">
                                            </button>
                                        <?php else: ?>
                                            <a href="<?= $proofUrl ?>" target="_blank" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded text-[10px] font-bold text-emerald-800">
                                                Buka PDF
                                            </a>
                                        <?php endif; ?>
                                        <div class="text-[9px] text-slate-400 font-mono mt-0.5"><?= clean($g['payment_bank_sender'] ?: 'Bank') ?></div>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-400 italic">Belum Upload</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-3 text-center">
                                    <?= renderPaymentBadge($g['payment_status']) ?>
                                    <?php if ($g['payment_status'] === 'verified' && !empty($g['payment_verified_at'])): ?>
                                        <div class="text-[9px] text-slate-400 mt-0.5"><?= date('d/m H:i', strtotime($g['payment_verified_at'])) ?></div>
                                    <?php elseif ($g['payment_status'] === 'rejected' && !empty($g['payment_reject_reason'])): ?>
                                        <div class="text-[9px] text-rose-600 truncate max-w-[120px] mx-auto" title="<?= clean($g['payment_reject_reason']) ?>">
                                            <?= clean($g['payment_reject_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        <?php if ($g['payment_status'] !== 'verified'): ?>
                                            <button type="button" onclick="openValidateGroupModal(<?= $g['id'] ?>, '<?= clean($g['group_code']) ?>', '<?= clean($g['pic_name']) ?>', '<?= formatRupiah($g['total_amount']) ?>')" class="px-2.5 py-1 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-[11px] shadow-xs transition">
                                                ✓ Verifikasi
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($g['payment_status'] === 'pending'): ?>
                                            <button type="button" onclick="openRejectGroupModal(<?= $g['id'] ?>, '<?= clean($g['group_code']) ?>')" class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-[11px] transition">
                                                ✗ Tolak
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($g['payment_status'] === 'verified'): ?>
                                            <button type="button" onclick="resetGroupPayment(<?= $g['id'] ?>)" class="px-2 py-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 text-[10px] font-semibold transition" title="Reset kembali status pembayaran">
                                                Reset
                                            </button>
                                            <a href="<?= BASE_URL ?>print_proof.php?code=<?= urlencode($g['group_code']) ?>" target="_blank" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition">
                                                Cetak
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>

        <!-- TAB 2: REKAP PW (LEGACY) -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3 text-center w-8">No</th>
                            <th class="py-3 px-4">Pengurus Wilayah (PW)</th>
                            <th class="py-3 px-3 text-center">Delegasi</th>
                            <th class="py-3 px-4 text-right">Tagihan PW</th>
                            <th class="py-3 px-4 text-center">Bukti Transfer</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($pwPayments as $idx => $pw): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="py-3 px-3 text-center text-slate-400 font-bold"><?= $idx + 1 ?></td>
                                <td class="py-3 px-4">
                                    <div class="font-extrabold text-slate-900"><?= clean($pw['pw_name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= clean($pw['pw_code']) ?></div>
                                </td>
                                <td class="py-3 px-3 text-center font-bold text-slate-800"><?= (int)$pw['total_participants'] ?></td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900"><?= formatRupiah($pw['expected_total']) ?></td>
                                <td class="py-3 px-4 text-center">
                                    <?php if (!empty($pw['proof_file'])): ?>
                                        <button type="button" onclick="previewImage('<?= BASE_URL . clean($pw['proof_file']) ?>', '<?= clean($pw['pw_name']) ?>')" class="text-emerald-700 hover:underline font-bold text-[11px]">
                                            Lihat Bukti
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[10px]">Belum Ada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <?= renderPaymentBadge($pw['pay_status'] ?: 'unpaid') ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if (!empty($pw['payment_id'])): ?>
                                        <?php if ($pw['pay_status'] !== 'verified'): ?>
                                            <button type="button" onclick="openValidateModal(<?= $pw['payment_id'] ?>, '<?= clean($pw['pw_name']) ?>', '<?= formatRupiah($pw['total_amount'] ?: $pw['expected_total']) ?>')" class="px-2.5 py-1 rounded bg-emerald-700 text-white font-bold text-[11px]">
                                                Verifikasi
                                            </button>
                                        <?php else: ?>
                                            <button type="button" onclick="resetPayment(<?= $pw['payment_id'] ?>)" class="text-[10px] text-slate-400 hover:text-slate-600">
                                                Reset
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- Modal Validasi Group -->
<div id="modal-validate-group" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs" onclick="closeModal('modal-validate-group')"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-4 z-10 border border-slate-200">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-emerald-100 rounded-2xl flex items-center justify-center text-emerald-800 text-lg">
                ✓
            </div>
            <div>
                <h3 class="font-extrabold text-slate-900 text-sm">Verifikasi Pembayaran Rombongan</h3>
                <p class="text-xs text-slate-500">Konfirmasi bahwa dana transfer telah masuk rekening panitia</p>
            </div>
        </div>

        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs space-y-1.5 text-emerald-950">
            <div>Kode Transaksi: <strong id="m-grp-code" class="font-mono text-emerald-900">—</strong></div>
            <div>PIC Rombongan: <strong id="m-grp-pic">—</strong></div>
            <div>Total Tagihan: <strong id="m-grp-amount" class="font-mono text-emerald-900">—</strong></div>
            <p class="pt-2 text-[11px] text-emerald-800 border-t border-emerald-200 mt-2">
                Setelah diverifikasi, kwitansi resmi di bukti pendaftaran delegasi akan otomatis berstatus <strong>LUNAS</strong>.
            </p>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="button" onclick="submitValidateGroup()" class="flex-1 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl text-xs transition">
                Ya, Verifikasi Lunas
            </button>
            <button type="button" onclick="closeModal('modal-validate-group')" class="px-4 py-2.5 border border-slate-200 text-slate-600 font-semibold rounded-xl text-xs hover:bg-slate-50 transition">
                Batal
            </button>
        </div>
    </div>
</div>

<!-- Modal Tolak Group -->
<div id="modal-reject-group" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs" onclick="closeModal('modal-reject-group')"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-4 z-10 border border-slate-200">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-rose-100 rounded-2xl flex items-center justify-center text-rose-800 text-lg">
                ✕
            </div>
            <div>
                <h3 class="font-extrabold text-slate-900 text-sm">Tolak Konfirmasi Pembayaran</h3>
                <p class="text-xs text-slate-500" id="m-rej-grp-code">Kode: —</p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Alasan Penolakan <span class="text-rose-500">*</span></label>
            <textarea id="m-rej-reason" rows="3" placeholder="Contoh: Nominal tidak sesuai dengan mutasi bank, foto struk buram, dll." class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-rose-500 focus:outline-none resize-none"></textarea>
            <p class="text-[10px] text-slate-400 mt-1">Alasan ini akan ditampilkan pada portal konfirmasi pendaftar.</p>
        </div>

        <div class="flex gap-2 pt-1">
            <button type="button" onclick="submitRejectGroup()" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs transition">
                Tolak Pembayaran Ini
            </button>
            <button type="button" onclick="closeModal('modal-reject-group')" class="px-4 py-2.5 border border-slate-200 text-slate-600 font-semibold rounded-xl text-xs hover:bg-slate-50 transition">
                Batal
            </button>
        </div>
    </div>
</div>

<!-- Modal Preview Foto Bukti Transfer -->
<div id="modal-preview-proof" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/75 backdrop-blur-sm" onclick="closeModal('modal-preview-proof')"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-5 space-y-3 z-10 overflow-hidden">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <span class="text-xs font-extrabold text-slate-800" id="m-preview-title">Bukti Transfer</span>
            <button type="button" onclick="closeModal('modal-preview-proof')" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 text-xs">✕ Tutup</button>
        </div>
        <div class="max-h-[75vh] overflow-auto flex items-center justify-center bg-slate-100 rounded-2xl p-2">
            <img id="m-preview-img" src="" alt="Bukti Transfer" class="max-h-[70vh] object-contain rounded-xl">
        </div>
        <div class="text-right pt-1">
            <a id="m-preview-link" href="#" target="_blank" class="text-xs font-bold text-emerald-800 hover:underline">Buka Gambar di Tab Baru ↗</a>
        </div>
    </div>
</div>

<!-- Modal Validasi Legacy PW -->
<div id="modal-validate" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs" onclick="closeModal('modal-validate')"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-4 z-10 border border-slate-200">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-emerald-100 rounded-2xl flex items-center justify-center text-emerald-800 text-lg">
                ✓
            </div>
            <div>
                <h3 class="font-extrabold text-slate-900 text-sm">Validasi Pembayaran PW</h3>
                <p class="text-xs text-slate-500">Konfirmasi penerimaan iuran delegasi PW</p>
            </div>
        </div>
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs space-y-1">
            <p>Pengurus Wilayah: <strong id="modal-validate-pw-name" class="text-emerald-900">—</strong></p>
            <p>Jumlah: <strong id="modal-validate-amount" class="text-emerald-900 font-mono">—</strong></p>
        </div>
        <div class="flex gap-2 pt-2">
            <button onclick="submitValidatePW()" class="flex-1 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl text-xs transition">
                Ya, Validasi Pembayaran
            </button>
            <button onclick="closeModal('modal-validate')" class="px-4 py-2.5 border border-slate-200 text-slate-600 font-semibold rounded-xl text-xs hover:bg-slate-50 transition">
                Batal
            </button>
        </div>
    </div>
</div>

<script>
let activeGroupId   = null;
let activePaymentId = null;

function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.getElementById(id).classList.add('flex');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.getElementById(id).classList.remove('flex');
}

function previewImage(url, title) {
    document.getElementById('m-preview-img').src = url;
    document.getElementById('m-preview-link').href = url;
    document.getElementById('m-preview-title').textContent = 'Bukti Transfer: ' + title;
    openModal('modal-preview-proof');
}

// Modal Group Actions
function openValidateGroupModal(gId, code, pic, amount) {
    activeGroupId = gId;
    document.getElementById('m-grp-code').textContent = code;
    document.getElementById('m-grp-pic').textContent  = pic;
    document.getElementById('m-grp-amount').textContent = amount;
    openModal('modal-validate-group');
}

function openRejectGroupModal(gId, code) {
    activeGroupId = gId;
    document.getElementById('m-rej-grp-code').textContent = 'Kode: ' + code;
    document.getElementById('m-rej-reason').value = '';
    openModal('modal-reject-group');
}

function submitValidateGroup() {
    if (!activeGroupId) return;
    executeAction({
        action: 'verify',
        item_type: 'group',
        group_id: activeGroupId
    });
    closeModal('modal-validate-group');
}

function submitRejectGroup() {
    const reason = document.getElementById('m-rej-reason').value.trim();
    if (!reason) {
        alert('Mohon masukkan alasan penolakan.');
        return;
    }
    executeAction({
        action: 'reject',
        item_type: 'group',
        group_id: activeGroupId,
        reject_reason: reason
    });
    closeModal('modal-reject-group');
}

function resetGroupPayment(gId) {
    if (!confirm('Yakin ingin mereset status pembayaran rombongan ini ke Belum Bayar?')) return;
    executeAction({
        action: 'reset',
        item_type: 'group',
        group_id: gId
    });
}

// Legacy PW actions
function openValidateModal(payId, pwName, amount) {
    activePaymentId = payId;
    document.getElementById('modal-validate-pw-name').textContent = pwName;
    document.getElementById('modal-validate-amount').textContent = amount;
    openModal('modal-validate');
}

function submitValidatePW() {
    if (!activePaymentId) return;
    executeAction({
        action: 'verify',
        item_type: 'pw',
        payment_id: activePaymentId
    });
    closeModal('modal-validate');
}

function resetPayment(pId) {
    if (!confirm('Yakin ingin mereset status pembayaran PW ini?')) return;
    executeAction({
        action: 'reset',
        item_type: 'pw',
        payment_id: pId
    });
}

function executeAction(payload) {
    const fd = new FormData();
    for (const k in payload) {
        fd.append(k, payload[k]);
    }

    fetch('<?= BASE_URL ?>admin/payments.php', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'Terjadi kesalahan.');
        }
    })
    .catch(err => {
        alert('Gagal memproses permintaan: ' + err);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
