<?php
/**
 * Ekspor Data Delegasi ke Format Excel / CSV
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['superadmin', 'admin_pw']);

$pdo = getDB();
$user = currentUser();

$pw_id = $_GET['pw_id'] ?? '';
if (isPWAdmin()) {
    $pw_id = $user['pw_id'];
}

$where = ["1=1"];
$params = [];
if (!empty($pw_id)) {
    $where[] = "p.pw_id = :pw";
    $params[':pw'] = $pw_id;
}

$sql = "
    SELECT p.*, w.name as pw_name, w.code as pw_code, r.building_name, r.room_number 
    FROM participants p
    LEFT JOIN wilayah w ON p.pw_id = w.id
    LEFT JOIN rooms r ON p.room_id = r.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY w.name ASC, p.full_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = "Rekap_Peserta_Rakernas_AlKhidmah_" . date('Ymd_His') . ".csv";

// Output headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// UTF-8 BOM agar terbaca sempurna di Microsoft Excel
fputs($output, "\xEF\xBB\xBF");

// Header Kolom
fputcsv($output, [
    'No',
    'Kode Registrasi',
    'Nama Lengkap',
    'L/P',
    'Unsur Organisasi',
    'No. WhatsApp / HP',
    'NIK',
    'Email',
    'Pengurus Wilayah (PW)',
    'Pengurus Daerah (PD)',
    'Jabatan',
    'Jenis Peserta',
    'Komisi Sidang',
    'Gedung Penginapan',
    'Nomor Kamar',
    'Hari Kedatangan',
    'Jam Kedatangan',
    'Moda Transportasi',
    'Perlu Penjemputan',
    'Titik Penjemputan',
    'Waktu Penjemputan Dikehendaki',
    'Catatan Panitia Transportasi',
    'Status Presensi',
    'Waktu Presensi',
    'Kit Rakernas'
]);

$no = 1;
foreach ($rows as $r) {
    fputcsv($output, [
        $no++,
        $r['registration_code'],
        $r['full_name'],
        $r['gender'] == 'L' ? 'Ikhwan (L)' : 'Akhwat (P)',
        $r['unsur_organisasi'] ?: 'Al Khidmah',
        $r['phone'],
        $r['nik'],
        $r['email'],
        $r['pw_name'],
        $r['pd_name'],
        $r['jabatan_organisasi'],
        strtoupper($r['participant_type']),
        $r['komisi'],
        $r['building_name'] ?: 'Belum diset',
        $r['room_number'] ?: 'Belum diset',
        $r['arrival_day'] ?: '-',
        $r['arrival_time'] ?: '-',
        $r['transport_mode'] ?: ($r['transport_type'] ?? '-'),
        !empty($r['need_pickup']) ? 'YA' : 'TIDAK',
        $r['pickup_location'] ?: '-',
        $r['pickup_time'] ?: '-',
        $r['pickup_notes'] ?: '-',
        $r['is_attended'] ? 'HADIR' : 'BELUM HADIR',
        $r['attended_at'] ?: '-',
        $r['kit_received'] ? 'SUDAH' : 'BELUM'
    ]);
}

fclose($output);
exit;
