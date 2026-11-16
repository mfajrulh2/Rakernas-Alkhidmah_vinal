<?php
/**
 * Manajemen Jadwal & Rundown Acara
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('superadmin');

$pdo = getDB();

// Handle form action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_schedule') {
        $date  = trim($_POST['session_date'] ?? '');
        $start = trim($_POST['start_time'] ?? '');
        $end   = trim($_POST['end_time'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $loc   = trim($_POST['location'] ?? '');
        $spk   = trim($_POST['speaker'] ?? '');
        $desc  = trim($_POST['description'] ?? '');

        if (!empty($date) && !empty($title)) {
            $stmt = $pdo->prepare("INSERT INTO schedules (session_date, start_time, end_time, title, location, speaker, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$date, $start, $end, $title, $loc, $spk, $desc]);
            setFlash('success', 'Agenda rundown berhasil ditambahkan.');
        }
        header("Location: " . BASE_URL . "admin/schedules.php");
        exit;
    } elseif ($action === 'delete_schedule') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM schedules WHERE id = ?")->execute([$id]);
        setFlash('info', 'Agenda rundown telah dihapus.');
        header("Location: " . BASE_URL . "admin/schedules.php");
        exit;
    }
}

$schedules = $pdo->query("SELECT * FROM schedules ORDER BY session_date ASC, start_time ASC")->fetchAll();

$page_title = "Manajemen Rundown Acara";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8 mb-16">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Manajemen Rundown & Agenda</h1>
            <p class="text-xs text-slate-500 mt-0.5">Kelola seluruh rangkaian sidang pleno, komisi, dan majlis doa</p>
        </div>
        <button onclick="document.getElementById('modal-add-schedule').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-sm flex items-center gap-1.5 self-start">
            <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Tambah Agenda Baru</span>
        </button>
    </div>

    <!-- Table Schedules -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-6">Tanggal & Waktu</th>
                        <th class="py-3 px-6">Agenda Kegiatan</th>
                        <th class="py-3 px-4">Lokasi Ruangan</th>
                        <th class="py-3 px-4">Narasumber / Pimpinan</th>
                        <th class="py-3 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($schedules)): ?>
                        <tr><td colspan="5" class="py-8 text-center text-slate-400">Belum ada agenda kegiatan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $s): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-6 whitespace-nowrap">
                                    <div class="font-bold text-slate-800"><?= formatTanggalIndo($s['session_date']) ?></div>
                                    <div class="font-mono text-emerald-700 text-[11px] font-bold mt-0.5">
                                        <?= substr($s['start_time'], 0, 5) ?> - <?= substr($s['end_time'], 0, 5) ?> WIB
                                    </div>
                                </td>

                                <td class="py-3.5 px-6">
                                    <div class="font-bold text-slate-900 text-sm"><?= clean($s['title']) ?></div>
                                    <?php if (!empty($s['description'])): ?>
                                        <div class="text-[11px] text-slate-500 mt-1"><?= clean($s['description']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                                        <?= clean($s['location']) ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-medium text-slate-700"><?= clean($s['speaker'] ?: '-') ?></div>
                                </td>

                                <td class="py-3.5 px-6 text-right whitespace-nowrap">
                                    <form action="" method="POST" onsubmit="return confirm('Hapus agenda ini?');" class="inline">
                                        <input type="hidden" name="action" value="delete_schedule">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Agenda -->
<div id="modal-add-schedule" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
            <h3 class="text-lg font-extrabold text-slate-900">Tambah Agenda Rundown</h3>
            <button onclick="document.getElementById('modal-add-schedule').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create_schedule">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tanggal *</label>
                <input type="date" name="session_date" required value="2026-11-15" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Waktu Mulai *</label>
                    <input type="time" name="start_time" required value="08:00" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Waktu Selesai *</label>
                    <input type="time" name="end_time" required value="11:30" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Agenda / Sesi *</label>
                <input type="text" name="title" required placeholder="Contoh: Sidang Pleno I" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ruangan / Tempat</label>
                <input type="text" name="location" required placeholder="Contoh: Aula Utama Al Fithrah" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Narasumber / Pimpinan</label>
                <input type="text" name="speaker" placeholder="Contoh: Presidium Sidang Pleno" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Deskripsi Singkat</label>
                <textarea name="description" rows="2" placeholder="Penjelasan agenda kegiatan..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none"></textarea>
            </div>

            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-add-schedule').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-600">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-alkhidmah-800 text-white text-xs font-bold hover:bg-alkhidmah-900">Simpan Agenda</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
