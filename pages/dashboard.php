<?php
/**
 * RFID Payment Gateway - Dashboard
 */
$pageTitle = 'Dashboard';
require_once '_header.php';

$pdo = getDB();

// Stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$totalSaldo = $pdo->query("SELECT COALESCE(SUM(saldo), 0) FROM users WHERE status = 'active'")->fetchColumn();
$pendingTopups = $pdo->query("SELECT COUNT(*) FROM topup_requests WHERE status = 'pending'")->fetchColumn();

// Transaksi hari ini
$todayTopup = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) FROM transactions WHERE jenis = 'topup' AND status = 'success' AND DATE(created_at) = CURDATE()")->fetchColumn();
$todayPayment = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) FROM transactions WHERE jenis = 'payment' AND status = 'success' AND DATE(created_at) = CURDATE()")->fetchColumn();
$todayTrxCount = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'success' AND DATE(created_at) = CURDATE()")->fetchColumn();

// Transaksi terbaru
$recentTrx = $pdo->query("
    SELECT t.*, u.nama, u.rfid_uid 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.created_at DESC 
    LIMIT 10
")->fetchAll();

// Kartu dengan saldo rendah
$lowBalance = $pdo->query("
    SELECT * FROM users 
    WHERE status = 'active' AND saldo < 10000 
    ORDER BY saldo ASC 
    LIMIT 5
")->fetchAll();
?>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">💳</div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label">Total Kartu RFID (<?= $activeUsers ?> aktif)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">💰</div>
        <div class="stat-value"><?= formatRupiah($totalSaldo) ?></div>
        <div class="stat-label">Total Saldo Beredar</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">📈</div>
        <div class="stat-value"><?= formatRupiah($todayTopup) ?></div>
        <div class="stat-label">Top-up Hari Ini</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">📉</div>
        <div class="stat-value"><?= formatRupiah($todayPayment) ?></div>
        <div class="stat-label">Pembayaran Hari Ini</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem">
    <!-- Transaksi Terbaru -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📋 Transaksi Terbaru</h3>
            <a href="transactions.php" class="btn btn-ghost btn-sm">Lihat Semua →</a>
        </div>

        <?php if (empty($recentTrx)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>Belum ada transaksi</h3>
                <p>Transaksi akan muncul di sini setelah ada aktivitas top-up atau pembayaran.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Nama</th>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTrx as $trx): ?>
                        <tr>
                            <td style="white-space:nowrap"><?= date('d/m H:i', strtotime($trx['created_at'])) ?></td>
                            <td>
                                <strong style="color:var(--text-primary)"><?= sanitize($trx['nama']) ?></strong><br>
                                <small class="text-muted font-mono"><?= sanitize($trx['rfid_uid']) ?></small>
                            </td>
                            <td>
                                <?php if ($trx['jenis'] === 'topup'): ?>
                                    <span class="badge badge-success">↑ Top-up</span>
                                <?php elseif ($trx['jenis'] === 'payment'): ?>
                                    <span class="badge badge-info">↓ Payment</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">↩ Refund</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:600;color:var(--text-primary)"><?= formatRupiah($trx['jumlah']) ?></td>
                            <td>
                                <?php
                                $statusBadge = [
                                    'success' => 'badge-success',
                                    'pending' => 'badge-warning',
                                    'failed' => 'badge-danger',
                                    'expired' => 'badge-default',
                                ];
                                $badge = $statusBadge[$trx['status']] ?? 'badge-default';
                                ?>
                                <span class="badge <?= $badge ?>"><?= ucfirst($trx['status']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Info -->
    <div>
        <!-- Pending Top-ups -->
        <?php if ($pendingTopups > 0): ?>
        <div class="card mb-2" style="border-color:rgba(245,158,11,0.3)">
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem">
                <span style="font-size:1.5rem">⏳</span>
                <div>
                    <div style="font-weight:700;font-size:1.2rem"><?= $pendingTopups ?></div>
                    <div class="text-muted" style="font-size:0.8rem">Request Top-up Pending</div>
                </div>
            </div>
            <a href="topup_requests.php" class="btn btn-warning btn-sm btn-block">Review Sekarang</a>
        </div>
        <?php endif; ?>

        <!-- Saldo Rendah -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">⚠️ Saldo Rendah</h3>
            </div>
            <?php if (empty($lowBalance)): ?>
                <p class="text-muted" style="font-size:0.85rem">Semua kartu memiliki saldo cukup 👍</p>
            <?php else: ?>
                <?php foreach ($lowBalance as $user): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--border-color)">
                    <div>
                        <div style="font-weight:600;font-size:0.85rem"><?= sanitize($user['nama']) ?></div>
                        <div class="text-muted font-mono" style="font-size:0.75rem"><?= sanitize($user['rfid_uid']) ?></div>
                    </div>
                    <span class="badge badge-danger"><?= formatRupiah($user['saldo']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Quick Stats -->
        <div class="card mt-2">
            <div class="card-header">
                <h3 class="card-title">📊 Ringkasan Hari Ini</h3>
            </div>
            <div style="font-size:0.85rem">
                <div class="flex-between mb-1">
                    <span class="text-muted">Total Transaksi</span>
                    <strong><?= $todayTrxCount ?> trx</strong>
                </div>
                <div class="flex-between mb-1">
                    <span class="text-muted">Masuk (Top-up)</span>
                    <strong class="text-success">+<?= formatRupiah($todayTopup) ?></strong>
                </div>
                <div class="flex-between">
                    <span class="text-muted">Keluar (Payment)</span>
                    <strong class="text-danger">-<?= formatRupiah($todayPayment) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '_footer.php'; ?>
