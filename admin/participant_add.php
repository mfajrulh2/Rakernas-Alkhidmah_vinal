<?php
/**
 * Form Tambah Peserta / Delegasi (Admin Pusat)
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['superadmin']);

$pdo = getDB();
$errors = [];

$unsurOptions  = getUnsurOrganisasiOptions();
$komisiOptions = getKomisiOptions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name'] ?? '');
    $gender           = trim($_POST['gender'] ?? 'L');
    $unsur_organisasi = trim($_POST['unsur_organisasi'] ?? 'Al Khidmah');
    $pd_name          = trim($_POST['pd_name'] ?? '');
    $jabatan          = trim($_POST['jabatan_organisasi'] ?? '');
    $komisi           = trim($_POST['komisi'] ?? $komisiOptions[0]);
    $phone            = trim($_POST['phone'] ?? '');
    $nik              = trim($_POST['nik'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $pw_id            = (int)($_POST['pw_id'] ?? 0);
    $type             = trim($_POST['participant_type'] ?? 'utusan');
    $room_id          = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $notes            = trim($_POST['notes'] ?? '');

    if (empty($full_name)) $errors[] = "Nama lengkap wajib diisi.";
    if (empty($phone)) $errors[] = "Nomor WhatsApp wajib diisi.";
    if ($pw_id <= 0) $errors[] = "Pengurus Wilayah (PW) wajib dipilih.";

    // Handle upload photo jika ada
    $photoPath = null;
    if (isset($_FILES['photo_file']) && !empty($_FILES['photo_file']['name'])) {
        $photoUpload = uploadParticipantPhoto($_FILES['photo_file']);
        if ($photoUpload['error']) {
            $errors[] = $photoUpload['error'];
        } else {
            $photoPath = $photoUpload['path'];
        }
    }

    if (empty($errors)) {
        // Ambil kode wilayah untuk generate nomor registrasi
        $stmtPW = $pdo->prepare("SELECT code FROM wilayah WHERE id = ?");
        $stmtPW->execute([$pw_id]);
        $pwCode = $stmtPW->fetchColumn() ?: 'GEN';

        $regCode = generateRegistrationCode($pwCode);

        // Pastikan unik
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM participants WHERE registration_code = ?");
        $stmtCheck->execute([$regCode]);
        if ($stmtCheck->fetchColumn() > 0) {
            $regCode = generateRegistrationCode($pwCode) . 'A';
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO participants 
            (registration_code, nik, full_name, gender, unsur_organisasi, phone, email, photo_file, pw_id, pd_name, jabatan_organisasi, participant_type, komisi, room_id, registered_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([
            $regCode, $nik, $full_name, $gender, $unsur_organisasi, $phone, $email, $photoPath, $pw_id, $pd_name, $jabatan, $type, $komisi, $room_id, currentUser()['id'], $notes
        ]);

        setFlash('success', "Delegasi {$full_name} berhasil ditambahkan dengan kode {$regCode}.");
        header("Location: " . BASE_URL . "admin/participants.php");
        exit;
    }
}

// Ambil list Wilayah
$wilayahList = $pdo->query("SELECT id, name, code FROM wilayah ORDER BY name ASC")->fetchAll();

// Ambil list Kamar
$roomList = $pdo->query("SELECT id, building_name, room_number, gender, capacity FROM rooms ORDER BY building_name ASC, room_number ASC")->fetchAll();

$page_title = "Tambah Delegasi Baru";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto mb-16">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Tambah Delegasi Baru</h1>
            <p class="text-xs text-slate-500 mt-0.5">Input data delegasi langsung dari Panitia Pusat</p>
        </div>
        <a href="<?= BASE_URL ?>admin/participants.php" class="px-4 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
            &larr; Kembali
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
            <?php foreach ($errors as $err): ?>
                <div>• <?= clean($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8">
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
            <!-- Informasi Personal -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                    Identitas Delegasi
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap & Gelar *</label>
                        <input type="text" name="full_name" required value="<?= clean($_POST['full_name'] ?? '') ?>" placeholder="Contoh: Drs. H. Achmad Fauzi, M.Pd" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Jenis Kelamin *</label>
                        <select name="gender" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                            <option value="L" <?= ($_POST['gender'] ?? 'L') === 'L' ? 'selected' : '' ?>>Laki-laki (L)</option>
                            <option value="P" <?= ($_POST['gender'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan (P)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Unsur Organisasi *</label>
                        <select name="unsur_organisasi" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold text-emerald-800">
                            <?php foreach ($unsurOptions as $uo): ?>
                                <option value="<?= clean($uo) ?>" <?= ($_POST['unsur_organisasi'] ?? 'Al Khidmah') === $uo ? 'selected' : '' ?>><?= clean($uo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp *</label>
                        <input type="text" name="phone" required value="<?= clean($_POST['phone'] ?? '') ?>" placeholder="Contoh: 081234567890" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">NIK (KTP)</label>
                        <input type="text" name="nik" value="<?= clean($_POST['nik'] ?? '') ?>" placeholder="16 Digit NIK" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Upload Foto Delegasi</label>
                        <input type="file" name="photo_file" accept=".jpg,.jpeg,.png,.webp" onchange="validateAdminPhotoSize(this)" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-semibold hover:file:bg-emerald-100 transition">
                        <p class="text-[11px] text-amber-700 mt-1 font-medium flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-500 inline shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                            upload file foto dengan mengenakan baju dan kopiah putih - ukuran maks 5 Mb
                        </p>
                    </div>
                </div>
            </div>

            <!-- Asal Wilayah & Organisasi -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Asal Wilayah & Jabatan
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pengurus Wilayah (PW) *</label>
                        <select name="pw_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                            <option value="">Pilih Pengurus Wilayah</option>
                            <?php foreach ($wilayahList as $w): ?>
                                <option value="<?= $w['id'] ?>" <?= ($_POST['pw_id'] ?? '') == $w['id'] ? 'selected' : '' ?>><?= clean($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pengurus Daerah</label>
                        <input type="text" name="pd_name" value="<?= clean($_POST['pd_name'] ?? '') ?>" placeholder="Contoh: PD Al Khidmah Surabaya" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Jabatan / Bidang</label>
                        <input type="text" name="jabatan_organisasi" value="<?= clean($_POST['jabatan_organisasi'] ?? '') ?>" placeholder="Contoh: Ketua / Sekretaris / Anggota" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Klasifikasi Peserta *</label>
                        <select name="participant_type" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                            <option value="utusan" <?= ($_POST['participant_type'] ?? '') === 'utusan' ? 'selected' : '' ?>>Peserta Utusan (Hak Suara & Bicara)</option>
                            <option value="peninjau" <?= ($_POST['participant_type'] ?? '') === 'peninjau' ? 'selected' : '' ?>>Peserta Peninjau (Hak Bicara)</option>
                            <option value="pengurus_pusat" <?= ($_POST['participant_type'] ?? '') === 'pengurus_pusat' ? 'selected' : '' ?>>Pengurus Pusat</option>
                            <option value="undangan" <?= ($_POST['participant_type'] ?? '') === 'undangan' ? 'selected' : '' ?>>Undangan Khusus</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Sidang Komisi & Penginapan -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                    Sidang Komisi & Fasilitas Kamar
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Komisi Sidang *</label>
                        <select name="komisi" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold text-slate-800">
                            <?php foreach ($komisiOptions as $ko): ?>
                                <option value="<?= clean($ko) ?>" <?= ($_POST['komisi'] ?? $komisiOptions[0]) === $ko ? 'selected' : '' ?>><?= clean($ko) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kamar Penginapan</label>
                        <select name="room_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                            <option value="">-- Tentukan Nanti --</option>
                            <?php foreach ($roomList as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= ($_POST['room_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                                    <?= clean($r['building_name']) ?> - <?= clean($r['room_number']) ?> (<?= $r['gender'] == 'L' ? 'Ikhwan' : 'Akhwat' ?>, Kapasitas <?= $r['capacity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email (Opsional)</label>
                        <input type="email" name="email" value="<?= clean($_POST['email'] ?? '') ?>" placeholder="delegasi@alkhidmah.org" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Catatan Khusus (Opsional)</label>
                        <textarea name="notes" rows="2" placeholder="Catatan khusus kedatangan atau konsumsi..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none"><?= clean($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="<?= BASE_URL ?>admin/participants.php" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-sm">
                    Simpan Delegasi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function validateAdminPhotoSize(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxBytes = 5 * 1024 * 1024;
        if (file.size > maxBytes) {
            alert('Ukuran file foto melebihi 5 MB (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB). Silakan pilih foto dengan ukuran maksimal 5 MB.');
            input.value = '';
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
