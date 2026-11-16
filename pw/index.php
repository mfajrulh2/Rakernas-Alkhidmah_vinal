<?php
/**
 * Dashboard Admin Pengurus Wilayah (PW)
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

// Ambil info PW & Kuota
$stmtW = $pdo->prepare("SELECT * FROM wilayah WHERE id = ?");
$stmtW->execute([$pw_id]);
$pw = $stmtW->fetch();

if (!$pw) {
    die("Wilayah Anda belum terkonfigurasi. Hubungi Panitia Pusat.");
}

// Hitung delegasi terdaftar untuk PW ini
$stmtCount = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN participant_type = 'utusan' THEN 1 ELSE 0 END) as utusan_count,
        SUM(CASE WHEN participant_type = 'peninjau' THEN 1 ELSE 0 END) as peninjau_count,
        SUM(CASE WHEN is_attended = 1 THEN 1 ELSE 0 END) as hadir_count
    FROM participants 
    WHERE pw_id = ?
");
$stmtCount->execute([$pw_id]);
$stats = $stmtCount->fetch();

$totalTerdaftar = (int)$stats['total'];
$utusanCount    = (int)$stats['utusan_count'];
$peninjauCount  = (int)$stats['peninjau_count'];
$hadirCount     = (int)$stats['hadir_count'];

$sisaUtusan   = max(0, $pw['quota_utusan'] - $utusanCount);
$sisaPeninjau = max(0, $pw['quota_peninjau'] - $peninjauCount);

// Ambil 5 delegasi terakhir
$stmtLast = $pdo->prepare("
    SELECT p.*, r.building_name, r.room_number 
    FROM participants p 
    LEFT JOIN rooms r ON p.room_id = r.id 
    WHERE p.pw_id = ? 
    ORDER BY p.id DESC 
    LIMIT 10
");
$stmtLast->execute([$pw_id]);
$delegates = $stmtLast->fetchAll();

// Ambil status pembayaran PW
$payInfo = getPWPaymentInfo($pw_id);

$page_title = "Portal PW - " . clean($pw['name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8 mb-16">
    <!-- Header Card PW -->
    <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider text-emerald-800 bg-emerald-50 px-3 py-1 rounded-full mb-2">
                <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                Portal Mandiri Pengurus Wilayah
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                <?= clean($pw['name']) ?>
            </h1>
            <p class="text-xs text-slate-500 mt-1 font-mono">
                Kode Wilayah: <strong><?= clean($pw['code']) ?></strong> • Kontak: <?= clean($pw['contact_person'] ?: '-') ?> (<?= clean($pw['contact_phone'] ?: '-') ?>)
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="<?= BASE_URL ?>pw/register_bulk.php" class="px-5 py-3 rounded-2xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-md shadow-emerald-900/10 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Daftarkan Kolektif (Cepat)</span>
            </a>
            <a href="<?= BASE_URL ?>pw/payment.php" class="px-4 py-3 rounded-2xl bg-amber-50 border border-amber-300 text-amber-800 font-bold text-xs hover:bg-amber-100 transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Iuran & Pembayaran</span>
            </a>
            <?php if ($payInfo['is_verified']): ?>
            <a href="<?= BASE_URL ?>pw/print_cards.php" class="px-4 py-3 rounded-2xl bg-white border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak Semua ID Card</span>
            </a>
            <?php else: ?>
            <a href="<?= BASE_URL ?>pw/payment.php" title="Selesaikan pembayaran iuran untuk mencetak Bukti Pendaftaran / ID Card" class="px-4 py-3 rounded-2xl bg-slate-100 border border-slate-200 text-slate-400 font-bold text-xs cursor-not-allowed flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>Cetak ID Card (Terkunci)</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kuota Progress Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <!-- Utusan -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Kuota Utusan</span>
                <span class="text-xs font-bold text-slate-400">Hak Suara & Bicara</span>
            </div>
            <div class="text-3xl font-extrabold text-slate-900"><?= $utusanCount ?> <span class="text-sm font-normal text-slate-400">/ <?= $pw['quota_utusan'] ?> orang</span></div>
            <div class="mt-3 w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-emerald-600 h-2 rounded-full" style="width: <?= min(100, round(($utusanCount / max(1, $pw['quota_utusan'])) * 100)) ?>%"></div>
            </div>
            <div class="text-[11px] text-slate-500 mt-2 flex justify-between">
                <span>Sisa Kuota:</span>
                <span class="font-bold <?= $sisaUtusan == 0 ? 'text-rose-600' : 'text-emerald-700' ?>"><?= $sisaUtusan ?> Kursi</span>
            </div>
        </div>

        <!-- Peninjau -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700">Kuota Peninjau</span>
                <span class="text-xs font-bold text-slate-400">Hak Bicara</span>
            </div>
            <div class="text-3xl font-extrabold text-slate-900"><?= $peninjauCount ?> <span class="text-sm font-normal text-slate-400">/ <?= $pw['quota_peninjau'] ?> orang</span></div>
            <div class="mt-3 w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-amber-500 h-2 rounded-full" style="width: <?= min(100, round(($peninjauCount / max(1, $pw['quota_peninjau'])) * 100)) ?>%"></div>
            </div>
            <div class="text-[11px] text-slate-500 mt-2 flex justify-between">
                <span>Sisa Kuota:</span>
                <span class="font-bold <?= $sisaPeninjau == 0 ? 'text-rose-600' : 'text-amber-700' ?>"><?= $sisaPeninjau ?> Kursi</span>
            </div>
        </div>

        <!-- Presensi Hadir -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-blue-700">Tiba di Lokasi</span>
                <span class="text-xs font-bold text-slate-400">Check-in</span>
            </div>
            <div class="text-3xl font-extrabold text-blue-900"><?= $hadirCount ?> <span class="text-sm font-normal text-slate-400">/ <?= $totalTerdaftar ?> hadir</span></div>
            <div class="mt-3 w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $totalTerdaftar > 0 ? min(100, round(($hadirCount / $totalTerdaftar) * 100)) : 0 ?>%"></div>
            </div>
            <div class="text-[11px] text-slate-500 mt-2 flex justify-between">
                <span>Persentase Kehadiran:</span>
                <span class="font-bold text-blue-700"><?= $totalTerdaftar > 0 ? round(($hadirCount / $totalTerdaftar) * 100) : 0 ?>%</span>
            </div>
        </div>
    </div>

    <!-- Kartu Link Khusus Registrasi Tanpa Login untuk PW -->
    <?php 
    $pwRegUrl = getPWRegistrationUrl($pw['reg_token'] ?? '');
    $pwWaMsg = "Assalamu'alaikum Wr. Wb.\n\nBerikut tautan pendaftaran delegasi Pengurus Wilayah Al Khidmah *" . clean($pw['name']) . "* untuk acara RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH:\n\n" . $pwRegUrl . "\n\nSisa Kuota Utusan: " . $sisaUtusan . " orang\nSisa Kuota Peninjau: " . $sisaPeninjau . " orang\n\nSilakan mengisi data delegasi melalui tautan di atas tanpa perlu login. Terima kasih.";
    $pwShareWa = "https://api.whatsapp.com/send?text=" . urlencode($pwWaMsg);
    ?>
    <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-amber-50 border-2 border-emerald-300 rounded-3xl p-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-emerald-700 text-white flex items-center justify-center shrink-0 shadow-md">
                    <svg class="w-6 h-6 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </div>
                <div>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-200 text-emerald-900 text-[10px] font-black uppercase tracking-wider mb-1">
                        Fitur Mandiri PW
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900">Tautan Khusus Pendaftaran Delegasi PW Anda (Tanpa Perlu Login)</h3>
                    <p class="text-xs text-slate-600 mt-1 max-w-2xl leading-relaxed">
                        Anda dapat membagikan link ini kepada Sekretaris atau Delegasi dari Pengurus Daerah (PD) di wilayah Anda agar mereka dapat langsung mengisikan datanya sendiri tanpa harus mengetahui password akun PW ini. Kuota tetap terkontrol dan tersimpan otomatis ke akun Anda.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-emerald-200/80 flex flex-col sm:flex-row items-center gap-3">
            <div class="w-full sm:flex-grow flex items-center">
                <input type="text" id="pw-reglink-input" readonly value="<?= clean($pwRegUrl) ?>" class="w-full px-4 py-2.5 rounded-xl border border-emerald-300 bg-white text-xs font-mono text-slate-800 focus:outline-none select-all shadow-inner">
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button type="button" onclick="copyPWRegLink()" class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow transition flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                    <span>Salin Link</span>
                </button>
                <a href="<?= clean($pwShareWa) ?>" target="_blank" class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow transition flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.1.824z"/></svg>
                    <span>Bagikan ke WA</span>
                </a>
            </div>
        </div>
        <div id="pw-copy-toast" class="hidden text-xs text-emerald-800 font-bold mt-2 flex items-center gap-1">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Tautan registrasi berhasil disalin ke clipboard!
        </div>
    </div>

    <!-- Delegasi Terdaftar Table -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">Daftar Delegasi Wilayah Anda</h2>
                <p class="text-xs text-slate-500 mt-0.5">Seluruh utusan dan peninjau yang didaftarkan oleh <?= clean($pw['name']) ?></p>
            </div>
            <a href="<?= BASE_URL ?>pw/participants.php" class="text-xs font-bold text-alkhidmah-700 hover:text-alkhidmah-900">
                Lihat Lengkap &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-6">Kode & Kehadiran</th>
                        <th class="py-3 px-6">Nama Delegasi</th>
                        <th class="py-3 px-4">Pengurus Daerah & Jabatan</th>
                        <th class="py-3 px-4">Klasifikasi</th>
                        <th class="py-3 px-4">Kamar Penginapan</th>
                        <th class="py-3 px-6 text-right">E-ID Card</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($delegates)): ?>
                        <tr><td colspan="6" class="py-10 text-center text-slate-400">Belum ada delegasi yang didaftarkan. Silakan klik tombol "Daftarkan Kolektif" di atas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($delegates as $d): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-6">
                                    <div class="font-mono font-bold text-slate-800"><?= clean($d['registration_code']) ?></div>
                                    <div class="mt-1"><?= renderAttendanceBadge($d['is_attended'], $d['attended_at']) ?></div>
                                </td>

                                <td class="py-3.5 px-6">
                                    <div class="font-bold text-slate-900 text-sm"><?= clean($d['full_name']) ?></div>
                                    <div class="text-[11px] text-slate-500 font-mono">WA: <?= clean($d['phone']) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-800"><?= clean($d['pd_name'] ?: 'PW') ?></div>
                                    <div class="text-[11px] text-emerald-800"><?= clean($d['jabatan_organisasi']) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <?= renderParticipantBadge($d['participant_type']) ?>
                                    <div class="text-[10px] text-slate-500 mt-1"><?= clean($d['komisi']) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <?php if (!empty($d['building_name'])): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-50 text-indigo-700">
                                            <?= clean($d['building_name']) ?> - <?= clean($d['room_number']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Ditetapkan Panitia</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-6 text-right">
                                    <?php if ($payInfo['is_verified']): ?>
                                    <a href="<?= BASE_URL ?>print_card.php?code=<?= urlencode($d['registration_code']) ?>" target="_blank" class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold text-xs inline-flex items-center gap-1 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        Cetak
                                    </a>
                                    <?php else: ?>
                                    <a href="<?= BASE_URL ?>pw/payment.php" title="Terkunci: Selesaikan pembayaran iuran dahulu" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-400 font-bold text-xs inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        Terkunci
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyPWRegLink() {
    const input = document.getElementById('pw-reglink-input');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const toast = document.getElementById('pw-copy-toast');
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 2500);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
