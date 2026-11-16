<?php
/**
 * API: Get Peserta Terdaftar Berdasarkan Kategori & Asal Kepengurusan
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

ob_start();

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$kategori    = trim($_GET['kategori'] ?? $_POST['kategori'] ?? '');
$pw_id       = (int)($_GET['pw_id'] ?? $_POST['pw_id'] ?? 0);
$daerah_id   = (int)($_GET['daerah_id'] ?? $_POST['daerah_id'] ?? 0);
$daerah_name = trim($_GET['daerah_name'] ?? $_POST['daerah_name'] ?? '');

$pdo = getDB();

try {
    // Periksa kolom tabel participants secara dinamis agar 100% aman dari missing column
    $cols = $pdo->query("SHOW COLUMNS FROM `participants`")->fetchAll(PDO::FETCH_COLUMN);
    $colSet = array_flip($cols);

    $fields = ['p.id', 'p.registration_code', 'p.full_name', 'p.gender', 'p.created_at'];

    if (isset($colSet['pd_name'])) {
        $fields[] = 'p.pd_name';
    } else {
        $fields[] = "'' AS pd_name";
    }

    if (isset($colSet['jabatan_organisasi'])) {
        $fields[] = 'p.jabatan_organisasi';
    } else {
        $fields[] = "'' AS jabatan_organisasi";
    }

    if (isset($colSet['unsur_organisasi'])) {
        $fields[] = 'p.unsur_organisasi';
    } else {
        $fields[] = "'' AS unsur_organisasi";
    }

    if (isset($colSet['participant_type'])) {
        $fields[] = 'p.participant_type';
    } else {
        $fields[] = "'' AS participant_type";
    }

    if (isset($colSet['komisi'])) {
        $fields[] = 'p.komisi';
    } else {
        $fields[] = "'' AS komisi";
    }

    if (isset($colSet['photo'])) {
        $fields[] = 'p.photo';
    } elseif (isset($colSet['photo_file'])) {
        $fields[] = 'p.photo_file AS photo';
    } else {
        $fields[] = 'NULL AS photo';
    }

    if (isset($colSet['is_group_leader'])) {
        $fields[] = 'p.is_group_leader';
    } else {
        $fields[] = '0 AS is_group_leader';
    }

    $fields[] = 'w.name AS pw_name';

    $hasKategori = isset($colSet['kategori']);
    $hasDaerahId = isset($colSet['daerah_id']);

    $where = [];
    $params = [];

    if ($kategori === 'PP') {
        if ($hasKategori) {
            $where[] = "(p.kategori = 'PP' OR p.participant_type = 'pengurus_pusat')";
        } else {
            $where[] = "p.participant_type = 'pengurus_pusat'";
        }
    } elseif ($kategori === 'Tamu Undangan') {
        if ($hasKategori) {
            $where[] = "(p.kategori = 'Tamu Undangan' OR p.participant_type = 'undangan')";
        } else {
            $where[] = "p.participant_type = 'undangan'";
        }
    } elseif ($kategori === 'PW') {
        if ($hasKategori && $pw_id > 0) {
            $where[] = "(p.pw_id = ? AND (p.kategori = 'PW' OR p.kategori IS NULL OR p.kategori = '' " . ($hasDaerahId ? "OR p.daerah_id IS NULL" : "") . "))";
            $params[] = $pw_id;
        } elseif ($pw_id > 0) {
            $where[] = "p.pw_id = ?";
            $params[] = $pw_id;
        } elseif ($hasKategori) {
            $where[] = "p.kategori = 'PW'";
        }
    } elseif ($kategori === 'PD') {
        if ($hasDaerahId && $daerah_id > 0) {
            if (isset($colSet['pd_name']) && !empty($daerah_name)) {
                $where[] = "(p.daerah_id = ? OR p.pd_name = ?)";
                $params[] = $daerah_id;
                $params[] = $daerah_name;
            } else {
                $where[] = "p.daerah_id = ?";
                $params[] = $daerah_id;
            }
        } elseif (isset($colSet['pd_name']) && !empty($daerah_name)) {
            $where[] = "p.pd_name = ?";
            $params[] = $daerah_name;
        } elseif ($pw_id > 0) {
            $where[] = "p.pw_id = ?";
            $params[] = $pw_id;
            if ($hasKategori) {
                $where[] = "p.kategori = 'PD'";
            }
        }
    } else {
        if ($pw_id > 0) {
            $where[] = "p.pw_id = ?";
            $params[] = $pw_id;
        }
    }

    $sqlWhere = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    $sqlFields = implode(", ", $fields);

    $sql = "
        SELECT {$sqlFields}
        FROM participants p
        LEFT JOIN wilayah w ON p.pw_id = w.id
        {$sqlWhere}
        ORDER BY p.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $participants = $stmt->fetchAll();

    $cleanData = [];
    foreach ($participants as $p) {
        $photoUrl = null;
        if (!empty($p['photo']) && file_exists(__DIR__ . '/../' . $p['photo'])) {
            $photoUrl = BASE_URL . $p['photo'];
        }

        $cleanData[] = [
            'id'                => (int)($p['id'] ?? 0),
            'registration_code' => $p['registration_code'] ?? '-',
            'full_name'         => clean($p['full_name'] ?? '-'),
            'gender'            => (($p['gender'] ?? 'L') === 'P') ? 'Perempuan' : 'Laki-laki',
            'unsur'             => clean($p['unsur_organisasi'] ?? $p['jabatan_organisasi'] ?? '-'),
            'jabatan'           => clean($p['jabatan_organisasi'] ?? '-'),
            'komisi'            => clean($p['komisi'] ?? '-'),
            'pw_name'           => clean($p['pw_name'] ?? '-'),
            'pd_name'           => clean($p['pd_name'] ?? '-'),
            'is_leader'         => !empty($p['is_group_leader']),
            'photo'             => $photoUrl,
            'created_at'        => !empty($p['created_at']) ? date('d/m/Y H:i', strtotime($p['created_at'])) : '-'
        ];
    }

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'      => true,
        'count'        => count($cleanData),
        'participants' => $cleanData
    ]);
    exit;

} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'      => false,
        'message'      => $e->getMessage(),
        'participants' => []
    ]);
    exit;
}
