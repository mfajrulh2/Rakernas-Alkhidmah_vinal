<?php
/**
 * Impor Data Delegasi dari Excel / CSV
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('superadmin');

$pdo = getDB();

// Download template CSV jika diminta
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Template_Import_Delegasi_Rakernas.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nama Lengkap', 'L/P', 'Nomor HP', 'NIK', 'Kode PW', 'Nama PD', 'Jabatan', 'Jenis Peserta', 'Komisi']);
    fputcsv($out, ['H. Achmad Fauzi, M.Pd', 'L', '081234567801', '3578010101850001', 'PW-JATIM', 'PD Surabaya', 'Ketua PD', 'utusan', 'Komisi A (Organisasi)']);
    fputcsv($out, ['Hj. Siti Aminah', 'P', '081234567802', '3578010101850002', 'PW-JATENG', 'PD Semarang', 'Sekretaris PD', 'peninjau', 'Komisi B (Program Kerja)']);
    fclose($out);
    exit;
}

$successCount = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Gagal mengunggah file. Silakan periksa ukuran file.";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle !== FALSE) {
            // Ambil mapping wilayah (code => id)
            $stmtW = $pdo->query("SELECT id, code, name FROM wilayah");
            $wilayahMap = [];
            while ($w = $stmtW->fetch()) {
                $wilayahMap[strtoupper($w['code'])] = $w['id'];
                $wilayahMap[strtoupper($w['name'])] = $w['id'];
            }

            // Lewati BOM & baris header
            $header = fgetcsv($handle, 1000, ',');

            $pdo->beginTransaction();
            try {
                $rowNum = 1;
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $rowNum++;
                    if (empty($data) || empty(trim($data[0] ?? ''))) continue;

                    $fullName = trim($data[0]);
                    $gender   = strtoupper(trim($data[1] ?? 'L')) === 'P' ? 'P' : 'L';
                    $phone    = trim($data[2] ?? '');
                    $nik      = trim($data[3] ?? '');
                    $pwCode   = strtoupper(trim($data[4] ?? ''));
                    $pdName   = trim($data[5] ?? '');
                    $jabatan  = trim($data[6] ?? 'Pengurus');
                    $type     = strtolower(trim($data[7] ?? 'utusan'));
                    if (!in_array($type, ['utusan', 'peninjau', 'pengurus_pusat', 'undangan'])) {
                        $type = 'utusan';
                    }
                    $komisi   = trim($data[8] ?? 'Komisi A (Organisasi)');

                    // Cari ID PW
                    $pw_id = $wilayahMap[$pwCode] ?? null;
                    if (!$pw_id) {
                        // Fallback ke PW pertama jika tidak ada
                        $pw_id = reset($wilayahMap);
                        $pwCode = 'GEN';
                    }

                    $regCode = generateRegistrationCode($pwCode);

                    $stmtIns = $pdo->prepare("
                        INSERT INTO participants 
                        (registration_code, nik, full_name, gender, phone, pw_id, pd_name, jabatan_organisasi, participant_type, komisi, registered_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([
                        $regCode, $nik, $fullName, $gender, $phone, $pw_id, $pdName, $jabatan, $type, $komisi, currentUser()['id']
                    ]);
                    $successCount++;
                }
                $pdo->commit();
                setFlash('success', "Berhasil mengimpor {$successCount} data delegasi ke dalam sistem.");
                header("Location: " . BASE_URL . "admin/participants.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Error baris {$rowNum}: " . $e->getMessage();
            }
            fclose($handle);
        }
    }
}

$page_title = "Import Data Delegasi";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto mb-16 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Import Delegasi Massal</h1>
            <p class="text-xs text-slate-500 mt-0.5">Unggah berkas CSV/Excel untuk mendaftarkan puluhan peserta sekaligus</p>
        </div>
        <a href="<?= BASE_URL ?>admin/participants.php" class="px-4 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
            &larr; Kembali
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
            <?php foreach ($errors as $err): ?>
                <div>• <?= clean($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Download Template Box -->
    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/60 p-6 rounded-3xl border border-emerald-200 flex items-center justify-between gap-4">
        <div>
            <div class="font-bold text-sm text-emerald-950">1. Unduh Template Spreadsheet</div>
            <p class="text-xs text-emerald-800 mt-0.5">Format file sudah disesuaikan dengan kolom database sistem.</p>
        </div>
        <a href="?download_template=1" class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shadow transition flex items-center gap-1.5 flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            <span>Download Template CSV</span>
        </a>
    </div>

    <!-- Upload Form -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8">
        <div class="font-bold text-sm text-slate-900 mb-4">2. Unggah Berkas yang Telah Diisi</div>
        
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div class="border-2 border-dashed border-slate-300 rounded-2xl p-8 text-center hover:border-emerald-600 transition bg-slate-50/50">
                <input type="file" name="csv_file" required accept=".csv" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                <p class="text-[11px] text-slate-400 mt-2">Format yang didukung: file <strong>.csv</strong> (Maksimal 10MB)</p>
            </div>

            <div class="pt-4 flex justify-end gap-2">
                <button type="submit" class="w-full py-3 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow">
                    Mulai Proses Import &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
