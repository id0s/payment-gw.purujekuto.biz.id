<?php
/**
 * RFID Payment Gateway - Webhook API
 * Menerima notifikasi dari payment gateway eksternal (opsional).
 * Endpoint ini siap digunakan jika nanti didaftarkan ke Midtrans/Xendit/Tripay.
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// Log semua webhook masuk
$rawBody = file_get_contents('php://input');
logActivity("Webhook received: " . substr($rawBody, 0, 500));

$data = json_decode($rawBody, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

if (empty($data)) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid raw input or POST data'], 400);
}

// ── Check if it is a WijayaPay Webhook ────────────────
$isWijayaPay = false;
$refId = $data['ref_id'] ?? $data['order_id'] ?? null;
$status = $data['status'] ?? '';
$pdo = getDB();

$trx = null;
$trxShop = null;

if ($refId) {
    // Cek apakah ada di transaksi topup
    $stmt = $pdo->prepare("SELECT id, user_id, status, gateway, jumlah FROM transactions WHERE order_id = ?");
    $stmt->execute([$refId]);
    $trx = $stmt->fetch();
    
    if ($trx && $trx['gateway'] === 'wijayapay') {
        $isWijayaPay = true;
    } else {
        // Cek apakah ada di transaksi toko kelontong
        $stmtShop = $pdo->prepare("SELECT id, status, product_id, total_harga FROM transaksi WHERE order_id = ?");
        $stmtShop->execute([$refId]);
        $trxShop = $stmtShop->fetch();
        if ($trxShop) {
            $isWijayaPay = true;
        }
    }
}

if ($isWijayaPay) {
    // Prosedur Webhook WijayaPay
    // Signature: md5(code_merchant + api_key + ref_id)
    $receivedSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? $_POST['signature'] ?? $data['signature'] ?? '';
    $expectedSignature = md5(WIJAYAPAY_MERCHANT_CODE . WIJAYAPAY_API_KEY . $refId);
    
    if ($receivedSignature && $receivedSignature !== $expectedSignature) {
        logActivity("Webhook WijayaPay: invalid signature");
        jsonResponse(['status' => false, 'message' => 'Invalid signature'], 401);
    }
    
    // Cek status transaksi saat ini di DB kita
    if (($trx && $trx['status'] === 'success') || ($trxShop && $trxShop['status'] === 'success')) {
        jsonResponse(['status' => true]);
    }
    
    $statusLower = strtolower($status);
    $paidStatuses = ['success', 'settled', 'settlement', 'capture', 'paid', 'approved'];
    
    try {
        $pdo->beginTransaction();
        
        $trxRef = $data['trx_reference'] ?? $data['reference'] ?? null;
        
        if (in_array($statusLower, $paidStatuses)) {
            if ($trx) {
                // Update transaksi top-up
                $pdo->prepare("UPDATE transactions SET status = 'success', gateway_ref = ?, raw_response = ? WHERE id = ?")
                    ->execute([$trxRef, $rawBody ?: json_encode($data), $trx['id']]);
                    
                // Tambah saldo user
                $pdo->prepare("UPDATE users SET saldo = saldo + ? WHERE id = ?")
                    ->execute([$trx['jumlah'], $trx['user_id']]);
                    
                logActivity("Webhook WijayaPay SUCCESS: +{$trx['jumlah']} untuk user ID {$trx['user_id']} (Ref: $refId)");
            } elseif ($trxShop) {
                // Update transaksi toko
                $pdo->prepare("UPDATE transaksi SET status = 'success' WHERE id = ?")
                    ->execute([$trxShop['id']]);

                // Kurangi stok barang toko
                // Cek detail keranjang POS
                $stmtDetails = $pdo->prepare("SELECT product_id, qty FROM transaksi_detail WHERE transaksi_id = ?");
                $stmtDetails->execute([$trxShop['id']]);
                $details = $stmtDetails->fetchAll();

                if (!empty($details)) {
                    $updateStock = $pdo->prepare("UPDATE products SET stok = GREATEST(0, stok - ?) WHERE id = ?");
                    foreach ($details as $item) {
                        $updateStock->execute([(int)$item['qty'], (int)$item['product_id']]);
                    }
                } else {
                    // Cek jika produk tunggal (checkout manual)
                    if ($trxShop['product_id']) {
                        $pdo->prepare("UPDATE products SET stok = GREATEST(0, stok - 1) WHERE id = ?")
                            ->execute([$trxShop['product_id']]);
                    }
                }

                logActivity("Webhook WijayaPay Toko SUCCESS: Transaksi {$refId} lunas, stok dipotong.");
            }
        } else {
            if ($trx) {
                // Update status ke failed
                $pdo->prepare("UPDATE transactions SET status = 'failed', gateway_ref = ?, raw_response = ? WHERE id = ?")
                    ->execute([$trxRef, $rawBody ?: json_encode($data), $trx['id']]);
                logActivity("Webhook WijayaPay FAILED: status $status untuk Ref: $refId");
            } elseif ($trxShop) {
                $pdo->prepare("UPDATE transaksi SET status = 'failed' WHERE id = ?")
                    ->execute([$trxShop['id']]);
                logActivity("Webhook WijayaPay Toko FAILED: status $status untuk Ref: $refId");
            }
        }
        
        $pdo->commit();
        jsonResponse(['status' => true]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logActivity("Webhook WijayaPay Error: " . $e->getMessage());
        jsonResponse(['status' => false, 'message' => 'Internal error'], 500);
    }
}

// ── Validasi Webhook Secret (untuk Webhook Non-WijayaPay / Default) ────────────────────
$receivedSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_GET['secret'] ?? '';
if ($receivedSecret !== WEBHOOK_SECRET) {
    logActivity("Webhook rejected: invalid secret");
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

// ── Proses Webhook ─────────────────────────────
// Format yang diharapkan:
// {
//   "order_id": "TOP-20260612-XXXX",
//   "rfid_uid": "A1B2C3D4",
//   "amount": 50000,
//   "status": "PAID",
//   "payment_method": "BCA VA",
//   "reference": "xxx-yyy-zzz"
// }

$orderId = $data['order_id'] ?? null;
$rfidUid = $data['rfid_uid'] ?? null;
$amount = (float)($data['amount'] ?? 0);
$status = $data['status'] ?? '';
$payMethod = $data['payment_method'] ?? 'Webhook';
$reference = $data['reference'] ?? null;

if (!$rfidUid || $amount <= 0) {
    jsonResponse(['status' => 'error', 'message' => 'Data tidak lengkap'], 400);
}

// Hanya proses jika status PAID/settlement
$paidStatuses = ['PAID', 'paid', 'settlement', 'capture', 'success'];
if (!in_array($status, $paidStatuses)) {
    jsonResponse(['status' => 'ignored', 'message' => 'Status bukan PAID']);
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // Cari user
    $stmt = $pdo->prepare("SELECT id, nama FROM users WHERE rfid_uid = ? AND status = 'active'");
    $stmt->execute([strtoupper($rfidUid)]);
    $user = $stmt->fetch();

    if (!$user) {
        $pdo->rollBack();
        logActivity("Webhook: user '$rfidUid' not found");
        jsonResponse(['status' => 'error', 'message' => 'User tidak ditemukan'], 404);
    }

    // Cek duplikat (jika order_id sudah pernah diproses)
    if ($orderId) {
        $dupCheck = $pdo->prepare("SELECT id FROM transactions WHERE order_id = ? AND status = 'success'");
        $dupCheck->execute([$orderId]);
        if ($dupCheck->fetch()) {
            $pdo->rollBack();
            jsonResponse(['status' => 'ignored', 'message' => 'Transaksi sudah diproses sebelumnya']);
        }
    }

    // Tambah saldo
    $pdo->prepare("UPDATE users SET saldo = saldo + ? WHERE id = ?")->execute([$amount, $user['id']]);

    // Catat transaksi
    $newOrderId = $orderId ?: generateOrderId('WBH');
    $pdo->prepare("INSERT INTO transactions (user_id, order_id, jenis, jumlah, metode_bayar, gateway, gateway_ref, status, keterangan, raw_response) VALUES (?, ?, 'topup', ?, ?, 'webhook', ?, 'success', 'Top-up via webhook', ?)")
        ->execute([$user['id'], $newOrderId, $amount, $payMethod, $reference, $rawBody]);

    $pdo->commit();
    logActivity("Webhook success: +$amount untuk {$user['nama']} ($rfidUid)");

    jsonResponse(['status' => 'success', 'message' => 'Saldo berhasil ditambahkan']);

} catch (Exception $e) {
    $pdo->rollBack();
    logActivity("Webhook error: " . $e->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'Internal error'], 500);
}
