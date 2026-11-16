<?php
/**
 * Cetak E-ID Card Tunggal (Print View)
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$code = trim($_GET['code'] ?? '');
if (empty($code)) {
    die("Kode registrasi tidak valid.");
}

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT p.*, w.name as pw_name, w.code as pw_code, r.building_name, r.room_number 
    FROM participants p 
    LEFT JOIN wilayah w ON p.pw_id = w.id 
    LEFT JOIN rooms r ON p.room_id = r.id 
    WHERE p.registration_code = :code 
    LIMIT 1
");
$stmt->execute([':code' => $code]);
$p = $stmt->fetch();

if (!$p) {
    die("Data delegasi tidak ditemukan.");
}

// Pengaturan Desain Kartu dari Database
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

// Ribbon Tipe Peserta
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
    default:
        $typeName = strtoupper($p['participant_type']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card - <?= clean($p['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/custom.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-8 flex flex-col items-center justify-center">

    <!-- Print Control Bar -->
    <div class="no-print mb-6 flex items-center gap-3">
        <button onclick="window.print()" class="px-6 py-2.5 rounded-xl bg-emerald-700 text-white font-bold text-sm shadow hover:bg-emerald-800 transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            <span>Cetak / Simpan PDF</span>
        </button>
        <button onclick="window.close()" class="px-4 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 font-medium text-sm hover:bg-slate-50 transition">
            Tutup
        </button>
    </div>

    <!-- ID Card Container (Standard B4 / A6 Badge Size) -->
    <div class="id-card-wrapper bg-white rounded-3xl overflow-hidden border border-slate-300 shadow-2xl flex flex-col justify-between relative" style="width: 90mm; min-height: 140mm; max-width: 90mm;">
        
        <!-- Top Ornamental Header -->
        <div class="bg-gradient-to-br <?= $activeTheme['header'] ?> text-white p-4 text-center relative overflow-hidden">
            <!-- Decorative Accent line -->
            <div class="h-1 bg-gradient-to-r from-amber-400 via-yellow-300 to-amber-500 absolute top-0 left-0 right-0"></div>

            <div class="flex items-center justify-center gap-2 mb-1">
                <?php if ($appLogoUrl): ?>
                    <img src="<?= $appLogoUrl ?>" class="w-6 h-6 object-contain">
                <?php else: ?>
                    <div class="w-6 h-6 rounded-lg bg-white/10 border border-white/20 flex items-center justify-center text-amber-400 shadow-sm">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                    </div>
                <?php endif; ?>
                <div class="text-[9px] uppercase tracking-widest text-emerald-200 font-extrabold truncate max-w-[200px]">
                    <?= clean($cardOrg) ?>
                </div>
            </div>

            <h1 class="text-sm font-extrabold tracking-tight leading-tight text-white uppercase">
                <?= clean($cardTitle) ?>
            </h1>
            <p class="text-[8px] text-emerald-200 mt-0.5">
                <?= clean($eventDate) ?>
            </p>
        </div>

        <!-- Participant Type Ribbon -->
        <div class="<?= $badgeColor ?> py-1 px-4 text-center text-xs font-black tracking-widest uppercase shadow-sm">
            <?= $typeName ?>
        </div>

        <!-- Card Body -->
        <div class="p-4 text-center flex-grow flex flex-col items-center justify-center space-y-3">
            
            <!-- Participant Identity -->
            <div class="w-full">
                <h2 class="text-base font-extrabold text-slate-900 leading-tight uppercase tracking-tight">
                    <?= clean($p['full_name']) ?>
                </h2>
                <div class="text-[11px] font-bold <?= $activeTheme['text'] ?> mt-0.5">
                    <?= clean($p['jabatan_organisasi']) ?>
                </div>
                <div class="text-[10px] text-slate-600 mt-0.5 font-medium">
                    <?= clean($p['pd_name'] ? $p['pd_name'] : $p['pw_name']) ?>
                    <?php if (!empty($p['unsur_organisasi'])): ?>
                        • <span class="font-bold text-emerald-700"><?= clean($p['unsur_organisasi']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="text-[9px] text-slate-400">
                    <?= clean($p['pw_name']) ?>
                </div>
            </div>

            <!-- Photo & QR Code Box -->
            <div class="flex items-center justify-center gap-2.5">
                <?php if (!empty($p['photo_file'])): ?>
                    <div class="bg-white p-1 rounded-2xl border-2 <?= $activeTheme['border'] ?> shadow-sm inline-block">
                        <img src="<?= BASE_URL . clean($p['photo_file']) ?>" alt="Foto" class="w-24 h-28 object-cover rounded-xl">
                    </div>
                <?php endif; ?>
                <div class="bg-white p-2 rounded-2xl border-2 <?= $activeTheme['border'] ?> shadow-sm inline-block">
                    <img src="<?= getQRCodeUrl($p['registration_code'], 150) ?>" alt="QR Code" class="<?= !empty($p['photo_file']) ? 'w-24 h-24' : 'w-28 h-28' ?> object-contain">
                    <div class="text-[10px] font-mono font-bold text-slate-700 tracking-wider mt-1">
                        <?= clean($p['registration_code']) ?>
                    </div>
                </div>
            </div>

            <!-- Commission & Accommodation Info Box -->
            <?php if ($showKomisi || $showRoom): ?>
                <div class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-left text-[10px] space-y-1">
                    <?php if ($showKomisi): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Komisi Sidang:</span>
                            <span class="font-bold text-slate-800"><?= clean($p['komisi']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($showRoom): ?>
                        <div class="flex justify-between items-center <?= $showKomisi ? 'border-t border-slate-200/60 pt-1' : '' ?>">
                            <span class="text-slate-500">Penginapan:</span>
                            <span class="font-bold <?= $activeTheme['text'] ?>">
                                <?= !empty($p['building_name']) ? clean($p['building_name'] . ' - ' . $p['room_number']) : 'Ditetapkan di Lokasi' ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bottom Footer Ribbon -->
        <div class="bg-slate-900 text-white p-2 text-center text-[8px] font-medium tracking-wide">
            <div><?= clean($footer1) ?></div>
            <div class="text-slate-400 text-[7px] mt-0.5"><?= clean($footer2) ?></div>
        </div>
    </div>

</body>
</html>
