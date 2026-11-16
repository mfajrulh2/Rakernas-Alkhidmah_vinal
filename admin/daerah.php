<?php
/**
 * Master Data Pengurus Daerah (PD) & Kuota
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('superadmin');
ensureRegistrationTablesExist();

$pdo = getDB();

// Handle Form Submissions: Tambah / Edit / Hapus / Import CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Tambah PD
    if ($action === 'create_daerah') {
        $pw_id = (int)($_POST['pw_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $quota = (int)($_POST['quota'] ?? 5);

        if ($pw_id <= 0 || empty($name)) {
            setFlash('danger', 'Asal Wilayah (PW) dan Nama Pengurus Daerah (PD) wajib diisi.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO daerah (pw_id, name, quota) VALUES (?, ?, ?)");
                $stmt->execute([$pw_id, $name, $quota]);
                setFlash('success', "Pengurus Daerah '{$name}' berhasil ditambahkan.");
                header("Location: " . BASE_URL . "admin/daerah.php?pw_id=" . $pw_id);
                exit;
            } catch (Exception $e) {
                setFlash('danger', "Gagal menambahkan PD: " . $e->getMessage());
            }
        }
    }
    // 2. Edit PD
    elseif ($action === 'edit_daerah') {
        $id    = (int)($_POST['id'] ?? 0);
        $pw_id = (int)($_POST['pw_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $quota = (int)($_POST['quota'] ?? 5);

        if ($id <= 0 || $pw_id <= 0 || empty($name)) {
            setFlash('danger', 'Data tidak lengkap untuk diperbarui.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE daerah SET pw_id = ?, name = ?, quota = ? WHERE id = ?");
                $stmt->execute([$pw_id, $name, $quota, $id]);
                setFlash('success', "Data Pengurus Daerah '{$name}' berhasil diperbarui.");
                header("Location: " . BASE_URL . "admin/daerah.php?pw_id=" . $pw_id);
                exit;
            } catch (Exception $e) {
                setFlash('danger', "Gagal memperbarui PD: " . $e->getMessage());
            }
        }
    }
    // 3. Hapus PD
    elseif ($action === 'delete_daerah') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmtName = $pdo->prepare("SELECT name FROM daerah WHERE id = ?");
                $stmtName->execute([$id]);
                $dName = $stmtName->fetchColumn();

                $pdo->prepare("DELETE FROM daerah WHERE id = ?")->execute([$id]);
                setFlash('info', "Pengurus Daerah '{$dName}' berhasil dihapus.");
            } catch (Exception $e) {
                setFlash('danger', "Gagal menghapus PD: " . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "admin/daerah.php");
        exit;
    }
    // 4. Import Massal PD dari CSV
    elseif ($action === 'import_daerah_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            if ($handle !== false) {
                $imported = 0;
                $row = 0;

                // Siapkan map kode/nama wilayah => id
                $allPW = $pdo->query("SELECT id, code, name FROM wilayah")->fetchAll();
                
                $stmtIns = $pdo->prepare("INSERT INTO daerah (pw_id, name, quota) VALUES (?, ?, ?)");

                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $row++;
                    if ($row === 1) continue; // Skip header

                    // Format CSV yang diharapkan:
                    // Kolom 0: Kode/Nama Wilayah (PW)
                    // Kolom 1: Nama Daerah (PD)
                    // Kolom 2: Kuota (opsional, default 5)
                    $pwIdentifier = trim($data[0] ?? '');
                    $pdName       = trim($data[1] ?? '');
                    $pdQuota      = !empty($data[2]) ? (int)$data[2] : 5;

                    if (empty($pwIdentifier) || empty($pdName)) continue;

                    // Match PW
                    $matchedPWId = null;
                    foreach ($allPW as $pwItem) {
                        if (strcasecmp($pwItem['code'], $pwIdentifier) === 0 || 
                            stripos($pwItem['name'], $pwIdentifier) !== false) {
                            $matchedPWId = $pwItem['id'];
                            break;
                        }
                    }

                    if ($matchedPWId) {
                        try {
                            $stmtIns->execute([$matchedPWId, $pdName, $pdQuota]);
                            $imported++;
                        } catch (Exception $e) {
                            // Lewati duplikat/error
                        }
                    }
                }
                fclose($handle);
                setFlash('success', "Berhasil mengimpor {$imported} data Pengurus Daerah (PD).");
            } else {
                setFlash('danger', "Gagal membaca file CSV yang diunggah.");
            }
        }
        header("Location: " . BASE_URL . "admin/daerah.php");
        exit;
    }
}

// Ambil daftar seluruh PW untuk dropdown filter & modal
$allPW = $pdo->query("SELECT id, code, name, province FROM wilayah ORDER BY name ASC")->fetchAll();

// Filter berdasarkan PW
$filterPW = (int)($_GET['pw_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];

if ($filterPW > 0) {
    $where[] = "d.pw_id = ?";
    $params[] = $filterPW;
}
if (!empty($search)) {
    $where[] = "(d.name LIKE ? OR w.name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql = "
    SELECT d.*, w.name as pw_name, w.code as pw_code,
           COUNT(p.id) as total_peserta
    FROM daerah d
    JOIN wilayah w ON d.pw_id = w.id
    LEFT JOIN participants p ON (p.daerah_id = d.id OR p.pd_name = d.name) AND p.pw_id = d.pw_id
    WHERE " . implode(" AND ", $where) . "
    GROUP BY d.id
    ORDER BY w.name ASC, d.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daerahList = $stmt->fetchAll();

$page_title = "Master Data Pengurus Daerah (PD)";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 mb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Master Pengurus Daerah (PD)</h1>
            <p class="text-xs text-slate-500 mt-0.5">Kelola daftar Pengurus Daerah di bawah naungan Pengurus Wilayah (PW) Al Khidmah</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="document.getElementById('modal-import-pd').classList.remove('hidden')" class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition flex items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Import CSV / Excel</span>
            </button>
            <button onclick="document.getElementById('modal-add-pd').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah PD Baru</span>
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded-3xl border border-slate-200/90 shadow-sm">
        <form action="" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="w-full sm:w-72">
                <select name="pw_id" onchange="this.form.submit()" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold">
                    <option value="">-- Seluruh Wilayah (PW) --</option>
                    <?php foreach ($allPW as $pw): ?>
                        <option value="<?= $pw['id'] ?>" <?= $filterPW === (int)$pw['id'] ? 'selected' : '' ?>>
                            <?= clean($pw['code']) ?> - <?= clean($pw['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-grow">
                <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Cari nama Pengurus Daerah..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <button type="submit" class="px-5 py-2 rounded-xl bg-slate-800 text-white font-bold text-xs hover:bg-slate-900 transition">
                Filter
            </button>

            <?php if ($filterPW > 0 || !empty($search)): ?>
                <a href="daerah.php" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-500 font-bold text-xs hover:bg-slate-50 transition flex items-center justify-center">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table Master PD -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="p-4 px-6 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span>Total <strong><?= count($daerahList) ?></strong> Pengurus Daerah terdaftar</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-6">No</th>
                        <th class="py-3 px-6">Nama Pengurus Daerah (PD)</th>
                        <th class="py-3 px-4">Asal Wilayah (PW)</th>
                        <th class="py-3 px-4 text-center">Kuota Peserta</th>
                        <th class="py-3 px-4 text-center">Peserta Terdaftar</th>
                        <th class="py-3 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($daerahList)): ?>
                        <tr><td colspan="6" class="text-center py-12 text-slate-400">Belum ada data Pengurus Daerah (PD). Silakan tambah atau import CSV.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($daerahList as $d): ?>
                            <?php 
                            $quota = (int)$d['quota'];
                            $terdaftar = (int)$d['total_peserta'];
                            $sisa = max(0, $quota - $terdaftar);
                            ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-6 font-mono text-slate-400"><?= $no++ ?></td>
                                
                                <td class="py-3.5 px-6 font-bold text-slate-900 text-sm">
                                    <?= clean($d['name']) ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-800"><?= clean($d['pw_name']) ?></div>
                                    <span class="inline-block px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700 font-mono text-[10px]"><?= clean($d['pw_code']) ?></span>
                                </td>

                                <td class="py-3.5 px-4 text-center font-bold font-mono text-slate-800">
                                    <?= $quota ?> Orang
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="font-bold text-emerald-800"><?= $terdaftar ?></span>
                                    <span class="text-slate-400">/ <?= $quota ?></span>
                                    <div class="text-[10px] <?= $sisa > 0 ? 'text-emerald-600' : 'text-rose-600' ?> font-semibold">
                                        <?= $sisa > 0 ? "Sisa {$sisa}" : 'Penuh' ?>
                                    </div>
                                </td>

                                <td class="py-3.5 px-6 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" 
                                            data-id="<?= $d['id'] ?>"
                                            data-pwid="<?= $d['pw_id'] ?>"
                                            data-name="<?= clean($d['name']) ?>"
                                            data-quota="<?= $d['quota'] ?>"
                                            onclick="openEditPDModal(this)"
                                            class="p-1.5 text-blue-700 hover:bg-blue-50 rounded-lg transition" title="Edit PD">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form action="" method="POST" onsubmit="return confirm('Hapus Pengurus Daerah <?= clean($d['name']) ?>?');" class="inline">
                                            <input type="hidden" name="action" value="delete_daerah">
                                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                            <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Hapus PD">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah PD Baru -->
<div id="modal-add-pd" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-base font-extrabold text-slate-900">Tambah Pengurus Daerah (PD) Baru</h3>
            <button onclick="document.getElementById('modal-add-pd').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create_daerah">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Pengurus Wilayah (PW) Induk *</label>
                <select name="pw_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold">
                    <option value="">-- Pilih Wilayah (PW) --</option>
                    <?php foreach ($allPW as $pw): ?>
                        <option value="<?= $pw['id'] ?>" <?= $filterPW === (int)$pw['id'] ? 'selected' : '' ?>>
                            <?= clean($pw['code']) ?> - <?= clean($pw['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Pengurus Daerah (PD) *</label>
                <input type="text" name="name" required placeholder="Contoh: PD Grobogan, PD Semarang" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kuota Maksimal Peserta *</label>
                <input type="number" name="quota" value="5" min="1" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                <p class="text-[11px] text-slate-400 mt-1">Sesuai ketentuan, kuota maksimal Pengurus Daerah adalah 5 orang.</p>
            </div>

            <div class="pt-3 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-add-pd').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-600">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-alkhidmah-800 text-white text-xs font-bold hover:bg-alkhidmah-900 shadow">Simpan PD</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit PD -->
<div id="modal-edit-pd" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-base font-extrabold text-slate-900">Edit Pengurus Daerah (PD)</h3>
            <button onclick="document.getElementById('modal-edit-pd').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_daerah">
            <input type="hidden" name="id" id="edit-pd-id">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Pengurus Wilayah (PW) Induk *</label>
                <select name="pw_id" id="edit-pd-pwid" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white font-semibold">
                    <?php foreach ($allPW as $pw): ?>
                        <option value="<?= $pw['id'] ?>">
                            <?= clean($pw['code']) ?> - <?= clean($pw['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Pengurus Daerah (PD) *</label>
                <input type="text" name="name" id="edit-pd-name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kuota Maksimal Peserta *</label>
                <input type="number" name="quota" id="edit-pd-quota" min="1" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div class="pt-3 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-edit-pd').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-600">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-alkhidmah-800 text-white text-xs font-bold hover:bg-alkhidmah-900 shadow">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import CSV PD -->
<div id="modal-import-pd" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-extrabold text-slate-900">Import Master Data PD dari File</h3>
            <button onclick="document.getElementById('modal-import-pd').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <p class="text-xs text-slate-600 mb-3 leading-relaxed">
            Format file CSV harus memiliki urutan kolom: <br>
            <code>Kode PW / Nama PW, Nama Daerah, Kuota (Opsional, default 5)</code>
        </p>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="import_daerah_csv">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Pilih File CSV *</label>
                <input type="file" name="csv_file" accept=".csv" required class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-slate-50">
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-import-pd').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-600">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-700 text-white text-xs font-bold hover:bg-emerald-800 shadow">Upload & Import</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditPDModal(btn) {
    document.getElementById('edit-pd-id').value = btn.dataset.id || '';
    document.getElementById('edit-pd-pwid').value = btn.dataset.pwid || '';
    document.getElementById('edit-pd-name').value = btn.dataset.name || '';
    document.getElementById('edit-pd-quota').value = btn.dataset.quota || 5;
    document.getElementById('modal-edit-pd').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
