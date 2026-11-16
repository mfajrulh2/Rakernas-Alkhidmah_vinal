-- ========================================================
-- UPDATE SCHEMA: TABEL PEMBAYARAN IURAN KAFILAH PW
-- ========================================================

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

-- Pengaturan Rekening & Iuran Bawaan
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('fee_per_participant', '350000'),
('payment_bank_name', 'Bank Syariah Indonesia (BSI)'),
('payment_account_no', '7123456789'),
('payment_account_name', 'Bendahara RAKERNAS Al Khidmah'),
('payment_instructions', 'Transfer sesuai nominal tagihan delegasi ke rekening resmi panitia. Cantumkan berita transfer: IURAN [KODE PW] (contoh: IURAN PW-JATIM). Setelah transfer, unggah bukti pembayaran di portal ini.')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
