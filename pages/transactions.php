<?php
/**
 * RFID Payment Gateway - Halaman Riwayat Transaksi
 */
$pageTitle = 'Riwayat Transaksi';
require_once '_header.php';

$pdo = getDB();

// Filters
$search = $_GET['search'] ?? '';
$jenis = $_GET['jenis'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$sql = "SELECT t.*, u.nama, u.rfid_uid FROM transactions t JOIN users u ON t.user_id = u.id WHERE 1=1";
$countSql = "SELECT COUNT(*) FROM transactions t JOIN users u ON t.user_id = u.id WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (u.nama LIKE ? OR u.rfid_uid LIKE ? OR t.order_id LIKE ?)";
    $countSql .= " AND (u.nama LIKE ? OR u.rfid_uid LIKE ? OR t.order_id LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($jenis) {
    $sql .= " AND t.jenis = ?";
    $countSql .= " AND t.jenis = ?";
    $params[] = $jenis;
}
if ($status) {
    $sql .= " AND t.status = ?";
    $countSql .= " AND t.status = ?";
    $params[] = $status;
}
if ($dateFrom) {
    $sql .= " AND DATE(t.created_at) >= ?";
    $countSql .= " AND DATE(t.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $sql .= " AND DATE(t.created_at) <= ?";
    $countSql .= " AND DATE(t.created_at) <= ?";
    $params[] = $dateTo;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$sql .= " ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Totals
$totalTopup = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) FROM transactions WHERE jenis='topup' AND status='success'")->fetchColumn();
$totalPayment = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) FROM transactions WHERE jenis='payment' AND status='success'")->fetchColumn();
?>

<!-- Quick Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green">📈</div>
        <div class="stat-value"><?= formatRupiah($totalTopup) ?></div>
        <div class="stat-label">Total Top-up (All Time)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">📉</div>
        <div class="stat-value"><?= formatRupiah($totalPayment) ?></div>
        <div class="stat-label">Total Pembayaran (All Time)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📋</div>
        <div class="stat-value"><?= number_format($total) ?></div>
        <div class="stat-label">Total Transaksi</div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-2">
    <form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end">
        <div style="flex:1;min-width:180px">
            <label class="form-label">Cari</label>
            <input type="text" name="search" class="form-control" placeholder="Nama, UID, Order ID..." value="<?= sanitize($search) ?>">
        </div>
        <div style="width:130px">
            <label class="form-label">Jenis</label>
            <select name="jenis" class="form-control">
                <option value="">Semua</option>
                <option value="topup" <?= $jenis === 'topup' ? 'selected' : '' ?>>Top-up</option>
                <option value="payment" <?= $jenis === 'payment' ? 'selected' : '' ?>>Payment</option>
                <option value="refund" <?= $jenis === 'refund' ? 'selected' : '' ?>>Refund</option>
            </select>
        </div>
        <div style="width:130px">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Semua</option>
                <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>
        <div style="width:150px">
            <label class="form-label">Dari</label>
            <input type="date" name="date_from" class="form-control" value="<?= sanitize($dateFrom) ?>">
        </div>
        <div style="width:150px">
            <label class="form-label">Sampai</label>
            <input type="date" name="date_to" class="form-control" value="<?= sanitize($dateTo) ?>">
        </div>
        <button type="submit" class="btn btn-primary">🔍 Filter</button>
        <a href="transactions.php" class="btn btn-ghost">Reset</a>
    </form>
</div>

<!-- Tabel Transaksi -->
<div class="card">
    <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <h3>Tidak ada transaksi</h3>
            <p>Coba ubah filter pencarian Anda.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Waktu</th>
                        <th>Nama / UID</th>
                        <th>Jenis</th>
                        <th>Jumlah</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Ket.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td>
                            <code style="font-size:0.72rem;color:var(--accent-primary)"><?= sanitize($t['order_id'] ?? '-') ?></code>
                        </td>
                        <td style="white-space:nowrap;font-size:0.8rem">
                            <?= date('d/m/Y', strtotime($t['created_at'])) ?><br>
                            <span class="text-muted"><?= date('H:i:s', strtotime($t['created_at'])) ?></span>
                        </td>
                        <td>
                            <strong style="color:var(--text-primary)"><?= sanitize($t['nama']) ?></strong><br>
                            <small class="text-muted font-mono"><?= sanitize($t['rfid_uid']) ?></small>
                        </td>
                        <td>
                            <?php
                            $jenisMap = [
                                'topup' => ['badge-success', '↑ Top-up'],
                                'payment' => ['badge-info', '↓ Payment'],
                                'refund' => ['badge-warning', '↩ Refund'],
                            ];
                            $j = $jenisMap[$t['jenis']] ?? ['badge-default', $t['jenis']];
                            ?>
                            <span class="badge <?= $j[0] ?>"><?= $j[1] ?></span>
                        </td>
                        <td style="font-weight:700;color:var(--text-primary)">
                            <?= formatRupiah($t['jumlah']) ?>
                        </td>
                        <td style="font-size:0.8rem"><?= sanitize($t['metode_bayar'] ?? '-') ?></td>
                        <td>
                            <?php
                            $statusMap = [
                                'success' => 'badge-success',
                                'pending' => 'badge-warning',
                                'failed' => 'badge-danger',
                                'expired' => 'badge-default',
                                'refunded' => 'badge-purple',
                            ];
                            $sb = $statusMap[$t['status']] ?? 'badge-default';
                            ?>
                            <span class="badge <?= $sb ?>"><?= ucfirst($t['status']) ?></span>
                        </td>
                        <td style="font-size:0.78rem;max-width:150px;overflow:hidden;text-overflow:ellipsis" title="<?= sanitize($t['keterangan'] ?? '') ?>">
                            <?= sanitize($t['keterangan'] ?? '-') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $queryStr = http_build_query(array_filter([
                'search' => $search, 'jenis' => $jenis, 'status' => $status,
                'date_from' => $dateFrom, 'date_to' => $dateTo
            ]));
            ?>
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&<?= $queryStr ?>" class="page-btn">← Prev</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&<?= $queryStr ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&<?= $queryStr ?>" class="page-btn">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '_footer.php'; ?>
