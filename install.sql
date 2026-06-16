
CREATE DATABASE IF NOT EXISTS `rfid_payment`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `rfid_payment`;

-- ── Tabel Users (Pemilik Kartu RFID) ──────────
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rfid_uid` VARCHAR(50) NOT NULL UNIQUE COMMENT 'UID dari kartu RFID',
    `nama` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `telepon` VARCHAR(20) DEFAULT NULL,
    `saldo` DECIMAL(12,2) DEFAULT 0.00,
    `pin` VARCHAR(255) DEFAULT NULL COMMENT 'Hashed PIN untuk keamanan',
    `status` ENUM('active','blocked','inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_rfid_uid` (`rfid_uid`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- ── Tabel Transaksi ───────────────────────────
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `order_id` VARCHAR(100) UNIQUE COMMENT 'ID unik transaksi',
    `jenis` ENUM('topup','payment','refund') NOT NULL,
    `jumlah` DECIMAL(12,2) NOT NULL,
    `metode_bayar` VARCHAR(50) DEFAULT NULL COMMENT 'BCA VA, Dana, QRIS, Manual, dll',
    `gateway` VARCHAR(50) DEFAULT NULL COMMENT 'manual, webhook, atau nama gateway',
    `gateway_ref` VARCHAR(200) DEFAULT NULL COMMENT 'Reference ID dari payment gateway',
    `status` ENUM('pending','success','failed','expired','refunded') DEFAULT 'pending',
    `keterangan` VARCHAR(255) DEFAULT NULL,
    `approved_by` INT DEFAULT NULL COMMENT 'Admin ID yang approve',
    `raw_response` TEXT DEFAULT NULL COMMENT 'Raw JSON dari payment gateway',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_user_jenis` (`user_id`, `jenis`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB;

-- ── Tabel Payment Methods (Metode Pembayaran) ─
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipe` ENUM('bank','ewallet','qris','other') NOT NULL COMMENT 'Kategori metode',
    `nama` VARCHAR(100) NOT NULL COMMENT 'Nama bank/ewallet (BCA, Dana, OVO, dll)',
    `kode` VARCHAR(20) DEFAULT NULL COMMENT 'Kode bank (014=BCA, 008=Mandiri, dll)',
    `nomor_akun` VARCHAR(100) NOT NULL COMMENT 'Nomor rekening atau nomor e-wallet',
    `nama_pemilik` VARCHAR(100) NOT NULL COMMENT 'Nama pemilik rekening/akun',
    `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Nama file icon',
    `instruksi` TEXT DEFAULT NULL COMMENT 'Instruksi transfer untuk user',
    `is_active` TINYINT(1) DEFAULT 1,
    `urutan` INT DEFAULT 0 COMMENT 'Urutan tampil',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tipe` (`tipe`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB;

-- ── Tabel Top-up Requests ─────────────────────
CREATE TABLE IF NOT EXISTS `topup_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `payment_method_id` INT DEFAULT NULL,
    `jumlah` DECIMAL(12,2) NOT NULL,
    `bukti_transfer` VARCHAR(255) DEFAULT NULL COMMENT 'Path file bukti transfer',
    `catatan` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
    `approved_by` INT DEFAULT NULL,
    `approved_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB;

-- ── Tabel Devices (Alat RFID Reader) ──────────
CREATE TABLE IF NOT EXISTS `devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_id` VARCHAR(50) NOT NULL UNIQUE COMMENT 'ID unik alat',
    `nama` VARCHAR(100) NOT NULL,
    `lokasi` VARCHAR(100) DEFAULT NULL,
    `tipe` ENUM('payment','topup','checkin') DEFAULT 'payment',
    `harga_tap` DECIMAL(12,2) DEFAULT 10000.00 COMMENT 'Harga per tap di device ini',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_seen` TIMESTAMP NULL COMMENT 'Terakhir kali device aktif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_device_id` (`device_id`)
) ENGINE=InnoDB;

-- ── Tabel RFID Logs ───────────────────────────
CREATE TABLE IF NOT EXISTS `rfid_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rfid_uid` VARCHAR(50) NOT NULL,
    `aksi` ENUM('tap_pay','tap_check','tap_topup','tap_unknown','register') NOT NULL,
    `device_id` VARCHAR(50) DEFAULT NULL,
    `result` VARCHAR(50) DEFAULT NULL,
    `detail` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_uid_time` (`rfid_uid`, `created_at`),
    INDEX `idx_device` (`device_id`)
) ENGINE=InnoDB;

-- ── Tabel Admin ───────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `role` ENUM('superadmin','admin','operator') DEFAULT 'operator',
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Tabel Settings ────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    `value` TEXT DEFAULT NULL,
    `label` VARCHAR(100) DEFAULT NULL COMMENT 'Label untuk tampilan admin',
    `type` ENUM('text','number','boolean','textarea') DEFAULT 'text',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ══════════════════════════════════════════════
-- DATA DEFAULT
-- ══════════════════════════════════════════════

-- Admin default (password: admin123)
INSERT INTO `admins` (`username`, `password`, `nama`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'superadmin');

-- Settings default
INSERT INTO `settings` (`key`, `value`, `label`, `type`) VALUES
('app_name', 'RFID Payment Gateway', 'Nama Aplikasi', 'text'),
('tap_price', '10000', 'Harga per Tap (Rp)', 'number'),
('min_topup', '10000', 'Minimum Top-up (Rp)', 'number'),
('max_topup', '5000000', 'Maksimum Top-up (Rp)', 'number'),
('store_name', 'Toko Saya', 'Nama Toko/Lokasi', 'text'),
('store_address', '-', 'Alamat', 'textarea'),
('low_balance_warning', '5000', 'Peringatan Saldo Rendah (Rp)', 'number'),
('brand_color', '#6366f1', 'Warna Aksen Brand (HEX)', 'text'),
('brand_gradient', 'linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa)', 'Gradasi Aksen Brand', 'text');

-- Contoh metode pembayaran
INSERT INTO `payment_methods` (`tipe`, `nama`, `kode`, `nomor_akun`, `nama_pemilik`, `instruksi`, `urutan`) VALUES
('bank', 'BCA', '014', '1234567890', 'PT Contoh Usaha', 'Transfer ke rekening BCA di atas, lalu konfirmasi ke admin.', 1),
('bank', 'Mandiri', '008', '0987654321', 'PT Contoh Usaha', 'Transfer ke rekening Mandiri di atas.', 2),
('bank', 'BRI', '002', '1122334455', 'PT Contoh Usaha', 'Transfer ke rekening BRI di atas.', 3),
('bank', 'BNI', '009', '5566778899', 'PT Contoh Usaha', 'Transfer ke rekening BNI di atas.', 4),
('ewallet', 'Dana', NULL, '081234567890', 'Nama Pemilik', 'Kirim ke nomor Dana di atas.', 5),
('ewallet', 'OVO', NULL, '081234567890', 'Nama Pemilik', 'Kirim ke nomor OVO di atas.', 6),
('ewallet', 'GoPay', NULL, '081234567890', 'Nama Pemilik', 'Kirim ke nomor GoPay di atas.', 7),
('ewallet', 'ShopeePay', NULL, '081234567890', 'Nama Pemilik', 'Kirim ke akun ShopeePay di atas.', 8);

-- Contoh kartu RFID
INSERT INTO `users` (`rfid_uid`, `nama`, `email`, `saldo`) VALUES
('A1B2C3D4', 'Budi Santoso', 'budi@example.com', 50000),
('E5F6G7H8', 'Siti Rahayu', 'siti@example.com', 25000),
('I9J0K1L2', 'Ahmad Fauzi', 'ahmad@example.com', 0);

-- Contoh device
INSERT INTO `devices` (`device_id`, `nama`, `lokasi`, `tipe`, `harga_tap`) VALUES
('DEV-001', 'Reader Kasir Utama', 'Kasir 1', 'payment', 10000),
('DEV-002', 'Reader Top-up', 'Meja Admin', 'topup', 0);

-- ── Tabel Suppliers (Pemasok Barang Toko Kelontong) ─
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_supplier` VARCHAR(100) NOT NULL,
    `kontak_person` VARCHAR(100) DEFAULT NULL,
    `telepon` VARCHAR(20) DEFAULT NULL,
    `alamat` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Tabel Products (Toko Kelontong) ───────────
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_produk` VARCHAR(100) NOT NULL,
    `sku_code` VARCHAR(50) DEFAULT NULL UNIQUE,
    `harga_beli` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Harga modal dari supplier',
    `harga_jual` DECIMAL(12,2) NOT NULL,
    `stok` INT DEFAULT 0,
    `kategori` VARCHAR(50) DEFAULT NULL,
    `supplier_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    INDEX `idx_sku` (`sku_code`)
) ENGINE=InnoDB;

-- ── Tabel Purchase Orders (Restok Barang) ──────
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `supplier_id` INT NOT NULL,
    `invoice_no` VARCHAR(50) NOT NULL COMMENT 'Nomor nota dari supplier',
    `total_bayar` DECIMAL(12,2) NOT NULL,
    `status` ENUM('ordered', 'received', 'cancelled') DEFAULT 'received',
    `received_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
    INDEX `idx_supplier` (`supplier_id`)
) ENGINE=InnoDB;

-- ── Tabel Purchase Order Details (Detail Restok)
CREATE TABLE IF NOT EXISTS `purchase_order_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `qty` INT NOT NULL,
    `harga_beli` DECIMAL(12,2) NOT NULL COMMENT 'Harga modal restok per unit',
    `subtotal` DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    INDEX `idx_purchase_order_id` (`purchase_order_id`)
) ENGINE=InnoDB;

-- ── Tabel Transaksi Toko ──────────────────────
CREATE TABLE IF NOT EXISTS `transaksi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` VARCHAR(100) NOT NULL UNIQUE,
    `product_id` INT DEFAULT NULL,
    `total_harga` DECIMAL(12,2) NOT NULL,
    `metode_bayar` VARCHAR(50) NOT NULL COMMENT 'tunai, rfid, qris',
    `rfid_uid` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('pending','success','failed') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_rfid_uid` (`rfid_uid`)
) ENGINE=InnoDB;

-- ── Tabel Transaksi Detail Toko ───────────────
CREATE TABLE IF NOT EXISTS `transaksi_detail` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaksi_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `qty` INT NOT NULL DEFAULT 1,
    `harga_satuan` DECIMAL(12,2) NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    INDEX `idx_transaksi_id` (`transaksi_id`)
) ENGINE=InnoDB;

-- ══════════════════════════════════════════════
-- SEEDER DATA TAMBAHAN (PRO RETAIL)
-- ══════════════════════════════════════════════

-- Contoh data supplier
INSERT INTO `suppliers` (`id`, `nama_supplier`, `kontak_person`, `telepon`, `alamat`) VALUES
(1, 'PT Indofood Sukses Makmur', 'Budi Sales', '0811223344', 'Kawasan Industri Candi, Semarang'),
(2, 'CV Maju Jaya Sembako', 'Siti Logistik', '0855667788', 'Jl. Gajah Mada No. 45, Pekalongan'),
(3, 'Unilever Distributor Jateng', 'Andi Supply', '0899001122', 'Kawasan Industri Terboyo, Semarang');

-- Contoh data produk (Toko Kelontong)
INSERT INTO `products` (`id`, `nama_produk`, `sku_code`, `harga_beli`, `harga_jual`, `stok`, `kategori`, `supplier_id`) VALUES
(1, 'Mie Instan Goreng', 'MIE-001', 2800.00, 3500.00, 100, 'Makanan', 1),
(2, 'Air Mineral 600ml', 'MIN-001', 1800.00, 3000.00, 50, 'Minuman', 2),
(3, 'Sabun Mandi Cair', 'SAB-001', 11500.00, 15000.00, 20, 'Kebutuhan Rumah', 3),
(4, 'Kopi Instan Sachet', 'KOP-001', 1400.00, 2000.00, 200, 'Minuman', 1);

-- Contoh Nota/Invoice pembelian masuk (restok barang) dari Supplier
INSERT INTO `purchase_orders` (`id`, `supplier_id`, `invoice_no`, `total_bayar`, `status`, `received_at`) VALUES
(1, 1, 'INV/IDF-2026/001', 560000.00, 'received', NOW());

INSERT INTO `purchase_order_details` (`purchase_order_id`, `product_id`, `qty`, `harga_beli`, `subtotal`) VALUES
(1, 1, 200, 2800.00, 560000.00);
