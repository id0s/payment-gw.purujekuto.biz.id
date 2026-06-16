<?php
/**
 * ============================================
 * RFID Payment Gateway - Konfigurasi Utama
 * ============================================
 * Self-hosted, open-source payment gateway
 * untuk sistem pembayaran RFID closed-loop.
 */

// ── Database ───────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'rfid_payment');
define('DB_USER', 'payment-gw');
define('DB_PASS', '1');
define('DB_CHARSET', 'utf8mb4');

// ── Aplikasi ───────────────────────────────────
define('APP_NAME', 'RFID Payment Gateway');
define('APP_URL', 'https://payment-gw.purujekuto.biz.id');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Jakarta');
define('APP_ROOT', __DIR__);

// ── Keamanan ───────────────────────────────────
// Token untuk autentikasi API dari hardware RFID reader
define('API_TOKEN', 'rfid-hw-token-change-me');

// Secret untuk validasi webhook dari payment gateway eksternal (opsional)
define('WEBHOOK_SECRET', 'rfid-pgw-secret-' . md5('purujekuto2026'));

// ── WijayaPay ──────────────────────────────────
define('WIJAYAPAY_MERCHANT_CODE', 'WP6a2c2e6435f68');
define('WIJAYAPAY_API_KEY', '5d5aef9bfa3556f3453cf7d0e6');
define('WIJAYAPAY_IS_PRODUCTION', true);

// ── Pengaturan Default ─────────────────────────
define('DEFAULT_TAP_PRICE', 10000); // Harga default per tap (Rp)
define('MIN_TOPUP', 10000);         // Minimum top-up (Rp)
define('MAX_TOPUP', 5000000);       // Maksimum top-up (Rp)

// ── Timezone ───────────────────────────────────
date_default_timezone_set(APP_TIMEZONE);

// ── Session ────────────────────────────────────
if (!defined('APPPATH') && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Koneksi Database (PDO Singleton) ───────────
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']));
        }
    }
    return $pdo;
}

// ── Helper: JSON Response ──────────────────────
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Helper: Format Rupiah ──────────────────────
function formatRupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// ── Helper: Cek Login Admin ────────────────────
function requireLogin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ' . APP_URL . '/pages/login.php');
        exit;
    }
}

// ── Helper: Cek API Token ──────────────────────
function requireApiToken() {
    $headers = getallheaders();
    $token = $headers['X-Api-Token'] ?? $headers['x-api-token'] ?? $_GET['token'] ?? '';
    if ($token !== API_TOKEN) {
        jsonResponse(['status' => 'error', 'message' => 'Token tidak valid'], 401);
    }
}

// ── Helper: Generate Order ID ──────────────────
function generateOrderId($prefix = 'TRX') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

// ── Helper: Log Aktivitas ──────────────────────
function logActivity($message) {
    $logDir = APP_ROOT . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/activity_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (is_dir($logDir)) {
        @file_put_contents($logFile, "[$timestamp] [$ip] $message\n", FILE_APPEND);
    }
}

// ── Helper: Sanitize Input ─────────────────────
if (! function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// ── Helper: Get Setting dari DB ────────────────
function getSetting($key, $default = null) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}
