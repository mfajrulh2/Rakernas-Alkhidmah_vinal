<?php
/**
 * Modul Registrasi Peserta Rakernas Online (Multi-Step Wizard)
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

ensureRegistrationTablesExist();
$pdo = getDB();

// Ambil pengaturan
$feePerPerson = (int)getSetting('fee_per_participant', 300000);

// Data Rekening Resmi Pembayaran dari Pengaturan
$bankInfo     = getPaymentBankInfo();
$bankName     = $bankInfo['bank_name'];
$bankAccount  = $bankInfo['account_no'];
$bankHolder   = $bankInfo['account_holder'];

// Kontak WhatsApp Panitia / Pusat Informasi
$panitiaInfo   = getPanitiaContact();
$panitiaWA     = $panitiaInfo['wa'];
$panitiaName   = $panitiaInfo['name'];
$panitiaWALink = $panitiaInfo['wa_link'];

// Informasi Acara, Tempat, Tanggal, dan Organisasi dari Pengaturan
$eventInfo     = getEventInfo();
$eventLocation = $eventInfo['location'];
$eventDate     = $eventInfo['date'];
$eventCity     = $eventInfo['city'];
$eventName     = $eventInfo['name'];
$orgName       = getSetting('idcard_org_name', 'PERKUMPULAN JAMA\'AH AL KHIDMAH');

// Ambil daftar seluruh PW untuk dropdown
$stmtPW = $pdo->query("SELECT id, code, name, province, quota_utusan, quota_peninjau FROM wilayah ORDER BY name ASC");
$allWilayah = $stmtPW->fetchAll();

// Handle Form Submission dari Step 3 (Konfirmasi & Terbitkan Bukti)
$successGroup = null;
$submitError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_registration') {
    try {
        $kategori   = trim($_POST['kategori'] ?? '');
        $pw_id      = !empty($_POST['pw_id']) ? (int)$_POST['pw_id'] : null;
        $daerah_id  = !empty($_POST['daerah_id']) ? (int)$_POST['daerah_id'] : null;
        $daerahName = trim($_POST['daerah_name'] ?? '');

        // Validasi kuota di sisi server
        $pesertaNames = $_POST['nama_peserta'] ?? [];
        $totalPeserta = count($pesertaNames);

        if ($totalPeserta <= 0) {
            throw new Exception("Minimal harus ada 1 peserta yang didaftarkan.");
        }

        if ($kategori === 'PW' && $pw_id) {
            if ($totalPeserta > 10) {
                throw new Exception("Jumlah peserta melebihi batas kuota Pengurus Wilayah (Maksimal 10 orang).");
            }
        } elseif ($kategori === 'PD' && ($daerah_id || !empty($daerahName))) {
            if ($totalPeserta > 5) {
                throw new Exception("Jumlah peserta melebihi batas kuota Pengurus Daerah (Maksimal 5 orang).");
            }
        }

        // Inspeksi kolom peserta secara dinamis sebelum membuka transaksi (tanpa DDL dalam transaksi)
        $partColsRaw = $pdo->query("SHOW COLUMNS FROM `participants`")->fetchAll(PDO::FETCH_ASSOC);
        $partColMap = [];
        $pwIdNullable = true;
        foreach ($partColsRaw as $c) {
            $partColMap[$c['Field']] = true;
            if ($c['Field'] === 'pw_id' && strtoupper($c['Null']) === 'NO') {
                $pwIdNullable = false;
            }
        }

        // Jika kolom pw_id NOT NULL di MySQL dan kategori PP / Tamu Undangan, fallback ke PW pertama agar tidak integrity constraint violation
        $safePwId = $pw_id;
        if ($safePwId === null && !$pwIdNullable) {
            $safePwId = (int)($pdo->query("SELECT id FROM wilayah ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);
        }

        // Generate group_code
        $groupCode = generateGroupCode();
        $picName   = trim($pesertaNames[0] ?? 'Ketua Rombongan');
        $picPhone  = trim($_POST['phone_peserta'][0] ?? '');

        // Nominal iuran
        $nominalPerPerson = ($kategori === 'Tamu Undangan') ? 0 : $feePerPerson;
        $totalAmount      = $nominalPerPerson * $totalPeserta;

        // Buka Transaksi
        $pdo->beginTransaction();

        // Insert ke registration_groups
        $stmtG = $pdo->prepare("
            INSERT INTO `registration_groups` (
                group_code, kategori, pw_id, daerah_id, daerah_name, 
                pic_name, pic_phone, total_participants, fee_per_person, 
                total_amount, payment_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', NOW())
        ");
        $stmtG->execute([
            $groupCode, $kategori, $pw_id, $daerah_id, $daerahName,
            $picName, $picPhone, $totalPeserta, $nominalPerPerson, $totalAmount
        ]);
        $groupId = $pdo->lastInsertId();

        // Data ketua rombongan untuk fallback peserta yang 'Sama dengan Ketua Rombongan'
        $leaderArrivalDay   = trim($_POST['arrival_day'][0] ?? '');
        if ($leaderArrivalDay === '__custom__' && !empty($_POST['arrival_day_custom'][0])) {
            $leaderArrivalDay = trim($_POST['arrival_day_custom'][0]);
        }
        $leaderArrivalTime  = trim($_POST['arrival_time'][0] ?? '');
        $leaderDepartureDay = trim($_POST['departure_day'][0] ?? '');
        if ($leaderDepartureDay === '__custom__' && !empty($_POST['departure_day_custom'][0])) {
            $leaderDepartureDay = trim($_POST['departure_day_custom'][0]);
        }
        $leaderDepartureTime= trim($_POST['departure_time'][0] ?? '');
        $leaderTransport    = trim($_POST['transport_mode'][0] ?? '');
        $leaderNeedPickup   = !empty($_POST['need_pickup'][0]) ? 1 : 0;
        $leaderPickupLoc    = $leaderNeedPickup ? trim($_POST['pickup_location'][0] ?? '') : null;
        $leaderPickupTime   = $leaderNeedPickup ? trim($_POST['pickup_time_req'][0] ?? '') : null;
        $leaderPickupNotes  = $leaderNeedPickup ? trim($_POST['pickup_notes'][0] ?? '') : null;

        // Ambil prefix kode peserta
        $pwCode = 'GEN';
        if ($pw_id) {
            $stmtC = $pdo->prepare("SELECT code FROM wilayah WHERE id = ?");
            $stmtC->execute([$pw_id]);
            $pwCode = $stmtC->fetchColumn() ?: 'GEN';
        }

        for ($i = 0; $i < $totalPeserta; $i++) {
            $isLeader = ($i === 0) ? 1 : 0;
            $nama     = trim($pesertaNames[$i] ?? '');
            if (empty($nama)) continue;

            $gender   = $_POST['gender_peserta'][$i] ?? 'L';
            $phone    = trim($_POST['phone_peserta'][$i] ?? '');
            
            // Unsur, Jabatan, Komisi (dikosongkan jika Tamu Undangan)
            if ($kategori === 'Tamu Undangan') {
                $unsur    = 'Tamu Undangan';
                $jabatan  = 'Tamu Undangan';
                $komisi   = 'Tamu Undangan';
                $partType = 'undangan';
            } else {
                $unsur    = trim($_POST['unsur_peserta'][$i] ?? 'Al Khidmah');
                $jabatan  = trim($_POST['jabatan_peserta'][$i] ?? 'Pengurus');
                $komisi   = trim($_POST['komisi_peserta'][$i] ?? 'Bidang Organisasi');
                $partType = ($kategori === 'PP') ? 'pengurus_pusat' : 'utusan';
            }

            // Perjalanan & Transportasi
            $sameAsLeader = !empty($_POST['same_as_leader'][$i]) ? 1 : 0;
            if ($isLeader || !$sameAsLeader) {
                $arrDay   = trim($_POST['arrival_day'][$i] ?? $leaderArrivalDay);
                if ($arrDay === '__custom__' && !empty($_POST['arrival_day_custom'][$i])) {
                    $arrDay = trim($_POST['arrival_day_custom'][$i]);
                }
                $arrTime  = trim($_POST['arrival_time'][$i] ?? $leaderArrivalTime);
                $depDay   = trim($_POST['departure_day'][$i] ?? $leaderDepartureDay);
                if ($depDay === '__custom__' && !empty($_POST['departure_day_custom'][$i])) {
                    $depDay = trim($_POST['departure_day_custom'][$i]);
                }
                $depTime  = trim($_POST['departure_time'][$i] ?? $leaderDepartureTime);
                $trans    = trim($_POST['transport_mode'][$i] ?? $leaderTransport);
                $pickup   = !empty($_POST['need_pickup'][$i]) ? 1 : 0;
                $pickupLoc= $pickup ? trim($_POST['pickup_location'][$i] ?? '') : null;
                $pickupTimeReq = $pickup ? trim($_POST['pickup_time_req'][$i] ?? '') : null;
                $pickupNotes   = $pickup ? trim($_POST['pickup_notes'][$i] ?? '') : null;
            } else {
                $arrDay   = $leaderArrivalDay;
                $arrTime  = $leaderArrivalTime;
                $depDay   = $leaderDepartureDay;
                $depTime  = $leaderDepartureTime;
                $trans    = $leaderTransport;
                $pickup   = $leaderNeedPickup;
                $pickupLoc= $leaderPickupLoc;
                $pickupTimeReq = $leaderPickupTime;
                $pickupNotes   = $leaderPickupNotes;
            }

            // Upload Foto
            $photoPath = null;
            if (isset($_FILES['foto_peserta']['name'][$i]) && !empty($_FILES['foto_peserta']['name'][$i])) {
                if (function_exists('uploadParticipantPhoto')) {
                    $uploadRes = uploadParticipantPhoto($_FILES['foto_peserta'], $i);
                    if (!empty($uploadRes['uploaded'])) {
                        $photoPath = $uploadRes['path'];
                    }
                }
            }

            $regCode = generateRegistrationCode($pwCode);

            // Bangun kolom dan nilai INSERT dinamis sesuai ketersediaan di tabel participants
            $insertFields = ['registration_code', 'full_name', 'gender', 'phone'];
            $insertValues = [$regCode, $nama, $gender, $phone];

            if (isset($partColMap['group_id'])) {
                $insertFields[] = 'group_id';
                $insertValues[] = $groupId;
            }
            if (isset($partColMap['kategori'])) {
                $insertFields[] = 'kategori';
                $insertValues[] = $kategori;
            }
            if (isset($partColMap['pw_id'])) {
                $insertFields[] = 'pw_id';
                $insertValues[] = $safePwId;
            }
            if (isset($partColMap['daerah_id'])) {
                $insertFields[] = 'daerah_id';
                $insertValues[] = $daerah_id;
            }
            if (isset($partColMap['pd_name'])) {
                $insertFields[] = 'pd_name';
                $insertValues[] = $daerahName;
            }
            if (isset($partColMap['unsur_organisasi'])) {
                $insertFields[] = 'unsur_organisasi';
                $insertValues[] = $unsur;
            }
            if (isset($partColMap['jabatan_organisasi'])) {
                $insertFields[] = 'jabatan_organisasi';
                $insertValues[] = $jabatan;
            }
            if (isset($partColMap['participant_type'])) {
                $insertFields[] = 'participant_type';
                $insertValues[] = $partType;
            }
            if (isset($partColMap['komisi'])) {
                $insertFields[] = 'komisi';
                $insertValues[] = $komisi;
            }
            if (isset($partColMap['is_group_leader'])) {
                $insertFields[] = 'is_group_leader';
                $insertValues[] = $isLeader;
            }
            if (isset($partColMap['arrival_day'])) {
                $insertFields[] = 'arrival_day';
                $insertValues[] = $arrDay;
            }
            if (isset($partColMap['arrival_time'])) {
                $insertFields[] = 'arrival_time';
                $insertValues[] = $arrTime;
            }
            if (isset($partColMap['departure_day'])) {
                $insertFields[] = 'departure_day';
                $insertValues[] = $depDay;
            }
            if (isset($partColMap['departure_time'])) {
                $insertFields[] = 'departure_time';
                $insertValues[] = $depTime;
            }
            if (isset($partColMap['transport_mode'])) {
                $insertFields[] = 'transport_mode';
                $insertValues[] = $trans;
            }
            if (isset($partColMap['need_pickup'])) {
                $insertFields[] = 'need_pickup';
                $insertValues[] = $pickup;
            }
            if (isset($partColMap['pickup_location'])) {
                $insertFields[] = 'pickup_location';
                $insertValues[] = $pickupLoc;
            }
            if (isset($partColMap['pickup_time'])) {
                $insertFields[] = 'pickup_time';
                $insertValues[] = $pickupTimeReq;
            }
            if (isset($partColMap['pickup_notes'])) {
                $insertFields[] = 'pickup_notes';
                $insertValues[] = $pickupNotes;
            }
            if (isset($partColMap['same_as_leader'])) {
                $insertFields[] = 'same_as_leader';
                $insertValues[] = $sameAsLeader;
            }
            if (isset($partColMap['photo'])) {
                $insertFields[] = 'photo';
                $insertValues[] = $photoPath;
            }
            if (isset($partColMap['photo_file'])) {
                $insertFields[] = 'photo_file';
                $insertValues[] = $photoPath;
            }
            if (isset($partColMap['status_verifikasi'])) {
                $insertFields[] = 'status_verifikasi';
                $insertValues[] = 'verified';
            }
            if (isset($partColMap['created_at'])) {
                $insertFields[] = 'created_at';
                $insertValues[] = date('Y-m-d H:i:s');
            }

            $placeholders = implode(', ', array_fill(0, count($insertFields), '?'));
            $fieldNames = '`' . implode('`, `', $insertFields) . '`';
            $stmtP = $pdo->prepare("INSERT INTO `participants` ({$fieldNames}) VALUES ({$placeholders})");
            $stmtP->execute($insertValues);
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        // Redirect ke Step 4 dengan group_code
        header("Location: " . BASE_URL . "registrasi.php?success=" . urlencode($groupCode));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $submitError = $e->getMessage();
    }
}

// Cek apakah baru selesai submit pendaftaran (Step 4 Bukti Pendaftaran)
$viewGroupCode = trim($_GET['success'] ?? '');
$successGroupData = null;
$groupParticipants = [];
if (!empty($viewGroupCode)) {
    $stmtViewG = $pdo->prepare("
        SELECT g.*, w.name as pw_name, d.name as db_daerah_name
        FROM registration_groups g
        LEFT JOIN wilayah w ON g.pw_id = w.id
        LEFT JOIN daerah d ON g.daerah_id = d.id
        WHERE g.group_code = ? LIMIT 1
    ");
    $stmtViewG->execute([$viewGroupCode]);
    $successGroupData = $stmtViewG->fetch();

    if ($successGroupData) {
        $stmtViewP = $pdo->prepare("
            SELECT * FROM participants WHERE group_id = ? ORDER BY is_group_leader DESC, id ASC
        ");
        $stmtViewP->execute([$successGroupData['id']]);
        $groupParticipants = $stmtViewP->fetchAll();
    }
}

$page_title = "Pendaftaran Peserta Rakernas Online";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Include html2canvas for Download as Image -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<div class="max-w-5xl mx-auto space-y-8 pb-20">

    <?php if ($successGroupData): ?>
        <!-- =================================================================== -->
        <!-- TAHAP D: CETAK BUKTI PENDAFTARAN & KWITANSI (STEP 4) -->
        <!-- =================================================================== -->
        <div class="space-y-6">
            <!-- Alert Berhasil -->
            <div class="bg-emerald-50 border-2 border-emerald-500 rounded-3xl p-6 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4 no-print">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-md">
                        <svg class="w-7 h-7 text-gold-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-emerald-950">Pendaftaran Berhasil Diterbitkan!</h2>
                        <p class="text-xs text-emerald-800 mt-0.5">Kode Transaksi: <strong class="font-mono bg-white px-2 py-0.5 rounded border border-emerald-300"><?= clean($successGroupData['group_code']) ?></strong> • Simpan dan unduh bukti pendaftaran di bawah ini.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-stretch sm:self-auto shrink-0">
                    <button onclick="downloadAsPNG()" class="px-4 py-2.5 rounded-xl bg-emerald-800 text-white hover:bg-emerald-900 transition text-xs font-bold flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download Gambar (PNG)</span>
                    </button>
                    <button onclick="window.print()" class="px-4 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition text-xs font-bold flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Cetak / PDF (A4)</span>
                    </button>
                </div>
            </div>

            <!-- Lembar Resmi Bukti Pendaftaran (Siap Cetak / html2canvas) -->
            <div id="print-area" class="bg-white rounded-3xl border border-slate-200 shadow-lg p-6 sm:p-10 space-y-8">
                <!-- Kop Surat Al Khidmah -->
                <div class="border-b-2 border-emerald-900 pb-5 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-alkhidmah-900 flex items-center justify-center text-white shrink-0 shadow-sm">
                            <svg class="w-10 h-10 text-gold-400" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-[11px] font-extrabold uppercase tracking-widest text-emerald-800">Panitia Pelaksana <?= clean($eventName) ?></div>
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-tight"><?= clean($orgName) ?></h1>
                            <p class="text-xs text-slate-500 font-medium"><?= clean($eventLocation) ?> • <?= clean($eventDate) ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-900 text-xs font-black rounded-lg border border-emerald-300">
                            BUKTI PENDAFTARAN RESMI
                        </span>
                        <div class="text-[10px] text-slate-400 mt-1 font-mono">Diterbitkan: <?= date('d/m/Y H:i', strtotime($successGroupData['created_at'])) ?> WIB</div>
                    </div>
                </div>

                <!-- Info Identitas Rombongan & QR Code -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-slate-50/80 rounded-2xl p-5 sm:p-6 border border-slate-200/80">
                    <div class="md:col-span-2 space-y-3">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <span class="text-slate-400 block text-[10px] uppercase font-bold">Kode Transaksi</span>
                                <span class="font-mono font-black text-slate-900 text-sm text-emerald-900"><?= clean($successGroupData['group_code']) ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] uppercase font-bold">Kategori Peserta</span>
                                <span class="font-extrabold text-slate-900"><?= clean($successGroupData['kategori']) ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] uppercase font-bold">Total Delegasi</span>
                                <span class="font-extrabold text-slate-900 text-sm"><?= (int)$successGroupData['total_participants'] ?> Orang</span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="text-slate-400 block text-[10px] uppercase font-bold">Asal Kepengurusan</span>
                                <span class="font-bold text-slate-800">
                                    <?php 
                                    if ($successGroupData['kategori'] === 'PP') {
                                        echo "Pengurus Pusat (PP) Al Khidmah";
                                    } elseif ($successGroupData['kategori'] === 'Tamu Undangan') {
                                        echo "Tamu Undangan Khusus";
                                    } elseif ($successGroupData['kategori'] === 'PW') {
                                        echo clean($successGroupData['pw_name']);
                                    } else {
                                        echo clean($successGroupData['daerah_name'] ?: $successGroupData['db_daerah_name']) . " (" . clean($successGroupData['pw_name']) . ")";
                                    }
                                    ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] uppercase font-bold">PIC / Ketua Rombongan</span>
                                <span class="font-bold text-slate-800"><?= clean($successGroupData['pic_name']) ?> <span class="text-slate-400 font-mono text-[11px]">(<?= clean($successGroupData['pic_phone']) ?>)</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Check-in -->
                    <div class="flex flex-col items-center justify-center p-3 bg-white rounded-xl border border-slate-200 shadow-xs text-center">
                        <img src="<?= getQRCodeUrl($successGroupData['group_code'], 180) ?>" alt="QR Code Pendaftaran" class="w-32 h-32 object-contain rounded-lg">
                        <span class="text-[10px] font-mono font-bold text-slate-600 mt-2">Scan untuk Check-in Presensi</span>
                        <span class="text-[9px] text-slate-400">Tunjukkan barcode ini kepada petugas di lokasi</span>
                    </div>
                </div>

                <!-- Tabel Daftar Peserta Rombongan -->
                <div>
                    <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-3">Daftar Peserta Rombongan (<?= count($groupParticipants) ?> Orang)</h3>
                    <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-100 text-slate-500 font-bold text-[10px] uppercase">
                                <tr>
                                    <th class="py-2.5 px-3 text-center w-8">No</th>
                                    <th class="py-2.5 px-3">Kode Registrasi</th>
                                    <th class="py-2.5 px-4">Nama Lengkap</th>
                                    <th class="py-2.5 px-3 text-center">L/P</th>
                                    <?php if ($successGroupData['kategori'] !== 'Tamu Undangan'): ?>
                                        <th class="py-2.5 px-4">Unsur / Jabatan</th>
                                        <th class="py-2.5 px-4">Komisi</th>
                                    <?php endif; ?>
                                    <th class="py-2.5 px-4">Jadwal Kedatangan</th>
                                    <th class="py-2.5 px-3 text-center">Transportasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($groupParticipants as $idx => $p): ?>
                                    <tr class="<?= $p['is_group_leader'] ? 'bg-emerald-50/40 font-semibold' : '' ?>">
                                        <td class="py-2.5 px-3 text-center text-slate-400 font-bold"><?= $idx + 1 ?></td>
                                        <td class="py-2.5 px-3 font-mono text-[11px] font-bold text-emerald-800"><?= clean($p['registration_code']) ?></td>
                                        <td class="py-2.5 px-4">
                                            <div class="font-bold text-slate-900"><?= clean($p['full_name']) ?></div>
                                            <div class="text-[10px] text-slate-400 font-mono"><?= clean($p['phone']) ?></div>
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-bold"><?= clean($p['gender']) ?></td>
                                        <?php if ($successGroupData['kategori'] !== 'Tamu Undangan'): ?>
                                            <td class="py-2.5 px-4">
                                                <div><?= clean($p['jabatan_organisasi']) ?></div>
                                            </td>
                                            <td class="py-2.5 px-4">
                                                <span class="text-[11px] text-slate-600"><?= clean($p['komisi']) ?></span>
                                            </td>
                                        <?php endif; ?>
                                        <td class="py-2.5 px-4 text-xs">
                                            <div><?= clean($p['arrival_day'] ?: '-') ?> <?= clean($p['arrival_time']) ?></div>
                                            <?php if (!empty($p['need_pickup'])): ?>
                                                <div class="mt-1 bg-amber-50 border border-amber-200/90 rounded-lg p-1.5 text-[10px] text-amber-950">
                                                    <div class="font-bold text-amber-900">🚐 Jemput: <?= clean($p['pickup_location'] ?: 'Stasiun/Bandara') ?></div>
                                                    <?php if (!empty($p['pickup_time'])): ?>
                                                        <div class="text-[9px] text-slate-600">Jam: <?= clean($p['pickup_time']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($p['pickup_notes'])): ?>
                                                        <div class="text-[9px] text-slate-500 italic mt-0.5">Catatan: <?= clean($p['pickup_notes']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2.5 px-3 text-center text-xs text-slate-600">
                                            <?= clean($p['transport_mode'] ?: '-') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kwitansi Tagihan Iuran Akomodasi (HANYA UNTUK PP, PW, DAN PD) -->
                <?php if ($successGroupData['kategori'] !== 'Tamu Undangan'): ?>
                    <div class="border-2 border-dashed border-amber-300 bg-amber-50/60 rounded-3xl p-6 sm:p-7 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-amber-200">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold text-sm shadow-xs">Rp</span>
                                <div>
                                    <h4 class="text-sm font-extrabold text-amber-950 uppercase tracking-wide">Kwitansi Tagihan Iuran Akomodasi</h4>
                                    <p class="text-[11px] text-amber-800">Iuran akomodasi, konsumsi, dan fasilitas RAKERNAS Jama'ah Al Khidmah 2026</p>
                                </div>
                            </div>
                            <span class="text-xs font-black text-amber-900 bg-amber-200/80 px-3 py-1 rounded-xl">
                                STATUS: MENUNGGU PEMBAYARAN
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
                            <div class="space-y-2">
                                <div class="flex justify-between py-1 border-b border-amber-200/60 text-slate-600">
                                    <span>Tarif Iuran per Delegasi</span>
                                    <span class="font-mono font-bold text-slate-800">Rp <?= number_format($successGroupData['fee_per_person'], 0, ',', '.') ?></span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-amber-200/60 text-slate-600">
                                    <span>Jumlah Delegasi</span>
                                    <span class="font-bold text-slate-800"><?= (int)$successGroupData['total_participants'] ?> Orang</span>
                                </div>
                                <div class="flex justify-between py-2 text-base font-extrabold text-amber-950">
                                    <span>Total Tagihan</span>
                                    <span class="font-mono text-emerald-800 text-lg">Rp <?= number_format($successGroupData['total_amount'], 0, ',', '.') ?></span>
                                </div>
                            </div>

                            <div class="bg-white/90 p-4 rounded-2xl border border-amber-200 space-y-1.5">
                                <div class="text-[10px] uppercase font-bold text-slate-400">Rekening Resmi Pembayaran</div>
                                <div class="text-sm font-extrabold text-slate-900"><?= clean($bankName) ?></div>
                                <div class="text-base font-mono font-black text-emerald-800 tracking-wider"><?= clean($bankAccount) ?></div>
                                <div class="text-xs font-medium text-slate-600">a.n. <?= clean($bankHolder) ?></div>
                                <p class="text-[10px] text-amber-900 pt-1 italic">* Harap cantumkan berita transfer: <strong><?= clean($successGroupData['group_code']) ?></strong></p>
                            </div>
                        </div>

                        <!-- Tombol Aksi Konfirmasi Pembayaran -->
                        <div class="no-print pt-3 border-t border-amber-200/70 flex flex-wrap items-center justify-end gap-2.5">
                            <a href="<?= BASE_URL ?>konfirmasi_pembayaran.php?code=<?= urlencode($successGroupData['group_code']) ?>" class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5">
                                <span>📤 Upload Bukti Transfer</span>
                            </a>
                            <a href="<?= getWhatsAppConfirmUrl($successGroupData) ?>" target="_blank" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                <span>Konfirmasi via WhatsApp</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-emerald-50 rounded-2xl p-4 border border-emerald-200 text-xs text-emerald-900 flex items-center gap-3">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><strong>Kategori Tamu Undangan:</strong> Bebas biaya iuran akomodasi (Free of Charge). Seluruh fasilitas persidangan dan penginapan disiapkan oleh Panitia Pusat.</span>
                    </div>
                <?php endif; ?>

                <!-- Pusat Informasi & Konfirmasi WhatsApp Panitia -->
                <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 rounded-2xl border border-emerald-200 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-emerald-800 tracking-wider">Pusat Informasi & Konfirmasi Panitia</div>
                            <div class="text-sm font-black text-slate-900 flex items-center gap-2 flex-wrap">
                                <span>No. WhatsApp: <?= clean($panitiaWA) ?></span>
                                <span class="text-xs font-bold text-emerald-800 bg-emerald-100/90 border border-emerald-300 px-2.5 py-0.5 rounded-full">a.n. <?= clean($panitiaName) ?></span>
                            </div>
                            <p class="text-[11px] text-slate-600 mt-0.5">Layanan bantuan informasi pendaftaran, konfirmasi bukti transfer, dan koordinasi kedatangan delegasi.</p>
                        </div>
                    </div>
                    <div class="no-print shrink-0">
                        <a href="<?= clean($panitiaWALink) ?>?text=<?= urlencode('Assalamu\'alaikum Panitia Rakernas, saya mengonfirmasi pendaftaran delegasi dengan kode: ' . $successGroupData['group_code']) ?>" target="_blank" class="px-4 py-2.5 rounded-xl bg-emerald-700 text-white font-bold text-xs hover:bg-emerald-800 transition inline-flex items-center gap-2 shadow-sm">
                            <span>Hubungi via WhatsApp</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Catatan Panitia & Tanda Tangan -->
                <div class="pt-6 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs text-slate-500">
                    <div class="space-y-1">
                        <div class="font-bold text-slate-700 uppercase text-[10px]">Ketentuan Kedatangan:</div>
                        <ul class="list-disc pl-4 space-y-0.5 text-[11px]">
                            <li>Tunjukkan bukti pendaftaran ini (cetak atau e-voucher) di meja registrasi kedatangan.</li>
                            <li>Wajib mengenakan baju putih dan kopiah putih bagi ikhwan / busana muslimah putih bagi akhwat.</li>
                            <li>Konfirmasi pengiriman bukti transfer atau koordinasi kedatangan dapat dikirimkan ke WhatsApp Panitia: <strong><?= clean($panitiaWA) ?></strong> (a.n. <strong><?= clean($panitiaName) ?></strong>).</li>
                        </ul>
                    </div>
                    <div class="text-right flex flex-col justify-end">
                        <p class="text-slate-500 text-[11px] font-medium"><?= clean($eventCity) ?>, <?= date('d F Y', strtotime($successGroupData['created_at'])) ?></p>
                        <p class="font-bold text-slate-800 mt-1">Panitia Pelaksana <?= clean($eventName) ?></p>
                        <p class="text-[10px] text-emerald-700 font-semibold mt-8"><?= clean($orgName) ?></p>
                    </div>
                </div>
            </div>

            <!-- Tombol Kembali / Daftar Baru -->
            <div class="flex justify-between items-center pt-4 no-print">
                <a href="<?= BASE_URL ?>" class="text-xs font-bold text-slate-500 hover:text-slate-800 flex items-center gap-1">
                    &larr; Kembali ke Beranda
                </a>
                <a href="<?= BASE_URL ?>registrasi.php" class="px-5 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition flex items-center gap-1.5 shadow-sm">
                    <span>+ Pendaftaran Rombongan Baru</span>
                </a>
            </div>
        </div>

        <script>
        function downloadAsPNG() {
            const element = document.getElementById('print-area');
            html2canvas(element, { scale: 2 }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Bukti_Pendaftaran_<?= $successGroupData['group_code'] ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
        </script>

    <?php else: ?>
        <!-- =================================================================== -->
        <!-- WIZARD PENDAFTARAN (STEP 1, 2, 3) -->
        <!-- =================================================================== -->

        <!-- Page Header -->
        <div class="text-center space-y-2 max-w-2xl mx-auto">
            <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-900 text-xs font-extrabold rounded-full tracking-wide">
                FORMULIR REGISTRASI RESMI
            </span>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Pendaftaran Peserta RAKERNAS 2026</h1>
            <p class="text-xs sm:text-sm text-slate-600 font-light">
                Silakan ikuti langkah-langkah pendaftaran delegasi di bawah ini secara bertahap.
            </p>
        </div>

        <?php if (!empty($submitError)): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><strong>Gagal menyimpan pendaftaran:</strong> <?= clean($submitError) ?></span>
            </div>
        <?php endif; ?>

        <!-- Progress Stepper -->
        <div class="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between max-w-2xl mx-auto text-xs font-bold">
                <!-- Step 1 Indicator -->
                <div id="step-nav-1" class="flex flex-col items-center gap-1.5 text-emerald-800">
                    <div class="w-8 h-8 rounded-full bg-emerald-700 text-white flex items-center justify-center text-xs font-black shadow-sm">1</div>
                    <span class="text-[11px] sm:text-xs text-center">Identifikasi Kategori</span>
                </div>
                <div class="flex-grow h-0.5 bg-slate-200 mx-2 mb-4"></div>

                <!-- Step 2 Indicator -->
                <div id="step-nav-2" class="flex flex-col items-center gap-1.5 text-slate-400">
                    <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-black">2</div>
                    <span class="text-[11px] sm:text-xs text-center">Formulir Peserta</span>
                </div>
                <div class="flex-grow h-0.5 bg-slate-200 mx-2 mb-4"></div>

                <!-- Step 3 Indicator -->
                <div id="step-nav-3" class="flex flex-col items-center gap-1.5 text-slate-400">
                    <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-black">3</div>
                    <span class="text-[11px] sm:text-xs text-center">Resume & Iuran</span>
                </div>
            </div>
        </div>

        <form id="wizard-form" action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_registration">
            <input type="hidden" id="input_kategori" name="kategori" value="">
            <input type="hidden" id="input_pw_id" name="pw_id" value="">
            <input type="hidden" id="input_daerah_id" name="daerah_id" value="">
            <input type="hidden" id="input_daerah_name" name="daerah_name" value="">

            <!-- =================================================================== -->
            <!-- STEP 1: IDENTIFIKASI KATEGORI PESERTA -->
            <!-- =================================================================== -->
            <div id="step-1" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-10 space-y-8">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Pilih Kategori Kepengurusan / Delegasi</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Pilih salah satu dari 4 kategori kepesertaan Rakernas</p>
                </div>

                <!-- 4 Kategori Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- PP -->
                    <label class="category-card cursor-pointer border-2 border-slate-200 hover:border-emerald-500 rounded-2xl p-5 text-center transition flex flex-col items-center justify-between group">
                        <input type="radio" name="temp_kategori" value="PP" class="hidden" onchange="selectCategory('PP')">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <div class="font-extrabold text-sm text-slate-900">Pengurus Pusat (PP)</div>
                        <div class="text-[10px] text-slate-500 mt-1">Delegasi dari Dewan Pengurus Pusat Al Khidmah</div>
                        <div class="mt-3 text-[10px] font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full">Bebas Kuota</div>
                    </label>

                    <!-- PW -->
                    <label class="category-card cursor-pointer border-2 border-slate-200 hover:border-emerald-500 rounded-2xl p-5 text-center transition flex flex-col items-center justify-between group">
                        <input type="radio" name="temp_kategori" value="PW" class="hidden" onchange="selectCategory('PW')">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="font-extrabold text-sm text-slate-900">Pengurus Wilayah (PW)</div>
                        <div class="text-[10px] text-slate-500 mt-1">Delegasi resmi Pengurus Wilayah provinsi</div>
                        <div class="mt-3 text-[10px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full">Maks. 10 Orang</div>
                    </label>

                    <!-- PD -->
                    <label class="category-card cursor-pointer border-2 border-slate-200 hover:border-emerald-500 rounded-2xl p-5 text-center transition flex flex-col items-center justify-between group">
                        <input type="radio" name="temp_kategori" value="PD" class="hidden" onchange="selectCategory('PD')">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div class="font-extrabold text-sm text-slate-900">Pengurus Daerah (PD)</div>
                        <div class="text-[10px] text-slate-500 mt-1">Delegasi Pengurus Daerah Kota/Kabupaten</div>
                        <div class="mt-3 text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full">Maks. 5 Orang</div>
                    </label>

                    <!-- Tamu Undangan -->
                    <label class="category-card cursor-pointer border-2 border-slate-200 hover:border-emerald-500 rounded-2xl p-5 text-center transition flex flex-col items-center justify-between group">
                        <input type="radio" name="temp_kategori" value="Tamu Undangan" class="hidden" onchange="selectCategory('Tamu Undangan')">
                        <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-700 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <div class="font-extrabold text-sm text-slate-900">Tamu Undangan</div>
                        <div class="text-[10px] text-slate-500 mt-1">Kyai, Ulama, Habaib, Pejabat & Tamu Khusus</div>
                        <div class="mt-3 text-[10px] font-bold text-purple-800 bg-purple-50 px-2 py-0.5 rounded-full">Bebas Iuran</div>
                    </label>
                </div>

                <!-- Bagian Form Dropdown Wilayah & Daerah (Muncul Dinamis) -->
                <div id="origin-selection-container" class="hidden bg-slate-50 p-6 rounded-2xl border border-slate-200 space-y-4">
                    <!-- Dropdown Wilayah (PW & PD) -->
                    <div id="box-pw-dropdown" class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-800 uppercase">Pilih Asal Wilayah (PW) *</label>
                        <select id="select_wilayah" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-600 focus:outline-none" onchange="onWilayahChange(this.value)">
                            <option value="">-- Pilih Wilayah / Provinsi --</option>
                            <?php foreach ($allWilayah as $w): ?>
                                <option value="<?= $w['id'] ?>" data-name="<?= clean($w['name']) ?>" data-code="<?= clean($w['code']) ?>">
                                    <?= clean($w['name']) ?> (<?= clean($w['province']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Dependent Dropdown Daerah (Hanya untuk PD) -->
                    <div id="box-pd-dropdown" class="space-y-1.5 hidden">
                        <label class="block text-xs font-bold text-slate-800 uppercase">Pilih Pengurus Daerah (PD) *</label>
                        <div class="relative">
                            <select id="select_daerah" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-600 focus:outline-none" onchange="onDaerahChange(this.value)">
                                <option value="">-- Pilih Daerah setelah memilih Wilayah --</option>
                            </select>
                            <div id="daerah-loading" class="hidden absolute right-3 top-2.5 text-xs text-slate-400">
                                Memuat...
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Data Pengurus Daerah menyesuaikan wilayah yang dipilih di atas.</p>
                    </div>

                    <!-- Info Alert Kuota -->
                    <div id="quota-info-badge" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 hidden flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span id="quota-info-text"></span>
                    </div>
                </div>

                <!-- Tombol Next Step 1 -->
                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button type="button" onclick="goToStep2()" class="px-6 py-3 rounded-2xl bg-alkhidmah-800 text-white font-extrabold text-xs hover:bg-alkhidmah-900 transition flex items-center gap-2 shadow-md">
                        <span>Lanjut ke Formulir Peserta</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>

            <!-- =================================================================== -->
            <!-- STEP 2: FORMULIR PESERTA & ROMBONGAN -->
            <!-- =================================================================== -->
            <div id="step-2" class="hidden space-y-6">
                <!-- Datalist Lokasi Penjemputan Populer -->
                <datalist id="list-lokasi-jemput">
                    <option value="Stasiun Surabaya Gubeng (SGU)">
                    <option value="Stasiun Surabaya Pasar Turi (SBI)">
                    <option value="Stasiun Wonokromo (WO)">
                    <option value="Bandara Internasional Juanda - Terminal 1 (T1 Domestik)">
                    <option value="Bandara Internasional Juanda - Terminal 2 (T2 Internasional & Garuda)">
                    <option value="Terminal Purabaya (Bungurasih) Surabaya">
                    <option value="Terminal Osowilangun Surabaya">
                    <option value="Pelabuhan Tanjung Perak Surabaya">
                    <option value="Pondok Pesantren Assalafi Al Fithrah (Langsung)">
                </datalist>
                <!-- Info Header Asal -->
                <div class="bg-gradient-to-r from-emerald-800 to-emerald-950 text-white rounded-3xl p-5 sm:p-6 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-gold-300 tracking-wider">Identitas Kepesertaan Terpilih</span>
                        <h2 id="banner-origin-title" class="text-lg font-black text-white leading-tight">Pengurus Wilayah</h2>
                        <p id="banner-origin-sub" class="text-xs text-emerald-200 mt-0.5">Batas Kuota: 10 Orang Delegasi</p>
                    </div>
                    <button type="button" onclick="goToStep1()" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/20 text-xs font-bold transition flex items-center gap-1.5 self-start sm:self-auto">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                        <span>Ganti Kategori / Wilayah</span>
                    </button>
                </div>

                <!-- Tab Navigation: Formulir vs Data Peserta Terdaftar (B.4) -->
                <div class="flex border-b border-slate-200 gap-4">
                    <button type="button" id="tab-btn-form" onclick="switchFormTab('form')" class="pb-3 border-b-2 border-emerald-600 font-extrabold text-xs text-emerald-900 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Formulir Pengisian Peserta</span>
                    </button>
                    <button type="button" id="tab-btn-registered" onclick="switchFormTab('registered')" class="pb-3 border-b-2 border-transparent font-bold text-xs text-slate-500 hover:text-slate-800 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span id="tab-registered-label">Data Peserta Terdaftar (0)</span>
                    </button>
                </div>

                <!-- KONTEN TAB 1: FORMULIR PESERTA & ROMBONGAN -->
                <div id="content-tab-form" class="space-y-6">
                    <!-- Container Cards Peserta (Dynamic Repeater) -->
                    <div id="participants-container" class="space-y-6">
                        <!-- Card Peserta 1 (Ketua Rombongan) will be rendered by JS -->
                    </div>

                    <!-- Tombol Aksi Bawah: Tambah Peserta Rombongan & Lanjut ke Resume -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-200">
                        <button type="button" id="btn-add-companion" onclick="addCompanionCard()" class="px-5 py-2.5 rounded-2xl bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-300 font-extrabold text-xs transition flex items-center gap-2 shadow-xs">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                            <span>Tambah Peserta Rombongan</span>
                        </button>

                        <div class="flex items-center gap-3">
                            <button type="button" onclick="goToStep1()" class="px-5 py-2.5 rounded-2xl border border-slate-300 text-slate-600 font-bold text-xs hover:bg-slate-50 transition">
                                &larr; Kembali
                            </button>
                            <button type="button" onclick="goToStep3()" class="px-6 py-2.5 rounded-2xl bg-alkhidmah-800 text-white font-extrabold text-xs hover:bg-alkhidmah-900 transition flex items-center gap-1.5 shadow-md">
                                <span>Selanjutnya (Resume Data) &rarr;</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- KONTEN TAB 2: DATA PESERTA TERDAFTAR (B.4) -->
                <div id="content-tab-registered" class="hidden bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900" id="registered-list-heading">Daftar Peserta Terdaftar</h3>
                            <p class="text-xs text-slate-500">Menampilkan delegasi yang sudah terdaftar pada kepengurusan ini</p>
                        </div>
                        <button type="button" onclick="loadRegisteredParticipants()" class="text-xs text-emerald-700 font-bold hover:underline flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>Refresh</span>
                        </button>
                    </div>

                    <div id="registered-table-wrapper" class="overflow-x-auto border border-slate-200 rounded-2xl">
                        <!-- Loaded via JS -->
                        <div class="p-8 text-center text-xs text-slate-400">Memuat data peserta...</div>
                    </div>
                </div>
            </div>

            <!-- =================================================================== -->
            <!-- STEP 3: RESUME JUMLAH PESERTA & KONFIRMASI -->
            <!-- =================================================================== -->
            <div id="step-3" class="hidden bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-10 space-y-8">
                <div>
                    <span class="text-xs font-black text-emerald-800 uppercase tracking-wider">Tahap C: Resume Pendaftaran</span>
                    <h2 class="text-xl font-extrabold text-slate-900 mt-1">Konfirmasi & Ringkasan Data Rombongan</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Pastikan seluruh data peserta dan rencana perjalanan di bawah ini sudah benar sebelum diterbitkan.</p>
                </div>

                <!-- Info Box Rombongan -->
                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Kategori</span>
                        <span id="resume-kategori" class="font-extrabold text-slate-900 text-sm">-</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Asal Kepengurusan</span>
                        <span id="resume-origin" class="font-bold text-slate-800 text-sm">-</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Total Peserta</span>
                        <span id="resume-total-count" class="font-black text-slate-900 text-sm">1 Orang</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Total Iuran Akomodasi</span>
                        <span id="resume-total-fee" class="font-mono font-black text-emerald-800 text-sm">Rp 0</span>
                    </div>
                </div>

                <!-- Tabel Resume Seluruh Peserta -->
                <div class="space-y-3">
                    <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wide">Rincian Seluruh Peserta Rombongan</h3>
                    <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-100 text-slate-500 font-bold text-[10px] uppercase">
                                <tr>
                                    <th class="py-3 px-3 text-center w-8">No</th>
                                    <th class="py-3 px-4">Nama Lengkap & Kontak</th>
                                    <th class="py-3 px-3 text-center">L/P</th>
                                    <th class="py-3 px-4" id="resume-th-unsur">Unsur & Jabatan</th>
                                    <th class="py-3 px-4" id="resume-th-komisi">Komisi/Bidang</th>
                                    <th class="py-3 px-4">Jadwal Kedatangan</th>
                                    <th class="py-3 px-3 text-center">Transportasi</th>
                                </tr>
                            </thead>
                            <tbody id="resume-table-body" class="divide-y divide-slate-100">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tombol Navigasi Step 3 -->
                <div class="flex items-center justify-between pt-6 border-t border-slate-200">
                    <button type="button" onclick="goToStep2()" class="px-5 py-2.5 rounded-2xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                        <span>Kembali & Edit Data</span>
                    </button>

                    <button type="submit" class="px-7 py-3 rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-800 hover:from-emerald-500 hover:to-emerald-700 text-white font-extrabold text-xs transition shadow-lg shadow-emerald-950/20 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>Konfirmasi & Terbitkan Bukti Pendaftaran</span>
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>

</div>

<!-- JAVASCRIPT LOGIC UNTUK WIZARD FORM MULTI-STEP -->
<script>
// State Registrasi
let regState = {
    kategori: '',
    pw_id: '',
    pw_name: '',
    daerah_id: '',
    daerah_name: '',
    quota: 999, // default unlimited
    participants: []
};

const FEE_PER_PERSON = <?= $feePerPerson ?>;
const ARRIVAL_OPTIONS = <?= json_encode(getArrivalDayOptions()) ?>;
const DEPARTURE_OPTIONS = <?= json_encode(getDepartureDayOptions()) ?>;

function buildArrivalSelectOptions(selectedVal) {
    let html = '';
    let found = false;
    ARRIVAL_OPTIONS.forEach((opt, idx) => {
        const isSel = selectedVal ? (selectedVal === opt) : (idx === 0 || (ARRIVAL_OPTIONS.length > 1 && idx === 1));
        if (isSel) found = true;
        html += `<option value="${opt}" ${isSel ? 'selected' : ''}>${opt}</option>`;
    });
    html += `<option value="__custom__" ${(!found && selectedVal) ? 'selected' : ''}>+ Input Tanggal Lainnya...</option>`;
    return html;
}

function buildDepartureSelectOptions(selectedVal) {
    let html = '';
    let found = false;
    DEPARTURE_OPTIONS.forEach((opt, idx) => {
        const isSel = selectedVal ? (selectedVal === opt) : (idx === 0);
        if (isSel) found = true;
        html += `<option value="${opt}" ${isSel ? 'selected' : ''}>${opt}</option>`;
    });
    html += `<option value="__custom__" ${(!found && selectedVal) ? 'selected' : ''}>+ Input Tanggal Lainnya...</option>`;
    return html;
}

function onArrivalChange(selectEl, customInputId) {
    const customInp = document.getElementById(customInputId);
    if (!customInp) return;
    if (selectEl.value === '__custom__') {
        customInp.classList.remove('hidden');
        customInp.required = true;
        customInp.focus();
    } else {
        customInp.classList.add('hidden');
        customInp.required = false;
        customInp.value = '';
    }
}

function onDepartureChange(selectEl, customInputId) {
    const customInp = document.getElementById(customInputId);
    if (!customInp) return;
    if (selectEl.value === '__custom__') {
        customInp.classList.remove('hidden');
        customInp.required = true;
        customInp.focus();
    } else {
        customInp.classList.add('hidden');
        customInp.required = false;
        customInp.value = '';
    }
}

function togglePickupDetails(chk, targetId) {
    const box = document.getElementById(targetId);
    if (!box) return;
    if (chk.checked) {
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
    }
}

// Simpan state saat berpindah atau load
function saveToSession() {
    sessionStorage.setItem('rakernas_reg_state', JSON.stringify(regState));
}

// 1. Pemilihan Kategori di Step 1
function selectCategory(cat) {
    regState.kategori = cat;
    document.getElementById('input_kategori').value = cat;

    // Reset visual selection
    document.querySelectorAll('.category-card').forEach(card => {
        card.classList.remove('border-emerald-600', 'bg-emerald-50/50', 'ring-2', 'ring-emerald-500');
    });

    const activeRadio = document.querySelector(`input[name="temp_kategori"][value="${cat}"]`);
    if (activeRadio) {
        activeRadio.checked = true;
        activeRadio.closest('.category-card').classList.add('border-emerald-600', 'bg-emerald-50/50', 'ring-2', 'ring-emerald-500');
    }

    const originContainer = document.getElementById('origin-selection-container');
    const boxPW           = document.getElementById('box-pw-dropdown');
    const boxPD           = document.getElementById('box-pd-dropdown');
    const quotaBadge      = document.getElementById('quota-info-badge');
    const quotaText       = document.getElementById('quota-info-text');

    if (cat === 'PP' || cat === 'Tamu Undangan') {
        originContainer.classList.add('hidden');
        regState.pw_id = '';
        regState.pw_name = '';
        regState.daerah_id = '';
        regState.daerah_name = '';
        regState.quota = 999;
    } else if (cat === 'PW') {
        originContainer.classList.remove('hidden');
        boxPW.classList.remove('hidden');
        boxPD.classList.add('hidden');
        quotaBadge.classList.remove('hidden');
        quotaText.innerHTML = "<strong>Ketentuan Kuota PW:</strong> Maksimal 10 orang delegasi per Pengurus Wilayah.";
        regState.quota = 10;
    } else if (cat === 'PD') {
        originContainer.classList.remove('hidden');
        boxPW.classList.remove('hidden');
        boxPD.classList.remove('hidden');
        quotaBadge.classList.remove('hidden');
        quotaText.innerHTML = "<strong>Ketentuan Kuota PD:</strong> Maksimal 5 orang delegasi per Pengurus Daerah.";
        regState.quota = 5;
    }
}

// 2. Event saat dropdown Wilayah dipilih
function onWilayahChange(pwId) {
    regState.pw_id = pwId;
    document.getElementById('input_pw_id').value = pwId;

    const selectW = document.getElementById('select_wilayah');
    const opt = selectW.options[selectW.selectedIndex];
    regState.pw_name = opt ? opt.getAttribute('data-name') : '';

    if (regState.kategori === 'PD') {
        loadDaerahDropdown(pwId);
    }
}

// 3. Load Dropdown Daerah secara dependent via AJAX
function loadDaerahDropdown(pwId) {
    const selectD = document.getElementById('select_daerah');
    const loader  = document.getElementById('daerah-loading');

    if (!pwId) {
        selectD.innerHTML = '<option value="">-- Pilih Wilayah terlebih dahulu --</option>';
        return;
    }

    loader.classList.remove('hidden');
    selectD.innerHTML = '<option value="">Memuat daftar Pengurus Daerah...</option>';

    fetch(`<?= BASE_URL ?>api/get_daerah.php?pw_id=${pwId}`)
        .then(res => res.json())
        .then(data => {
            loader.classList.add('hidden');
            if (data.success && data.daerah.length > 0) {
                let html = '<option value="">-- Pilih Pengurus Daerah (PD) --</option>';
                data.daerah.forEach(d => {
                    html += `<option value="${d.id}" data-name="${d.name}">${d.name}</option>`;
                });
                selectD.innerHTML = html;
            } else {
                selectD.innerHTML = '<option value="">(Belum ada data PD di wilayah ini)</option>';
            }
        })
        .catch(err => {
            loader.classList.add('hidden');
            selectD.innerHTML = '<option value="">Gagal memuat daerah</option>';
        });
}

function onDaerahChange(dId) {
    regState.daerah_id = dId;
    document.getElementById('input_daerah_id').value = dId;

    const selectD = document.getElementById('select_daerah');
    const opt = selectD.options[selectD.selectedIndex];
    regState.daerah_name = opt ? opt.getAttribute('data-name') : '';
    document.getElementById('input_daerah_name').value = regState.daerah_name;
}

// 4. Navigasi ke Step 2
function goToStep2() {
    if (!regState.kategori) {
        alert('Silakan pilih salah satu kategori kepesertaan terlebih dahulu.');
        return;
    }

    if (regState.kategori === 'PW' && !regState.pw_id) {
        alert('Silakan pilih asal Pengurus Wilayah (PW).');
        return;
    }

    if (regState.kategori === 'PD') {
        if (!regState.pw_id) {
            alert('Silakan pilih asal Pengurus Wilayah (PW).');
            return;
        }
        if (!regState.daerah_id && !regState.daerah_name) {
            alert('Silakan pilih Pengurus Daerah (PD).');
            return;
        }
    }

    // Update banner di Step 2
    const bannerTitle = document.getElementById('banner-origin-title');
    const bannerSub   = document.getElementById('banner-origin-sub');

    if (regState.kategori === 'PP') {
        bannerTitle.innerText = "Pengurus Pusat (PP) Al Khidmah";
        bannerSub.innerText = "Kategori: Pengurus Pusat • Kuota: Tidak Terbatas";
    } else if (regState.kategori === 'Tamu Undangan') {
        bannerTitle.innerText = "Tamu Undangan Khusus";
        bannerSub.innerText = "Kategori: Tamu Undangan • Bebas Biaya Iuran Akomodasi";
    } else if (regState.kategori === 'PW') {
        bannerTitle.innerText = regState.pw_name;
        bannerSub.innerText = "Kategori: Pengurus Wilayah (PW) • Batas Kuota: Maks. 10 Orang";
    } else if (regState.kategori === 'PD') {
        bannerTitle.innerText = `${regState.daerah_name} (${regState.pw_name})`;
        bannerSub.innerText = "Kategori: Pengurus Daerah (PD) • Batas Kuota: Maks. 5 Orang";
    }

    // Update Stepper Nav
    document.getElementById('step-nav-1').classList.replace('text-emerald-800', 'text-slate-400');
    document.getElementById('step-nav-1').querySelector('div').className = 'w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-black shadow-sm';
    document.getElementById('step-nav-2').classList.replace('text-slate-400', 'text-emerald-800');
    document.getElementById('step-nav-2').querySelector('div').className = 'w-8 h-8 rounded-full bg-emerald-700 text-white flex items-center justify-center text-xs font-black shadow-sm';

    // Show Step 2
    document.getElementById('step-1').classList.add('hidden');
    document.getElementById('step-2').classList.remove('hidden');
    document.getElementById('step-3').classList.add('hidden');

    // Inisialisasi card pertama (Ketua Rombongan) jika belum ada
    if (document.querySelectorAll('.participant-card').length === 0) {
        addLeaderCard();
    }

    // Load Tab Peserta Terdaftar
    loadRegisteredParticipants();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Navigasi Kembali ke Step 1
function goToStep1() {
    document.getElementById('step-nav-2').classList.replace('text-emerald-800', 'text-slate-400');
    document.getElementById('step-nav-2').querySelector('div').className = 'w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-black';
    document.getElementById('step-nav-1').classList.replace('text-slate-400', 'text-emerald-800');
    document.getElementById('step-nav-1').querySelector('div').className = 'w-8 h-8 rounded-full bg-emerald-700 text-white flex items-center justify-center text-xs font-black shadow-sm';

    document.getElementById('step-2').classList.add('hidden');
    document.getElementById('step-1').classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// 5. Render Card Peserta 1 (Ketua Rombongan)
function addLeaderCard() {
    const container = document.getElementById('participants-container');
    const isGuest = (regState.kategori === 'Tamu Undangan');

    const cardHtml = `
    <div class="participant-card bg-white rounded-3xl border-2 border-emerald-500/80 p-6 sm:p-8 shadow-sm space-y-6" data-index="0">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-full bg-emerald-700 text-white flex items-center justify-center text-xs font-black">1</span>
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900">${isGuest ? 'Identitas Tamu Undangan' : 'Ketua Rombongan / Peserta Utama'}</h3>
                    <p class="text-[11px] text-slate-400">Data identitas & jadwal perjalanan ketua rombongan</p>
                </div>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                ${isGuest ? 'Tamu Undangan' : 'Ketua Rombongan'}
            </span>
        </div>

        <!-- Field Identitas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Lengkap (beserta gelar jika ada) *</label>
                <input type="text" name="nama_peserta[]" required placeholder="Contoh: H. M. Sholihin, S.Pd.I" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Jenis Kelamin *</label>
                <select name="gender_peserta[]" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    <option value="L">Laki-laki (Ikhwan)</option>
                    <option value="P">Perempuan (Akhwat)</option>
                </select>
            </div>

            ${!isGuest ? `
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Unsur Organisasi *</label>
                <select name="unsur_peserta[]" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    <option value="Al Khidmah">Al Khidmah</option>
                    <option value="Ath Thoriqoh">Ath Thoriqoh</option>
                    <option value="UCC">UCC</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Jabatan *</label>
                <input type="text" name="jabatan_peserta[]" required placeholder="Contoh: Ketua / Sekretaris" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Komisi / Bidang *</label>
                <select name="komisi_peserta[]" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    <option value="Bidang Organisasi">Bidang Organisasi</option>
                    <option value="Bidang Kemajlisan & Kethoriqohan">Bidang Kemajlisan & Kethoriqohan</option>
                    <option value="Bidang Usaha Ekonomi">Bidang Usaha Ekonomi</option>
                    <option value="Bidang Kepemudaan">Bidang Kepemudaan</option>
                    <option value="Bidang IT & Multimedia">Bidang IT & Multimedia</option>
                </select>
            </div>
            ` : ''}

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nomor WhatsApp Aktif *</label>
                <input type="text" name="phone_peserta[]" required placeholder="08xxxxxxxxxx" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
            </div>

            <div class="${isGuest ? 'sm:col-span-2' : ''}">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Upload Foto Peserta</label>
                <input type="file" name="foto_peserta[]" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-800 hover:file:bg-emerald-100">
                <span class="text-[10px] text-slate-400 mt-0.5 block">Kopiah & baju putih (ikhwan) / jilbab putih (akhwat). Maks 5MB.</span>
            </div>
        </div>

        <!-- Bagian Informasi Kedatangan & Kepulangan Ketua -->
        <div class="pt-4 border-t border-slate-100 space-y-3">
            <h4 class="text-xs font-extrabold text-slate-800 uppercase tracking-wide">Informasi Perjalanan & Kedatangan</h4>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Hari Kedatangan *</label>
                    <select name="arrival_day[]" required onchange="onArrivalChange(this, 'leader-arrival-custom')" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                        ${buildArrivalSelectOptions()}
                    </select>
                    <input type="text" name="arrival_day_custom[]" id="leader-arrival-custom" placeholder="Ketik tanggal kedatangan..." class="hidden mt-1.5 w-full px-3 py-1.5 rounded-lg border border-emerald-400 bg-emerald-50/50 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Estimasi Jam Tiba *</label>
                    <input type="time" name="arrival_time[]" value="13:00" required class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Hari Kepulangan *</label>
                    <select name="departure_day[]" required onchange="onDepartureChange(this, 'leader-departure-custom')" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                        ${buildDepartureSelectOptions()}
                    </select>
                    <input type="text" name="departure_day_custom[]" id="leader-departure-custom" placeholder="Ketik tanggal kepulangan..." class="hidden mt-1.5 w-full px-3 py-1.5 rounded-lg border border-emerald-400 bg-emerald-50/50 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Estimasi Jam Pulang *</label>
                    <input type="time" name="departure_time[]" value="11:00" required class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Transportasi yang Digunakan *</label>
                    <select name="transport_mode[]" required class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                        <option value="Kendaraan Pribadi">Kendaraan Pribadi / Elf Rombongan</option>
                        <option value="Kereta Api">Kereta Api</option>
                        <option value="Pesawat">Pesawat Udara</option>
                        <option value="Bus Umum">Bus Umum</option>
                        <option value="Kapal Laut">Kapal Laut</option>
                    </select>
                </div>

                <div class="flex items-center pt-5">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                        <input type="checkbox" name="need_pickup[]" value="1" onchange="togglePickupDetails(this, 'leader-pickup-box')" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                        <span>Perlu Penjemputan / Pengantaran oleh Panitia di Stasiun / Bandara</span>
                    </label>
                </div>
            </div>

            <!-- Detail Titik & Waktu Penjemputan Ketua / Tamu -->
            <div id="leader-pickup-box" class="hidden p-4 sm:p-5 bg-emerald-50/70 border border-emerald-300 rounded-2xl space-y-3.5 transition-all">
                <div class="flex items-center gap-2 text-xs font-extrabold text-emerald-950 border-b border-emerald-200/80 pb-2">
                    <svg class="w-4 h-4 text-emerald-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Informasi Titik Lokasi & Waktu Penjemputan (Panitia Transportasi)</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">
                            Titik / Tempat Penjemputan *
                        </label>
                        <input type="text" name="pickup_location[]" list="list-lokasi-jemput" placeholder="Ketik atau pilih (cth: Stasiun Gubeng / Bandara Juanda T1)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                        <span class="text-[10px] text-slate-500 mt-1 block">Bisa memilih dari daftar atau ketik nama lokasi/stasiun kedatangan.</span>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">
                            Waktu / Jam Penjemputan yang Dikehendaki *
                        </label>
                        <input type="text" name="pickup_time_req[]" placeholder="Contoh: pk 13:30 WIB (sesuai jam tiba kereta)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                        <span class="text-[10px] text-slate-500 mt-1 block">Waktu/jam kesiapan rombongan untuk dijemput.</span>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">
                            Catatan Tambahan untuk Panitia Transportasi
                        </label>
                        <textarea name="pickup_notes[]" rows="2" placeholder="Contoh: Kereta Sancaka No KA 88 (Gerbong 4), membawa 3 koper besar, kontak koordinator lapangan 0812-3456-7890" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white resize-none"></textarea>
                        <p class="text-[10px] text-slate-400 mt-1">Sampaikan nomor penerbangan/kereta, ciri rombongan, atau nomor HP koordinator penjemputan.</p>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
    `;

    container.innerHTML = cardHtml;
}

// 6. Tambah Peserta Rombongan (Companion)
function addCompanionCard() {
    const cards = document.querySelectorAll('.participant-card');
    const currentCount = cards.length;

    // Cek batas kuota
    if (currentCount >= regState.quota) {
        alert(`Batas kuota maksimal untuk kategori ${regState.kategori} adalah ${regState.quota} orang.`);
        return;
    }

    const container = document.getElementById('participants-container');
    const newIdx    = currentCount;
    const isGuest   = (regState.kategori === 'Tamu Undangan');

    const cardDiv = document.createElement('div');
    cardDiv.className = "participant-card bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6";
    cardDiv.setAttribute('data-index', newIdx);

    cardDiv.innerHTML = `
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-full bg-slate-700 text-white flex items-center justify-center text-xs font-black">${newIdx + 1}</span>
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900">Peserta Rombongan #${newIdx + 1}</h3>
                    <p class="text-[11px] text-slate-400">Data identitas mandiri peserta rombongan</p>
                </div>
            </div>
            <button type="button" onclick="removeCompanionCard(this)" class="px-2.5 py-1 text-rose-600 hover:bg-rose-50 rounded-lg text-xs font-bold transition flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span>Hapus</span>
            </button>
        </div>

        <!-- Field Identitas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Lengkap *</label>
                <input type="text" name="nama_peserta[]" required placeholder="Nama lengkap peserta" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Jenis Kelamin *</label>
                <select name="gender_peserta[]" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    <option value="L">Laki-laki (Ikhwan)</option>
                    <option value="P">Perempuan (Akhwat)</option>
                </select>
            </div>

            ${!isGuest ? `
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Unsur Organisasi *</label>
                <select name="unsur_peserta[]" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    <option value="Al Khidmah">Al Khidmah</option>
                    <option value="Ath Thoriqoh">Ath Thoriqoh</option>
                    <option value="UCC">UCC</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Jabatan *</label>
                <input type="text" name="jabatan_peserta[]" required placeholder="Contoh: Pengurus / Anggota" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Komisi / Bidang *</label>
                <select name="komisi_peserta[]" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    <option value="Bidang Organisasi">Bidang Organisasi</option>
                    <option value="Bidang Kemajlisan & Kethoriqohan">Bidang Kemajlisan & Kethoriqohan</option>
                    <option value="Bidang Usaha Ekonomi">Bidang Usaha Ekonomi</option>
                    <option value="Bidang Kepemudaan">Bidang Kepemudaan</option>
                    <option value="Bidang IT & Multimedia">Bidang IT & Multimedia</option>
                </select>
            </div>
            ` : ''}

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nomor WhatsApp *</label>
                <input type="text" name="phone_peserta[]" required placeholder="08xxxxxxxxxx" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
            </div>

            <div class="${isGuest ? 'sm:col-span-2' : ''}">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Upload Foto</label>
                <input type="file" name="foto_peserta[]" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-800">
            </div>
        </div>

        <!-- Checkbox: Sama dengan Ketua Rombongan -->
        <div class="pt-4 border-t border-slate-100 space-y-3">
            <div class="bg-amber-50/70 border border-amber-200/80 rounded-2xl p-4 flex items-center justify-between">
                <label class="inline-flex items-center gap-2.5 cursor-pointer text-xs font-extrabold text-amber-950">
                    <input type="checkbox" name="same_as_leader[${newIdx}]" value="1" checked onchange="toggleCompanionTravel(this, ${newIdx})" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                    <span>Sama dengan Ketua Rombongan</span>
                </label>
                <span class="text-[10px] text-amber-800 hidden sm:inline">Otomatis menyalin jadwal kedatangan & transportasi ketua</span>
            </div>

            <!-- Bagian Travel Peserta Rombongan (Default Hidden jika checked) -->
            <div id="companion-travel-${newIdx}" class="hidden space-y-3 pt-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Hari Kedatangan</label>
                        <select name="arrival_day[]" onchange="onArrivalChange(this, 'comp-arrival-custom-${newIdx}')" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs bg-white">
                            ${buildArrivalSelectOptions()}
                        </select>
                        <input type="text" name="arrival_day_custom[]" id="comp-arrival-custom-${newIdx}" placeholder="Ketik tanggal..." class="hidden mt-1.5 w-full px-3 py-1.5 rounded-lg border border-emerald-400 bg-emerald-50/50 text-xs focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Estimasi Jam Tiba</label>
                        <input type="time" name="arrival_time[]" value="13:00" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Hari Kepulangan</label>
                        <select name="departure_day[]" onchange="onDepartureChange(this, 'comp-departure-custom-${newIdx}')" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs bg-white">
                            ${buildDepartureSelectOptions()}
                        </select>
                        <input type="text" name="departure_day_custom[]" id="comp-departure-custom-${newIdx}" placeholder="Ketik tanggal..." class="hidden mt-1.5 w-full px-3 py-1.5 rounded-lg border border-emerald-400 bg-emerald-50/50 text-xs focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Estimasi Jam Pulang</label>
                        <input type="time" name="departure_time[]" value="11:00" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Transportasi yang Digunakan</label>
                        <select name="transport_mode[]" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs">
                            <option value="Kendaraan Pribadi">Kendaraan Pribadi / Elf Rombongan</option>
                            <option value="Kereta Api">Kereta Api</option>
                            <option value="Pesawat">Pesawat Udara</option>
                            <option value="Bus Umum">Bus Umum</option>
                            <option value="Kapal Laut">Kapal Laut</option>
                        </select>
                    </div>

                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                            <input type="checkbox" name="need_pickup[]" value="1" onchange="togglePickupDetails(this, 'comp-pickup-box-${newIdx}')" class="w-4 h-4 text-emerald-600 rounded">
                            <span>Perlu Penjemputan / Pengantaran oleh Panitia di Stasiun / Bandara</span>
                        </label>
                    </div>
                </div>

                <!-- Form Detail Penjemputan Peserta Rombongan -->
                <div id="comp-pickup-box-${newIdx}" class="hidden p-4 sm:p-5 bg-emerald-50/70 border border-emerald-300 rounded-2xl space-y-3.5 transition-all">
                    <div class="flex items-center gap-2 text-xs font-extrabold text-emerald-950 border-b border-emerald-200/80 pb-2">
                        <svg class="w-4 h-4 text-emerald-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Informasi Titik Lokasi & Waktu Penjemputan Peserta #${newIdx + 1}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Titik / Tempat Penjemputan</label>
                            <input type="text" name="pickup_location[]" list="list-lokasi-jemput" placeholder="Ketik atau pilih lokasi..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs bg-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Waktu / Jam Penjemputan</label>
                            <input type="text" name="pickup_time_req[]" placeholder="Contoh: pk 13:30 WIB" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs bg-white">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Catatan Tambahan untuk Panitia Transportasi</label>
                            <textarea name="pickup_notes[]" rows="2" placeholder="Catatan armada, no tiket/kereta, dll..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs bg-white resize-none"></textarea>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    `;

    container.appendChild(cardDiv);

    // Disable tombol tambah jika kuota tercapai
    if (document.querySelectorAll('.participant-card').length >= regState.quota) {
        document.getElementById('btn-add-companion').classList.add('opacity-50', 'cursor-not-allowed');
    }
}

function removeCompanionCard(btn) {
    btn.closest('.participant-card').remove();
    document.getElementById('btn-add-companion').classList.remove('opacity-50', 'cursor-not-allowed');
}

function toggleCompanionTravel(chk, idx) {
    const travelBox = document.getElementById(`companion-travel-${idx}`);
    if (travelBox) {
        if (chk.checked) {
            travelBox.classList.add('hidden');
        } else {
            travelBox.classList.remove('hidden');
        }
    }
}

// 7. Tab Switching antara Form dan Data Peserta Terdaftar
function switchFormTab(tab) {
    const btnForm = document.getElementById('tab-btn-form');
    const btnReg  = document.getElementById('tab-btn-registered');
    const cntForm = document.getElementById('content-tab-form');
    const cntReg  = document.getElementById('content-tab-registered');

    if (tab === 'form') {
        btnForm.className = "pb-3 border-b-2 border-emerald-600 font-extrabold text-xs text-emerald-900 flex items-center gap-2";
        btnReg.className  = "pb-3 border-b-2 border-transparent font-bold text-xs text-slate-500 hover:text-slate-800 flex items-center gap-2";
        cntForm.classList.remove('hidden');
        cntReg.classList.add('hidden');
    } else {
        btnReg.className  = "pb-3 border-b-2 border-emerald-600 font-extrabold text-xs text-emerald-900 flex items-center gap-2";
        btnForm.className = "pb-3 border-b-2 border-transparent font-bold text-xs text-slate-500 hover:text-slate-800 flex items-center gap-2";
        cntForm.classList.add('hidden');
        cntReg.classList.remove('hidden');
        loadRegisteredParticipants();
    }
}

// Load peserta terdaftar via AJAX berdasarkan kategori & asal
function loadRegisteredParticipants() {
    const tableWrapper = document.getElementById('registered-table-wrapper');
    const tabLabel     = document.getElementById('tab-registered-label');
    const heading      = document.getElementById('registered-list-heading');

    let query = `kategori=${encodeURIComponent(regState.kategori)}`;
    if (regState.pw_id) query += `&pw_id=${regState.pw_id}`;
    if (regState.daerah_id) query += `&daerah_id=${regState.daerah_id}`;
    if (regState.daerah_name) query += `&daerah_name=${encodeURIComponent(regState.daerah_name)}`;

    heading.innerText = `Data Peserta Terdaftar - ${regState.daerah_name || regState.pw_name || regState.kategori}`;

    fetch(`<?= BASE_URL ?>api/get_registered_participants.php?${query}`)
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                tabLabel.innerText = `Data Peserta Terdaftar (${data.count || 0})`;

                if (!data.participants || data.participants.length === 0) {
                    tableWrapper.innerHTML = `
                        <div class="p-8 text-center text-xs text-slate-400">
                            Belum ada peserta yang terdaftar untuk kepengurusan ini.
                        </div>
                    `;
                } else {
                    let html = `
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-bold">
                            <tr>
                                <th class="py-2.5 px-3 text-center w-8">No</th>
                                <th class="py-2.5 px-3">Kode</th>
                                <th class="py-2.5 px-4">Nama Peserta</th>
                                <th class="py-2.5 px-3 text-center">L/P</th>
                                <th class="py-2.5 px-4">Jabatan</th>
                                <th class="py-2.5 px-4">Komisi</th>
                                <th class="py-2.5 px-3 text-right">Terdaftar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                    `;

                    data.participants.forEach((p, i) => {
                        html += `
                        <tr class="hover:bg-slate-50">
                            <td class="py-2.5 px-3 text-center text-slate-400 font-bold">${i + 1}</td>
                            <td class="py-2.5 px-3 font-mono text-[11px] font-bold text-emerald-800">${p.registration_code}</td>
                            <td class="py-2.5 px-4 font-bold text-slate-900">${p.full_name}</td>
                            <td class="py-2.5 px-3 text-center font-bold">${p.gender === 'Perempuan' ? 'P' : 'L'}</td>
                            <td class="py-2.5 px-4">${p.jabatan}</td>
                            <td class="py-2.5 px-4 text-slate-600">${p.komisi}</td>
                            <td class="py-2.5 px-3 text-right text-[10px] text-slate-400 font-mono">${p.created_at}</td>
                        </tr>
                        `;
                    });

                    html += `</tbody></table>`;
                    tableWrapper.innerHTML = html;
                }
            } else {
                tableWrapper.innerHTML = `<div class="p-4 text-xs text-rose-500">Gagal memuat: ${data ? (data.message || 'Terjadi kesalahan') : 'Respons kosong'}</div>`;
            }
        })
        .catch(err => {
            tableWrapper.innerHTML = `<div class="p-4 text-xs text-rose-500">Gagal memuat data peserta terdaftar. Silakan klik tombol <strong>Refresh</strong> di atas.</div>`;
        });
}

// 8. Navigasi ke Step 3 (Resume Pendaftaran)
function goToStep3() {
    // Validasi form HTML5
    const form = document.getElementById('wizard-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const cards = document.querySelectorAll('.participant-card');
    if (cards.length === 0) {
        alert('Minimal harus ada 1 peserta yang didaftarkan.');
        return;
    }

    const isGuest = (regState.kategori === 'Tamu Undangan');

    // Populate Box Header Resume
    document.getElementById('resume-kategori').innerText = regState.kategori;
    document.getElementById('resume-origin').innerText = regState.daerah_name ? `${regState.daerah_name} (${regState.pw_name})` : (regState.pw_name || regState.kategori);
    document.getElementById('resume-total-count').innerText = `${cards.length} Orang`;

    const totalFee = isGuest ? 0 : (cards.length * FEE_PER_PERSON);
    document.getElementById('resume-total-fee').innerText = isGuest ? 'Rp 0 (Bebas Iuran)' : `Rp ${totalFee.toLocaleString('id-ID')}`;

    // Header Tabel Resume
    const thUnsur = document.getElementById('resume-th-unsur');
    const thKomisi= document.getElementById('resume-th-komisi');
    if (isGuest) {
        thUnsur.classList.add('hidden');
        thKomisi.classList.add('hidden');
    } else {
        thUnsur.classList.remove('hidden');
        thKomisi.classList.remove('hidden');
    }

    // Populate Rows Resume Table
    const tbody = document.getElementById('resume-table-body');
    tbody.innerHTML = '';

    // Ambil travel ketua rombongan (card 0)
    let leaderArrDay   = cards[0].querySelector('select[name="arrival_day[]"]')?.value || '-';
    if (leaderArrDay === '__custom__') {
        leaderArrDay = cards[0].querySelector('input[name="arrival_day_custom[]"]')?.value || 'Tanggal Khusus';
    }
    const leaderArrTime  = cards[0].querySelector('input[name="arrival_time[]"]')?.value || '-';
    const leaderTrans    = cards[0].querySelector('select[name="transport_mode[]"]')?.value || '-';
    const leaderPickup   = cards[0].querySelector('input[name="need_pickup[]"]')?.checked || false;
    const leaderPickupLoc= leaderPickup ? (cards[0].querySelector('input[name="pickup_location[]"]')?.value || '') : '';
    const leaderPickupTime= leaderPickup ? (cards[0].querySelector('input[name="pickup_time_req[]"]')?.value || '') : '';
    const leaderPickupNotes= leaderPickup ? (cards[0].querySelector('textarea[name="pickup_notes[]"]')?.value || '') : '';

    cards.forEach((card, idx) => {
        const nama    = card.querySelector('input[name="nama_peserta[]"]')?.value || '-';
        const phone   = card.querySelector('input[name="phone_peserta[]"]')?.value || '-';
        const gender  = card.querySelector('select[name="gender_peserta[]"]')?.value || 'L';
        const unsur   = card.querySelector('select[name="unsur_peserta[]"]')?.value || '-';
        const jabatan = card.querySelector('input[name="jabatan_peserta[]"]')?.value || '-';
        const komisi  = card.querySelector('select[name="komisi_peserta[]"]')?.value || '-';

        let arrDay     = leaderArrDay;
        let arrTime    = leaderArrTime;
        let trans      = leaderTrans;
        let needPickup = leaderPickup;
        let pLoc       = leaderPickupLoc;
        let pTime      = leaderPickupTime;
        let pNotes     = leaderPickupNotes;

        const sameChk = card.querySelector(`input[name="same_as_leader[${idx}]"]`);
        if (sameChk && !sameChk.checked) {
            arrDay  = card.querySelector('select[name="arrival_day[]"]')?.value || leaderArrDay;
            if (arrDay === '__custom__') {
                arrDay = card.querySelector('input[name="arrival_day_custom[]"]')?.value || 'Tanggal Khusus';
            }
            arrTime    = card.querySelector('input[name="arrival_time[]"]')?.value || leaderArrTime;
            trans      = card.querySelector('select[name="transport_mode[]"]')?.value || leaderTrans;
            needPickup = card.querySelector('input[name="need_pickup[]"]')?.checked || false;
            pLoc       = needPickup ? (card.querySelector('input[name="pickup_location[]"]')?.value || '') : '';
            pTime      = needPickup ? (card.querySelector('input[name="pickup_time_req[]"]')?.value || '') : '';
            pNotes     = needPickup ? (card.querySelector('textarea[name="pickup_notes[]"]')?.value || '') : '';
        }

        const tr = document.createElement('tr');
        tr.className = idx === 0 ? "bg-emerald-50/50 font-semibold" : "";
        tr.innerHTML = `
            <td class="py-3 px-3 text-center text-slate-400 font-bold">${idx + 1}</td>
            <td class="py-3 px-4">
                <div class="font-extrabold text-slate-900">${nama} ${idx === 0 ? '<span class="text-[9px] bg-emerald-200 text-emerald-900 px-1.5 py-0.2 rounded font-black ml-1">KETUA</span>' : ''}</div>
                <div class="text-[10px] text-slate-400 font-mono">${phone}</div>
            </td>
            <td class="py-3 px-3 text-center font-bold">${gender}</td>
            ${!isGuest ? `<td class="py-3 px-4">${jabatan} <span class="text-slate-400">(${unsur})</span></td>` : ''}
            ${!isGuest ? `<td class="py-3 px-4 text-slate-600">${komisi}</td>` : ''}
            <td class="py-3 px-4 text-xs">
                <div>${arrDay} <span class="font-mono text-slate-500">${arrTime}</span></div>
                ${needPickup ? `
                    <div class="mt-1 bg-amber-50 border border-amber-200/90 rounded-lg p-1.5 text-[10px] text-amber-950">
                        <div class="font-bold text-amber-900">🚐 Jemput: ${pLoc || 'Stasiun/Bandara'}</div>
                        ${pTime ? `<div class="text-[9px] text-slate-600">Jam: ${pTime}</div>` : ''}
                        ${pNotes ? `<div class="text-[9px] text-slate-500 italic mt-0.5">${pNotes}</div>` : ''}
                    </div>
                ` : ''}
            </td>
            <td class="py-3 px-3 text-center text-xs">
                <div>${trans}</div>
                ${needPickup ? `<span class="inline-block mt-0.5 text-[9px] bg-emerald-100 text-emerald-800 px-1.5 py-0.2 rounded font-bold">Perlu Jemputan</span>` : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Update Stepper Nav
    document.getElementById('step-nav-2').classList.replace('text-emerald-800', 'text-slate-400');
    document.getElementById('step-nav-2').querySelector('div').className = 'w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-black shadow-sm';
    document.getElementById('step-nav-3').classList.replace('text-slate-400', 'text-emerald-800');
    document.getElementById('step-nav-3').querySelector('div').className = 'w-8 h-8 rounded-full bg-emerald-700 text-white flex items-center justify-center text-xs font-black shadow-sm';

    // Show Step 3
    document.getElementById('step-2').classList.add('hidden');
    document.getElementById('step-3').classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
