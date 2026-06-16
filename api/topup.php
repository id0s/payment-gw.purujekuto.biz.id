<?php
/**
 * RFID Payment Gateway - Top-up API
 * POST action=approve → Approve request top-up
 * POST action=reject  → Reject request top-up
 */
require_once __DIR__ . '/../config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Hanya action selain request_wijayapay yang butuh login admin
if ($action !== 'request_wijayapay') {
    requireLogin();
}

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();

switch ($action) {

    case 'approve':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            jsonResponse(['status' => 'error', 'message' => 'ID tidak valid'], 400);
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT tr.*, u.nama, u.rfid_uid FROM topup_requests tr JOIN users u ON tr.user_id = u.id WHERE tr.id = ? AND tr.status = 'pending' FOR UPDATE");
            $stmt->execute([$id]);
            $request = $stmt->fetch();

            if (!$request) {
                $pdo->rollBack();
                jsonResponse(['status' => 'error', 'message' => 'Request tidak ditemukan atau sudah diproses'], 404);
            }

            // Approve request
            $pdo->prepare("UPDATE topup_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
                ->execute([$_SESSION['admin_id'], $id]);

            // Tambah saldo
            $pdo->prepare("UPDATE users SET saldo = saldo + ? WHERE id = ?")
                ->execute([$request['jumlah'], $request['user_id']]);

            // Catat transaksi
            $orderId = generateOrderId('APR');
            $metode = '';
            if ($request['payment_method_id']) {
                $pmStmt = $pdo->prepare("SELECT nama FROM payment_methods WHERE id = ?");
                $pmStmt->execute([$request['payment_method_id']]);
                $pm = $pmStmt->fetch();
                $metode = $pm ? $pm['nama'] : 'Unknown';
            }

            $pdo->prepare("INSERT INTO transactions (user_id, order_id, jenis, jumlah, metode_bayar, gateway, status, keterangan, approved_by) VALUES (?, ?, 'topup', ?, ?, 'manual', 'success', ?, ?)")
                ->execute([
                    $request['user_id'], $orderId, $request['jumlah'],
                    $metode ?: 'Manual', 
                    'Top-up approved: ' . ($request['catatan'] ?? ''),
                    $_SESSION['admin_id']
                ]);

            $pdo->commit();
            logActivity("Top-up request #$id approved: " . formatRupiah($request['jumlah']) . " untuk {$request['nama']}");
            jsonResponse(['status' => 'success', 'message' => 'Top-up ' . formatRupiah($request['jumlah']) . ' untuk ' . $request['nama'] . ' berhasil di-approve']);

        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'Gagal memproses'], 500);
        }
        break;

    case 'reject':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE topup_requests SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'")
            ->execute([$_SESSION['admin_id'], $id]);
        
        logActivity("Top-up request #$id rejected");
        jsonResponse(['status' => 'success', 'message' => 'Request top-up ditolak']);
        break;

    case 'request_wijayapay':
        $uid = strtoupper(trim($_POST['rfid_uid'] ?? ''));
        $nominal = (int)($_POST['nominal'] ?? 0);
        $codePayment = strtoupper(trim($_POST['code_payment'] ?? 'QRIS'));

        $allowedPayments = [
            'QRIS', 'BCAVA', 'BNIVA', 'BRIVA', 'BSIVA', 'MANDIRIVA', 
            'MAYBANKVA', 'PERMATAVA', 'CIMBVA', 'DANAMONVA', 'MUAMALATVA', 
            'SINARMASVA', 'OCBCVA', 'ALFAMART', 'INDOMARET'
        ];
        if (!in_array($codePayment, $allowedPayments)) {
            $codePayment = 'QRIS';
        }

        if (empty($uid) || $nominal <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'UID dan nominal wajib diisi'], 400);
        }

        // Cek kartu aktif
        $stmt = $pdo->prepare("SELECT id, nama, status FROM users WHERE rfid_uid = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(['status' => 'error', 'message' => 'Kartu tidak terdaftar'], 404);
        }
        if ($user['status'] !== 'active') {
            jsonResponse(['status' => 'error', 'message' => 'Kartu sedang diblokir/tidak aktif'], 403);
        }

        // Validasi nominal min/max
        $minTopup = (int)getSetting('min_topup', MIN_TOPUP);
        $maxTopup = (int)getSetting('max_topup', MAX_TOPUP);
        if ($nominal < $minTopup || $nominal > $maxTopup) {
            jsonResponse(['status' => 'error', 'message' => 'Nominal top-up minimal ' . formatRupiah($minTopup) . ' dan maksimal ' . formatRupiah($maxTopup)], 400);
        }

        // Generate Order ID
        $orderId = generateOrderId('WJP');

        // Rakit data request untuk WijayaPay
        $merchantCode = WIJAYAPAY_MERCHANT_CODE;
        $apiKey = WIJAYAPAY_API_KEY;
        $refId = $orderId;

        // Hitung Signature
        $signature = md5($merchantCode . $apiKey . $refId);

        // Kirim request ke WijayaPay API
        $url = WIJAYAPAY_IS_PRODUCTION 
            ? 'https://wijayapay.com/api/transaction/create'
            : 'https://sandbox.wijayapay.com/api/transaction/create';

        $postData = [
            'code_merchant' => $merchantCode,
            'api_key' => $apiKey,
            'ref_id' => $refId,
            'code_payment' => $codePayment,
            'nominal' => $nominal
        ];

        // Jalankan POST dengan cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData)); // x-www-form-urlencoded
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'X-Signature: ' . $signature,
            'User-Agent: WijayaPay-PHP/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("WijayaPay response: " . $response);

        if ($curlError) {
            jsonResponse(['status' => 'error', 'message' => 'Gagal terhubung ke WijayaPay: ' . $curlError], 500);
        }

        $resJson = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['status' => 'error', 'message' => 'Response dari WijayaPay tidak valid: ' . $response], 500);
        }

        // Cek status response dari WijayaPay
        $isSuccess = true;
        if (isset($resJson['success']) && $resJson['success'] === false) {
            $isSuccess = false;
        }
        if (isset($resJson['status']) && ($resJson['status'] === false || $resJson['status'] === 'error')) {
            $isSuccess = false;
        }

        if (!$isSuccess) {
            $msg = $resJson['message'] ?? $resJson['msg'] ?? 'Gagal membuat transaksi di WijayaPay';
            jsonResponse(['status' => 'error', 'message' => $msg], 400);
        }

        // Ambil data pembayaran dari response
        $qrData = $resJson['data']['qr_string'] ?? $resJson['data']['qr_data'] ?? $resJson['qr_data'] ?? '';
        $paymentUrl = $resJson['data']['qr_image'] ?? $resJson['data']['payment_url'] ?? $resJson['payment_url'] ?? '';
        $nomorVa = $resJson['data']['nomor_va'] ?? '';
        $nomorPembayaran = $resJson['data']['nomor_pembayaran'] ?? '';
        $payMethodName = $resJson['data']['payment_name'] ?? $resJson['data']['payment_method'] ?? $codePayment;
        $tutorial = $resJson['data']['tutorial_pembayaran'] ?? '';
        $trxRef = $resJson['data']['trx_reference'] ?? $resJson['trx_reference'] ?? '';

        if (empty($qrData) && empty($paymentUrl) && empty($nomorVa) && empty($nomorPembayaran)) {
            logActivity("WijayaPay unexpected response: " . $response);
            jsonResponse(['status' => 'error', 'message' => 'Respons WijayaPay tidak menyertakan instruksi pembayaran yang valid'], 500);
        }

        try {
            // Simpan transaksi di database kita dengan status pending
            $pdo->prepare("INSERT INTO transactions (user_id, order_id, jenis, jumlah, metode_bayar, gateway, gateway_ref, status, keterangan, raw_response) VALUES (?, ?, 'topup', ?, ?, 'wijayapay', ?, 'pending', ?, ?)")
                ->execute([
                    $user['id'], 
                    $orderId, 
                    $nominal, 
                    $payMethodName,
                    $trxRef ?: null, 
                    "Top-up " . $payMethodName . " WijayaPay",
                    $response
                ]);

            jsonResponse([
                'status' => 'success',
                'order_id' => $orderId,
                'payment_name' => $payMethodName,
                'qr_data' => $qrData,
                'payment_url' => $paymentUrl,
                'nomor_va' => $nomorVa,
                'nomor_pembayaran' => $nomorPembayaran,
                'tutorial' => $tutorial,
                'nominal' => $nominal
            ]);

        } catch (Exception $e) {
            jsonResponse(['status' => 'error', 'message' => 'Gagal mencatat transaksi: ' . $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Action tidak valid'], 400);
}
