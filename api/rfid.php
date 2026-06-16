<?php
/**
 * RFID Payment Gateway - RFID API
 * Endpoint untuk hardware RFID reader dan admin
 * 
 * === Untuk Hardware (ESP32/USB Reader) ===
 * GET  /api/rfid.php?action=tap&uid=XXXX&device=DEV-001&token=xxx  → Tap bayar
 * GET  /api/rfid.php?action=check&uid=XXXX&token=xxx               → Cek saldo
 * 
 * === Untuk Admin (via Dashboard) ===
 * POST /api/rfid.php  action=register   → Daftarkan kartu baru
 * POST /api/rfid.php  action=update     → Update data kartu
 * POST /api/rfid.php  action=block      → Blokir kartu
 * POST /api/rfid.php  action=activate   → Aktifkan kartu
 * POST /api/rfid.php  action=delete     → Hapus kartu
 * POST /api/rfid.php  action=topup      → Top-up saldo langsung
 * GET  /api/rfid.php?action=list        → List semua kartu (admin)
 * GET  /api/rfid.php?action=detail&id=X → Detail kartu (admin)
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
// Allow CORS for hardware devices
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Api-Token, Content-Type');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDB();

switch ($action) {

    // ══════════════════════════════════════════
    // HARDWARE: Tap Kartu (Potong Saldo)
    // ══════════════════════════════════════════
    case 'tap':
        requireApiToken();
        $uid = strtoupper(trim($_GET['uid'] ?? ''));
        $deviceId = $_GET['device'] ?? 'UNKNOWN';

        if (empty($uid)) {
            jsonResponse(['status' => 'error', 'message' => 'UID kosong'], 400);
        }

        try {
            $pdo->beginTransaction();

            // Cari user berdasarkan UID
            $stmt = $pdo->prepare("SELECT id, nama, saldo, status FROM users WHERE rfid_uid = ? FOR UPDATE");
            $stmt->execute([$uid]);
            $user = $stmt->fetch();

            if (!$user) {
                $pdo->rollBack();
                // Log unknown card
                $pdo->prepare("INSERT INTO rfid_logs (rfid_uid, aksi, device_id, result) VALUES (?, 'tap_unknown', ?, 'not_found')")
                    ->execute([$uid, $deviceId]);
                jsonResponse(['status' => 'error', 'message' => 'Kartu tidak terdaftar', 'uid' => $uid], 404);
            }

            if ($user['status'] !== 'active') {
                $pdo->rollBack();
                jsonResponse(['status' => 'error', 'message' => 'Kartu diblokir', 'uid' => $uid], 403);
            }

            // Ambil harga tap dari device atau default
            $deviceStmt = $pdo->prepare("SELECT harga_tap FROM devices WHERE device_id = ? AND is_active = 1");
            $deviceStmt->execute([$deviceId]);
            $device = $deviceStmt->fetch();
            $hargaTap = $device ? (float)$device['harga_tap'] : DEFAULT_TAP_PRICE;

            // Update last_seen device
            if ($device) {
                $pdo->prepare("UPDATE devices SET last_seen = NOW() WHERE device_id = ?")->execute([$deviceId]);
            }

            if ($user['saldo'] < $hargaTap) {
                $pdo->rollBack();
                $pdo->prepare("INSERT INTO rfid_logs (rfid_uid, aksi, device_id, result, detail) VALUES (?, 'tap_pay', ?, 'insufficient', ?)")
                    ->execute([$uid, $deviceId, 'Saldo: ' . $user['saldo']]);
                jsonResponse([
                    'status' => 'error',
                    'message' => 'Saldo tidak mencukupi',
                    'nama' => $user['nama'],
                    'saldo' => (float)$user['saldo'],
                    'harga' => $hargaTap
                ], 402);
            }

            // Potong saldo
            $pdo->prepare("UPDATE users SET saldo = saldo - ? WHERE id = ?")->execute([$hargaTap, $user['id']]);

            // Catat transaksi
            $orderId = generateOrderId('TAP');
            $pdo->prepare("INSERT INTO transactions (user_id, order_id, jenis, jumlah, metode_bayar, gateway, status, keterangan) VALUES (?, ?, 'payment', ?, 'RFID Tap', 'device', 'success', ?)")
                ->execute([$user['id'], $orderId, $hargaTap, "Tap di $deviceId"]);

            // Log
            $pdo->prepare("INSERT INTO rfid_logs (rfid_uid, aksi, device_id, result, detail) VALUES (?, 'tap_pay', ?, 'success', ?)")
                ->execute([$uid, $deviceId, "Potong $hargaTap"]);

            $pdo->commit();
            $sisaSaldo = $user['saldo'] - $hargaTap;

            jsonResponse([
                'status' => 'success',
                'message' => 'Pembayaran berhasil',
                'nama' => $user['nama'],
                'jumlah' => $hargaTap,
                'sisa_saldo' => $sisaSaldo,
                'order_id' => $orderId
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'Sistem error'], 500);
        }
        break;

    // ══════════════════════════════════════════
    // HARDWARE: Cek Saldo
    // ══════════════════════════════════════════
    case 'check':
        requireApiToken();
        $uid = strtoupper(trim($_GET['uid'] ?? ''));

        if (empty($uid)) {
            jsonResponse(['status' => 'error', 'message' => 'UID kosong'], 400);
        }

        $stmt = $pdo->prepare("SELECT nama, saldo, status FROM users WHERE rfid_uid = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(['status' => 'error', 'message' => 'Kartu tidak terdaftar'], 404);
        }

        // Log check
        $pdo->prepare("INSERT INTO rfid_logs (rfid_uid, aksi, result) VALUES (?, 'tap_check', 'success')")->execute([$uid]);

        jsonResponse([
            'status' => 'success',
            'nama' => $user['nama'],
            'saldo' => (float)$user['saldo'],
            'kartu_status' => $user['status']
        ]);
        break;

    // ══════════════════════════════════════════
    // ADMIN: Register Kartu Baru
    // ══════════════════════════════════════════
    case 'register':
        requireLogin();
        $uid = strtoupper(trim($_POST['rfid_uid'] ?? ''));
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');

        if (empty($uid) || empty($nama)) {
            jsonResponse(['status' => 'error', 'message' => 'UID dan Nama wajib diisi'], 400);
        }

        // Cek duplikat
        $check = $pdo->prepare("SELECT id FROM users WHERE rfid_uid = ?");
        $check->execute([$uid]);
        if ($check->fetch()) {
            jsonResponse(['status' => 'error', 'message' => 'UID sudah terdaftar'], 409);
        }

        $pdo->prepare("INSERT INTO users (rfid_uid, nama, email, telepon) VALUES (?, ?, ?, ?)")
            ->execute([$uid, $nama, $email ?: null, $telepon ?: null]);

        $pdo->prepare("INSERT INTO rfid_logs (rfid_uid, aksi, result, detail) VALUES (?, 'register', 'success', ?)")
            ->execute([$uid, "Didaftarkan oleh admin"]);

        logActivity("Kartu RFID '$uid' didaftarkan untuk '$nama'");
        jsonResponse(['status' => 'success', 'message' => "Kartu $uid berhasil didaftarkan untuk $nama"]);
        break;

    // ══════════════════════════════════════════
    // ADMIN: Update Data Kartu
    // ══════════════════════════════════════════
    case 'update':
        requireLogin();
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');

        if (!$id || empty($nama)) {
            jsonResponse(['status' => 'error', 'message' => 'Data tidak lengkap'], 400);
        }

        $pdo->prepare("UPDATE users SET nama = ?, email = ?, telepon = ? WHERE id = ?")
            ->execute([$nama, $email ?: null, $telepon ?: null, $id]);

        logActivity("Data kartu ID $id diupdate");
        jsonResponse(['status' => 'success', 'message' => 'Data berhasil diupdate']);
        break;

    // ══════════════════════════════════════════
    // ADMIN: Blokir Kartu
    // ══════════════════════════════════════════
    case 'block':
        requireLogin();
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ?")->execute([$id]);
        logActivity("Kartu ID $id diblokir");
        jsonResponse(['status' => 'success', 'message' => 'Kartu berhasil diblokir']);
        break;

    // ══════════════════════════════════════════
    // ADMIN: Aktifkan Kartu
    // ══════════════════════════════════════════
    case 'activate':
        requireLogin();
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
        logActivity("Kartu ID $id diaktifkan");
        jsonResponse(['status' => 'success', 'message' => 'Kartu berhasil diaktifkan']);
        break;

    // ══════════════════════════════════════════
    // ADMIN: Hapus Kartu
    // ══════════════════════════════════════════
    case 'delete':
        requireLogin();
        $id = (int)($_POST['id'] ?? 0);
        
        // Cek apakah ada transaksi terkait
        $trxCount = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
        $trxCount->execute([$id]);
        if ($trxCount->fetchColumn() > 0) {
            // Soft delete - set inactive
            $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$id]);
            jsonResponse(['status' => 'success', 'message' => 'Kartu dinonaktifkan (ada riwayat transaksi)']);
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            jsonResponse(['status' => 'success', 'message' => 'Kartu berhasil dihapus']);
        }
        logActivity("Kartu ID $id dihapus/dinonaktifkan");
        break;

    // ══════════════════════════════════════════
    // ADMIN: Top-up Saldo Langsung
    // ══════════════════════════════════════════
    case 'topup':
        requireLogin();
        $id = (int)($_POST['id'] ?? 0);
        $jumlah = (int)($_POST['jumlah'] ?? 0);
        $keterangan = trim($_POST['keterangan'] ?? 'Top-up manual oleh admin');

        if (!$id || $jumlah <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Data tidak lengkap atau jumlah tidak valid'], 400);
        }

        try {
            $pdo->beginTransaction();

            // Cek user terdaftar
            $stmt = $pdo->prepare("SELECT id, nama, rfid_uid, status FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $user = $stmt->fetch();

            if (!$user) {
                $pdo->rollBack();
                jsonResponse(['status' => 'error', 'message' => 'User tidak ditemukan'], 404);
            }

            // Tambah saldo
            $pdo->prepare("UPDATE users SET saldo = saldo + ? WHERE id = ?")
                ->execute([$jumlah, $id]);

            // Catat transaksi
            $orderId = generateOrderId('TOP');
            $pdo->prepare("INSERT INTO transactions (user_id, order_id, jenis, jumlah, metode_bayar, gateway, status, keterangan, approved_by) VALUES (?, ?, 'topup', ?, 'Manual', 'manual', 'success', ?, ?)")
                ->execute([
                    $id,
                    $orderId,
                    $jumlah,
                    $keterangan ?: 'Top-up manual oleh admin',
                    $_SESSION['admin_id'] ?? null
                ]);

            // Log RFID
            $pdo->prepare("INSERT INTO rfid_logs (rfid_uid, aksi, result, detail) VALUES (?, 'tap_topup', 'success', ?)")
                ->execute([
                    $user['rfid_uid'],
                    "Top-up manual oleh admin sebesar $jumlah"
                ]);

            $pdo->commit();

            logActivity("Top-up manual untuk {$user['nama']} ({$user['rfid_uid']}) sebesar " . formatRupiah($jumlah) . " oleh admin ID " . ($_SESSION['admin_id'] ?? 'unknown'));
            jsonResponse(['status' => 'success', 'message' => 'Top-up ' . formatRupiah($jumlah) . ' untuk ' . $user['nama'] . ' berhasil diproses']);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse(['status' => 'error', 'message' => 'Gagal memproses: ' . $e->getMessage()], 500);
        }
        break;

    // ══════════════════════════════════════════
    // ADMIN: List Semua Kartu
    // ══════════════════════════════════════════
    case 'list':
        requireLogin();
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';

        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];

        if ($search) {
            $sql .= " AND (nama LIKE ? OR rfid_uid LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        jsonResponse(['status' => 'success', 'data' => $users]);
        break;

    // ══════════════════════════════════════════
    // ADMIN: Detail Kartu
    // ══════════════════════════════════════════
    case 'detail':
        requireLogin();
        $id = (int)($_GET['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(['status' => 'error', 'message' => 'User tidak ditemukan'], 404);
        }

        // Ambil transaksi terbaru
        $trx = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $trx->execute([$id]);

        jsonResponse(['status' => 'success', 'user' => $user, 'transactions' => $trx->fetchAll()]);
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Action tidak valid'], 400);
}
