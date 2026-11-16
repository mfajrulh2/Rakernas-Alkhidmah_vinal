<?php
/**
 * Form Edit Data Delegasi (Admin Pusat)
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['superadmin', 'petugas_scan']);

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmtP = $pdo->prepare("
    SELECT p.*, w.name as pw_name, d.name as db_daerah_name 
    FROM participants p
    LEFT JOIN wilayah w ON p.pw_id = w.id
    LEFT JOIN daerah d ON p.daerah_id = d.id
    WHERE p.id = ?
");
$stmtP->execute([$id]);
$p = $stmtP->fetch();

if (!$p) {
    setFlash('danger', 'Data peserta tidak ditemukan.');
    header("Location: " . BASE_URL . "admin/participants.php");
    exit;
}

$unsurOptions  = getUnsurOrganisasiOptions();
$komisiOptions = getKomisiOptions();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name'] ?? '');
    $gender           = trim($_POST['gender'] ?? 'L');
    $kategori         = trim($_POST['kategori'] ?? 'PW');
    $unsur_organisasi = trim($_POST['unsur_organisasi'] ?? 'Al Khidmah');
    $pw_id            = !empty($_POST['pw_id']) ? (int)$_POST['pw_id'] : null;
    $daerah_id        = !empty($_POST['daerah_id']) ? (int)$_POST['daerah_id'] : null;
    $pd_name          = trim($_POST['pd_name'] ?? '');
    $jabatan          = trim($_POST['jabatan_organisasi'] ?? '');
    $komisi           = trim($_POST['komisi'] ?? $komisiOptions[0]);
    $phone            = trim($_POST['phone'] ?? '');
    $nik              = trim($_POST['nik'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $type             = trim($_POST['participant_type'] ?? 'utusan');
    $room_id          = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $arrival_day      = trim($_POST['arrival_day'] ?? '');
    $arrival_time     = trim($_POST['arrival_time'] ?? '');
    $departure_day    = trim($_POST['departure_day'] ?? '');
    $departure_time   = trim($_POST['departure_time'] ?? '');
    $transport_mode   = trim($_POST['transport_mode'] ?? '');
    $need_pickup      = !empty($_POST['need_pickup']) ? 1 : 0;
    $pickup_location  = trim($_POST['pickup_location'] ?? '');
    $pickup_time      = trim($_POST['pickup_time'] ?? '');
    $pickup_notes     = trim($_POST['pickup_notes'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');
    $status_verif     = trim($_POST['status_verifikasi'] ?? 'verified');

    if (empty($full_name)) $errors[] = "Nama lengkap wajib diisi.";
    if (empty($phone)) $errors[] = "Nomor WhatsApp wajib diisi.";
    if ($kategori === 'PW' && empty($pw_id)) {
        $errors[] = "Pengurus Wilayah (PW) wajib dipilih.";
    }
    if ($kategori === 'PD' && empty($pw_id)) {
        $errors[] = "Wilayah induk wajib dipilih untuk Pengurus Daerah.";
    }

    $photoPath = !empty($p['photo']) ? $p['photo'] : (!empty($p['photo_file']) ? $p['photo_file'] : null);
    // Cek jika ada upload foto baru
    if (isset($_FILES['photo_file']) && !empty($_FILES['photo_file']['name'])) {
        $photoUpload = uploadParticipantPhoto($_FILES['photo_file']);
        if (!empty($photoUpload['error'])) {
            $errors[] = $photoUpload['error'];
        } else {
            $photoPath = $photoUpload['path'];
        }
    }

    if (empty($errors)) {
        // Ambil struktur kolom participants dinamis
        $cols = $pdo->query("SHOW COLUMNS FROM `participants`")->fetchAll(PDO::FETCH_COLUMN);
        $colSet = array_flip($cols);

        $updates = [];
        $values  = [];

        $fieldMap = [
            'full_name'          => $full_name,
            'gender'             => $gender,
            'kategori'           => $kategori,
            'unsur_organisasi'   => $unsur_organisasi,
            'phone'              => $phone,
            'nik'                => $nik,
            'email'              => $email,
            'pw_id'              => $pw_id,
            'daerah_id'          => $daerah_id,
            'pd_name'            => $pd_name,
            'jabatan_organisasi' => $jabatan,
            'participant_type'   => $type,
            'komisi'             => $komisi,
            'room_id'            => $room_id,
            'arrival_day'        => $arrival_day,
            'arrival_time'       => $arrival_time,
            'departure_day'      => $departure_day,
            'departure_time'     => $departure_time,
            'transport_mode'     => $transport_mode,
            'need_pickup'        => $need_pickup,
            'pickup_location'    => $pickup_location,
            'pickup_time'        => $pickup_time,
            'pickup_notes'       => $pickup_notes,
            'status_verifikasi'  => $status_verif,
            'notes'              => $notes
        ];

        foreach ($fieldMap as $f => $val) {
            if (isset($colSet[$f])) {
                $updates[] = "`{$f}` = ?";
                $values[]  = $val;
            }
        }

        if (isset($colSet['photo'])) {
            $updates[] = "`photo` = ?";
            $values[]  = $photoPath;
        }
        if (isset($colSet['photo_file'])) {
            $updates[] = "`photo_file` = ?";
            $values[]  = $photoPath;
        }

        $values[] = $id;
        $sqlUp = "UPDATE `participants` SET " . implode(", ", $updates) . " WHERE `id` = ?";
        $stmtUp = $pdo->prepare($sqlUp);
        $stmtUp->execute($values);

        setFlash('success', "Data delegasi {$full_name} berhasil diperbarui.");
        header("Location: " . BASE_URL . "admin/participant_detail.php?id=" . $id);
        exit;
    }
}

// Ambil list Wilayah
$wilayahList = $pdo->query("SELECT id, name, code FROM wilayah ORDER BY name ASC")->fetchAll();

// Ambil list Daerah jika pw_id terisi
$currPwId = (int)($_POST['pw_id'] ?? ($p['pw_id'] ?? 0));
$daerahList = [];
if ($currPwId > 0) {
    try {
        $stmtD = $pdo->prepare("SELECT id, name, quota FROM daerah WHERE pw_id = ? ORDER BY name ASC");
        $stmtD->execute([$currPwId]);
        $daerahList = $stmtD->fetchAll();
    } catch (Exception $e) {}
}

// Ambil list Kamar
$roomList = $pdo->query("SELECT id, building_name, room_number, gender, capacity FROM rooms ORDER BY building_name ASC, room_number ASC")->fetchAll();

$currPhoto = !empty($p['photo']) ? $p['photo'] : (!empty($p['photo_file']) ? $p['photo_file'] : null);
$currKat = $_POST['kategori'] ?? ($p['kategori'] ?? ($p['participant_type'] === 'pengurus_pusat' ? 'PP' : ($p['participant_type'] === 'undangan' ? 'Tamu Undangan' : 'PW')));

$page_title = "Edit Delegasi - " . clean($p['full_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto mb-16 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="inline-flex items-center gap-1.5 font-mono text-xs font-bold text-slate-500 mb-1">
                Kode Registrasi: <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200"><?= clean($p['registration_code']) ?></span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Edit Data Peserta Delegasi</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>admin/participant_detail.php?id=<?= $p['id'] ?>" class="px-3.5 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Lihat Detail</span>
            </a>
            <a href="<?= BASE_URL ?>admin/participants.php" class="px-4 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                &larr; Kembali
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
            <?php foreach ($errors as $err): ?>
                <div>• <?= clean($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8">
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">

            <!-- 1. Kategori & Kepengurusan -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    Kategori Kepengurusan
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kategori Peserta *</label>
                        <select name="kategori" id="edit_kategori" onchange="toggleCategoryFields(this.value)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-emerald-900 focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                            <option value="PP" <?= $currKat === 'PP' ? 'selected' : '' ?>>Pengurus Pusat (PP)</option>
                            <option value="PW" <?= $currKat === 'PW' ? 'selected' : '' ?>>Pengurus Wilayah (PW)</option>
                            <option value="PD" <?= $currKat === 'PD' ? 'selected' : '' ?>>Pengurus Daerah (PD)</option>
                            <option value="Tamu Undangan" <?= $currKat === 'Tamu Undangan' ? 'selected' : '' ?>>Tamu Undangan Khusus</option>
                        </select>
                    </div>

                    <div id="box-pw" class="<?= in_array($currKat, ['PW', 'PD']) ? '' : 'hidden' ?>">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pengurus Wilayah (PW)</label>
                        <select name="pw_id" id="edit_pw_id" onchange="loadDistrictsForPW(this.value)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white font-medium">
                            <option value="">-- Pilih Wilayah --</option>
                            <?php foreach ($wilayahList as $w): ?>
                                <option value="<?= $w['id'] ?>" <?= $currPwId === (int)$w['id'] ? 'selected' : '' ?>><?= clean($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="box-pd" class="<?= ($currKat === 'PD') ? '' : 'hidden' ?>">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pengurus Daerah (PD)</label>
                        <select name="daerah_id" id="edit_daerah_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white font-medium">
                            <option value="">-- Pilih Daerah --</option>
                            <?php 
                            $currDaerahId = (int)($_POST['daerah_id'] ?? ($p['daerah_id'] ?? 0));
                            foreach ($daerahList as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $currDaerahId === (int)$d['id'] ? 'selected' : '' ?>><?= clean($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="pd_name" id="edit_pd_name" value="<?= clean($_POST['pd_name'] ?? $p['pd_name']) ?>" placeholder="Atau nama daerah manual..." class="mt-1.5 w-full px-3 py-1.5 rounded-lg border border-slate-200 text-xs">
                    </div>
                </div>
            </div>

            <!-- 2. Identitas Delegasi -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    Identitas Pribadi Peserta
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap & Gelar *</label>
                        <input type="text" name="full_name" required value="<?= clean($_POST['full_name'] ?? $p['full_name']) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none font-bold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Jenis Kelamin *</label>
                        <select name="gender" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                            <?php $currGender = $_POST['gender'] ?? $p['gender']; ?>
                            <option value="L" <?= $currGender === 'L' ? 'selected' : '' ?>>Laki-laki (Ikhwan)</option>
                            <option value="P" <?= $currGender === 'P' ? 'selected' : '' ?>>Perempuan (Akhwat)</option>
                        </select>
                    </div>

                    <div id="box-unsur">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Unsur Organisasi</label>
                        <select name="unsur_organisasi" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white font-semibold text-emerald-800">
                            <?php $currUnsur = $_POST['unsur_organisasi'] ?? ($p['unsur_organisasi'] ?? 'Al Khidmah'); ?>
                            <?php foreach ($unsurOptions as $uo): ?>
                                <option value="<?= clean($uo) ?>" <?= $currUnsur === $uo ? 'selected' : '' ?>><?= clean($uo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp *</label>
                        <input type="text" name="phone" required value="<?= clean($_POST['phone'] ?? $p['phone']) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Jabatan</label>
                        <input type="text" name="jabatan_organisasi" value="<?= clean($_POST['jabatan_organisasi'] ?? $p['jabatan_organisasi']) ?>" placeholder="Contoh: Ketua / Pengurus" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Komisi Sidang</label>
                        <select name="komisi" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white font-semibold text-slate-800">
                            <?php $currKomisi = $_POST['komisi'] ?? $p['komisi']; ?>
                            <?php foreach ($komisiOptions as $ko): ?>
                                <option value="<?= clean($ko) ?>" <?= $currKomisi === $ko ? 'selected' : '' ?>><?= clean($ko) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Klasifikasi Delegasi</label>
                        <select name="participant_type" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                            <?php $currType = $_POST['participant_type'] ?? $p['participant_type']; ?>
                            <option value="utusan" <?= $currType === 'utusan' ? 'selected' : '' ?>>Utusan (Hak Suara & Bicara)</option>
                            <option value="peninjau" <?= $currType === 'peninjau' ? 'selected' : '' ?>>Peninjau (Hak Bicara)</option>
                            <option value="pengurus_pusat" <?= $currType === 'pengurus_pusat' ? 'selected' : '' ?>>Pengurus Pusat</option>
                            <option value="undangan" <?= $currType === 'undangan' ? 'selected' : '' ?>>Tamu Undangan Khusus</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">NIK (KTP)</label>
                        <input type="text" name="nik" value="<?= clean($_POST['nik'] ?? $p['nik']) ?>" placeholder="16 digit NIK" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <!-- Upload & Preview Foto -->
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Foto Peserta</label>
                        <div class="flex items-center gap-4">
                            <?php if (!empty($currPhoto) && file_exists(__DIR__ . '/../' . $currPhoto)): ?>
                                <div class="relative shrink-0">
                                    <img src="<?= BASE_URL . clean($currPhoto) ?>" alt="Foto" class="w-16 h-20 object-cover rounded-xl border-2 border-emerald-500 shadow-sm">
                                </div>
                            <?php else: ?>
                                <div class="w-16 h-20 rounded-xl bg-slate-100 border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 shrink-0">
                                    <span class="text-[9px]">No photo</span>
                                </div>
                            <?php endif; ?>
                            <div class="flex-grow">
                                <input type="file" name="photo_file" accept=".jpg,.jpeg,.png,.webp" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-semibold">
                                <span class="text-[11px] text-slate-400 mt-1 block">Format JPG/PNG/WebP, busana baju & kopiah putih. Maksimal 5 MB.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Informasi Perjalanan & Kedatangan -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    Informasi Kedatangan & Perjalanan
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Hari Kedatangan</label>
                        <select name="arrival_day" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                            <?php 
                            $currArrDay = $_POST['arrival_day'] ?? $p['arrival_day']; 
                            $arrOpts = getArrivalDayOptions();
                            if (!empty($currArrDay) && !in_array($currArrDay, $arrOpts)) {
                                $arrOpts[] = $currArrDay;
                            }
                            ?>
                            <?php foreach ($arrOpts as $ao): ?>
                                <option value="<?= clean($ao) ?>" <?= $currArrDay === $ao ? 'selected' : '' ?>><?= clean($ao) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Estimasi Jam Tiba</label>
                        <input type="time" name="arrival_time" value="<?= clean($_POST['arrival_time'] ?? ($p['arrival_time'] ?: '13:00')) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Hari Kepulangan</label>
                        <select name="departure_day" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                            <?php 
                            $currDepDay = $_POST['departure_day'] ?? $p['departure_day']; 
                            $depOpts = getDepartureDayOptions();
                            if (!empty($currDepDay) && !in_array($currDepDay, $depOpts)) {
                                $depOpts[] = $currDepDay;
                            }
                            ?>
                            <?php foreach ($depOpts as $do): ?>
                                <option value="<?= clean($do) ?>" <?= $currDepDay === $do ? 'selected' : '' ?>><?= clean($do) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Estimasi Jam Pulang</label>
                        <input type="time" name="departure_time" value="<?= clean($_POST['departure_time'] ?? ($p['departure_time'] ?: '11:00')) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Transportasi yang Digunakan</label>
                        <select name="transport_mode" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                            <?php $currTrans = $_POST['transport_mode'] ?? ($p['transport_mode'] ?: ($p['transport_type'] ?? 'Kendaraan Pribadi')); ?>
                            <option value="Kendaraan Pribadi" <?= $currTrans === 'Kendaraan Pribadi' ? 'selected' : '' ?>>Kendaraan Pribadi / Elf Rombongan</option>
                            <option value="Kereta Api" <?= $currTrans === 'Kereta Api' ? 'selected' : '' ?>>Kereta Api</option>
                            <option value="Pesawat" <?= $currTrans === 'Pesawat' ? 'selected' : '' ?>>Pesawat Udara</option>
                            <option value="Bus Umum" <?= $currTrans === 'Bus Umum' ? 'selected' : '' ?>>Bus Umum</option>
                            <option value="Kapal Laut" <?= $currTrans === 'Kapal Laut' ? 'selected' : '' ?>>Kapal Laut</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2 flex items-center pt-3">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-800">
                            <input type="checkbox" name="need_pickup" id="admin_need_pickup" value="1" <?= (!empty($_POST['need_pickup']) || !empty($p['need_pickup'])) ? 'checked' : '' ?> onchange="document.getElementById('admin-pickup-box').classList.toggle('hidden', !this.checked)" class="w-4 h-4 text-emerald-600 rounded">
                            <span>Membutuhkan Penjemputan / Pengantaran oleh Panitia di Lokasi Tiba</span>
                        </label>
                    </div>

                    <div id="admin-pickup-box" class="sm:col-span-2 <?= (!empty($_POST['need_pickup']) || !empty($p['need_pickup'])) ? '' : 'hidden' ?> bg-amber-50/70 border border-amber-200 rounded-2xl p-4 transition-all space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-amber-500 text-white flex items-center justify-center text-xs">🚐</span>
                            <span class="text-xs font-bold text-amber-900 uppercase tracking-wider">Detail Permintaan Penjemputan / Pengantaran</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-amber-950 uppercase mb-1">Titik / Lokasi Penjemputan</label>
                                <input type="text" name="pickup_location" list="admin-list-lokasi" value="<?= clean($_POST['pickup_location'] ?? ($p['pickup_location'] ?? '')) ?>" placeholder="Contoh: Stasiun Gubeng / Bandara Juanda T1" class="w-full px-3.5 py-2.5 rounded-xl border border-amber-300 text-xs bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                <datalist id="admin-list-lokasi">
                                    <option value="Stasiun Surabaya Gubeng (SGU)">
                                    <option value="Stasiun Surabaya Pasarturi (SBI)">
                                    <option value="Stasiun Surabaya Kota / Semut">
                                    <option value="Stasiun Wonokromo">
                                    <option value="Bandara Internasional Juanda - Terminal 1 (Domestik)">
                                    <option value="Bandara Internasional Juanda - Terminal 2 (Internasional & Garuda/AirAsia)">
                                    <option value="Terminal Purabaya / Bungurasih">
                                    <option value="Terminal Osowilangun">
                                    <option value="Pelabuhan Tanjung Perak Surabaya">
                                </datalist>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-amber-950 uppercase mb-1">Waktu / Jam Penjemputan Dikehendaki</label>
                                <input type="text" name="pickup_time" value="<?= clean($_POST['pickup_time'] ?? ($p['pickup_time'] ?? '')) ?>" placeholder="Contoh: pk 13:30 WIB" class="w-full px-3.5 py-2.5 rounded-xl border border-amber-300 text-xs bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-bold text-amber-950 uppercase mb-1">Catatan Tambahan untuk Panitia Transportasi</label>
                                <textarea name="pickup_notes" rows="2" placeholder="Nama Kereta/No Penerbangan, perkiraan jumlah barang/koper, nomor kontak koordinator lapangan..." class="w-full px-3.5 py-2 rounded-xl border border-amber-300 text-xs bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none"><?= clean($_POST['pickup_notes'] ?? ($p['pickup_notes'] ?? '')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Penginapan, Verifikasi & Catatan -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    Penginapan & Catatan
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alokasi Kamar</label>
                        <select name="room_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white">
                            <option value="">-- Belum Ditentukan --</option>
                            <?php $currRoom = $_POST['room_id'] ?? $p['room_id']; ?>
                            <?php foreach ($roomList as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= (int)$currRoom === (int)$r['id'] ? 'selected' : '' ?>>
                                    <?= clean($r['building_name']) ?> - <?= clean($r['room_number']) ?> (<?= $r['gender'] == 'L' ? 'Ikhwan' : 'Akhwat' ?>, Kapasitas <?= $r['capacity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Status Verifikasi</label>
                        <select name="status_verifikasi" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white font-bold">
                            <?php $currVerif = $_POST['status_verifikasi'] ?? $p['status_verifikasi']; ?>
                            <option value="verified" <?= $currVerif === 'verified' ? 'selected' : '' ?>>Terverifikasi</option>
                            <option value="pending" <?= $currVerif === 'pending' ? 'selected' : '' ?>>Pending / Menunggu</option>
                            <option value="rejected" <?= $currVerif === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email</label>
                        <input type="email" name="email" value="<?= clean($_POST['email'] ?? $p['email']) ?>" placeholder="delegasi@alkhidmah.org" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Catatan Khusus</label>
                        <textarea name="notes" rows="2" placeholder="Catatan tambahan dari delegasi..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none"><?= clean($_POST['notes'] ?? $p['notes']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="<?= BASE_URL ?>admin/participants.php" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-700 text-white font-bold text-xs hover:bg-emerald-800 transition shadow-sm">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCategoryFields(cat) {
    const boxPw = document.getElementById('box-pw');
    const boxPd = document.getElementById('box-pd');
    const boxUnsur = document.getElementById('box-unsur');

    if (cat === 'PP') {
        boxPw.classList.add('hidden');
        boxPd.classList.add('hidden');
        boxUnsur.classList.remove('hidden');
    } else if (cat === 'Tamu Undangan') {
        boxPw.classList.add('hidden');
        boxPd.classList.add('hidden');
    } else if (cat === 'PW') {
        boxPw.classList.remove('hidden');
        boxPd.classList.add('hidden');
        boxUnsur.classList.remove('hidden');
    } else if (cat === 'PD') {
        boxPw.classList.remove('hidden');
        boxPd.classList.remove('hidden');
        boxUnsur.classList.remove('hidden');
    }
}

function loadDistrictsForPW(pwId) {
    const selectDaerah = document.getElementById('edit_daerah_id');
    if (!pwId) {
        selectDaerah.innerHTML = '<option value="">-- Pilih Daerah --</option>';
        return;
    }

    fetch(`<?= BASE_URL ?>api/get_daerah.php?pw_id=${pwId}`)
        .then(res => res.json())
        .then(data => {
            let html = '<option value="">-- Pilih Daerah --</option>';
            if (data && data.length > 0) {
                data.forEach(d => {
                    html += `<option value="${d.id}">${d.name}</option>`;
                });
            }
            selectDaerah.innerHTML = html;
        })
        .catch(err => {
            console.error('Error load daerah:', err);
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
