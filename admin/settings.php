<?php
/**
 * Pengaturan Kegiatan, Header & Desain E-ID Card
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

    // 1. Aksi Hapus Logo
    if (isset($_POST['delete_logo'])) {
        $currLogo = getSetting('app_logo', '');
        if (!empty($currLogo) && file_exists(__DIR__ . '/../' . $currLogo)) {
            @unlink(__DIR__ . '/../' . $currLogo);
        }
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('app_logo', '') ON DUPLICATE KEY UPDATE setting_value = ''")->execute();
        setFlash('info', 'Logo kustom berhasil dihapus. Sistem kembali menggunakan lambang resmi bawaan.');
        header("Location: " . BASE_URL . "admin/settings.php?tab=branding");
        exit;
    }

    // 2. Upload Logo Baru (jika ada)
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

        if (in_array($ext, $allowed)) {
            $destDir = __DIR__ . '/../assets/images/';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            $filename = 'app_logo_' . time() . '.' . $ext;
            $destPath = $destDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // Sinkronisasi otomatis ke folder clone lain jika ada
                $alt1 = 'C:/xampp/htdocs/rakernas/assets/images/' . $filename;
                if ($destPath !== $alt1 && is_dir('C:/xampp/htdocs/rakernas/assets/images/')) {
                    @copy($destPath, $alt1);
                }
                $alt2 = 'e:/JOB/2026/11. November/RAKERNAS/APP RAKERNAS/assets/images/' . $filename;
                if ($destPath !== $alt2 && is_dir('e:/JOB/2026/11. November/RAKERNAS/APP RAKERNAS/assets/images/')) {
                    @copy($destPath, $alt2);
                }

                $relPath = 'assets/images/' . $filename;
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('app_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$relPath, $relPath]);
            }
        }
    }

    // 3. Simpan Seluruh Pengaturan Teks & Desain
    $settingsToUpdate = [
        // Tab 1: Info Acara
        'event_location'         => trim($_POST['event_location'] ?? ''),
        'event_date'             => trim($_POST['event_date'] ?? ''),
        'event_city'             => trim($_POST['event_city'] ?? 'Surabaya'),
        'event_name'             => trim($_POST['event_name'] ?? ''),
        'event_theme'            => trim($_POST['event_theme'] ?? ''),
        'contact_center'         => trim($_POST['contact_center'] ?? '0857-2726-6990'),
        'panitia_wa'             => trim($_POST['contact_center'] ?? '0857-2726-6990'),
        'panitia_contact_name'   => trim($_POST['panitia_contact_name'] ?? 'M Ilyas Balya'),
        'default_quota_utusan'   => (int)($_POST['default_quota_utusan'] ?? 10),
        'default_quota_peninjau' => (int)($_POST['default_quota_peninjau'] ?? 5),
        'arrival_day_options'    => trim($_POST['arrival_day_options'] ?? ''),
        'departure_day_options'  => trim($_POST['departure_day_options'] ?? ''),

        // Tab 2: Header & Branding
        'app_brand_title'        => trim($_POST['app_brand_title'] ?? 'AL KHIDMAH'),
        'app_brand_subtitle'     => trim($_POST['app_brand_subtitle'] ?? 'Sistem Rakernas'),

        // Tab 3: Desain ID Card
        'idcard_title'           => trim($_POST['idcard_title'] ?? 'RAKERNAS 2026'),
        'idcard_org_name'        => trim($_POST['idcard_org_name'] ?? 'PERKUMPULAN JAMA\'AH AL KHIDMAH'),
        'idcard_theme'           => trim($_POST['idcard_theme'] ?? 'emerald'),
        'idcard_footer_line1'    => trim($_POST['idcard_footer_line1'] ?? 'Wajib dikenakan selama rangkaian acara RAKERNAS'),
        'idcard_footer_line2'    => trim($_POST['idcard_footer_line2'] ?? 'Panitia Pelaksana RAKERNAS Jama\'ah Al Khidmah'),
        'idcard_show_room'       => isset($_POST['idcard_show_room']) ? '1' : '0',
        'idcard_show_komisi'     => isset($_POST['idcard_show_komisi']) ? '1' : '0',

        // Tab 4: Iuran & Pembayaran
        'fee_per_participant'    => (int)($_POST['fee_per_participant'] ?? 300000),
        'payment_bank_name'      => trim($_POST['payment_bank_name'] ?? ''),
        'bank_name'              => trim($_POST['payment_bank_name'] ?? ''),
        'payment_account_no'     => trim($_POST['payment_account_no'] ?? ''),
        'bank_account'           => trim($_POST['payment_account_no'] ?? ''),
        'payment_account_name'   => trim($_POST['payment_account_name'] ?? ''),
        'bank_account_name'      => trim($_POST['payment_account_name'] ?? ''),
        'payment_instructions'   => trim($_POST['payment_instructions'] ?? ''),
    ];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (:k, :v) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        foreach ($settingsToUpdate as $key => $val) {
            $stmt->execute([':k' => $key, ':v' => $val]);
        }

        $activeTab = $_POST['active_tab'] ?? 'info';
        setFlash('success', 'Pengaturan berhasil disimpan dan langsung diterapkan ke seluruh sistem!');
        header("Location: " . BASE_URL . "admin/settings.php?tab=" . urlencode($activeTab));
        exit;
    } catch (Exception $e) {
        setFlash('danger', 'Gagal menyimpan pengaturan: ' . $e->getMessage());
    }
}

// Ambil semua pengaturan
$currentSettings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$appLogoUrl = getAppLogoUrl();
$currentTab = $_GET['tab'] ?? 'info';

$page_title = "Pengaturan Header & Desain ID Card";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto mb-16 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider text-emerald-800 bg-emerald-50 px-3 py-1 rounded-full mb-1">
                Kustomisasi Aplikasi
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Pengaturan Header & Desain ID Card</h1>
            <p class="text-xs text-slate-500 mt-0.5">Ubah logo instansi, teks bilah navigasi, informasi acara, dan tata letak kartu peserta</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>print_card.php?code=RKN-JTM-001" target="_blank" class="px-4 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition flex items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Lihat Contoh ID Card</span>
            </a>
            <a href="<?= BASE_URL ?>admin/index.php" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition">
                &larr; Dashboard
            </a>
        </div>
    </div>

    <!-- Tabs Navigation Bar -->
    <div class="flex border-b border-slate-200 gap-2">
        <button type="button" onclick="switchTab('info')" id="tab-btn-info" class="tab-btn px-5 py-3 text-xs font-bold border-b-2 transition flex items-center gap-2 <?= $currentTab === 'info' ? 'border-emerald-600 text-emerald-700 bg-white rounded-t-2xl shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>1. Informasi Acara & Lokasi</span>
        </button>

        <button type="button" onclick="switchTab('branding')" id="tab-btn-branding" class="tab-btn px-5 py-3 text-xs font-bold border-b-2 transition flex items-center gap-2 <?= $currentTab === 'branding' ? 'border-emerald-600 text-emerald-700 bg-white rounded-t-2xl shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span>2. Header & Logo Aplikasi</span>
        </button>

        <button type="button" onclick="switchTab('idcard')" id="tab-btn-idcard" class="tab-btn px-5 py-3 text-xs font-bold border-b-2 transition flex items-center gap-2 <?= $currentTab === 'idcard' ? 'border-emerald-600 text-emerald-700 bg-white rounded-t-2xl shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            <span>3. Desain E-ID Card Peserta</span>
        </button>

        <button type="button" onclick="switchTab('payment')" id="tab-btn-payment" class="tab-btn px-5 py-3 text-xs font-bold border-b-2 transition flex items-center gap-2 <?= $currentTab === 'payment' ? 'border-amber-500 text-amber-700 bg-white rounded-t-2xl shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span>4. Iuran & Rekening Panitia</span>
        </button>
    </div>

    <!-- Main Settings Form -->
    <form action="" method="POST" enctype="multipart/form-data" id="main-settings-form">
        <input type="hidden" name="active_tab" id="active-tab-input" value="<?= clean($currentTab) ?>">

        <!-- TAB 1: INFORMASI ACARA & LOKASI -->
        <div id="tab-content-info" class="tab-pane <?= $currentTab === 'info' ? '' : 'hidden' ?> bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 space-y-6">
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Lokasi & Waktu Pelaksanaan
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Lokasi Kegiatan (Event Location) *
                        </label>
                        <input type="text" name="event_location" required value="<?= clean($currentSettings['event_location'] ?? 'Pondok Pesantren Assalafi Al Fithrah & Gedung Serbaguna') ?>" placeholder="Contoh: Pondok Pesantren Assalafi Al Fithrah Surabaya" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                        <p class="text-[11px] text-slate-400 mt-1">Ditampilkan pada kop surat bukti pendaftaran, header notifikasi, dan halaman beranda.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Tanggal Kegiatan (Event Date) *
                            </label>
                            <input type="text" name="event_date" required value="<?= clean($currentSettings['event_date'] ?? '15 - 17 November 2026') ?>" placeholder="Contoh: 15 - 17 November 2026" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Kota Tempat Pelaksanaan (Untuk Tanda Tangan Dokumen)
                            </label>
                            <input type="text" name="event_city" value="<?= clean($currentSettings['event_city'] ?? 'Surabaya') ?>" placeholder="Contoh: Surabaya" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-slate-100">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                                <span>Pilihan Hari Kedatangan</span>
                                <span class="text-[10px] text-emerald-600 font-semibold lowercase">1 baris = 1 opsi</span>
                            </label>
                            <textarea name="arrival_day_options" rows="3" placeholder="Satu pilihan per baris, contoh:&#10;Kamis, 14 Nov 2026&#10;Jumat, 15 Nov 2026&#10;Sabtu, 16 Nov 2026" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none font-mono"><?= clean($currentSettings['arrival_day_options'] ?? "Kamis, 14 Nov 2026\nJumat, 15 Nov 2026\nSabtu, 16 Nov 2026") ?></textarea>
                            <p class="text-[11px] text-slate-400 mt-1">Pilihan dropdown hari kedatangan di formulir registrasi delegasi.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                                <span>Pilihan Hari Kepulangan</span>
                                <span class="text-[10px] text-emerald-600 font-semibold lowercase">1 baris = 1 opsi</span>
                            </label>
                            <textarea name="departure_day_options" rows="3" placeholder="Satu pilihan per baris, contoh:&#10;Minggu, 17 Nov 2026&#10;Senin, 18 Nov 2026" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none font-mono"><?= clean($currentSettings['departure_day_options'] ?? "Minggu, 17 Nov 2026\nSenin, 18 Nov 2026") ?></textarea>
                            <p class="text-[11px] text-slate-400 mt-1">Pilihan dropdown hari kepulangan di formulir registrasi delegasi.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gold-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                    Nama & Tema Rakernas
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Resmi Kegiatan *</label>
                        <input type="text" name="event_name" required value="<?= clean($currentSettings['event_name'] ?? 'Rapat Kerja Nasional (RAKERNAS) Perkumpulan Jama\'ah Al Khidmah') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tema Rakernas</label>
                        <textarea name="event_theme" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none"><?= clean($currentSettings['event_theme'] ?? 'Meneguhkan Khidmah, Menguatkan Ukhuwah, Menuju Kemandirian Jam\'iyyah') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp Panitia / Pusat Informasi</label>
                            <input type="text" name="contact_center" value="<?= clean($currentSettings['contact_center'] ?? ($currentSettings['panitia_wa'] ?? '0857-2726-6990')) ?>" placeholder="0857-2726-6990" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Penanggung Jawab / Kontak Panitia</label>
                            <input type="text" name="panitia_contact_name" value="<?= clean($currentSettings['panitia_contact_name'] ?? 'M Ilyas Balya') ?>" placeholder="M Ilyas Balya" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Standar Kuota Bawaan PW Baru
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Default Kuota Utusan</label>
                        <input type="number" name="default_quota_utusan" value="<?= (int)($currentSettings['default_quota_utusan'] ?? 10) ?>" min="1" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Default Kuota Peninjau</label>
                        <input type="number" name="default_quota_peninjau" value="<?= (int)($currentSettings['default_quota_peninjau'] ?? 5) ?>" min="0" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: HEADER & LOGO APLIKASI -->
        <div id="tab-content-branding" class="tab-pane <?= $currentTab === 'branding' ? '' : 'hidden' ?> bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 space-y-6">
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Logo Aplikasi (Header & ID Card)
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                    <!-- Preview Logo Saat Ini -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center flex flex-col items-center justify-center">
                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-3">Tampilan Logo Saat Ini:</div>
                        <div class="w-24 h-24 rounded-2xl bg-white border border-slate-200 shadow-sm p-2 flex items-center justify-center mb-3">
                            <?php if ($appLogoUrl): ?>
                                <img src="<?= $appLogoUrl ?>?v=<?= time() ?>" alt="Logo" class="max-w-full max-h-full object-contain">
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-alkhidmah-700 to-alkhidmah-900 flex items-center justify-center text-white shadow-inner">
                                    <svg class="w-10 h-10 text-gold-400" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-[11px] text-slate-500 font-medium">
                            <?= $appLogoUrl ? 'Logo Kustom Aktif' : 'Lambang Default Al Khidmah' ?>
                        </div>
                        <?php if ($appLogoUrl): ?>
                            <button type="submit" name="delete_logo" value="1" onclick="return confirm('Hapus logo ini dan gunakan kembali logo lambang default?');" class="mt-3 text-[11px] text-rose-600 hover:text-rose-800 font-semibold underline">
                                Hapus & Reset Logo
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Upload Input Box -->
                    <div class="md:col-span-2 space-y-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Unggah File Logo Baru</label>
                        <div class="border-2 border-dashed border-slate-300 rounded-2xl p-6 text-center hover:border-emerald-600 transition bg-slate-50/50">
                            <input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.svg,.webp" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                            <p class="text-[11px] text-slate-400 mt-2">
                                Format didukung: <strong>PNG (Transparan), JPG, SVG, WebP</strong>. Disarankan gambar persegi/lingkaran resolusi minimal 200x200 pixel.
                            </p>
                        </div>
                        <div class="text-[11px] text-slate-500 bg-amber-50 border border-amber-200/80 p-3 rounded-xl flex items-start gap-2">
                            <span class="text-amber-600">💡</span>
                            <span>Logo ini akan otomatis tampil di sudut kiri navigasi bar atas aplikasi dan di bagian kepala (header) cetak E-ID Card delegasi.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teks Brand Header -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Teks Nama Brand di Header Navbar
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Judul Utama Brand</label>
                        <input type="text" name="app_brand_title" id="inp-brand-title" value="<?= clean($currentSettings['app_brand_title'] ?? 'AL KHIDMAH') ?>" placeholder="AL KHIDMAH" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Sub-Teks di Bawah Judul</label>
                        <input type="text" name="app_brand_subtitle" id="inp-brand-subtitle" value="<?= clean($currentSettings['app_brand_subtitle'] ?? 'Sistem Rakernas') ?>" placeholder="Sistem Rakernas" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: DESAIN E-ID CARD PESERTA -->
        <div id="tab-content-idcard" class="tab-pane <?= $currentTab === 'idcard' ? '' : 'hidden' ?> bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 space-y-8">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Kolom Kiri: Form Desain ID Card (7 Kolom) -->
                <div class="lg:col-span-7 space-y-6">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                            Pilihan Warna Tema ID Card
                        </h3>
                        
                        <?php $selectedTheme = $currentSettings['idcard_theme'] ?? 'emerald'; ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <label class="cursor-pointer border-2 rounded-2xl p-3 flex items-center gap-2.5 transition hover:shadow-sm <?= $selectedTheme === 'emerald' ? 'border-emerald-600 bg-emerald-50/40' : 'border-slate-200' ?>">
                                <input type="radio" name="idcard_theme" value="emerald" onchange="updatePreviewTheme('emerald')" <?= $selectedTheme === 'emerald' ? 'checked' : '' ?> class="text-emerald-600">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Hijau Emerald</div>
                                    <div class="text-[10px] text-slate-500">Khas Al Khidmah</div>
                                </div>
                            </label>

                            <label class="cursor-pointer border-2 rounded-2xl p-3 flex items-center gap-2.5 transition hover:shadow-sm <?= $selectedTheme === 'gold' ? 'border-amber-600 bg-amber-50/40' : 'border-slate-200' ?>">
                                <input type="radio" name="idcard_theme" value="gold" onchange="updatePreviewTheme('gold')" <?= $selectedTheme === 'gold' ? 'checked' : '' ?> class="text-amber-600">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Emas Keagungan</div>
                                    <div class="text-[10px] text-slate-500">Gold & Amber</div>
                                </div>
                            </label>

                            <label class="cursor-pointer border-2 rounded-2xl p-3 flex items-center gap-2.5 transition hover:shadow-sm <?= $selectedTheme === 'blue' ? 'border-blue-600 bg-blue-50/40' : 'border-slate-200' ?>">
                                <input type="radio" name="idcard_theme" value="blue" onchange="updatePreviewTheme('blue')" <?= $selectedTheme === 'blue' ? 'checked' : '' ?> class="text-blue-600">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Biru Royal</div>
                                    <div class="text-[10px] text-slate-500">Elegan & Berwibawa</div>
                                </div>
                            </label>

                            <label class="cursor-pointer border-2 rounded-2xl p-3 flex items-center gap-2.5 transition hover:shadow-sm <?= $selectedTheme === 'maroon' ? 'border-rose-600 bg-rose-50/40' : 'border-slate-200' ?>">
                                <input type="radio" name="idcard_theme" value="maroon" onchange="updatePreviewTheme('maroon')" <?= $selectedTheme === 'maroon' ? 'checked' : '' ?> class="text-rose-600">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Merah Marun</div>
                                    <div class="text-[10px] text-slate-500">Tegas & Mantap</div>
                                </div>
                            </label>

                            <label class="cursor-pointer border-2 rounded-2xl p-3 flex items-center gap-2.5 transition hover:shadow-sm <?= $selectedTheme === 'navy' ? 'border-indigo-600 bg-indigo-50/40' : 'border-slate-200' ?>">
                                <input type="radio" name="idcard_theme" value="navy" onchange="updatePreviewTheme('navy')" <?= $selectedTheme === 'navy' ? 'checked' : '' ?> class="text-indigo-600">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Deep Navy</div>
                                    <div class="text-[10px] text-slate-500">Formal Eksekutif</div>
                                </div>
                            </label>

                            <label class="cursor-pointer border-2 rounded-2xl p-3 flex items-center gap-2.5 transition hover:shadow-sm <?= $selectedTheme === 'slate' ? 'border-slate-600 bg-slate-50' : 'border-slate-200' ?>">
                                <input type="radio" name="idcard_theme" value="slate" onchange="updatePreviewTheme('slate')" <?= $selectedTheme === 'slate' ? 'checked' : '' ?> class="text-slate-600">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Hitam Minimalis</div>
                                    <div class="text-[10px] text-slate-500">Netral & Bersih</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Teks Header ID Card -->
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Teks Header & Footer ID Card
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Judul Kartu (Header Atas)</label>
                                <input type="text" name="idcard_title" id="inp-card-title" oninput="updatePreviewText()" value="<?= clean($currentSettings['idcard_title'] ?? 'RAKERNAS 2026') ?>" placeholder="RAKERNAS 2026" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Organisasi di Kartu</label>
                                <input type="text" name="idcard_org_name" id="inp-card-org" oninput="updatePreviewText()" value="<?= clean($currentSettings['idcard_org_name'] ?? 'PERKUMPULAN JAMA\'AH AL KHIDMAH') ?>" placeholder="PERKUMPULAN JAMA'AH AL KHIDMAH" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Footer Baris 1</label>
                                    <input type="text" name="idcard_footer_line1" id="inp-card-f1" oninput="updatePreviewText()" value="<?= clean($currentSettings['idcard_footer_line1'] ?? 'Wajib dikenakan selama rangkaian acara RAKERNAS') ?>" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Footer Baris 2</label>
                                    <input type="text" name="idcard_footer_line2" id="inp-card-f2" oninput="updatePreviewText()" value="<?= clean($currentSettings['idcard_footer_line2'] ?? 'Panitia Pelaksana RAKERNAS Jama\'ah Al Khidmah') ?>" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pilihan Elemen Data yang Ditampilkan -->
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-3">Informasi Tambahan pada Kartu</h3>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2.5 text-xs text-slate-700 font-medium cursor-pointer">
                                <input type="checkbox" name="idcard_show_room" id="chk-card-room" onchange="updatePreviewElements()" value="1" <?= ($currentSettings['idcard_show_room'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                                <span>Tampilkan Nomor Kamar / Wisma Penginapan pada ID Card</span>
                            </label>
                            <label class="flex items-center gap-2.5 text-xs text-slate-700 font-medium cursor-pointer">
                                <input type="checkbox" name="idcard_show_komisi" id="chk-card-komisi" onchange="updatePreviewElements()" value="1" <?= ($currentSettings['idcard_show_komisi'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                                <span>Tampilkan Nama Komisi Sidang pada ID Card</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Interactive Live Preview ID Card (5 Kolom) -->
                <div class="lg:col-span-5 bg-slate-50 border border-slate-200 rounded-3xl p-6 flex flex-col items-center">
                    <div class="flex items-center justify-between w-full mb-3 text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <span>Simulasi Tampilan ID Card:</span>
                        <span class="text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full font-mono">Real-time Preview</span>
                    </div>

                    <!-- ID Card Preview Mockup (matches print_card.php) -->
                    <div id="preview-card-box" class="w-[82mm] bg-white rounded-2xl overflow-hidden border border-slate-300 shadow-xl flex flex-col justify-between transition-all" style="min-height: 122mm;">
                        
                        <!-- Header Preview -->
                        <div id="preview-header-bg" class="bg-gradient-to-br from-emerald-950 via-emerald-900 to-emerald-800 text-white p-3 text-center relative overflow-hidden transition-all">
                            <div class="h-1 bg-amber-400 absolute top-0 left-0 right-0"></div>
                            <div class="flex items-center justify-center gap-1.5 mb-0.5">
                                <?php if ($appLogoUrl): ?>
                                    <img src="<?= $appLogoUrl ?>" class="w-5 h-5 object-contain">
                                <?php else: ?>
                                    <div class="w-5 h-5 rounded bg-emerald-800 flex items-center justify-center text-amber-400">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                                    </div>
                                <?php endif; ?>
                                <span id="prev-org-name" class="text-[8px] uppercase tracking-widest text-emerald-300 font-extrabold truncate">
                                    <?= clean($currentSettings['idcard_org_name'] ?? 'PERKUMPULAN JAMA\'AH AL KHIDMAH') ?>
                                </span>
                            </div>
                            <h2 id="prev-title" class="text-xs font-black tracking-tight leading-tight uppercase text-white">
                                <?= clean($currentSettings['idcard_title'] ?? 'RAKERNAS 2026') ?>
                            </h2>
                            <p class="text-[7px] text-emerald-200 mt-0.5">
                                <?= clean($currentSettings['event_date'] ?? '15 - 17 November 2026') ?>
                            </p>
                        </div>

                        <!-- Ribbon -->
                        <div id="preview-ribbon" class="bg-emerald-700 text-white py-0.5 px-3 text-center text-[10px] font-black tracking-wider uppercase">
                            PESERTA UTUSAN
                        </div>

                        <!-- Body Mockup -->
                        <div class="p-3 text-center flex-grow flex flex-col items-center justify-center space-y-2">
                            <div class="w-full">
                                <h3 class="text-xs font-extrabold text-slate-900 leading-tight uppercase">
                                    KH. MUHAMMAD NAJIB
                                </h3>
                                <div class="text-[10px] font-bold text-emerald-800 mt-0.5">
                                    Ketua Pengurus Daerah
                                </div>
                                <div class="text-[9px] text-slate-600">
                                    PD Surabaya • PW Jawa Timur
                                </div>
                            </div>

                            <!-- QR Code Preview -->
                            <div class="bg-white p-1 rounded-xl border border-emerald-800 inline-block shadow-sm">
                                <img src="<?= getQRCodeUrl('RKN-JTM-001', 100) ?>" class="w-20 h-20 object-contain mx-auto">
                                <div class="text-[8px] font-mono font-bold text-slate-700 tracking-wider">
                                    RKN-JTM-001
                                </div>
                            </div>

                            <!-- Details Box -->
                            <div class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-left text-[9px] space-y-0.5">
                                <div id="prev-box-komisi" class="flex justify-between items-center">
                                    <span class="text-slate-500">Komisi:</span>
                                    <span class="font-bold text-slate-800">Komisi A (Organisasi)</span>
                                </div>
                                <div id="prev-box-room" class="flex justify-between items-center border-t border-slate-200/60 pt-0.5">
                                    <span class="text-slate-500">Kamar:</span>
                                    <span class="font-bold text-emerald-800">Wisma Utama - 101</span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Ribbon -->
                        <div id="preview-footer-bg" class="bg-slate-900 text-white p-1.5 text-center text-[7px] leading-tight">
                            <div id="prev-footer-1"><?= clean($currentSettings['idcard_footer_line1'] ?? 'Wajib dikenakan selama rangkaian acara RAKERNAS') ?></div>
                            <div id="prev-footer-2" class="text-slate-400 mt-0.5"><?= clean($currentSettings['idcard_footer_line2'] ?? 'Panitia Pelaksana RAKERNAS Jama\'ah Al Khidmah') ?></div>
                        </div>

                    </div>
                    <div class="text-[10px] text-slate-400 mt-3 text-center">Ukuran standar cetak B4/A6 (85mm x 120mm)</div>
                </div>

            </div>

        </div>

        <!-- TAB 4: IURAN & REKENING PANITIA -->
        <div id="tab-content-payment" class="tab-pane <?= $currentTab === 'payment' ? '' : 'hidden' ?> bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 space-y-6">

            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Nominal Iuran Delegasi
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nominal Iuran per Orang (Rupiah) *
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-500">Rp</span>
                            <input type="number" name="fee_per_participant" min="0" step="1000" required
                                   value="<?= (int)($currentSettings['fee_per_participant'] ?? 350000) ?>"
                                   class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 text-sm font-bold text-alkhidmah-800 focus:ring-2 focus:ring-amber-400 focus:border-amber-400 focus:outline-none">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1.5">
                            Tagihan per PW = Jumlah Delegasi Terdaftar × Nominal ini. Perubahan langsung berlaku untuk semua PW yang belum membayar.
                        </p>
                        <!-- Preview kalkulasi -->
                        <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Contoh: PW dengan 10 delegasi akan ditagih <strong id="preview-fee-calc">Rp <?= number_format((int)($currentSettings['fee_per_participant'] ?? 350000) * 10, 0, ',', '.') ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Rekening Tujuan Transfer Panitia
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Bank</label>
                        <input type="text" name="payment_bank_name"
                               value="<?= clean($currentSettings['payment_bank_name'] ?? ($currentSettings['bank_name'] ?? 'Bank Syariah Indonesia (BSI)')) ?>"
                               placeholder="contoh: Bank Syariah Indonesia (BSI)"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor Rekening</label>
                        <input type="text" name="payment_account_no"
                               value="<?= clean($currentSettings['payment_account_no'] ?? ($currentSettings['bank_account'] ?? '')) ?>"
                               placeholder="contoh: 7123456789"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-mono font-bold tracking-wider focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Pemilik Rekening (Atas Nama)</label>
                        <input type="text" name="payment_account_name"
                               value="<?= clean($currentSettings['payment_account_name'] ?? ($currentSettings['bank_account_name'] ?? 'Bendahara RAKERNAS Jama\'ah Al Khidmah')) ?>"
                               placeholder="contoh: Bendahara RAKERNAS Jama'ah Al Khidmah"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Instruksi / Petunjuk Transfer</label>
                        <textarea name="payment_instructions" rows="3"
                                  placeholder="Instruksi yang ditampilkan kepada akun PW saat halaman pembayaran..."
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none resize-none"><?= clean($currentSettings['payment_instructions'] ?? '') ?></textarea>
                        <p class="text-[11px] text-slate-400 mt-1">Contoh: Cantumkan berita transfer: IURAN [KODE PW]</p>
                    </div>
                </div>

                <!-- Preview rekening -->
                <div class="mt-5 p-5 bg-alkhidmah-50 border border-alkhidmah-200 rounded-2xl">
                    <p class="text-xs font-bold text-alkhidmah-700 mb-3">Preview tampilan di halaman pembayaran PW:</p>
                    <div class="grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <div class="text-xs text-alkhidmah-600 font-medium">Bank</div>
                            <div class="font-bold text-alkhidmah-900"><?= clean($currentSettings['payment_bank_name'] ?? 'Bank Syariah Indonesia (BSI)') ?></div>
                        </div>
                        <div>
                            <div class="text-xs text-alkhidmah-600 font-medium">Nomor Rekening</div>
                            <div class="font-extrabold text-alkhidmah-900 tracking-widest"><?= clean($currentSettings['payment_account_no'] ?? '-') ?></div>
                        </div>
                        <div>
                            <div class="text-xs text-alkhidmah-600 font-medium">Atas Nama</div>
                            <div class="font-bold text-alkhidmah-900"><?= clean($currentSettings['payment_account_name'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sticky Bottom Bar Simpan -->
        <div class="sticky bottom-4 bg-white/95 backdrop-blur-md p-4 rounded-2xl border border-slate-200 shadow-xl flex items-center justify-between gap-4 mt-6">
            <div class="text-xs text-slate-500">
                Klik tombol di samping untuk menyimpan seluruh perubahan.
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-alkhidmah-800 text-white font-bold text-xs hover:bg-alkhidmah-900 transition shadow-md shadow-emerald-900/10 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Simpan Pengaturan Sekarang</span>
                </button>
            </div>
        </div>

    </form>
</div>

<script>
// Switch Tabs
function switchTab(tabId) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-emerald-600', 'text-emerald-700', 'bg-white', 'rounded-t-2xl', 'shadow-sm');
        btn.classList.add('border-transparent', 'text-slate-500');
    });

    const activePane = document.getElementById('tab-content-' + tabId);
    const activeBtn = document.getElementById('tab-btn-' + tabId);
    if (activePane && activeBtn) {
        activePane.classList.remove('hidden');
        activeBtn.classList.remove('border-transparent', 'text-slate-500');
        activeBtn.classList.add('border-emerald-600', 'text-emerald-700', 'bg-white', 'rounded-t-2xl', 'shadow-sm');
        document.getElementById('active-tab-input').value = tabId;
    }
}

// Update Interactive Preview
function updatePreviewText() {
    document.getElementById('prev-title').innerText = document.getElementById('inp-card-title').value.toUpperCase() || 'RAKERNAS 2026';
    document.getElementById('prev-org-name').innerText = document.getElementById('inp-card-org').value.toUpperCase() || 'PERKUMPULAN JAMA\'AH AL KHIDMAH';
    document.getElementById('prev-footer-1').innerText = document.getElementById('inp-card-f1').value || 'Wajib dikenakan selama rangkaian acara';
    document.getElementById('prev-footer-2').innerText = document.getElementById('inp-card-f2').value || 'Panitia Pelaksana';
}

function updatePreviewElements() {
    const showRoom = document.getElementById('chk-card-room').checked;
    const showKomisi = document.getElementById('chk-card-komisi').checked;

    document.getElementById('prev-box-room').style.display = showRoom ? 'flex' : 'none';
    document.getElementById('prev-box-komisi').style.display = showKomisi ? 'flex' : 'none';
}

function updatePreviewTheme(theme) {
    const header = document.getElementById('preview-header-bg');
    const ribbon = document.getElementById('preview-ribbon');

    const themes = {
        'emerald': { header: 'from-emerald-950 via-emerald-900 to-emerald-800', ribbon: 'bg-emerald-700' },
        'gold':    { header: 'from-amber-950 via-amber-900 to-amber-800', ribbon: 'bg-amber-600' },
        'blue':    { header: 'from-blue-950 via-blue-900 to-blue-800', ribbon: 'bg-blue-600' },
        'maroon':  { header: 'from-rose-950 via-rose-900 to-rose-800', ribbon: 'bg-rose-700' },
        'navy':    { header: 'from-indigo-950 via-indigo-900 to-indigo-800', ribbon: 'bg-indigo-800' },
        'slate':   { header: 'from-slate-950 via-slate-900 to-slate-800', ribbon: 'bg-slate-700' }
    };

    const sel = themes[theme] || themes['emerald'];
    header.className = `bg-gradient-to-br ${sel.header} text-white p-3 text-center relative overflow-hidden transition-all`;
    ribbon.className = `${sel.ribbon} text-white py-0.5 px-3 text-center text-[10px] font-black tracking-wider uppercase`;
}

// Inisialisasi on load
window.addEventListener('DOMContentLoaded', () => {
    updatePreviewElements();
    const selTheme = document.querySelector('input[name="idcard_theme"]:checked');
    if (selTheme) updatePreviewTheme(selTheme.value);

    // Live preview kalkulasi iuran
    const feeInput = document.querySelector('input[name="fee_per_participant"]');
    const feePreview = document.getElementById('preview-fee-calc');
    if (feeInput && feePreview) {
        feeInput.addEventListener('input', () => {
            const val = parseInt(feeInput.value) || 0;
            feePreview.textContent = 'Rp ' + (val * 10).toLocaleString('id-ID');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
