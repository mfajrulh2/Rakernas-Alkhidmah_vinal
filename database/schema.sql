-- ========================================================
-- DATABASE SCHEMA: APLIKASI MANAJEMEN RAKERNAS
-- PERKUMPULAN JAMA'AH AL KHIDMAH
-- ========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Tabel Wilayah (Pengurus Wilayah / PW Al Khidmah)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `wilayah`;
CREATE TABLE `wilayah` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `province` VARCHAR(100) NOT NULL,
  `quota_utusan` INT NOT NULL DEFAULT 10,
  `quota_peninjau` INT NOT NULL DEFAULT 5,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `contact_phone` VARCHAR(30) DEFAULT NULL,
  `reg_token` VARCHAR(64) DEFAULT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Tabel Daerah (Pengurus Daerah / PD Al Khidmah)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `daerah`;
CREATE TABLE `daerah` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pw_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `quota` INT NOT NULL DEFAULT 5,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_daerah_pw` (`pw_id`),
  CONSTRAINT `fk_daerah_pw` FOREIGN KEY (`pw_id`) REFERENCES `wilayah` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Tabel Users (Super Admin, Admin PW, Petugas Scan)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `role` ENUM('superadmin', 'admin_pw', 'petugas_scan') NOT NULL DEFAULT 'admin_pw',
  `pw_id` INT DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_pw` FOREIGN KEY (`pw_id`) REFERENCES `wilayah` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Tabel Kamar / Akomodasi Penginapan
-- --------------------------------------------------------
DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `building_name` VARCHAR(100) NOT NULL,
  `room_number` VARCHAR(50) NOT NULL,
  `gender` ENUM('L', 'P', 'Campur') NOT NULL DEFAULT 'L',
  `capacity` INT NOT NULL DEFAULT 4,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Tabel Transaksi Registrasi Rombongan (registration_groups)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `registration_groups`;
CREATE TABLE `registration_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_code` VARCHAR(50) NOT NULL UNIQUE,
  `kategori` ENUM('PP', 'PW', 'PD', 'Tamu Undangan') NOT NULL,
  `pw_id` INT DEFAULT NULL,
  `daerah_id` INT DEFAULT NULL,
  `daerah_name` VARCHAR(150) DEFAULT NULL,
  `pic_name` VARCHAR(150) NOT NULL,
  `pic_phone` VARCHAR(30) NOT NULL,
  `total_participants` INT NOT NULL DEFAULT 1,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('unpaid', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'unpaid',
  `payment_proof` VARCHAR(255) DEFAULT NULL,
  `payment_date` DATETIME DEFAULT NULL,
  `payment_bank_sender` VARCHAR(100) DEFAULT NULL,
  `payment_amount_paid` DECIMAL(12,2) DEFAULT NULL,
  `payment_notes` TEXT DEFAULT NULL,
  `payment_verified_at` DATETIME DEFAULT NULL,
  `payment_verified_by` INT DEFAULT NULL,
  `payment_reject_reason` TEXT DEFAULT NULL,
  `arrival_date` VARCHAR(50) DEFAULT NULL,
  `arrival_time` VARCHAR(20) DEFAULT NULL,
  `departure_date` VARCHAR(50) DEFAULT NULL,
  `departure_time` VARCHAR(20) DEFAULT NULL,
  `transport_mode` VARCHAR(100) DEFAULT NULL,
  `needs_pickup` TINYINT(1) NOT NULL DEFAULT 0,
  `pickup_location_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_grp_code` (`group_code`),
  INDEX `idx_grp_pw` (`pw_id`),
  INDEX `idx_grp_daerah` (`daerah_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Tabel Peserta / Delegasi Rakernas
-- --------------------------------------------------------
DROP TABLE IF EXISTS `participants`;
CREATE TABLE `participants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_code` VARCHAR(40) NOT NULL UNIQUE,
  `group_id` INT DEFAULT NULL,
  `kategori` ENUM('PP', 'PW', 'PD', 'Tamu Undangan') NOT NULL DEFAULT 'PD',
  `nik` VARCHAR(25) DEFAULT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `gender` ENUM('L', 'P') NOT NULL DEFAULT 'L',
  `unsur_organisasi` VARCHAR(80) NOT NULL DEFAULT 'Al Khidmah',
  `phone` VARCHAR(30) NOT NULL,
  `photo_file` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `pw_id` INT DEFAULT NULL,
  `daerah_id` INT DEFAULT NULL,
  `pd_name` VARCHAR(120) DEFAULT NULL,
  `jabatan_organisasi` VARCHAR(120) DEFAULT 'Pengurus',
  `participant_type` ENUM('utusan', 'peninjau', 'pengurus_pusat', 'undangan', 'panitia') NOT NULL DEFAULT 'utusan',
  `komisi` VARCHAR(100) NOT NULL DEFAULT 'A. Bidang Organisasi',
  `room_id` INT DEFAULT NULL,
  `arrival_date` DATETIME DEFAULT NULL,
  `transport_type` VARCHAR(50) DEFAULT 'Kendaraan Pribadi / Elf Rombongan',
  `needs_pickup` TINYINT(1) NOT NULL DEFAULT 0,
  `pickup_location_notes` TEXT DEFAULT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `status_verifikasi` ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'verified',
  `is_attended` TINYINT(1) NOT NULL DEFAULT 0,
  `attended_at` DATETIME DEFAULT NULL,
  `kit_received` TINYINT(1) NOT NULL DEFAULT 0,
  `kit_received_at` DATETIME DEFAULT NULL,
  `registered_by` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_reg_code` (`registration_code`),
  INDEX `idx_part_group` (`group_id`),
  INDEX `idx_pw_id` (`pw_id`),
  INDEX `idx_is_attended` (`is_attended`),
  CONSTRAINT `fk_part_pw` FOREIGN KEY (`pw_id`) REFERENCES `wilayah` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_part_group` FOREIGN KEY (`group_id`) REFERENCES `registration_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_part_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_part_user` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Tabel Rundown & Jadwal Kegiatan
-- --------------------------------------------------------
DROP TABLE IF EXISTS `schedules`;
CREATE TABLE `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `location` VARCHAR(120) NOT NULL,
  `speaker` VARCHAR(150) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `order_num` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Tabel Materi & Dokumen Persidangan
-- --------------------------------------------------------
DROP TABLE IF EXISTS `materials`;
CREATE TABLE `materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'Materi Sidang',
  `file_url` TEXT NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Tabel Pengaturan Sistem (Settings)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` VARCHAR(60) PRIMARY KEY,
  `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- DATA AWAL (SEEDS)
-- ========================================================

-- Settings Default
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('event_name', 'Rapat Kerja Nasional (RAKERNAS) Perkumpulan Jama\'ah Al Khidmah'),
('event_theme', 'Meneguhkan Khidmah, Menguatkan Ukhuwah, Menuju Kemandirian Jam\'iyyah'),
('event_date', '15 - 17 November 2026'),
('event_location', 'Pondok Pesantren Assalafi Al Fithrah & Gedung Serbaguna'),
('contact_center', '0857-2726-6990'),
('app_brand_title', 'AL KHIDMAH'),
('app_brand_subtitle', 'Sistem Rakernas'),
('bank_name', 'Bank Rakyat Indonesia (BRI)'),
('bank_account_no', '0023-01-001234-53-1'),
('bank_account_holder', 'Panitia Pelaksana RAKERNAS Jama\'ah Al Khidmah'),
('fee_per_person', '150000'),
('default_quota_utusan', '10'),
('default_quota_peninjau', '5'),
('registration_open', '1');

-- Wilayah Awal (PW Jamaah Al Khidmah)
INSERT INTO `wilayah` (`code`, `name`, `province`, `quota_utusan`, `quota_peninjau`, `contact_person`, `contact_phone`, `reg_token`) VALUES
('PW-JATIM', 'PW Jama\'ah Al Khidmah Jawa Timur', 'Jawa Timur', 15, 10, 'H. M. Syafi\'i', '081234567891', 'token_jatim_2026'),
('PW-JATENG', 'PW Jama\'ah Al Khidmah Jawa Tengah', 'Jawa Tengah', 12, 8, 'H. Ahmad Fauzi', '081234567892', 'token_jateng_2026'),
('PW-DIY', 'PW Jama\'ah Al Khidmah D.I. Yogyakarta', 'D.I. Yogyakarta', 10, 5, 'H. Nur Hidayat', '081234567893', 'token_diy_2026'),
('PW-JABAR', 'PW Jama\'ah Al Khidmah Jawa Barat', 'Jawa Barat', 10, 5, 'H. Ridwan Malik', '081234567894', 'token_jabar_2026'),
('PW-DKI', 'PW Jama\'ah Al Khidmah DKI Jakarta & Banten', 'DKI Jakarta', 12, 6, 'H. Lukman Hakim', '081234567895', 'token_dki_2026'),
('PW-BALI-NTB', 'PW Jama\'ah Al Khidmah Bali & NTB', 'Bali & NTB', 8, 4, 'H. Subhan', '081234567896', 'token_bali_2026'),
('PW-SUMUT', 'PW Jama\'ah Al Khidmah Sumatera Utara', 'Sumatera Utara', 8, 4, 'H. Zulkifli', '081234567897', 'token_sumut_2026'),
('PW-RIAU', 'PW Jama\'ah Al Khidmah Riau & Kepri', 'Riau', 8, 4, 'H. Syamsul Bahri', '081234567898', 'token_riau_2026'),
('PW-SUMSEL', 'PW Jama\'ah Al Khidmah Sumatera Selatan & Lampung', 'Sumatera Selatan', 8, 4, 'H. Mansyur', '081234567899', 'token_sumsel_2026'),
('PW-KALSEL', 'PW Jama\'ah Al Khidmah Kalimantan Selatan', 'Kalimantan Selatan', 10, 5, 'H. Abdul Ghani', '081234567810', 'token_kalsel_2026'),
('PW-KALTIM', 'PW Jama\'ah Al Khidmah Kalimantan Timur', 'Kalimantan Timur', 8, 4, 'H. Mahfudz', '081234567811', 'token_kaltim_2026'),
('PW-SULSEL', 'PW Jama\'ah Al Khidmah Sulawesi Selatan', 'Sulawesi Selatan', 8, 4, 'H. Rusli', '081234567812', 'token_sulsel_2026'),
('PW-LN', 'PW Jama\'ah Al Khidmah Luar Negeri (Malaysia & Singapura)', 'Luar Negeri', 6, 4, 'Ust. Farhan', '+60123456789', 'token_ln_2026');

-- Data Daerah Contoh (PD)
INSERT INTO `daerah` (`pw_id`, `name`, `code`, `quota`) VALUES
(1, 'PD Surabaya', 'PD-SBY', 10),
(1, 'PD Gresik', 'PD-GSK', 8),
(1, 'PD Sidoarjo', 'PD-SDA', 8),
(1, 'PD Lamongan', 'PD-LMG', 6),
(2, 'PD Semarang', 'PD-SMG', 8),
(2, 'PD Kudus', 'PD-KDS', 6),
(5, 'PD Jakarta Selatan', 'PD-JS', 6);

-- Users Default:
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `pw_id`, `phone`) VALUES
('admin', '$2y$10$w81z.lO3R/u7fM9u76yW5.6r0QJ8iQd.PswZ5kQW6K3a9fXvY1M2.', 'Panitia Pusat Rakernas', 'superadmin', NULL, '085727266990'),
('petugas', '$2y$10$w81z.lO3R/u7fM9u76yW5.6r0QJ8iQd.PswZ5kQW6K3a9fXvY1M2.', 'Petugas Front Desk Presensi', 'petugas_scan', NULL, '08110000002'),
('pw_jatim', '$2y$10$w81z.lO3R/u7fM9u76yW5.6r0QJ8iQd.PswZ5kQW6K3a9fXvY1M2.', 'Admin PW Al Khidmah Jatim', 'admin_pw', 1, '081234567891'),
('pw_jateng', '$2y$10$w81z.lO3R/u7fM9u76yW5.6r0QJ8iQd.PswZ5kQW6K3a9fXvY1M2.', 'Admin PW Al Khidmah Jateng', 'admin_pw', 2, '081234567892'),
('pw_diy', '$2y$10$w81z.lO3R/u7fM9u76yW5.6r0QJ8iQd.PswZ5kQW6K3a9fXvY1M2.', 'Admin PW Al Khidmah DIY', 'admin_pw', 3, '081234567893'),
('pw_jabar', '$2y$10$w81z.lO3R/u7fM9u76yW5.6r0QJ8iQd.PswZ5kQW6K3a9fXvY1M2.', 'Admin PW Al Khidmah Jabar', 'admin_pw', 4, '081234567894'),
('pw_dki', '$2y$10$w81z.lO3R/u7fM9u76yW5.6r0QJ8iQd.PswZ5kQW6K3a9fXvY1M2.', 'Admin PW Al Khidmah DKI & Banten', 'admin_pw', 5, '081234567895');

-- Data Kamar Penginapan Contoh
INSERT INTO `rooms` (`building_name`, `room_number`, `gender`, `capacity`, `notes`) VALUES
('Wisma Utama (Ikhwan)', 'Kamar 101', 'L', 4, 'Lantai 1, AC, Kamar Mandi Dalam'),
('Wisma Utama (Ikhwan)', 'Kamar 102', 'L', 4, 'Lantai 1, AC, Kamar Mandi Dalam'),
('Wisma Utama (Ikhwan)', 'Kamar 103', 'L', 4, 'Lantai 1, AC, Kamar Mandi Dalam'),
('Wisma Khodijah (Akhwat)', 'Kamar 201', 'P', 4, 'Lantai 2, AC, Kamar Mandi Dalam'),
('Wisma Khodijah (Akhwat)', 'Kamar 202', 'P', 4, 'Lantai 2, AC, Kamar Mandi Dalam'),
('Gedung Tamu VIP', 'Kamar VIP 01', 'L', 2, 'Khusus Masyaikh / Pengurus Pusat');

-- Data Rundown Contoh
INSERT INTO `schedules` (`session_date`, `start_time`, `end_time`, `title`, `location`, `speaker`, `description`, `order_num`) VALUES
('2026-11-15', '13:00:00', '17:00:00', 'Registrasi Ulang Peserta & Check-in Penginapan', 'Lobi Gedung Serbaguna', 'Panitia Kesekretariatan', 'Verifikasi berkas mandat, scan barcode, penyerahan Kit Rakernas dan kunci kamar.', 1),
('2026-11-15', '19:30:00', '22:00:00', 'Pembukaan RAKERNAS & Majlis Dzikir Maulidurrasul SAW', 'Aula Utama', 'Majelis Ilmi & Pengurus Pusat', 'Pembukaan resmi oleh Ketua Umum & Doa Bersama untuk Keselamatan Bangsa.', 2),
('2026-11-16', '08:30:00', '11:45:00', 'Sidang Pleno I: Laporan Kinerja Pengurus Pusat', 'Aula Utama', 'Pimpinan Sidang Pleno', 'Penyampaian laporan pertanggungjawaban program kerja berjalan.', 3),
('2026-11-16', '13:15:00', '16:30:00', 'Sidang Komisi (A: Organisasi, B: Majlis, C: Usaha, D: Pemuda, E: IT)', 'Ruang Komisi Masing-masing', 'Ketua Tiap Komisi', 'Pembahasan mendalam strategi jam\'iyyah, kaderisasi, dan kemandirian.', 4),
('2026-11-16', '19:30:00', '21:30:00', 'Sidang Pleno II: Pengesahan Hasil Sidang Komisi', 'Aula Utama', 'Presidium Sidang', 'Penetapan keputusan resmi Rakernas dan penandatanganan berita acara.', 5),
('2026-11-17', '08:00:00', '10:30:00', 'Penutupan Resmi & Ramah Tamah Delegasi', 'Aula Utama', 'Panitia & Masyaikh', 'Penyerahan cinderamata dan pelepasan kepulangan kafilah.', 6);

SET FOREIGN_KEY_CHECKS = 1;
