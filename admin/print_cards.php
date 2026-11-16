<?php
/**
 * Cetak Massal E-ID Card Peserta (Grid A4 Siap Cetak)
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
// Jika yang login adalah admin PW, kunci hanya untuk PW-nya & pastikan sudah lunas
if (isPWAdmin()) {
    $pw_id = $user['pw_id'];
    $payInfo = getPWPaymentInfo($pw_id);
    if (!$payInfo['is_verified']) {
        setFlash('warning', '🔒 Cetak Bukti Pendaftaran / ID Card belum dapat diakses sebelum pembayaran iuran PW Anda divalidasi oleh Panitia Pusat.');
        header("Location: " . BASE_URL . "pw/payment.php");
        exit;
    }
}

$type = $_GET['type'] ?? '';

$where = ["1=1"];
$params = [];

if (!empty($pw_id)) {
    $where[] = "p.pw_id = :pw";
    $params[':pw'] = $pw_id;
}
if (!empty($type)) {
    $where[] = "p.participant_type = :t";
    $params[':t'] = $type;
}

$whereSql = implode(" AND ", $where);
$sql = "
    SELECT p.*, w.name as pw_name, w.code as pw_code, r.building_name, r.room_number 
    FROM participants p 
    LEFT JOIN wilayah w ON p.pw_id = w.id 
    LEFT JOIN rooms r ON p.room_id = r.id 
    WHERE {$whereSql}
    ORDER BY w.name ASC, p.participant_type ASC, p.full_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$participants = $stmt->fetchAll();

$wilayahList = $pdo->query("SELECT id, name FROM wilayah ORDER BY name ASC")->fetchAll();

// Pengaturan Desain ID Card Dinamis
$appLogoUrl  = getAppLogoUrl();
$cardTheme   = getSetting('idcard_theme', 'emerald');
$cardTitle   = getSetting('idcard_title', 'RAKERNAS 2026');
$cardOrg     = getSetting('idcard_org_name', 'PERKUMPULAN JAMA\'AH AL KHIDMAH');
$eventDate   = getSetting('event_date', '15 - 17 November 2026');
$footer1     = getSetting('idcard_footer_line1', 'Wajib dikenakan selama rangkaian acara RAKERNAS');
$footer2     = getSetting('idcard_footer_line2', 'Panitia Pelaksana RAKERNAS Jama\'ah Al Khidmah');
$showRoom    = getSetting('idcard_show_room', '1') === '1';
$showKomisi  = getSetting('idcard_show_komisi', '1') === '1';

// Tema Warna ID Card
$themes = [
    'emerald' => ['header' => 'from-emerald-950 via-emerald-900 to-emerald-800', 'ribbon' => 'bg-emerald-700', 'border' => 'border-emerald-800', 'text' => 'text-emerald-800'],
    'gold'    => ['header' => 'from-amber-950 via-amber-900 to-amber-800',       'ribbon' => 'bg-amber-600',   'border' => 'border-amber-700',   'text' => 'text-amber-800'],
    'blue'    => ['header' => 'from-blue-950 via-blue-900 to-blue-800',         'ribbon' => 'bg-blue-600',    'border' => 'border-blue-700',    'text' => 'text-blue-800'],
    'maroon'  => ['header' => 'from-rose-950 via-rose-900 to-rose-800',         'ribbon' => 'bg-rose-700',    'border' => 'border-rose-800',    'text' => 'text-rose-800'],
    'navy'    => ['header' => 'from-indigo-950 via-indigo-900 to-indigo-800',   'ribbon' => 'bg-indigo-800',  'border' => 'border-indigo-800',  'text' => 'text-indigo-800'],
    'slate'   => ['header' => 'from-slate-950 via-slate-900 to-slate-800',       'ribbon' => 'bg-slate-700',   'border' => 'border-slate-800',   'text' => 'text-slate-800']
];
$activeTheme = $themes[$cardTheme] ?? $themes['emerald'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Massal ID Card (<?= count($participants) ?> Peserta) - Al Khidmah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/custom.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .page-break { page-break-after: always; break-after: page; }
        }
        .card-sheet {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6mm;
            width: 100%;
            max-width: 195mm;
            margin: 0 auto;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-6">

    <!-- Top Action Toolbar -->
    <div class="no-print max-w-4xl mx-auto mb-6 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="<?= isPWAdmin() ? BASE_URL.'pw/participants.php' : BASE_URL.'admin/participants.php' ?>" class="px-3.5 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                &larr; Kembali
            </a>
            <div>
                <h1 class="text-sm font-bold text-slate-900">Lembar Cetak Massal ID Card</h1>
                <p class="text-[11px] text-slate-500">Ditemukan <strong><?= count($participants) ?></strong> ID card siap cetak (Format 4 kartu per lembar A4)</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <?php if (isSuperAdmin()): ?>
                <form action="" method="GET" class="flex items-center gap-2">
                    <select name="pw_id" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs focus:outline-none bg-white">
                        <option value="">Semua Wilayah</option>
                        <?php foreach ($wilayahList as $w): ?>
                            <option value="<?= $w['id'] ?>" <?= $pw_id == $w['id'] ? 'selected' : '' ?>><?= clean($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>

            <button onclick="window.print()" class="px-5 py-2 rounded-xl bg-emerald-700 text-white font-bold text-xs hover:bg-emerald-800 transition shadow flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak Lembar A4 (Print)</span>
            </button>
        </div>
    </div>

    <!-- Container of Sheets (4 cards per A4 sheet) -->
    <?php if (empty($participants)): ?>
        <div class="max-w-md mx-auto bg-white p-8 rounded-3xl border border-slate-200 text-center text-slate-500 text-sm">
            Tidak ada peserta yang ditemukan untuk dicetak.
        </div>
    <?php else: ?>
        <?php 
        $chunks = array_chunk($participants, 4);
        foreach ($chunks as $pageIndex => $fourCards): 
        ?>
            <div class="card-sheet <?= $pageIndex < count($chunks) - 1 ? 'page-break' : '' ?> mb-8">
                <?php foreach ($fourCards as $p): ?>
                    <?php 
                    $badgeColor = $activeTheme['ribbon'] . ' text-white';
                    $typeName = 'PESERTA UTUSAN';
                    switch (strtolower($p['participant_type'])) {
                        case 'peninjau':
                            $badgeColor = 'bg-amber-600 text-white';
                            $typeName = 'PESERTA PENINJAU';
                            break;
                        case 'pengurus_pusat':
                            $badgeColor = 'bg-indigo-800 text-white';
                            $typeName = 'PENGURUS PUSAT';
                            break;
                        case 'undangan':
                            $badgeColor = 'bg-purple-800 text-white';
                            $typeName = 'UNDANGAN KHUSUS';
                            break;
                    }
                    ?>
                    <!-- Single ID Card (Standard B4 / A6 Badge Size) -->
                    <div class="id-card-wrapper bg-white rounded-2xl overflow-hidden border border-slate-300 shadow-sm flex flex-col justify-between" style="height: 130mm; width: 90mm; margin: 0 auto;">
                        
                        <!-- Header -->
                        <div class="bg-gradient-to-br <?= $activeTheme['header'] ?> text-white p-3 text-center relative overflow-hidden">
                            <div class="h-1 bg-amber-400 absolute top-0 left-0 right-0"></div>
                            <div class="flex items-center justify-center gap-1.5 mb-0.5">
                                <?php if ($appLogoUrl): ?>
                                    <img src="<?= $appLogoUrl ?>" class="w-4 h-4 object-contain">
                                <?php else: ?>
                                    <div class="w-4 h-4 rounded bg-white/10 flex items-center justify-center text-amber-400">
                                        <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                                    </div>
                                <?php endif; ?>
                                <span class="text-[8px] uppercase tracking-widest text-emerald-200 font-extrabold truncate max-w-[190px]">
                                    <?= clean($cardOrg) ?>
                                </span>
                            </div>
                            <h2 class="text-xs font-black tracking-tight leading-tight uppercase text-white truncate">
                                <?= clean($cardTitle) ?>
                            </h2>
                            <p class="text-[7px] text-emerald-200 mt-0.5">
                                <?= clean($eventDate) ?>
                            </p>
                        </div>

                        <!-- Ribbon -->
                        <div class="<?= $badgeColor ?> py-0.5 px-3 text-center text-[10px] font-black tracking-wider uppercase">
                            <?= $typeName ?>
                        </div>

                        <!-- Card Body -->
                        <div class="p-3 text-center flex-grow flex flex-col items-center justify-center space-y-2">
                            <div class="w-full">
                                <h3 class="text-xs font-extrabold text-slate-900 leading-tight uppercase truncate">
                                    <?= clean($p['full_name']) ?>
                                </h3>
                                <div class="text-[10px] font-bold <?= $activeTheme['text'] ?> mt-0.5 truncate">
                                    <?= clean($p['jabatan_organisasi']) ?>
                                </div>
                                <div class="text-[9px] text-slate-600 truncate">
                                    <?= clean($p['pd_name'] ?: $p['pw_name']) ?>
                                    <?php if (!empty($p['unsur_organisasi'])): ?>
                                        • <span class="font-bold text-emerald-800"><?= clean($p['unsur_organisasi']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[8px] text-slate-400 truncate">
                                    <?= clean($p['pw_name']) ?>
                                </div>
                            </div>

                            <!-- Photo & QR Code -->
                            <div class="flex items-center justify-center gap-2">
                                <?php if (!empty($p['photo_file'])): ?>
                                    <div class="bg-white p-0.5 rounded-lg border <?= $activeTheme['border'] ?> shadow-xs inline-block">
                                        <img src="<?= BASE_URL . clean($p['photo_file']) ?>" alt="Foto" class="w-16 h-20 object-cover rounded">
                                    </div>
                                <?php endif; ?>
                                <div class="bg-white p-1 rounded-xl border <?= $activeTheme['border'] ?> inline-block shadow-sm">
                                    <img src="<?= getQRCodeUrl($p['registration_code'], 110) ?>" alt="QR" class="<?= !empty($p['photo_file']) ? 'w-16 h-16' : 'w-20 h-20' ?> object-contain mx-auto">
                                    <div class="text-[8px] font-mono font-bold text-slate-700 tracking-wider">
                                        <?= clean($p['registration_code']) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Details -->
                            <?php if ($showKomisi || $showRoom): ?>
                                <div class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-left text-[9px] space-y-0.5">
                                    <?php if ($showKomisi): ?>
                                        <div class="flex justify-between items-center">
                                            <span class="text-slate-500">Komisi:</span>
                                            <span class="font-bold text-slate-800 truncate"><?= clean($p['komisi']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($showRoom): ?>
                                        <div class="flex justify-between items-center <?= $showKomisi ? 'border-t border-slate-200/60 pt-0.5' : '' ?>">
                                            <span class="text-slate-500">Kamar:</span>
                                            <span class="font-bold <?= $activeTheme['text'] ?> truncate">
                                                <?= !empty($p['building_name']) ? clean($p['building_name'] . ' ' . $p['room_number']) : 'Di Lokasi' ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Bottom Ribbon -->
                        <div class="bg-slate-900 text-white p-1 text-center text-[7px] leading-tight">
                            <div><?= clean($footer1) ?></div>
                            <div class="text-slate-400 text-[6px]"><?= clean($footer2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
