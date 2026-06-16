CREATE DATABASE IF NOT EXISTS `rfid_payment_saas`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `rfid_payment_saas`;

-- ── 1. Tabel Tenants (Merchants/Toko) ──────────
CREATE TABLE IF NOT EXISTS `tenants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Subdomain/Slug tenant, misal: warung-fitri',
    `nama_bisnis` VARCHAR(100) NOT NULL COMMENT 'Nama Toko/Bisnis',
    `alamat` TEXT DEFAULT NULL,
    `telepon` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('active', 'suspended', 'trial') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB;

-- ── 2. Tabel Users (Milik Tenant) ────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `rfid_uid` VARCHAR(50) NOT NULL COMMENT 'UID dari kartu RFID',
    `nama` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `telepon` VARCHAR(20) DEFAULT NULL,
    `saldo` DECIMAL(12,2) DEFAULT 0.00,
    `pin` VARCHAR(255) DEFAULT NULL COMMENT 'Hashed PIN untuk keamanan',
    `status` ENUM('active','blocked','inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uid_per_tenant` (`tenant_id`, `rfid_uid`),
    INDEX `idx_rfid_uid` (`rfid_uid`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- ── 3. Tabel Payment Methods (Metode Pembayaran per Tenant)
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `tipe` ENUM('bank','ewallet','qris','other') NOT NULL,
    `nama` VARCHAR(100) NOT NULL COMMENT 'Nama bank/ewallet (BCA, Dana, dll)',
    `kode` VARCHAR(20) DEFAULT NULL,
    `nomor_akun` VARCHAR(100) NOT NULL,
    `nama_pemilik` VARCHAR(100) NOT NULL,
    `icon` VARCHAR(50) DEFAULT NULL,
    `instruksi` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `urutan` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_tenant_active` (`tenant_id`, `is_active`)
) ENGINE=InnoDB;

-- ── 4. Tabel Top-up Requests (Request per Tenant) ──
CREATE TABLE IF NOT EXISTS `topup_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `payment_method_id` INT DEFAULT NULL,
    `jumlah` DECIMAL(12,2) NOT NULL,
    `bukti_transfer` VARCHAR(255) DEFAULT NULL,
    `catatan` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
    `approved_by` INT DEFAULT NULL,
    `approved_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL,
    INDEX `idx_tenant_status` (`tenant_id`, `status`)
) ENGINE=InnoDB;

-- ── 5. Tabel Devices (Hardware per Tenant) ─────────
CREATE TABLE IF NOT EXISTS `devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `device_id` VARCHAR(50) NOT NULL UNIQUE COMMENT 'ID unik alat (hardware token)',
    `nama` VARCHAR(100) NOT NULL,
    `lokasi` VARCHAR(100) DEFAULT NULL,
    `tipe` ENUM('payment','topup','checkin') DEFAULT 'payment',
    `harga_tap` DECIMAL(12,2) DEFAULT 10000.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_seen` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_tenant_device` (`tenant_id`, `device_id`)
) ENGINE=InnoDB;

-- ── 6. Tabel RFID Logs (Log per Tenant) ────────────
CREATE TABLE IF NOT EXISTS `rfid_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `rfid_uid` VARCHAR(50) NOT NULL,
    `aksi` ENUM('tap_pay','tap_check','tap_topup','tap_unknown','register') NOT NULL,
    `device_id` VARCHAR(50) DEFAULT NULL,
    `result` VARCHAR(50) DEFAULT NULL,
    `detail` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_tenant_uid_time` (`tenant_id`, `rfid_uid`, `created_at`)
) ENGINE=InnoDB;

-- ── 7. Tabel Admin (Staf/Pemilik per Tenant) ───────
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT DEFAULT NULL COMMENT 'NULL untuk Superadmin platform, INT untuk Admin Tenant',
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `role` ENUM('superadmin','admin','operator') DEFAULT 'operator',
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `username_per_tenant` (`tenant_id`, `username`)
) ENGINE=InnoDB;

-- ── 8. Tabel Settings (Pengaturan per Tenant) ──────
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `key` VARCHAR(50) NOT NULL,
    `value` TEXT DEFAULT NULL,
    `label` VARCHAR(100) DEFAULT NULL,
    `type` ENUM('text','number','boolean','textarea') DEFAULT 'text',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `key_per_tenant` (`tenant_id`, `key`)
) ENGINE=InnoDB;

-- ── 9. Tabel Transaksi Gateway Pusat ──────────────
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `order_id` VARCHAR(100) UNIQUE,
    `jenis` ENUM('topup','payment','refund') NOT NULL,
    `jumlah` DECIMAL(12,2) NOT NULL,
    `metode_bayar` VARCHAR(50) DEFAULT NULL,
    `gateway` VARCHAR(50) DEFAULT NULL,
    `gateway_ref` VARCHAR(200) DEFAULT NULL,
    `status` ENUM('pending','success','failed','expired','refunded') DEFAULT 'pending',
    `keterangan` VARCHAR(255) DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `raw_response` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_tenant_order` (`tenant_id`, `order_id`),
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_tenant_user_jenis` (`tenant_id`, `user_id`, `jenis`)
) ENGINE=InnoDB;

-- ── 10. Tabel Suppliers (Pemasok per Tenant) ───────
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `nama_supplier` VARCHAR(100) NOT NULL,
    `kontak_person` VARCHAR(100) DEFAULT NULL,
    `telepon` VARCHAR(20) DEFAULT NULL,
    `alamat` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_tenant_supplier` (`tenant_id`)
) ENGINE=InnoDB;

-- ── 11. Tabel Products (Toko Kelontong POS per Tenant)
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `nama_produk` VARCHAR(100) NOT NULL,
    `sku_code` VARCHAR(50) DEFAULT NULL,
    `harga_beli` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Harga modal dari supplier',
    `harga_jual` DECIMAL(12,2) NOT NULL,
    `stok` INT DEFAULT 0,
    `kategori` VARCHAR(50) DEFAULT NULL,
    `supplier_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `sku_per_tenant` (`tenant_id`, `sku_code`),
    INDEX `idx_tenant_sku` (`tenant_id`, `sku_code`)
) ENGINE=InnoDB;

-- ── 12. Tabel Purchase Orders (Restok per Tenant) ──
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `supplier_id` INT NOT NULL,
    `invoice_no` VARCHAR(50) NOT NULL COMMENT 'Nomor nota supplier',
    `total_bayar` DECIMAL(12,2) NOT NULL,
    `status` ENUM('ordered', 'received', 'cancelled') DEFAULT 'received',
    `received_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
    INDEX `idx_tenant_supplier` (`tenant_id`, `supplier_id`)
) ENGINE=InnoDB;

-- ── 13. Tabel Purchase Order Details (Detail Restok)
CREATE TABLE IF NOT EXISTS `purchase_order_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `qty` INT NOT NULL,
    `harga_beli` DECIMAL(12,2) NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ── 14. Tabel Transaksi Toko per Tenant ────────────
CREATE TABLE IF NOT EXISTS `transaksi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `order_id` VARCHAR(100) NOT NULL UNIQUE,
    `product_id` INT DEFAULT NULL,
    `total_harga` DECIMAL(12,2) NOT NULL,
    `metode_bayar` VARCHAR(50) NOT NULL COMMENT 'tunai, rfid, qris',
    `rfid_uid` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('pending','success','failed') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
    INDEX `idx_tenant_order` (`tenant_id`, `order_id`),
    INDEX `idx_tenant_rfid` (`tenant_id`, `rfid_uid`)
) ENGINE=InnoDB;

-- ── 15. Tabel Transaksi Detail Toko ──────────────
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
-- DATA SEEDER / DEFAULT VALUE UNTUK SAAS
-- ══════════════════════════════════════════════

-- 1. Tambah Tenant Contoh
INSERT INTO `tenants` (`id`, `slug`, `nama_bisnis`, `alamat`, `telepon`, `status`) VALUES
(1, 'fitri-lopet', 'Warung Fitri Lopet Celluler', 'Jl. Perintis Kemerdekaan No. 29', '081234567890', 'active'),
(2, 'toko-budi', 'Toko Kelontong Budi', 'Jl. Sudirman No. 10', '087766554433', 'active');

-- 2. Superadmin Platform (Pengelola SaaS)
INSERT INTO `admins` (`tenant_id`, `username`, `password`, `nama`, `role`) VALUES
(NULL, 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SaaS Platform Owner', 'superadmin');

-- 3. Admin Tenant Fitri Lopet (Tenant ID: 1)
INSERT INTO `admins` (`tenant_id`, `username`, `password`, `nama`, `role`) VALUES
(1, 'admin_fitri', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Fitri', 'admin');

-- 4. Admin Tenant Toko Budi (Tenant ID: 2)
INSERT INTO `admins` (`tenant_id`, `username`, `password`, `nama`, `role`) VALUES
(2, 'admin_budi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Pemilik', 'admin');

-- 5. Default Settings untuk Tenant 1
INSERT INTO `settings` (`tenant_id`, `key`, `value`, `label`, `type`) VALUES
(1, 'app_name', 'Fitri Lopet Gateway', 'Nama Aplikasi', 'text'),
(1, 'tap_price', '5000', 'Harga per Tap (Rp)', 'number'),
(1, 'min_topup', '10000', 'Minimum Top-up (Rp)', 'number'),
(1, 'max_topup', '1000000', 'Maksimum Top-up (Rp)', 'number'),
(1, 'brand_color', '#6366f1', 'Warna Aksen Brand (HEX)', 'text'),
(1, 'brand_gradient', 'linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa)', 'Gradasi Aksen Brand', 'text');

-- 6. Default Settings untuk Tenant 2
INSERT INTO `settings` (`tenant_id`, `key`, `value`, `label`, `type`) VALUES
(2, 'app_name', 'Budi Pay', 'Nama Aplikasi', 'text'),
(2, 'tap_price', '10000', 'Harga per Tap (Rp)', 'number'),
(2, 'min_topup', '5000', 'Minimum Top-up (Rp)', 'number'),
(2, 'max_topup', '2000000', 'Maksimum Top-up (Rp)', 'number'),
(2, 'brand_color', '#10b981', 'Warna Aksen Brand (HEX)', 'text'),
(2, 'brand_gradient', 'linear-gradient(135deg, #10b981, #059669)', 'Gradasi Aksen Brand', 'text');

-- 7. Contoh Metode Pembayaran Tenant 1
INSERT INTO `payment_methods` (`tenant_id`, `tipe`, `nama`, `nomor_akun`, `nama_pemilik`, `instruksi`, `urutan`) VALUES
(1, 'ewallet', 'Dana Fitri', '081234567890', 'Fitri', 'Kirim ke nomor Dana di atas.', 1);

-- 8. Contoh Kartu RFID Tenant 1
INSERT INTO `users` (`tenant_id`, `rfid_uid`, `nama`, `saldo`) VALUES
(1, 'A1B2C3D4', 'Budi Pelanggan Fitri', 50000.00);

-- 9. Contoh Kartu RFID Tenant 2 (UID yang sama bisa didaftarkan di merchant yang berbeda secara terisolasi)
INSERT INTO `users` (`tenant_id`, `rfid_uid`, `nama`, `saldo`) VALUES
(2, 'A1B2C3D4', 'Budi Pelanggan Toko Budi', 20000.00);

-- 10. Contoh Data Supplier per Tenant
INSERT INTO `suppliers` (`id`, `tenant_id`, `nama_supplier`, `kontak_person`, `telepon`, `alamat`) VALUES
(1, 1, 'PT Indofood Sukses Makmur', 'Budi Sales', '0811223344', 'Kawasan Industri Candi, Semarang'),
(2, 1, 'Unilever Distributor Jateng', 'Andi Supply', '0899001122', 'Kawasan Industri Terboyo, Semarang'),
(3, 2, 'CV Maju Jaya Sembako', 'Siti Logistik', '0855667788', 'Jl. Gajah Mada No. 45, Pekalongan');

-- 11. Contoh Data Produk per Tenant
INSERT INTO `products` (`id`, `tenant_id`, `nama_produk`, `sku_code`, `harga_beli`, `harga_jual`, `stok`, `kategori`, `supplier_id`) VALUES
(1, 1, 'Mie Instan Goreng', 'MIE-001', 2800.00, 3500.00, 100, 'Makanan', 1),
(2, 1, 'Sabun Mandi Cair', 'SAB-001', 11500.00, 15000.00, 20, 'Kebutuhan Rumah', 2),
(3, 2, 'Air Mineral 600ml', 'MIN-001', 1800.00, 3000.00, 50, 'Minuman', 3);

-- 12. Contoh Purchase Orders (Restok Barang) per Tenant
INSERT INTO `purchase_orders` (`id`, `tenant_id`, `supplier_id`, `invoice_no`, `total_bayar`, `status`, `received_at`) VALUES
(1, 1, 1, 'INV/IDF-2026/001', 560000.00, 'received', NOW());

INSERT INTO `purchase_order_details` (`purchase_order_id`, `product_id`, `qty`, `harga_beli`, `subtotal`) VALUES
(1, 1, 200, 2800.00, 560000.00);
