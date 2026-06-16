<?php
/**
 * RFID Payment Gateway - Halaman Pengaturan
 */
$pageTitle = 'Pengaturan';
require_once '_header.php';

$pdo = getDB();

// Handle save settings
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'save_settings') {
        $settings = $_POST['settings'] ?? [];
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("UPDATE settings SET `value` = ? WHERE `key` = ?");
            $stmt->execute([$value, $key]);
        }
        $message = 'Pengaturan berhasil disimpan';
        $messageType = 'success';
        logActivity("Settings diupdate");
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $message = 'Semua field password harus diisi';
            $messageType = 'danger';
        } elseif ($newPass !== $confirmPass) {
            $message = 'Password baru tidak cocok';
            $messageType = 'danger';
        } elseif (strlen($newPass) < 6) {
            $message = 'Password minimal 6 karakter';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch();

            if (!password_verify($currentPass, $admin['password'])) {
                $message = 'Password lama salah';
                $messageType = 'danger';
            } else {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")->execute([$hashed, $_SESSION['admin_id']]);
                $message = 'Password berhasil diubah';
                $messageType = 'success';
                logActivity("Admin '{$_SESSION['admin_username']}' mengubah password");
            }
        }
    }
}

// Load settings
$settings = $pdo->query("SELECT * FROM settings ORDER BY id")->fetchAll();
$settingsMap = [];
foreach ($settings as $s) {
    $settingsMap[$s['key']] = $s;
}

// Load devices
$devices = $pdo->query("SELECT * FROM devices ORDER BY device_id")->fetchAll();
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $message === 'Pengaturan berhasil disimpan' ? '✅' : ($messageType === 'danger' ? '❌' : '✅') ?> <?= sanitize($message) ?>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">
    <!-- Pengaturan Umum -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">⚙️ Pengaturan Umum</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="form_action" value="save_settings">
            <?php foreach ($settings as $s): ?>
            <div class="form-group">
                <label class="form-label"><?= sanitize($s['label'] ?? $s['key']) ?></label>
                <?php if ($s['type'] === 'textarea'): ?>
                    <textarea name="settings[<?= $s['key'] ?>]" class="form-control"><?= sanitize($s['value'] ?? '') ?></textarea>
                <?php elseif ($s['type'] === 'number'): ?>
                    <input type="number" name="settings[<?= $s['key'] ?>]" class="form-control" value="<?= sanitize($s['value'] ?? '') ?>">
                <?php elseif ($s['type'] === 'boolean'): ?>
                    <select name="settings[<?= $s['key'] ?>]" class="form-control">
                        <option value="1" <?= $s['value'] === '1' ? 'selected' : '' ?>>Ya</option>
                        <option value="0" <?= $s['value'] === '0' ? 'selected' : '' ?>>Tidak</option>
                    </select>
                <?php else: ?>
                    <input type="text" name="settings[<?= $s['key'] ?>]" class="form-control" value="<?= sanitize($s['value'] ?? '') ?>">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
        </form>
    </div>

    <div>
        <!-- Ganti Password -->
        <div class="card mb-2">
            <div class="card-header">
                <h3 class="card-title">🔐 Ganti Password</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="change_password">
                <div class="form-group">
                    <label class="form-label">Password Lama</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-warning">🔐 Ubah Password</button>
            </form>
        </div>

        <!-- Devices -->
        <div class="card mb-2">
            <div class="card-header">
                <h3 class="card-title">📡 RFID Devices</h3>
            </div>
            <?php if (empty($devices)): ?>
                <p class="text-muted" style="font-size:0.85rem">Belum ada device terdaftar.</p>
            <?php else: ?>
                <?php foreach ($devices as $d): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--border-color)">
                    <div>
                        <code style="color:var(--accent-primary);font-size:0.8rem"><?= sanitize($d['device_id']) ?></code>
                        <div style="font-weight:600;font-size:0.85rem"><?= sanitize($d['nama']) ?></div>
                        <div class="text-muted" style="font-size:0.75rem">
                            📍 <?= sanitize($d['lokasi'] ?? '-') ?> • 
                            💰 <?= formatRupiah($d['harga_tap']) ?>/tap
                        </div>
                    </div>
                    <div>
                        <span class="badge <?= $d['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $d['is_active'] ? '● Online' : '● Offline' ?>
                        </span>
                        <?php if ($d['last_seen']): ?>
                            <div class="text-muted" style="font-size:0.7rem"><?= date('d/m H:i', strtotime($d['last_seen'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- API Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🔗 API Info</h3>
            </div>
            <div style="font-size:0.82rem">
                <div class="form-group">
                    <label class="form-label">API Token (untuk Hardware)</label>
                    <input type="text" class="form-control font-mono" value="<?= API_TOKEN ?>" readonly 
                           onclick="this.select()" style="font-size:0.78rem">
                    <small class="text-muted">Kirim sebagai header X-Api-Token atau parameter ?token=</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <input type="text" class="form-control font-mono" value="<?= APP_URL ?>/api/webhook.php" readonly 
                           onclick="this.select()" style="font-size:0.78rem">
                </div>
                <div class="form-group">
                    <label class="form-label">Webhook Secret</label>
                    <input type="text" class="form-control font-mono" value="<?= WEBHOOK_SECRET ?>" readonly 
                           onclick="this.select()" style="font-size:0.78rem">
                    <small class="text-muted">Kirim sebagai header X-Webhook-Secret</small>
                </div>
                <hr style="border-color:var(--border-color);margin:1rem 0">
                <p class="text-muted"><strong>Contoh Tap (cURL):</strong></p>
                <code style="display:block;background:var(--bg-glass);padding:0.75rem;border-radius:var(--radius-sm);font-size:0.72rem;word-break:break-all;color:var(--success)">
                    curl "<?= APP_URL ?>/api/rfid.php?action=tap&uid=A1B2C3D4&device=DEV-001&token=<?= API_TOKEN ?>"
                </code>
            </div>
        </div>
    </div>
</div>

<?php require_once '_footer.php'; ?>
