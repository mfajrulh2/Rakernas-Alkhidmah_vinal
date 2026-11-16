<?php
/**
 * Konfigurasi Database & Pengaturan Aplikasi
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

// Hindari direct access jika diperlukan
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Pengaturan Database (Ubah sesuai database hosting cPanel / lokal Anda)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_rakernas_alkhidmah');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// Informasi Aplikasi
define('APP_NAME', 'SI-RAKERNAS AL KHIDMAH');
define('APP_TITLE', 'Sistem Informasi & Manajemen RAKERNAS Jama\'ah Al Khidmah');
define('APP_YEAR', '2026');
define('APP_THEME_COLOR', 'emerald'); // Emerald Green khas Al Khidmah

// Base URL (otomatis mendeteksi folder aplikasi)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
// Ambil root folder aplikasi jika berada di subfolder admin/pw/api
$app_root = preg_replace('/(\/(admin|pw|api|includes|assets))$/', '', $script_dir);
$base_url = rtrim($protocol . $host . $app_root, '/') . '/';
define('BASE_URL', $base_url);

// Inisialisasi PDO Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET . ";port=" . DB_PORT;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Tampilan ramah jika database belum dikonfigurasi
            die("<div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:20px; border:1px solid #ef4444; border-radius:8px; background:#fef2f2; color:#991b1b;'>
                    <h3 style='margin-top:0;'>Gagal Terhubung ke Database MySQL</h3>
                    <p>Mohon periksa konfigurasi pada file <code>config/database.php</code> dan pastikan database <strong>" . DB_NAME . "</strong> sudah dibuat dan diimport dari <code>database/schema.sql</code>.</p>
                    <small>Detail error: " . htmlspecialchars($e->getMessage()) . "</small>
                 </div>");
        }
    }
    return $pdo;
}

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
