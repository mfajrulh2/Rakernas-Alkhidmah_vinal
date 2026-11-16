<?php
/**
 * Helper Functions
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

if (!defined('APP_INIT')) {
    require_once __DIR__ . '/../config/database.php';
}

// Polyfill kompatibilitas PHP 7.x (str_starts_with, str_ends_with, str_contains)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle === '' || $needle === substr($haystack, -strlen($needle));
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

/**
 * Sanitasi string input
 */
function clean($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Set flash alert session
 */
function setFlash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Render flash message jika ada
 */
function displayFlash() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        $bgColors = [
            'success' => 'bg-emerald-50 border-emerald-500 text-emerald-800',
            'danger'  => 'bg-rose-50 border-rose-500 text-rose-800',
            'warning' => 'bg-amber-50 border-amber-500 text-amber-800',
            'info'    => 'bg-blue-50 border-blue-500 text-blue-800'
        ];
        $icons = [
            'success' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'danger'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'warning' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />',
            'info'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'
        ];

        $class = $bgColors[$flash['type']] ?? $bgColors['info'];
        $icon = $icons[$flash['type']] ?? $icons['info'];

        echo "
        <div class='mb-6 p-4 rounded-xl border-l-4 shadow-sm flex items-start gap-3 {$class} transition-all'>
            <svg class='w-5 h-5 flex-shrink-0 mt-0.5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                {$icon}
            </svg>
            <div class='text-sm font-medium leading-relaxed'>" . clean($flash['message']) . "</div>
        </div>
        ";
    }
}

/**
 * Ambil pesan flash (dan hapus dari session)
 */
function getFlash($type = null) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        if ($type === null || $flash['type'] === $type) {
            unset($_SESSION['flash']);
            return $flash['message'];
        }
    }
    return null;
}

/**
 * Format tanggal Indonesia
 */
function formatTanggalIndo($dateStr, $withTime = false) {
    if (!$dateStr || $dateStr == '0000-00-00 00:00:00' || $dateStr == '0000-00-00') {
        return '-';
    }
    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($dateStr);
    $d = date('j', $timestamp);
    $m = $bulanIndo[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp);

    $result = "{$d} {$m} {$y}";
    if ($withTime) {
        $result .= " pk " . date('H:i', $timestamp) . " WIB";
    }
    return $result;
}

/**
 * Generate QR Code URL menggunakan API gratis tanpa ketergantungan library luar
 */
function getQRCodeUrl($data, $size = 200) {
    $encoded = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}&margin=6";
}

/**
 * Generate Registration Code Unik
 * Contoh: RKN-JTM-042
 */
function generateRegistrationCode($pwCode) {
    $cleanPrefix = preg_replace('/[^A-Za-z0-9]/', '', str_replace('PW-', '', strtoupper($pwCode)));
    if (empty($cleanPrefix)) $cleanPrefix = 'GEN';
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return 'RKN-' . $cleanPrefix . '-' . $random;
}

/**
 * Ambil Pengaturan dari Database
 */
function getSetting($key, $default = '') {
    static $settingsCache = null;
    if ($settingsCache === null) {
        $pdo = getDB();
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $settingsCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $settingsCache = [];
        }
    }
    return $settingsCache[$key] ?? $default;
}

/**
 * Ambil URL Logo Aplikasi (jika ada yang diupload)
 */
function getAppLogoUrl() {
    $logo = getSetting('app_logo', '');
    if (!empty($logo) && file_exists(__DIR__ . '/../' . $logo)) {
        return BASE_URL . $logo;
    }
    return null;
}

/**
 * Ambil Info Rekening Pembayaran Resmi dari Pengaturan
 */
function getPaymentBankInfo() {
    $bankName = getSetting('payment_bank_name');
    if (empty($bankName)) $bankName = getSetting('bank_name', 'Bank Syariah Indonesia (BSI)');

    $accountNo = getSetting('payment_account_no');
    if (empty($accountNo)) $accountNo = getSetting('bank_account', '7123456789');

    $accountHolder = getSetting('payment_account_name');
    if (empty($accountHolder)) $accountHolder = getSetting('bank_account_name', 'Bendahara RAKERNAS Jama\'ah Al Khidmah');

    $instructions = getSetting('payment_instructions', '');

    return [
        'bank_name'      => $bankName,
        'account_no'     => $accountNo,
        'account_holder' => $accountHolder,
        'instructions'   => $instructions,
    ];
}

/**
 * Ambil Kontak WhatsApp & Penanggung Jawab Panitia
 */
function getPanitiaContact() {
    $wa = getSetting('panitia_wa');
    if (empty($wa)) $wa = getSetting('contact_center', '0857-2726-6990');

    $name = getSetting('panitia_contact_name');
    if (empty($name)) $name = 'M Ilyas Balya';

    $cleanWA = preg_replace('/[^0-9]/', '', $wa);
    if (str_starts_with($cleanWA, '0')) {
        $cleanWA = '62' . substr($cleanWA, 1);
    }

    return [
        'wa'      => $wa,
        'name'    => $name,
        'wa_link' => 'https://wa.me/' . $cleanWA
    ];
}

/**
 * Ambil Info Acara Resmi (Lokasi, Tanggal, Kota, Nama Kegiatan)
 */
function getEventInfo() {
    $eventName     = getSetting('event_name', 'Rapat Kerja Nasional (RAKERNAS)');
    $eventLocation = getSetting('event_location', 'Pondok Pesantren Assalafi Al Fithrah');
    $eventDate     = getSetting('event_date', '15 - 17 November 2026');
    $eventCity     = getSetting('event_city');

    if (empty($eventCity)) {
        // Coba ekstrak kota dari event_location jika mengandung nama kota
        $locLower = strtolower($eventLocation);
        $cities = ['surabaya', 'semarang', 'jakarta', 'bandung', 'yogyakarta', 'jogja', 'solo', 'surakarta', 'malang', 'gresik', 'sidoarjo', 'kediri', 'bogor', 'bekasi', 'tangerang', 'depok', 'medan', 'makassar', 'palembang', 'denpasar', 'banjarmasin', 'samarinda', 'pontianak', 'lampung'];
        foreach ($cities as $c) {
            if (strpos($locLower, $c) !== false) {
                $eventCity = ucwords($c);
                break;
            }
        }
        if (empty($eventCity)) {
            $eventCity = 'Surabaya';
        }
    }

    return [
        'name'     => $eventName,
        'location' => $eventLocation,
        'date'     => $eventDate,
        'city'     => $eventCity,
    ];
}

/**
 * Ambil daftar pilihan hari kedatangan dari Pengaturan
 */
function getArrivalDayOptions() {
    $raw = getSetting('arrival_day_options');
    if (!empty($raw)) {
        $lines = preg_split('/[,\n\r]+/', $raw);
        $options = array_values(array_filter(array_map('trim', $lines)));
        if (!empty($options)) {
            return $options;
        }
    }
    return [
        'Kamis, 14 Nov 2026',
        'Jumat, 15 Nov 2026',
        'Sabtu, 16 Nov 2026'
    ];
}

/**
 * Ambil daftar pilihan hari kepulangan dari Pengaturan
 */
function getDepartureDayOptions() {
    $raw = getSetting('departure_day_options');
    if (!empty($raw)) {
        $lines = preg_split('/[,\n\r]+/', $raw);
        $options = array_values(array_filter(array_map('trim', $lines)));
        if (!empty($options)) {
            return $options;
        }
    }
    return [
        'Minggu, 17 Nov 2026',
        'Senin, 18 Nov 2026'
    ];
}

/**
 * Tipe badge peserta dengan warna elegan
 */
function renderParticipantBadge($type) {
    switch (strtolower($type)) {
        case 'utusan':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">Utusan</span>';
        case 'peninjau':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300">Peninjau</span>';
        case 'pengurus_pusat':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 border border-indigo-300">Pengurus Pusat</span>';
        case 'undangan':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 border border-purple-300">Undangan</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 border border-gray-300">' . clean(ucfirst($type)) . '</span>';
    }
}

/**
 * Tipe status kehadiran
 */
function renderAttendanceBadge($isAttended, $attendedAt = null) {
    if ($isAttended) {
        $time = $attendedAt ? "<br><span class='text-[10px] text-emerald-600'>" . date('H:i', strtotime($attendedAt)) . " WIB</span>" : "";
        return '<span class="inline-flex flex-col items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-300">
                    <span class="flex items-center gap-1"><svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg> Hadir</span>' . $time . '
                </span>';
    }
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-300">Belum Hadir</span>';
}

/**
 * Format Angka ke Rupiah
 */
function formatRupiah($nominal) {
    return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
}

/**
 * Pastikan Tabel Payments & Pengaturan Terkait Sudah Tersedia (Auto Migration)
 */
function ensurePaymentTableExists() {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $pdo = getDB();
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `payments` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `invoice_code` VARCHAR(50) NOT NULL UNIQUE,
              `pw_id` INT NOT NULL,
              `total_participants` INT NOT NULL DEFAULT 0,
              `amount_per_person` DECIMAL(15,2) NOT NULL DEFAULT 0,
              `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
              `bank_name` VARCHAR(80) DEFAULT NULL,
              `account_number` VARCHAR(80) DEFAULT NULL,
              `sender_name` VARCHAR(120) DEFAULT NULL,
              `payment_date` DATE DEFAULT NULL,
              `proof_file` VARCHAR(255) DEFAULT NULL,
              `status` ENUM('unpaid', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'unpaid',
              `verified_by` INT DEFAULT NULL,
              `verified_at` DATETIME DEFAULT NULL,
              `reject_reason` TEXT DEFAULT NULL,
              `notes` TEXT DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT `fk_payments_pw` FOREIGN KEY (`pw_id`) REFERENCES `wilayah` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Insert default iuran settings if not set
        $pdo->exec("
            INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
            ('fee_per_participant', '350000'),
            ('payment_bank_name', 'Bank Syariah Indonesia (BSI)'),
            ('payment_account_no', '7123456789'),
            ('payment_account_name', 'Bendahara RAKERNAS Al Khidmah'),
            ('payment_instructions', 'Transfer sesuai nominal tagihan delegasi ke rekening resmi panitia. Cantumkan berita transfer: IURAN [KODE PW] (contoh: IURAN PW-JATIM). Setelah transfer, unggah bukti pembayaran di portal ini.');
        ");
    } catch (Exception $e) {
        // Abaikan jika tabel sudah ada atau permission terbatas
    }
}

/**
 * Cek status pembayaran dan rincian iuran PW
 */
function getPWPaymentInfo($pw_id) {
    ensurePaymentTableExists();
    $pdo = getDB();

    // Hitung total delegasi terdaftar untuk PW ini
    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM participants WHERE pw_id = ?");
    $stmtC->execute([$pw_id]);
    $totalPeserta = (int)$stmtC->fetchColumn();

    $feePerPerson = (float)getSetting('fee_per_participant', '350000');
    $expectedTotal = $totalPeserta * $feePerPerson;

    // Ambil pembayaran terakhir
    $stmtP = $pdo->prepare("SELECT * FROM payments WHERE pw_id = ? ORDER BY id DESC LIMIT 1");
    $stmtP->execute([$pw_id]);
    $payment = $stmtP->fetch() ?: null;

    $status = $payment ? $payment['status'] : ($totalPeserta > 0 ? 'unpaid' : 'unpaid');
    $isVerified = ($status === 'verified');

    return [
        'status'              => $status,
        'is_verified'         => $isVerified,
        'payment'             => $payment,
        'total_participants'  => $totalPeserta,
        'fee_per_person'      => $feePerPerson,
        'expected_total'      => $expectedTotal
    ];
}

/**
 * Hitung jumlah pembayaran yang butuh validasi (pending) baik dari payments maupun registration_groups
 */
function countPendingPayments() {
    ensurePaymentTableExists();
    ensureRegistrationTablesExist();
    $pdo = getDB();
    try {
        $countOld = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
        $countNew = (int)$pdo->query("SELECT COUNT(*) FROM registration_groups WHERE payment_status = 'pending'")->fetchColumn();
        return $countOld + $countNew;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Tipe badge status pembayaran
 */
function renderPaymentBadge($status) {
    switch (strtolower($status)) {
        case 'verified':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        Lunas / Terverifikasi
                    </span>';
        case 'pending':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                        <svg class="w-3.5 h-3.5 text-amber-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>
                        Menunggu Validasi
                    </span>';
        case 'rejected':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300">
                        <svg class="w-3.5 h-3.5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                        Ditolak
                    </span>';
        default:
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-300">
                        Belum Konfirmasi
                    </span>';
    }
}

/**
 * Pastikan Kolom Baru pada Tabel Participants Sudah Tersedia (Auto Migration)
 */
function ensureParticipantColumnsExist() {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $pdo = getDB();
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `participants`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('unsur_organisasi', $cols)) {
            $pdo->exec("ALTER TABLE `participants` ADD COLUMN `unsur_organisasi` VARCHAR(80) NOT NULL DEFAULT 'Al Khidmah' AFTER `gender`");
        }
        if (!in_array('photo_file', $cols)) {
            $pdo->exec("ALTER TABLE `participants` ADD COLUMN `photo_file` VARCHAR(255) DEFAULT NULL AFTER `phone`");
        }
    } catch (Exception $e) {
        // Abaikan jika gagal
    }
}
ensureParticipantColumnsExist();

/**
 * Daftar Pilihan Unsur Organisasi
 */
function getUnsurOrganisasiOptions() {
    return [
        'Al Khidmah',
        'Ath Thoriqoh',
        'UCC'
    ];
}

/**
 * Daftar Pilihan Komisi Sidang
 */
function getKomisiOptions() {
    return [
        'A. Bidang Organisasi',
        'B. Bidang Kemajlisan & Kethoriqohan',
        'C. Bidang Usaha Ekonomi',
        'D. Bidang Kepemudaan',
        'E. Bidang IT dan Multimedia'
    ];
}

/**
 * Render Badge Unsur Organisasi
 */
function renderUnsurBadge($unsur) {
    $u = trim($unsur ?: 'Al Khidmah');
    switch ($u) {
        case 'Ath Thoriqoh':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300">Ath Thoriqoh</span>';
        case 'UCC':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-300">UCC</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">' . clean($u) . '</span>';
    }
}

/**
 * Helper Upload Foto Peserta (Maks 5 MB, JPG/PNG/WebP)
 */
function uploadParticipantPhoto($fileData, $index = null) {
    $name     = $index !== null ? ($fileData['name'][$index] ?? '') : ($fileData['name'] ?? '');
    $tmpName  = $index !== null ? ($fileData['tmp_name'][$index] ?? '') : ($fileData['tmp_name'] ?? '');
    $error    = $index !== null ? ($fileData['error'][$index] ?? UPLOAD_ERR_NO_FILE) : ($fileData['error'] ?? UPLOAD_ERR_NO_FILE);
    $size     = $index !== null ? ($fileData['size'][$index] ?? 0) : ($fileData['size'] ?? 0);

    if ($error === UPLOAD_ERR_NO_FILE || empty($name)) {
        return ['uploaded' => false, 'path' => null, 'error' => null];
    }

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || $size > 5 * 1024 * 1024) {
        return ['uploaded' => false, 'path' => null, 'error' => 'Ukuran file foto melebihi batas maksimal 5 MB.'];
    }

    if ($error !== UPLOAD_ERR_OK) {
        return ['uploaded' => false, 'path' => null, 'error' => 'Gagal mengunggah file foto (Error code: ' . $error . ').'];
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts)) {
        return ['uploaded' => false, 'path' => null, 'error' => 'Format foto tidak didukung. Gunakan file JPG, JPEG, PNG, atau WebP.'];
    }

    $uploadDir = __DIR__ . '/../uploads/participants/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = 'foto_' . time() . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($tmpName, $targetPath)) {
        return ['uploaded' => true, 'path' => 'uploads/participants/' . $filename, 'error' => null];
    }

    return ['uploaded' => false, 'path' => null, 'error' => 'Gagal menyimpan file foto ke server.'];
}

/**
 * Alias handlePhotoUpload untuk kompatibilitas
 */
function handlePhotoUpload($fileData, $index = null) {
    return uploadParticipantPhoto($fileData, $index);
}

/**
 * Helper Upload Bukti Transfer Pembayaran (Maks 5 MB, JPG/PNG/WebP/PDF)
 */
function uploadPaymentProof($fileData) {
    $name    = $fileData['name'] ?? '';
    $tmpName = $fileData['tmp_name'] ?? '';
    $error   = $fileData['error'] ?? UPLOAD_ERR_NO_FILE;
    $size    = $fileData['size'] ?? 0;

    if ($error === UPLOAD_ERR_NO_FILE || empty($name)) {
        return ['uploaded' => false, 'path' => null, 'error' => 'File bukti transfer belum dipilih.'];
    }

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || $size > 5 * 1024 * 1024) {
        return ['uploaded' => false, 'path' => null, 'error' => 'Ukuran file bukti transfer melebihi batas 5 MB.'];
    }

    if ($error !== UPLOAD_ERR_OK) {
        return ['uploaded' => false, 'path' => null, 'error' => 'Gagal mengunggah file (Kode error: ' . $error . ').'];
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allowedExts)) {
        return ['uploaded' => false, 'path' => null, 'error' => 'Format file tidak didukung. Harap upload gambar (JPG, PNG, WebP) atau PDF.'];
    }

    $uploadDir = __DIR__ . '/../uploads/payments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = 'bukti_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($tmpName, $targetPath)) {
        return ['uploaded' => true, 'path' => 'uploads/payments/' . $filename, 'error' => null];
    }

    return ['uploaded' => false, 'path' => null, 'error' => 'Gagal menyimpan file bukti pembayaran ke server.'];
}

/**
 * Generate Link WhatsApp Otomatis untuk Konfirmasi Pembayaran
 */
function getWhatsAppConfirmUrl($group) {
    $adminWa = getSetting('contact_center', '0857-2726-6990');
    // Bersihkan nomor menjadi format internasional 62...
    $cleanPhone = preg_replace('/[^0-9]/', '', $adminWa);
    if (strpos($cleanPhone, '0') === 0) {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    } elseif (strpos($cleanPhone, '62') !== 0) {
        $cleanPhone = '62' . $cleanPhone;
    }

    $groupCode = $group['group_code'] ?? '-';
    $picName   = $group['pic_name'] ?? '-';
    $kategori  = $group['kategori'] ?? '-';
    $asal      = !empty($group['daerah_name']) ? ($group['daerah_name'] . ' (' . ($group['pw_name'] ?? '') . ')') : ($group['pw_name'] ?? $kategori);
    $totalPax  = (int)($group['total_participants'] ?? 1);
    $totalAmount = formatRupiah($group['total_amount'] ?? 0);

    $msg  = "Assalamu'alaikum Panitia Pelaksana RAKERNAS Al Khidmah 2026,\n\n";
    $msg .= "Saya ingin konfirmasi pembayaran iuran akomodasi delegasi:\n";
    $msg .= "• Kode Transaksi : " . $groupCode . "\n";
    $msg .= "• Kategori : " . $kategori . "\n";
    $msg .= "• Asal Kepengurusan : " . $asal . "\n";
    $msg .= "• Ketua / PIC : " . $picName . "\n";
    $msg .= "• Jumlah Delegasi : " . $totalPax . " Orang\n";
    $msg .= "• Total Tagihan : " . $totalAmount . "\n\n";
    $msg .= "Bersama ini saya lampirkan struk / bukti transfer bank. Mohon untuk diverifikasi. Terima kasih.\n";
    $msg .= "Wassalamu'alaikum Wr. Wb.";

    return "https://wa.me/" . $cleanPhone . "?text=" . rawurlencode($msg);
}

/**
 * Auto-migration: Pastikan kolom reg_token ada di tabel wilayah dan terisi token unik
 */
function ensureWilayahRegTokenExists() {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $pdo = getDB();
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `wilayah`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('reg_token', $cols)) {
            $pdo->exec("ALTER TABLE `wilayah` ADD COLUMN `reg_token` VARCHAR(64) DEFAULT NULL UNIQUE AFTER `contact_phone`");
        }

        // Cek jika ada wilayah yang belum memiliki reg_token
        $stmtNull = $pdo->query("SELECT id FROM `wilayah` WHERE `reg_token` IS NULL OR `reg_token` = ''");
        $emptyList = $stmtNull->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($emptyList)) {
            $stmtUp = $pdo->prepare("UPDATE `wilayah` SET `reg_token` = ? WHERE `id` = ?");
            foreach ($emptyList as $wId) {
                $newToken = bin2hex(random_bytes(16)); // 32 hex chars
                $stmtUp->execute([$newToken, $wId]);
            }
        }
    } catch (Exception $e) {
        // Abaikan jika tabel belum siap
    }
}
ensureWilayahRegTokenExists();

/**
 * Ambil data wilayah berdasarkan registration token
 */
function getWilayahByRegToken($token) {
    if (empty($token)) return false;
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("SELECT * FROM `wilayah` WHERE `reg_token` = ? LIMIT 1");
        $stmt->execute([trim($token)]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Generate URL registrasi khusus PW
 */
function getPWRegistrationUrl($token) {
    if (empty($token)) return '#';
    return rtrim(BASE_URL, '/') . '/daftar.php?token=' . urlencode($token);
}

/**
 * Regenerate registration token untuk suatu PW
 */
function regeneratePWRegToken($pwId) {
    $pdo = getDB();
    $newToken = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("UPDATE `wilayah` SET `reg_token` = ? WHERE `id` = ?");
    $stmt->execute([$newToken, (int)$pwId]);
    return $newToken;
}

/**
 * Auto-Migration: Tabel Master Daerah (PD), Transaksi Rombongan, & Kolom Baru Peserta
 */
function ensureRegistrationTablesExist() {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $pdo = getDB();
    try {
        // 1. Tabel Master Daerah (PD)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `daerah` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `pw_id` INT NOT NULL,
              `name` VARCHAR(150) NOT NULL,
              `code` VARCHAR(50) DEFAULT NULL,
              `quota` INT NOT NULL DEFAULT 5,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_daerah_pw` (`pw_id`),
              CONSTRAINT `fk_daerah_pw` FOREIGN KEY (`pw_id`) REFERENCES `wilayah` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Tabel Transaksi Registrasi Rombongan (registration_groups)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `registration_groups` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `group_code` VARCHAR(50) NOT NULL UNIQUE,
              `kategori` ENUM('PP', 'PW', 'PD', 'Tamu Undangan') NOT NULL,
              `pw_id` INT DEFAULT NULL,
              `daerah_id` INT DEFAULT NULL,
              `daerah_name` VARCHAR(150) DEFAULT NULL,
              `pic_name` VARCHAR(150) NOT NULL,
              `pic_phone` VARCHAR(30) NOT NULL,
              `total_participants` INT NOT NULL DEFAULT 1,
              `fee_per_person` DECIMAL(12,2) NOT NULL DEFAULT 0,
              `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
              `payment_status` ENUM('unpaid', 'pending', 'verified') NOT NULL DEFAULT 'unpaid',
              `notes` TEXT DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_rg_code` (`group_code`),
              INDEX `idx_rg_kategori` (`kategori`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2b. Kolom Tambahan pada Tabel Registration Groups (Transportasi, Penjemputan, & Pembayaran)
        try {
            $rgCols = $pdo->query("SHOW COLUMNS FROM `registration_groups`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('pickup_location', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `pickup_location` VARCHAR(255) DEFAULT NULL AFTER `notes`"); } catch (Exception $e) {}
            }
            if (!in_array('pickup_time', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `pickup_time` VARCHAR(100) DEFAULT NULL AFTER `pickup_location`"); } catch (Exception $e) {}
            }
            if (!in_array('pickup_notes', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `pickup_notes` TEXT DEFAULT NULL AFTER `pickup_time`"); } catch (Exception $e) {}
            }
            if (!in_array('payment_proof', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `payment_proof` VARCHAR(255) DEFAULT NULL AFTER `payment_status`"); } catch (Exception $e) {}
            }
            if (!in_array('payment_date', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `payment_date` DATE DEFAULT NULL AFTER `payment_proof`"); } catch (Exception $e) {}
            }
            if (!in_array('payment_bank_sender', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `payment_bank_sender` VARCHAR(150) DEFAULT NULL AFTER `payment_date`"); } catch (Exception $e) {}
            }
            if (!in_array('payment_amount_paid', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `payment_amount_paid` DECIMAL(12,2) DEFAULT NULL AFTER `payment_bank_sender`"); } catch (Exception $e) {}
            }
            if (!in_array('payment_notes', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `payment_notes` TEXT DEFAULT NULL AFTER `payment_amount_paid`"); } catch (Exception $e) {}
            }
            if (!in_array('payment_verified_at', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `payment_verified_at` DATETIME DEFAULT NULL AFTER `payment_notes`"); } catch (Exception $e) {}
            }
            if (!in_array('payment_verified_by', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `payment_verified_by` VARCHAR(100) DEFAULT NULL AFTER `payment_verified_at`"); } catch (Exception $e) {}
            }
            if (!in_array('payment_reject_reason', $rgCols)) {
                try { $pdo->exec("ALTER TABLE `registration_groups` ADD COLUMN `payment_reject_reason` TEXT DEFAULT NULL AFTER `payment_verified_by`"); } catch (Exception $e) {}
            }
            // Pastikan enum payment_status mendukung 'rejected'
            try {
                $pdo->exec("ALTER TABLE `registration_groups` MODIFY COLUMN `payment_status` ENUM('unpaid', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'unpaid'");
            } catch (Exception $e) {}
        } catch (Exception $e) {}

        // 3. Kolom Tambahan pada Tabel Participants
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `participants`")->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('group_id', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `group_id` INT DEFAULT NULL AFTER `registration_code`"); } catch (Exception $e) {}
                try { $pdo->exec("ALTER TABLE `participants` ADD INDEX `idx_part_group` (`group_id`)"); } catch (Exception $e) {}
            }
            if (!in_array('kategori', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `kategori` ENUM('PP', 'PW', 'PD', 'Tamu Undangan') NOT NULL DEFAULT 'PW' AFTER `group_id`"); } catch (Exception $e) {}
            }
            if (!in_array('daerah_id', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `daerah_id` INT DEFAULT NULL AFTER `pw_id`"); } catch (Exception $e) {}
            }
            if (!in_array('pd_name', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `pd_name` VARCHAR(150) DEFAULT NULL AFTER `daerah_id`"); } catch (Exception $e) {}
            }
            if (!in_array('unsur_organisasi', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `unsur_organisasi` VARCHAR(80) DEFAULT 'Al Khidmah' AFTER `gender`"); } catch (Exception $e) {}
            }
            if (!in_array('is_group_leader', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `is_group_leader` TINYINT(1) NOT NULL DEFAULT 0 AFTER `kategori`"); } catch (Exception $e) {}
            }
            if (!in_array('arrival_day', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `arrival_day` VARCHAR(50) DEFAULT NULL AFTER `room_id`"); } catch (Exception $e) {}
            }
            if (!in_array('arrival_time', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `arrival_time` VARCHAR(20) DEFAULT NULL AFTER `arrival_day`"); } catch (Exception $e) {}
            }
            if (!in_array('departure_day', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `departure_day` VARCHAR(50) DEFAULT NULL AFTER `arrival_time`"); } catch (Exception $e) {}
            }
            if (!in_array('departure_time', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `departure_time` VARCHAR(20) DEFAULT NULL AFTER `departure_day`"); } catch (Exception $e) {}
            }
            if (!in_array('transport_mode', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `transport_mode` VARCHAR(50) DEFAULT NULL AFTER `departure_time`"); } catch (Exception $e) {}
            }
            if (!in_array('need_pickup', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `need_pickup` TINYINT(1) NOT NULL DEFAULT 0 AFTER `transport_mode`"); } catch (Exception $e) {}
            }
            if (!in_array('pickup_location', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `pickup_location` VARCHAR(255) DEFAULT NULL AFTER `need_pickup`"); } catch (Exception $e) {}
            }
            if (!in_array('pickup_time', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `pickup_time` VARCHAR(100) DEFAULT NULL AFTER `pickup_location`"); } catch (Exception $e) {}
            }
            if (!in_array('pickup_notes', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `pickup_notes` TEXT DEFAULT NULL AFTER `pickup_time`"); } catch (Exception $e) {}
            }
            if (!in_array('same_as_leader', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `same_as_leader` TINYINT(1) NOT NULL DEFAULT 0 AFTER `need_pickup`"); } catch (Exception $e) {}
            }
            if (!in_array('photo', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `photo` VARCHAR(255) DEFAULT NULL AFTER `same_as_leader`"); } catch (Exception $e) {}
            }
            if (!in_array('photo_file', $cols)) {
                try { $pdo->exec("ALTER TABLE `participants` ADD COLUMN `photo_file` VARCHAR(255) DEFAULT NULL AFTER `photo`"); } catch (Exception $e) {}
            }

            // Pastikan pw_id boleh NULL untuk kategori PP dan Tamu Undangan
            try {
                $pdo->exec("ALTER TABLE `participants` MODIFY COLUMN `pw_id` INT DEFAULT NULL");
            } catch (Exception $e) {
                try {
                    $pdo->exec("ALTER TABLE `participants` DROP FOREIGN KEY `fk_part_pw`");
                    $pdo->exec("ALTER TABLE `participants` MODIFY COLUMN `pw_id` INT DEFAULT NULL");
                    $pdo->exec("ALTER TABLE `participants` ADD CONSTRAINT `fk_part_pw` FOREIGN KEY (`pw_id`) REFERENCES `wilayah` (`id`) ON DELETE SET NULL");
                } catch (Exception $e2) {}
            }
        } catch (Exception $e) {}

        // Pastikan default kontak panitia tersimpan di settings jika belum ada
        try {
            $defaultSettings = [
                'contact_center'        => '0857-2726-6990',
                'panitia_wa'            => '0857-2726-6990',
                'panitia_contact_name'  => 'M Ilyas Balya',
                'arrival_day_options'   => "Kamis, 14 Nov 2026\nJumat, 15 Nov 2026\nSabtu, 16 Nov 2026",
                'departure_day_options' => "Minggu, 17 Nov 2026\nSenin, 18 Nov 2026",
            ];
            $stmtSet = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = IF(`setting_value` IS NULL OR `setting_value` = '' OR `setting_value` = '0812-3456-7890', VALUES(`setting_value`), `setting_value`)");
            foreach ($defaultSettings as $sk => $sv) {
                $stmtSet->execute([$sk, $sv]);
            }
        } catch (Exception $e) {}

        // Sinkronisasi data master resmi 31 PW dan 154 PD
        ensureOfficialMasterData($pdo);
    } catch (Exception $e) {
        // Abaikan jika permission terbatas
    }
}
ensureRegistrationTablesExist();

/**
 * Auto-migration: Pastikan 31 PW Resmi & 154 PD Resmi Terdaftar di Database
 */
function ensureOfficialMasterData($pdo) {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        // Cek flag migrasi
        $stmtFlag = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'master_data_synced_v2' LIMIT 1");
        $stmtFlag->execute();
        $isSynced = $stmtFlag->fetchColumn();
        if ($isSynced === '1') {
            return;
        }

        // 31 Master Wilayah Resmi
        $wilayahList = [
            ['PPA', 'PW Jama\'ah Al Khidmah Papua', 'Papua'],
            ['SULSEL', 'PW Jama\'ah Al Khidmah Sulawesi Selatan', 'Sulawesi Selatan'],
            ['GRNTL', 'PW Jama\'ah Al Khidmah Gorontalo', 'Gorontalo'],
            ['SULTRA', 'PW Jama\'ah Al Khidmah Sulawesi Utara', 'Sulawesi Utara'],
            ['SULTENG', 'PW Jama\'ah Al Khidmah Sulawesi Tengah', 'Sulawesi Tengah'],
            ['MLKU', 'PW Jama\'ah Al Khidmah Maluku', 'Maluku'],
            ['MLKUTGH', 'PW Jama\'ah Al Khidmah Maluku Tengah', 'Maluku Tengah'],
            ['MLKUTR', 'PW Jama\'ah Al Khidmah Maluku Utara', 'Maluku Utara'],
            ['NTT', 'PW Jama\'ah Al Khidmah Nusa Tenggara Timur', 'Nusa Tenggara Timur'],
            ['NTB', 'PW Jama\'ah Al Khidmah Nusa Tenggara Barat', 'Nusa Tenggara Barat'],
            ['BALI', 'PW Jama\'ah Al Khidmah Bali', 'Bali'],
            ['KALSEL', 'PW Jama\'ah Al Khidmah Kalimantan Selatan', 'Kalimantan Selatan'],
            ['KALTENG', 'PW Jama\'ah Al Khidmah Kalimantan Tengah', 'Kalimantan Tengah'],
            ['KALBAR', 'PW Jama\'ah Al Khidmah Kalimantan Barat', 'Kalimantan Barat'],
            ['KALTIM', 'PW Jama\'ah Al Khidmah Kalimantan Timur', 'Kalimantan Timur'],
            ['SUMSEL', 'PW Jama\'ah Al Khidmah Sumatera Selatan', 'Sumatera Selatan'],
            ['SUMTRA', 'PW Jama\'ah Al Khidmah Sumatera Utara', 'Sumatera Utara'],
            ['SUMBAR', 'PW Jama\'ah Al Khidmah Sumatera Barat', 'Sumatera Barat'],
            ['JMB', 'PW Jama\'ah Al Khidmah Jambi', 'Jambi'],
            ['BNGKL', 'PW Jama\'ah Al Khidmah Bengkulu', 'Bengkulu'],
            ['LAMP', 'PW Jama\'ah Al Khidmah Lampung', 'Lampung'],
            ['KEPRI', 'PW Jama\'ah Al Khidmah Kepulauan Riau', 'Kepulauan Riau'],
            ['DKI', 'PW Jama\'ah Al Khidmah DKI Jakarta', 'DKI Jakarta'],
            ['JABAR', 'PW Jama\'ah Al Khidmah Jawa Barat', 'Jawa Barat'],
            ['BNTN', 'PW Jama\'ah Al Khidmah Banten', 'Banten'],
            ['DIY', 'PW Jama\'ah Al Khidmah D.I. Yogyakarta', 'D.I. Yogyakarta'],
            ['JATENG', 'PW Jama\'ah Al Khidmah Jawa Tengah', 'Jawa Tengah'],
            ['JATIM', 'PW Jama\'ah Al Khidmah Jawa Timur', 'Jawa Timur'],
            ['SING', 'PDI Jama\'ah Al Khidmah Singapura', 'Singapura'],
            ['MLY', 'PDI Jama\'ah Al Khidmah Malaysia', 'Malaysia'],
            ['THAI', 'PDI Jama\'ah Al Khidmah Thailand', 'Thailand']
        ];

        // 154 Master Daerah (PD) Resmi
        $daerahMap = [
            'PPA' => ['PD Keerom', 'PD Merauke', 'PD Jayapura', 'PD Timika'],
            'SULSEL' => ['PD Makassar', 'PD Wajo', 'PD Luwu Timur', 'PD Luwu Utara', 'PD Gowa', 'PD Maros', 'PD Soppeng', 'PD Bone'],
            'GRNTL' => ['PD Gorontalo'],
            'SULTRA' => ['PD Manado', 'PD Minahasa Utara', 'PD Bitung', 'PD Bolaang Mongondow', 'PD Tomohon'],
            'SULTENG' => ['PD Palu'],
            'MLKU' => ['PD Kota Ambon'],
            'MLKUTGH' => ['PD Masohi'],
            'MLKUTR' => ['PD Ternate'],
            'NTT' => ['PD Timor Tengah Utara', 'PD Bajawa', 'PD Kupang', 'PD Manggarai'],
            'NTB' => ['PD Lombok Barat', 'PD Lombok Tengah', 'PD Mataram'],
            'BALI' => ['PD Denpasar', 'PD Gianyar', 'PD Tabanan', 'PD Badung'],
            'KALSEL' => ['PD Banjarbaru', 'PD Muara Teweh', 'PD Banjarmasin'],
            'KALTENG' => ['PD Katingan', 'PD Pulang Pisau', 'PD Kapuas', 'PD Lamandau', 'PD Sukamara', 'PD Palangkaraya'],
            'KALBAR' => ['PD Pontianak', 'PD Gabang Landak'],
            'KALTIM' => ['PD Samarinda', 'PD Pangkalan Bun', 'PD Kutai Kartanegara'],
            'SUMSEL' => ['PD Musirawas Utara', 'PD Musirawas', 'PD Palembang', 'PD OKU Timur', 'PD OKI', 'PD Kerinci', 'PD OKU Selatan', 'PD OKU Induk'],
            'SUMTRA' => ['PD Medan'],
            'SUMBAR' => ['PD Padang'],
            'JMB' => ['PD Sarolangun', 'PD Tanjung Jabung Barat', 'PD Muaro Jambi', 'PD Kota Jambi'],
            'BNGKL' => ['PD Bengkulu Kota', 'PD Bengkulu Utara'],
            'LAMP' => ['PD Mesuji', 'PD Tulang Bawang Barat', 'PD Metro', 'PD Pesawaran', 'PD Pesisir Barat', 'PD Lampung Selatan', 'PD Kota Bandar Lampung', 'PD Tulang Bawang'],
            'KEPRI' => ['PD Batam', 'PD Bintan', 'PD Karimun', 'PD Anambas', 'PD Tanjungpinang', 'PD Natuna'],
            'DKI' => ['PD Jakarta Pusat', 'PD Jakarta Barat', 'PD Jakarta Timur', 'PD Jakarta Utara', 'PD Jakarta Selatan', 'PD Kepulauan Seribu'],
            'JABAR' => ['PD Bogor', 'PD Kab. Cirebon', 'PD Kota Cirebon', 'PD Kota Bandung', 'PD Bandung Barat', 'PD Garut', 'PD Karawang', 'PD Ciamis', 'PD Cimahi', 'PD Kuningan', 'PD Majalengka'],
            'BNTN' => ['PD Kab. Serang', 'PD Tangerang', 'PD Tangerang Selatan', 'PD Serang Kota', 'PD Cilegon'],
            'DIY' => ['PD Bantul', 'PD Sleman', 'PD Kulon Progo', 'PD Yogyakarta', 'PD Gunungkidul'],
            'JATENG' => ['PD Batang', 'PD Blora', 'PD Boyolali', 'PD Brebes', 'PD Demak', 'PD Grobogan', 'PD Jepara', 'PD Karanganyar', 'PD Kendal', 'PD Kudus', 'PD Pati', 'PD Pekalongan', 'PD Pemalang', 'PD Kab. Semarang', 'PD Kota Semarang', 'PD Salatiga', 'PD Solo', 'PD Sragen', 'PD Tegal', 'PD Banjarnegara', 'PD Cilacap', 'PD Banyumas', 'PD Kebumen', 'PD Temanggung', 'PD Klaten', 'PD Wonosobo', 'PD Magelang', 'PD Purworejo', 'PD Purbalingga', 'PD Wonogiri'],
            'JATIM' => ['PD Kab. Malang', 'PD Kota Malang', 'PD Kota Batu', 'PD Blitar', 'PD Kediri', 'PD Tulungagung', 'PD Trenggalek', 'PD Pacitan', 'PD Ponorogo', 'PD Nganjuk', 'PD Ngawi', 'PD Madiun', 'PD Magetan', 'PD Bojonegoro', 'PD Tuban', 'PD Lamongan', 'PD Gresik', 'PD Jombang', 'PD Mojokerto', 'PD Kota Surabaya', 'PD Sidoarjo', 'PD Bangkalan', 'PD Pamekasan', 'PD Sumenep', 'PD Probolinggo', 'PD Pasuruan', 'PD Sampang', 'PD Jember', 'PD Lumajang', 'PD Banyuwangi'],
            'SING' => ['PDI Singapura'],
            'MLY' => ['PDI Kuala Lumpur', 'PDI Johor', 'PDI Melaka', 'PDI Sepang', 'PDI Tampin Negeri 9'],
            'THAI' => ['PDI Yala Thailand Selatan']
        ];

        // Simpan / update wilayah
        $pwIdMap = [];
        $stmtFindW = $pdo->prepare("SELECT id FROM wilayah WHERE code = ? OR code = ? OR province = ? LIMIT 1");
        $stmtInsW  = $pdo->prepare("INSERT INTO wilayah (code, name, province, quota_utusan, quota_peninjau, reg_token) VALUES (?, ?, ?, 10, 5, ?)");
        $stmtUpdW  = $pdo->prepare("UPDATE wilayah SET name = ?, province = ? WHERE id = ?");

        foreach ($wilayahList as $w) {
            $code = $w[0];
            $name = $w[1];
            $prov = $w[2];

            $stmtFindW->execute([$code, 'PW-' . $code, $prov]);
            $existingId = $stmtFindW->fetchColumn();

            if ($existingId) {
                $pwIdMap[$code] = (int)$existingId;
                $stmtUpdW->execute([$name, $prov, $existingId]);
            } else {
                $token = bin2hex(random_bytes(16));
                $stmtInsW->execute([$code, $name, $prov, $token]);
                $newId = (int)$pdo->lastInsertId();
                $pwIdMap[$code] = $newId;

                // Buat user login PW default jika belum ada
                $uName = 'pw_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $code));
                $hash  = password_hash('pw123', PASSWORD_DEFAULT);
                $stmtU = $pdo->prepare("INSERT IGNORE INTO users (username, password, full_name, role, pw_id) VALUES (?, ?, ?, 'admin_pw', ?)");
                $stmtU->execute([$uName, $hash, 'Admin ' . $name, $newId]);
            }
        }

        // Simpan daerah jika belum ada
        $stmtFindD = $pdo->prepare("SELECT id FROM daerah WHERE pw_id = ? AND name = ? LIMIT 1");
        $stmtInsD  = $pdo->prepare("INSERT INTO daerah (pw_id, name, quota) VALUES (?, ?, 5)");

        foreach ($daerahMap as $code => $dList) {
            $wId = $pwIdMap[$code] ?? 0;
            if ($wId <= 0) continue;

            foreach ($dList as $dName) {
                $stmtFindD->execute([$wId, $dName]);
                if (!$stmtFindD->fetchColumn()) {
                    $stmtInsD->execute([$wId, $dName]);
                }
            }
        }

        // Tandai flag sudah disinkronkan
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('master_data_synced_v2', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");

    } catch (Exception $e) {
        // Abaikan jika table belum siap
    }
}

/**
 * Ambil daftar daerah (PD) berdasarkan PW ID
 */
function getDaerahListByPW($pw_id) {
    ensureRegistrationTablesExist();
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("SELECT * FROM `daerah` WHERE `pw_id` = ? ORDER BY `name` ASC");
        $stmt->execute([(int)$pw_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Hitung jumlah peserta yang sudah terdaftar per PD
 */
function countRegisteredByPD($daerah_name, $pw_id = null) {
    if (empty($daerah_name)) return 0;
    $pdo = getDB();
    try {
        if ($pw_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `participants` WHERE `pd_name` = ? AND `pw_id` = ?");
            $stmt->execute([trim($daerah_name), (int)$pw_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `participants` WHERE `pd_name` = ?");
            $stmt->execute([trim($daerah_name)]);
        }
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Generate Kode Unik Transaksi Registrasi Rombongan
 * Contoh: REG-20261116-A8F2
 */
function generateGroupCode() {
    $datePart = date('Ymd');
    $randPart = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return 'REG-' . $datePart . '-' . $randPart;
}

