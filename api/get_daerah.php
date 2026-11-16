<?php
/**
 * API: Get Daftar Pengurus Daerah (PD) Berdasarkan PW ID
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$pw_id = (int)($_GET['pw_id'] ?? $_POST['pw_id'] ?? 0);

if ($pw_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter pw_id tidak valid.',
        'daerah'  => []
    ]);
    exit;
}

$pdo = getDB();
try {
    $stmt = $pdo->prepare("
        SELECT d.id, d.pw_id, d.name, d.quota,
               COUNT(p.id) as registered_count
        FROM daerah d
        LEFT JOIN participants p ON (p.daerah_id = d.id OR p.pd_name = d.name) AND p.pw_id = d.pw_id
        WHERE d.pw_id = ?
        GROUP BY d.id
        ORDER BY d.name ASC
    ");
    $stmt->execute([$pw_id]);
    $rawList = $stmt->fetchAll();

    $daerahList = [];
    foreach ($rawList as $row) {
        $quota = (int)($row['quota'] ?: 5);
        $reg   = (int)$row['registered_count'];
        $rem   = max(0, $quota - $reg);

        $daerahList[] = [
            'id'               => (int)$row['id'],
            'pw_id'            => (int)$row['pw_id'],
            'name'             => $row['name'],
            'quota'            => $quota,
            'registered_count' => $reg,
            'remaining_quota'  => $rem
        ];
    }

    echo json_encode([
        'success' => true,
        'pw_id'   => $pw_id,
        'count'   => count($daerahList),
        'daerah'  => $daerahList
    ]);
    exit;
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil data daerah: ' . $e->getMessage(),
        'daerah'  => []
    ]);
    exit;
}
