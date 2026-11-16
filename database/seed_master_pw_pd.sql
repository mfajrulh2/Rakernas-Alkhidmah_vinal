-- ========================================================
-- MASTER DATA RESMI PW & PD RAKERNAS AL KHIDMAH
-- 31 Pengurus Wilayah (PW / PDI) & 154 Pengurus Daerah (PD)
-- ========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 0. PASTIKAN STRUKTUR KOLOM TABEL PARTICIPANTS SIAP
ALTER TABLE `participants` ADD COLUMN IF NOT EXISTS `photo` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `participants` MODIFY COLUMN `pw_id` INT DEFAULT NULL;

-- 1. INSERT / UPDATE 31 WILAYAH
INSERT INTO `wilayah` (`code`, `name`, `province`, `quota_utusan`, `quota_peninjau`, `reg_token`) VALUES
('PPA', 'PW Jama\'ah Al Khidmah Papua', 'Papua', 10, 5, MD5(CONCAT('PPA_', RAND()))),
('SULSEL', 'PW Jama\'ah Al Khidmah Sulawesi Selatan', 'Sulawesi Selatan', 10, 5, MD5(CONCAT('SULSEL_', RAND()))),
('GRNTL', 'PW Jama\'ah Al Khidmah Gorontalo', 'Gorontalo', 10, 5, MD5(CONCAT('GRNTL_', RAND()))),
('SULTRA', 'PW Jama\'ah Al Khidmah Sulawesi Utara', 'Sulawesi Utara', 10, 5, MD5(CONCAT('SULTRA_', RAND()))),
('SULTENG', 'PW Jama\'ah Al Khidmah Sulawesi Tengah', 'Sulawesi Tengah', 10, 5, MD5(CONCAT('SULTENG_', RAND()))),
('MLKU', 'PW Jama\'ah Al Khidmah Maluku', 'Maluku', 10, 5, MD5(CONCAT('MLKU_', RAND()))),
('MLKUTGH', 'PW Jama\'ah Al Khidmah Maluku Tengah', 'Maluku Tengah', 10, 5, MD5(CONCAT('MLKUTGH_', RAND()))),
('MLKUTR', 'PW Jama\'ah Al Khidmah Maluku Utara', 'Maluku Utara', 10, 5, MD5(CONCAT('MLKUTR_', RAND()))),
('NTT', 'PW Jama\'ah Al Khidmah Nusa Tenggara Timur', 'Nusa Tenggara Timur', 10, 5, MD5(CONCAT('NTT_', RAND()))),
('NTB', 'PW Jama\'ah Al Khidmah Nusa Tenggara Barat', 'Nusa Tenggara Barat', 10, 5, MD5(CONCAT('NTB_', RAND()))),
('BALI', 'PW Jama\'ah Al Khidmah Bali', 'Bali', 10, 5, MD5(CONCAT('BALI_', RAND()))),
('KALSEL', 'PW Jama\'ah Al Khidmah Kalimantan Selatan', 'Kalimantan Selatan', 10, 5, MD5(CONCAT('KALSEL_', RAND()))),
('KALTENG', 'PW Jama\'ah Al Khidmah Kalimantan Tengah', 'Kalimantan Tengah', 10, 5, MD5(CONCAT('KALTENG_', RAND()))),
('KALBAR', 'PW Jama\'ah Al Khidmah Kalimantan Barat', 'Kalimantan Barat', 10, 5, MD5(CONCAT('KALBAR_', RAND()))),
('KALTIM', 'PW Jama\'ah Al Khidmah Kalimantan Timur', 'Kalimantan Timur', 10, 5, MD5(CONCAT('KALTIM_', RAND()))),
('SUMSEL', 'PW Jama\'ah Al Khidmah Sumatera Selatan', 'Sumatera Selatan', 10, 5, MD5(CONCAT('SUMSEL_', RAND()))),
('SUMTRA', 'PW Jama\'ah Al Khidmah Sumatera Utara', 'Sumatera Utara', 10, 5, MD5(CONCAT('SUMTRA_', RAND()))),
('SUMBAR', 'PW Jama\'ah Al Khidmah Sumatera Barat', 'Sumatera Barat', 10, 5, MD5(CONCAT('SUMBAR_', RAND()))),
('JMB', 'PW Jama\'ah Al Khidmah Jambi', 'Jambi', 10, 5, MD5(CONCAT('JMB_', RAND()))),
('BNGKL', 'PW Jama\'ah Al Khidmah Bengkulu', 'Bengkulu', 10, 5, MD5(CONCAT('BNGKL_', RAND()))),
('LAMP', 'PW Jama\'ah Al Khidmah Lampung', 'Lampung', 10, 5, MD5(CONCAT('LAMP_', RAND()))),
('KEPRI', 'PW Jama\'ah Al Khidmah Kepulauan Riau', 'Kepulauan Riau', 10, 5, MD5(CONCAT('KEPRI_', RAND()))),
('DKI', 'PW Jama\'ah Al Khidmah DKI Jakarta', 'DKI Jakarta', 10, 5, MD5(CONCAT('DKI_', RAND()))),
('JABAR', 'PW Jama\'ah Al Khidmah Jawa Barat', 'Jawa Barat', 10, 5, MD5(CONCAT('JABAR_', RAND()))),
('BNTN', 'PW Jama\'ah Al Khidmah Banten', 'Banten', 10, 5, MD5(CONCAT('BNTN_', RAND()))),
('DIY', 'PW Jama\'ah Al Khidmah D.I. Yogyakarta', 'D.I. Yogyakarta', 10, 5, MD5(CONCAT('DIY_', RAND()))),
('JATENG', 'PW Jama\'ah Al Khidmah Jawa Tengah', 'Jawa Tengah', 10, 5, MD5(CONCAT('JATENG_', RAND()))),
('JATIM', 'PW Jama\'ah Al Khidmah Jawa Timur', 'Jawa Timur', 10, 5, MD5(CONCAT('JATIM_', RAND()))),
('SING', 'PDI Jama\'ah Al Khidmah Singapura', 'Singapura', 10, 5, MD5(CONCAT('SING_', RAND()))),
('MLY', 'PDI Jama\'ah Al Khidmah Malaysia', 'Malaysia', 10, 5, MD5(CONCAT('MLY_', RAND()))),
('THAI', 'PDI Jama\'ah Al Khidmah Thailand', 'Thailand', 10, 5, MD5(CONCAT('THAI_', RAND()))
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `province` = VALUES(`province`);

-- 2. INSERT DAERAH (PD) PER WILAYAH
-- Kuota default tiap PD = 5 orang
INSERT INTO `daerah` (`pw_id`, `name`, `quota`) 
SELECT w.id, d.name, 5
FROM wilayah w
JOIN (
    -- PAPUA
    SELECT 'PPA' as code, 'PD Keerom' as name UNION ALL
    SELECT 'PPA', 'PD Merauke' UNION ALL
    SELECT 'PPA', 'PD Jayapura' UNION ALL
    SELECT 'PPA', 'PD Timika' UNION ALL
    -- SULAWESI SELATAN
    SELECT 'SULSEL', 'PD Makassar' UNION ALL
    SELECT 'SULSEL', 'PD Wajo' UNION ALL
    SELECT 'SULSEL', 'PD Luwu Timur' UNION ALL
    SELECT 'SULSEL', 'PD Luwu Utara' UNION ALL
    SELECT 'SULSEL', 'PD Gowa' UNION ALL
    SELECT 'SULSEL', 'PD Maros' UNION ALL
    SELECT 'SULSEL', 'PD Soppeng' UNION ALL
    SELECT 'SULSEL', 'PD Bone' UNION ALL
    -- GORONTALO
    SELECT 'GRNTL', 'PD Gorontalo' UNION ALL
    -- SULAWESI UTARA
    SELECT 'SULTRA', 'PD Manado' UNION ALL
    SELECT 'SULTRA', 'PD Minahasa Utara' UNION ALL
    SELECT 'SULTRA', 'PD Bitung' UNION ALL
    SELECT 'SULTRA', 'PD Bolaang Mongondow' UNION ALL
    SELECT 'SULTRA', 'PD Tomohon' UNION ALL
    -- SULAWESI TENGAH
    SELECT 'SULTENG', 'PD Palu' UNION ALL
    -- MALUKU
    SELECT 'MLKU', 'PD Kota Ambon' UNION ALL
    -- MALUKU TENGAH
    SELECT 'MLKUTGH', 'PD Masohi' UNION ALL
    -- MALUKU UTARA
    SELECT 'MLKUTR', 'PD Ternate' UNION ALL
    -- NUSA TENGGARA TIMUR
    SELECT 'NTT', 'PD Timor Tengah Utara' UNION ALL
    SELECT 'NTT', 'PD Bajawa' UNION ALL
    SELECT 'NTT', 'PD Kupang' UNION ALL
    SELECT 'NTT', 'PD Manggarai' UNION ALL
    -- NUSA TENGGARA BARAT
    SELECT 'NTB', 'PD Lombok Barat' UNION ALL
    SELECT 'NTB', 'PD Lombok Tengah' UNION ALL
    SELECT 'NTB', 'PD Mataram' UNION ALL
    -- BALI
    SELECT 'BALI', 'PD Denpasar' UNION ALL
    SELECT 'BALI', 'PD Gianyar' UNION ALL
    SELECT 'BALI', 'PD Tabanan' UNION ALL
    SELECT 'BALI', 'PD Badung' UNION ALL
    -- KALIMANTAN SELATAN
    SELECT 'KALSEL', 'PD Banjarbaru' UNION ALL
    SELECT 'KALSEL', 'PD Muara Teweh' UNION ALL
    SELECT 'KALSEL', 'PD Banjarmasin' UNION ALL
    -- KALIMANTAN TENGAH
    SELECT 'KALTENG', 'PD Katingan' UNION ALL
    SELECT 'KALTENG', 'PD Pulang Pisau' UNION ALL
    SELECT 'KALTENG', 'PD Kapuas' UNION ALL
    SELECT 'KALTENG', 'PD Lamandau' UNION ALL
    SELECT 'KALTENG', 'PD Sukamara' UNION ALL
    SELECT 'KALTENG', 'PD Palangkaraya' UNION ALL
    -- KALIMANTAN BARAT
    SELECT 'KALBAR', 'PD Pontianak' UNION ALL
    SELECT 'KALBAR', 'PD Gabang Landak' UNION ALL
    -- KALIMANTAN TIMUR
    SELECT 'KALTIM', 'PD Samarinda' UNION ALL
    SELECT 'KALTIM', 'PD Pangkalan Bun' UNION ALL
    SELECT 'KALTIM', 'PD Kutai Kartanegara' UNION ALL
    -- SUMATERA SELATAN
    SELECT 'SUMSEL', 'PD Musirawas Utara' UNION ALL
    SELECT 'SUMSEL', 'PD Musirawas' UNION ALL
    SELECT 'SUMSEL', 'PD Palembang' UNION ALL
    SELECT 'SUMSEL', 'PD OKU Timur' UNION ALL
    SELECT 'SUMSEL', 'PD OKI' UNION ALL
    SELECT 'SUMSEL', 'PD Kerinci' UNION ALL
    SELECT 'SUMSEL', 'PD OKU Selatan' UNION ALL
    SELECT 'SUMSEL', 'PD OKU Induk' UNION ALL
    -- SUMATERA UTARA
    SELECT 'SUMTRA', 'PD Medan' UNION ALL
    -- SUMATERA BARAT
    SELECT 'SUMBAR', 'PD Padang' UNION ALL
    -- JAMBI
    SELECT 'JMB', 'PD Sarolangun' UNION ALL
    SELECT 'JMB', 'PD Tanjung Jabung Barat' UNION ALL
    SELECT 'JMB', 'PD Muaro Jambi' UNION ALL
    SELECT 'JMB', 'PD Kota Jambi' UNION ALL
    -- BENGKULU
    SELECT 'BNGKL', 'PD Bengkulu Kota' UNION ALL
    SELECT 'BNGKL', 'PD Bengkulu Utara' UNION ALL
    -- LAMPUNG
    SELECT 'LAMP', 'PD Mesuji' UNION ALL
    SELECT 'LAMP', 'PD Tulang Bawang Barat' UNION ALL
    SELECT 'LAMP', 'PD Metro' UNION ALL
    SELECT 'LAMP', 'PD Pesawaran' UNION ALL
    SELECT 'LAMP', 'PD Pesisir Barat' UNION ALL
    SELECT 'LAMP', 'PD Lampung Selatan' UNION ALL
    SELECT 'LAMP', 'PD Kota Bandar Lampung' UNION ALL
    SELECT 'LAMP', 'PD Tulang Bawang' UNION ALL
    -- KEPULAUAN RIAU
    SELECT 'KEPRI', 'PD Batam' UNION ALL
    SELECT 'KEPRI', 'PD Bintan' UNION ALL
    SELECT 'KEPRI', 'PD Karimun' UNION ALL
    SELECT 'KEPRI', 'PD Anambas' UNION ALL
    SELECT 'KEPRI', 'PD Tanjungpinang' UNION ALL
    SELECT 'KEPRI', 'PD Natuna' UNION ALL
    -- DKI JAKARTA
    SELECT 'DKI', 'PD Jakarta Pusat' UNION ALL
    SELECT 'DKI', 'PD Jakarta Barat' UNION ALL
    SELECT 'DKI', 'PD Jakarta Timur' UNION ALL
    SELECT 'DKI', 'PD Jakarta Utara' UNION ALL
    SELECT 'DKI', 'PD Jakarta Selatan' UNION ALL
    SELECT 'DKI', 'PD Kepulauan Seribu' UNION ALL
    -- JAWA BARAT
    SELECT 'JABAR', 'PD Bogor' UNION ALL
    SELECT 'JABAR', 'PD Kab. Cirebon' UNION ALL
    SELECT 'JABAR', 'PD Kota Cirebon' UNION ALL
    SELECT 'JABAR', 'PD Kota Bandung' UNION ALL
    SELECT 'JABAR', 'PD Bandung Barat' UNION ALL
    SELECT 'JABAR', 'PD Garut' UNION ALL
    SELECT 'JABAR', 'PD Karawang' UNION ALL
    SELECT 'JABAR', 'PD Ciamis' UNION ALL
    SELECT 'JABAR', 'PD Cimahi' UNION ALL
    SELECT 'JABAR', 'PD Kuningan' UNION ALL
    SELECT 'JABAR', 'PD Majalengka' UNION ALL
    -- BANTEN
    SELECT 'BNTN', 'PD Kab. Serang' UNION ALL
    SELECT 'BNTN', 'PD Tangerang' UNION ALL
    SELECT 'BNTN', 'PD Tangerang Selatan' UNION ALL
    SELECT 'BNTN', 'PD Serang Kota' UNION ALL
    SELECT 'BNTN', 'PD Cilegon' UNION ALL
    -- DIY
    SELECT 'DIY', 'PD Bantul' UNION ALL
    SELECT 'DIY', 'PD Sleman' UNION ALL
    SELECT 'DIY', 'PD Kulon Progo' UNION ALL
    SELECT 'DIY', 'PD Yogyakarta' UNION ALL
    SELECT 'DIY', 'PD Gunungkidul' UNION ALL
    -- JAWA TENGAH
    SELECT 'JATENG', 'PD Batang' UNION ALL
    SELECT 'JATENG', 'PD Blora' UNION ALL
    SELECT 'JATENG', 'PD Boyolali' UNION ALL
    SELECT 'JATENG', 'PD Brebes' UNION ALL
    SELECT 'JATENG', 'PD Demak' UNION ALL
    SELECT 'JATENG', 'PD Grobogan' UNION ALL
    SELECT 'JATENG', 'PD Jepara' UNION ALL
    SELECT 'JATENG', 'PD Karanganyar' UNION ALL
    SELECT 'JATENG', 'PD Kendal' UNION ALL
    SELECT 'JATENG', 'PD Kudus' UNION ALL
    SELECT 'JATENG', 'PD Pati' UNION ALL
    SELECT 'JATENG', 'PD Pekalongan' UNION ALL
    SELECT 'JATENG', 'PD Pemalang' UNION ALL
    SELECT 'JATENG', 'PD Kab. Semarang' UNION ALL
    SELECT 'JATENG', 'PD Kota Semarang' UNION ALL
    SELECT 'JATENG', 'PD Salatiga' UNION ALL
    SELECT 'JATENG', 'PD Solo' UNION ALL
    SELECT 'JATENG', 'PD Sragen' UNION ALL
    SELECT 'JATENG', 'PD Tegal' UNION ALL
    SELECT 'JATENG', 'PD Banjarnegara' UNION ALL
    SELECT 'JATENG', 'PD Cilacap' UNION ALL
    SELECT 'JATENG', 'PD Banyumas' UNION ALL
    SELECT 'JATENG', 'PD Kebumen' UNION ALL
    SELECT 'JATENG', 'PD Temanggung' UNION ALL
    SELECT 'JATENG', 'PD Klaten' UNION ALL
    SELECT 'JATENG', 'PD Wonosobo' UNION ALL
    SELECT 'JATENG', 'PD Magelang' UNION ALL
    SELECT 'JATENG', 'PD Purworejo' UNION ALL
    SELECT 'JATENG', 'PD Purbalingga' UNION ALL
    SELECT 'JATENG', 'PD Wonogiri' UNION ALL
    -- JAWA TIMUR
    SELECT 'JATIM', 'PD Kab. Malang' UNION ALL
    SELECT 'JATIM', 'PD Kota Malang' UNION ALL
    SELECT 'JATIM', 'PD Kota Batu' UNION ALL
    SELECT 'JATIM', 'PD Blitar' UNION ALL
    SELECT 'JATIM', 'PD Kediri' UNION ALL
    SELECT 'JATIM', 'PD Tulungagung' UNION ALL
    SELECT 'JATIM', 'PD Trenggalek' UNION ALL
    SELECT 'JATIM', 'PD Pacitan' UNION ALL
    SELECT 'JATIM', 'PD Ponorogo' UNION ALL
    SELECT 'JATIM', 'PD Nganjuk' UNION ALL
    SELECT 'JATIM', 'PD Ngawi' UNION ALL
    SELECT 'JATIM', 'PD Madiun' UNION ALL
    SELECT 'JATIM', 'PD Magetan' UNION ALL
    SELECT 'JATIM', 'PD Bojonegoro' UNION ALL
    SELECT 'JATIM', 'PD Tuban' UNION ALL
    SELECT 'JATIM', 'PD Lamongan' UNION ALL
    SELECT 'JATIM', 'PD Gresik' UNION ALL
    SELECT 'JATIM', 'PD Jombang' UNION ALL
    SELECT 'JATIM', 'PD Mojokerto' UNION ALL
    SELECT 'JATIM', 'PD Kota Surabaya' UNION ALL
    SELECT 'JATIM', 'PD Sidoarjo' UNION ALL
    SELECT 'JATIM', 'PD Bangkalan' UNION ALL
    SELECT 'JATIM', 'PD Pamekasan' UNION ALL
    SELECT 'JATIM', 'PD Sumenep' UNION ALL
    SELECT 'JATIM', 'PD Probolinggo' UNION ALL
    SELECT 'JATIM', 'PD Pasuruan' UNION ALL
    SELECT 'JATIM', 'PD Sampang' UNION ALL
    SELECT 'JATIM', 'PD Jember' UNION ALL
    SELECT 'JATIM', 'PD Lumajang' UNION ALL
    SELECT 'JATIM', 'PD Banyuwangi' UNION ALL
    -- PDI SINGAPURA, MALAYSIA, THAILAND
    SELECT 'SING', 'PDI Singapura' UNION ALL
    SELECT 'MLY', 'PDI Kuala Lumpur' UNION ALL
    SELECT 'MLY', 'PDI Johor' UNION ALL
    SELECT 'MLY', 'PDI Melaka' UNION ALL
    SELECT 'MLY', 'PDI Sepang' UNION ALL
    SELECT 'MLY', 'PDI Tampin Negeri 9' UNION ALL
    SELECT 'THAI', 'PDI Yala Thailand Selatan'
) d ON (w.code = d.code OR w.code = CONCAT('PW-', d.code) OR w.province = d.code)
WHERE NOT EXISTS (
    SELECT 1 FROM `daerah` existing 
    WHERE existing.pw_id = w.id AND existing.name = d.name
);

SET FOREIGN_KEY_CHECKS = 1;
