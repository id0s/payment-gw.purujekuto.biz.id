<?php
/**
 * RFID Payment Gateway - Halaman Manajemen Kartu RFID
 */
$pageTitle = 'Kartu RFID';
require_once '_header.php';

$pdo = getDB();

// Ambil semua kartu
$search = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) as total_trx,
        (SELECT MAX(created_at) FROM transactions WHERE user_id = u.id) as last_trx
        FROM users u WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (u.nama LIKE ? OR u.rfid_uid LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($filterStatus) {
    $sql .= " AND u.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!-- Flash message -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= $_SESSION['flash']['type'] ?>">
        <?= $_SESSION['flash']['message'] ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- Toolbar -->
<div class="toolbar">
    <div class="search-box">
        <form method="GET">
            <input type="text" name="search" placeholder="Cari nama, UID, atau email..." value="<?= sanitize($search) ?>">
            <?php if ($filterStatus): ?><input type="hidden" name="status" value="<?= sanitize($filterStatus) ?>"><?php endif; ?>
        </form>
    </div>
    <select class="form-control" style="width:auto" onchange="window.location='?status='+this.value+'&search=<?= urlencode($search) ?>'">
        <option value="">Semua Status</option>
        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="blocked" <?= $filterStatus === 'blocked' ? 'selected' : '' ?>>Blocked</option>
        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
    <button class="btn btn-primary" onclick="App.openModal('modal-register')">
        ➕ Register Kartu
    </button>
</div>

<!-- Tabel Kartu -->
<div class="card">
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <div class="empty-icon">💳</div>
            <h3>Belum ada kartu terdaftar</h3>
            <p>Klik "Register Kartu" untuk mendaftarkan kartu RFID baru.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>Nama</th>
                        <th>Kontak</th>
                        <th>Saldo</th>
                        <th>Status</th>
                        <th>Transaksi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <code style="background:var(--bg-glass);padding:0.2rem 0.5rem;border-radius:4px;font-size:0.8rem;color:var(--accent-primary)">
                                <?= sanitize($u['rfid_uid']) ?>
                            </code>
                        </td>
                        <td><strong style="color:var(--text-primary)"><?= sanitize($u['nama']) ?></strong></td>
                        <td>
                            <?php if ($u['email']): ?>
                                <div style="font-size:0.8rem"><?= sanitize($u['email']) ?></div>
                            <?php endif; ?>
                            <?php if ($u['telepon']): ?>
                                <div style="font-size:0.8rem" class="text-muted"><?= sanitize($u['telepon']) ?></div>
                            <?php endif; ?>
                            <?php if (!$u['email'] && !$u['telepon']): ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color:<?= $u['saldo'] < 10000 ? 'var(--danger)' : 'var(--success)' ?>">
                                <?= formatRupiah($u['saldo']) ?>
                            </strong>
                        </td>
                        <td>
                            <?php
                            $statusMap = [
                                'active' => ['badge-success', '● Active'],
                                'blocked' => ['badge-danger', '● Blocked'],
                                'inactive' => ['badge-default', '● Inactive'],
                            ];
                            $s = $statusMap[$u['status']] ?? ['badge-default', $u['status']];
                            ?>
                            <span class="badge <?= $s[0] ?>"><?= $s[1] ?></span>
                            <br>
                            <?php if ($u['pin'] !== null): ?>
                                <span class="badge badge-success" title="PIN Aktif" style="margin-top:0.25rem; font-size:0.7rem; display:inline-flex; align-items:center; gap:0.2rem;">
                                    🔒 Secure
                                </span>
                            <?php else: ?>
                                <span class="badge badge-warning" title="Belum Atur PIN" style="margin-top:0.25rem; font-size:0.7rem; display:inline-flex; align-items:center; gap:0.2rem;">
                                    🔓 No PIN
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-size:0.8rem"><?= $u['total_trx'] ?> trx</span>
                            <?php if ($u['last_trx']): ?>
                                <br><small class="text-muted"><?= date('d/m/y', strtotime($u['last_trx'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.3rem;flex-wrap:wrap">
                                 <button class="btn btn-success btn-sm" 
                                         onclick="showTopupModal(<?= $u['id'] ?>, '<?= sanitize($u['nama']) ?>', '<?= sanitize($u['rfid_uid']) ?>')">
                                     💰 Top-up
                                 </button>
                                <button class="btn btn-ghost btn-sm" 
                                        onclick="showEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    ✏️
                                </button>
                                <?php if ($u['status'] === 'active'): ?>
                                    <button class="btn btn-ghost btn-sm" onclick="blockCard(<?= $u['id'] ?>)">🚫</button>
                                <?php else: ?>
                                    <button class="btn btn-ghost btn-sm" onclick="activateCard(<?= $u['id'] ?>)">✅</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Register Kartu Baru -->
<div id="modal-register" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>➕ Register Kartu RFID Baru</h3>
            <button class="modal-close" onclick="App.closeModal('modal-register')">✕</button>
        </div>
        <form id="form-register" onsubmit="registerCard(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">UID Kartu RFID *</label>
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <input type="text" name="rfid_uid" id="register_rfid_uid" class="form-control" placeholder="Contoh: A1B2C3D4" required 
                               style="text-transform:uppercase" maxlength="50">
                        <button type="button" class="btn btn-ghost" onclick="App.scanNfc('register_rfid_uid')" style="white-space: nowrap; padding: 0.7rem 1rem;">📱 NFC</button>
                    </div>
                    <small class="text-muted">UID bisa dibaca menggunakan RFID reader atau aplikasi NFC di HP</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Pemilik *</label>
                    <input type="text" name="nama" class="form-control" placeholder="Nama lengkap" required>
                </div>
                <div class="form-group">
                    <label class="form-label">PIN Kartu (6 Digit Angka)</label>
                    <input type="password" name="pin" class="form-control" placeholder="Masukkan 6 digit angka PIN keamanan" maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
                    <small class="text-muted">Opsional. Masukkan 6 digit angka untuk keamanan transaksi tap kartu.</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="email@contoh.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telepon" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="App.closeModal('modal-register')">Batal</button>
                <button type="submit" class="btn btn-primary">💳 Daftarkan Kartu</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Top-up Saldo -->
<div id="modal-topup" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>💰 Top-up Saldo</h3>
            <button class="modal-close" onclick="cancelAdminQrisPayment()">✕</button>
        </div>
        <form id="form-topup" onsubmit="processTopup(event)">
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--text-secondary)">
                    Top-up untuk: <strong id="topup-nama" style="color:var(--text-primary)"></strong>
                </p>
                <input type="hidden" name="id" id="topup-id">
                <input type="hidden" name="uid" id="topup-uid">
                
                <div id="topup-form-inputs">
                    <div class="form-group">
                        <label class="form-label">Tipe Top-up</label>
                        <select id="topup-tipe" class="form-control" onchange="toggleTopupMode()">
                            <option value="qris" selected>QRIS / Online WijayaPay</option>
                            <option value="manual">Top-up Manual (Saldo Langsung)</option>
                        </select>
                    </div>

                    <!-- Dropdown Metode Pembayaran Online -->
                    <div class="form-group" id="topup-gateway-group" style="display: none;">
                        <label class="form-label">Metode Pembayaran Online</label>
                        <select id="topup-gateway" class="form-control">
                            <option value="QRIS" selected>QRIS Dinamis (Otomatis)</option>
                            <optgroup label="Virtual Account (Transfer Bank)">
                                <option value="BCAVA">BCA Virtual Account</option>
                                <option value="BNIVA">BNI Virtual Account</option>
                                <option value="BRIVA">BRI Virtual Account</option>
                                <option value="MANDIRIVA">Mandiri Virtual Account</option>
                                <option value="BSIVA">BSI Virtual Account</option>
                                <option value="PERMATAVA">Permata Virtual Account</option>
                                <option value="CIMBVA">CIMB Virtual Account</option>
                                <option value="DANAMONVA">Danamon Virtual Account</option>
                                <option value="MUAMALATVA">Muamalat Virtual Account</option>
                                <option value="MAYBANKVA">Maybank Virtual Account</option>
                                <option value="SINARMASVA">Sinarmas Virtual Account</option>
                                <option value="OCBCVA">OCBC Virtual Account</option>
                            </optgroup>
                            <optgroup label="Gerai Retail (Kasir)">
                                <option value="ALFAMART">Alfamart</option>
                                <option value="INDOMARET">Indomaret</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Jumlah Top-up (Rp) *</label>
                        <input type="number" name="jumlah" id="topup-amount" class="form-control" placeholder="Contoh: 50000" 
                               min="1000" max="5000000" required>
                    </div>
                    <div class="form-group" id="topup-keterangan-group" style="display: none;">
                        <label class="form-label">Keterangan</label>
                        <input type="text" name="keterangan" class="form-control" value="Top-up manual oleh admin">
                    </div>
                </div>

                <!-- Container QRIS / VA / Retail Display -->
                <div id="topup-qris-area" style="display: none; text-align: center; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem;">
                    <h4 id="topup-payment-title" style="font-weight: 700; margin-bottom: 0.25rem;">Selesaikan Pembayaran</h4>
                    <p id="topup-payment-subtitle" style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Silakan lakukan transfer sesuai rincian di bawah ini</p>
                    
                    <div id="topup-qris-qr" style="background: white; padding: 0.75rem; border-radius: var(--radius-sm); display: inline-block; margin: 0.5rem 0; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);">
                        <!-- QR Code Image -->
                    </div>

                    <!-- Container VA & Retail -->
                    <div id="topup-va-retail-container" style="display: none; margin: 1rem 0;">
                        <div style="font-size: 0.8rem; color: var(--text-secondary);" id="topup-va-retail-label">Nomor Virtual Account:</div>
                        <div style="font-size: 1.6rem; font-weight: 800; color: var(--accent-primary); letter-spacing: 1px; margin: 0.25rem 0;" id="topup-va-retail-code">1234567890</div>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="copyAdminPaymentCode()" style="margin-top: 0.25rem;">📋 Salin Kode</button>
                    </div>

                    <div style="font-size: 0.85rem; margin-bottom: 0.5rem;">
                        Nominal Transfer: <strong style="color: var(--success); font-size: 1.05rem;" id="topup-qris-amount-val">Rp 0</strong>
                    </div>

                    <div style="font-weight: 700; color: var(--danger); font-size: 1rem;" id="topup-qris-timer">Sisa Waktu: 15:00</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">
                        Order ID: <span id="topup-qris-order-id" class="font-mono">WJP-XXX</span><br>
                        Status: <span style="color: var(--warning); font-weight: 600;">Menunggu Pembayaran...</span>
                    </div>

                    <!-- Container Petunjuk Pembayaran -->
                    <div id="topup-payment-tutorial-container" style="display: none; text-align: left; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.75rem; margin-top: 0.75rem; font-size: 0.75rem; max-height: 150px; overflow-y: auto;">
                        <strong style="color: var(--text-primary);">Petunjuk Pembayaran:</strong>
                        <ol id="topup-payment-tutorial-list" style="margin-left: 1.1rem; margin-top: 0.25rem; color: var(--text-secondary); line-height: 1.3;"></ol>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="topup-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="cancelAdminQrisPayment()">Batal</button>
                <button type="submit" id="topup-submit-btn" class="btn btn-success">💰 Proses Top-up</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Kartu -->
<div id="modal-edit" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>✏️ Edit Data Kartu</h3>
            <button class="modal-close" onclick="App.closeModal('modal-edit')">✕</button>
        </div>
        <form id="form-edit" onsubmit="updateCard(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-group">
                    <label class="form-label">UID</label>
                    <input type="text" id="edit-uid" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Pemilik *</label>
                    <input type="text" name="nama" id="edit-nama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Ubah PIN Kartu (6 Digit Angka)</label>
                    <input type="password" name="pin" id="edit-pin" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah PIN" maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
                    <small class="text-muted">Masukkan 6 digit angka baru jika ingin memperbarui PIN kartu saat ini.</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit-email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telepon" id="edit-telepon" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="App.closeModal('modal-edit')">Batal</button>
                <button type="submit" class="btn btn-primary">💾 Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraJS = <<<'JS'
let adminPollInterval = null;
let adminCountdownInterval = null;

function showTopupModal(id, nama, uid) {
    document.getElementById('topup-id').value = id;
    document.getElementById('topup-nama').textContent = nama;
    document.getElementById('topup-uid').value = uid;

    // Reset fields and toggle visibility to default
    document.getElementById('topup-tipe').value = 'qris';
    document.getElementById('topup-gateway').value = 'QRIS';
    document.getElementById('topup-gateway-group').style.display = 'block';
    document.getElementById('topup-amount').value = '';
    document.getElementById('topup-amount').disabled = false;
    document.getElementById('topup-amount').required = true;
    document.getElementById('topup-keterangan-group').style.display = 'none';
    document.getElementById('topup-form-inputs').style.display = 'block';
    document.getElementById('topup-qris-area').style.display = 'none';

    // Reset submit button text
    const submitBtn = document.getElementById('topup-submit-btn');
    submitBtn.style.display = 'inline-flex';
    submitBtn.className = 'btn btn-primary';
    submitBtn.innerHTML = '⚡ Buat Transaksi';
    submitBtn.disabled = false;

    // Cancel any active polling
    if (adminPollInterval) clearInterval(adminPollInterval);
    if (adminCountdownInterval) clearInterval(adminCountdownInterval);

    App.openModal('modal-topup');
}

function toggleTopupMode() {
    const mode = document.getElementById('topup-tipe').value;
    const submitBtn = document.getElementById('topup-submit-btn');
    const gatewayGroup = document.getElementById('topup-gateway-group');
    const keteranganGroup = document.getElementById('topup-keterangan-group');
    if (mode === 'qris') {
        submitBtn.className = 'btn btn-primary';
        submitBtn.innerHTML = '⚡ Buat Transaksi';
        gatewayGroup.style.display = 'block';
        keteranganGroup.style.display = 'none';
    } else {
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '💰 Proses Top-up';
        gatewayGroup.style.display = 'none';
        keteranganGroup.style.display = 'block';
    }
}

function showEditModal(user) {
    document.getElementById('edit-id').value = user.id;
    document.getElementById('edit-uid').value = user.rfid_uid;
    document.getElementById('edit-nama').value = user.nama;
    document.getElementById('edit-email').value = user.email || '';
    document.getElementById('edit-telepon').value = user.telepon || '';
    document.getElementById('edit-pin').value = '';
    App.openModal('modal-edit');
}

async function registerCard(e) {
    e.preventDefault();
    const data = App.formData('form-register');
    const result = await App.request('../api/rfid.php?action=register', { body: data });
    if (result.status === 'success') {
        App.toast(result.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        App.toast(result.message, 'error');
    }
}

async function processTopup(e) {
    e.preventDefault();
    const mode = document.getElementById('topup-tipe').value;
    if (mode === 'manual') {
        const data = App.formData('form-topup');
        const result = await App.request('../api/rfid.php?action=topup', { body: data });
        if (result.status === 'success') {
            App.toast(result.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            App.toast(result.message, 'error');
        }
    } else {
        await startAdminQrisTopup();
    }
}

async function startAdminQrisTopup() {
    const uid = document.getElementById('topup-uid').value;
    const amount = document.getElementById('topup-amount').value;
    const gateway = document.getElementById('topup-gateway').value;

    if (!uid || amount <= 0) {
        App.toast('UID dan nominal wajib diisi', 'error');
        return;
    }

    const submitBtn = document.getElementById('topup-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Memproses...';

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'request_wijayapay');
        formData.append('rfid_uid', uid);
        formData.append('nominal', amount);
        formData.append('code_payment', gateway);

        const response = await fetch('../api/topup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });

        const data = await response.json();
        if (data.status === 'success') {
            App.toast('Transaksi Berhasil Dibuat!', 'success');
            
            document.getElementById('topup-form-inputs').style.display = 'none';
            document.getElementById('topup-qris-area').style.display = 'block';

            document.getElementById('topup-qris-amount-val').textContent = App.formatRupiah(data.nominal);
            document.getElementById('topup-qris-order-id').textContent = data.order_id;

            // Reset display states
            document.getElementById('topup-qris-qr').style.display = 'none';
            document.getElementById('topup-va-retail-container').style.display = 'none';
            document.getElementById('topup-payment-tutorial-container').style.display = 'none';

            if (data.qr_data) {
                // QRIS Mode
                document.getElementById('topup-payment-title').textContent = 'Scan QRIS Untuk Membayar';
                document.getElementById('topup-payment-subtitle').textContent = 'Minta pelanggan men-scan QRIS di bawah ini';
                document.getElementById('topup-qris-qr').style.display = 'inline-block';
                
                const qrContainer = document.getElementById('topup-qris-qr');
                qrContainer.innerHTML = '';
                const img = document.createElement('img');
                img.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(data.qr_data)}`;
                img.style.display = 'block';
                img.alt = 'QRIS';
                qrContainer.appendChild(img);
            } else if (data.nomor_va || data.nomor_pembayaran) {
                // VA or Retail Mode
                document.getElementById('topup-payment-title').textContent = data.payment_name;
                document.getElementById('topup-payment-subtitle').textContent = 'Silakan pelanggan melakukan pembayaran sesuai rincian di bawah ini';
                document.getElementById('topup-va-retail-container').style.display = 'block';
                
                const codeVal = data.nomor_va || data.nomor_pembayaran;
                document.getElementById('topup-va-retail-code').textContent = codeVal;
                document.getElementById('topup-va-retail-label').textContent = data.nomor_va ? 'Nomor Virtual Account:' : 'Kode Pembayaran:';

                if (data.tutorial) {
                    document.getElementById('topup-payment-tutorial-container').style.display = 'block';
                    const list = document.getElementById('topup-payment-tutorial-list');
                    list.innerHTML = '';
                    const steps = data.tutorial.split('\n');
                    steps.forEach(step => {
                        if (step.trim()) {
                            const li = document.createElement('li');
                            li.textContent = step.trim();
                            list.appendChild(li);
                        }
                    });
                }
            } else if (data.payment_url) {
                // Link Mode
                document.getElementById('topup-payment-title').textContent = 'Buka Link Pembayaran';
                document.getElementById('topup-payment-subtitle').textContent = 'Silakan selesaikan pembayaran di halaman luar';
                document.getElementById('topup-qris-qr').style.display = 'inline-block';
                
                const qrContainer = document.getElementById('topup-qris-qr');
                qrContainer.innerHTML = '';
                const a = document.createElement('a');
                a.href = data.payment_url;
                a.target = '_blank';
                a.className = 'btn btn-primary btn-sm';
                a.textContent = 'Buka Pembayaran';
                qrContainer.appendChild(a);
            }

            submitBtn.style.display = 'none';

            startAdminTimer(15 * 60);
            startAdminPolling(data.order_id);
        } else {
            App.toast(data.message || 'Gagal membuat transaksi', 'error');
            submitBtn.disabled = false;
            toggleTopupMode();
        }
    } catch (e) {
        console.error(e);
        App.toast('Gagal terhubung ke server', 'error');
        submitBtn.disabled = false;
        toggleTopupMode();
    }
}

function copyAdminPaymentCode() {
    const code = document.getElementById('topup-va-retail-code').textContent;
    navigator.clipboard.writeText(code).then(() => {
        App.toast('Kode pembayaran berhasil disalin!', 'success');
    }).catch(() => {
        App.toast('Gagal menyalin kode', 'error');
    });
}

function startAdminTimer(duration) {
    let timer = duration, minutes, seconds;
    if (adminCountdownInterval) clearInterval(adminCountdownInterval);

    const display = document.getElementById('topup-qris-timer');
    adminCountdownInterval = setInterval(() => {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        display.textContent = "Sisa Waktu: " + minutes + ":" + seconds;

        if (--timer < 0) {
            clearInterval(adminCountdownInterval);
            cancelAdminQrisPayment('Waktu pembayaran habis');
        }
    }, 1000);
}

function startAdminPolling(orderId) {
    if (adminPollInterval) clearInterval(adminPollInterval);
    adminPollInterval = setInterval(async () => {
        try {
            const res = await fetch(`../api/transactions.php?action=check_status&order_id=${encodeURIComponent(orderId)}`);
            const data = await res.json();
            
            if (data.status === 'success') {
                if (data.trx_status === 'success') {
                    clearInterval(adminPollInterval);
                    clearInterval(adminCountdownInterval);
                    
                    App.toast('Pembayaran Berhasil Diterima!', 'success');
                    setTimeout(() => {
                        App.closeModal('modal-topup');
                        location.reload();
                    }, 1000);
                } else if (data.trx_status === 'failed' || data.trx_status === 'expired') {
                    clearInterval(adminPollInterval);
                    clearInterval(adminCountdownInterval);
                    App.toast('Pembayaran gagal atau kedaluwarsa', 'error');
                    cancelAdminQrisPayment(null, false);
                }
            }
        } catch (e) {
            console.error('Polling error:', e);
        }
    }, 3000);
}

function cancelAdminQrisPayment(reason = null, showToast = true) {
    if (adminPollInterval) clearInterval(adminPollInterval);
    if (adminCountdownInterval) clearInterval(adminCountdownInterval);

    if (reason && showToast) {
        App.toast(reason, 'warning');
    } else if (showToast) {
        App.toast('Pembayaran QRIS dibatalkan/ditutup', 'info');
    }

    document.getElementById('topup-form-inputs').style.display = 'block';
    document.getElementById('topup-qris-area').style.display = 'none';
    
    const submitBtn = document.getElementById('topup-submit-btn');
    submitBtn.style.display = 'inline-flex';
    submitBtn.disabled = false;
    toggleTopupMode();

    App.closeModal('modal-topup');
}

async function updateCard(e) {
    e.preventDefault();
    const data = App.formData('form-edit');
    const result = await App.request('../api/rfid.php?action=update', { body: data });
    if (result.status === 'success') {
        App.toast(result.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        App.toast(result.message, 'error');
    }
}

async function blockCard(id) {
    if (await App.confirm('Blokir kartu ini?')) {
        const result = await App.request('../api/rfid.php?action=block', { body: 'id=' + id });
        App.toast(result.message, result.status === 'success' ? 'success' : 'error');
        if (result.status === 'success') setTimeout(() => location.reload(), 1000);
    }
}

async function activateCard(id) {
    if (await App.confirm('Aktifkan kembali kartu ini?')) {
        const result = await App.request('../api/rfid.php?action=activate', { body: 'id=' + id });
        App.toast(result.message, result.status === 'success' ? 'success' : 'error');
        if (result.status === 'success') setTimeout(() => location.reload(), 1000);
    }
}
JS;

require_once '_footer.php';
?>
