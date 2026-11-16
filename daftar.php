<?php
/**
 * Portal Pendaftaran Delegasi Khusus PW (Tanpa Perlu Login)
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();
$token = trim($_GET['token'] ?? '');

$pw = getWilayahByRegToken($token);

// Jika token tidak valid, tampilkan halaman error yang ramah
if (!$pw) {
    $page_title = "Link Registrasi Tidak Valid";
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="max-w-xl mx-auto my-16 px-4">
        <div class="bg-white rounded-3xl border border-rose-200 shadow-xl p-8 text-center space-y-4">
            <div class="w-16 h-16 rounded-2xl bg-rose-50 text-rose-600 mx-auto flex items-center justify-center">
                <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900">Tautan Registrasi Tidak Ditemukan</h1>
            <p class="text-xs text-slate-600 leading-relaxed">
                Tautan registrasi khusus ini tidak valid, salah ketik, atau telah diperbarui oleh Panitia Pusat RAKERNAS.
            </p>
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-500">
                Silakan hubungi <strong>Panitia Pusat RAKERNAS Jama'ah Al Khidmah</strong> untuk mendapatkan tautan pendaftaran resmi untuk Pengurus Wilayah Anda.
            </div>
            <div class="pt-2">
                <a href="<?= BASE_URL ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-alkhidmah-800 text-white text-xs font-bold hover:bg-alkhidmah-900 transition shadow">
                    &larr; Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Ambil statistik kuota PW
$pw_id = (int)$pw['id'];
$stmtCount = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN participant_type = 'utusan' THEN 1 ELSE 0 END) as utusan_count,
        SUM(CASE WHEN participant_type = 'peninjau' THEN 1 ELSE 0 END) as peninjau_count
    FROM participants 
    WHERE pw_id = ?
");
$stmtCount->execute([$pw_id]);
$stats = $stmtCount->fetch();

$totalTerdaftar = (int)$stats['total'];
$utusanCount    = (int)$stats['utusan_count'];
$peninjauCount  = (int)$stats['peninjau_count'];

$quotaUtusan   = (int)$pw['quota_utusan'];
$quotaPeninjau = (int)$pw['quota_peninjau'];

$sisaUtusan   = max(0, $quotaUtusan - $utusanCount);
$sisaPeninjau = max(0, $quotaPeninjau - $peninjauCount);

$unsurOptions  = getUnsurOrganisasiOptions();
$komisiOptions = getKomisiOptions();

// Info Pembayaran PW
$payInfo = getPWPaymentInfo($pw_id);

// Ambil pengaturan rekening tujuan dari settings dengan fallback
$bankTujuan   = getSetting('payment_bank_name', 'Bank Rakyat Indonesia (BRI)');
$noRek        = getSetting('payment_account_no', '228301019405508');
$namaRek      = getSetting('payment_account_name', 'PW AL KHIDMAH JATENG');
$instructions = getSetting('payment_instructions', 'Pastikan sudah mentransfer sesuai total tagihan ke rekening panitia. Cantumkan berita transfer: IURAN ' . clean($pw['code']) . '. Unggah bukti pembayaran melalui formulir di bawah ini.');

$errors = [];
$successMessage = '';

// =============================================
// HANDLE FORM SUBMISSIONS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // 1. Pendaftaran Delegasi Baru
    if ($_POST['action'] === 'register_delegates') {
        $names   = $_POST['full_name'] ?? [];
        $genders = $_POST['gender'] ?? [];
        $unsurs  = $_POST['unsur_organisasi'] ?? [];
        $pds     = $_POST['pd_name'] ?? [];
        $jabs    = $_POST['jabatan_organisasi'] ?? [];
        $komisis = $_POST['komisi'] ?? [];
        $phones  = $_POST['phone'] ?? [];
        $types   = $_POST['participant_type'] ?? [];

        $count = count($names);
        if ($count === 0) {
            $errors[] = "Minimal daftarkan 1 orang delegasi.";
        }

        // Validasi kuota tambahan
        $newUtusan = 0;
        $newPeninjau = 0;
        for ($i = 0; $i < $count; $i++) {
            $t = strtolower(trim($types[$i] ?? 'utusan'));
            if ($t === 'peninjau') {
                $newPeninjau++;
            } else {
                $newUtusan++;
            }
        }

        if ($newUtusan > $sisaUtusan) {
            $errors[] = "Jumlah Peserta Utusan yang didaftarkan ({$newUtusan}) melebihi sisa kuota yang tersedia ({$sisaUtusan}).";
        }
        if ($newPeninjau > $sisaPeninjau) {
            $errors[] = "Jumlah Peserta Peninjau yang didaftarkan ({$newPeninjau}) melebihi sisa kuota yang tersedia ({$sisaPeninjau}).";
        }

        // Validasi field & file foto
        $uploadedPhotos = [];
        if (empty($errors)) {
            for ($i = 0; $i < $count; $i++) {
                $idx = $i + 1;
                $nama = trim($names[$i] ?? '');
                $wa   = trim($phones[$i] ?? '');

                if (empty($nama)) {
                    $errors[] = "Delegasi ke-{$idx}: Nama lengkap wajib diisi.";
                }
                if (empty($wa)) {
                    $errors[] = "Delegasi ke-{$idx}: Nomor WhatsApp wajib diisi.";
                }

                // Upload foto jika ada
                $photoPath = null;
                if (isset($_FILES['photo_file']['name'][$i]) && !empty($_FILES['photo_file']['name'][$i])) {
                    $uploadRes = uploadParticipantPhoto($_FILES['photo_file'], $i);
                    if ($uploadRes['error']) {
                        $errors[] = "Delegasi ke-{$idx}: " . $uploadRes['error'];
                    } else {
                        $photoPath = $uploadRes['path'];
                    }
                }
                $uploadedPhotos[$i] = $photoPath;
            }
        }

        // Eksekusi insert jika tidak ada error
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $registeredCount = 0;
                for ($i = 0; $i < $count; $i++) {
                    $nama    = trim($names[$i]);
                    $gender  = in_array($genders[$i] ?? '', ['L', 'P']) ? $genders[$i] : 'L';
                    $unsur   = trim($unsurs[$i] ?? 'Al Khidmah');
                    $pd      = trim($pds[$i] ?? '');
                    $jabatan = trim($jabs[$i] ?? '');
                    $komisi  = trim($komisis[$i] ?? $komisiOptions[0]);
                    $phone   = trim($phones[$i]);
                    $type    = strtolower(trim($types[$i] ?? 'utusan')) === 'peninjau' ? 'peninjau' : 'utusan';
                    $photo   = $uploadedPhotos[$i] ?? null;

                    $regCode = generateRegistrationCode($pw['code']);
                    // Cek keunikan kode
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM participants WHERE registration_code = ?");
                    $stmtCheck->execute([$regCode]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        $regCode = generateRegistrationCode($pw['code']) . 'A';
                    }

                    $stmtIns = $pdo->prepare("
                        INSERT INTO participants 
                        (registration_code, full_name, gender, unsur_organisasi, phone, photo_file, pw_id, pd_name, jabatan_organisasi, participant_type, komisi, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([
                        $regCode, $nama, $gender, $unsur, $phone, $photo, $pw_id, $pd, $jabatan, $type, $komisi, 'Daftar Mandiri via Link Khusus PW'
                    ]);

                    $registeredCount++;
                }

                $pdo->commit();
                setFlash('success', "Alhamdulillah, berhasil mendaftarkan {$registeredCount} orang delegasi untuk {$pw['name']}!");
                header("Location: " . BASE_URL . "daftar.php?token=" . urlencode($token) . "&tab=list");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Gagal menyimpan data delegasi: " . $e->getMessage();
            }
        }
    }

    // 2. Konfirmasi Pembayaran Iuran
    elseif ($_POST['action'] === 'confirm_payment') {
        if ($totalTerdaftar === 0) {
            $errors[] = "Belum ada delegasi yang didaftarkan. Silakan daftarkan delegasi terlebih dahulu sebelum melakukan konfirmasi pembayaran.";
        } elseif ($payInfo['is_verified']) {
            $errors[] = "Pembayaran iuran untuk wilayah Anda sudah terverifikasi dan berstatus LUNAS.";
        } else {
            $bankName   = trim($_POST['bank_name'] ?? '');
            $senderName = trim($_POST['sender_name'] ?? '');
            $payDate    = trim($_POST['payment_date'] ?? '');
            $notes      = trim($_POST['notes'] ?? '');

            if (empty($bankName) || empty($senderName) || empty($payDate)) {
                $errors[] = "Harap lengkapi semua isian wajib: Bank Pengirim, Nama Pengirim, dan Tanggal Transfer.";
            }

            // Upload bukti transfer
            $uploadDir = __DIR__ . '/uploads/payments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $proofFile = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $size = $_FILES['proof_file']['size'];
                if ($size > 5 * 1024 * 1024) {
                    $errors[] = "Ukuran file bukti transfer melebihi 5 MB.";
                } else {
                    $ext = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                    if (!in_array($ext, $allowedExts)) {
                        $errors[] = "Format file bukti transfer tidak didukung. Gunakan file JPG, PNG, atau PDF.";
                    } else {
                        $filename = 'bukti_' . $pw_id . '_' . time() . '.' . $ext;
                        $targetPath = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $targetPath)) {
                            $proofFile = 'uploads/payments/' . $filename;
                        } else {
                            $errors[] = "Gagal menyimpan file bukti transfer ke server.";
                        }
                    }
                }
            }

            // Validasi keberadaan bukti pembayaran
            $stmtExist = $pdo->prepare("SELECT id, proof_file FROM payments WHERE pw_id = ? AND status IN ('pending','rejected','unpaid') ORDER BY id DESC LIMIT 1");
            $stmtExist->execute([$pw_id]);
            $existingPay = $stmtExist->fetch();

            if (!$proofFile && (!$existingPay || empty($existingPay['proof_file']))) {
                $errors[] = "File bukti transfer pembayaran wajib diunggah.";
            }

            if (empty($errors)) {
                try {
                    $feePerPerson = (float)getSetting('fee_per_participant', '300000');
                    $totalAmount  = $feePerPerson * $totalTerdaftar;
                    $cleanPwCode  = preg_replace('/[^A-Za-z0-9]/', '', $pw['code']);
                    $invoiceCode  = 'INV-' . strtoupper($cleanPwCode) . '-' . date('YmdHis');

                    if ($existingPay) {
                        $stmtUp = $pdo->prepare("
                            UPDATE payments SET
                                invoice_code = ?,
                                total_participants = ?,
                                amount_per_person = ?,
                                total_amount = ?,
                                bank_name = ?,
                                sender_name = ?,
                                payment_date = ?,
                                proof_file = COALESCE(?, proof_file),
                                status = 'pending',
                                reject_reason = NULL,
                                notes = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmtUp->execute([
                            $invoiceCode, $totalTerdaftar, $feePerPerson, $totalAmount,
                            $bankName, $senderName, $payDate,
                            $proofFile, $notes, $existingPay['id']
                        ]);
                    } else {
                        $stmtIns = $pdo->prepare("
                            INSERT INTO payments
                                (invoice_code, pw_id, total_participants, amount_per_person, total_amount,
                                 bank_name, sender_name, payment_date, proof_file, status, notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                        ");
                        $stmtIns->execute([
                            $invoiceCode, $pw_id, $totalTerdaftar, $feePerPerson, $totalAmount,
                            $bankName, $senderName, $payDate, $proofFile, $notes
                        ]);
                    }

                    setFlash('success', 'Alhamdulillah, konfirmasi pembayaran berhasil dikirim! Panitia Pusat akan segera memverifikasi bukti transfer Anda.');
                    header("Location: " . BASE_URL . "daftar.php?token=" . urlencode($token) . "&tab=payment");
                    exit;
                } catch (Exception $e) {
                    $errors[] = "Gagal memproses konfirmasi pembayaran: " . $e->getMessage();
                }
            }
        }
    }
}

// Refresh payInfo setelah update
$payInfo = getPWPaymentInfo($pw_id);

// Ambil seluruh delegasi terdaftar untuk PW ini
$stmtAll = $pdo->prepare("
    SELECT * FROM participants 
    WHERE pw_id = ? 
    ORDER BY id DESC
");
$stmtAll->execute([$pw_id]);
$allDelegates = $stmtAll->fetchAll();

$activeTab = $_GET['tab'] ?? 'form';
if (!in_array($activeTab, ['form', 'list', 'payment'])) {
    $activeTab = 'form';
}

$page_title = "Pendaftaran Delegasi - " . clean($pw['name']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto mb-16 space-y-6">

    <!-- Header Kartu PW -->
    <div class="bg-gradient-to-r from-alkhidmah-950 via-alkhidmah-900 to-emerald-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
        <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-xs font-bold mb-3 tracking-wide">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Link Resmi Pendaftaran Delegasi
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white leading-tight">
                    <?= clean($pw['name']) ?>
                </h1>
                <p class="text-xs text-emerald-200/80 mt-1 font-mono">
                    Kode PW: <strong class="text-white"><?= clean($pw['code']) ?></strong> • Wilayah: <?= clean($pw['province']) ?>
                </p>
            </div>

            <!-- Kuota Badges & Status Iuran -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="bg-white/10 backdrop-blur border border-white/10 rounded-2xl p-3.5 text-center min-w-[95px]">
                    <div class="text-[10px] text-emerald-200 uppercase font-bold tracking-wider">Utusan</div>
                    <div class="text-xl font-extrabold text-white mt-0.5 font-mono">
                        <?= $utusanCount ?> <span class="text-xs text-emerald-300 font-normal">/ <?= $quotaUtusan ?></span>
                    </div>
                    <div class="text-[9px] <?= $sisaUtusan > 0 ? 'text-emerald-300' : 'text-rose-300' ?> mt-0.5 font-semibold">
                        <?= $sisaUtusan > 0 ? "Sisa {$sisaUtusan}" : 'Penuh' ?>
                    </div>
                </div>

                <div class="bg-white/10 backdrop-blur border border-white/10 rounded-2xl p-3.5 text-center min-w-[95px]">
                    <div class="text-[10px] text-amber-200 uppercase font-bold tracking-wider">Peninjau</div>
                    <div class="text-xl font-extrabold text-amber-300 mt-0.5 font-mono">
                        <?= $peninjauCount ?> <span class="text-xs text-amber-200 font-normal">/ <?= $quotaPeninjau ?></span>
                    </div>
                    <div class="text-[9px] <?= $sisaPeninjau > 0 ? 'text-amber-200' : 'text-rose-300' ?> mt-0.5 font-semibold">
                        <?= $sisaPeninjau > 0 ? "Sisa {$sisaPeninjau}" : 'Penuh' ?>
                    </div>
                </div>

                <div class="bg-white/10 backdrop-blur border border-white/10 rounded-2xl p-3.5 text-center min-w-[110px]">
                    <div class="text-[10px] text-slate-200 uppercase font-bold tracking-wider">Status Iuran</div>
                    <div class="mt-1">
                        <?php if ($payInfo['is_verified']): ?>
                            <span class="inline-block px-2 py-0.5 rounded-lg bg-emerald-500 text-white text-[11px] font-black">LUNAS</span>
                        <?php elseif ($payInfo['status'] === 'pending'): ?>
                            <span class="inline-block px-2 py-0.5 rounded-lg bg-amber-500 text-white text-[11px] font-bold">Verifikasi</span>
                        <?php elseif ($payInfo['status'] === 'rejected'): ?>
                            <span class="inline-block px-2 py-0.5 rounded-lg bg-rose-500 text-white text-[11px] font-bold">Ditolak</span>
                        <?php else: ?>
                            <span class="inline-block px-2 py-0.5 rounded-lg bg-white/20 text-white text-[11px] font-medium">Belum Bayar</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigasi 3 Menu -->
    <div class="flex flex-wrap items-center justify-between border-b border-slate-200 pb-2 gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <!-- Tab 1: Formulir Pendaftaran -->
            <a href="?token=<?= urlencode($token) ?>&tab=form" class="px-4 sm:px-5 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 <?= $activeTab === 'form' ? 'bg-alkhidmah-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Formulir Pendaftaran</span>
            </a>

            <!-- Tab 2: Daftar Delegasi Terdaftar -->
            <a href="?token=<?= urlencode($token) ?>&tab=list" class="px-4 sm:px-5 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 <?= $activeTab === 'list' ? 'bg-alkhidmah-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Daftar Delegasi (<?= count($allDelegates) ?>)</span>
            </a>

            <!-- Tab 3: Konfirmasi Pembayaran -->
            <a href="?token=<?= urlencode($token) ?>&tab=payment" class="px-4 sm:px-5 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2 <?= $activeTab === 'payment' ? 'bg-alkhidmah-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                <span>Konfirmasi Pembayaran</span>
                <?php if ($payInfo['is_verified']): ?>
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <?php elseif ($payInfo['status'] === 'pending'): ?>
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Error Alert Banner -->
    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
            <div class="font-bold flex items-center gap-1.5 mb-1">
                <svg class="w-4 h-4 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                Perhatian:
            </div>
            <?php foreach ($errors as $err): ?>
                <div>• <?= clean($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 1: FORMULIR PENDAFTARAN -->
    <?php if ($activeTab === 'form'): ?>
        <?php if ($sisaUtusan <= 0 && $sisaPeninjau <= 0): ?>
            <div class="bg-amber-50 rounded-3xl border border-amber-300 p-8 text-center space-y-3">
                <div class="w-12 h-12 bg-amber-100 text-amber-700 rounded-2xl flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-base font-bold text-amber-900">Seluruh Kuota Delegasi Telah Terpenuhi</h3>
                <p class="text-xs text-amber-800 max-w-md mx-auto">
                    Pengurus Wilayah <?= clean($pw['name']) ?> telah mendaftarkan seluruh kuota peserta utusan (<?= $quotaUtusan ?>) dan peninjau (<?= $quotaPeninjau ?>).
                </p>
                <div class="pt-2 flex items-center justify-center gap-3">
                    <a href="?token=<?= urlencode($token) ?>&tab=list" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-700 text-white font-bold text-xs hover:bg-amber-800 transition shadow">
                        Lihat Data Delegasi &rarr;
                    </a>
                    <a href="?token=<?= urlencode($token) ?>&tab=payment" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-700 text-white font-bold text-xs hover:bg-emerald-800 transition shadow">
                        Konfirmasi Pembayaran &rarr;
                    </a>
                </div>
            </div>
        <?php else: ?>
            <form action="?token=<?= urlencode($token) ?>" method="POST" enctype="multipart/form-data" id="publicRegisterForm" class="space-y-6">
                <input type="hidden" name="action" value="register_delegates">

                <div id="formsContainer" class="space-y-6">
                    <!-- Template Card Delegasi 1 -->
                    <div class="delegate-card bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 relative" data-index="0">
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                            <div class="flex items-center gap-2.5">
                                <span class="card-number w-7 h-7 rounded-full bg-alkhidmah-800 text-white flex items-center justify-center text-xs font-black font-mono">1</span>
                                <h3 class="text-sm font-extrabold text-slate-800">Form Delegasi #1</h3>
                            </div>
                            <span class="btn-remove-card hidden text-xs text-rose-500 hover:text-rose-700 font-bold cursor-pointer transition" onclick="removeCard(this)">
                                &times; Hapus Formulir Ini
                            </span>
                        </div>

                        <!-- 8 Field Sesuai Urutan Resmi -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- 1. Nama Lengkap -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">1. Nama Lengkap & Gelar *</label>
                                <input type="text" name="full_name[]" required placeholder="Contoh: Drs. H. Achmad Fauzi, M.Pd" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                            </div>

                            <!-- 2. Jenis Kelamin -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">2. Jenis Kelamin *</label>
                                <select name="gender[]" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                                    <option value="L">Laki-laki (L)</option>
                                    <option value="P">Perempuan (P)</option>
                                </select>
                            </div>

                            <!-- 3. Unsur Organisasi -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">3. Unsur Organisasi *</label>
                                <select name="unsur_organisasi[]" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold text-emerald-800">
                                    <?php foreach ($unsurOptions as $uo): ?>
                                        <option value="<?= clean($uo) ?>"><?= clean($uo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 4. Pengurus Daerah -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">4. Pengurus Daerah</label>
                                <input type="text" name="pd_name[]" placeholder="Contoh: PD Al Khidmah Surabaya" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                            </div>

                            <!-- 5. Jabatan / Bidang -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">5. Jabatan / Bidang</label>
                                <input type="text" name="jabatan_organisasi[]" placeholder="Contoh: Ketua / Sekretaris / Anggota" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                            </div>

                            <!-- 6. Komisi Sidang -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">6. Komisi Sidang *</label>
                                <select name="komisi[]" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold text-slate-800">
                                    <?php foreach ($komisiOptions as $ko): ?>
                                        <option value="<?= clean($ko) ?>"><?= clean($ko) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Tipe Delegasi (Utusan / Peninjau) -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Klasifikasi Peserta *</label>
                                <select name="participant_type[]" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold">
                                    <option value="utusan" <?= $sisaUtusan <= 0 ? 'disabled' : '' ?>>Utusan <?= $sisaUtusan <= 0 ? '(Penuh)' : '' ?></option>
                                    <option value="peninjau" <?= $sisaPeninjau <= 0 ? 'disabled' : '' ?>>Peninjau <?= $sisaPeninjau <= 0 ? '(Penuh)' : '' ?></option>
                                </select>
                            </div>

                            <!-- 7. Nomor WA -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">7. Nomor WhatsApp *</label>
                                <input type="text" name="phone[]" required placeholder="Contoh: 081234567890" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                            </div>

                            <!-- 8. Upload Foto (Maks 5 MB) -->
                            <div class="sm:col-span-2 lg:col-span-3 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">8. Upload Foto Delegasi</label>
                                <input type="file" name="photo_file[]" accept=".jpg,.jpeg,.png,.webp" onchange="validatePhotoSize(this)" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-white file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-semibold hover:file:bg-emerald-100 transition">
                                <p class="text-[11px] text-amber-700 mt-1.5 font-medium flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-amber-500 inline shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                    upload file foto dengan mengenakan baju dan kopiah putih - ukuran maks 5 Mb
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Tambah Form Delegasi Lainnya & Submit -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2">
                    <button type="button" onclick="addDelegateCard()" class="px-5 py-3 rounded-2xl bg-white border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>+ Tambah Form Delegasi Lainnya</span>
                    </button>

                    <button type="submit" class="px-8 py-3.5 rounded-2xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-lg flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Kirim & Simpan Data Delegasi</span>
                    </button>
                </div>
            </form>
        <?php endif; ?>

    <!-- TAB 2: DAFTAR DELEGASI TERDAFTAR -->
    <?php elseif ($activeTab === 'list'): ?>
        
        <div class="space-y-4">
            <!-- Informasi Dokumen Bukti Pendaftaran -->
            <div class="bg-emerald-50/90 border border-emerald-200 rounded-3xl p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-extrabold text-emerald-950">Dokumen Bukti Pendaftaran Resmi Delegasi</h4>
                        <p class="text-[11px] sm:text-xs text-emerald-800 mt-0.5 leading-relaxed">
                            Cetak lembar bukti pendaftaran ber-QR Code di bawah ini untuk dibawa dan ditunjukkan kepada petugas saat tiba di lokasi Rakernas untuk proses registrasi kedatangan dan penerimaan ID Card fisik.
                        </p>
                    </div>
                </div>

                <?php if (!empty($allDelegates)): ?>
                    <a href="print_proof.php?token=<?= urlencode($token) ?>&all=1" target="_blank" class="shrink-0 px-5 py-2.5 rounded-xl bg-emerald-700 text-white text-xs font-bold hover:bg-emerald-800 transition shadow flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Cetak Semua Bukti (A4)</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Tabel Delegasi Terdaftar -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
                <div class="p-5 px-6 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Total <strong><?= count($allDelegates) ?></strong> delegasi terdaftar untuk <?= clean($pw['name']) ?></span>
                    <a href="?token=<?= urlencode($token) ?>&tab=form" class="font-bold text-emerald-700 hover:text-emerald-900 flex items-center gap-1">
                        + Tambah Delegasi Baru
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider font-semibold border-b border-slate-100">
                            <tr>
                                <th class="py-3 px-4">No</th>
                                <th class="py-3 px-4">Kode & Klasifikasi</th>
                                <th class="py-3 px-4">Foto & Nama Lengkap</th>
                                <th class="py-3 px-4">Pengurus Daerah & Jabatan</th>
                                <th class="py-3 px-4">Komisi Sidang</th>
                                <th class="py-3 px-4">No. WhatsApp</th>
                                <th class="py-3 px-6 text-right">Bukti Pendaftaran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($allDelegates)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-12 text-slate-400">Belum ada delegasi yang didaftarkan untuk wilayah ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($allDelegates as $del): ?>
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-3 px-4 font-mono text-slate-400"><?= $no++ ?></td>
                                        
                                        <td class="py-3 px-4">
                                            <div class="font-mono font-bold text-slate-800 text-[11px]"><?= clean($del['registration_code']) ?></div>
                                            <div class="mt-0.5"><?= renderParticipantBadge($del['participant_type']) ?></div>
                                        </td>

                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-3">
                                                <?php if (!empty($del['photo_file'])): ?>
                                                    <img src="<?= BASE_URL . clean($del['photo_file']) ?>" alt="Foto" class="w-9 h-11 object-cover rounded-lg border border-slate-200 shrink-0">
                                                <?php else: ?>
                                                    <div class="w-9 h-11 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center text-xs font-bold shrink-0">
                                                        <?= strtoupper(substr($del['full_name'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="font-bold text-slate-900 text-sm"><?= clean($del['full_name']) ?></div>
                                                    <div class="flex items-center gap-1.5 mt-0.5">
                                                        <span class="text-[10px] font-bold px-1.5 py-0.2 rounded <?= $del['gender'] === 'L' ? 'bg-sky-50 text-sky-700' : 'bg-pink-50 text-pink-700' ?>">
                                                            <?= $del['gender'] === 'L' ? 'L' : 'P' ?>
                                                        </span>
                                                        <?= renderUnsurBadge($del['unsur_organisasi'] ?? 'Al Khidmah') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="py-3 px-4">
                                            <div class="font-semibold text-slate-800"><?= clean($del['pd_name'] ?: '-') ?></div>
                                            <div class="text-[10px] text-slate-500"><?= clean($del['jabatan_organisasi'] ?: '-') ?></div>
                                        </td>

                                        <td class="py-3 px-4">
                                            <span class="inline-block px-2 py-0.5 rounded bg-slate-50 border border-slate-200 font-semibold text-[11px] text-slate-700">
                                                <?= clean($del['komisi']) ?>
                                            </span>
                                        </td>

                                        <td class="py-3 px-4 font-mono text-slate-600">
                                            <?= clean($del['phone']) ?>
                                        </td>

                                        <td class="py-3 px-6 text-right whitespace-nowrap">
                                            <a href="print_proof.php?code=<?= urlencode($del['registration_code']) ?>&token=<?= urlencode($token) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 text-xs font-bold hover:bg-emerald-100 transition shadow-sm">
                                                <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span>Cetak Bukti Pendaftaran</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAB 3: KONFIRMASI PEMBAYARAN IURAN -->
    <?php elseif ($activeTab === 'payment'): ?>
        
        <div class="space-y-6">

            <!-- Ringkasan Tagihan & Rekening Pembayaran -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                
                <!-- Kartu Rincian Tagihan -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-7 flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Rincian Tagihan Iuran</span>
                            <?= renderPaymentBadge($payInfo['status']) ?>
                        </div>
                        <h3 class="text-xl font-black text-slate-900 mt-2">
                            Iuran Kontribusi Delegasi
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Total kewajiban kontribusi iuran delegasi RAKERNAS 2026.
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2 text-xs">
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Jumlah Delegasi Terdaftar:</span>
                            <span class="font-bold text-slate-900 font-mono"><?= $totalTerdaftar ?> Orang</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Biaya Iuran per Delegasi:</span>
                            <span class="font-bold text-slate-900 font-mono"><?= formatRupiah($payInfo['fee_per_person']) ?></span>
                        </div>
                        <div class="border-t border-slate-200 pt-2 flex justify-between items-center">
                            <span class="font-extrabold text-slate-900">Total Tagihan:</span>
                            <span class="font-black text-emerald-800 text-base sm:text-lg font-mono">
                                <?= formatRupiah($payInfo['expected_total']) ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($totalTerdaftar === 0): ?>
                        <div class="text-[11px] text-amber-700 bg-amber-50 p-3 rounded-xl border border-amber-200">
                            Belum ada delegasi yang didaftarkan. Daftarkan delegasi terlebih dahulu sebelum transfer.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Kartu Rekening Tujuan Transfer -->
                <div class="bg-gradient-to-br from-emerald-950 via-emerald-900 to-alkhidmah-900 text-white rounded-3xl p-6 sm:p-7 shadow-lg flex flex-col justify-between space-y-4 relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

                    <div class="relative z-10">
                        <div class="text-[10px] text-amber-300 uppercase tracking-widest font-extrabold">Rekening Resmi Pembayaran</div>
                        <h3 class="text-lg font-black text-white mt-1"><?= clean($bankTujuan) ?></h3>
                        <p class="text-xs text-emerald-200/80 mt-0.5">Atas Nama: <strong class="text-white"><?= clean($namaRek) ?></strong></p>
                    </div>

                    <div class="relative z-10 bg-white/10 backdrop-blur rounded-2xl p-4 border border-white/10 space-y-2">
                        <div class="text-[10px] text-emerald-200 uppercase font-semibold">Nomor Rekening:</div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xl sm:text-2xl font-black font-mono tracking-wider text-amber-300" id="rekNumber">
                                <?= clean($noRek) ?>
                            </span>
                            <button type="button" onclick="copyRekening()" class="px-3 py-1.5 rounded-xl bg-white/20 hover:bg-white/30 text-white text-[11px] font-bold transition flex items-center gap-1.5 shrink-0" title="Salin nomor rekening">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <span id="copyBtnText">Salin</span>
                            </button>
                        </div>
                    </div>

                    <p class="relative z-10 text-[11px] text-emerald-100/80 leading-relaxed">
                        Berita Transfer: <code class="bg-black/30 px-2 py-0.5 rounded text-amber-200 font-mono">IURAN <?= clean($pw['code']) ?></code>
                    </p>
                </div>

            </div>

            <!-- Status Pembayaran Saat Ini -->
            <?php if ($payInfo['is_verified']): ?>
                <!-- STATUS: LUNAS -->
                <div class="bg-emerald-50 rounded-3xl border border-emerald-300 p-6 sm:p-8 space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                            <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-base font-extrabold text-emerald-950">Pembayaran Terverifikasi & LUNAS</h3>
                            <p class="text-xs text-emerald-800 leading-relaxed">
                                Alhamdulillah, iuran kontribusi delegasi <?= clean($pw['name']) ?> telah diverifikasi oleh Bendahara Panitia Pusat RAKERNAS.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-white p-4 rounded-2xl border border-emerald-200 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Kode Invoice</span>
                            <span class="font-mono font-bold text-slate-800"><?= clean($payInfo['payment']['invoice_code']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Bank Pengirim</span>
                            <span class="font-bold text-slate-800"><?= clean($payInfo['payment']['bank_name']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Nama Pengirim</span>
                            <span class="font-bold text-slate-800"><?= clean($payInfo['payment']['sender_name']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Tanggal Transfer</span>
                            <span class="font-bold text-slate-800"><?= formatTanggalIndo($payInfo['payment']['payment_date']) ?></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <?php if (!empty($payInfo['payment']['proof_file'])): ?>
                            <a href="<?= BASE_URL . clean($payInfo['payment']['proof_file']) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white border border-emerald-300 text-emerald-800 font-bold text-xs hover:bg-emerald-100 transition shadow-sm">
                                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>Lihat Bukti Transfer</span>
                            </a>
                        <?php endif; ?>
                        <a href="?token=<?= urlencode($token) ?>&tab=list" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-700 text-white font-bold text-xs hover:bg-emerald-800 transition shadow">
                            <span>Cetak Bukti Pendaftaran Delegasi &rarr;</span>
                        </a>
                    </div>
                </div>

            <?php elseif ($payInfo['status'] === 'pending'): ?>
                <!-- STATUS: MENUNGGU VERIFIKASI -->
                <div class="bg-amber-50 rounded-3xl border border-amber-300 p-6 sm:p-8 space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center shrink-0">
                            <svg class="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-base font-extrabold text-amber-950">Konfirmasi Pembayaran Sedang Diverifikasi</h3>
                            <p class="text-xs text-amber-800 leading-relaxed">
                                Bukti pembayaran telah diterima dan sedang menunggu pemeriksaan oleh Bendahara Panitia Pusat RAKERNAS (1×24 jam). Anda tetap dapat memperbarui bukti jika ada kesalahan transfer di bawah ini.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-white p-4 rounded-2xl border border-amber-200 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Kode Invoice</span>
                            <span class="font-mono font-bold text-slate-800"><?= clean($payInfo['payment']['invoice_code']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Bank Pengirim</span>
                            <span class="font-bold text-slate-800"><?= clean($payInfo['payment']['bank_name']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Nama Pengirim</span>
                            <span class="font-bold text-slate-800"><?= clean($payInfo['payment']['sender_name']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Tanggal Transfer</span>
                            <span class="font-bold text-slate-800"><?= formatTanggalIndo($payInfo['payment']['payment_date']) ?></span>
                        </div>
                    </div>

                    <?php if (!empty($payInfo['payment']['proof_file'])): ?>
                        <div class="pt-1">
                            <a href="<?= BASE_URL . clean($payInfo['payment']['proof_file']) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white border border-amber-300 text-amber-800 font-bold text-xs hover:bg-amber-100 transition shadow-sm">
                                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>Lihat File Bukti yang Dikirim</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($payInfo['status'] === 'rejected'): ?>
                <!-- STATUS: DITOLAK -->
                <div class="bg-rose-50 rounded-3xl border border-rose-300 p-6 sm:p-8 space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-rose-600 text-white flex items-center justify-center shrink-0">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-base font-extrabold text-rose-950">Konfirmasi Pembayaran Ditolak Panitia</h3>
                            <p class="text-xs text-rose-800 leading-relaxed">
                                Mohon maaf, konfirmasi pembayaran Anda tidak dapat diverifikasi oleh panitia.
                            </p>
                            <?php if (!empty($payInfo['payment']['reject_reason'])): ?>
                                <div class="p-3 bg-white/80 rounded-xl border border-rose-200 text-xs font-semibold text-rose-900 mt-2">
                                    Alasan: <?= clean($payInfo['payment']['reject_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- FORMULIR KONFIRMASI PEMBAYARAN -->
            <?php if (!$payInfo['is_verified']): ?>
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 space-y-6">
                    <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                        <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-extrabold text-slate-800">
                                <?= $payInfo['status'] === 'pending' ? 'Perbarui Konfirmasi Pembayaran' : 'Formulir Konfirmasi Pembayaran' ?>
                            </h3>
                            <p class="text-xs text-slate-500">
                                Isi formulir di bawah ini setelah melakukan transfer sesuai total tagihan.
                            </p>
                        </div>
                    </div>

                    <form action="?token=<?= urlencode($token) ?>&tab=payment" method="POST" enctype="multipart/form-data" class="space-y-5">
                        <input type="hidden" name="action" value="confirm_payment">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Bank Pengirim -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Bank Pengirim *</label>
                                <input type="text" name="bank_name" required placeholder="Contoh: BRI, BNI, BSI, Mandiri, BCA" value="<?= clean($payInfo['payment']['bank_name'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                            </div>

                            <!-- Nama Pemilik Rekening -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Pengirim (Sesuai Rekening) *</label>
                                <input type="text" name="sender_name" required placeholder="Nama pemilik rekening pengirim" value="<?= clean($payInfo['payment']['sender_name'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                            </div>

                            <!-- Tanggal Transfer -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tanggal Transfer *</label>
                                <input type="date" name="payment_date" required max="<?= date('Y-m-d') ?>" value="<?= clean($payInfo['payment']['payment_date'] ?? date('Y-m-d')) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                            </div>

                            <!-- File Bukti Transfer -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Upload Bukti Transfer <?= !empty($payInfo['payment']['proof_file']) ? '(Opsional jika tidak ganti)' : '*' ?></label>
                                <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" <?= empty($payInfo['payment']['proof_file']) ? 'required' : '' ?> class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-white file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-amber-100 file:text-amber-800 file:font-semibold hover:file:bg-amber-200 transition">
                                <p class="text-[11px] text-slate-500 mt-1">Format: JPG, PNG, atau PDF (maksimal 5 MB)</p>
                            </div>
                        </div>

                        <!-- Catatan Tambahan -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Catatan Tambahan (Opsional)</label>
                            <textarea name="notes" rows="2" placeholder="Tuliskan keterangan jika ada rincian transfer khusus..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none resize-none"><?= clean($payInfo['payment']['notes'] ?? '') ?></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button type="submit" class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-amber-600 text-white font-bold text-xs hover:bg-amber-700 transition shadow-lg flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                <span>Kirim Konfirmasi Pembayaran</span>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

<script>
// Validasi ukuran foto (maks 5MB)
function validatePhotoSize(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxBytes = 5 * 1024 * 1024; // 5 MB
        if (file.size > maxBytes) {
            alert('Ukuran file foto melebihi 5 MB (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB).\nSilakan pilih foto dengan ukuran maksimal 5 MB.');
            input.value = '';
        }
    }
}

// Tambah card formulir delegasi baru secara dinamis
function addDelegateCard() {
    const container = document.getElementById('formsContainer');
    const cards = container.querySelectorAll('.delegate-card');
    const newIdx = cards.length;

    // Clone card pertama
    const clone = cards[0].cloneNode(true);
    clone.setAttribute('data-index', newIdx);
    clone.querySelector('.card-number').innerText = newIdx + 1;
    clone.querySelector('h3').innerText = 'Form Delegasi #' + (newIdx + 1);

    // Tampilkan tombol hapus pada clone
    const removeBtn = clone.querySelector('.btn-remove-card');
    if (removeBtn) {
        removeBtn.classList.remove('hidden');
    }

    // Reset nilai input di clone
    clone.querySelectorAll('input').forEach(input => {
        input.value = '';
    });

    container.appendChild(clone);

    // Scroll halus ke kartu baru
    clone.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function removeCard(btn) {
    const card = btn.closest('.delegate-card');
    const container = document.getElementById('formsContainer');
    if (container.querySelectorAll('.delegate-card').length > 1) {
        card.remove();
        // Renomor ulang
        container.querySelectorAll('.delegate-card').forEach((c, idx) => {
            c.setAttribute('data-index', idx);
            c.querySelector('.card-number').innerText = idx + 1;
            c.querySelector('h3').innerText = 'Form Delegasi #' + (idx + 1);
        });
    } else {
        alert('Minimal harus ada 1 formulir delegasi.');
    }
}

// Salin nomor rekening ke clipboard
function copyRekening() {
    const text = document.getElementById('rekNumber').innerText.trim();
    navigator.clipboard.writeText(text).then(() => {
        const btnText = document.getElementById('copyBtnText');
        btnText.innerText = 'Tersalin!';
        setTimeout(() => {
            btnText.innerText = 'Salin';
        }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
