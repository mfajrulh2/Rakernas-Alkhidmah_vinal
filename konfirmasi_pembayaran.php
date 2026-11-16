<?php
/**
 * Halaman Konfirmasi Pembayaran Iuran Delegasi
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

ensureRegistrationTablesExist();
$pdo = getDB();

$code = trim($_GET['code'] ?? ($_POST['group_code'] ?? ''));
$groupData = null;
$errors = [];
$successMsg = '';

// Ambil info rekening & kontak resmi dari settings
$bankName   = getSetting('payment_bank_name', 'Bank Rakyat Indonesia (BRI)');
$accountNo  = getSetting('payment_account_no', '0023-01-001234-53-1');
$accountOwner = getSetting('payment_account_name', "Panitia Pelaksana RAKERNAS Jama'ah Al Khidmah");
$panitiaWa  = getSetting('contact_center', '0857-2726-6990');
$panitiaPic = getSetting('panitia_contact_name', 'M Ilyas Balya');

// Handle Form Submission: Upload Bukti Transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_confirmation'])) {
    $groupCodePost = trim($_POST['group_code'] ?? '');
    $bankSender    = trim($_POST['bank_sender'] ?? '');
    $paymentDate   = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $amountPaid    = (float)str_replace(['.', ','], ['', '.'], trim($_POST['amount_paid'] ?? '0'));
    $paymentNotes  = trim($_POST['payment_notes'] ?? '');

    // Cari group
    $stmtG = $pdo->prepare("SELECT * FROM registration_groups WHERE group_code = ? LIMIT 1");
    $stmtG->execute([$groupCodePost]);
    $targetGroup = $stmtG->fetch();

    if (!$targetGroup) {
        $errors[] = "Kode transaksi registrasi tidak ditemukan.";
    } elseif ($targetGroup['kategori'] === 'Tamu Undangan') {
        $errors[] = "Kategori Tamu Undangan bebas dari iuran akomodasi.";
    } else {
        if (empty($bankSender)) {
            $errors[] = "Nama bank / nama pemilik rekening pengirim wajib diisi.";
        }
        if (empty($paymentDate)) {
            $errors[] = "Tanggal transfer wajib diisi.";
        }
        if ($amountPaid <= 0) {
            $errors[] = "Nominal yang ditransfer harus lebih dari 0.";
        }

        // Upload bukti transfer
        if (!isset($_FILES['proof_file']) || empty($_FILES['proof_file']['name'])) {
            $errors[] = "File foto / scan bukti transfer wajib diunggah.";
        } else {
            $upload = uploadPaymentProof($_FILES['proof_file']);
            if (!empty($upload['error'])) {
                $errors[] = $upload['error'];
            } else {
                $proofPath = $upload['path'];
            }
        }

        if (empty($errors)) {
            $stmtUp = $pdo->prepare("
                UPDATE registration_groups SET
                    payment_status = 'pending',
                    payment_proof = ?,
                    payment_date = ?,
                    payment_bank_sender = ?,
                    payment_amount_paid = ?,
                    payment_notes = ?,
                    payment_reject_reason = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUp->execute([
                $proofPath,
                $paymentDate,
                $bankSender,
                $amountPaid,
                $paymentNotes,
                $targetGroup['id']
            ]);

            setFlash('success', 'Alhamdulillah! Konfirmasi pembayaran Anda berhasil dikirim. Panitia bendahara akan segera memverifikasi bukti transfer Anda.');
            header("Location: " . BASE_URL . "konfirmasi_pembayaran.php?code=" . urlencode($groupCodePost));
            exit;
        }
    }
}

// Cari data transaksi rombongan jika parameter code ada
if (!empty($code)) {
    $stmt = $pdo->prepare("
        SELECT g.*, w.name AS pw_name, w.code AS pw_code, d.name AS db_daerah_name
        FROM registration_groups g
        LEFT JOIN wilayah w ON g.pw_id = w.id
        LEFT JOIN daerah d ON g.daerah_id = d.id
        WHERE g.group_code = :code OR g.pic_phone = :phone
        ORDER BY g.id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':code'  => $code,
        ':phone' => $code
    ]);
    $groupData = $stmt->fetch();
}

$page_title = "Konfirmasi Pembayaran Iuran Delegasi";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto mb-20 space-y-8">

    <!-- Header Section -->
    <div class="text-center max-w-2xl mx-auto">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-900 border border-emerald-300 mb-3">
            <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            KONFIRMASI PEMBAYARAN IURAN AKOMODASI
        </span>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Kirim Bukti Pembayaran</h1>
        <p class="text-xs sm:text-sm text-slate-600 mt-2">
            Silakan unggah struk/bukti transfer bank di formulir ini atau konfirmasi langsung melalui WhatsApp Panitia Bendahara.
        </p>
    </div>

    <!-- Alert Flash -->
    <?php if ($flash = getFlash('success')): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 text-xs sm:text-sm flex items-start gap-3 shadow-xs">
            <svg class="w-5 h-5 text-emerald-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <div class="font-medium"><?= $flash ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <?php foreach ($errors as $err): ?>
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                    <span><?= clean($err) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Form Pencarian Kode jika belum ada code -->
    <?php if (!$groupData): ?>
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm">
            <div class="max-w-md mx-auto text-center space-y-4">
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-800 flex items-center justify-center mx-auto text-xl font-bold">
                    🔍
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Masukkan Kode Transaksi Registrasi</h3>
                    <p class="text-xs text-slate-500 mt-1">Kode transaksi tertera pada kwitansi pendaftaran (contoh: <code>REG-20261116-XXXX</code>) atau gunakan nomor WhatsApp PIC/Ketua Rombongan.</p>
                </div>

                <form action="" method="GET" class="flex gap-2 pt-2">
                    <input type="text" name="code" required value="<?= clean($code) ?>" placeholder="Contoh: REG-20261116-B13A" class="w-full px-4 py-3 rounded-2xl border border-slate-300 text-xs sm:text-sm uppercase font-mono font-bold focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    <button type="submit" class="px-6 py-3 rounded-2xl bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs sm:text-sm transition shrink-0">
                        Cari
                    </button>
                </form>

                <?php if (!empty($code) && !$groupData): ?>
                    <p class="text-xs text-rose-600 font-medium pt-2">
                        Data pendaftaran dengan kode "<?= clean($code) ?>" tidak ditemukan. Mohon periksa kembali kode Anda.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>

        <!-- Informasi Rombongan & Status Transaksi -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Kode Transaksi Rombongan</span>
                    <h2 class="text-xl font-black font-mono text-emerald-900"><?= clean($groupData['group_code']) ?></h2>
                </div>
                <div>
                    <?php if ($groupData['payment_status'] === 'verified'): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-900 border border-emerald-300">
                            <svg class="w-4 h-4 text-emerald-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            STATUS: LUNAS / TERVERIFIKASI
                        </span>
                    <?php elseif ($groupData['payment_status'] === 'pending'): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black bg-amber-100 text-amber-900 border border-amber-300">
                            <svg class="w-4 h-4 text-amber-700 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>
                            STATUS: MENUNGGU VERIFIKASI BENDAHARA
                        </span>
                    <?php elseif ($groupData['payment_status'] === 'rejected'): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black bg-rose-100 text-rose-900 border border-rose-300">
                            <svg class="w-4 h-4 text-rose-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                            STATUS: KONFIRMASI DITOLAK
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-300">
                            STATUS: MENUNGGU PEMBAYARAN
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detail Pendaftaran -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Kategori</span>
                    <span class="font-black text-slate-800"><?= clean($groupData['kategori']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Asal Kepengurusan</span>
                    <span class="font-bold text-slate-800">
                        <?php 
                        if ($groupData['kategori'] === 'PP') echo "Pengurus Pusat (PP)";
                        elseif ($groupData['kategori'] === 'PW') echo clean($groupData['pw_name']);
                        else echo clean($groupData['daerah_name'] ?: ($groupData['db_daerah_name'] ?? '-')) . " (" . clean($groupData['pw_name']) . ")";
                        ?>
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Ketua / PIC</span>
                    <span class="font-bold text-slate-800"><?= clean($groupData['pic_name']) ?></span>
                    <div class="text-[10px] text-slate-400 font-mono"><?= clean($groupData['pic_phone']) ?></div>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Total Delegasi</span>
                    <span class="font-extrabold text-slate-900"><?= (int)$groupData['total_participants'] ?> Orang</span>
                </div>
            </div>

            <!-- Tagihan Nominal Iuran -->
            <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wider block">Total Tagihan Iuran Akomodasi</span>
                    <div class="text-2xl sm:text-3xl font-black text-amber-950"><?= formatRupiah($groupData['total_amount']) ?></div>
                    <p class="text-[11px] text-amber-800 mt-0.5">Iuran: <?= formatRupiah($groupData['fee_per_person']) ?> / delegasi × <?= (int)$groupData['total_participants'] ?> orang</p>
                </div>
                <div class="sm:text-right">
                    <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wider block">Rekening Resmi Tujuan:</span>
                    <div class="font-black text-sm text-amber-950 font-mono"><?= clean($bankName) ?> • <?= clean($accountNo) ?></div>
                    <div class="text-[11px] text-amber-900">a.n. <?= clean($accountOwner) ?></div>
                </div>
            </div>

            <!-- Peringatan jika Ditolak -->
            <?php if ($groupData['payment_status'] === 'rejected'): ?>
                <div class="p-4 bg-rose-50 border border-rose-300 rounded-2xl text-xs text-rose-900 space-y-1">
                    <div class="font-extrabold flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-rose-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>Konfirmasi Pembayaran Ditolak oleh Panitia</span>
                    </div>
                    <?php if (!empty($groupData['payment_reject_reason'])): ?>
                        <div class="bg-white/80 p-2.5 rounded-xl border border-rose-200 mt-1">
                            <span class="font-bold text-rose-950">Alasan:</span> <?= clean($groupData['payment_reject_reason']) ?>
                        </div>
                    <?php endif; ?>
                    <p class="text-[11px] text-rose-700">Silakan unggah kembali bukti transfer yang jelas dan benar menggunakan form di bawah ini.</p>
                </div>
            <?php endif; ?>

            <!-- Preview Bukti jika status Pending atau Verified -->
            <?php if (!empty($groupData['payment_proof'])): ?>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 sm:p-5">
                    <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider block mb-2">Bukti Transfer yang Telah Diunggah:</span>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <?php 
                        $ext = strtolower(pathinfo($groupData['payment_proof'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])): 
                        ?>
                            <a href="<?= BASE_URL . clean($groupData['payment_proof']) ?>" target="_blank" class="block w-28 h-28 rounded-xl overflow-hidden border border-slate-300 shadow-xs hover:opacity-90">
                                <img src="<?= BASE_URL . clean($groupData['payment_proof']) ?>" alt="Bukti Transfer" class="w-full h-full object-cover">
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL . clean($groupData['payment_proof']) ?>" target="_blank" class="px-4 py-3 rounded-xl bg-white border border-slate-300 text-xs font-bold text-emerald-800 hover:bg-slate-50 flex items-center gap-2">
                                <span>📄 Buka Dokumen Bukti (PDF)</span>
                            </a>
                        <?php endif; ?>

                        <div class="text-xs space-y-1 text-slate-600">
                            <div><span class="font-semibold text-slate-800">Bank Pengirim:</span> <?= clean($groupData['payment_bank_sender'] ?: '-') ?></div>
                            <div><span class="font-semibold text-slate-800">Tanggal Transfer:</span> <?= clean($groupData['payment_date'] ? date('d/m/Y', strtotime($groupData['payment_date'])) : '-') ?></div>
                            <div><span class="font-semibold text-slate-800">Nominal Transfer:</span> <?= formatRupiah($groupData['payment_amount_paid'] ?: $groupData['total_amount']) ?></div>
                            <?php if (!empty($groupData['payment_notes'])): ?>
                                <div><span class="font-semibold text-slate-800">Catatan:</span> <?= clean($groupData['payment_notes']) ?></div>
                            <?php endif; ?>
                            <?php if ($groupData['payment_status'] === 'verified'): ?>
                                <div class="text-emerald-700 font-bold mt-1">✓ Diverifikasi pada: <?= date('d/m/Y H:i', strtotime($groupData['payment_verified_at'])) ?> WIB</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Aksi Tombol Bukti & Cetak jika sudah Lunas -->
            <?php if ($groupData['payment_status'] === 'verified'): ?>
                <div class="pt-2 flex flex-wrap gap-3">
                    <a href="<?= BASE_URL ?>print_proof.php?code=<?= urlencode($groupData['group_code']) ?>" target="_blank" class="px-5 py-2.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs flex items-center gap-2 shadow-sm transition">
                        <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Cetak Bukti Pendaftaran (Lunas)</span>
                    </a>
                    <a href="<?= BASE_URL ?>check_status.php?q=<?= urlencode($groupData['group_code']) ?>" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                        Cek Delegasi & ID Card
                    </a>
                </div>
            <?php endif; ?>

        </div>

        <!-- Opsi Konfirmasi Pembayaran (Tampil jika belum Lunas) -->
        <?php if ($groupData['payment_status'] !== 'verified'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Formulir Upload Bukti Mandiri (2 Kolom) -->
                <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-5">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm">📤</span>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">Metode 1: Unggah Bukti Transfer di Sini</h3>
                            <p class="text-[11px] text-slate-500">Bukti struk Anda akan langsung masuk ke antrean verifikasi bendahara panitia.</p>
                        </div>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="group_code" value="<?= clean($groupData['group_code']) ?>">

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                Unggah Foto / Scan Struk Transfer Bank *
                            </label>
                            <input type="file" name="proof_file" required accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full text-xs text-slate-500 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-800 hover:file:bg-emerald-100 border border-slate-200 rounded-2xl p-2 bg-slate-50/50">
                            <span class="text-[10px] text-slate-400 mt-1 block">Format didukung: JPG, PNG, WebP, atau PDF (Maksimal 5 MB).</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Bank & Pemilik Rekening Pengirim *</label>
                                <input type="text" name="bank_sender" required value="<?= clean($_POST['bank_sender'] ?? ($groupData['payment_bank_sender'] ?? '')) ?>" placeholder="Contoh: BRI a.n. H. Ahmad" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tanggal Transfer *</label>
                                <input type="date" name="payment_date" required value="<?= clean($_POST['payment_date'] ?? ($groupData['payment_date'] ?? date('Y-m-d'))) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nominal yang Ditransfer (Rp) *</label>
                            <input type="number" name="amount_paid" required value="<?= (int)($_POST['amount_paid'] ?? ($groupData['payment_amount_paid'] ?: $groupData['total_amount'])) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-bold font-mono focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Catatan Tambahan untuk Bendahara (Opsional)</label>
                            <textarea name="payment_notes" rows="2" placeholder="Tuliskan berita transfer atau keterangan tambahan jika ada..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none resize-none"><?= clean($_POST['payment_notes'] ?? ($groupData['payment_notes'] ?? '')) ?></textarea>
                        </div>

                        <div class="pt-2">
                            <button type="submit" name="submit_confirmation" class="w-full py-3 rounded-2xl bg-emerald-800 hover:bg-emerald-900 text-white font-extrabold text-xs sm:text-sm shadow-md transition flex items-center justify-center gap-2">
                                <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Kirim Konfirmasi Pembayaran</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Jalur Cepat via WhatsApp (1 Kolom) -->
                <div class="bg-gradient-to-br from-emerald-900 to-emerald-950 text-white rounded-3xl p-6 sm:p-7 shadow-sm flex flex-col justify-between space-y-6">
                    <div class="space-y-4">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-800 text-gold-400 flex items-center justify-center text-lg font-bold">
                            💬
                        </div>
                        <div>
                            <span class="text-[10px] font-black tracking-widest uppercase text-emerald-300 block mb-1">Metode 2: Jalur Cepat</span>
                            <h3 class="text-base font-extrabold text-white">Konfirmasi Langsung via WhatsApp</h3>
                            <p class="text-xs text-emerald-200/90 mt-1.5 leading-relaxed">
                                Lebih nyaman berkomunikasi via WhatsApp? Klik tombol di bawah untuk membuka chat resmi ke panitia dengan format pesan yang sudah otomatis disiapkan.
                            </p>
                        </div>

                        <div class="bg-white/10 backdrop-blur-xs p-3.5 rounded-2xl border border-white/15 text-xs space-y-1">
                            <div class="text-[10px] uppercase text-emerald-300 font-bold">Pusat Layanan & Bendahara:</div>
                            <div class="font-black text-white"><?= clean($panitiaPic) ?></div>
                            <div class="font-mono text-emerald-300"><?= clean($panitiaWa) ?></div>
                        </div>
                    </div>

                    <div>
                        <a href="<?= getWhatsAppConfirmUrl($groupData) ?>" target="_blank" class="w-full py-3.5 px-4 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-white font-black text-xs sm:text-sm shadow-lg flex items-center justify-center gap-2 transition text-center">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            <span>Chat WhatsApp Panitia</span>
                        </a>
                        <p class="text-[10px] text-center text-emerald-300 mt-2">
                            Pesan template otomatis terisi & tinggal lampirkan foto struk.
                        </p>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
