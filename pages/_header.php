<?php
/**
 * Layout Header - Sidebar + Content Start
 * Include di awal setiap halaman admin
 */
require_once __DIR__ . '/../config.php';
requireLogin();

// Tentukan halaman aktif dari nama file
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Hitung jumlah topup pending untuk badge
$pendingCount = 0;
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT COUNT(*) FROM topup_requests WHERE status = 'pending'");
    $pendingCount = $stmt->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <style>
        :root {
            <?php
            $brandColor = getSetting('brand_color');
            $brandGradient = getSetting('brand_gradient');
            if ($brandColor) {
                echo "--accent-primary: " . sanitize($brandColor) . ";\n";
                echo "            --accent-primary-glow: " . sanitize($brandColor) . "33;\n";
            }
            if ($brandGradient) {
                echo "            --accent-gradient: " . htmlspecialchars_decode(sanitize($brandGradient)) . ";\n";
            }
            ?>
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="s-logo">💳</div>
                <div>
                    <div class="s-title"><?= APP_NAME ?></div>
                    <div class="s-subtitle">v<?= APP_VERSION ?></div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Menu Utama</div>
                    <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                        <span class="nav-icon">📊</span> Dashboard
                    </a>
                    <a href="rfid.php" class="nav-link <?= $currentPage === 'rfid' ? 'active' : '' ?>">
                        <span class="nav-icon">💳</span> Kartu RFID
                    </a>
                    <a href="transactions.php" class="nav-link <?= $currentPage === 'transactions' ? 'active' : '' ?>">
                        <span class="nav-icon">📋</span> Transaksi
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Pembayaran</div>
                    <a href="payment_methods.php" class="nav-link <?= $currentPage === 'payment_methods' ? 'active' : '' ?>">
                        <span class="nav-icon">🏦</span> Metode Pembayaran
                    </a>
                    <a href="topup_requests.php" class="nav-link <?= $currentPage === 'topup_requests' ? 'active' : '' ?>">
                        <span class="nav-icon">📥</span> Request Top-up
                        <?php if ($pendingCount > 0): ?>
                            <span class="nav-badge"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Sistem</div>
                    <a href="settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                        <span class="nav-icon">⚙️</span> Pengaturan
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="admin-info">
                    <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_nama'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <div class="admin-name"><?= sanitize($_SESSION['admin_nama'] ?? 'Admin') ?></div>
                        <div class="admin-role"><?= ucfirst($_SESSION['admin_role'] ?? 'admin') ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <button class="sidebar-toggle" onclick="App.toggleSidebar()">☰</button>
                    <h2><?= $pageTitle ?? 'Dashboard' ?></h2>
                </div>
                <div style="display:flex;align-items:center;gap:1rem">
                    <span style="font-size:0.8rem;color:var(--text-muted)"><?= date('d M Y, H:i') ?> WIB</span>
                    <a href="../api/auth.php?action=logout" class="btn btn-ghost btn-sm">🚪 Logout</a>
                </div>
            </div>
            <div class="content-body">
