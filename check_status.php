<?php
/**
 * Halaman Cek Mandiri Delegasi & Cetak E-ID Card
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDB();
$keyword = trim($_GET['q'] ?? '');
$results = [];
$searched = false;

if (!empty($keyword)) {
    $searched = true;
    $clean_q = preg_replace('/[^a-zA-Z0-9]/', '', $keyword);
    
    $stmt = $pdo->prepare("
        SELECT p.*, w.name as pw_name, w.code as pw_code, r.building_name, r.room_number, g.group_code, g.payment_status as group_pay_status, g.kategori as group_kategori
        FROM participants p 
        LEFT JOIN wilayah w ON p.pw_id = w.id 
        LEFT JOIN rooms r ON p.room_id = r.id 
        LEFT JOIN registration_groups g ON p.group_id = g.id
        WHERE p.registration_code = :exact 
           OR p.phone LIKE :phone 
           OR p.nik LIKE :nik 
           OR p.full_name LIKE :name
           OR g.group_code = :exact
        ORDER BY p.id DESC
        LIMIT 10
    ");
    $stmt->execute([
        ':exact' => $keyword,
        ':phone' => "%{$keyword}%",
        ':nik'   => "%{$keyword}%",
        ':name'  => "%{$keyword}%"
    ]);
    $results = $stmt->fetchAll();
}

$page_title = "Cek Status Delegasi & E-ID Card";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto mb-16">
    <!-- Header Page -->
    <div class="text-center max-w-xl mx-auto mb-8">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Pencarian Delegasi & E-ID Card</h1>
        <p class="text-sm text-slate-600 mt-2">
            Masukkan <strong>Nomor WhatsApp</strong>, <strong>NIK</strong>, atau <strong>Kode Registrasi</strong> yang telah didaftarkan oleh PW.
        </p>
    </div>

    <!-- Search Box Card -->
    <div class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-200/80 shadow-md mb-8">
        <form action="" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-grow">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" name="q" required value="<?= clean($keyword) ?>" placeholder="Contoh: 08123456789 atau RKN-JTM-001 atau Nama Lengkap" class="w-full pl-12 pr-4 py-3.5 rounded-2xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-alkhidmah-600 focus:border-alkhidmah-600 text-sm text-slate-800 transition">
            </div>
            <button type="submit" class="py-3.5 px-8 rounded-2xl bg-alkhidmah-800 text-white font-bold hover:bg-alkhidmah-900 transition shadow-md shadow-emerald-900/10 flex items-center justify-center gap-2">
                <span>Cari Data</span>
            </button>
        </form>
    </div>

    <!-- Search Results -->
    <?php if ($searched): ?>
        <?php if (empty($results)): ?>
            <div class="bg-white p-10 rounded-3xl border border-slate-200 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">Data Tidak Ditemukan</h3>
                <p class="text-sm text-slate-500 max-w-md mx-auto">
                    Tidak ditemukan delegasi dengan kata kunci "<strong><?= clean($keyword) ?></strong>". Pastikan data telah didaftarkan oleh Pengurus Wilayah (PW) Anda.
                </p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500 px-1">
                    <span>Ditemukan <?= count($results) ?> data delegasi:</span>
                </div>

                <?php foreach ($results as $item): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition overflow-hidden">
                        <div class="p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="flex-grow space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-300">
                                        <?= clean($item['registration_code']) ?>
                                    </span>
                                    <?= renderParticipantBadge($item['participant_type']) ?>
                                    <?= renderAttendanceBadge($item['is_attended'], $item['attended_at']) ?>
                                    <?php if (!empty($item['group_code']) && ($item['group_kategori'] ?? '') !== 'Tamu Undangan'): ?>
                                        <?php if (($item['group_pay_status'] ?? '') === 'verified'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                ✓ Iuran Lunas
                                            </span>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>konfirmasi_pembayaran.php?code=<?= urlencode($item['group_code']) ?>" class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300 hover:bg-amber-200 transition">
                                                💳 Konfirmasi Pembayaran
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <h2 class="text-xl font-extrabold text-slate-900"><?= clean($item['full_name']) ?></h2>
                                    <div class="text-sm font-semibold text-alkhidmah-700 mt-0.5">
                                        <?= clean($item['jabatan_organisasi']) ?> • <?= clean($item['pd_name'] ?: $item['pw_name']) ?>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        Pengurus Wilayah: <strong><?= clean($item['pw_name']) ?></strong>
                                    </div>
                                </div>

                                <!-- Detail Fasilitas & Kamar -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100 text-xs">
                                    <div class="flex items-center gap-2 text-slate-600">
                                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <span>
                                            Kamar: <strong><?= !empty($item['building_name']) ? clean($item['building_name'] . ' - ' . $item['room_number']) : '<span class="text-amber-600 font-normal">Akan ditentukan panitia</span>' ?></strong>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-600">
                                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        <span>Sidang: <strong><?= clean($item['komisi']) ?></strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- QR Code Preview & Print Action -->
                            <div class="flex md:flex-col items-center justify-between md:justify-center gap-4 pt-4 md:pt-0 border-t md:border-t-0 md:border-l border-slate-100 md:pl-8">
                                <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-sm text-center">
                                    <img src="<?= getQRCodeUrl($item['registration_code'], 120) ?>" alt="QR Code" class="w-24 h-24 object-contain mx-auto">
                                    <div class="text-[10px] font-mono text-slate-400 mt-1">Scan Presensi</div>
                                </div>
                                <a href="<?= BASE_URL ?>print_card.php?code=<?= urlencode($item['registration_code']) ?>" target="_blank" class="px-5 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-sm flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    <span>Cetak ID Card</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
