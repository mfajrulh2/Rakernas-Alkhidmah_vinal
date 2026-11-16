<?php
/**
 * Form Edit Data Delegasi oleh Akun PW
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin_pw');

$pdo   = getDB();
$user  = currentUser();
$pw_id = $user['pw_id'];
$id    = (int)($_GET['id'] ?? 0);

// Pastikan delegasi milik PW yang sedang login
$stmtP = $pdo->prepare("SELECT * FROM participants WHERE id = ? AND pw_id = ?");
$stmtP->execute([$id, $pw_id]);
$p = $stmtP->fetch();

if (!$p) {
    setFlash('danger', 'Data delegasi tidak ditemukan atau bukan dari wilayah Anda.');
    header("Location: " . BASE_URL . "pw/participants.php");
    exit;
}

$unsurList  = getUnsurOrganisasiOptions();
$komisiList = getKomisiOptions();
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $gender    = ($_POST['gender'] ?? 'L') === 'P' ? 'P' : 'L';
    $unsur     = trim($_POST['unsur_organisasi'] ?? 'Al Khidmah');
    $pd_name   = trim($_POST['pd_name'] ?? '');
    $jabatan   = trim($_POST['jabatan_organisasi'] ?? '');
    $komisi    = trim($_POST['komisi'] ?? $komisiList[0]);
    $phone     = trim($_POST['phone'] ?? '');

    if (empty($full_name)) $errors[] = "Nama Lengkap wajib diisi.";
    if (empty($phone))     $errors[] = "Nomor WA wajib diisi.";

    if (!in_array($unsur, $unsurList)) {
        $unsur = 'Al Khidmah';
    }

    // Handle upload foto baru (opsional)
    $photoPath = $p['photo_file'] ?? null;
    if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = uploadParticipantPhoto($_FILES['photo_file']);
        if ($up['error']) {
            $errors[] = $up['error'];
        } elseif ($up['uploaded']) {
            // Hapus foto lama jika ada
            if (!empty($photoPath) && file_exists(__DIR__ . '/../' . $photoPath)) {
                @unlink(__DIR__ . '/../' . $photoPath);
            }
            $photoPath = $up['path'];
        }
    }

    if (empty($errors)) {
        $stmtUp = $pdo->prepare("
            UPDATE participants SET
                full_name = ?,
                gender = ?,
                unsur_organisasi = ?,
                pd_name = ?,
                jabatan_organisasi = ?,
                komisi = ?,
                phone = ?,
                photo_file = ?
            WHERE id = ? AND pw_id = ?
        ");
        $stmtUp->execute([
            $full_name,
            $gender,
            $unsur,
            $pd_name,
            $jabatan,
            $komisi,
            $phone,
            $photoPath,
            $id,
            $pw_id
        ]);

        setFlash('success', "Data delegasi {$full_name} berhasil diperbarui.");
        header("Location: " . BASE_URL . "pw/participants.php");
        exit;
    }
}

$page_title = "Edit Delegasi - " . clean($p['full_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto mb-16 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 font-mono text-xs font-bold text-slate-500 mb-1">
                Kode Registrasi: <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200"><?= clean($p['registration_code']) ?></span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Edit Data Delegasi</h1>
            <p class="text-xs text-slate-500 mt-0.5">Perbarui informasi delegasi <?= clean($user['pw_name']) ?></p>
        </div>
        <a href="<?= BASE_URL ?>pw/participants.php" class="px-4 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition self-start">
            &larr; Kembali ke Daftar Delegasi
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
            <?php foreach ($errors as $err): ?>
                <div>• <?= clean($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200/80 flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
            <h3 class="font-bold text-sm text-slate-800">Formulir Perubahan Data Delegasi</h3>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <!-- 1. Nama Lengkap -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        1. Nama Lengkap <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="full_name" required value="<?= clean($p['full_name']) ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>

                <!-- 2. Jenis Kelamin L/P -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        2. Jenis Kelamin (L/P) <span class="text-rose-500">*</span>
                    </label>
                    <select name="gender" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                        <option value="L" <?= $p['gender'] === 'L' ? 'selected' : '' ?>>L - Laki-laki (Ikhwan)</option>
                        <option value="P" <?= $p['gender'] === 'P' ? 'selected' : '' ?>>P - Perempuan (Akhwat)</option>
                    </select>
                </div>

                <!-- 3. Unsur Organisasi -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        3. Unsur Organisasi <span class="text-rose-500">*</span>
                    </label>
                    <?php $currUnsur = $p['unsur_organisasi'] ?? 'Al Khidmah'; ?>
                    <select name="unsur_organisasi" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold text-slate-800">
                        <?php foreach ($unsurList as $u): ?>
                            <option value="<?= clean($u) ?>" <?= $currUnsur === $u ? 'selected' : '' ?>><?= clean($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Pengurus Daerah -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        4. Pengurus Daerah <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="pd_name" required value="<?= clean($p['pd_name']) ?>" placeholder="Contoh: PD Surabaya"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>

                <!-- 5. Jabatan/Bidang -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        5. Jabatan / Bidang <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="jabatan_organisasi" required value="<?= clean($p['jabatan_organisasi']) ?>" placeholder="Contoh: Ketua PD / Sekretaris"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>

                <!-- 6. Komisi Sidang -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        6. Komisi Sidang <span class="text-rose-500">*</span>
                    </label>
                    <select name="komisi" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                        <?php foreach ($komisiList as $k): ?>
                            <option value="<?= clean($k) ?>" <?= $p['komisi'] === $k ? 'selected' : '' ?>><?= clean($k) ?></option>
                        <?php endforeach; ?>
                        <?php if (!in_array($p['komisi'], $komisiList) && !empty($p['komisi'])): ?>
                            <option value="<?= clean($p['komisi']) ?>" selected><?= clean($p['komisi']) ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- 7. Nomor WA -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        7. Nomor WA <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="phone" required value="<?= clean($p['phone']) ?>" placeholder="Contoh: 081234567890"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>

                <!-- 8. Upload Foto -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        8. Upload Foto Peserta
                    </label>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <?php if (!empty($p['photo_file'])): ?>
                            <div class="flex-shrink-0 text-center">
                                <img src="<?= BASE_URL . clean($p['photo_file']) ?>" alt="Foto" class="w-20 h-24 object-cover rounded-xl border border-slate-300 shadow-sm">
                                <span class="block text-[10px] text-emerald-700 font-semibold mt-1">Foto Saat Ini</span>
                            </div>
                        <?php endif; ?>

                        <div class="flex-grow w-full">
                            <input type="file" name="photo_file" accept=".jpg,.jpeg,.png,.webp" onchange="validatePhotoSize(this)"
                                   class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-800 hover:file:bg-emerald-100 transition bg-slate-50/50">
                            <p class="text-[11px] font-medium text-amber-700 mt-1.5 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>upload file foto dengan mengenakan baju dan kopiah putih - ukuran maks 5 Mb</span>
                            </p>
                            <?php if (!empty($p['photo_file'])): ?>
                                <p class="text-[11px] text-slate-400 mt-0.5">Biarkan kosong jika tidak ingin mengganti foto yang sudah ada.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="<?= BASE_URL ?>pw/participants.php" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Simpan Perubahan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function validatePhotoSize(input) {
    if (input.files && input.files[0]) {
        const maxSize = 5 * 1024 * 1024; // 5 MB
        if (input.files[0].size > maxSize) {
            alert('Ukuran file foto melebihi batas maksimal 5 Mb! Silakan pilih file foto dengan ukuran maksimal 5 Mb.');
            input.value = '';
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
