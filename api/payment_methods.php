<?php
/**
 * RFID Payment Gateway - Payment Methods API
 * CRUD metode pembayaran (bank, e-wallet, QRIS)
 * 
 * POST action=create  → Tambah metode baru
 * POST action=update  → Update metode
 * POST action=delete  → Hapus metode
 * POST action=toggle  → Aktifkan/nonaktifkan
 * GET  action=list    → List semua metode
 */
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDB();

switch ($action) {

    case 'create':
        $tipe = $_POST['tipe'] ?? '';
        $nama = trim($_POST['nama'] ?? '');
        $kode = trim($_POST['kode'] ?? '');
        $nomorAkun = trim($_POST['nomor_akun'] ?? '');
        $namaPemilik = trim($_POST['nama_pemilik'] ?? '');
        $instruksi = trim($_POST['instruksi'] ?? '');

        if (empty($tipe) || empty($nama) || empty($nomorAkun) || empty($namaPemilik)) {
            jsonResponse(['status' => 'error', 'message' => 'Data tidak lengkap'], 400);
        }

        $pdo->prepare("INSERT INTO payment_methods (tipe, nama, kode, nomor_akun, nama_pemilik, instruksi) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$tipe, $nama, $kode ?: null, $nomorAkun, $namaPemilik, $instruksi]);

        logActivity("Metode pembayaran '$nama' ditambahkan");
        jsonResponse(['status' => 'success', 'message' => "$nama berhasil ditambahkan"]);
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $tipe = $_POST['tipe'] ?? '';
        $nama = trim($_POST['nama'] ?? '');
        $kode = trim($_POST['kode'] ?? '');
        $nomorAkun = trim($_POST['nomor_akun'] ?? '');
        $namaPemilik = trim($_POST['nama_pemilik'] ?? '');
        $instruksi = trim($_POST['instruksi'] ?? '');

        if (!$id || empty($nama) || empty($nomorAkun)) {
            jsonResponse(['status' => 'error', 'message' => 'Data tidak lengkap'], 400);
        }

        $pdo->prepare("UPDATE payment_methods SET tipe=?, nama=?, kode=?, nomor_akun=?, nama_pemilik=?, instruksi=? WHERE id=?")
            ->execute([$tipe, $nama, $kode ?: null, $nomorAkun, $namaPemilik, $instruksi, $id]);

        logActivity("Metode pembayaran ID $id diupdate");
        jsonResponse(['status' => 'success', 'message' => "$nama berhasil diupdate"]);
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM payment_methods WHERE id = ?")->execute([$id]);
        logActivity("Metode pembayaran ID $id dihapus");
        jsonResponse(['status' => 'success', 'message' => 'Metode pembayaran berhasil dihapus']);
        break;

    case 'toggle':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE payment_methods SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        logActivity("Metode pembayaran ID $id di-toggle");
        jsonResponse(['status' => 'success', 'message' => 'Status berhasil diubah']);
        break;

    case 'list':
        $methods = $pdo->query("SELECT * FROM payment_methods ORDER BY urutan ASC, nama ASC")->fetchAll();
        jsonResponse(['status' => 'success', 'data' => $methods]);
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Action tidak valid'], 400);
}
