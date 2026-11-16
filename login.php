<?php
/**
 * Halaman Login Sistem
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Jika sudah login, redirect
if (isLoggedIn()) {
    $user = currentUser();
    if ($user['role'] === 'superadmin') {
        header("Location: " . BASE_URL . "admin/index.php");
    } elseif ($user['role'] === 'admin_pw') {
        header("Location: " . BASE_URL . "pw/index.php");
    } else {
        header("Location: " . BASE_URL . "admin/scan.php");
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Mohon masukkan username dan password.";
    } else {
        $login = attemptLogin($username, $password);
        if ($login['success']) {
            if ($login['role'] === 'superadmin') {
                header("Location: " . BASE_URL . "admin/index.php");
            } elseif ($login['role'] === 'admin_pw') {
                header("Location: " . BASE_URL . "pw/index.php");
            } else {
                header("Location: " . BASE_URL . "admin/scan.php");
            }
            exit;
        } else {
            $error = $login['message'];
        }
    }
}

$page_title = "Login Pengurus & Panitia";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto my-8">
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xl overflow-hidden">
        <!-- Header Card -->
        <div class="bg-gradient-to-br from-alkhidmah-950 via-alkhidmah-900 to-alkhidmah-800 p-8 text-center text-white">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center text-gold-400 mb-4 shadow-inner">
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                </svg>
            </div>
            <h2 class="text-xl font-extrabold tracking-tight">Login Portal Panitia & PW</h2>
            <p class="text-emerald-200 text-xs mt-1">SI-RAKERNAS Perkumpulan Jama'ah Al Khidmah</p>
        </div>

        <!-- Form Body -->
        <div class="p-8">
            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <span><?= clean($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <input type="text" name="username" required autofocus placeholder="Masukkan username Anda" value="<?= clean($_POST['username'] ?? '') ?>" class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-alkhidmah-600 focus:border-alkhidmah-600 text-sm text-slate-800 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <input type="password" name="password" required placeholder="••••••••" class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-alkhidmah-600 focus:border-alkhidmah-600 text-sm text-slate-800 transition">
                    </div>
                </div>

                <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-alkhidmah-700 to-alkhidmah-900 text-white font-bold text-sm hover:from-alkhidmah-800 hover:to-alkhidmah-950 transition shadow-lg shadow-emerald-900/20 flex items-center justify-center gap-2">
                    <span>Masuk ke Sistem</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>

            <!-- Petunjuk Akun Demo / Default -->
            <div class="mt-8 pt-6 border-t border-slate-100 bg-slate-50 -mx-8 -mb-8 p-6 rounded-b-3xl">
                <div class="text-xs font-semibold text-slate-500 mb-2 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Informasi Akun Default (Database Seed):
                </div>
                <div class="text-[11px] text-slate-600 space-y-1 font-mono">
                    <div>• <strong>Panitia Pusat:</strong> admin / admin123</div>
                    <div>• <strong>Admin PW Jatim:</strong> pw_jatim / pw123</div>
                    <div>• <strong>Admin PW Jateng:</strong> pw_jateng / pw123</div>
                    <div>• <strong>Petugas Scan:</strong> petugas / petugas123</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
