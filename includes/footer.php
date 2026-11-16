        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 mt-auto text-slate-500 text-xs no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-center md:text-left">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-alkhidmah-800 flex items-center justify-center text-gold-400 font-bold text-xs">
                        AK
                    </div>
                    <div>
                        <div class="font-bold text-slate-800 text-sm"><?= clean(getSetting('event_name', 'RAKERNAS PERKUMPULAN JAMA\'AH AL KHIDMAH')) ?></div>
                        <div class="text-[11px] text-slate-400 font-serif italic">"Meneguhkan Khidmah, Menguatkan Ukhuwah, Menuju Kemandirian Jam'iyyah"</div>
                    </div>
                </div>
                <div class="text-right">
                    <p>&copy; <?= date('Y') ?> Panitia Pelaksana RAKERNAS Jama'ah Al Khidmah.</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Sistem Manajemen Delegasi & Presensi Berbasis Web</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Interactive Scripts -->
    <script>
        // Mobile menu toggle
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileBtn && mobileMenu) {
            mobileBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>
