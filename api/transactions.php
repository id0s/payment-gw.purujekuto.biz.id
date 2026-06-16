<?php
/**
 * RFID Payment Gateway - Transactions API
 * GET action=list → List transaksi dengan filter
 */
require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Hanya action selain check_status yang butuh login admin
if ($action !== 'check_status') {
    requireLogin();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$action = $_GET['action'] ?? '';
$pdo = getDB();

switch ($action) {
    case 'list':
        $search = $_GET['search'] ?? '';
        $jenis = $_GET['jenis'] ?? '';
        $status = $_GET['status'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT t.*, u.nama, u.rfid_uid 
                FROM transactions t 
                JOIN users u ON t.user_id = u.id 
                WHERE 1=1";
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

        // Count
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Data
        $sql .= " ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        jsonResponse([
            'status' => 'success',
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]);
        break;

    case 'check_status':
        $orderId = trim($_GET['order_id'] ?? $_POST['order_id'] ?? '');
        if (empty($orderId)) {
            jsonResponse(['status' => 'error', 'message' => 'Order ID wajib diisi'], 400);
        }

        $stmt = $pdo->prepare("SELECT status, jumlah FROM transactions WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $trx = $stmt->fetch();

        if (!$trx) {
            jsonResponse(['status' => 'error', 'message' => 'Transaksi tidak ditemukan'], 404);
        }

        jsonResponse([
            'status' => 'success',
            'trx_status' => $trx['status'],
            'amount' => (float)$trx['jumlah']
        ]);
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Action tidak valid'], 400);
}
