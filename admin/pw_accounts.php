<?php
/**
 * Manajemen Akun PW & Kuota Delegasi
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('superadmin');

$pdo = getDB();

// Handle Form Submit: Tambah / Edit / Hapus PW & Akun Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_pw') {
        $code           = strtoupper(trim($_POST['code'] ?? ''));
        $name           = trim($_POST['name'] ?? '');
        $province       = trim($_POST['province'] ?? '');
        $quota_utusan   = (int)($_POST['quota_utusan'] ?? 10);
        $quota_peninjau = (int)($_POST['quota_peninjau'] ?? 5);
        $username       = strtolower(trim($_POST['username'] ?? ''));
        $password       = trim($_POST['password'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $contact_phone  = trim($_POST['contact_phone'] ?? '');

        if (empty($code) || empty($name) || empty($username) || empty($password)) {
            setFlash('danger', 'Kode PW, Nama Wilayah, Username, dan Password wajib diisi.');
        } else {
            try {
                $pdo->beginTransaction();

                // Insert Wilayah
                $regToken = bin2hex(random_bytes(16));
                $stmtW = $pdo->prepare("
                    INSERT INTO wilayah (code, name, province, quota_utusan, quota_peninjau, contact_person, contact_phone, reg_token)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtW->execute([$code, $name, $province, $quota_utusan, $quota_peninjau, $contact_person, $contact_phone, $regToken]);
                $pw_id = $pdo->lastInsertId();

                // Insert User Login Admin PW
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtU = $pdo->prepare("
                    INSERT INTO users (username, password, full_name, role, pw_id, phone)
                    VALUES (?, ?, ?, 'admin_pw', ?, ?)
                ");
                $stmtU->execute([$username, $hash, 'Admin ' . $name, $pw_id, $contact_phone]);

                $pdo->commit();
                setFlash('success', "Akun PW {$name} berhasil dibuat dengan username: {$username}");
                header("Location: " . BASE_URL . "admin/pw_accounts.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', 'Gagal membuat akun PW: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'edit_pw') {
        $pw_id          = (int)($_POST['pw_id'] ?? 0);
        $code           = strtoupper(trim($_POST['code'] ?? ''));
        $name           = trim($_POST['name'] ?? '');
        $province       = trim($_POST['province'] ?? '');
        $quota_utusan   = (int)($_POST['quota_utusan'] ?? 10);
        $quota_peninjau = (int)($_POST['quota_peninjau'] ?? 5);
        $contact_person = trim($_POST['contact_person'] ?? '');
        $contact_phone  = trim($_POST['contact_phone'] ?? '');
        $username       = strtolower(trim($_POST['username'] ?? ''));
        $new_password   = trim($_POST['new_password'] ?? '');

        if (empty($code) || empty($name)) {
            setFlash('danger', 'Kode PW dan Nama Wilayah wajib diisi.');
        } else {
            try {
                $pdo->beginTransaction();

                // Update Wilayah
                $stmtW = $pdo->prepare("
                    UPDATE wilayah SET
                        code = ?, name = ?, province = ?, quota_utusan = ?, 
                        quota_peninjau = ?, contact_person = ?, contact_phone = ?
                    WHERE id = ?
                ");
                $stmtW->execute([$code, $name, $province, $quota_utusan, $quota_peninjau, $contact_person, $contact_phone, $pw_id]);

                // Update atau buat user login PW
                if (!empty($username)) {
                    $stmtCheckU = $pdo->prepare("SELECT id FROM users WHERE pw_id = ? AND role = 'admin_pw' LIMIT 1");
                    $stmtCheckU->execute([$pw_id]);
                    $existingUser = $stmtCheckU->fetch();

                    if ($existingUser) {
                        if (!empty($new_password)) {
                            $hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmtU = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, phone = ? WHERE id = ?");
                            $stmtU->execute([$username, $hash, 'Admin ' . $name, $contact_phone, $existingUser['id']]);
                        } else {
                            $stmtU = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, phone = ? WHERE id = ?");
                            $stmtU->execute([$username, 'Admin ' . $name, $contact_phone, $existingUser['id']]);
                        }
                    } else {
                        $pass = !empty($new_password) ? $new_password : 'pw123';
                        $hash = password_hash($pass, PASSWORD_DEFAULT);
                        $stmtU = $pdo->prepare("INSERT INTO users (username, password, full_name, role, pw_id, phone) VALUES (?, ?, ?, 'admin_pw', ?, ?)");
                        $stmtU->execute([$username, $hash, 'Admin ' . $name, $pw_id, $contact_phone]);
                    }
                }

                $pdo->commit();
                setFlash('success', "Data Pengurus Wilayah {$name} berhasil diperbarui.");
                header("Location: " . BASE_URL . "admin/pw_accounts.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', 'Gagal memperbarui data PW: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete_pw') {
        $pw_id = (int)($_POST['pw_id'] ?? 0);
        if ($pw_id > 0) {
            try {
                $pdo->beginTransaction();
                // Ambil nama wilayah
                $stmtName = $pdo->prepare("SELECT name FROM wilayah WHERE id = ?");
                $stmtName->execute([$pw_id]);
                $delName = $stmtName->fetchColumn();

                if ($delName) {
                    // Hapus user login PW terkait
                    $pdo->prepare("DELETE FROM users WHERE pw_id = ?")->execute([$pw_id]);
                    // Hapus delegasi peserta terkait
                    $pdo->prepare("DELETE FROM participants WHERE pw_id = ?")->execute([$pw_id]);
                    // Hapus wilayah
                    $pdo->prepare("DELETE FROM wilayah WHERE id = ?")->execute([$pw_id]);

                    $pdo->commit();
                    setFlash('info', "Pengurus Wilayah {$delName} beserta akun dan delegasi terkait telah berhasil dihapus.");
                } else {
                    $pdo->rollBack();
                    setFlash('danger', 'Data Pengurus Wilayah tidak ditemukan.');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', 'Gagal menghapus data PW: ' . $e->getMessage());
            }
        }
        header("Location: " . BASE_URL . "admin/pw_accounts.php");
        exit;
    } elseif ($action === 'reset_password') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_pass = trim($_POST['new_password'] ?? '');
        if ($user_id > 0 && !empty($new_pass)) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'admin_pw'");
            $stmt->execute([$hash, $user_id]);
            setFlash('success', 'Password akun PW berhasil direset.');
        }
        header("Location: " . BASE_URL . "admin/pw_accounts.php");
        exit;
    } elseif ($action === 'update_quota') {
        $pw_id = (int)($_POST['pw_id'] ?? 0);
        $quota_u = (int)($_POST['quota_utusan'] ?? 10);
        $quota_p = (int)($_POST['quota_peninjau'] ?? 5);

        $stmt = $pdo->prepare("UPDATE wilayah SET quota_utusan = ?, quota_peninjau = ? WHERE id = ?");
        $stmt->execute([$quota_u, $quota_p, $pw_id]);
        setFlash('success', 'Kuota delegasi PW berhasil diperbarui.');
        header("Location: " . BASE_URL . "admin/pw_accounts.php");
        exit;
    } elseif ($action === 'regenerate_token') {
        $pw_id = (int)($_POST['pw_id'] ?? 0);
        if ($pw_id > 0) {
            $newToken = regeneratePWRegToken($pw_id);
            setFlash('success', 'Tautan registrasi khusus PW berhasil diperbarui / diregenerate.');
        }
        header("Location: " . BASE_URL . "admin/pw_accounts.php");
        exit;
    }
}

// Ambil list seluruh PW beserta akun loginnya & jumlah delegasi terdaftar
$sql = "
    SELECT w.*, 
           u.id as user_id, u.username, u.full_name as user_name, u.is_active,
           COUNT(p.id) as total_terdaftar,
           SUM(CASE WHEN p.participant_type = 'utusan' THEN 1 ELSE 0 END) as utusan_terdaftar,
           SUM(CASE WHEN p.participant_type = 'peninjau' THEN 1 ELSE 0 END) as peninjau_terdaftar
    FROM wilayah w
    LEFT JOIN users u ON w.id = u.pw_id AND u.role = 'admin_pw'
    LEFT JOIN participants p ON w.id = p.pw_id
    GROUP BY w.id
    ORDER BY w.name ASC
";
$pwList = $pdo->query($sql)->fetchAll();

$page_title = "Manajemen Akun PW & Kuota";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8 mb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Akun Pengurus Wilayah (PW)</h1>
            <p class="text-xs text-slate-500 mt-0.5">Kelola akun akses login mandiri PW, ubah data wilayah, dan batasan kuota utusan/peninjau</p>
        </div>
        <button onclick="document.getElementById('modal-add-pw').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-sm flex items-center gap-1.5 self-start">
            <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Tambah Wilayah & Akun Baru</span>
        </button>
    </div>

    <!-- Banner Fitur Link Khusus Registrasi Tanpa Login -->
    <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-amber-50 border border-emerald-200 rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                <svg class="w-5 h-5 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            </div>
            <div>
                <h3 class="text-sm font-extrabold text-slate-900">Link Pendaftaran Khusus PW (Tanpa Perlu Login)</h3>
                <p class="text-xs text-slate-600 mt-0.5">
                    Setiap PW memiliki tautan khusus berbasis token rahasia. Panitia Pusat dapat menyalin atau mengirimkan link tersebut via WhatsApp agar Pengurus Daerah/Wilayah dapat langsung mendaftarkan delegasi tanpa perlu login akun.
                </p>
            </div>
        </div>
        <div class="text-xs font-semibold text-emerald-800 bg-white/80 border border-emerald-200 px-3.5 py-2 rounded-xl shrink-0 self-start md:self-auto">
            Klik tombol <strong>"Link Khusus"</strong> pada baris PW
        </div>
    </div>

    <!-- PW Table -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3.5 px-6">Nama Pengurus Wilayah (PW)</th>
                        <th class="py-3.5 px-4">Akun Login</th>
                        <th class="py-3.5 px-4">Kontak Person</th>
                        <th class="py-3.5 px-4 text-center">Kuota Utusan</th>
                        <th class="py-3.5 px-4 text-center">Kuota Peninjau</th>
                        <th class="py-3.5 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($pwList as $pw): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-4 px-6">
                                <div class="font-bold text-slate-900 text-sm"><?= clean($pw['name']) ?></div>
                                <div class="text-[10px] font-mono text-emerald-700 font-bold mt-0.5"><?= clean($pw['code']) ?> • <?= clean($pw['province']) ?></div>
                            </td>

                            <td class="py-4 px-4">
                                <?php if (!empty($pw['username'])): ?>
                                    <div class="font-mono text-xs font-bold text-slate-800 bg-slate-100 px-2.5 py-1 rounded-md inline-block">
                                        <?= clean($pw['username']) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-rose-600 italic">Belum dibuat</span>
                                <?php endif; ?>
                            </td>

                            <td class="py-4 px-4">
                                <div class="font-medium text-slate-800"><?= clean($pw['contact_person'] ?: '-') ?></div>
                                <div class="text-[10px] text-slate-400 font-mono"><?= clean($pw['contact_phone'] ?: '-') ?></div>
                            </td>

                            <td class="py-4 px-4 text-center">
                                <span class="font-bold text-emerald-800 text-sm"><?= (int)$pw['utusan_terdaftar'] ?></span>
                                <span class="text-slate-400 text-xs">/ <?= (int)$pw['quota_utusan'] ?></span>
                            </td>

                            <td class="py-4 px-4 text-center">
                                <span class="font-bold text-amber-800 text-sm"><?= (int)$pw['peninjau_terdaftar'] ?></span>
                                <span class="text-slate-400 text-xs">/ <?= (int)$pw['quota_peninjau'] ?></span>
                            </td>

                            <td class="py-4 px-6 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Link Khusus Registrasi Button -->
                                    <?php 
                                    $regUrl = getPWRegistrationUrl($pw['reg_token'] ?? '');
                                    $waPhone = preg_replace('/[^0-9]/', '', $pw['contact_phone'] ?? '');
                                    if (!empty($waPhone) && substr($waPhone, 0, 1) === '0') {
                                        $waPhone = '62' . substr($waPhone, 1);
                                    }
                                    $waMsg = "Assalamu'alaikum Wr. Wb.\n\nYth. Pengurus Wilayah Al Khidmah *" . clean($pw['name']) . "*,\n\nBerikut tautan resmi pendaftaran delegasi RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH (Dapat langsung diisi tanpa perlu login):\n\n" . $regUrl . "\n\nKuota Utusan: " . (int)$pw['quota_utusan'] . " orang\nKuota Peninjau: " . (int)$pw['quota_peninjau'] . " orang\n\nMohon mengisi pendaftaran sebelum batas waktu penutupan. Terima kasih.\nPanitia Pelaksana RAKERNAS";
                                    $waLink = !empty($waPhone) ? "https://api.whatsapp.com/send?phone={$waPhone}&text=" . urlencode($waMsg) : '';
                                    ?>
                                    <button type="button"
                                        data-pwname="<?= clean($pw['name']) ?>"
                                        data-pwid="<?= $pw['id'] ?>"
                                        data-regurl="<?= clean($regUrl) ?>"
                                        data-walink="<?= clean($waLink) ?>"
                                        data-waphone="<?= clean($pw['contact_phone'] ?? '') ?>"
                                        onclick="openRegLinkModalFromBtn(this)"
                                        title="Link Pendaftaran Khusus PW (Tanpa Login)"
                                        class="p-1.5 px-2 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-300 rounded-lg transition text-xs font-bold flex items-center gap-1 shadow-xs">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                        <span>Link Khusus</span>
                                    </button>

                                    <!-- Edit Button (Menggunakan data-* agar aman dari tanda petik Jama'ah) -->
                                    <button type="button"
                                        data-id="<?= $pw['id'] ?>"
                                        data-code="<?= clean($pw['code']) ?>"
                                        data-name="<?= clean($pw['name']) ?>"
                                        data-province="<?= clean($pw['province']) ?>"
                                        data-quotau="<?= (int)$pw['quota_utusan'] ?>"
                                        data-quotap="<?= (int)$pw['quota_peninjau'] ?>"
                                        data-contactperson="<?= clean($pw['contact_person'] ?? '') ?>"
                                        data-contactphone="<?= clean($pw['contact_phone'] ?? '') ?>"
                                        data-username="<?= clean($pw['username'] ?? '') ?>"
                                        onclick="openEditModalFromBtn(this)"
                                        title="Edit Data PW & Akun"
                                        class="p-1.5 text-slate-500 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition text-xs font-semibold flex items-center gap-1">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        <span>Edit</span>
                                    </button>

                                    <!-- Atur Kuota Button -->
                                    <button type="button"
                                        data-id="<?= $pw['id'] ?>"
                                        data-name="<?= clean($pw['name']) ?>"
                                        data-quotau="<?= (int)$pw['quota_utusan'] ?>"
                                        data-quotap="<?= (int)$pw['quota_peninjau'] ?>"
                                        onclick="openQuotaModalFromBtn(this)"
                                        title="Ubah Kuota"
                                        class="p-1.5 text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition text-xs font-semibold">
                                        Kuota
                                    </button>

                                    <!-- Reset Sandi Button -->
                                    <?php if (!empty($pw['user_id'])): ?>
                                        <button type="button"
                                            data-userid="<?= $pw['user_id'] ?>"
                                            data-username="<?= clean($pw['username']) ?>"
                                            onclick="openResetModalFromBtn(this)"
                                            title="Reset Password"
                                            class="p-1.5 text-slate-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition text-xs font-semibold">
                                            Sandi
                                        </button>
                                    <?php endif; ?>

                                    <!-- Delegasi Link -->
                                    <a href="<?= BASE_URL ?>admin/participants.php?pw_id=<?= $pw['id'] ?>" class="p-1.5 text-slate-500 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition text-xs font-semibold">
                                        Delegasi &rarr;
                                    </a>

                                    <!-- Delete Button -->
                                    <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Pengurus Wilayah ini?\n\nPERHATIAN: Seluruh data delegasi dan akun login wilayah ini juga akan ikut terhapus!');" class="inline">
                                        <input type="hidden" name="action" value="delete_pw">
                                        <input type="hidden" name="pw_id" value="<?= $pw['id'] ?>">
                                        <button type="submit" title="Hapus PW" class="p-1.5 text-slate-400 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition">
                                            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah PW & Akun Baru -->
<div id="modal-add-pw" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
            <h3 class="text-lg font-extrabold text-slate-900">Tambah PW & Akun Login</h3>
            <button onclick="document.getElementById('modal-add-pw').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create_pw">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kode PW *</label>
                    <input type="text" name="code" required placeholder="PW-PAPUA" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Provinsi *</label>
                    <input type="text" name="province" required placeholder="Papua Barat" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Wilayah *</label>
                <input type="text" name="name" required placeholder="PW Jama'ah Al Khidmah Papua Barat" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kuota Utusan</label>
                    <input type="number" name="quota_utusan" value="10" min="1" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kuota Peninjau</label>
                    <input type="number" name="quota_peninjau" value="5" min="0" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama PIC PW</label>
                    <input type="text" name="contact_person" placeholder="Ust. Ahmad" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">No. WhatsApp</label>
                    <input type="text" name="contact_phone" placeholder="08123456789" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Username Login *</label>
                    <input type="text" name="username" required placeholder="pw_papua" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password Login *</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-add-pw').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-600">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-alkhidmah-800 text-white text-xs font-bold hover:bg-alkhidmah-900">Simpan PW Baru</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit PW & Akun Login -->
<div id="modal-edit-pw" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
            <div>
                <h3 class="text-lg font-extrabold text-slate-900">Edit Data Pengurus Wilayah</h3>
                <p class="text-xs text-slate-500 mt-0.5">Perbarui informasi wilayah, kuota, atau akun login</p>
            </div>
            <button onclick="document.getElementById('modal-edit-pw').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_pw">
            <input type="hidden" name="pw_id" id="edit-pw-id">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kode PW *</label>
                    <input type="text" name="code" id="edit-pw-code" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Provinsi *</label>
                    <input type="text" name="province" id="edit-pw-province" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Wilayah *</label>
                <input type="text" name="name" id="edit-pw-name" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kuota Utusan</label>
                    <input type="number" name="quota_utusan" id="edit-pw-quota-u" min="1" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kuota Peninjau</label>
                    <input type="number" name="quota_peninjau" id="edit-pw-quota-p" min="0" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama PIC PW</label>
                    <input type="text" name="contact_person" id="edit-pw-contact-person" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">No. WhatsApp</label>
                    <input type="text" name="contact_phone" id="edit-pw-contact-phone" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Username Login</label>
                    <input type="text" name="username" id="edit-pw-username" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password Baru (Opsional)</label>
                    <input type="password" name="new_password" placeholder="Kosongkan jika tidak diubah" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-edit-pw').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-600">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-alkhidmah-800 text-white text-xs font-bold hover:bg-alkhidmah-900">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reset Password -->
<div id="modal-reset-pwd" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200">
        <h3 class="text-base font-extrabold text-slate-900 mb-2">Reset Password Akun PW</h3>
        <p class="text-xs text-slate-500 mb-4">Username: <span id="reset-user-text" class="font-mono font-bold text-slate-800"></span></p>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset-user-id">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password Baru</label>
                <input type="password" name="new_password" required placeholder="Masukkan password baru" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-reset-pwd').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-600">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 text-white text-xs font-bold hover:bg-amber-700">Simpan Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Atur Kuota -->
<div id="modal-quota" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200">
        <h3 class="text-base font-extrabold text-slate-900 mb-2">Atur Kuota Delegasi</h3>
        <p class="text-xs text-slate-500 mb-4" id="quota-pw-name"></p>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_quota">
            <input type="hidden" name="pw_id" id="quota-pw-id">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Maksimal Utusan (Hak Suara)</label>
                <input type="number" name="quota_utusan" id="quota-u-val" min="1" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Maksimal Peninjau</label>
                <input type="number" name="quota_peninjau" id="quota-p-val" min="0" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-quota').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-600">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-alkhidmah-800 text-white text-xs font-bold hover:bg-alkhidmah-900">Perbarui Kuota</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Link Khusus Registrasi PW -->
<div id="modal-reg-link" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Link Pendaftaran Khusus PW</h3>
                    <p class="text-[11px] text-emerald-700 font-bold" id="reglink-pw-name">PW Al Khidmah</p>
                </div>
            </div>
            <button onclick="document.getElementById('modal-reg-link').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 font-bold text-lg leading-none">✕</button>
        </div>

        <div class="space-y-4">
            <p class="text-xs text-slate-600 leading-relaxed">
                Tautan ini dapat langsung dibuka oleh Pengurus Wilayah/Daerah tanpa perlu memasukkan password login. Kuota tetap terkontrol dan tersimpan otomatis ke akun PW.
            </p>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tautan URL Registrasi:</label>
                <div class="flex gap-2">
                    <input type="text" id="reglink-input" readonly class="flex-grow px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-mono text-slate-800 focus:outline-none select-all">
                    <button type="button" onclick="copyRegLink()" class="px-4 py-2.5 rounded-xl bg-slate-800 text-white font-bold text-xs hover:bg-slate-900 transition flex items-center gap-1.5 shrink-0 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                        <span>Salin</span>
                    </button>
                </div>
                <div id="copy-toast" class="hidden text-[11px] text-emerald-600 font-bold mt-1">✓ Berhasil disalin ke clipboard!</div>
            </div>

            <!-- Tombol Kirim WA & Buka -->
            <div class="flex flex-col sm:flex-row gap-2 pt-2">
                <a id="reglink-btn-wa" href="#" target="_blank" class="flex-1 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold text-center flex items-center justify-center gap-2 shadow-sm transition">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.1.824z"/></svg>
                    <span>Kirim via WhatsApp</span>
                </a>
                <a id="reglink-btn-open" href="#" target="_blank" class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold text-center flex items-center justify-center gap-1.5 transition">
                    <span>Buka Tautan</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>

            <!-- Regenerate Token -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                <span>Ingin ganti link baru?</span>
                <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membuat ulang (regenerate) token link registrasi ini?\n\nTautan lama yang sudah dibagikan sebelumnya tidak akan dapat diakses lagi!');">
                    <input type="hidden" name="action" value="regenerate_token">
                    <input type="hidden" name="pw_id" id="reglink-regen-pwid" value="">
                    <button type="submit" class="text-rose-600 hover:text-rose-800 font-bold hover:underline">
                        🔄 Buat Ulang Token
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openRegLinkModalFromBtn(btn) {
    const pwName = btn.dataset.pwname || '';
    const pwId = btn.dataset.pwid || '';
    const regUrl = btn.dataset.regurl || '';
    const waLink = btn.dataset.walink || '';

    document.getElementById('reglink-pw-name').innerText = pwName;
    document.getElementById('reglink-input').value = regUrl;
    document.getElementById('reglink-btn-open').href = regUrl;
    document.getElementById('reglink-regen-pwid').value = pwId;

    const waBtn = document.getElementById('reglink-btn-wa');
    if (waLink) {
        waBtn.href = waLink;
        waBtn.classList.remove('hidden');
    } else {
        waBtn.classList.add('hidden');
    }

    document.getElementById('modal-reg-link').classList.remove('hidden');
}

function copyRegLink() {
    const input = document.getElementById('reglink-input');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const toast = document.getElementById('copy-toast');
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 2500);
    });
}
function openEditModalFromBtn(btn) {
    document.getElementById('edit-pw-id').value = btn.dataset.id || '';
    document.getElementById('edit-pw-code').value = btn.dataset.code || '';
    document.getElementById('edit-pw-name').value = btn.dataset.name || '';
    document.getElementById('edit-pw-province').value = btn.dataset.province || '';
    document.getElementById('edit-pw-quota-u').value = btn.dataset.quotau || 10;
    document.getElementById('edit-pw-quota-p').value = btn.dataset.quotap || 5;
    document.getElementById('edit-pw-contact-person').value = btn.dataset.contactperson || '';
    document.getElementById('edit-pw-contact-phone').value = btn.dataset.contactphone || '';
    document.getElementById('edit-pw-username').value = btn.dataset.username || '';
    document.getElementById('modal-edit-pw').classList.remove('hidden');
}

function openQuotaModalFromBtn(btn) {
    document.getElementById('quota-pw-id').value = btn.dataset.id || '';
    document.getElementById('quota-pw-name').innerText = btn.dataset.name || '';
    document.getElementById('quota-u-val').value = btn.dataset.quotau || 10;
    document.getElementById('quota-p-val').value = btn.dataset.quotap || 5;
    document.getElementById('modal-quota').classList.remove('hidden');
}

function openResetModalFromBtn(btn) {
    document.getElementById('reset-user-id').value = btn.dataset.userid || '';
    document.getElementById('reset-user-text').innerText = btn.dataset.username || '';
    document.getElementById('modal-reset-pwd').classList.remove('hidden');
}

// Fallback functions
function openEditModal(pw) {
    document.getElementById('edit-pw-id').value = pw.id;
    document.getElementById('edit-pw-code').value = pw.code || '';
    document.getElementById('edit-pw-name').value = pw.name || '';
    document.getElementById('edit-pw-province').value = pw.province || '';
    document.getElementById('edit-pw-quota-u').value = pw.quota_utusan || 10;
    document.getElementById('edit-pw-quota-p').value = pw.quota_peninjau || 5;
    document.getElementById('edit-pw-contact-person').value = pw.contact_person || '';
    document.getElementById('edit-pw-contact-phone').value = pw.contact_phone || '';
    document.getElementById('edit-pw-username').value = pw.username || '';
    document.getElementById('modal-edit-pw').classList.remove('hidden');
}

function openResetModal(userId, username) {
    document.getElementById('reset-user-id').value = userId;
    document.getElementById('reset-user-text').innerText = username;
    document.getElementById('modal-reset-pwd').classList.remove('hidden');
}

function openQuotaModal(pwId, pwName, quotaU, quotaP) {
    document.getElementById('quota-pw-id').value = pwId;
    document.getElementById('quota-pw-name').innerText = pwName;
    document.getElementById('quota-u-val').value = quotaU;
    document.getElementById('quota-p-val').value = quotaP;
    document.getElementById('modal-quota').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
