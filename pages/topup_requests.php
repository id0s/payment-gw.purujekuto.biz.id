<?php
/**
 * RFID Payment Gateway - Halaman Request Top-up
 */
$pageTitle = 'Request Top-up';
require_once '_header.php';

$pdo = getDB();

$filterStatus = $_GET['status'] ?? 'pending';

$sql = "SELECT tr.*, u.nama, u.rfid_uid, u.saldo,
        pm.nama as pm_nama, pm.tipe as pm_tipe, pm.nomor_akun as pm_nomor,
        a.nama as approved_nama
        FROM topup_requests tr
        JOIN users u ON tr.user_id = u.id
        LEFT JOIN payment_methods pm ON tr.payment_method_id = pm.id
        LEFT JOIN admins a ON tr.approved_by = a.id
        WHERE 1=1";
$params = [];

if ($filterStatus) {
    $sql .= " AND tr.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY tr.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();
?>

<!-- Filter Tabs -->
<div style="display:flex;gap:0.5rem;margin-bottom:1.25rem">
    <a href="?status=pending" class="btn <?= $filterStatus === 'pending' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
        ⏳ Pending
    </a>
    <a href="?status=approved" class="btn <?= $filterStatus === 'approved' ? 'btn-success' : 'btn-ghost' ?> btn-sm">
        ✅ Approved
    </a>
    <a href="?status=rejected" class="btn <?= $filterStatus === 'rejected' ? 'btn-danger' : 'btn-ghost' ?> btn-sm">
        ❌ Rejected
    </a>
    <a href="?status=" class="btn <?= $filterStatus === '' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
        📋 Semua
    </a>
</div>

<div class="card">
    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <div class="empty-icon">📥</div>
            <h3>Tidak ada request <?= $filterStatus ?></h3>
            <p>Request top-up dari user akan muncul di sini.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Waktu</th>
                        <th>Nama / UID</th>
                        <th>Jumlah</th>
                        <th>Metode</th>
                        <th>Catatan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td style="white-space:nowrap;font-size:0.8rem">
                            <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
                        </td>
                        <td>
                            <strong style="color:var(--text-primary)"><?= sanitize($r['nama']) ?></strong><br>
                            <small class="text-muted font-mono"><?= sanitize($r['rfid_uid']) ?></small>
                            <br><small class="text-muted">Saldo: <?= formatRupiah($r['saldo']) ?></small>
                        </td>
                        <td style="font-weight:700;color:var(--success);font-size:1rem">
                            <?= formatRupiah($r['jumlah']) ?>
                        </td>
                        <td>
                            <?php if ($r['pm_nama']): ?>
                                <span class="badge <?= $r['pm_tipe'] === 'bank' ? 'badge-info' : 'badge-success' ?>">
                                    <?= sanitize($r['pm_nama']) ?>
                                </span>
                                <br><small class="text-muted"><?= sanitize($r['pm_nomor'] ?? '') ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem;max-width:150px"><?= sanitize($r['catatan'] ?? '-') ?></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <span class="badge badge-warning">⏳ Pending</span>
                            <?php elseif ($r['status'] === 'approved'): ?>
                                <span class="badge badge-success">✅ Approved</span>
                                <?php if ($r['approved_nama']): ?>
                                    <br><small class="text-muted">oleh <?= sanitize($r['approved_nama']) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-danger">❌ Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                            <div style="display:flex;gap:0.3rem">
                                <button class="btn btn-success btn-sm" onclick="approveRequest(<?= $r['id'] ?>)">
                                    ✅ Approve
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="rejectRequest(<?= $r['id'] ?>)">
                                    ❌
                                </button>
                            </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$extraJS = <<<'JS'
async function approveRequest(id) {
    if (await App.confirm('Approve top-up request ini? Saldo user akan bertambah.')) {
        const result = await App.request('../api/topup.php', { body: 'action=approve&id=' + id });
        App.toast(result.message, result.status === 'success' ? 'success' : 'error');
        if (result.status === 'success') setTimeout(() => location.reload(), 1000);
    }
}

async function rejectRequest(id) {
    if (await App.confirm('Tolak request top-up ini?')) {
        const result = await App.request('../api/topup.php', { body: 'action=reject&id=' + id });
        App.toast(result.message, result.status === 'success' ? 'success' : 'error');
        if (result.status === 'success') setTimeout(() => location.reload(), 1000);
    }
}
JS;

require_once '_footer.php';
?>
