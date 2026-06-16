<?php
/**
 * RFID Payment Gateway - Halaman Metode Pembayaran
 */
$pageTitle = 'Metode Pembayaran';
require_once '_header.php';

$pdo = getDB();
$methods = $pdo->query("SELECT * FROM payment_methods ORDER BY urutan ASC, nama ASC")->fetchAll();

// Group by tipe
$grouped = ['bank' => [], 'ewallet' => [], 'qris' => [], 'other' => []];
foreach ($methods as $m) {
    $grouped[$m['tipe']][] = $m;
}
?>

<!-- Toolbar -->
<div class="toolbar">
    <div style="flex:1">
        <p class="text-muted" style="font-size:0.85rem">
            Kelola rekening bank, e-wallet, dan metode pembayaran lainnya. User akan transfer ke rekening/akun ini untuk top-up saldo RFID.
        </p>
    </div>
    <button class="btn btn-primary" onclick="App.openModal('modal-add-pm')">
        ➕ Tambah Metode
    </button>
</div>

<!-- Bank Transfer -->
<?php if (!empty($grouped['bank'])): ?>
<h3 style="font-size:0.9rem;color:var(--text-muted);margin-bottom:0.75rem;text-transform:uppercase;letter-spacing:1px">🏦 Transfer Bank</h3>
<div class="pm-grid mb-3">
    <?php foreach ($grouped['bank'] as $m): ?>
    <div class="pm-card">
        <div class="pm-status">
            <?php if ($m['is_active']): ?>
                <span class="badge badge-success">● Aktif</span>
            <?php else: ?>
                <span class="badge badge-default">● Nonaktif</span>
            <?php endif; ?>
        </div>
        <div class="pm-header">
            <div class="pm-icon bank"><?= strtoupper(substr($m['nama'], 0, 2)) ?></div>
            <div>
                <div class="pm-name"><?= sanitize($m['nama']) ?></div>
                <div class="pm-type">Bank Transfer <?= $m['kode'] ? '(Kode: '.$m['kode'].')' : '' ?></div>
            </div>
        </div>
        <div class="pm-detail"><strong><?= sanitize($m['nomor_akun']) ?></strong></div>
        <div class="pm-detail">a.n. <?= sanitize($m['nama_pemilik']) ?></div>
        <div class="pm-actions">
            <button class="btn btn-ghost btn-sm" onclick='showEditPM(<?= json_encode($m) ?>)'>✏️ Edit</button>
            <button class="btn btn-ghost btn-sm" onclick="togglePM(<?= $m['id'] ?>)">
                <?= $m['is_active'] ? '🔴 Nonaktifkan' : '🟢 Aktifkan' ?>
            </button>
            <button class="btn btn-ghost btn-sm text-danger" onclick="deletePM(<?= $m['id'] ?>, '<?= sanitize($m['nama']) ?>')">🗑️</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- E-Wallet -->
<?php if (!empty($grouped['ewallet'])): ?>
<h3 style="font-size:0.9rem;color:var(--text-muted);margin-bottom:0.75rem;text-transform:uppercase;letter-spacing:1px">📱 E-Wallet</h3>
<div class="pm-grid mb-3">
    <?php foreach ($grouped['ewallet'] as $m): ?>
    <div class="pm-card">
        <div class="pm-status">
            <?php if ($m['is_active']): ?>
                <span class="badge badge-success">● Aktif</span>
            <?php else: ?>
                <span class="badge badge-default">● Nonaktif</span>
            <?php endif; ?>
        </div>
        <div class="pm-header">
            <div class="pm-icon ewallet"><?= strtoupper(substr($m['nama'], 0, 2)) ?></div>
            <div>
                <div class="pm-name"><?= sanitize($m['nama']) ?></div>
                <div class="pm-type">E-Wallet</div>
            </div>
        </div>
        <div class="pm-detail"><strong><?= sanitize($m['nomor_akun']) ?></strong></div>
        <div class="pm-detail">a.n. <?= sanitize($m['nama_pemilik']) ?></div>
        <div class="pm-actions">
            <button class="btn btn-ghost btn-sm" onclick='showEditPM(<?= json_encode($m) ?>)'>✏️ Edit</button>
            <button class="btn btn-ghost btn-sm" onclick="togglePM(<?= $m['id'] ?>)">
                <?= $m['is_active'] ? '🔴 Nonaktifkan' : '🟢 Aktifkan' ?>
            </button>
            <button class="btn btn-ghost btn-sm text-danger" onclick="deletePM(<?= $m['id'] ?>, '<?= sanitize($m['nama']) ?>')">🗑️</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- QRIS & Other -->
<?php foreach (['qris' => '📷 QRIS', 'other' => '📦 Lainnya'] as $tipe => $label): ?>
<?php if (!empty($grouped[$tipe])): ?>
<h3 style="font-size:0.9rem;color:var(--text-muted);margin-bottom:0.75rem;text-transform:uppercase;letter-spacing:1px"><?= $label ?></h3>
<div class="pm-grid mb-3">
    <?php foreach ($grouped[$tipe] as $m): ?>
    <div class="pm-card">
        <div class="pm-status">
            <span class="badge <?= $m['is_active'] ? 'badge-success' : 'badge-default' ?>">● <?= $m['is_active'] ? 'Aktif' : 'Nonaktif' ?></span>
        </div>
        <div class="pm-header">
            <div class="pm-icon <?= $tipe === 'qris' ? 'ewallet' : 'bank' ?>"><?= strtoupper(substr($m['nama'], 0, 2)) ?></div>
            <div>
                <div class="pm-name"><?= sanitize($m['nama']) ?></div>
                <div class="pm-type"><?= ucfirst($tipe) ?></div>
            </div>
        </div>
        <div class="pm-detail"><strong><?= sanitize($m['nomor_akun']) ?></strong></div>
        <div class="pm-detail">a.n. <?= sanitize($m['nama_pemilik']) ?></div>
        <div class="pm-actions">
            <button class="btn btn-ghost btn-sm" onclick='showEditPM(<?= json_encode($m) ?>)'>✏️ Edit</button>
            <button class="btn btn-ghost btn-sm" onclick="togglePM(<?= $m['id'] ?>)">
                <?= $m['is_active'] ? '🔴 Nonaktifkan' : '🟢 Aktifkan' ?>
            </button>
            <button class="btn btn-ghost btn-sm text-danger" onclick="deletePM(<?= $m['id'] ?>, '<?= sanitize($m['nama']) ?>')">🗑️</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php if (empty($methods)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">🏦</div>
        <h3>Belum ada metode pembayaran</h3>
        <p>Tambahkan rekening bank atau e-wallet agar user bisa transfer untuk top-up saldo RFID.</p>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Tambah Metode Pembayaran -->
<div id="modal-add-pm" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>➕ Tambah Metode Pembayaran</h3>
            <button class="modal-close" onclick="App.closeModal('modal-add-pm')">✕</button>
        </div>
        <form id="form-add-pm" onsubmit="createPM(event)">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tipe *</label>
                        <select name="tipe" class="form-control" required>
                            <option value="bank">Bank Transfer</option>
                            <option value="ewallet">E-Wallet</option>
                            <option value="qris">QRIS</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama *</label>
                        <input type="text" name="nama" class="form-control" placeholder="BCA, Dana, OVO..." required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Bank</label>
                    <input type="text" name="kode" class="form-control" placeholder="014 (BCA), 008 (Mandiri), dll">
                    <small class="text-muted">Opsional, hanya untuk bank transfer</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nomor Rekening / Akun *</label>
                        <input type="text" name="nomor_akun" class="form-control" placeholder="1234567890" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Pemilik *</label>
                        <input type="text" name="nama_pemilik" class="form-control" placeholder="Nama di rekening" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Instruksi Transfer</label>
                    <textarea name="instruksi" class="form-control" placeholder="Instruksi untuk user saat transfer..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="App.closeModal('modal-add-pm')">Batal</button>
                <button type="submit" class="btn btn-primary">💾 Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Metode Pembayaran -->
<div id="modal-edit-pm" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>✏️ Edit Metode Pembayaran</h3>
            <button class="modal-close" onclick="App.closeModal('modal-edit-pm')">✕</button>
        </div>
        <form id="form-edit-pm" onsubmit="updatePM(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="epm-id">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tipe *</label>
                        <select name="tipe" id="epm-tipe" class="form-control" required>
                            <option value="bank">Bank Transfer</option>
                            <option value="ewallet">E-Wallet</option>
                            <option value="qris">QRIS</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama *</label>
                        <input type="text" name="nama" id="epm-nama" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Bank</label>
                    <input type="text" name="kode" id="epm-kode" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nomor Rekening / Akun *</label>
                        <input type="text" name="nomor_akun" id="epm-nomor" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Pemilik *</label>
                        <input type="text" name="nama_pemilik" id="epm-pemilik" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Instruksi Transfer</label>
                    <textarea name="instruksi" id="epm-instruksi" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="App.closeModal('modal-edit-pm')">Batal</button>
                <button type="submit" class="btn btn-primary">💾 Update</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraJS = <<<'JS'
async function createPM(e) {
    e.preventDefault();
    const data = App.formData('form-add-pm');
    const result = await App.request('../api/payment_methods.php?action=create', { body: data });
    if (result.status === 'success') {
        App.toast(result.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        App.toast(result.message, 'error');
    }
}

function showEditPM(pm) {
    document.getElementById('epm-id').value = pm.id;
    document.getElementById('epm-tipe').value = pm.tipe;
    document.getElementById('epm-nama').value = pm.nama;
    document.getElementById('epm-kode').value = pm.kode || '';
    document.getElementById('epm-nomor').value = pm.nomor_akun;
    document.getElementById('epm-pemilik').value = pm.nama_pemilik;
    document.getElementById('epm-instruksi').value = pm.instruksi || '';
    App.openModal('modal-edit-pm');
}

async function updatePM(e) {
    e.preventDefault();
    const data = App.formData('form-edit-pm');
    const result = await App.request('../api/payment_methods.php?action=update', { body: data });
    if (result.status === 'success') {
        App.toast(result.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        App.toast(result.message, 'error');
    }
}

async function togglePM(id) {
    const result = await App.request('../api/payment_methods.php?action=toggle', { body: 'id=' + id });
    if (result.status === 'success') {
        App.toast('Status berhasil diubah', 'success');
        setTimeout(() => location.reload(), 800);
    }
}

async function deletePM(id, nama) {
    if (await App.confirm('Hapus metode pembayaran "' + nama + '"?')) {
        const result = await App.request('../api/payment_methods.php?action=delete', { body: 'id=' + id });
        App.toast(result.message, result.status === 'success' ? 'success' : 'error');
        if (result.status === 'success') setTimeout(() => location.reload(), 1000);
    }
}
JS;

require_once '_footer.php';
?>
