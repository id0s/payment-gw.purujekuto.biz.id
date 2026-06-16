<?php
/**
 * Reset admin password - HAPUS FILE INI SETELAH DIGUNAKAN!
 */
require_once __DIR__ . '/config.php';

$newPassword = 'admin123';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $pdo = getDB();
    $pdo->prepare("UPDATE admins SET password = ? WHERE username = 'admin'")
        ->execute([$hash]);
    echo "✅ Password admin berhasil direset ke: $newPassword\n";
    echo "Hash: $hash\n";
    echo "\n⚠️ HAPUS FILE INI SETELAH SELESAI!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
