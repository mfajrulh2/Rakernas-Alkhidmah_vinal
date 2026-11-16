<?php
/**
 * Hapus Peserta Delegasi
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('superadmin');

$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();

// Cek detail peserta sebelum dihapus
$stmtCheck = $pdo->prepare("SELECT id, full_name, group_id, is_group_leader, kategori, participant_type FROM participants WHERE id = ?");
$stmtCheck->execute([$id]);
$p = $stmtCheck->fetch();

if ($p) {
    $name = $p['full_name'];
    $groupId = $p['group_id'] ? (int)$p['group_id'] : null;
    $wasLeader = !empty($p['is_group_leader']);

    // Hapus data peserta
    $stmtDel = $pdo->prepare("DELETE FROM participants WHERE id = ?");
    $stmtDel->execute([$id]);

    // Jika peserta memiliki group pendaftaran, sinkronkan data rombongan
    if ($groupId) {
        // Cek sisa peserta dalam grup
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM participants WHERE group_id = ?");
        $stmtCount->execute([$groupId]);
        $remainingCount = (int)$stmtCount->fetchColumn();

        if ($remainingCount === 0) {
            // Jika sudah tidak ada anggota tersisa, hapus data group pendaftaran
            $stmtDelGroup = $pdo->prepare("DELETE FROM registration_groups WHERE id = ?");
            $stmtDelGroup->execute([$groupId]);
        } else {
            // Jika yang dihapus adalah ketua rombongan, tunjuk anggota pertama yang tersisa sebagai ketua
            if ($wasLeader) {
                $stmtNewLeader = $pdo->prepare("UPDATE participants SET is_group_leader = 1 WHERE group_id = ? ORDER BY id ASC LIMIT 1");
                $stmtNewLeader->execute([$groupId]);
            }

            // Hitung ulang total amount (Rp 300.000 per orang kecuali Tamu Undangan)
            $kat = $p['kategori'] ?? ($p['participant_type'] === 'undangan' ? 'Tamu Undangan' : 'PW');
            $costPerPerson = ($kat === 'Tamu Undangan') ? 0 : 300000;
            $newAmount = $remainingCount * $costPerPerson;

            $stmtUpdateGroup = $pdo->prepare("UPDATE registration_groups SET total_participants = ?, total_amount = ? WHERE id = ?");
            $stmtUpdateGroup->execute([$remainingCount, $newAmount, $groupId]);
        }
    }

    setFlash('info', "Data delegasi '{$name}' telah berhasil dihapus.");
} else {
    setFlash('danger', "Data peserta tidak ditemukan.");
}

$redirect = !empty($_GET['redirect']) ? $_GET['redirect'] : BASE_URL . "admin/participants.php";
header("Location: " . $redirect);
exit;
