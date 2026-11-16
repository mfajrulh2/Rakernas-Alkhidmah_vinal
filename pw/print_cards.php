<?php
/**
 * Cetak Massal ID Card Delegasi PW Terkait
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin_pw');
$user   = currentUser();
$pw_id  = $user['pw_id'];

// Cek status pembayaran — harus verified dulu
$payInfo = getPWPaymentInfo($pw_id);
if (!$payInfo['is_verified']) {
    setFlash('warning', '🔒 Cetak ID Card belum dapat diakses. Pembayaran iuran delegasi harus diverifikasi oleh Panitia Pusat terlebih dahulu. Silakan konfirmasi pembayaran di halaman Iuran & Pembayaran.');
    header("Location: " . BASE_URL . "pw/payment.php");
    exit;
}

header("Location: " . BASE_URL . "admin/print_cards.php?pw_id=" . $pw_id);
exit;

