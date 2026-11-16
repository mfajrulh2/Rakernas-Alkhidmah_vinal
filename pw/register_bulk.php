<?php
/**
 * Form Pendaftaran Delegasi oleh PW
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

// Hitung kuota saat ini
$stmtCount = $pdo->prepare("
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN participant_type = 'utusan' THEN 1 ELSE 0 END) as utusan_count,
        SUM(CASE WHEN participant_type = 'peninjau' THEN 1 ELSE 0 END) as peninjau_count
    FROM participants 
    WHERE pw_id = ?
");
$stmtCount->execute([$pw_id]);
$curr = $stmtCount->fetch();

$curTotal    = (int)$curr['total_count'];
$curUtusan   = (int)$curr['utusan_count'];
$curPeninjau = (int)$curr['peninjau_count'];
$totalQuota  = (int)$pw['quota_utusan'] + (int)$pw['quota_peninjau'];
$sisaKuota   = max(0, $totalQuota - $curTotal);
$sisaUtusan   = max(0, $pw['quota_utusan'] - $curUtusan);
$sisaPeninjau = max(0, $pw['quota_peninjau'] - $curPeninjau);

$unsurList  = getUnsurOrganisasiOptions();
$komisiList = getKomisiOptions();
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $names    = $_POST['full_name'] ?? [];
    $genders  = $_POST['gender'] ?? [];
    $unsurs   = $_POST['unsur_organisasi'] ?? [];
    $pds      = $_POST['pd_name'] ?? [];
    $jabatans = $_POST['jabatan_organisasi'] ?? [];
    $komisis  = $_POST['komisi'] ?? [];
    $phones   = $_POST['phone'] ?? [];

    $validRows = [];
    $tempSisaUtusan = $sisaUtusan;

    for ($i = 0; $i < count($names); $i++) {
        $name  = trim($names[$i] ?? '');
        $phone = trim($phones[$i] ?? '');
        if (empty($name)) continue;

        $rowNum = $i + 1;
        if (empty($phone)) {
            $errors[] = "Delegasi #{$rowNum} ({$name}): Nomor WA wajib diisi.";
            continue;
        }

        // Handle upload foto untuk baris ke-$i
        $photoPath = null;
        if (isset($_FILES['photo_file'])) {
            $up = uploadParticipantPhoto($_FILES['photo_file'], $i);
            if ($up['error']) {
                $errors[] = "Delegasi #{$rowNum} ({$name}): " . $up['error'];
                continue;
            }
            $photoPath = $up['path'];
        }

        $unsur = trim($unsurs[$i] ?? 'Al Khidmah');
        if (!in_array($unsur, $unsurList)) {
            $unsur = 'Al Khidmah';
        }

        $komisi = trim($komisis[$i] ?? $komisiList[0]);

        // Tentukan tipe berdasarkan sisa kuota utusan/peninjau
        if ($tempSisaUtusan > 0) {
            $type = 'utusan';
            $tempSisaUtusan--;
        } else {
            $type = 'peninjau';
        }

        $validRows[] = [
            'full_name'        => $name,
            'gender'           => ($genders[$i] ?? 'L') === 'P' ? 'P' : 'L',
            'unsur_organisasi' => $unsur,
            'pd_name'          => trim($pds[$i] ?? ''),
            'jabatan'          => trim($jabatans[$i] ?? ''),
            'komisi'           => $komisi,
            'phone'            => $phone,
            'photo_file'       => $photoPath,
            'type'             => $type
        ];
    }

    if (empty($validRows) && empty($errors)) {
        $errors[] = "Mohon isi setidaknya 1 data delegasi.";
    }

    if (count($validRows) > $sisaKuota) {
        $errors[] = "Jumlah delegasi yang didaftarkan (" . count($validRows) . ") melebihi sisa kuota wilayah Anda ({$sisaKuota} kursi).";
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmtIns = $pdo->prepare("
                INSERT INTO participants 
                (registration_code, full_name, gender, unsur_organisasi, pd_name, jabatan_organisasi, komisi, phone, photo_file, pw_id, participant_type, registered_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($validRows as $row) {
                $regCode = generateRegistrationCode($pw['code']);
                $stmtIns->execute([
                    $regCode,
                    $row['full_name'],
                    $row['gender'],
                    $row['unsur_organisasi'],
                    $row['pd_name'],
                    $row['jabatan'],
                    $row['komisi'],
                    $row['phone'],
                    $row['photo_file'],
                    $pw_id,
                    $row['type'],
                    $user['id']
                ]);
            }
            $pdo->commit();

            setFlash('success', "Alhamdulillah, berhasil mendaftarkan " . count($validRows) . " delegasi.");
            header("Location: " . BASE_URL . "pw/participants.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

$page_title = "Form Pendaftaran Delegasi PW";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto mb-16 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider text-emerald-800 bg-emerald-50 px-3 py-1 rounded-full mb-1">
                Formulir Registrasi Delegasi
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Pendaftaran Delegasi Wilayah</h1>
            <p class="text-xs text-slate-500 mt-0.5">Isi data delegasi sesuai mandat resmi <?= clean($pw['name']) ?></p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="<?= BASE_URL ?>pw/participants.php" class="px-4 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                Lihat Daftar Delegasi
            </a>
            <a href="<?= BASE_URL ?>pw/index.php" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition">
                &larr; Dashboard
            </a>
        </div>
    </div>

    <!-- Info Kuota Tersedia Bar -->
    <div class="bg-gradient-to-r from-alkhidmah-900 to-alkhidmah-800 text-white p-5 rounded-3xl border border-alkhidmah-700/60 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-white/10 flex items-center justify-center text-gold-400">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
            </div>
            <div>
                <div class="font-bold text-sm text-white"><?= clean($pw['name']) ?></div>
                <div class="text-[11px] text-emerald-200">Sisa Kuota Wilayah yang Dapat Didaftarkan:</div>
            </div>
        </div>

        <div class="flex items-center gap-3 text-xs">
            <div class="bg-black/20 px-3.5 py-1.5 rounded-xl border border-white/10">
                <span>Total Sisa Kuota: </span>
                <strong class="text-emerald-300"><?= $sisaKuota ?> Kursi</strong>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
            <?php foreach ($errors as $err): ?>
                <div>• <?= clean($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Form Container -->
    <form action="" method="POST" enctype="multipart/form-data" id="form-bulk" class="space-y-6">
        <div id="delegate-cards-container" class="space-y-6">
            <!-- Card Delegasi 1 (Default) -->
            <div class="delegate-card bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-6 py-3.5 border-b border-slate-200/80 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-alkhidmah-800 text-white text-xs font-bold flex items-center justify-center card-number">1</span>
                        <h3 class="font-bold text-sm text-slate-800">Data Peserta Delegasi</h3>
                    </div>
                    <button type="button" onclick="removeDelegateCard(this)" class="btn-remove-card hidden text-xs font-semibold text-rose-600 hover:text-rose-800 px-2.5 py-1 rounded-lg hover:bg-rose-50 transition">
                        ✕ Hapus Form Ini
                    </button>
                </div>

                <div class="p-6 sm:p-8 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- 1. Nama Lengkap -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            1. Nama Lengkap <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="full_name[]" required placeholder="Masukkan nama lengkap beserta gelar"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <!-- 2. Jenis Kelamin L/P -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            2. Jenis Kelamin (L/P) <span class="text-rose-500">*</span>
                        </label>
                        <select name="gender[]" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                            <option value="L">L - Laki-laki (Ikhwan)</option>
                            <option value="P">P - Perempuan (Akhwat)</option>
                        </select>
                    </div>

                    <!-- 3. Unsur Organisasi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            3. Unsur Organisasi <span class="text-rose-500">*</span>
                        </label>
                        <select name="unsur_organisasi[]" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold text-slate-800">
                            <?php foreach ($unsurList as $u): ?>
                                <option value="<?= clean($u) ?>"><?= clean($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Pengurus Daerah -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            4. Pengurus Daerah <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="pd_name[]" required placeholder="Contoh: PD Surabaya / PW Jawa Timur"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <!-- 5. Jabatan/Bidang -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            5. Jabatan / Bidang <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="jabatan_organisasi[]" required placeholder="Contoh: Ketua PD / Sekretaris / Bidang Organisasi"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <!-- 6. Komisi Sidang -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            6. Komisi Sidang <span class="text-rose-500">*</span>
                        </label>
                        <select name="komisi[]" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                            <?php foreach ($komisiList as $k): ?>
                                <option value="<?= clean($k) ?>"><?= clean($k) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 7. Nomor WA -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            7. Nomor WA <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="phone[]" required placeholder="Contoh: 081234567890"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <!-- 8. Upload Foto -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            8. Upload Foto Peserta
                        </label>
                        <input type="file" name="photo_file[]" accept=".jpg,.jpeg,.png,.webp" onchange="validatePhotoSize(this)"
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-800 hover:file:bg-emerald-100 transition bg-slate-50/50">
                        <p class="text-[11px] font-medium text-amber-700 mt-1.5 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>upload file foto dengan mengenakan baju dan kopiah putih - ukuran maks 5 Mb</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <button type="button" onclick="addDelegateCard()" class="px-4 py-2.5 rounded-xl border-2 border-dashed border-emerald-600 hover:bg-emerald-50 text-emerald-800 font-bold text-xs transition flex items-center gap-1.5 self-start">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Form Delegasi Lainnya</span>
            </button>

            <div class="flex items-center gap-3 justify-end">
                <a href="<?= BASE_URL ?>pw/participants.php" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Simpan Pendaftaran Delegasi</span>
                </button>
            </div>
        </div>
    </form>
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

function updateCardNumbers() {
    const cards = document.querySelectorAll('.delegate-card');
    cards.forEach((card, idx) => {
        const numBadge = card.querySelector('.card-number');
        if (numBadge) numBadge.textContent = idx + 1;
        const removeBtn = card.querySelector('.btn-remove-card');
        if (removeBtn) {
            if (cards.length > 1) {
                removeBtn.classList.remove('hidden');
            } else {
                removeBtn.classList.add('hidden');
            }
        }
    });
}

function addDelegateCard() {
    const container = document.getElementById('delegate-cards-container');
    const firstCard = container.querySelector('.delegate-card');
    const clone = firstCard.cloneNode(true);

    // Kosongkan nilai input pada form baru
    clone.querySelectorAll('input[type="text"], input[type="file"]').forEach(inp => inp.value = '');
    clone.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);

    container.appendChild(clone);
    updateCardNumbers();
    clone.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function removeDelegateCard(btn) {
    const cards = document.querySelectorAll('.delegate-card');
    if (cards.length <= 1) return;
    btn.closest('.delegate-card').remove();
    updateCardNumbers();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
