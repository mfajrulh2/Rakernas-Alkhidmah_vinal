<?php
/**
 * AJAX API: Endpoint Check-in Presensi & Pengambilan Kit
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Cek hak akses petugas / superadmin
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['superadmin', 'petugas_scan', 'admin_pw'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi login tidak sah atau telah berakhir.']);
    exit;
}

$pdo = getDB();
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$code = trim($data['code'] ?? '');
$toggleKit = !empty($data['kit_received']);

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kode registrasi tidak ditemukan.']);
    exit;
}

// Cari data peserta berdasarkan kode registrasi / NIK / HP
$stmt = $pdo->prepare("
    SELECT p.*, w.name as pw_name, w.code as pw_code, r.building_name, r.room_number 
    FROM participants p
    LEFT JOIN wilayah w ON p.pw_id = w.id
    LEFT JOIN rooms r ON p.room_id = r.id
    WHERE p.registration_code = :c OR p.nik = :c OR p.phone = :c
    LIMIT 1
");
$stmt->execute([':c' => $code]);
$p = $stmt->fetch();

if (!$p) {
    http_response_code(404);
    echo json_encode([
        'success' => false, 
        'message' => "Data delegasi dengan kode '{$code}' tidak terdaftar dalam sistem."
    ]);
    exit;
}

// Cek apakah sudah check-in sebelumnya
$alreadyAttended = ($p['is_attended'] == 1);

// Lakukan check-in
$now = date('Y-m-d H:i:s');
$kitVal = $toggleKit ? 1 : $p['kit_received'];
$kitTime = $toggleKit ? ($p['kit_received_at'] ?: $now) : $p['kit_received_at'];

$stmtUp = $pdo->prepare("
    UPDATE participants 
    SET is_attended = 1, 
        attended_at = COALESCE(attended_at, :now),
        kit_received = :kit,
        kit_received_at = :kit_time
    WHERE id = :id
");
$stmtUp->execute([
    ':now' => $now,
    ':kit' => $kitVal,
    ':kit_time' => $kitTime,
    ':id' => $p['id']
]);

// Siapkan pesan respons
$msg = $alreadyAttended 
    ? "Delegasi sudah check-in sebelumnya pada " . date('H:i', strtotime($p['attended_at'])) . " WIB" 
    : "Alhamdulillah, check-in berhasil!";

echo json_encode([
    'success' => true,
    'message' => $msg,
    'is_already_attended' => $alreadyAttended,
    'participant' => [
        'id'                => $p['id'],
        'registration_code' => $p['registration_code'],
        'full_name'         => $p['full_name'],
        'pw_name'           => $p['pw_name'],
        'pd_name'           => $p['pd_name'],
        'jabatan'           => $p['jabatan_organisasi'],
        'participant_type'  => $p['participant_type'],
        'komisi'            => $p['komisi'],
        'room'              => !empty($p['building_name']) ? ($p['building_name'] . ' - ' . $p['room_number']) : 'Belum ditentukan',
        'is_attended'       => 1,
        'attended_at'       => $p['attended_at'] ?: $now,
        'kit_received'      => $kitVal
    ]
]);
