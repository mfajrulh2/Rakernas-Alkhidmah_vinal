<?php
/**
 * Halaman Publik Jadwal / Rundown & Materi Persidangan
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDB();

// Ambil jadwal
$schedules = [];
try {
    $stmt = $pdo->query("SELECT * FROM schedules ORDER BY session_date ASC, start_time ASC");
    $all = $stmt->fetchAll();
    foreach ($all as $s) {
        $schedules[$s['session_date']][] = $s;
    }
} catch (Exception $e) {}

// Ambil materi
$materials = [];
try {
    $stmtM = $pdo->query("SELECT * FROM materials ORDER BY id DESC");
    $materials = $stmtM->fetchAll();
} catch (Exception $e) {}

$page_title = "Jadwal & Materi Sidang Rakernas";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto mb-16">
    <!-- Header -->
    <div class="text-center max-w-xl mx-auto mb-10">
        <span class="text-xs font-bold uppercase tracking-widest text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
            Agenda Resmi Kegiatan
        </span>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mt-2">Jadwal & Rundown Acara</h1>
        <p class="text-sm text-slate-600 mt-1">
            <?= clean(getSetting('event_name', 'RAKERNAS JAMA\'AH AL KHIDMAH')) ?>
        </p>
    </div>

    <!-- Timeline Jadwal -->
    <div class="space-y-8 mb-12">
        <?php if (empty($schedules)): ?>
            <div class="bg-white p-8 rounded-3xl border border-slate-200 text-center text-slate-500">
                Belum ada jadwal yang dimasukkan oleh panitia.
            </div>
        <?php else: ?>
            <?php foreach ($schedules as $date => $items): ?>
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden">
                    <!-- Date Header -->
                    <div class="bg-gradient-to-r from-alkhidmah-900 to-alkhidmah-800 text-white px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <h2 class="text-base font-bold"><?= formatTanggalIndo($date) ?></h2>
                        </div>
                        <span class="text-xs bg-alkhidmah-800 text-emerald-200 px-3 py-1 rounded-full border border-alkhidmah-600">
                            <?= count($items) ?> Agenda
                        </span>
                    </div>

                    <!-- Items list -->
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($items as $item): ?>
                            <div class="p-6 flex flex-col sm:flex-row sm:items-start justify-between gap-4 hover:bg-slate-50/60 transition">
                                <div class="sm:w-36 flex-shrink-0">
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-50 text-emerald-800 text-xs font-mono font-bold">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <?= substr($item['start_time'], 0, 5) ?> - <?= substr($item['end_time'], 0, 5) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-1">Waktu Indonesia Barat</div>
                                </div>

                                <div class="flex-grow">
                                    <h3 class="text-base font-bold text-slate-900"><?= clean($item['title']) ?></h3>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="text-xs text-slate-600 mt-1 leading-relaxed"><?= nl2br(clean($item['description'])) ?></p>
                                    <?php endif; ?>

                                    <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-slate-500">
                                        <span class="flex items-center gap-1.5 text-alkhidmah-700 font-semibold">
                                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <?= clean($item['location']) ?>
                                        </span>
                                        <?php if (!empty($item['speaker'])): ?>
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                Pimpinan / Narasumber: <strong><?= clean($item['speaker']) ?></strong>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Dokumen & Materi Sidang -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Materi & Berkas Persidangan</h2>
                <p class="text-xs text-slate-500 mt-0.5">Draf tata tertib, materi komisi, dan keputusan resmi</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-gold-50 text-gold-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
        </div>

        <?php if (empty($materials)): ?>
            <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200 text-center text-xs text-slate-500">
                Belum ada dokumen materi yang diunggah. Materi persidangan akan dibagikan menjelang sidang komisi.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach ($materials as $m): ?>
                    <a href="<?= clean($m['file_url']) ?>" target="_blank" class="p-4 rounded-2xl border border-slate-200 hover:border-alkhidmah-600 hover:shadow-sm transition flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xs">
                                PDF
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800 group-hover:text-alkhidmah-700 transition"><?= clean($m['title']) ?></div>
                                <div class="text-[11px] text-slate-500"><?= clean($m['category']) ?></div>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-slate-400 group-hover:text-alkhidmah-700 group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
