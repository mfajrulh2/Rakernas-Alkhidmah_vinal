<?php
/**
 * Dokumen Bukti Pendaftaran Resmi Delegasi (Print View / PDF / PNG)
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH 2026
 * 
 * Sesuai Standar Resmi Modul Registrasi Peserta Rakernas (Tahap D)
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

$paramCode = trim($_GET['code'] ?? ($_GET['group'] ?? ($_GET['group_code'] ?? '')));
$partId    = (int)($_GET['id'] ?? 0);

$groupData = null;
$participants = [];

// Skenario 1: Cek apakah kode cocok dengan group_code di registration_groups
if (!empty($paramCode)) {
    $stmtG = $pdo->prepare("
        SELECT g.*, w.name as pw_name, d.name as db_daerah_name
        FROM registration_groups g
        LEFT JOIN wilayah w ON g.pw_id = w.id
        LEFT JOIN daerah d ON g.daerah_id = d.id
        WHERE g.group_code = ? LIMIT 1
    ");
    $stmtG->execute([$paramCode]);
    $groupData = $stmtG->fetch();

    if ($groupData) {
        $stmtP = $pdo->prepare("
            SELECT p.*, w.name as db_pw_name 
            FROM participants p 
            LEFT JOIN wilayah w ON p.pw_id = w.id
            WHERE p.group_id = ? 
            ORDER BY p.is_group_leader DESC, p.id ASC
        ");
        $stmtP->execute([$groupData['id']]);
        $participants = $stmtP->fetchAll();
    }
}

// Skenario 2: Jika belum ditemukan di group, cek peserta berdasarkan registration_code atau id
if (!$groupData && (!empty($paramCode) || $partId > 0)) {
    if (!empty($paramCode)) {
        $stmtSingle = $pdo->prepare("
            SELECT p.*, w.name as pw_name, d.name as db_daerah_name
            FROM participants p
            LEFT JOIN wilayah w ON p.pw_id = w.id
            LEFT JOIN daerah d ON p.daerah_id = d.id
            WHERE p.registration_code = ? LIMIT 1
        ");
        $stmtSingle->execute([$paramCode]);
    } else {
        $stmtSingle = $pdo->prepare("
            SELECT p.*, w.name as pw_name, d.name as db_daerah_name
            FROM participants p
            LEFT JOIN wilayah w ON p.pw_id = w.id
            LEFT JOIN daerah d ON p.daerah_id = d.id
            WHERE p.id = ? LIMIT 1
        ");
        $stmtSingle->execute([$partId]);
    }
    $targetParticipant = $stmtSingle->fetch();

    if ($targetParticipant) {
        // Jika peserta tergabung dalam transaksi registration_groups
        if (!empty($targetParticipant['group_id'])) {
            $stmtG = $pdo->prepare("
                SELECT g.*, w.name as pw_name, d.name as db_daerah_name
                FROM registration_groups g
                LEFT JOIN wilayah w ON g.pw_id = w.id
                LEFT JOIN daerah d ON g.daerah_id = d.id
                WHERE g.id = ? LIMIT 1
            ");
            $stmtG->execute([$targetParticipant['group_id']]);
            $groupData = $stmtG->fetch();

            if ($groupData) {
                $stmtP = $pdo->prepare("
                    SELECT p.*, w.name as db_pw_name 
                    FROM participants p 
                    LEFT JOIN wilayah w ON p.pw_id = w.id
                    WHERE p.group_id = ? 
                    ORDER BY p.is_group_leader DESC, p.id ASC
                ");
                $stmtP->execute([$groupData['id']]);
                $participants = $stmtP->fetchAll();
            }
        }

        // Jika peserta individu (belum/tidak ada group_id)
        if (!$groupData) {
            $feePerPerson = (int)getSetting('fee_per_participant', 300000);
            $kat = $targetParticipant['kategori'] ?? ($targetParticipant['participant_type'] === 'pengurus_pusat' ? 'PP' : ($targetParticipant['participant_type'] === 'undangan' ? 'Tamu Undangan' : 'PW'));
            $isGuest = ($kat === 'Tamu Undangan');

            $groupData = [
                'id'                 => 0,
                'group_code'         => $targetParticipant['registration_code'],
                'kategori'           => $kat,
                'pw_id'              => $targetParticipant['pw_id'] ?? null,
                'pw_name'            => $targetParticipant['pw_name'] ?? 'Pusat',
                'daerah_id'          => $targetParticipant['daerah_id'] ?? null,
                'daerah_name'        => $targetParticipant['pd_name'] ?? '',
                'db_daerah_name'     => $targetParticipant['db_daerah_name'] ?? '',
                'pic_name'           => $targetParticipant['full_name'],
                'pic_phone'          => $targetParticipant['phone'],
                'total_participants' => 1,
                'fee_per_person'     => $isGuest ? 0 : $feePerPerson,
                'total_amount'       => $isGuest ? 0 : $feePerPerson,
                'payment_status'     => 'unpaid',
                'created_at'         => $targetParticipant['created_at'] ?? date('Y-m-d H:i:s')
            ];
            $participants = [$targetParticipant];
        }
    }
}

if (!$groupData || empty($participants)) {
    die("<div style='font-family:sans-serif; max-width:550px; margin:50px auto; padding:24px; border:1px solid #e2e8f0; border-radius:16px; text-align:center;'>
            <h2 style='color:#0f172a; margin-bottom:8px;'>Data Bukti Pendaftaran Tidak Ditemukan</h2>
            <p style='color:#64748b; font-size:14px;'>Silakan pastikan kode pendaftaran atau kode transaksi yang Anda masukkan sudah benar.</p>
            <a href='" . BASE_URL . "admin/participants.php' style='display:inline-block; margin-top:16px; padding:10px 20px; background:#065f46; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold; font-size:13px;'>Kembali ke Data Peserta</a>
         </div>");
}

$feePerPerson = (int)getSetting('fee_per_participant', 300000);

// Data Rekening Resmi Pembayaran dari Pengaturan
$bankInfo     = getPaymentBankInfo();
$bankName     = $bankInfo['bank_name'];
$bankAccount  = $bankInfo['account_no'];
$bankHolder   = $bankInfo['account_holder'];

// Kontak WhatsApp Panitia / Pusat Informasi
$panitiaInfo   = getPanitiaContact();
$panitiaWA     = $panitiaInfo['wa'];
$panitiaName   = $panitiaInfo['name'];
$panitiaWALink = $panitiaInfo['wa_link'];

// Informasi Acara, Tempat, Tanggal, dan Organisasi dari Pengaturan
$eventInfo     = getEventInfo();
$eventLocation = $eventInfo['location'];
$eventDate     = $eventInfo['date'];
$eventCity     = $eventInfo['city'];
$eventName     = $eventInfo['name'];
$orgName       = getSetting('idcard_org_name', 'PERKUMPULAN JAMA\'AH AL KHIDMAH');

$appLogoUrl   = getAppLogoUrl();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pendaftaran RAKERNAS 2026 - <?= clean($groupData['group_code']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
        }
        .font-arabic {
            font-family: 'Amiri', serif;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            #print-area {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm 12mm 10mm 12mm;
            }
        }
    </style>
</head>
<body class="text-slate-800 antialiased p-4 sm:p-8">

    <div class="max-w-4xl mx-auto space-y-6">

        <!-- Top Action Bar (Tombol Download PNG, Cetak PDF, Tutup) -->
        <div class="no-print bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="javascript:window.history.back()" class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition" title="Kembali">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">Bukti Pendaftaran Resmi Peserta RAKERNAS</h2>
                    <p class="text-xs text-slate-500">Kode Transaksi: <strong class="font-mono text-emerald-800"><?= clean($groupData['group_code']) ?></strong></p>
                </div>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <button onclick="downloadAsPNG()" class="px-4 py-2.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white font-bold text-xs flex items-center gap-1.5 transition shadow-sm">
                    <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Download Gambar (PNG)</span>
                </button>
                <button onclick="window.print()" class="px-4 py-2.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs flex items-center gap-1.5 transition shadow-sm">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak / PDF (A4)</span>
                </button>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- LEMBAR RESMI BUKTI PENDAFTARAN (SIAP CETAK / HTML2CANVAS) -->
        <!-- =================================================================== -->
        <div id="print-area" class="bg-white rounded-3xl border border-slate-200/90 shadow-lg p-6 sm:p-10 space-y-7">
            
            <!-- Kop Surat Resmi Al Khidmah -->
            <div class="border-b-2 border-emerald-900 pb-5 flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <?php if ($appLogoUrl): ?>
                        <img src="<?= $appLogoUrl ?>" alt="Logo Al Khidmah" class="w-16 h-16 object-contain rounded-2xl shrink-0 shadow-xs">
                    <?php else: ?>
                        <div class="w-16 h-16 rounded-2xl bg-emerald-900 flex items-center justify-center text-white shrink-0 shadow-sm">
                            <svg class="w-10 h-10 text-amber-300" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="text-[11px] font-extrabold uppercase tracking-widest text-emerald-800">Panitia Pelaksana <?= clean($eventName) ?></div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-tight"><?= clean($orgName) ?></h1>
                        <p class="text-xs text-slate-500 font-medium mt-0.5"><?= clean($eventLocation) ?> • <?= clean($eventDate) ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-900 text-xs font-black rounded-lg border border-emerald-300">
                        BUKTI PENDAFTARAN RESMI
                    </span>
                    <div class="text-[10px] text-slate-400 mt-1 font-mono">Diterbitkan: <?= date('d/m/Y H:i', strtotime($groupData['created_at'])) ?> WIB</div>
                </div>
            </div>

            <!-- Info Identitas Rombongan & QR Code -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-slate-50/80 rounded-2xl p-5 sm:p-6 border border-slate-200/80">
                <div class="md:col-span-2 space-y-3">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Kode Transaksi</span>
                            <span class="font-mono font-black text-emerald-900 text-sm"><?= clean($groupData['group_code']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Kategori Peserta</span>
                            <span class="font-extrabold text-slate-900"><?= clean($groupData['kategori']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Total Delegasi</span>
                            <span class="font-extrabold text-slate-900 text-sm"><?= (int)$groupData['total_participants'] ?> Orang</span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Asal Kepengurusan</span>
                            <span class="font-bold text-slate-800">
                                <?php 
                                if ($groupData['kategori'] === 'PP') {
                                    echo "Pengurus Pusat (PP) Al Khidmah";
                                } elseif ($groupData['kategori'] === 'Tamu Undangan') {
                                    echo "Tamu Undangan Khusus";
                                } elseif ($groupData['kategori'] === 'PW') {
                                    echo clean($groupData['pw_name']);
                                } else {
                                    $daerahLabel = clean($groupData['daerah_name'] ?: ($groupData['db_daerah_name'] ?? ''));
                                    echo (!empty($daerahLabel) ? $daerahLabel . " (" . clean($groupData['pw_name']) . ")" : clean($groupData['pw_name']));
                                }
                                ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">PIC / Ketua Rombongan</span>
                            <span class="font-bold text-slate-800"><?= clean($groupData['pic_name']) ?> <span class="text-slate-400 font-mono text-[11px]">(<?= clean($groupData['pic_phone']) ?>)</span></span>
                        </div>
                    </div>
                </div>

                <!-- QR Code Check-in Presensi -->
                <div class="flex flex-col items-center justify-center p-3 bg-white rounded-xl border border-slate-200 shadow-xs text-center">
                    <img src="<?= getQRCodeUrl($groupData['group_code'], 180) ?>" alt="QR Code Pendaftaran" class="w-32 h-32 object-contain rounded-lg">
                    <span class="text-[10px] font-mono font-bold text-slate-600 mt-2">Scan untuk Check-in Presensi</span>
                    <span class="text-[9px] text-slate-400">Tunjukkan barcode ini kepada petugas di lokasi</span>
                </div>
            </div>

            <!-- Tabel Daftar Peserta Rombongan -->
            <div>
                <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-3">Daftar Peserta Rombongan (<?= count($participants) ?> Orang)</h3>
                <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-100 text-slate-500 font-bold text-[10px] uppercase">
                            <tr>
                                <th class="py-2.5 px-3 text-center w-8">No</th>
                                <th class="py-2.5 px-3">Kode Registrasi</th>
                                <th class="py-2.5 px-4">Nama Lengkap</th>
                                <th class="py-2.5 px-3 text-center">L/P</th>
                                <?php if ($groupData['kategori'] !== 'Tamu Undangan'): ?>
                                    <th class="py-2.5 px-4">Unsur / Jabatan</th>
                                    <th class="py-2.5 px-4">Komisi</th>
                                <?php endif; ?>
                                <th class="py-2.5 px-4">Jadwal Kedatangan</th>
                                <th class="py-2.5 px-3 text-center">Transportasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($participants as $idx => $p): ?>
                                <tr class="<?= !empty($p['is_group_leader']) ? 'bg-emerald-50/40 font-semibold' : '' ?>">
                                    <td class="py-2.5 px-3 text-center text-slate-400 font-bold"><?= $idx + 1 ?></td>
                                    <td class="py-2.5 px-3 font-mono text-[11px] font-bold text-emerald-800"><?= clean($p['registration_code']) ?></td>
                                    <td class="py-2.5 px-4">
                                        <div class="font-bold text-slate-900"><?= clean($p['full_name']) ?></div>
                                        <div class="text-[10px] text-slate-400 font-mono"><?= clean($p['phone']) ?></div>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-bold"><?= clean($p['gender']) ?></td>
                                    <?php if ($groupData['kategori'] !== 'Tamu Undangan'): ?>
                                        <td class="py-2.5 px-4">
                                            <div><?= clean($p['jabatan_organisasi'] ?: ($p['unsur_organisasi'] ?? '-')) ?></div>
                                        </td>
                                        <td class="py-2.5 px-4">
                                            <span class="text-[11px] text-slate-600"><?= clean($p['komisi'] ?: '-') ?></span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="py-2.5 px-4 text-xs">
                                        <div><?= clean($p['arrival_day'] ?: '-') ?> <?= clean($p['arrival_time'] ?? '') ?></div>
                                        <?php if (!empty($p['need_pickup'])): ?>
                                            <div class="mt-1 bg-amber-50 border border-amber-200/90 rounded-lg p-1.5 text-[10px] text-amber-950 text-left">
                                                <div class="font-bold text-amber-900">🚐 Jemput: <?= clean($p['pickup_location'] ?: 'Stasiun / Bandara') ?></div>
                                                <?php if (!empty($p['pickup_time'])): ?>
                                                    <div class="text-[9px] text-slate-600">Waktu: <?= clean($p['pickup_time']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($p['pickup_notes'])): ?>
                                                    <div class="text-[9px] text-slate-500 italic mt-0.5 leading-tight">Catatan: <?= clean($p['pickup_notes']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-xs text-slate-600">
                                        <?= clean($p['transport_mode'] ?: ($p['transport_type'] ?? '-')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kwitansi Tagihan Iuran Akomodasi (HANYA UNTUK PP, PW, DAN PD) -->
            <?php if ($groupData['kategori'] !== 'Tamu Undangan'): ?>
                <div class="border-2 border-dashed border-amber-300 bg-amber-50/60 rounded-3xl p-6 sm:p-7 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-amber-200">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold text-sm shadow-xs">Rp</span>
                            <div>
                                <h4 class="text-sm font-extrabold text-amber-950 uppercase tracking-wide">Kwitansi Tagihan Iuran Akomodasi</h4>
                                <p class="text-[11px] text-amber-800">Iuran akomodasi, konsumsi, dan fasilitas RAKERNAS Jama'ah Al Khidmah 2026</p>
                            </div>
                        </div>
                        <?php if ($groupData['payment_status'] === 'verified'): ?>
                            <span class="text-xs font-black text-emerald-900 bg-emerald-200/90 px-3 py-1 rounded-xl flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-emerald-800" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                STATUS: LUNAS / TERVERIFIKASI
                            </span>
                        <?php elseif ($groupData['payment_status'] === 'pending'): ?>
                            <span class="text-xs font-black text-amber-900 bg-amber-200/90 px-3 py-1 rounded-xl">
                                STATUS: MENUNGGU VERIFIKASI BENDAHARA
                            </span>
                        <?php else: ?>
                            <span class="text-xs font-black text-amber-900 bg-amber-200/80 px-3 py-1 rounded-xl">
                                STATUS: MENUNGGU PEMBAYARAN
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
                        <div class="space-y-2">
                            <div class="flex justify-between py-1 border-b border-amber-200/60 text-slate-600">
                                <span>Tarif Iuran per Delegasi</span>
                                <span class="font-mono font-bold text-slate-800">Rp <?= number_format($groupData['fee_per_person'], 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-amber-200/60 text-slate-600">
                                <span>Jumlah Delegasi</span>
                                <span class="font-bold text-slate-800"><?= (int)$groupData['total_participants'] ?> Orang</span>
                            </div>
                            <div class="flex justify-between py-2 text-base font-extrabold text-amber-950">
                                <span>Total Tagihan</span>
                                <span class="font-mono text-emerald-800 text-lg">Rp <?= number_format($groupData['total_amount'], 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <div class="bg-white/90 p-4 rounded-2xl border border-amber-200 space-y-1.5">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Rekening Resmi Pembayaran</div>
                            <div class="text-sm font-extrabold text-slate-900"><?= clean($bankName) ?></div>
                            <div class="text-base font-mono font-black text-emerald-800 tracking-wider"><?= clean($bankAccount) ?></div>
                            <div class="text-xs font-medium text-slate-600">a.n. <?= clean($bankHolder) ?></div>
                            <p class="text-[10px] text-amber-900 pt-1 italic">* Harap cantumkan berita transfer: <strong><?= clean($groupData['group_code']) ?></strong></p>
                        </div>
                    </div>

                    <?php if ($groupData['payment_status'] !== 'verified'): ?>
                        <div class="no-print pt-3 border-t border-amber-200/70 flex flex-wrap items-center justify-end gap-2.5">
                            <a href="<?= BASE_URL ?>konfirmasi_pembayaran.php?code=<?= urlencode($groupData['group_code']) ?>" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5">
                                <span>📤 Upload Bukti Transfer</span>
                            </a>
                            <a href="<?= getWhatsAppConfirmUrl($groupData) ?>" target="_blank" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                <span>Konfirmasi via WhatsApp</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Pusat Informasi & Konfirmasi WhatsApp Panitia -->
            <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 rounded-2xl border border-emerald-200 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-bold text-emerald-800 tracking-wider">Pusat Informasi & Konfirmasi Panitia</div>
                        <div class="text-sm font-black text-slate-900 flex items-center gap-2 flex-wrap">
                            <span>No. WhatsApp: <?= clean($panitiaWA) ?></span>
                            <span class="text-xs font-bold text-emerald-800 bg-emerald-100/90 border border-emerald-300 px-2.5 py-0.5 rounded-full">a.n. <?= clean($panitiaName) ?></span>
                        </div>
                        <p class="text-[11px] text-slate-600 mt-0.5">Layanan bantuan informasi pendaftaran, konfirmasi bukti transfer, dan koordinasi kedatangan delegasi.</p>
                    </div>
                </div>
                <div class="no-print shrink-0">
                    <a href="<?= clean($panitiaWALink) ?>?text=<?= urlencode('Assalamu\'alaikum Panitia Rakernas, saya mengonfirmasi pendaftaran delegasi dengan kode: ' . $groupData['group_code']) ?>" target="_blank" class="px-4 py-2.5 rounded-xl bg-emerald-700 text-white font-bold text-xs hover:bg-emerald-800 transition inline-flex items-center gap-2 shadow-sm">
                        <span>Hubungi via WhatsApp</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>

            <!-- Catatan Petunjuk & Tanda Tangan Panitia -->
            <div class="pt-6 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
                <div class="space-y-1.5 text-slate-500 text-[11px] leading-relaxed">
                    <p class="font-bold text-slate-700">Ketentuan & Petunjuk Kedatangan:</p>
                    <ol class="list-decimal pl-4 space-y-1">
                        <li>Bukti pendaftaran ini wajib dibawa dan ditunjukkan kepada petugas saat tiba di meja registrasi.</li>
                        <li>Petugas akan memindai QR Code di atas untuk validasi presensi dan pengambilan Kit Rakernas.</li>
                        <li>Delegasi diharapkan mengenakan busana resmi: baju & kopiah putih (ikhwan) / busana putih (akhwat).</li>
                        <li>Konfirmasi pengiriman bukti transfer atau koordinasi kedatangan dapat dikirimkan ke WhatsApp Panitia: <strong><?= clean($panitiaWA) ?></strong> (a.n. <strong><?= clean($panitiaName) ?></strong>).</li>
                    </ol>
                </div>

                <div class="text-right flex flex-col justify-end">
                    <p class="text-slate-500 text-[11px] font-medium"><?= clean($eventCity) ?>, <?= date('d F Y', strtotime($groupData['created_at'])) ?></p>
                    <p class="font-bold text-slate-800 mt-1">Panitia Pelaksana <?= clean($eventName) ?></p>
                    <p class="text-[10px] text-emerald-700 font-semibold mt-8"><?= clean($orgName) ?></p>
                </div>
            </div>

        </div>

    </div>

    <script>
    function downloadAsPNG() {
        const element = document.getElementById('print-area');
        html2canvas(element, { scale: 2 }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'Bukti_Pendaftaran_<?= $groupData['group_code'] ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }
    </script>
</body>
</html>
