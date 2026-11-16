<?php
/**
 * Scanner QR Code Presensi Real-Time
 * RAKERNAS PERKUMPULAN JAMA'AH AL KHIDMAH
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['superadmin', 'petugas_scan', 'admin_pw']);

$page_title = "Scanner Presensi & Check-in Lokasi";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- HTML5 QR Code CDN -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<div class="max-w-4xl mx-auto mb-16 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider text-emerald-800 bg-emerald-100 px-3 py-1 rounded-full mb-1">
                <span class="w-2 h-2 rounded-full bg-emerald-600 animate-ping"></span>
                Meja Registrasi & Front Desk
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Presensi Kedatangan Delegasi</h1>
        </div>
        <div class="flex items-center gap-2">
            <span id="audio-status" class="text-[11px] font-semibold text-slate-500 bg-white px-3 py-1.5 rounded-xl border border-slate-200 flex items-center gap-1">
                🔊 Audio Aktif
            </span>
            <a href="<?= BASE_URL ?>admin/participants.php" class="px-4 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                Daftar Hadir &rarr;
            </a>
        </div>
    </div>

    <!-- Main Scanner Grid -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        
        <!-- Left: Camera Box & Manual Form (7 cols) -->
        <div class="md:col-span-7 space-y-4">
            
            <!-- Camera Scanner Container -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <h2 class="text-sm font-bold text-slate-900">Kamera Pemindai QR</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="btn-camera-toggle" class="text-xs px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition">
                            Matikan Kamera
                        </button>
                    </div>
                </div>

                <!-- Video Viewport -->
                <div class="relative bg-slate-950 rounded-2xl overflow-hidden aspect-video flex items-center justify-center border border-slate-800">
                    <div id="qr-reader" class="w-full h-full"></div>
                    <div id="camera-loading" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900 text-white text-xs gap-2">
                        <svg class="w-8 h-8 text-emerald-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Menghubungkan ke kamera...</span>
                    </div>
                </div>

                <div class="text-[11px] text-slate-400 text-center mt-3">
                    Arahkan kode QR pada E-ID Card atau layar HP peserta ke dalam kotak kamera.
                </div>
            </div>

            <!-- Manual Input / Barcode Scanner Gun Box -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-4 sm:p-6">
                <div class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    Input Manual / Barcode Scanner Gun
                </div>
                <form id="form-manual" onsubmit="event.preventDefault(); processCheckin(document.getElementById('input-manual').value.trim());" class="flex gap-2">
                    <input type="text" id="input-manual" placeholder="Ketik kode (RKN-xxx) atau scan barcode gun..." class="flex-grow px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-alkhidmah-600 focus:outline-none">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-alkhidmah-800 text-white text-xs font-bold hover:bg-alkhidmah-900 transition shadow-sm">
                        Check-in
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Real-Time Scan Result & Verification Box (5 cols) -->
        <div class="md:col-span-5 space-y-4">
            
            <!-- Result Card -->
            <div id="result-box" class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 min-h-[320px] flex flex-col justify-between transition-all">
                
                <div id="result-placeholder" class="text-center py-12 text-slate-400">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 flex items-center justify-center text-slate-300 mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    </div>
                    <div class="text-xs font-bold text-slate-500">Menunggu Pemindaian QR...</div>
                    <p class="text-[11px] text-slate-400 mt-1 max-w-xs mx-auto">Hasil pemindaian dan verifikasi kamar akan tampil di panel ini secara instan.</p>
                </div>

                <!-- Verified Result Content (Hidden by default) -->
                <div id="result-content" class="hidden space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span id="badge-status" class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            Presensi Berhasil
                        </span>
                        <span id="res-code" class="font-mono text-xs font-bold text-slate-500"></span>
                    </div>

                    <div>
                        <h3 id="res-name" class="text-xl font-extrabold text-slate-900 leading-tight"></h3>
                        <div id="res-title" class="text-xs font-semibold text-emerald-800 mt-0.5"></div>
                        <div id="res-pw" class="text-xs text-slate-500"></div>
                    </div>

                    <!-- Room & Commission Box -->
                    <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-100 text-xs space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Penginapan:</span>
                            <span id="res-room" class="font-bold text-indigo-700"></span>
                        </div>
                        <div class="flex justify-between items-center border-t border-slate-200/60 pt-2">
                            <span class="text-slate-500">Komisi Sidang:</span>
                            <span id="res-komisi" class="font-bold text-slate-800"></span>
                        </div>
                    </div>

                    <!-- Kit Rakernas Checkbox -->
                    <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200/80 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <div>
                                <div class="text-xs font-bold text-amber-900">Kit Rakernas / Goodie Bag</div>
                                <div class="text-[10px] text-amber-700" id="res-kit-text">Belum Diserahkan</div>
                            </div>
                        </div>
                        <button type="button" id="btn-toggle-kit" onclick="toggleKitDelivered()" class="px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs transition">
                            Tandai Diterima
                        </button>
                    </div>

                    <button type="button" onclick="resetResultBox()" class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                        Scan Peserta Berikutnya &rarr;
                    </button>
                </div>

            </div>

            <!-- Recent Scans History -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3 text-xs font-bold text-slate-700 uppercase tracking-wider">
                    <span>Aktivitas Scan Sesi Ini</span>
                    <span id="scan-count" class="font-mono text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">0 Peserta</span>
                </div>
                <div id="recent-scan-list" class="space-y-2 max-h-48 overflow-y-auto text-xs divide-y divide-slate-100">
                    <div class="text-center py-4 text-slate-400 italic">Riwayat scan akan muncul di sini.</div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
// Audio synthesizer via HTML5 Web Audio API
const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
function playBeep(type = 'success') {
    if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain);
    gain.connect(audioCtx.destination);

    if (type === 'success') {
        // Cheerful high double beep
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.setValueAtTime(880.00, audioCtx.currentTime + 0.1); // A5
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);
        osc.start(audioCtx.currentTime);
        osc.stop(audioCtx.currentTime + 0.25);
    } else {
        // Low error buzz
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(180, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
        osc.start(audioCtx.currentTime);
        osc.stop(audioCtx.currentTime + 0.35);
    }
}

// Variables & Elements
let html5QrCode = null;
let isCameraRunning = false;
let currentParticipant = null;
let scannedHistory = [];

const resultPlaceholder = document.getElementById('result-placeholder');
const resultContent = document.getElementById('result-content');
const cameraLoading = document.getElementById('camera-loading');
const inputManual = document.getElementById('input-manual');

// Start Camera Scanner
function startCamera() {
    html5QrCode = new Html5Qrcode("qr-reader");
    const config = { fps: 10, qrbox: { width: 220, height: 220 } };

    html5QrCode.start(
        { facingMode: "environment" },
        config,
        (decodedText) => {
            // Success QR Scan
            processCheckin(decodedText);
        },
        (error) => {
            // Scanning in progress
        }
    ).then(() => {
        isCameraRunning = true;
        cameraLoading.classList.add('hidden');
    }).catch(err => {
        cameraLoading.innerHTML = `<span class="text-rose-400 p-4 text-center">Akses kamera gagal atau tidak diizinkan.<br><small>Gunakan input manual atau periksa izin browser.</small></span>`;
    });
}

// Toggle Camera
document.getElementById('btn-camera-toggle').addEventListener('click', function() {
    if (isCameraRunning) {
        html5QrCode.stop().then(() => {
            isCameraRunning = false;
            this.innerText = "Aktifkan Kamera";
        });
    } else {
        startCamera();
        this.innerText = "Matikan Kamera";
    }
});

// Process Checkin API Call
let isProcessing = false;
function processCheckin(code) {
    if (!code || isProcessing) return;
    isProcessing = true;

    // Bersihkan code dari URL jika format QR adalah link
    let cleanCode = code;
    if (code.includes('code=')) {
        cleanCode = code.split('code=')[1].split('&')[0];
    }

    fetch('<?= BASE_URL ?>api/checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: cleanCode })
    })
    .then(r => r.json())
    .then(data => {
        isProcessing = false;
        if (data.success) {
            playBeep('success');
            showResult(data.participant, data.is_already_attended);
            addToHistory(data.participant);
            inputManual.value = '';
        } else {
            playBeep('error');
            alert(data.message || 'Data tidak ditemukan.');
        }
    })
    .catch(err => {
        isProcessing = false;
        playBeep('error');
        alert('Terjadi kesalahan jaringan.');
    });
}

// Display Result Card
function showResult(p, alreadyAttended) {
    currentParticipant = p;
    resultPlaceholder.classList.add('hidden');
    resultContent.classList.remove('hidden');

    document.getElementById('res-code').innerText = p.registration_code;
    document.getElementById('res-name').innerText = p.full_name;
    document.getElementById('res-title').innerText = (p.jabatan || 'Pengurus') + ' • ' + (p.pd_name || p.pw_name);
    document.getElementById('res-pw').innerText = p.pw_name;
    document.getElementById('res-room').innerText = p.room;
    document.getElementById('res-komisi').innerText = p.komisi;

    const badge = document.getElementById('badge-status');
    if (alreadyAttended) {
        badge.className = "px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 flex items-center gap-1.5";
        badge.innerHTML = "⚠️ Sudah Hadir Sebelumnya";
    } else {
        badge.className = "px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 flex items-center gap-1.5";
        badge.innerHTML = "✓ Presensi Berhasil";
    }

    updateKitUI(p.kit_received);
}

// Update Kit UI
function updateKitUI(kitVal) {
    const kitText = document.getElementById('res-kit-text');
    const kitBtn = document.getElementById('btn-toggle-kit');
    if (kitVal == 1) {
        kitText.innerText = "Sudah Diterima";
        kitBtn.className = "px-3 py-1.5 rounded-xl bg-emerald-600 text-white font-bold text-xs";
        kitBtn.innerText = "✓ Sudah Diserahkan";
    } else {
        kitText.innerText = "Belum Diserahkan";
        kitBtn.className = "px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs";
        kitBtn.innerText = "Tandai Diterima";
    }
}

// Toggle Kit Delivered
function toggleKitDelivered() {
    if (!currentParticipant) return;
    fetch('<?= BASE_URL ?>api/checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            code: currentParticipant.registration_code,
            kit_received: 1
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            playBeep('success');
            currentParticipant.kit_received = 1;
            updateKitUI(1);
        }
    });
}

// Reset result view
function resetResultBox() {
    resultContent.classList.add('hidden');
    resultPlaceholder.classList.remove('hidden');
    currentParticipant = null;
    inputManual.focus();
}

// Add to History UI
function addToHistory(p) {
    scannedHistory.unshift(p);
    document.getElementById('scan-count').innerText = scannedHistory.length + ' Peserta';

    const list = document.getElementById('recent-scan-list');
    let html = '';
    scannedHistory.slice(0, 10).forEach(item => {
        const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        html += `
        <div class="py-2 flex items-center justify-between">
            <div>
                <div class="font-bold text-slate-800">${item.full_name}</div>
                <div class="text-[10px] text-slate-400 font-mono">${item.registration_code} • ${item.pw_name}</div>
            </div>
            <span class="font-mono text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">${time}</span>
        </div>`;
    });
    list.innerHTML = html;
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
    startCamera();
    inputManual.focus();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
