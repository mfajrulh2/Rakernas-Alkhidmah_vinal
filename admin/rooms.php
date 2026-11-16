<?php
/**
 * Manajemen Kamar & Penginapan
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('superadmin');

$pdo = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_room') {
        $b_name   = trim($_POST['building_name'] ?? '');
        $r_number = trim($_POST['room_number'] ?? '');
        $gender   = trim($_POST['gender'] ?? 'L');
        $capacity = (int)($_POST['capacity'] ?? 4);
        $notes    = trim($_POST['notes'] ?? '');

        if (!empty($b_name) && !empty($r_number)) {
            $stmt = $pdo->prepare("INSERT INTO rooms (building_name, room_number, gender, capacity, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$b_name, $r_number, $gender, $capacity, $notes]);
            setFlash('success', "Kamar {$b_name} - {$r_number} berhasil ditambahkan.");
        }
        header("Location: " . BASE_URL . "admin/rooms.php");
        exit;
    } elseif ($action === 'delete_room') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        // Lepas relasi kamar dari peserta dulu
        $pdo->prepare("UPDATE participants SET room_id = NULL WHERE room_id = ?")->execute([$roomId]);
        $pdo->prepare("DELETE FROM rooms WHERE id = ?")->execute([$roomId]);
        setFlash('info', "Kamar telah dihapus dan peserta di dalamnya telah diatur belum berpenghuni.");
        header("Location: " . BASE_URL . "admin/rooms.php");
        exit;
    } elseif ($action === 'remove_occupant') {
        $partId = (int)($_POST['participant_id'] ?? 0);
        $pdo->prepare("UPDATE participants SET room_id = NULL WHERE id = ?")->execute([$partId]);
        setFlash('success', "Peserta berhasil dikeluarkan dari kamar.");
        header("Location: " . BASE_URL . "admin/rooms.php");
        exit;
    }
}

// Ambil list kamar beserta jumlah penghuni
$rooms = $pdo->query("
    SELECT r.*, COUNT(p.id) as total_occupants
    FROM rooms r
    LEFT JOIN participants p ON r.id = p.room_id
    GROUP BY r.id
    ORDER BY r.building_name ASC, r.room_number ASC
")->fetchAll();

// Ambil penghuni per kamar untuk popover/detail
$stmtOcc = $pdo->query("
    SELECT p.id, p.full_name, p.room_id, p.gender, p.pd_name, w.code as pw_code
    FROM participants p
    JOIN wilayah w ON p.pw_id = w.id
    WHERE p.room_id IS NOT NULL
");
$allOccupants = $stmtOcc->fetchAll();
$occupantsByRoom = [];
foreach ($allOccupants as $occ) {
    $occupantsByRoom[$occ['room_id']][] = $occ;
}

$page_title = "Manajemen Kamar & Akomodasi";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8 mb-16">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Manajemen Kamar & Akomodasi</h1>
            <p class="text-xs text-slate-500 mt-0.5">Atur wisma/hotel penginapan delegasi ikhwan & akhwat</p>
        </div>
        <button onclick="document.getElementById('modal-add-room').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-sm flex items-center gap-1.5 self-start">
            <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Tambah Kamar Baru</span>
        </button>
    </div>

    <!-- Grid Kamar -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($rooms)): ?>
            <div class="col-span-full bg-white p-12 rounded-3xl border border-slate-200 text-center text-slate-400">
                Belum ada data kamar. Klik tombol "Tambah Kamar Baru" di atas.
            </div>
        <?php else: ?>
            <?php foreach ($rooms as $r): ?>
                <?php 
                $isFull = $r['total_occupants'] >= $r['capacity'];
                $genderBadge = $r['gender'] === 'L' ? '<span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-[10px] font-bold">Ikhwan</span>' : '<span class="bg-rose-50 text-rose-700 px-2 py-0.5 rounded text-[10px] font-bold">Akhwat</span>';
                $occupants = $occupantsByRoom[$r['id']] ?? [];
                ?>
                <div class="bg-white rounded-3xl border <?= $isFull ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-200/90' ?> p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <span class="text-xs font-semibold text-slate-400"><?= clean($r['building_name']) ?></span>
                                <h3 class="text-lg font-extrabold text-slate-900 leading-tight"><?= clean($r['room_number']) ?></h3>
                            </div>
                            <div>
                                <?= $genderBadge ?>
                            </div>
                        </div>

                        <!-- Progress Kapasitas -->
                        <div class="my-3">
                            <div class="flex justify-between text-xs font-semibold mb-1">
                                <span class="text-slate-500">Terisi:</span>
                                <span class="<?= $isFull ? 'text-emerald-700 font-bold' : 'text-slate-700' ?>"><?= $r['total_occupants'] ?> / <?= $r['capacity'] ?> Bed</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="<?= $isFull ? 'bg-emerald-600' : 'bg-indigo-600' ?> h-2 rounded-full" style="width: <?= min(100, round(($r['total_occupants'] / max(1, $r['capacity'])) * 100)) ?>%"></div>
                            </div>
                        </div>

                        <?php if (!empty($r['notes'])): ?>
                            <div class="text-[11px] text-slate-400 mb-4 italic">"<?= clean($r['notes']) ?>"</div>
                        <?php endif; ?>

                        <!-- Daftar Penghuni -->
                        <div class="border-t border-slate-100 pt-3 mt-3">
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Penghuni Kamar:</div>
                            <?php if (empty($occupants)): ?>
                                <div class="text-xs text-slate-400 italic">Kamar masih kosong.</div>
                            <?php else: ?>
                                <div class="space-y-1.5">
                                    <?php foreach ($occupants as $occ): ?>
                                        <div class="flex items-center justify-between text-xs bg-slate-50 p-2 rounded-xl border border-slate-100">
                                            <div>
                                                <div class="font-bold text-slate-800"><?= clean($occ['full_name']) ?></div>
                                                <div class="text-[10px] text-slate-400"><?= clean($occ['pd_name'] ?: $occ['pw_code']) ?></div>
                                            </div>
                                            <form action="" method="POST" onsubmit="return confirm('Keluarkan <?= clean($occ['full_name']) ?> dari kamar ini?');">
                                                <input type="hidden" name="action" value="remove_occupant">
                                                <input type="hidden" name="participant_id" value="<?= $occ['id'] ?>">
                                                <button type="submit" class="text-rose-500 hover:text-rose-700 p-1 text-[11px] font-bold" title="Keluarkan">✕</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Delete Room Button -->
                    <div class="border-t border-slate-100 pt-4 mt-4 flex justify-end">
                        <form action="" method="POST" onsubmit="return confirm('Hapus kamar <?= clean($r['room_number']) ?>?');">
                            <input type="hidden" name="action" value="delete_room">
                            <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="text-xs text-slate-400 hover:text-rose-600 transition">Hapus Kamar</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah Kamar -->
<div id="modal-add-room" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
            <h3 class="text-lg font-extrabold text-slate-900">Tambah Kamar Penginapan</h3>
            <button onclick="document.getElementById('modal-add-room').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create_room">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Gedung / Wisma *</label>
                <input type="text" name="building_name" required placeholder="Contoh: Gedung A / Wisma Santri" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nomor / Nama Kamar *</label>
                <input type="text" name="room_number" required placeholder="Contoh: Kamar 101 atau VIP 1" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Peruntukan Gender</label>
                    <select name="gender" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none bg-white">
                        <option value="L">Ikhwan (Pria)</option>
                        <option value="P">Akhwat (Wanita)</option>
                        <option value="Campur">Campur / Bebas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kapasitas Tempat Tidur</label>
                    <input type="number" name="capacity" value="4" min="1" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Fasilitas / Catatan</label>
                <input type="text" name="notes" placeholder="Contoh: AC, Kamar Mandi Dalam, Kasur Busa" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-add-room').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-600">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-alkhidmah-800 text-white text-xs font-bold hover:bg-alkhidmah-900">Simpan Kamar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
