# 🌟 SI-RAKERNAS AL KHIDMAH
### Sistem Informasi Manajemen RAKERNAS Perkumpulan Jama'ah Al Khidmah

Aplikasi web-based modern, cepat, dan responsif yang dirancang khusus untuk memfasilitasi kebutuhan **Rapat Kerja Nasional (RAKERNAS) Perkumpulan Jama'ah Al Khidmah**.

---

## 🚀 Fitur Unggulan

1. **Multi-Tier Delegasi & Registrasi Online:**
   - Formulir pendaftaran publik multi-kategori: Pengurus Pusat (PP), Pengurus Wilayah (PW), Pengurus Daerah (PD), dan Tamu Undangan Khusus.
   - Pendaftaran rombongan sekaligus (kolektif multi-pax) dengan perhitungan total iuran otomatis.
   - Input logistik kedatangan & penjemputan (Stasiun / Bandara / Kendaraan Pribadi).

2. **Konfirmasi Pembayaran Online & WhatsApp Terintegrasi:**
   - Konfirmasi mandiri melalui portal publik dengan upload bukti transfer bank (JPG, PNG, PDF).
   - Fitur 1-klik kirim pesan konfirmasi WhatsApp terformat langsung ke nomor Bendahara Panitia.
   - Panel Verifikasi Pembayaran Admin dengan status: *Belum Bayar*, *Menunggu Verifikasi*, *Lunas*, atau *Ditolak* (dengan alasan penolakan).
   - Cetak Bukti Pendaftaran Resmi (Invoice & Kuitansi Lunas) dengan stempel digital.

3. **Daftar Peserta Publik Terdaftar:**
   - Halaman publik transparan dan aman untuk memantau peserta yang telah terdaftar.
   - Filter interaktif berdasarkan Pengurus Wilayah (PW), Pengurus Daerah (PD), dan Status Iuran (Semua, Lunas, Menunggu Pembayaran).
   - Pencarian instan berdasarkan Nama atau Jabatan tanpa mengekspos data pribadi sensitif (No HP & NIK disembunyikan).

4. **Multi-Role & Akses Pengguna Terintegrasi:**
   - **Super Admin (Panitia Pusat):** Kontrol penuh atas seluruh data nasional, validasi pembayaran, penataan kamar penginapan, kontrol kuota PW/PD, jadwal, dan rekapitulasi.
   - **Admin Pengurus Wilayah (PW):** Login mandiri khusus setiap PW untuk mendaftarkan utusan & peninjau daerah secara kolektif dengan kuota otomatis.
   - **Petugas Meja Registrasi (Front Desk):** Akses pemindai presensi & pembagian kit.

5. **E-ID Card & QR Code Generator Otomatis:**
   - Setiap peserta terverifikasi mendapatkan E-ID Card berdesain elegan khas Al Khidmah lengkap dengan QR Code unik, komisi sidang, dan kamar wisma.
   - Cetak mandiri peserta atau **Cetak Massal (Batch Print)** dalam format layout lembar A4 (4 kartu per lembar).

6. **Live QR Scanner Presensi Kamera (Fast Check-in):**
   - Pemindaian barcode kamera langsung di browser HP / laptop panitia di lokasi acara.
   - Audio chime synthesizer untuk validasi instan.
   - Checklist penyerahan **Kit Rakernas / Goodie Bag**.
   - Mendukung alat Barcode Scanner Gun USB / keyboard scan cepat.

7. **Manajemen Kamar & Akomodasi Penginapan:**
   - Pemisahan wisma/kamar Ikhwan & Akhwat, kapasitas tempat tidur (bed), dan daftar nama penghuni per kamar.

8. **Ekspor & Impor Data Excel (CSV):**
   - Download rekap data peserta ke file Excel dengan encoding UTF-8 BOM.
   - Import massal peserta dari spreadsheet Excel/CSV.

---

## 📋 Struktur Folder Aplikasi

```
APP RAKERNAS/
├── admin/                    # Panel Super Admin & Panitia Pusat
│   ├── index.php             # Dashboard statistik
│   ├── participants.php      # Master data delegasi & aksi
│   ├── payments.php          # Modul validasi pembayaran iuran
│   ├── pw_accounts.php       # Manajemen akun login & kuota PW
│   ├── daerah.php            # Master data Pengurus Daerah (PD)
│   ├── rooms.php             # Manajemen kamar wisma penginapan
│   ├── scan.php              # Live camera QR scanner check-in
│   ├── print_cards.php       # Cetak massal ID card (Grid A4)
│   ├── export.php            # Download rekap CSV/Excel
│   ├── import.php            # Upload massal CSV/Excel
│   ├── schedules.php         # Manajemen rundown agenda
│   └── settings.php          # Pengaturan rekening, tema & kontak
├── api/
│   └── checkin.php           # API endpoint scanner presensi & kit
├── assets/
│   └── css/
│       └── custom.css        # Styling print ID card & font Khidmah
├── config/
│   └── database.php          # Koneksi MySQL PDO & dynamic BASE_URL
├── database/
│   └── schema.sql            # Skema tabel MySQL & seed data awal
├── includes/
│   ├── auth.php              # Autentikasi sesi & hak akses role
│   ├── functions.php         # Helper fungsi, auto-migration, QR & format
│   ├── header.php            # Navigasi responsif & Tailwind CDN
│   └── footer.php            # Footer template & modal scripts
├── pw/                       # Portal Khusus Pengurus Wilayah (PW)
│   ├── index.php             # Dashboard PW & kuota
│   ├── participants.php      # Daftar delegasi wilayah
│   ├── register_bulk.php     # Form input kolektif cepat
│   ├── payment.php           # Konfirmasi iuran delegasi PW
│   └── print_cards.php       # Cetak ID card delegasi PW
├── uploads/                  # Berkas unggahan (foto, bukti transfer, logo)
├── index.php                 # Halaman utama (Landing Page)
├── registrasi.php            # Portal registrasi online delegasi
├── konfirmasi_pembayaran.php # Portal konfirmasi bukti pembayaran
├── daftar_peserta.php        # Halaman publik peserta terdaftar
├── print_proof.php           # Cetak bukti pendaftaran / invoice
├── check_status.php          # Portal cek mandiri peserta & download ID card
├── login.php                 # Halaman login Panitia & PW
├── logout.php                # Logout handler
├── print_card.php            # Tampilan cetak E-ID Card tunggal
├── rundown.php               # Halaman publik jadwal & materi
├── push_github.bat           # Skrip otomatis commit & push GitHub
└── README.md                 # Petunjuk instalasi
```

---

## 💻 Panduan Instalasi Lokal (XAMPP / Laragon)

1. **Letakkan Folder Project:**
   - Salin/pindahkan folder `APP RAKERNAS` ke dalam folder `htdocs` (di XAMPP) atau `www` (di Laragon).
   - Contoh path: `C:/xampp/htdocs/rakernas/`

2. **Buat Database MySQL:**
   - Buka browser dan akses `http://localhost/phpmyadmin`
   - Buat database baru bernama: `db_rakernas_alkhidmah`
   - Klik tab **Import**, pilih file `database/schema.sql`, lalu klik **Import**.

3. **Cek Konfigurasi Database:**
   - File `config/database.php` secara otomatis mendeteksi folder dan domain. Pastikan kredensial MySQL lokal Anda sesuai:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'db_rakernas_alkhidmah');
     ```

4. **Akses Aplikasi:**
   - Buka browser ke alamat: `http://localhost/rakernas/`

---

## ☁️ Panduan Deploy ke Hosting cPanel

1. **Buat Database di cPanel:**
   - Buka **cPanel** > menu **MySQL Databases**. Buat database baru dan user database.
   - Buka **phpMyAdmin** di cPanel, import file `database/schema.sql`.

2. **Upload Berkas Aplikasi:**
   - Compress (ZIP) semua file di dalam folder project ini.
   - Upload file ZIP ke `public_html` (atau subdomain Anda) melalui cPanel File Manager lalu ekstrak.

3. **Sesuaikan `config/database.php`:**
   - Ubah `DB_NAME`, `DB_USER`, dan `DB_PASS` sesuai database cPanel.

---


---

## 📞 Dukungan & Lisensi

Dibuat dengan penuh khidmah untuk menyukseskan perhelatan **Rapat Kerja Nasional Perkumpulan Jama'ah Al Khidmah 2026**.
Semoga acara berjalan lancar, penuh berkah, dan menghasilkan kemaslahatan bagi umat.
