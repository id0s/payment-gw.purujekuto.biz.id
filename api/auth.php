<?php
/**
 * RFID Payment Gateway - Auth API
 * Handles login/logout
 */
require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'logout':
        logActivity("Admin '{$_SESSION['admin_username']}' logout");
        session_destroy();
        header('Location: ../pages/login.php');
        exit;
        break;

    case 'change_password':
        requireLogin();
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            jsonResponse(['status' => 'error', 'message' => 'Semua field harus diisi'], 400);
        }

        if ($newPass !== $confirmPass) {
            jsonResponse(['status' => 'error', 'message' => 'Password baru tidak cocok'], 400);
        }

        if (strlen($newPass) < 6) {
            jsonResponse(['status' => 'error', 'message' => 'Password minimal 6 karakter'], 400);
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!password_verify($currentPass, $admin['password'])) {
            jsonResponse(['status' => 'error', 'message' => 'Password lama salah'], 400);
        }

        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")->execute([$hashed, $_SESSION['admin_id']]);
        
        logActivity("Admin '{$_SESSION['admin_username']}' mengubah password");
        jsonResponse(['status' => 'success', 'message' => 'Password berhasil diubah']);
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Action tidak valid'], 400);
}
