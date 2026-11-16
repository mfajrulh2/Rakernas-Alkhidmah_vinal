<?php
/**
 * Logout Handler
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlash('info', 'Anda telah berhasil logout dari sistem.');
header("Location: " . BASE_URL . "login.php");
exit;
