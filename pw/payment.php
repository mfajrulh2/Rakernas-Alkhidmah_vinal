<?php
/**
 * Halaman Pembayaran Iuran Delegasi - Akun PW
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin_pw');

$pdo    = getDB();
$user   = currentUser();
$pw_id  = $user['pw_id'];

// Pastikan folder upload tersedia
$uploadDir = __DIR__ . '/../uploads/payments/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Ambil info PW
$stmtPW = $pdo->prepare("SELECT *, name AS pw_name, code AS pw_code FROM wilayah WHERE id = ?");
$stmtPW->execute([$pw_id]);
$pwData = $stmtPW->fetch();

// Ambil info pembayaran
$payInfo = getPWPaymentInfo($pw_id);

// =============================================
// HANDLE FORM: Konfirmasi Pembayaran Baru
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'submit_payment') {
        // Validasi: harus ada peserta
        if ($payInfo['total_participants'] === 0) {
            setFlash('danger', 'Tidak ada delegasi terdaftar. Silakan daftarkan delegasi terlebih dahulu sebelum konfirmasi pembayaran.');
            header('Location: payment.php');
            exit;
        }
        // Validasi: pembayaran belum verified
        if ($payInfo['is_verified']) {
            setFlash('info', 'Pembayaran Anda sudah terverifikasi. Tidak perlu kirim ulang konfirmasi.');
            header('Location: payment.php');
            exit;
        }

        $bankName    = trim($_POST['bank_name'] ?? '');
        $senderName  = trim($_POST['sender_name'] ?? '');
        $payDate     = trim($_POST['payment_date'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');
        $feePerPerson = $payInfo['fee_per_person'];
        $totalPeserta = $payInfo['total_participants'];
        $totalAmount  = $feePerPerson * $totalPeserta;

        if (empty($bankName) || empty($senderName) || empty($payDate)) {
            setFlash('danger', 'Harap lengkapi semua field yang diperlukan (Bank, Nama Pengirim, Tanggal Transfer).');
            header('Location: payment.php');
            exit;
        }

        // Upload bukti transfer
        $proofFile = null;
        if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['proof_file']['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            if (!in_array($mimeType, $allowedMimes)) {
                setFlash('danger', 'Format file bukti transfer tidak didukung. Gunakan JPG, PNG, atau PDF.');
                header('Location: payment.php');
                exit;
            }

            $ext       = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
            $filename  = 'bukti_' . $pw_id . '_' . time() . '.' . strtolower($ext);
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $targetPath)) {
                $proofFile = 'uploads/payments/' . $filename;
            }
        }

        // Generate invoice code
        $invoiceCode = 'INV-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $pwData['code'])) . '-' . date('YmdHis');

        // Cek apakah sudah ada payment record untuk PW ini
        $stmtExist = $pdo->prepare("SELECT id FROM payments WHERE pw_id = ? AND status IN ('pending','rejected') ORDER BY id DESC LIMIT 1");
        $stmtExist->execute([$pw_id]);
        $existingPay = $stmtExist->fetch();

        if ($existingPay) {
            // Update existing (re-submit after rejection)
            $stmtUp = $pdo->prepare("
                UPDATE payments SET
                    invoice_code = ?, total_participants = ?, amount_per_person = ?, total_amount = ?,
                    bank_name = ?, sender_name = ?, payment_date = ?,
                    proof_file = COALESCE(?, proof_file),
                    status = 'pending', reject_reason = NULL, notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUp->execute([
                $invoiceCode, $totalPeserta, $feePerPerson, $totalAmount,
                $bankName, $senderName, $payDate,
                $proofFile, $notes, $existingPay['id']
            ]);
        } else {
            // Insert baru
            $stmtIns = $pdo->prepare("
                INSERT INTO payments
                    (invoice_code, pw_id, total_participants, amount_per_person, total_amount,
                     bank_name, sender_name, payment_date, proof_file, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmtIns->execute([
                $invoiceCode, $pw_id, $totalPeserta, $feePerPerson, $totalAmount,
                $bankName, $senderName, $payDate, $proofFile, $notes
            ]);
        }

        setFlash('success', 'Konfirmasi pembayaran berhasil dikirim! Panitia akan memverifikasi dalam waktu 1×24 jam. Silakan pantau status di halaman ini.');
        header('Location: payment.php');
        exit;
    }
}

// Refresh payInfo setelah POST
$payInfo = getPWPaymentInfo($pw_id);

// Ambil pengaturan rekening tujuan
$bankTujuan    = getSetting('payment_bank_name', 'Bank Syariah Indonesia (BSI)');
$noRek         = getSetting('payment_account_no', '-');
$namaRek       = getSetting('payment_account_name', 'Bendahara RAKERNAS Al Khidmah');
$instructions  = getSetting('payment_instructions', '');

$page_title = 'Iuran & Pembayaran';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Judul Halaman -->
    <div class="flex items-center gap-3">
        <div class="p-2 bg-amber-100 rounded-xl">
            <svg class="w-7 h-7 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Iuran & Pembayaran Delegasi</h1>
            <p class="text-sm text-slate-500"><?= clean($pwData['name'] ?? '') ?> — <?= clean($pwData['code'] ?? '') ?></p>
        </div>
    </div>

    <!-- Status Banner -->
    <?php
    $statusBanners = [
        'unpaid'   => ['bg-slate-50 border-slate-300', 'text-slate-700', 'Belum Ada Konfirmasi Pembayaran', 'Silakan lengkapi data delegasi, lalu lakukan transfer dan konfirmasi pembayaran di bawah ini.'],
        'pending'  => ['bg-amber-50 border-amber-400', 'text-amber-800', '⏳ Menunggu Verifikasi Panitia', 'Konfirmasi pembayaran Anda sudah diterima dan sedang diverifikasi oleh Panitia Pusat. Proses verifikasi 1×24 jam.'],
        'verified' => ['bg-emerald-50 border-emerald-400', 'text-emerald-800', '✅ Pembayaran Terverifikasi / LUNAS', 'Pembayaran iuran delegasi Anda telah diverifikasi. Anda sekarang dapat mencetak Bukti Pendaftaran / ID Card.'],
        'rejected' => ['bg-rose-50 border-rose-400', 'text-rose-800', '❌ Pembayaran Ditolak', 'Konfirmasi pembayaran Anda ditolak oleh panitia. Mohon periksa alasan penolakan dan kirim ulang konfirmasi.'],
    ];
    $bannerInfo = $statusBanners[$payInfo['status']] ?? $statusBanners['unpaid'];
    ?>
    <div class="p-5 rounded-2xl border-2 <?= $bannerInfo[0] ?>">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-bold text-lg <?= $bannerInfo[1] ?>"><?= $bannerInfo[2] ?></h2>
                <p class="text-sm mt-1 <?= $bannerInfo[1] ?> opacity-80"><?= $bannerInfo[3] ?></p>
                <?php if ($payInfo['status'] === 'rejected' && !empty($payInfo['payment']['reject_reason'])): ?>
                    <div class="mt-3 p-3 bg-rose-100 rounded-lg">
                        <p class="text-xs font-semibold text-rose-700">Alasan Penolakan:</p>
                        <p class="text-sm text-rose-800 mt-0.5"><?= clean($payInfo['payment']['reject_reason']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($payInfo['is_verified']): ?>
                <a href="<?= BASE_URL ?>pw/print_cards.php"
                   class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl shadow transition text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Cetak ID Card
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice / Tagihan -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-gradient-to-r from-alkhidmah-900 to-alkhidmah-700 text-white px-6 py-4 flex items-center gap-3">
            <svg class="w-5 h-5 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
            </svg>
            <h2 class="font-bold text-base">Rincian Tagihan Iuran</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
                <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200">
                    <div class="text-2xl font-extrabold text-alkhidmah-800"><?= $payInfo['total_participants'] ?></div>
                    <div class="text-xs text-slate-500 mt-1">Jumlah Delegasi</div>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200">
                    <div class="text-lg font-bold text-slate-700"><?= formatRupiah($payInfo['fee_per_person']) ?></div>
                    <div class="text-xs text-slate-500 mt-1">Iuran per Orang</div>
                </div>
                <div class="bg-amber-50 rounded-xl p-4 text-center border border-amber-200 md:col-span-2">
                    <div class="text-2xl font-extrabold text-amber-700"><?= formatRupiah($payInfo['expected_total']) ?></div>
                    <div class="text-xs text-amber-600 mt-1 font-medium">Total Tagihan Yang Harus Dibayar</div>
                </div>
            </div>

            <?php if ($payInfo['total_participants'] === 0): ?>
                <div class="p-4 bg-orange-50 border border-orange-200 rounded-xl text-sm text-orange-800">
                    ⚠️ Belum ada delegasi yang terdaftar. <a href="<?= BASE_URL ?>pw/register_bulk.php" class="font-bold underline">Daftarkan delegasi terlebih dahulu.</a>
                </div>
            <?php endif; ?>

            <!-- Info Rekening Tujuan -->
            <div class="mt-5 p-5 bg-alkhidmah-50 border border-alkhidmah-200 rounded-xl">
                <h3 class="font-bold text-alkhidmah-800 mb-3 flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Transfer ke Rekening Resmi Panitia
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <div>
                        <div class="text-xs text-alkhidmah-600 font-medium">Bank</div>
                        <div class="font-bold text-alkhidmah-900"><?= clean($bankTujuan) ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-alkhidmah-600 font-medium">Nomor Rekening</div>
                        <div class="font-extrabold text-alkhidmah-900 text-lg tracking-widest"><?= clean($noRek) ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-alkhidmah-600 font-medium">Atas Nama</div>
                        <div class="font-bold text-alkhidmah-900"><?= clean($namaRek) ?></div>
                    </div>
                </div>
                <?php if ($instructions): ?>
                    <div class="mt-3 pt-3 border-t border-alkhidmah-200 text-xs text-alkhidmah-700">
                        <?= nl2br(clean($instructions)) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Riwayat Pembayaran (jika ada) -->
    <?php if ($payInfo['payment']): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Riwayat Konfirmasi Pembayaran</h2>
            <?= renderPaymentBadge($payInfo['status']) ?>
        </div>
        <div class="p-6 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-xs text-slate-500 font-medium">Kode Invoice</div>
                <div class="font-mono font-bold text-slate-800"><?= clean($payInfo['payment']['invoice_code']) ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-500 font-medium">Tanggal Transfer</div>
                <div class="font-semibold text-slate-700"><?= $payInfo['payment']['payment_date'] ? formatTanggalIndo($payInfo['payment']['payment_date']) : '-' ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-500 font-medium">Bank Pengirim</div>
                <div class="font-semibold text-slate-700"><?= clean($payInfo['payment']['bank_name'] ?? '-') ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-500 font-medium">Nama Pengirim</div>
                <div class="font-semibold text-slate-700"><?= clean($payInfo['payment']['sender_name'] ?? '-') ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-500 font-medium">Total Transfer</div>
                <div class="font-bold text-emerald-700"><?= formatRupiah($payInfo['payment']['total_amount']) ?></div>
            </div>
            <?php if ($payInfo['payment']['proof_file']): ?>
            <div>
                <div class="text-xs text-slate-500 font-medium">Bukti Transfer</div>
                <?php $proofExt = strtolower(pathinfo($payInfo['payment']['proof_file'], PATHINFO_EXTENSION)); ?>
                <?php if (in_array($proofExt, ['jpg','jpeg','png','gif','webp'])): ?>
                    <a href="<?= BASE_URL . clean($payInfo['payment']['proof_file']) ?>" target="_blank">
                        <img src="<?= BASE_URL . clean($payInfo['payment']['proof_file']) ?>"
                             class="mt-1 h-20 w-auto rounded-lg border border-slate-200 hover:opacity-80 transition" alt="Bukti Transfer">
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL . clean($payInfo['payment']['proof_file']) ?>" target="_blank"
                       class="inline-flex items-center gap-1 text-blue-600 hover:underline font-medium mt-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Lihat PDF
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($payInfo['is_verified'] && $payInfo['payment']['verified_at']): ?>
            <div class="col-span-2 md:col-span-3 bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                <div class="text-xs text-emerald-700 font-medium">✅ Diverifikasi pada: <?= formatTanggalIndo($payInfo['payment']['verified_at'], true) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Konfirmasi Pembayaran -->
    <?php if (!$payInfo['is_verified'] && $payInfo['total_participants'] > 0): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-gradient-to-r from-amber-600 to-amber-500 text-white px-6 py-4 flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h2 class="font-bold text-base">
                <?= $payInfo['status'] === 'rejected' ? 'Kirim Ulang Konfirmasi Pembayaran' : 'Form Konfirmasi Pembayaran' ?>
            </h2>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            <input type="hidden" name="action" value="submit_payment">

            <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-800">
                💡 Pastikan sudah mentransfer sejumlah <strong><?= formatRupiah($payInfo['expected_total']) ?></strong>
                ke rekening <?= clean($bankTujuan) ?> No. <strong><?= clean($noRek) ?></strong> a.n. <?= clean($namaRek) ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Bank Pengirim <span class="text-rose-500">*</span></label>
                    <input type="text" name="bank_name" required placeholder="contoh: BRI, BNI, BSI, Mandiri"
                           value="<?= clean($payInfo['payment']['bank_name'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Pengirim (sesuai rekening) <span class="text-rose-500">*</span></label>
                    <input type="text" name="sender_name" required placeholder="Nama pemilik rekening pengirim"
                           value="<?= clean($payInfo['payment']['sender_name'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Transfer <span class="text-rose-500">*</span></label>
                    <input type="date" name="payment_date" required
                           value="<?= clean($payInfo['payment']['payment_date'] ?? date('Y-m-d')) ?>"
                           max="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Upload Bukti Transfer <span class="text-xs text-slate-400">(JPG/PNG/PDF, maks 5MB)</span></label>
                    <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                           class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-amber-100 file:text-amber-700 file:font-semibold hover:file:bg-amber-200 transition">
                    <?php if (!empty($payInfo['payment']['proof_file'])): ?>
                        <p class="text-xs text-slate-500 mt-1">Sudah ada bukti sebelumnya. Upload baru hanya jika ingin mengganti.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Catatan Tambahan <span class="text-xs text-slate-400">(opsional)</span></label>
                <textarea name="notes" rows="2" placeholder="Tambahkan catatan jika diperlukan..."
                          class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 resize-none"><?= clean($payInfo['payment']['notes'] ?? '') ?></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-2 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl shadow transition text-sm"
                        onclick="return confirm('Pastikan data yang Anda isi sudah benar. Kirim konfirmasi pembayaran?')">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Kirim Konfirmasi Pembayaran
                </button>
                <a href="<?= BASE_URL ?>pw/participants.php"
                   class="px-5 py-3 border border-slate-300 text-slate-700 font-semibold rounded-xl hover:bg-slate-50 transition text-sm">
                    Kembali
                </a>
            </div>
        </form>
    </div>
    <?php elseif ($payInfo['is_verified']): ?>
    <!-- Pembayaran sudah lunas, tampilkan tombol cetak -->
    <div class="bg-white rounded-2xl shadow-sm border border-emerald-200 p-6 text-center space-y-4">
        <div class="text-6xl">🎉</div>
        <h2 class="text-xl font-bold text-emerald-800">Pembayaran Sudah Terverifikasi!</h2>
        <p class="text-sm text-slate-600">Silakan cetak Bukti Pendaftaran / ID Card untuk seluruh delegasi <?= clean($pwData['name'] ?? '') ?>.</p>
        <a href="<?= BASE_URL ?>pw/print_cards.php"
           class="inline-flex items-center gap-2 px-8 py-3 bg-alkhidmah-800 hover:bg-alkhidmah-900 text-white font-bold rounded-xl shadow-lg transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Cetak Bukti Pendaftaran / ID Card
        </a>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
